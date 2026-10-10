<?php
// Événements Google en créneaux et bandeaux pour l'iPhone (SPEC § 7.3). Fonctions pures : ni réseau ni base.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';

const JR_REGEX_CRENEAU = '/^([A-Z]{2}\d{2})\s*-\s*(Cours|TD|TP)(?:\s+\d+)?$/';
const JR_JOURS_SEMAINE = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

// Un événement Google (lu avec timeZone=Europe/Paris) devient un créneau. $source : 'utc' ou 'perso'.
// Journée entière : debut et fin en AAAA-MM-JJ, fin exclue (comme Google).
function creneau_lire(array $evenement, string $source): array
{
    $debut = $evenement['start']['date'] ?? $evenement['start']['dateTime'] ?? null;
    if (!is_string($debut) || $debut === '') {
        throw new InvalidArgumentException('Événement sans début');
    }
    $fin = $evenement['end']['date'] ?? $evenement['end']['dateTime'] ?? $debut;
    $journee = isset($evenement['start']['date']);
    if (!$journee) {
        $debut = iso_paris(new DateTimeImmutable($debut));
        $fin = iso_paris(new DateTimeImmutable((string) $fin));
    }
    $titre = trim((string) ($evenement['summary'] ?? ''));
    $creneau = [
        'debut' => $debut,
        'fin' => $fin,
        'journee' => $journee,
        'source' => $source,
        'uv' => null,
        'type' => null,
        'salle' => null,
        'titre' => $titre === '' ? 'Sans titre' : $titre,
        'median' => $source === 'perso' && str_starts_with(normaliser_texte($titre), 'median'),
    ];
    if ($source === 'utc' && preg_match(JR_REGEX_CRENEAU, $titre, $m)) {
        $salle = trim((string) ($evenement['location'] ?? ''));
        $creneau['uv'] = $m[1];
        $creneau['type'] = $m[2];
        $creneau['salle'] = $salle === '' ? null : $salle;
    }
    return $creneau;
}

// Journée entière de l'agenda UTC qui devient un bandeau, ou null
function bandeau_du_titre(string $titre): ?array
{
    $t = rtrim(normaliser_texte($titre), ' .');
    if ($t === 'vacances universitaires') {
        return ['type' => 'vacances', 'jour' => null, 'texte' => 'Vacances universitaires.'];
    }
    if ($t === "jour d'examen") {
        return ['type' => 'examen', 'jour' => null, 'texte' => "Jour d'examen."];
    }
    if ($t === 'ferie') {
        return ['type' => 'ferie', 'jour' => null, 'texte' => "Jour férié à l'UTC : pas de cours."];
    }
    if (preg_match('/^devient un (' . implode('|', JR_JOURS_SEMAINE) . ')$/', $t, $m)) {
        return ['type' => 'devient', 'jour' => $m[1], 'texte' => "Aujourd'hui, l'UTC suit l'emploi du temps du {$m[1]}."];
    }
    return null;
}

// Les $nombre jours à partir de $premier (AAAA-MM-JJ, Paris), chacun avec ses bandeaux et ses événements
// (journées entières d'abord, puis par heure). Un événement annulé ou illisible est ignoré : il ne doit
// pas casser l'agenda.
function jours_agenda(array $evenements_utc, array $evenements_perso, string $premier, int $nombre): array
{
    $jours = [];
    $jour = new DateTimeImmutable($premier, paris());
    for ($i = 0; $i < $nombre; $i++) {
        $jours[$jour->format('Y-m-d')] = ['bandeaux' => [], 'evenements' => []];
        $jour = $jour->modify('+1 day');
    }
    foreach (['utc' => $evenements_utc, 'perso' => $evenements_perso] as $source => $evenements) {
        foreach ($evenements as $evenement) {
            if (!is_array($evenement) || ($evenement['status'] ?? 'confirmed') === 'cancelled') {
                continue;
            }
            try {
                $creneau = creneau_lire($evenement, $source);
                $dates = jours_couverts($creneau);
            } catch (Throwable) {
                continue;
            }
            $bandeau = $source === 'utc' && $creneau['journee'] ? bandeau_du_titre($creneau['titre']) : null;
            foreach ($dates as $date) {
                if (!isset($jours[$date])) {
                    continue;
                }
                if ($bandeau !== null) {
                    $jours[$date]['bandeaux'][] = $bandeau;
                } else {
                    $jours[$date]['evenements'][] = $creneau;
                }
            }
        }
    }
    foreach ($jours as &$contenu) {
        usort($contenu['evenements'], fn(array $a, array $b): int => [!$a['journee'], $a['debut']] <=> [!$b['journee'], $b['debut']]);
    }
    unset($contenu);
    return $jours;
}

// Dates de Paris couvertes : une journée entière de son début à sa fin exclue, un créneau le jour de son début
function jours_couverts(array $creneau): array
{
    if (!$creneau['journee']) {
        return [substr($creneau['debut'], 0, 10)];
    }
    $dates = [];
    $jour = new DateTimeImmutable($creneau['debut'], paris());
    $fin = new DateTimeImmutable($creneau['fin'], paris());
    for ($i = 0; $jour < $fin && $i < 366; $i++) {
        $dates[] = $jour->format('Y-m-d');
        $jour = $jour->modify('+1 day');
    }
    return $dates === [] ? [$jour->format('Y-m-d')] : $dates;
}
