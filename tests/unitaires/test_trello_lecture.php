<?php
declare(strict_types=1);

test("Trello : un seul appel /batch pour les 4 tableaux, clé et jeton dans l'en-tête et jamais dans l'adresse", function () {
    faux_http(['GET https://api.trello.com/1/batch' => ['json' => batch_trello()]]);
    egal(['cartes' => []], trello_lire());
    egal(1, count($GLOBALS['JR_APPELS_HTTP']));
    $appel = $GLOBALS['JR_APPELS_HTTP'][0];
    vrai(in_array('Authorization: OAuth oauth_consumer_key="cle-de-test", oauth_token="jeton-de-test"', $appel['entetes'], true), 'en-tête Authorization attendu');
    vrai(!str_contains($appel['url'], 'cle-de-test') && !str_contains($appel['url'], 'jeton-de-test'), "secrets dans l'adresse");
    parse_str((string) parse_url($appel['url'], PHP_URL_QUERY), $p);
    $adresses = explode(',', $p['urls']);
    egal(4, count($adresses));
    egal('/boards/' . id_test('tableau', 1) . '?fields=name&lists=open&cards=open&checklists=all&labels=all', $adresses[0]);
    egal('/boards/' . id_test('tableau', 4) . '?fields=name&lists=open&cards=open&checklists=all&labels=all', $adresses[3]);
});

test("Trello : une carte est mise au format de l'iPhone (échéance à l'heure de Paris, Urgent à part, checklists dans l'ordre)", function () {
    $ids = fabrique_trello_ids()['tableaux']['etudes'];
    $carte = carte_trello('etudes', 'En cours', 7, [
        'name' => 'MT23 : préparer le médian',
        'desc' => "Chapitres 1 à 3\nhttps://moodle.utc.fr",
        'due' => '2026-10-21T17:15:00.000Z',
        'idLabels' => [$ids['etiquettes']['Urgent'], $ids['etiquettes']['MT23']],
        'labels' => [['id' => $ids['etiquettes']['Urgent'], 'name' => 'Urgent'], ['id' => $ids['etiquettes']['MT23'], 'name' => 'MT23'], ['id' => id_test('autre', 9), 'name' => '']],
        'dateLastActivity' => '2026-10-08T13:40:00.000Z',
    ]);
    $deuxieme = checklist_trello($carte['id'], 2, ['Annales A24' => false]);
    $premiere = checklist_trello($carte['id'], 1, ['Chapitre 1' => true, 'Chapitre 2' => false]);
    $premiere['checkItems'] = array_reverse($premiere['checkItems']);  // Trello ne garantit pas l'ordre
    faux_http(['GET https://api.trello.com/1/batch' => ['json' => batch_trello(['etudes' => [$carte]], ['etudes' => [$deuxieme, $premiere]])]]);
    egal(['cartes' => [[
        'id' => id_test('carte', 7),
        'domaine' => 'etudes',
        'colonne' => 'en_cours',
        'position' => 7000.0,
        'titre' => 'MT23 : préparer le médian',
        'description' => "Chapitres 1 à 3\nhttps://moodle.utc.fr",
        'url' => 'https://trello.com/c/test7',
        'echeance' => '2026-10-21T19:15:00+02:00',
        'echeance_faite' => false,
        'urgent' => true,
        'etiquettes' => ['MT23'],
        'checklists' => [
            ['id' => id_test('checklist', 1), 'nom' => 'Étapes', 'elements' => [
                ['id' => id_test('element', 11), 'nom' => 'Chapitre 1', 'coche' => true],
                ['id' => id_test('element', 12), 'nom' => 'Chapitre 2', 'coche' => false],
            ]],
            ['id' => id_test('checklist', 2), 'nom' => 'Étapes', 'elements' => [
                ['id' => id_test('element', 21), 'nom' => 'Annales A24', 'coche' => false],
            ]],
        ],
        'derniere_activite' => '2026-10-08T15:40:00+02:00',
    ]]], trello_lire());
});

test('Trello : cartes rangées par domaine puis par position ; colonne inconnue et carte archivée ignorées', function () {
    faux_http(['GET https://api.trello.com/1/batch' => ['json' => batch_trello([
        'perso' => [carte_trello('perso', 'À faire', 4)],
        'qyris' => [
            carte_trello('qyris', 'Fait', 3),
            carte_trello('qyris', 'À faire', 1, ['pos' => 99999]),
            carte_trello('qyris', 'En cours', 2, ['idList' => id_test('autre', 5)]),
        ],
        'asso' => [carte_trello('asso', 'En attente', 5, ['closed' => true])],
    ])]]);
    $cartes = trello_lire()['cartes'];
    egal([id_test('carte', 3), id_test('carte', 1), id_test('carte', 4)], array_column($cartes, 'id'));
    egal(['fait', 'a_faire', 'a_faire'], array_column($cartes, 'colonne'));
});

test('Trello : panne réseau, refus ou réponse de /batch inattendue donnent ErreurSource « trello_indisponible »', function () {
    $cas = [
        new ErreurReseau('délai dépassé'),
        ['statut' => 401, 'corps' => 'invalid token'],
        ['statut' => 429, 'corps' => 'trop de requêtes'],
        ['statut' => 500, 'corps' => 'oops'],
        ['json' => [['404' => 'not found'], [], [], []]],
        ['json' => []],
        ['corps' => 'pas du JSON'],
    ];
    foreach ($cas as $i => $reponse) {
        faux_http(['GET https://api.trello.com/1/batch' => $reponse]);
        egal('trello_indisponible', leve(ErreurSource::class, fn() => trello_lire())->codeErreur, "cas $i");
    }
});

test('Trello : correspondance entre colonnes Trello et codes, dans les deux sens', function () {
    $ids = fabrique_trello_ids()['tableaux']['asso']['colonnes'];
    egal([$ids['À faire'] => 'a_faire', $ids['En cours'] => 'en_cours', $ids['En attente'] => 'en_attente', $ids['Fait'] => 'fait'], trello_colonnes('asso'));
    egal($ids['Fait'], trello_id_colonne('asso', 'fait'));
    leve(InvalidArgumentException::class, fn() => trello_id_colonne('asso', 'poubelle'));
});
