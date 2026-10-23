<?php
// Les notifications à préparer (SPEC § 15.4) : cours, échéances, médians, brief du matin, rappels manuels.
// Tout est pur, sauf reglages_rappels() qui lit la base.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/brief.php';
require_once __DIR__ . '/formats.php';
require_once __DIR__ . '/reglages.php';

const JR_RAPPELS_AVANT = 86400;  // les notifications des 24 h à venir
const JR_RAPPELS_APRES = 43200;  // et celles des 12 dernières heures, pas encore périmées (la plus longue péremption)

// Valeurs par défaut (CONTENU.md § 3, Réglages). Brief : jours ISO, 1 = lundi ; aucun jour = pas de brief.
const JR_REGLAGES_RAPPELS = [
    'brief' => ['heure' => '07:30', 'jours' => [1, 2, 3, 4, 5, 6, 7]],
    'cours' => ['actif' => true, 'minutes' => 15],
    'echeances' => ['actif' => true, 'heure' => '20:00'],
    'medians' => ['actif' => true, 'jours' => [7, 3, 1], 'heure' => '09:00'],
];

// Chaque valeur abîmée reprend sa valeur par défaut : un réglage illisible ne doit jamais arrêter le planificateur
function reglages_rappels_valides(mixed $stockes): array
{
    $heure = fn(mixed $v): bool => is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) === 1;
    $liste = fn(int $min, int $max): Closure => fn(mixed $v): bool => is_array($v) && array_is_list($v)
        && array_filter($v, fn(mixed $n): bool => !is_int($n) || $n < $min || $n > $max) === [];
    $regles = [
        'brief' => ['heure' => $heure, 'jours' => $liste(1, 7)],
        'cours' => ['actif' => 'is_bool', 'minutes' => fn(mixed $v): bool => is_int($v) && $v >= 1 && $v <= 120],
        'echeances' => ['actif' => 'is_bool', 'heure' => $heure],
        'medians' => ['actif' => 'is_bool', 'jours' => $liste(1, 30), 'heure' => $heure],
    ];
    $reglages = JR_REGLAGES_RAPPELS;
    foreach ($regles as $partie => $champs) {
        $stockee = is_array($stockes) && is_array($stockes[$partie] ?? null) ? $stockes[$partie] : [];
        foreach ($champs as $champ => $valide) {
            $valeur = $stockee[$champ] ?? null;
            if ($valeur !== null && $valide($valeur)) {
                $reglages[$partie][$champ] = is_array($valeur) ? array_values(array_unique($valeur)) : $valeur;
            }
        }
    }
    return $reglages;
}

// Validation stricte pour l'écriture : rend les réglages normalisés (jours triés), ou null au moindre écart
function reglages_rappels_stricts(mixed $recus): ?array
{
    $heure = fn(mixed $v): bool => is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) === 1;
    $jours = fn(array $permis): Closure => fn(mixed $v): bool => is_array($v) && array_is_list($v) && $v !== []
        && count(array_unique($v)) === count($v)
        && array_filter($v, fn(mixed $n): bool => !is_int($n) || !in_array($n, $permis, true)) === [];
    $regles = [
        'brief' => ['heure' => $heure, 'jours' => $jours([1, 2, 3, 4, 5, 6, 7])],
        'cours' => ['actif' => 'is_bool', 'minutes' => fn(mixed $v): bool => in_array($v, [5, 10, 15, 30, 60], true)],
        'echeances' => ['actif' => 'is_bool', 'heure' => $heure],
        'medians' => ['actif' => 'is_bool', 'jours' => $jours([1, 3, 7, 14]), 'heure' => $heure],
    ];
    if (!is_array($recus) || array_keys($recus) !== array_keys(array_intersect_key($recus, $regles)) || count($recus) !== count($regles)) {
        return null;
    }
    $propres = [];
    foreach ($regles as $partie => $champs) {
        $recue = $recus[$partie];
        if (!is_array($recue) || count($recue) !== count($champs)) {
            return null;
        }
        foreach ($champs as $champ => $valide) {
            if (!array_key_exists($champ, $recue) || !$valide($recue[$champ])) {
                return null;
            }
            $valeur = $recue[$champ];
            if (is_array($valeur)) {
                sort($valeur);
            }
            $propres[$partie][$champ] = $valeur;
        }
    }
    return $propres;
}

function reglages_rappels(): array
{
    return reglages_rappels_valides(reglage_lire('rappels'));
}

function rappel(string $cle, string $type, DateTimeImmutable $prevu_pour, string $titre, string $texte, string $url): array
{
    return [
        'cle' => $cle,
        'type' => $type,
        'prevu_pour' => $prevu_pour->setTimezone(new DateTimeZone('UTC')),
        'titre' => couper($titre, 120),
        'texte' => couper($texte, 400),
        'url' => $url,
    ];
}

// À Paris : le jour de $moment moins $jours_avant jours, à l'heure HH:MM (changement d'heure compris)
function a_heure(DateTimeImmutable $moment, string $heure, int $jours_avant = 0): DateTimeImmutable
{
    [$h, $m] = array_map('intval', explode(':', $heure));
    return $moment->setTimezone(paris())->setTime(0, 0)->modify("-$jours_avant days")->setTime($h, $m);
}

// Les créneaux de l'agenda UTC (pas l'agenda principal), $minutes avant le début. Rien pour un cours commencé.
function rappels_cours(array $jours, array $reglage, DateTimeImmutable $maintenant): array
{
    $liste = [];
    $minutes = $reglage['minutes'];
    foreach ($jours as $jour) {
        foreach ($jour['evenements'] ?? [] as $e) {
            if ($e['source'] !== 'utc' || $e['journee']) {
                continue;
            }
            $debut = new DateTimeImmutable($e['debut']);
            if ($debut <= $maintenant) {
                continue;
            }
            $nom = $e['uv'] !== null ? "{$e['uv']} · {$e['type']}" : $e['titre'];
            $texte = $e['salle'] !== null ? "Salle {$e['salle']}." : 'De ' . heure_lisible($debut) . ' à ' . heure_lisible(new DateTimeImmutable($e['fin'])) . '.';
            $cle = 'cours:' . $e['debut'] . ':' . substr(hash('sha256', $e['titre']), 0, 12);
            $liste[] = rappel($cle, 'cours', $debut->modify("-$minutes minutes"), "$nom dans $minutes min", $texte, '/#/aujourdhui');
        }
    }
    return $liste;
}

// Cartes ouvertes avec une échéance pas encore faite, la veille à l'heure réglée
function rappels_echeances(array $cartes, array $reglage): array
{
    $liste = [];
    foreach ($cartes as $c) {
        if ($c['echeance'] === null || $c['colonne'] === 'fait' || $c['echeance_faite']) {
            continue;
        }
        $echeance = new DateTimeImmutable($c['echeance']);
        $elements = array_merge([], ...array_map(fn(array $l): array => $l['elements'], $c['checklists']));
        $reste = count(array_filter($elements, fn(array $e): bool => !$e['coche']));
        $etapes = match (true) {
            $elements === [] => '',
            $reste === 0 => ' Toutes les étapes sont cochées.',
            default => ' Il reste ' . pluriel($reste, 'étape') . ' sur ' . count($elements) . '.',
        };
        $liste[] = rappel("echeance:{$c['id']}:{$c['echeance']}", 'echeance', a_heure($echeance, $reglage['heure'], 1),
            "Demain : {$c['titre']}", 'Échéance à ' . heure_lisible($echeance) . '.' . $etapes, "/#/carte/{$c['id']}");
    }
    return $liste;
}

// Événements « Médian … » de l'agenda principal (repérés par creneaux.php), avec une heure, à J-n
function rappels_medians(array $jours, array $reglage, DateTimeImmutable $maintenant): array
{
    $liste = [];
    foreach ($jours as $jour) {
        foreach ($jour['evenements'] ?? [] as $e) {
            if (empty($e['median']) || $e['journee']) {
                continue;
            }
            $debut = new DateTimeImmutable($e['debut']);
            if ($debut <= $maintenant) {
                continue;
            }
            $heure = heure_lisible($debut);
            foreach ($reglage['jours'] as $n) {
                [$titre, $texte] = match (true) {
                    $n === 1 => ["{$e['titre']} demain", "$heure. Dors bien ce soir."],
                    $n >= 7 => ["{$e['titre']} dans $n jours", jour_nom($debut) . ' ' . $debut->setTimezone(paris())->format('d/m') . " à $heure."],
                    default => ["{$e['titre']} dans $n jours", jour_nom($debut) . " à $heure."],
                };
                $liste[] = rappel("median:{$e['debut']}:J$n", 'median', a_heure($debut, $reglage['heure'], $n), $titre, $texte, '/#/agenda');
            }
        }
    }
    return $liste;
}

// Le brief d'aujourd'hui et celui de demain, les jours choisis
function rappels_brief(array $cartes, array $jours, array $reglage, DateTimeImmutable $maintenant, int $mails_non_lus = 0): array
{
    $liste = [];
    $aujourdhui = $maintenant->setTimezone(paris())->setTime(0, 0);
    foreach ([$aujourdhui, $aujourdhui->modify('+1 day')] as $jour) {
        if (!in_array((int) $jour->format('N'), $reglage['jours'], true)) {
            continue;
        }
        $date = $jour->format('Y-m-d');
        $brief = brief_du_matin($date, $cartes, $jours, $mails_non_lus);
        $liste[] = rappel("brief:$date", 'brief', a_heure($jour, $reglage['heure']), $brief['titre'], $brief['texte'], '/#/aujourdhui');
    }
    return $liste;
}

function rappels_manuels(array $rappels): array
{
    $liste = [];
    foreach ($rappels as $r) {
        $cree_le = $r['cree_le']->setTimezone(paris());
        $quand = match (jours_entre($cree_le->format('Y-m-d'), $r['quand']->setTimezone(paris())->format('Y-m-d'))) {
            0 => "aujourd'hui",
            1 => 'hier',
            default => 'le ' . $cree_le->format('d/m'),
        };
        $liste[] = rappel("manuel:{$r['id']}", 'manuel', $r['quand'], $r['texte'], "Rappel que tu t'es mis $quand à " . heure_lisible($cree_le) . '.', '/#/aujourdhui');
    }
    return $liste;
}

// Les notifications prévues entre 12 h avant et 24 h après $maintenant, une par clé. Une source vide
// ($cartes ou $jours à []) ne donne rien pour elle.
function generer_rappels(array $cartes, array $jours, array $reglages, array $rappels, DateTimeImmutable $maintenant, int $mails_non_lus = 0): array
{
    $liste = array_merge(
        $reglages['cours']['actif'] ? rappels_cours($jours, $reglages['cours'], $maintenant) : [],
        $reglages['echeances']['actif'] ? rappels_echeances($cartes, $reglages['echeances']) : [],
        $reglages['medians']['actif'] ? rappels_medians($jours, $reglages['medians'], $maintenant) : [],
        rappels_brief($cartes, $jours, $reglages['brief'], $maintenant, $mails_non_lus),
        rappels_manuels($rappels),
    );
    $debut = $maintenant->modify('-' . JR_RAPPELS_APRES . ' seconds');
    $fin = $maintenant->modify('+' . JR_RAPPELS_AVANT . ' seconds');
    $gardes = [];
    foreach ($liste as $r) {
        if ($r['prevu_pour'] > $debut && $r['prevu_pour'] <= $fin) {
            $gardes[$r['cle']] ??= $r;
        }
    }
    return array_values($gardes);
}
