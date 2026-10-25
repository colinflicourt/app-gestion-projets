<?php
// Simulation de la régulation de l'XP (lot 4.1, M2) : aucun réseau, lit outils/donnees/historique-trello.json
// et l'emploi du temps A26. Réutilise xp_rejouer(), xp_progression() et xp_grade() avec des tables candidates.
//   php outils/simuler-xp.php            tout le rapport
//   php outils/simuler-xp.php C          un seul barème
// Écrit aussi outils/donnees/simulation-xp.json (hors git).

declare(strict_types=1);

require __DIR__ . '/../serveur/lib/xp.php';

const SIM_DEBUT = '2026-09-01';
const SIM_FIN = '2027-06-30';
const SIM_ICS = __DIR__ . '/../../../etudes/emploi-du-temps-A26-google.ics';
const SIM_HISTORIQUE = __DIR__ . '/donnees/historique-trello.json';

// Hypothèses de calendrier (le P27 n'est pas dans l'emploi du temps : estimations, à confirmer)
const SIM_EXAMENS_A26 = [['2026-10-19', '2026-10-24'], ['2026-11-02', '2026-11-07'], ['2027-01-07', '2027-01-15']];
const SIM_EXAMENS_P27 = [['2027-04-12', '2027-04-17'], ['2027-06-14', '2027-06-25']];
const SIM_VACANCES_ESTIMEES = [['2027-01-30', '2027-02-14'], ['2027-03-29', '2027-04-04'], ['2027-06-27', '2027-06-30']];
const SIM_COURS_P27_H = 16.0;

// Hypothèses sur les habitudes (tout ce qui n'est pas mesuré dans l'historique Trello)
const SIM_JOUR_H = [9.0, 9.0, 9.0, 9.0, 9.0, 7.0, 7.0];   // heures disponibles par jour (lun à dim), repas et trajets déduits
const SIM_PART_COMBAT_MAX = 0.25;                        // au plus ce quart des heures libres peut passer en combat
const SIM_DUREES = [45, 25, 60, 45, 70, 45, 45, 60, 25, 45];
const SIM_ISSUES = ['victoire', 'victoire', 'fini', 'victoire', 'victoire', 'forfait', 'victoire', 'fini', 'victoire', 'victoire'];
const SIM_PART_FINI = 0.6;                               // « J'ai fini la tâche » à 60 % de la durée
const SIM_PART_FORFAIT = 0.4;                            // forfait à 40 % de la durée
const SIM_DUREE_MOYENNE = 46.5;
const SIM_POIDS_COMBAT = [
    'normal' => ['etudes' => 0.45, 'qyris' => 0.25, 'perso' => 0.20, 'asso' => 0.10],
    'medians' => ['etudes' => 0.75, 'qyris' => 0.10, 'perso' => 0.08, 'asso' => 0.07],
];
const SIM_PROFILS = [
    'calme' => ['cartes' => 1.0, 'etapes' => 1.0, 'etapes_etudes' => 1.0, 'seances' => 2, 'journal' => 3, 'poids' => 'normal'],
    'normale' => ['cartes' => 3.0, 'etapes' => 2.0, 'etapes_etudes' => 2.0, 'seances' => 5, 'journal' => 6, 'poids' => 'normal'],
    'medians' => ['cartes' => 3.0, 'etapes' => 2.0, 'etapes_etudes' => 5.0, 'seances' => 9, 'journal' => 5, 'poids' => 'medians'],
];
// Part des cartes terminées : Urgent, finie avant l'échéance, carte éclair (une sur N, motif fixe)
const SIM_MOD_URGENT = 10;
const SIM_MOD_ECHEANCE = 7;
const SIM_MOD_ECLAIR = 10;
// Soirée de triche : 2 h, 40 min de chaque
const SIM_TRICHE_ECLAIR = 60;    // 1 carte éclair créée puis terminée toutes les 40 s
const SIM_TRICHE_ETAPES = 160;   // 1 étape ajoutée puis cochée toutes les 15 s
const SIM_TRICHE_JOURNAL = 240;  // 1 ligne de journal toutes les 10 s

// Barèmes candidats. 'regles' va tel quel à xp_rejouer(). Le reste est modélisé ici (sous-lots 4.2 et suivants).
// combat : 'tranche' = xp par tranche entière de 5 min ; 'minute' = xp par minute. plafonds : nombre de gains payés par jour.
function sim_baremes(): array
{
    $a = JR_XP_REGLES;
    $b = $a;
    $c = $a;
    $c['carte'] = 30;
    $c['bonus_urgent'] = 20;
    $c['bonus_echeance'] = 10;
    $c['carte_eclair'] = 5;
    $c['etape'] = 5;
    $c['pas_niveau'] = 160;
    $c['grades'] = ['stagiaire' => 0, 'assistant' => 1500, 'bras_droit' => 6000, 'majordome' => 22000];
    // Réglages d'essai : php outils/simuler-xp.php C pas=165 grades=1500,6000,20000 combat=0.5 rythme=1.5
    foreach (array_slice($GLOBALS['argv'] ?? [], 2) as $arg) {
        [$cle, $val] = explode('=', $arg, 2) + [1 => ''];
        if ($cle === 'pas') {
            $c['pas_niveau'] = (int) $val;
        } elseif ($cle === 'grades') {
            [$x, $y, $z] = array_map('intval', explode(',', $val));
            $c['grades'] = ['stagiaire' => 0, 'assistant' => $x, 'bras_droit' => $y, 'majordome' => $z];
        }
    }
    return [
        'A' => ['nom' => 'Barème actuel (CONTENU § 7)', 'regles' => $a, 'combat' => ['mode' => 'tranche', 'xp' => 1], 'boss' => 5, 'journal' => 5, 'plafonds' => []],
        'B' => ['nom' => 'Combat à 1 XP par minute, le reste inchangé', 'regles' => $b, 'combat' => ['mode' => 'minute', 'xp' => 1.0], 'boss' => 5, 'journal' => 5, 'plafonds' => []],
        'C' => ['nom' => 'Proposition de la régulation', 'regles' => $c, 'combat' => ['mode' => 'minute', 'xp' => 1.0], 'boss' => 10, 'journal' => 3,
            'plafonds' => ['etape' => 10, 'eclair' => 2, 'journal' => 2]],
    ];
}

// ---------- Utilitaires ----------

function sim_dt(string $jour, string $heure = '12:00:00'): DateTimeImmutable
{
    return new DateTimeImmutable("$jour $heure", paris());
}

function sim_jours(string $debut, string $fin): array
{
    $jours = [];
    for ($d = new DateTimeImmutable($debut, new DateTimeZone('UTC')); $d->format('Y-m-d') <= $fin; $d = $d->modify('+1 day')) {
        $jours[] = $d->format('Y-m-d');
    }
    return $jours;
}

function sim_lundi(string $jour): string
{
    $d = new DateTimeImmutable($jour, new DateTimeZone('UTC'));
    return $d->modify('-' . ((int) $d->format('N') - 1) . ' days')->format('Y-m-d');
}

function sim_pct(float $x): string
{
    return number_format($x * 100, 0) . ' %';
}

// ---------- Emploi du temps et calendrier ----------

function sim_lire_ics(): array
{
    $texte = file_get_contents(SIM_ICS);
    $heures = [];
    $vacances = [];
    $examens = [];
    preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $texte, $blocs);
    foreach ($blocs[1] as $bloc) {
        preg_match('/SUMMARY:(.*)/', $bloc, $s);
        preg_match('/DTSTART[^:]*:(\S+)/', $bloc, $a);
        preg_match('/DTEND[^:]*:(\S+)/', $bloc, $f);
        $titre = trim($s[1]);
        if (!str_contains($a[1], 'T')) {
            $jour = substr($a[1], 0, 4) . '-' . substr($a[1], 4, 2) . '-' . substr($a[1], 6, 2);
            if ($titre === 'Vacances universitaires') {
                $vacances[$jour] = true;
            } elseif ($titre === "Jour d'examen") {
                $examens[$jour] = true;
            }
            continue;
        }
        $debut = DateTimeImmutable::createFromFormat('Ymd\THis', $a[1]);
        $fin = DateTimeImmutable::createFromFormat('Ymd\THis', $f[1]);
        $lundi = sim_lundi($debut->format('Y-m-d'));
        $heures[$lundi] = ($heures[$lundi] ?? 0.0) + ($fin->getTimestamp() - $debut->getTimestamp()) / 3600;
    }
    return ['heures' => $heures, 'vacances' => $vacances, 'examens' => $examens];
}

function sim_dans(string $jour, array $plages): bool
{
    foreach ($plages as [$d, $f]) {
        if ($jour >= $d && $jour <= $f) {
            return true;
        }
    }
    return false;
}

// Semaines (lundi à dimanche, bornées à la période) : type, heures de cours, heures libres
function sim_semaines(array $ics): array
{
    $examens = [];
    foreach (array_merge(SIM_EXAMENS_A26, SIM_EXAMENS_P27) as [$d, $f]) {
        foreach (sim_jours(sim_lundi($d), $f) as $j) {
            $examens[sim_lundi($j)] = true;
        }
    }
    $avant_examens = [];
    foreach (array_keys($examens) as $lundi) {
        $avant_examens[(new DateTimeImmutable($lundi, new DateTimeZone('UTC')))->modify('-7 days')->format('Y-m-d')] = true;
    }
    $semaines = [];
    foreach (sim_jours(SIM_DEBUT, SIM_FIN) as $jour) {
        $semaines[sim_lundi($jour)][] = $jour;
    }
    $resultat = [];
    foreach ($semaines as $lundi => $jours) {
        $vacances = 0;
        foreach ($jours as $j) {
            if (isset($ics['vacances'][$j]) || sim_dans($j, SIM_VACANCES_ESTIMEES)) {
                $vacances++;
            }
        }
        $en_vacances = $vacances >= 5 || $vacances === count($jours);
        $type = isset($examens[$lundi]) || isset($avant_examens[$lundi]) ? 'examens' : ($en_vacances ? 'vacances' : 'normale');
        if (isset($ics['heures'][$lundi])) {
            $cours = $ics['heures'][$lundi];
        } else {
            $cours = $en_vacances ? 0.0 : SIM_COURS_P27_H;
            if ($type === 'examens' && !$en_vacances) {
                $cours = 6.0;
            }
        }
        $dispo = 0.0;
        foreach ($jours as $j) {
            $dispo += SIM_JOUR_H[(int) (new DateTimeImmutable($j, new DateTimeZone('UTC')))->format('N') - 1];
        }
        $resultat[] = [
            'lundi' => $lundi, 'jours' => $jours, 'type' => $type, 'cours_h' => $cours,
            'libre_h' => max(0.0, $dispo - $cours * count($jours) / 7),
            'source_cours' => isset($ics['heures'][$lundi]) ? 'emploi du temps' : 'estimation',
        ];
    }
    return $resultat;
}

// ---------- Historique réel ----------

function sim_reel(): array
{
    $h = json_decode((string) file_get_contents(SIM_HISTORIQUE), true, 512, JSON_THROW_ON_ERROR);
    $depuis = new DateTimeImmutable($h['depuis']);
    $jusqua = new DateTimeImmutable($h['extrait_le']);
    $semaines = ($jusqua->getTimestamp() - $depuis->getTimestamp()) / 604800;
    $cartes = [];
    foreach ($h['cartes'] as $c) {
        $cartes[$c['id']] = $c;
    }
    $par = [];
    foreach (JR_DOMAINES as $d) {
        $par[$d] = ['cartes_ouvertes' => 0, 'cartes_vues' => 0, 'terminees' => 0, 'archivees_sans_fait' => 0, 'coches' => 0, 'decoches' => 0, 'jour_max' => 0];
    }
    foreach ($h['cartes'] as $c) {
        $par[$c['domaine']]['cartes_vues']++;
        $par[$c['domaine']]['cartes_ouvertes'] += $c['ouverte'] ? 1 : 0;
    }
    $actions = [];
    $id = 0;
    $faites = [];
    $par_jour = [];
    foreach ($h['gestes'] as $g) {
        $d = $g['domaine'];
        $quand = new DateTimeImmutable($g['date']);
        $c = $cartes[$g['carte']] ?? null;
        $type = null;
        if ($g['type'] === 'updateCard' && ($g['vers'] ?? null) === 'Fait') {
            $type = 'carte_terminee';
            if (!isset($faites[$g['carte']])) {
                $faites[$g['carte']] = true;
                $par[$d]['terminees']++;
            }
        } elseif ($g['type'] === 'updateCard' && ($g['de'] ?? null) === 'Fait') {
            $type = 'carte_rouverte';
        } elseif ($g['type'] === 'updateCheckItemStateOnCard') {
            $type = $g['etat'] === 'complete' ? 'etape_cochee' : 'etape_decochee';
            $par[$d][$type === 'etape_cochee' ? 'coches' : 'decoches']++;
            if ($type === 'etape_cochee') {
                $jour = substr($g['date'], 0, 10);
                $par_jour[$d][$jour] = ($par_jour[$d][$jour] ?? 0) + 1;
                $par[$d]['jour_max'] = max($par[$d]['jour_max'], $par_jour[$d][$jour]);
            }
        } elseif ($g['type'] === 'updateCard' && ($g['archivee'] ?? false) === true && !isset($faites[$g['carte']])) {
            $par[$d]['archivees_sans_fait']++;
        }
        if ($type === null) {
            continue;
        }
        $due = $c['due'] ?? null;
        $actions[] = [
            'id' => ++$id, 'type' => $type, 'domaine' => $d, 'carte_id' => $g['carte'], 'element_id' => $g['element'] ?? null,
            'quand' => $quand,
            'donnees' => ['urgent' => $c['urgent'] ?? false, 'echeance' => $due === null ? null : (new DateTimeImmutable($due))->format('Y-m-d\TH:i:sP')],
        ];
    }
    $stats = ['semaines' => $semaines, 'par_domaine' => $par, 'actions' => $actions, 'depuis' => substr($h['depuis'], 0, 10), 'extrait' => substr($h['extrait_le'], 0, 10)];
    $stats['urgentes'] = count(array_filter($h['cartes'], fn(array $c): bool => $c['urgent']));
    $stats['avec_echeance'] = count(array_filter($h['cartes'], fn(array $c): bool => $c['due'] !== null));
    $stats['cartes'] = count($h['cartes']);
    $taux = ['cartes' => [], 'etapes' => []];
    foreach (JR_DOMAINES as $d) {
        $taux['cartes'][$d] = $par[$d]['terminees'] / $semaines;
        $taux['etapes'][$d] = max(0, $par[$d]['coches'] - $par[$d]['decoches']) / $semaines;
    }
    $stats['taux'] = $taux;
    return $stats;
}

// ---------- Plan d'une année : les gestes, indépendants du barème ----------

// Répartit $n éléments sur les jours d'une semaine selon un ordre de jours donné
function sim_jour_de(array $jours, array $ordre, int $k): string
{
    $valides = array_values(array_filter($ordre, fn(int $i): bool => $i < count($jours)));
    return $jours[$valides[$k % count($valides)]];
}

// $scenario : liste de noms de profil, un par semaine
function sim_plan(array $semaines, array $profils_semaine, array $taux): array
{
    $reste = [];
    $prend = function (string $cle, float $quota) use (&$reste): int {
        $reste[$cle] = ($reste[$cle] ?? 0.0) + $quota;
        $n = (int) floor($reste[$cle] + 1e-9);
        $reste[$cle] -= $n;
        return $n;
    };
    $plan = [];
    $carte_k = 0;
    $seance_k = 0;
    foreach ($semaines as $i => $s) {
        $p = SIM_PROFILS[$profils_semaine[$i]];
        $f = count($s['jours']) / 7;
        $mult_rythme = $GLOBALS['sim_rythme'] ?? 1.0;
        $mult_combat = $GLOBALS['sim_combat'] ?? 1.0;
        $items = [];
        foreach (JR_DOMAINES as $d) {
            for ($k = 0, $n = $prend("c$d", $taux['cartes'][$d] * $p['cartes'] * $f * $mult_rythme); $k < $n; $k++, $carte_k++) {
                $items[] = ['type' => 'carte', 'domaine' => $d, 'jour' => sim_jour_de($s['jours'], [1, 3, 5, 0, 2, 4, 6], $carte_k),
                    'urgent' => $carte_k % SIM_MOD_URGENT === 3, 'tenue' => $carte_k % SIM_MOD_ECHEANCE === 1, 'eclair' => $carte_k % SIM_MOD_ECLAIR === 7];
            }
            $mult = $d === 'etudes' ? $p['etapes_etudes'] : $p['etapes'];
            for ($k = 0, $n = $prend("e$d", $taux['etapes'][$d] * $mult * $f * $mult_rythme); $k < $n; $k++) {
                $items[] = ['type' => 'etape', 'domaine' => $d, 'jour' => sim_jour_de($s['jours'], [1, 3, 4, 6, 0, 2, 5], $k + $i)];
            }
        }
        $n_seances = $prend('s', $p['seances'] * $f * $mult_combat);
        $max = (int) floor(SIM_PART_COMBAT_MAX * $s['libre_h'] * 60 / SIM_DUREE_MOYENNE);
        $n_seances = min($n_seances, $max);
        $poids = SIM_POIDS_COMBAT[$p['poids']];
        for ($k = 0; $k < $n_seances; $k++, $seance_k++) {
            $domaine = null;
            $meilleur = -1.0;
            foreach ($poids as $d => $w) {
                $reste["w$d"] = ($reste["w$d"] ?? 0.0) + $w;
                if ($reste["w$d"] > $meilleur) {
                    $meilleur = $reste["w$d"];
                    $domaine = $d;
                }
            }
            $reste["w$domaine"] -= 1.0;
            $items[] = ['type' => 'seance', 'domaine' => $domaine, 'jour' => sim_jour_de($s['jours'], [0, 2, 3, 5, 1, 4, 6], $seance_k),
                'duree' => SIM_DUREES[$seance_k % 10], 'issue' => SIM_ISSUES[$seance_k % 10]];
        }
        for ($k = 0, $n = $prend('j', $p['journal'] * $f); $k < $n; $k++) {
            $items[] = ['type' => 'journal', 'domaine' => 'perso', 'jour' => sim_jour_de($s['jours'], [0, 1, 2, 3, 4, 5, 6], $k), 'semaine' => $i];
        }
        usort($items, fn(array $a, array $b): int => $a['jour'] <=> $b['jour']);
        foreach ($items as $it) {
            $plan[] = $it;
        }
    }
    usort($plan, fn(array $a, array $b): int => $a['jour'] <=> $b['jour']);
    return $plan;
}

// ---------- Valorisation d'un plan selon un barème ----------

function sim_combat_xp(int $minutes, array $bareme): int
{
    $c = $bareme['combat'];
    return $c['mode'] === 'tranche' ? intdiv($minutes, 5) * (int) $c['xp'] : (int) floor($minutes * $c['xp'] + 1e-9);
}

function sim_hex_carte(DateTimeImmutable $creation, int $n): string
{
    return sprintf('%08x%016x', $creation->getTimestamp(), $n);
}

// Rend la liste des gains {jour, domaine, src, xp}, dans l'ordre. Les cartes et étapes passent par xp_rejouer().
function sim_valoriser(array $plan, array $bareme): array
{
    $regles = $bareme['regles'];
    $actions = [];
    $meta = [];
    $id = 0;
    $heure = [];
    foreach ($plan as $k => $it) {
        if ($it['type'] !== 'carte' && $it['type'] !== 'etape') {
            continue;
        }
        $heure[$it['jour']] = ($heure[$it['jour']] ?? 0) + 1;
        $quand = sim_dt($it['jour'], '08:00:00')->modify('+' . (3 * $heure[$it['jour']]) . ' seconds');
        $id++;
        if ($it['type'] === 'carte') {
            $creation = $quand->modify($it['eclair'] ? '-180 seconds' : '-3 days');
            $echeance = $it['tenue'] && !$it['eclair'] ? $quand->setTimezone(paris())->modify('+1 day')->setTime(18, 0)->format('Y-m-d\TH:i:sP') : null;
            $actions[] = ['id' => $id, 'type' => 'carte_terminee', 'domaine' => $it['domaine'], 'carte_id' => sim_hex_carte($creation, $id), 'element_id' => null,
                'quand' => $quand, 'donnees' => ['urgent' => $it['urgent'], 'echeance' => $echeance]];
        } else {
            $actions[] = ['id' => $id, 'type' => 'etape_cochee', 'domaine' => $it['domaine'], 'carte_id' => sim_hex_carte($quand->modify('-30 days'), 7), 'element_id' => "e$id",
                'quand' => $quand, 'donnees' => []];
        }
        $meta[$id] = $k;
    }
    $rejeu = xp_rejouer($actions, sim_dt(SIM_FIN), $regles);
    $gains = [];
    foreach ($actions as $a) {
        $it = $plan[$meta[$a['id']]];
        $gain = $rejeu['gains'][$a['id']];
        if ($it['type'] === 'etape') {
            $gains[$meta[$a['id']]] = [['etape', $gain]];
        } elseif ($it['eclair']) {
            if ($gain !== $regles['carte_eclair']) {
                throw new LogicException('Carte éclair mal reconnue par le moteur');
            }
            $gains[$meta[$a['id']]] = [['eclair', $gain]];
        } else {
            $bonus = $gain - $regles['carte'];
            $gains[$meta[$a['id']]] = [['carte', $regles['carte']], ['bonus', $bonus]];
        }
    }
    $records = [];
    $compte = [];
    foreach ($plan as $k => $it) {
        $parts = $gains[$k] ?? [];
        if ($it['type'] === 'seance') {
            $duree = $it['duree'];
            if ($it['issue'] === 'victoire') {
                $parts = [['combat', sim_combat_xp($duree, $bareme)], ['boss', $bareme['boss']]];
            } elseif ($it['issue'] === 'fini') {
                $m = (int) round(SIM_PART_FINI * $duree);
                $parts = [['fini', sim_combat_xp($m, $bareme) + ($m >= 5 ? $bareme['boss'] : 0)]];
            } else {
                $parts = [['forfait', sim_combat_xp((int) round(SIM_PART_FORFAIT * $duree), $bareme)]];
            }
        } elseif ($it['type'] === 'journal') {
            $parts = [['journal', $bareme['journal']]];
        }
        foreach ($parts as [$src, $xp]) {
            $plafond = $bareme['plafonds'][$src] ?? null;
            if ($plafond !== null && $xp > 0) {
                $compte[$it['jour']][$src] = ($compte[$it['jour']][$src] ?? 0) + 1;
                if ($compte[$it['jour']][$src] > $plafond) {
                    $xp = 0;
                }
            }
            if ($src === 'bonus' && $xp === 0) {
                continue;
            }
            $records[] = ['jour' => $it['jour'], 'domaine' => $it['domaine'], 'src' => $src, 'xp' => $xp];
        }
    }
    return $records;
}

const SIM_FAMILLES = [
    'carte' => 'cartes', 'bonus' => 'cartes', 'eclair' => 'cartes', 'etape' => 'etapes',
    'combat' => 'combat', 'boss' => 'combat', 'fini' => 'combat', 'forfait' => 'combat', 'journal' => 'journal',
];

// Dates d'atteinte des niveaux (par domaine) et des grades, et totaux
function sim_paliers(array $records, array $regles): array
{
    $xp = array_fill_keys(JR_DOMAINES, 0);
    $total = 0;
    $niveaux = array_fill_keys(JR_DOMAINES, [1 => SIM_DEBUT]);
    $grades = ['stagiaire' => SIM_DEBUT];
    $niveau_max = array_fill_keys(JR_DOMAINES, 1);
    foreach ($records as $r) {
        if ($r['xp'] <= 0) {
            continue;
        }
        $d = $r['domaine'];
        $xp[$d] += $r['xp'];
        $total += $r['xp'];
        $n = xp_progression($xp[$d], $regles)['niveau'];
        for ($i = $niveau_max[$d] + 1; $i <= $n; $i++) {
            $niveaux[$d][$i] = $r['jour'];
        }
        $niveau_max[$d] = max($niveau_max[$d], $n);
        $g = xp_grade($total, $regles)['grade'];
        if (!isset($grades[$g])) {
            foreach ($regles['grades'] as $nom => $seuil) {
                if ($seuil <= $total && !isset($grades[$nom])) {
                    $grades[$nom] = $r['jour'];
                }
            }
        }
    }
    return ['xp' => $xp, 'total' => $total, 'niveaux' => $niveaux, 'grades' => $grades];
}

function sim_cumul_a(array $records, string $jour): array
{
    $xp = array_fill_keys(JR_DOMAINES, 0);
    foreach ($records as $r) {
        if ($r['jour'] <= $jour) {
            $xp[$r['domaine']] += $r['xp'];
        }
    }
    return $xp + ['total' => array_sum($xp)];
}

function sim_sources(array $records, ?array $jours = null): array
{
    $s = array_fill_keys(array_keys(SIM_FAMILLES), 0);
    foreach ($records as $r) {
        if ($jours === null || isset($jours[$r['jour']])) {
            $s[$r['src']] += $r['xp'];
        }
    }
    return $s;
}

function sim_triche(array $bareme): array
{
    $jour = '2027-03-10';
    $plan = [];
    for ($i = 0; $i < SIM_TRICHE_ECLAIR; $i++) {
        $plan[] = ['type' => 'carte', 'domaine' => 'etudes', 'jour' => $jour, 'urgent' => false, 'tenue' => false, 'eclair' => true];
    }
    for ($i = 0; $i < SIM_TRICHE_ETAPES; $i++) {
        $plan[] = ['type' => 'etape', 'domaine' => 'etudes', 'jour' => $jour];
    }
    for ($i = 0; $i < SIM_TRICHE_JOURNAL; $i++) {
        $plan[] = ['type' => 'journal', 'domaine' => 'perso', 'jour' => $jour, 'semaine' => 0];
    }
    $par_source = [];
    foreach (sim_valoriser($plan, $bareme) as $r) {
        $par_source[$r['src']] = ($par_source[$r['src']] ?? 0) + $r['xp'];
    }
    return $par_source;
}

function sim_heure_combat(array $bareme): int
{
    return sim_combat_xp(60, $bareme) + $bareme['boss'];
}

// ---------- Programme ----------

$filtre = $argv[1] ?? null;
foreach (array_slice($argv, 2) as $arg) {
    [$cle, $val] = explode('=', $arg, 2) + [1 => ''];
    if ($cle === 'combat' || $cle === 'rythme') {
        $GLOBALS["sim_$cle"] = (float) $val;
    }
}
$ics = sim_lire_ics();
$semaines = sim_semaines($ics);
$reel = sim_reel();
$baremes = sim_baremes();
$sortie = ['reel' => [], 'calendrier' => [], 'baremes' => []];

echo "# Simulation de l'XP de Jarvis Jr.\n\n";
echo "## 1. Données réelles (Trello, {$reel['depuis']} au {$reel['extrait']}, " . number_format($reel['semaines'], 1) . " semaines)\n\n";
echo "| Domaine | Cartes vues | Terminées | Archivées sans Fait | Étapes cochées | Décochées | Max en un jour | Cartes/sem | Étapes nettes/sem |\n|---|---|---|---|---|---|---|---|---|\n";
foreach (JR_DOMAINES as $d) {
    $p = $reel['par_domaine'][$d];
    printf("| %s | %d | %d | %d | %d | %d | %d | %.2f | %.1f |\n", $d, $p['cartes_vues'], $p['terminees'], $p['archivees_sans_fait'], $p['coches'], $p['decoches'], $p['jour_max'], $reel['taux']['cartes'][$d], $reel['taux']['etapes'][$d]);
    $sortie['reel'][$d] = $p + ['cartes_sem' => $reel['taux']['cartes'][$d], 'etapes_sem' => $reel['taux']['etapes'][$d]];
}
printf("\nCartes vues : %d, dont %d Urgent et %d avec échéance.\n", $reel['cartes'], $reel['urgentes'], $reel['avec_echeance']);
echo "\nXP que ces gestes réels auraient rapporté (rejeu par xp_rejouer, sans plafonds) :\n\n| Barème | " . implode(' | ', JR_DOMAINES) . " | Total | Par semaine |\n|---|---|---|---|---|---|---|\n";
foreach ($baremes as $nom => $b) {
    $rejeu = xp_rejouer($reel['actions'], sim_dt($reel['extrait']), $b['regles']);
    printf("| %s | %s | %d | %.0f |\n", $nom, implode(' | ', array_map(fn($d) => (string) $rejeu['domaines'][$d], JR_DOMAINES)), $rejeu['total'], $rejeu['total'] / $reel['semaines']);
    $sortie['reel']['xp_' . $nom] = ['domaines' => $rejeu['domaines'], 'total' => $rejeu['total']];
}

echo "\n## 2. Calendrier simulé ({$semaines[0]['jours'][0]} au " . SIM_FIN . ")\n\n";
$types = array_count_values(array_column($semaines, 'type'));
echo '| Semaines | ' . implode(' | ', array_keys($types)) . " |\n|---|---|---|---|\n| Nombre | " . implode(' | ', $types) . " |\n";
echo "\nHeures libres moyennes par semaine ordinaire : " . number_format(array_sum(array_column(array_filter($semaines, fn($s) => $s['type'] === 'normale'), 'libre_h')) / max(1, $types['normale']), 1) . " h.\n";
$sortie['calendrier'] = ['types' => $types];

$scenarios = [
    'calendrier' => array_map(fn(array $s): string => match ($s['type']) { 'normale' => 'normale', 'vacances' => 'calme', 'examens' => 'medians' }, $semaines),
    'calme' => array_fill(0, count($semaines), 'calme'),
    'normale' => array_fill(0, count($semaines), 'normale'),
    'medians' => array_fill(0, count($semaines), 'medians'),
];
$plans = [];
foreach ($scenarios as $nom => $profils) {
    $plans[$nom] = sim_plan($semaines, $profils, $reel['taux']);
}
$boss = 0;
$dates_boss = [];
foreach ($plans['calendrier'] as $it) {
    if ($it['type'] === 'seance' && ($it['issue'] === 'victoire' || ($it['issue'] === 'fini' && round(SIM_PART_FINI * $it['duree']) >= 5))) {
        $boss++;
        if (in_array($boss, [10, 25, 50, 100], true)) {
            $dates_boss[$boss] = $it['jour'];
        }
    }
}
echo "\nBoss battus (scénario calendrier) : " . $boss . ' sur l\'année ; ' . implode(', ', array_map(fn($n, $j) => "$n le $j", array_keys($dates_boss), $dates_boss)) . "\n";
$sortie['boss'] = ['total' => $boss, 'dates' => $dates_boss];
$jours_normaux = [];
foreach ($semaines as $i => $s) {
    if ($s['type'] === 'normale') {
        foreach ($s['jours'] as $j) {
            $jours_normaux[$j] = true;
        }
    }
}
$nb_normales = $types['normale'];
$etiquettes = ['carte' => 'Carte terminée', 'bonus' => 'Bonus urgent et échéance', 'eclair' => 'Carte éclair', 'etape' => 'Étape cochée', 'combat' => 'Combat (minutes)', 'boss' => 'Boss battu',
    'fini' => "J'ai fini la tâche (minutes et boss)", 'forfait' => 'Forfait', 'journal' => 'Ligne de journal'];

foreach ($baremes as $nom => $b) {
    if ($filtre !== null && $filtre !== $nom) {
        continue;
    }
    $r = $b['regles'];
    echo "\n\n## Barème $nom : {$b['nom']}\n\n";
    echo "| Règle | Valeur |\n|---|---|\n";
    printf("| Carte / urgent / échéance / éclair | %d / +%d / +%d / %d |\n", $r['carte'], $r['bonus_urgent'], $r['bonus_echeance'], $r['carte_eclair']);
    printf("| Étape | %d |\n| Combat | %s |\n| Boss battu | +%d |\n| Journal | %d |\n", $r['etape'], $b['combat']['mode'] === 'tranche' ? $b['combat']['xp'] . ' par tranche de 5 min' : $b['combat']['xp'] . ' par minute', $b['boss'], $b['journal']);
    printf("| Plafonds par jour | %s |\n", $b['plafonds'] === [] ? 'aucun' : implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($b['plafonds']), $b['plafonds'])));
    printf("| Pas de niveau | %d (niveau 13 = %d XP) |\n| Grades | %s |\n", $r['pas_niveau'], xp_seuil_niveau(13, $r),
        implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($r['grades']), $r['grades'])));
    if ($nom === 'A') {
        echo '| Identique à JR_XP_REGLES | ' . ($r === JR_XP_REGLES ? 'oui' : 'NON') . " |\n";
    }
    $sortie['baremes'][$nom] = ['nom' => $b['nom'], 'regles' => $r, 'combat' => $b['combat'], 'boss' => $b['boss'], 'journal' => $b['journal'], 'plafonds' => $b['plafonds'], 'scenarios' => []];

    foreach ($scenarios as $sc => $profils) {
        $records = sim_valoriser($plans[$sc], $b);
        $p = sim_paliers($records, $r);
        $sortie['baremes'][$nom]['scenarios'][$sc] = ['total' => $p['total'], 'xp' => $p['xp'], 'niveaux' => $p['niveaux'], 'grades' => $p['grades']];
        if ($sc === 'calendrier') {
            $cal = ['records' => $records, 'paliers' => $p];
        }
        $cle = $sc === 'calendrier' ? 'calendrier (normale, calme en vacances, médians en examens)' : "$sc toute l'année";
        echo "\n### Scénario $cle : " . $p['total'] . " XP sur l'année\n\n";
        echo '| Niveau | ' . implode(' | ', JR_DOMAINES) . " |\n|---|---|---|---|---|\n";
        $maxn = max(array_map(fn($d) => max(array_keys($p['niveaux'][$d])), JR_DOMAINES));
        for ($n = 2; $n <= max(13, $maxn); $n++) {
            if ($sc !== 'calendrier' && !in_array($n, [2, 4, 8, 13], true)) {
                continue;
            }
            $ligne = [];
            foreach (JR_DOMAINES as $d) {
                $ligne[] = $p['niveaux'][$d][$n] ?? '-';
            }
            echo "| $n | " . implode(' | ', $ligne) . " |\n";
        }
        echo "\nXP finale par domaine : " . implode(', ', array_map(fn($d) => "$d {$p['xp'][$d]}", JR_DOMAINES)) . "\n";
        echo 'Grades : ' . implode(', ', array_map(fn($g, $j) => "$g $j", array_keys($p['grades']), $p['grades'])) . "\n";
        foreach ($r['grades'] as $g => $seuil) {
            if (!isset($p['grades'][$g])) {
                echo "Grade $g (seuil $seuil) : non atteint\n";
            }
        }
    }

    // Part de chaque source dans une semaine ordinaire du scénario calendrier
    if ($b['plafonds'] !== []) {
        $libre = $b;
        $libre['plafonds'] = [];
        $sans = array_sum(array_column(sim_valoriser($plans['calendrier'], $libre), 'xp'));
        $avec = array_sum(array_column($cal['records'], 'xp'));
        printf("\nXP non payés à cause des plafonds, scénario calendrier : %d sur %d (%s)\n", $sans - $avec, $sans, sim_pct(($sans - $avec) / $sans));
        $sortie['baremes'][$nom]['perdu_plafonds'] = ['xp' => $sans - $avec, 'part' => ($sans - $avec) / $sans];
    }
    $src = sim_sources($cal['records'], $jours_normaux);
    $total_sem = array_sum($src);
    echo "\n### Une semaine ordinaire (moyenne des $nb_normales semaines normales du calendrier) : " . number_format($total_sem / $nb_normales, 0) . " XP\n\n";
    echo "| Source | XP par semaine | Part |\n|---|---|---|\n";
    $familles = [];
    foreach ($src as $s => $xp) {
        $familles[SIM_FAMILLES[$s]] = ($familles[SIM_FAMILLES[$s]] ?? 0) + $xp;
        printf("| %s | %.0f | %s |\n", $etiquettes[$s], $xp / $nb_normales, sim_pct($xp / max(1, $total_sem)));
    }
    echo "\nParts par famille : " . implode(', ', array_map(fn($f, $xp) => "$f " . sim_pct($xp / max(1, $total_sem)), array_keys($familles), $familles)) . "\n";
    $sortie['baremes'][$nom]['semaine_ordinaire'] = ['xp' => $total_sem / $nb_normales, 'sources' => array_map(fn($x) => $x / $nb_normales, $src), 'familles' => array_map(fn($x) => $x / max(1, $total_sem), $familles)];

    // Soirée de triche
    $tr = sim_triche($b);
    $heure = sim_heure_combat($b);
    echo "\n### Soirée de triche (2 h) contre une heure de combat\n\n";
    echo "| Source | XP |\n|---|---|\n";
    foreach ($tr as $s => $xp) {
        printf("| %s | %d |\n", $etiquettes[$s] ?? $s, $xp);
    }
    printf("| **Total soirée de triche** | **%d** |\n| Une heure de combat (60 min, boss battu) | %d |\n", array_sum($tr), $heure);
    $sortie['baremes'][$nom]['triche'] = ['sources' => $tr, 'total' => array_sum($tr), 'heure_combat' => $heure];

    // Les 5 critères sur le scénario calendrier
    $criteres = [];
    $criteres[1] = ['ok' => $heure >= $r['carte'], 'detail' => "1 h de combat = $heure XP, carte ordinaire = {$r['carte']} XP"];
    $max_famille = max($familles);
    $nom_famille = array_search($max_famille, $familles, true);
    $criteres[2] = ['ok' => $max_famille / $total_sem <= 0.5, 'detail' => "plus grosse famille : $nom_famille " . sim_pct($max_famille / $total_sem)];
    $actif = array_search(max($cal['paliers']['xp']), $cal['paliers']['xp'], true);
    $dates = $cal['paliers']['niveaux'][$actif];
    ksort($dates);
    $limite = (new DateTimeImmutable(SIM_DEBUT))->modify('+42 days')->format('Y-m-d');
    $precedent = SIM_DEBUT;
    $ok3 = true;
    $atteints = 0;
    $ecarts = [];
    foreach ($dates as $n => $j) {
        if ($n === 1) {
            continue;
        }
        if ($j > $limite) {
            break;
        }
        $ecart = (int) (new DateTimeImmutable($precedent))->diff(new DateTimeImmutable($j))->days;
        $ecarts[] = $ecart;
        $ok3 = $ok3 && $ecart <= 14;
        $precedent = $j;
        $atteints++;
    }
    $criteres[3] = ['ok' => $ok3 && $atteints >= 3, 'detail' => "domaine $actif, $atteints niveaux en 6 semaines, écarts en jours : " . implode(', ', $ecarts)];
    $e13 = $cal['paliers']['niveaux']['etudes'][13] ?? null;
    $maj = $cal['paliers']['grades']['majordome'] ?? null;
    $criteres[4] = ['ok' => $e13 !== null && $e13 >= '2027-06-10' && $e13 <= '2027-06-30' && $maj !== null && $maj >= '2027-04-15' && $maj <= '2027-06-30',
        'detail' => 'niveau 13 Études : ' . ($e13 ?? 'non atteint') . ', Majordome : ' . ($maj ?? 'non atteint')];
    $criteres[5] = ['ok' => array_sum($tr) < $heure, 'detail' => 'soirée de triche ' . array_sum($tr) . " XP, 1 h de combat $heure XP"];
    echo "\n### Les 5 critères (scénario calendrier)\n\n| # | Critère | Tenu | Mesure |\n|---|---|---|---|\n";
    $noms_criteres = [1 => 'Une heure de combat vaut au moins une carte ordinaire', 2 => "Aucune source ne dépasse la moitié de l'XP d'une semaine normale",
        3 => 'Un niveau au moins toutes les deux semaines pendant les 6 premières semaines (domaine le plus actif)', 4 => 'Niveau 13 Études vers les finaux de juin, Majordome entre mi-avril et fin juin',
        5 => 'Une soirée de triche rapporte moins qu\'une heure de combat'];
    foreach ($criteres as $n => $c) {
        echo "| $n | {$noms_criteres[$n]} | " . ($c['ok'] ? 'oui' : 'NON') . " | {$c['detail']} |\n";
    }
    $sortie['baremes'][$nom]['criteres'] = $criteres;
    $sortie['baremes'][$nom]['cumuls'] = [
        '2026-11-01' => sim_cumul_a($cal['records'], '2026-11-01'),
        '2027-01-15' => sim_cumul_a($cal['records'], '2027-01-15'),
        '2027-06-20' => sim_cumul_a($cal['records'], '2027-06-20'),
    ];
}

@mkdir(__DIR__ . '/donnees');
file_put_contents(__DIR__ . '/donnees/simulation-xp.json', json_encode($sortie, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n";
