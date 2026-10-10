<?php
declare(strict_types=1);

const CHECK_ITEM = 'PUT https://api.trello.com/1/cards/';

function carte_a_cocher(): array
{
    return carte_normalisee(['id' => id_test('carte', 1), 'domaine' => 'qyris', 'colonne' => 'en_cours', 'checklists' => [checklist_normalisee(1, [false, true])]]);
}

function cocher(int $id_client, array $champs = []): array
{
    return appeler('POST', 'taches/cocher', $champs + ['id_client' => uuid_test($id_client), 'carte' => id_test('carte', 1), 'element' => id_test('element', 11), 'coche' => true]);
}

function nombre_actions(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM actions')->fetchColumn();
}

test("tâches : cocher envoie l'état à Trello, corrige la copie et note etape_cochee", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello([carte_a_cocher()]);
    faux_http([CHECK_ITEM => ['json' => ['id' => id_test('element', 11)]]]);
    $r = cocher(1);
    egal(200, $r['statut']);
    egal(['ok' => true, 'carte' => id_test('carte', 1), 'element' => id_test('element', 11), 'coche' => true], corps($r));
    egal(['PUT https://api.trello.com/1/cards/' . id_test('carte', 1) . '/checkItem/' . id_test('element', 11)], appels_http());
    egal(['state' => 'complete'], json_decode((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], true));
    egal(true, copie_lire('trello')['contenu']['cartes'][0]['checklists'][0]['elements'][0]['coche']);
    $action = db()->query('SELECT type, domaine, carte_id, element_id, quand, source, precision_heure FROM actions')->fetch();
    egal(['type' => 'etape_cochee', 'domaine' => 'qyris', 'carte_id' => id_test('carte', 1), 'element_id' => id_test('element', 11), 'quand' => '2026-10-09 10:00:00', 'source' => 'app', 'precision_heure' => 'exacte'], $action);
});

test("tâches : décocher envoie « incomplete » et note etape_decochee ; un état inchangé part vers Trello sans nouvelle action", function () {
    base_de_test();
    copie_trello([carte_a_cocher()]);
    faux_http([CHECK_ITEM => ['json' => []]]);
    cocher(1, ['element' => id_test('element', 12), 'coche' => false]);
    egal(['state' => 'incomplete'], json_decode((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], true));
    cocher(2, ['element' => id_test('element', 12), 'coche' => false]);
    egal(2, count(appels_http()));
    egal(['etape_decochee'], db()->query('SELECT type FROM actions')->fetchAll(PDO::FETCH_COLUMN));
});

test('tâches : un renvoi depuis la file hors ligne rend le même résultat sans second appel ni seconde action', function () {
    base_de_test();
    copie_trello([carte_a_cocher()]);
    faux_http([CHECK_ITEM => ['json' => []]]);
    $premiere = corps(cocher(1));
    $renvoi = corps(cocher(1));
    ksort($premiere);
    ksort($renvoi);
    egal($premiere, $renvoi);
    egal(1, count(appels_http()));
    egal(1, nombre_actions());
});

test("tâches : une carte d'un autre tableau du compte (Courses…) est refusée (403) sans aucune écriture", function () {
    base_de_test();
    copie_trello([]);
    $courses = id_test('carte', 99);
    faux_http(['GET https://api.trello.com/1/cards/' . $courses => ['json' => ['id' => $courses, 'idBoard' => id_test('autre', 2), 'idList' => id_test('autre', 3), 'name' => 'Lait', 'pos' => 1, 'dateLastActivity' => '2026-10-09T08:00:00.000Z', 'checklists' => []]]]);
    $routes = ['taches/cocher' => ['element' => id_test('element', 1), 'coche' => true], 'tirage/parti' => ['element' => null]];
    $n = 0;
    foreach ($routes as $route => $champs) {
        $r = appeler('POST', $route, ['id_client' => uuid_test(++$n), 'carte' => $courses] + $champs);
        egal(403, $r['statut'], $route);
        egal('carte_refusee', corps($r)['erreur']['code'], $route);
    }
    egal([], array_values(array_filter(appels_http(), fn(string $a): bool => !str_starts_with($a, 'GET '))), 'aucune écriture attendue');
    egal(0, nombre_actions());
});

test('tâches : identifiants forgés ou valeurs de mauvais type donnent 400, sans appel à Trello', function () {
    base_de_test();
    copie_trello([carte_a_cocher()]);
    faux_http([]);
    $mauvais = [
        ['carte' => '../boards/x'], ['carte' => str_repeat('a', 10000)], ['carte' => 123], ['carte' => null],
        ['element' => 'abc'], ['element' => ['x']], ['coche' => 'oui'], ['coche' => 1],
    ];
    foreach ($mauvais as $i => $champs) {
        egal(400, cocher($i + 1, $champs)['statut'], json_encode($champs));
    }
    egal(400, appeler('POST', 'taches/cocher', ['carte' => id_test('carte', 1)])['statut'], 'sans id_client');
    egal(400, appeler('POST', 'taches/cocher', ['id_client' => 42])['statut'], 'id_client non texte');
    egal([], appels_http());
});

test("tâches : une carte absente de la copie est lue dans Trello ; dans l'un des 4 tableaux, l'écriture passe ; disparue, 404", function () {
    base_de_test();
    copie_trello([]);
    $carte = carte_trello('asso', 'À faire', 8);
    $carte['checklists'] = [checklist_trello($carte['id'], 8, ['Réserver la salle' => false])];
    faux_http(['GET https://api.trello.com/1/cards/' . $carte['id'] => ['json' => $carte], CHECK_ITEM => ['json' => []]]);
    $r = appeler('POST', 'taches/cocher', ['id_client' => uuid_test(1), 'carte' => $carte['id'], 'element' => id_test('element', 81), 'coche' => true]);
    egal(200, $r['statut']);
    egal(['GET', 'PUT'], array_column($GLOBALS['JR_APPELS_HTTP'], 'methode'));
    egal(['asso'], db()->query('SELECT domaine FROM actions')->fetchAll(PDO::FETCH_COLUMN));
    faux_http(['GET https://api.trello.com/1/cards/' => ['statut' => 404, 'corps' => 'The requested resource was not found.']]);
    $r = appeler('POST', 'taches/cocher', ['id_client' => uuid_test(2), 'carte' => id_test('carte', 50), 'element' => id_test('element', 1), 'coche' => true]);
    egal([404, 'carte_introuvable'], [$r['statut'], corps($r)['erreur']['code']]);
});

test("tâches : une étape inconnue de la carte donne 404 element_introuvable", function () {
    base_de_test();
    copie_trello([carte_a_cocher()]);
    faux_http([]);
    $r = cocher(1, ['element' => id_test('element', 99)]);
    egal([404, 'element_introuvable'], [$r['statut'], corps($r)['erreur']['code']]);
});

test("tâches : Trello en panne pendant l'écriture : 502 trello_indisponible, rien de gardé, un renvoi pourra réessayer", function () {
    base_de_test();
    copie_trello([carte_a_cocher()]);
    faux_http([CHECK_ITEM => new ErreurReseau('délai dépassé')]);
    $r = cocher(1);
    egal([502, 'trello_indisponible', 'Trello ne répond pas. Réessaie dans un instant.'], [$r['statut'], corps($r)['erreur']['code'], corps($r)['erreur']['message']]);
    egal(null, ecriture_trouver(uuid_test(1)));
    egal(0, nombre_actions());
    egal(false, copie_lire('trello')['contenu']['cartes'][0]['checklists'][0]['elements'][0]['coche']);
    faux_http([CHECK_ITEM => ['json' => []]]);
    egal(200, cocher(1)['statut']);
});

test('tâches : en écritures simulées (dev), rien ne part vers Trello, mais la copie et le journal suivent', function () {
    base_de_test();
    $GLOBALS['JR_CONFIG'] = config_de_test(['trello' => ['ecritures' => 'simulees']]);
    copie_trello([carte_a_cocher()]);
    faux_http([]);
    egal(200, cocher(1)['statut']);
    egal([], appels_http());
    egal(true, copie_lire('trello')['contenu']['cartes'][0]['checklists'][0]['elements'][0]['coche']);
    egal(1, nombre_actions());
});

test("tirage : « C'est parti » note tirage_parti avec l'étape, ou sans étape pour une carte entière", function () {
    base_de_test();
    copie_trello([carte_a_cocher()]);
    faux_http([]);
    egal(200, appeler('POST', 'tirage/parti', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 1), 'element' => id_test('element', 11)])['statut']);
    egal(200, appeler('POST', 'tirage/parti', ['id_client' => uuid_test(2), 'carte' => id_test('carte', 1), 'element' => null])['statut']);
    egal(400, appeler('POST', 'tirage/parti', ['id_client' => uuid_test(3), 'carte' => id_test('carte', 1), 'element' => 'abc'])['statut']);
    egal([['tirage_parti', id_test('element', 11)], ['tirage_parti', null]], array_map(fn(array $l): array => [$l['type'], $l['element_id']], db()->query('SELECT type, element_id FROM actions ORDER BY id')->fetchAll()));
    egal([], appels_http());
});

test("tirage : une étape qui n'appartient pas à la carte donne 404 element_introuvable, sans action", function () {
    base_de_test();
    copie_trello([carte_a_cocher()]);
    faux_http([]);
    $r = appeler('POST', 'tirage/parti', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 1), 'element' => id_test('element', 99)]);
    egal([404, 'element_introuvable'], [$r['statut'], corps($r)['erreur']['code']]);
    egal(0, nombre_actions());
});
