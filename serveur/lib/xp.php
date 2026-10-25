<?php
// Moteur d'XP du lot 4.1 : fonctions pures, aucun accès base. L'XP n'est jamais stockée, elle est rejouée
// à partir des actions non annulées (SPEC § 18.4). Les jours sont ceux de Paris.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';

// Barème C de REGULATION-XP.md § 5. Le miroir côté iPhone est REGLES dans site/js/lib/xp.js.
const JR_XP_REGLES = [
    'carte' => 30,
    'bonus_urgent' => 20,
    'bonus_echeance' => 10,
    'carte_eclair' => 5,
    'eclair_secondes' => 600,
    'plafond_eclairs' => 2,
    'etape' => 5,
    'plafond_etapes' => 10,
    'pas_niveau' => 160,
    'grades' => ['stagiaire' => 0, 'assistant' => 1500, 'bras_droit' => 6000, 'majordome' => 22000],
];

// Les 8 premiers caractères hexadécimaux d'un identifiant Trello sont l'horodatage Unix de création
function xp_creation_carte(string $carte_id): ?DateTimeImmutable
{
    if (preg_match('/^[0-9a-f]{24}$/i', $carte_id) !== 1) {
        return null;
    }
    return new DateTimeImmutable('@' . (int) hexdec(substr($carte_id, 0, 8)));
}

// Échéance à 18 h 00 Paris (posée sans heure) : comparée au jour ; sinon à la minute
function xp_echeance_tenue(mixed $echeance, DateTimeImmutable $quand): bool
{
    if (!is_string($echeance)) {
        return false;
    }
    $limite = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $echeance);
    if ($limite === false) {
        return false;
    }
    $limite_paris = $limite->setTimezone(paris());
    if ($limite_paris->format('H:i:s') === '18:00:00') {
        return $quand->setTimezone(paris())->format('Y-m-d') <= $limite_paris->format('Y-m-d');
    }
    return intdiv($quand->getTimestamp(), 60) <= intdiv($limite->getTimestamp(), 60);
}

// Carte éclair : terminée moins de eclair_secondes après sa création (durée négative comprise)
function xp_est_eclair(string $carte_id, DateTimeImmutable $quand, array $regles = JR_XP_REGLES): bool
{
    $creation = xp_creation_carte($carte_id);
    return $creation !== null && $quand->getTimestamp() - $creation->getTimestamp() < $regles['eclair_secondes'];
}

// Gain d'une carte terminée à $quand. Une carte éclair (durée négative comprise) rapporte la somme fixe, sans bonus.
function xp_gain_carte(string $carte_id, array $donnees, DateTimeImmutable $quand, array $regles = JR_XP_REGLES): int
{
    if (xp_est_eclair($carte_id, $quand, $regles)) {
        return $regles['carte_eclair'];
    }
    $gain = $regles['carte'];
    if (($donnees['urgent'] ?? false) === true) {
        $gain += $regles['bonus_urgent'];
    }
    if (xp_echeance_tenue($donnees['echeance'] ?? null, $quand)) {
        $gain += $regles['bonus_echeance'];
    }
    return $gain;
}

function xp_seuil_niveau(int $n, array $regles = JR_XP_REGLES): int
{
    return $n <= 1 ? 0 : intdiv($regles['pas_niveau'] * $n * ($n - 1), 2);
}

function xp_progression(int $xp, array $regles = JR_XP_REGLES): array
{
    $xp = max(0, $xp);
    $niveau = max(1, (int) floor((1 + sqrt(1 + 8 * $xp / $regles['pas_niveau'])) / 2));
    while ($niveau > 1 && xp_seuil_niveau($niveau, $regles) > $xp) {
        $niveau--;
    }
    while (xp_seuil_niveau($niveau + 1, $regles) <= $xp) {
        $niveau++;
    }
    $seuil = xp_seuil_niveau($niveau, $regles);
    return ['niveau' => $niveau, 'dans_niveau' => $xp - $seuil, 'taille_niveau' => xp_seuil_niveau($niveau + 1, $regles) - $seuil];
}

function xp_grade(int $total, array $regles = JR_XP_REGLES): array
{
    $grades = $regles['grades'];
    asort($grades);
    $noms = array_keys($grades);
    $rang = 0;
    foreach ($noms as $i => $nom) {
        if ($grades[$nom] <= $total) {
            $rang = $i;
        }
    }
    $suivant = $noms[$rang + 1] ?? null;
    return ['grade' => $noms[$rang], 'seuil' => $grades[$noms[$rang]], 'suivant' => $suivant, 'seuil_suivant' => $suivant === null ? null : $grades[$suivant]];
}

// Rejoue les actions non annulées, triées par quand puis id (voir SPEC § 18.4)
function xp_rejouer(array $actions, DateTimeImmutable $maintenant, array $regles = JR_XP_REGLES): array
{
    $paris = paris();
    $domaines = array_fill_keys(JR_DOMAINES, 0);
    $niveau_max = array_fill_keys(JR_DOMAINES, 1);
    $total = 0;
    $gains = [];
    $en_cours = [];
    $jours = [];
    $payes = [];
    $evenements = [];
    $rang_grade_max = 0;
    asort($regles['grades']);
    $noms_grades = array_keys($regles['grades']);

    foreach ($actions as $action) {
        $id = $action['id'];
        $quand = $action['quand'];
        $jour = $quand->setTimezone($paris)->format('Y-m-d');
        $cle = match ($action['type']) {
            'carte_terminee', 'carte_rouverte' => 't|' . $action['carte_id'],
            'etape_cochee', 'etape_decochee' => 'e|' . $action['carte_id'] . '|' . ($action['element_id'] ?? ''),
            default => null,
        };
        $gain = 0;
        if ($action['type'] === 'carte_terminee' || $action['type'] === 'etape_cochee') {
            if (!isset($en_cours[$cle])) {
                $genre = $action['type'] === 'etape_cochee' ? 'etapes' : (xp_est_eclair($action['carte_id'], $quand, $regles) ? 'eclairs' : null);
                $plafond = $genre === null ? null : $regles['plafond_' . $genre];
                $paye = $plafond === null || ($payes[$jour][$genre] ?? 0) < $plafond;
                if ($paye) {
                    $gain = $action['type'] === 'carte_terminee'
                        ? xp_gain_carte($action['carte_id'], $action['donnees'], $quand, $regles)
                        : $regles['etape'];
                    if ($genre !== null) {
                        $payes[$jour][$genre] = ($payes[$jour][$genre] ?? 0) + 1;
                    }
                    $domaines[$action['domaine']] += $gain;
                    $total += $gain;
                    $jours[$jour][$id] = $quand;
                }
                $en_cours[$cle] = ['gain' => $gain, 'domaine' => $action['domaine'], 'id' => $id, 'jour' => $jour, 'genre' => $paye ? $genre : null];
            }
        } elseif ($cle !== null && isset($en_cours[$cle])) {
            $acquis = $en_cours[$cle];
            $gain = -$acquis['gain'];
            $domaines[$acquis['domaine']] -= $acquis['gain'];
            $total -= $acquis['gain'];
            unset($en_cours[$cle]);
            if ($acquis['genre'] !== null) {
                $payes[$acquis['jour']][$acquis['genre']]--;
            }
            if ($acquis['jour'] === $jour && isset($jours[$jour])) {
                unset($jours[$jour][$acquis['id']]);
                if ($jours[$jour] === []) {
                    unset($jours[$jour]);
                }
            }
        }
        $gains[$id] = $gain;

        if ($gain > 0) {
            $instant = iso_paris($quand);
            $domaine = $action['domaine'];
            $niveau = xp_progression($domaines[$domaine], $regles)['niveau'];
            for ($n = $niveau_max[$domaine] + 1; $n <= $niveau; $n++) {
                $evenements[] = [$quand->getTimestamp(), ['cle' => "niveau:$domaine:$n", 'type' => 'niveau', 'domaine' => $domaine, 'niveau' => $n, 'date' => $instant, 'emis_le' => $instant]];
            }
            $niveau_max[$domaine] = max($niveau_max[$domaine], $niveau);
            $rang = array_search(xp_grade($total, $regles)['grade'], $noms_grades, true);
            for ($r = $rang_grade_max + 1; $r <= $rang; $r++) {
                $evenements[] = [$quand->getTimestamp(), ['cle' => 'grade:' . $noms_grades[$r], 'type' => 'grade', 'grade' => $noms_grades[$r], 'date' => $instant, 'emis_le' => $instant]];
            }
            $rang_grade_max = max($rang_grade_max, $rang);
        }
    }

    ksort($jours);
    $aujourdhui = $maintenant->setTimezone($paris)->format('Y-m-d');
    $serie = xp_serie($jours, $aujourdhui);
    foreach ($serie['jokers'] as [$couvert, $emis]) {
        $evenements[] = [$emis->getTimestamp(), ['cle' => "joker:$couvert", 'type' => 'joker', 'date' => $couvert, 'emis_le' => iso_paris($emis)]];
    }
    usort($evenements, fn(array $a, array $b): int => $a[0] <=> $b[0]);

    return [
        'total' => $total,
        'domaines' => $domaines,
        'gains' => $gains,
        'jours' => array_keys($jours),
        'evenements' => array_column($evenements, 1),
        'serie' => $serie['serie'],
        'aujourdhui' => $aujourdhui,
        'paye_aujourdhui' => ['etapes' => $payes[$aujourdhui]['etapes'] ?? 0, 'eclairs' => $payes[$aujourdhui]['eclairs'] ?? 0],
    ];
}

// Série et jokers. $jours : jour compté => [id d'action => instant], trié. Rend la série et les jokers dépensés
// [jour couvert, instant de la première action comptée du jour suivant].
function xp_serie(array $jours, string $aujourdhui): array
{
    $utc = new DateTimeZone('UTC');
    $semaine = fn(string $jour): string => (new DateTimeImmutable($jour, $utc))->format('o-W');
    $serie = 0;
    $en_attente = null;
    $pris = [];
    $jokers = [];
    if ($jours !== []) {
        $fin = new DateTimeImmutable($aujourdhui, $utc);
        for ($d = new DateTimeImmutable(array_key_first($jours), $utc); $d <= $fin; $d = $d->modify('+1 day')) {
            $jour = $d->format('Y-m-d');
            if (isset($jours[$jour])) {
                if ($en_attente !== null) {
                    $pris[$semaine($en_attente)] = true;
                    $jokers[] = [$en_attente, reset($jours[$jour])];
                    $en_attente = null;
                }
                $serie++;
            } elseif ($jour === $aujourdhui) {
                break;
            } elseif ($en_attente !== null) {
                $serie = 0;
                $en_attente = null;
            } elseif ($serie > 0) {
                if (isset($pris[$semaine($jour)])) {
                    $serie = 0;
                } else {
                    $en_attente = $jour;
                }
            }
        }
    }
    return [
        'serie' => [
            'jours' => $serie,
            'joker_libre' => !isset($pris[$semaine($aujourdhui)]),
            'dernier_joker' => $jokers === [] ? null : end($jokers)[0],
            'en_jeu' => $en_attente !== null,
        ],
        'jokers' => $jokers,
    ];
}

// Objet public pour l'iPhone : tous les niveaux et grades, les jokers des 30 derniers jours
function xp_objet(array $rejeu, array $regles = JR_XP_REGLES): array
{
    $grade = xp_grade($rejeu['total'], $regles);
    $domaines = [];
    foreach ($rejeu['domaines'] as $domaine => $xp) {
        $domaines[$domaine] = ['xp' => $xp] + xp_progression($xp, $regles);
    }
    $depuis = (new DateTimeImmutable($rejeu['aujourdhui'], new DateTimeZone('UTC')))->modify('-30 days')->format('Y-m-d');
    return [
        'total' => $rejeu['total'],
        'grade' => $grade['grade'],
        'seuil_grade' => $grade['seuil'],
        'grade_suivant' => $grade['suivant'],
        'seuil_suivant' => $grade['seuil_suivant'],
        'domaines' => $domaines,
        'serie' => $rejeu['serie'],
        'evenements' => array_values(array_filter($rejeu['evenements'], fn(array $e): bool => $e['type'] !== 'joker' || $e['date'] >= $depuis)),
        'aujourdhui_paye' => $rejeu['paye_aujourdhui'],
    ];
}
