<?php
// Ce que reçoit l'iPhone (SPEC § 5.5) : copies de Trello et de l'agenda, rafraîchies à la demande.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/detection.php';
require_once __DIR__ . '/google.php';
require_once __DIR__ . '/rafraichir.php';
require_once __DIR__ . '/reglages.php';
require_once __DIR__ . '/trello.php';

const JR_AGE_TRELLO = 60;
const JR_AGE_AGENDA = 600;
const JR_AGE_FORCE = 10;
const JR_JOURS_DONNEES = 15;  // aujourd'hui et les 14 jours suivants
const JR_FAITES_JOURS = 14;

// Trello rafraîchi si besoin, actions faites ailleurs notées au passage. Pas de détection quand les
// écritures sont simulées (dev) : la copie diffère alors de Trello exprès.
function donnees_trello(bool $force): array
{
    $detecter = config('trello')['ecritures'] === 'reelles' ? 'noter_actions_detectees' : null;
    return rafraichir('trello', $force ? JR_AGE_FORCE : JR_AGE_TRELLO, 'trello_lire', $detecter);
}

// Une action déjà notée par l'app depuis la copie précédente n'est pas notée une seconde fois
function noter_actions_detectees(?array $avant, array $apres, ?DateTimeImmutable $depuis): void
{
    foreach (detecter_actions($avant, $apres) as $a) {
        if ($depuis !== null && action_app_existe_depuis($a['type'], $a['carte_id'], $a['element_id'], $depuis)) {
            continue;
        }
        action_noter($a['type'], $a['domaine'], $a['carte_id'], $a['element_id'], $a['quand'], 'trello', 'approx', $a['donnees']);
    }
}

// Jamais connecté : agenda vide, sans erreur (Moi propose « Connecter Google Agenda »). Une copie qui ne
// commence pas à $premier (copie de la veille) est relue même si elle a moins de 10 min.
function donnees_agenda(string $source, string $premier, int $nombre, bool $force = false): array
{
    if (!google_connecte() && reglage_lire('agenda_calendriers') === null) {
        return ['recupere_le' => null, 'erreur' => null, 'google_connecte' => false, 'jours' => new stdClass()];
    }
    $r = rafraichir(
        $source,
        $force ? JR_AGE_FORCE : JR_AGE_AGENDA,
        fn(): array => google_lire_jours($premier, $nombre),
        null,
        fn(array $contenu): bool => ($contenu['premier'] ?? null) === $premier
    );
    return [
        'recupere_le' => iso_ou_null($r['recupere_le']),
        'erreur' => $r['erreur'],
        'google_connecte' => google_connecte(),
        'jours' => $r['contenu']['jours'] ?? new stdClass(),
    ];
}

// Les cartes de Fait ne partent que si leur dernière activité a moins de 14 jours
function cartes_a_montrer(array $cartes): array
{
    $limite = maintenant()->modify('-' . JR_FAITES_JOURS . ' days');
    return array_values(array_filter($cartes, fn(array $c): bool => $c['colonne'] !== 'fait' || new DateTimeImmutable($c['derniere_activite']) >= $limite));
}

// Une source en panne imprévue (autre qu'une erreur de source, déjà rendue avec sa copie) ne fait pas
// tomber l'autre : sa dernière copie part avec un code d'erreur, et la panne va au journal.
function donnees_reponse(bool $force): array
{
    try {
        $trello = donnees_trello($force);
    } catch (RuntimeException $e) {
        journal_erreur('donnees/trello', $e);
        $trello = resultat_copie(copie_lire('trello'), 'trello_indisponible');
    }
    try {
        $agenda = donnees_agenda('agenda', aujourdhui_paris()->format('Y-m-d'), JR_JOURS_DONNEES, $force);
    } catch (RuntimeException $e) {
        journal_erreur('donnees/agenda', $e);
        $copie = copie_lire('agenda');
        $agenda = [
            'recupere_le' => iso_ou_null($copie['recupere_le'] ?? null),
            'erreur' => 'google_indisponible',
            'google_connecte' => $copie !== null,
            'jours' => $copie['contenu']['jours'] ?? new stdClass(),
        ];
    }
    return [
        'maintenant' => iso_paris(maintenant()),
        'trello' => [
            'recupere_le' => iso_ou_null($trello['recupere_le']),
            'erreur' => $trello['erreur'],
            'cartes' => cartes_a_montrer($trello['contenu']['cartes'] ?? []),
        ],
        'agenda' => $agenda,
        'notifications' => ['cle' => config('vapid')['publique']],
    ];
}

// 2026-W43 → '2026-10-19' (son lundi), ou null. Semaines de l'an dernier à l'an prochain.
function semaine_premier_jour(string $semaine): ?string
{
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $semaine, $m) || (int) $m[2] < 1 || abs((int) $m[1] - (int) aujourdhui_paris()->format('Y')) > 1) {
        return null;
    }
    $lundi = aujourdhui_paris()->setISODate((int) $m[1], (int) $m[2], 1);
    return $lundi->format('o-\WW') === $semaine ? $lundi->format('Y-m-d') : null;
}

function iso_ou_null(?DateTimeImmutable $moment): ?string
{
    return $moment === null ? null : iso_paris($moment);
}
