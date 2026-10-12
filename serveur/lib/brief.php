<?php
// Le brief du matin (SPEC § 15.5) : titre et texte de la notification d'un jour. Fonctions pures.

declare(strict_types=1);

require_once __DIR__ . '/formats.php';

// CONTENU.md § 5.1 (proposition de Claude, à relire par Colin)
const JR_REPLIQUES = [
    "J'ai déjà tout rangé. Toi, tu n'as qu'à exister.",
    "Café d'abord, génie ensuite.",
    "Rien d'impossible aujourd'hui. J'ai vérifié.",
    "Je t'ai préparé la journée. Les applaudissements, c'est plus tard.",
    "Tu peux traîner au lit. Le Bug, lui, est déjà debout.",
    "Une journée de plus à me rendre indispensable.",
    "Si tu suis le plan, je prends le mérite. Sinon, c'est toi.",
    "Petit rappel : Jarvis, c'est moi en plus grand. Et en moins drôle.",
    "J'aurais fait la journée à ta place, mais il me manque des jambes.",
    "Les cartes Trello ne se terminent pas en les regardant. J'ai testé.",
    "Aujourd'hui, on vise la colonne Fait. Elle se sent seule.",
    "Le Poly t'attend. Il n'a rien d'autre à faire, lui.",
    "Debout, Majordome en devenir. Ah non, c'est moi le Majordome.",
    "Journée chargée. Heureusement, tu m'as.",
    "Météo de la journée : 100 % de chances que je te le rappelle.",
    "J'ai relu ton agenda. Courage. Sincèrement.",
    "La Flemme a demandé de tes nouvelles. Je lui ai dit que tu étais occupé.",
    "On fait simple : un GO, une tâche, une victoire.",
    "Tu as dormi. Moi non. Je ne me plains pas, je le signale.",
    "Si quelqu'un demande, c'était mon idée.",
];

// Le titre quand l'UTC a prévu autre chose qu'une journée de cours
const JR_BRIEF_BANDEAUX = ['vacances' => 'Vacances', 'ferie' => 'Férié', 'examen' => "Jour d'examen"];

// L'ordre des répliques d'un bloc de 20 jours : mélangé, mais toujours le même
function repliques_ordre(int $bloc): array
{
    $ordre = array_keys(JR_REPLIQUES);
    usort($ordre, fn(int $a, int $b): int => strcmp(hash('sha256', "$bloc:$a"), hash('sha256', "$bloc:$b")));
    return $ordre;
}

// Chaque réplique une fois par bloc de 20 jours, jamais deux jours de suite la même. Graine : la date.
function replique_du_jour(string $date): string
{
    $total = count(JR_REPLIQUES);
    $numero = intdiv((new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp(), 86400);
    $bloc = intdiv($numero, $total);
    $ordre = repliques_ordre($bloc);
    // Le premier jour d'un bloc ne répète pas le dernier du bloc précédent
    if ($ordre[0] === repliques_ordre($bloc - 1)[$total - 1]) {
        [$ordre[0], $ordre[1]] = [$ordre[1], $ordre[0]];
    }
    return JR_REPLIQUES[$ordre[$numero % $total]];
}

// Un jour absent de la copie de l'agenda (Google pas connecté, copie trop vieille) : ni cours ni
// « Pas de cours », on ne sait pas.
function brief_du_matin(string $date, array $cartes, array $jours): array
{
    $titre = date_longue(new DateTimeImmutable("$date 12:00", paris()));
    $morceaux = [];
    $jour = $jours[$date] ?? null;
    if (is_array($jour)) {
        $cours = array_values(array_filter($jour['evenements'] ?? [], fn(array $e): bool => $e['source'] === 'utc' && !$e['journee']));
        $etiquette = $cours === [] ? 'Pas de cours' : count($cours) . ' cours';
        foreach ($jour['bandeaux'] ?? [] as $bandeau) {
            if (isset(JR_BRIEF_BANDEAUX[$bandeau['type']])) {
                $etiquette = JR_BRIEF_BANDEAUX[$bandeau['type']];
            } elseif ($bandeau['type'] === 'devient') {
                $morceaux[] = $bandeau['texte'];
            }
        }
        $titre .= " · $etiquette";
        if ($cours !== []) {
            $premier = $cours[0];
            $nom = $premier['uv'] !== null ? "{$premier['uv']} {$premier['type']}" : $premier['titre'];
            $salle = $premier['salle'] !== null ? " en {$premier['salle']}" : '';
            $morceaux[] = 'Premier à ' . heure_lisible(new DateTimeImmutable($premier['debut'])) . "$salle ($nom).";
        }
    }
    $morceaux[] = brief_taches($cartes, $date);
    $morceaux[] = brief_median($jours, $date);
    $morceaux[] = replique_du_jour($date);
    return ['titre' => $titre, 'texte' => implode(' ', array_filter($morceaux, fn(string $m): bool => $m !== ''))];
}

// « 1 tâche du jour, 2 en retard. » : cartes ouvertes dont l'échéance tombe ce jour-là ou avant
function brief_taches(array $cartes, string $date): string
{
    $du_jour = 0;
    $en_retard = 0;
    foreach ($cartes as $c) {
        if ($c['echeance'] === null || $c['colonne'] === 'fait' || $c['echeance_faite']) {
            continue;
        }
        $jour = (new DateTimeImmutable($c['echeance']))->setTimezone(paris())->format('Y-m-d');
        if ($jour === $date) {
            $du_jour++;
        } elseif ($jour < $date) {
            $en_retard++;
        }
    }
    return match (true) {
        $du_jour > 0 && $en_retard > 0 => pluriel($du_jour, 'tâche') . " du jour, $en_retard en retard.",
        $du_jour > 0 => pluriel($du_jour, 'tâche') . ' du jour.',
        $en_retard > 0 => pluriel($en_retard, 'tâche') . ' en retard.',
        default => '',
    };
}

// Le médian le plus proche (événements « Médian … » de l'agenda principal), s'il tombe dans les 14 jours
function brief_median(array $jours, string $date): string
{
    ksort($jours);
    foreach ($jours as $jour => $contenu) {
        if ($jour < $date) {
            continue;
        }
        foreach ($contenu['evenements'] ?? [] as $e) {
            if (empty($e['median']) || $e['journee']) {
                continue;
            }
            $ecart = jours_entre($date, (string) $jour);
            $heure = heure_lisible(new DateTimeImmutable($e['debut']));
            return match (true) {
                $ecart > 14 => '',
                $ecart === 0 => "{$e['titre']} aujourd'hui à $heure.",
                $ecart === 1 => "{$e['titre']} demain à $heure.",
                default => "{$e['titre']} dans $ecart jours.",
            };
        }
    }
    return '';
}
