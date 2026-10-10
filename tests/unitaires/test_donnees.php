<?php
declare(strict_types=1);

const BATCH = 'GET https://api.trello.com/1/batch';
const EVENEMENTS_UTC = 'GET https://www.googleapis.com/calendar/v3/calendars/utc-a26%40group.calendar.google.com/events';
const EVENEMENTS_PERSO = 'GET https://www.googleapis.com/calendar/v3/calendars/colin%40exemple.fr/events';

test('données : première ouverture, Google jamais connecté : cartes de Trello, agenda vide sans erreur, aucune action notée', function () {
    base_de_test();
    fixer_heure('2026-10-09 13:40:00');
    faux_http([BATCH => ['json' => batch_trello(['qyris' => [carte_trello('qyris', 'Fait', 1)], 'etudes' => [carte_trello('etudes', 'À faire', 2)]])]]);
    $r = appeler('GET', 'donnees');
    egal(200, $r['statut']);
    $d = corps($r);
    egal('2026-10-09T15:40:00+02:00', $d['maintenant']);
    egal(['2026-10-09T15:40:00+02:00', null], [$d['trello']['recupere_le'], $d['trello']['erreur']]);
    egal([id_test('carte', 1), id_test('carte', 2)], array_column($d['trello']['cartes'], 'id'));
    egal(['recupere_le' => null, 'erreur' => null, 'google_connecte' => false, 'jours' => []], $d['agenda']);
    vrai(str_contains($r['corps'], '"jours":{}'), 'jours doit être un objet JSON vide');
    egal(0, (int) db()->query('SELECT COUNT(*) FROM actions')->fetchColumn());
});

test('données : Trello relu après 60 s (10 s avec force=1), pas avant', function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    faux_http([BATCH => ['json' => batch_trello()]]);
    appeler('GET', 'donnees');
    // Un appel imprévu lèverait EchecTest dans la route : réponse 500 au lieu de 200
    fixer_heure('2026-10-09 10:00:11');
    faux_http([]);
    egal(200, appeler('GET', 'donnees')['statut']);
    egal(200, appeler('GET', 'donnees', [], ['force' => '0'])['statut']);
    faux_http([BATCH => ['json' => batch_trello()]]);
    appeler('GET', 'donnees', [], ['force' => '1']);
    egal(1, count(appels_http()));
    fixer_heure('2026-10-09 10:01:10');  // 59 s après la lecture forcée
    faux_http([]);
    egal(200, appeler('GET', 'donnees')['statut']);
    fixer_heure('2026-10-09 10:01:11');
    faux_http([BATCH => ['json' => batch_trello()]]);
    appeler('GET', 'donnees');
    egal(1, count(appels_http()));
});

test("données : une étape cochée ailleurs est notée (source trello, heure approximative), sauf si l'app l'a déjà notée", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    $carte = carte_trello('etudes', 'En cours', 1, ['dateLastActivity' => '2026-10-09T09:58:00.000Z']);
    $batch = fn(bool $un, bool $deux): array => ['json' => batch_trello(['etudes' => [$carte]], ['etudes' => [checklist_trello($carte['id'], 1, ['Lire le poly' => $un, 'Annales' => $deux])]])];
    faux_http([BATCH => $batch(false, false)]);
    appeler('GET', 'donnees');
    // L'app coche « Annales » entre deux copies ; Jarvis coche « Lire le poly »
    action_noter('etape_cochee', 'etudes', $carte['id'], id_test('element', 12), utc_test('2026-10-09 10:00:30'), 'app', 'exacte');
    fixer_heure('2026-10-09 10:01:00');
    faux_http([BATCH => $batch(true, true)]);
    appeler('GET', 'donnees');
    $lignes = db()->query("SELECT type, domaine, carte_id, element_id, quand, source, precision_heure FROM actions WHERE source = 'trello'")->fetchAll();
    egal([['type' => 'etape_cochee', 'domaine' => 'etudes', 'carte_id' => $carte['id'], 'element_id' => id_test('element', 11), 'quand' => '2026-10-09 09:58:00', 'source' => 'trello', 'precision_heure' => 'approx']], $lignes);
});

test('données : en écritures simulées (dev), rien n\'est détecté', function () {
    base_de_test();
    $GLOBALS['JR_CONFIG'] = config_de_test(['trello' => ['ecritures' => 'simulees']]);
    fixer_heure('2026-10-09 10:00:00');
    faux_http([BATCH => ['json' => batch_trello(['qyris' => [carte_trello('qyris', 'En cours', 1)]])]]);
    appeler('GET', 'donnees');
    fixer_heure('2026-10-09 10:02:00');
    faux_http([BATCH => ['json' => batch_trello(['qyris' => [carte_trello('qyris', 'Fait', 1)]])]]);
    appeler('GET', 'donnees');
    egal(0, (int) db()->query('SELECT COUNT(*) FROM actions')->fetchColumn());
});

test('données : Trello en panne, la dernière copie revient avec son heure et trello_indisponible, jamais une 500', function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    faux_http([BATCH => ['json' => batch_trello(['perso' => [carte_trello('perso', 'À faire', 1)]])]]);
    appeler('GET', 'donnees');
    fixer_heure('2026-10-09 10:05:00');
    faux_http([BATCH => new ErreurReseau('délai dépassé')]);
    $r = appeler('GET', 'donnees');
    egal(200, $r['statut']);
    $t = corps($r)['trello'];
    egal(['trello_indisponible', '2026-10-09T12:00:00+02:00', [id_test('carte', 1)]], [$t['erreur'], $t['recupere_le'], array_column($t['cartes'], 'id')]);
});

test("données : une carte de Fait n'est envoyée que si sa dernière activité a moins de 14 jours", function () {
    base_de_test();
    fixer_heure('2026-10-20 10:00:00');
    faux_http([BATCH => ['json' => batch_trello(['asso' => [
        carte_trello('asso', 'Fait', 1, ['dateLastActivity' => '2026-10-06T09:59:00.000Z']),
        carte_trello('asso', 'Fait', 2, ['dateLastActivity' => '2026-10-06T10:01:00.000Z']),
        carte_trello('asso', 'À faire', 3, ['dateLastActivity' => '2026-01-01T10:00:00.000Z']),
    ]])]]);
    egal([id_test('carte', 2), id_test('carte', 3)], array_column(corps(appeler('GET', 'donnees'))['trello']['cartes'], 'id'));
});

test("données : avec Google connecté, l'agenda couvre aujourd'hui et les 14 jours suivants, de minuit à minuit, heure d'hiver comprise", function () {
    base_de_test();
    fixer_heure('2026-10-20 06:00:00');  // mardi 20/10, 8 h à Paris
    google_connecte_pour_test();
    faux_http([
        BATCH => ['json' => batch_trello()],
        EVENEMENTS_UTC => ['json' => ['items' => [
            evenement_google("Jour d'examen", '2026-10-20', '2026-10-21'),
            evenement_google('NF93 - TD 1', '2026-10-26T08:00', '2026-10-26T10:00', 'FA400'),
        ]]],
        EVENEMENTS_PERSO => ['json' => ['items' => [evenement_google('Médian MT23', '2026-10-21T19:15', '2026-10-21T20:45')]]],
    ]);
    $agenda = corps(appeler('GET', 'donnees'))['agenda'];
    egal([true, null, '2026-10-20T08:00:00+02:00'], [$agenda['google_connecte'], $agenda['erreur'], $agenda['recupere_le']]);
    egal(15, count($agenda['jours']));
    egal(['2026-10-20', '2026-11-03'], [array_key_first($agenda['jours']), array_key_last($agenda['jours'])]);
    egal('examen', $agenda['jours']['2026-10-20']['bandeaux'][0]['type']);
    egal('2026-10-26T08:00:00+01:00', $agenda['jours']['2026-10-26']['evenements'][0]['debut']);
    egal(true, $agenda['jours']['2026-10-21']['evenements'][0]['median']);
    $appel_utc = array_values(array_filter($GLOBALS['JR_APPELS_HTTP'], fn(array $a): bool => str_starts_with($a['methode'] . ' ' . $a['url'], EVENEMENTS_UTC)))[0];
    parse_str((string) parse_url($appel_utc['url'], PHP_URL_QUERY), $p);
    egal(['2026-10-20T00:00:00+02:00', '2026-11-04T00:00:00+01:00'], [$p['timeMin'], $p['timeMax']]);
});

test("données : l'agenda est gardé 10 min, puis relu ; il est relu aussi le lendemain, même avant 10 min", function () {
    base_de_test();
    fixer_heure('2026-10-09 21:55:00');  // 23 h 55 à Paris
    google_connecte_pour_test();
    $google = ['GET https://www.googleapis.com/calendar/v3/calendars/' => ['json' => ['items' => []]]];
    faux_http([BATCH => ['json' => batch_trello()]] + $google);
    appeler('GET', 'donnees');
    fixer_heure('2026-10-09 21:59:00');
    faux_http([BATCH => ['json' => batch_trello()]]);
    egal('2026-10-09', array_key_first(corps(appeler('GET', 'donnees'))['agenda']['jours']));
    fixer_heure('2026-10-09 22:01:00');  // 0 h 01 le 10 à Paris
    faux_http([BATCH => ['json' => batch_trello()]] + $google);
    egal('2026-10-10', array_key_first(corps(appeler('GET', 'donnees'))['agenda']['jours']));
});

test("données : Google révoqué : la dernière copie de l'agenda revient avec google_deconnecte, et Google n'est plus connecté", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    google_connecte_pour_test();
    $google = ['GET https://www.googleapis.com/calendar/v3/calendars/' => ['json' => ['items' => []]]];
    faux_http([BATCH => ['json' => batch_trello()]] + $google);
    appeler('GET', 'donnees');
    fixer_heure('2026-10-09 11:30:00');
    faux_http([BATCH => ['json' => batch_trello()], 'POST https://oauth2.googleapis.com/token' => ['statut' => 400, 'json' => ['error' => 'invalid_grant']]]);
    $agenda = corps(appeler('GET', 'donnees'))['agenda'];
    egal(['google_deconnecte', false, '2026-10-09T12:00:00+02:00', 15], [$agenda['erreur'], $agenda['google_connecte'], $agenda['recupere_le'], count($agenda['jours'])]);
});

test('agenda : une semaine ISO donne ses 7 jours du lundi au dimanche, dans sa propre copie ; une semaine invalide donne 400', function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    google_connecte_pour_test();
    faux_http(['GET https://www.googleapis.com/calendar/v3/calendars/' => ['json' => ['items' => []]]]);
    $r = corps(appeler('GET', 'agenda', [], ['semaine' => '2026-W43']));
    egal('2026-W43', $r['semaine']);
    egal(['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25'], array_keys($r['agenda']['jours']));
    vrai(copie_lire('agenda:2026-W43') !== null, 'copie de la semaine attendue');
    egal(['2026-12-28'], array_slice(array_keys(corps(appeler('GET', 'agenda', [], ['semaine' => '2026-W53']))['agenda']['jours']), 0, 1));
    foreach (['2026-W54', '2026-W00', '2026W43', '2030-W01', '2027-W53', '', 'abc'] as $mauvaise) {
        egal(400, appeler('GET', 'agenda', [], ['semaine' => $mauvaise])['statut'], $mauvaise);
    }
    egal(400, appeler('GET', 'agenda', [], ['semaine' => ['2026-W43']])['statut']);
    egal(400, appeler('GET', 'agenda')['statut']);
});

test("données : un jeton Google illisible (clé changée) est vu comme absent : 200, agenda en google_deconnecte, une ligne de journal", function () {
    base_de_test();
    fixer_heure('2026-10-10 10:00:00');
    google_connecte_pour_test();
    $GLOBALS['JR_CONFIG'] = config_de_test(['cle_chiffrement' => base64_encode(str_repeat("\x02", 32))]);
    egal(null, jeton_lire('google'));
    egal(false, google_connecte());
    faux_http([BATCH => ['json' => batch_trello()]]);
    $r = appeler('GET', 'donnees');
    egal(200, $r['statut']);
    egal('google_deconnecte', corps($r)['agenda']['erreur']);
    vrai(str_contains((string) file_get_contents(dossier_data() . '/erreurs.log'), 'Déchiffrement impossible'), 'ligne de journal attendue');
});

test("données : une panne imprévue de Trello ne fait pas tomber la réponse : dernière copie, trello_indisponible, agenda intact", function () {
    base_de_test();
    fixer_heure('2026-10-10 10:00:00');
    faux_http([BATCH => ['json' => batch_trello(['perso' => [carte_trello('perso', 'À faire', 1)]])]]);
    appeler('GET', 'donnees');
    fixer_heure('2026-10-10 10:05:00');
    faux_http([BATCH => new RuntimeException('panne imprévue')]);
    $r = appeler('GET', 'donnees');
    egal(200, $r['statut']);
    $d = corps($r);
    egal(['trello_indisponible', '2026-10-10T12:00:00+02:00'], [$d['trello']['erreur'], $d['trello']['recupere_le']]);
    egal([id_test('carte', 1)], array_column($d['trello']['cartes'], 'id'));
    egal(['recupere_le' => null, 'erreur' => null, 'google_connecte' => false, 'jours' => []], $d['agenda']);
});
