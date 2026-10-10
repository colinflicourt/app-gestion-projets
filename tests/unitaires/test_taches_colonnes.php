<?php
declare(strict_types=1);

const PUT_CARTE = 'PUT https://api.trello.com/1/cards/';

function deux_cartes_etudes(): array
{
    return [
        carte_normalisee(['id' => id_test('carte', 1), 'colonne' => 'en_cours', 'position' => 5000.0, 'urgent' => true, 'echeance' => '2026-10-21T19:15:00+02:00']),
        carte_normalisee(['id' => id_test('carte', 2), 'colonne' => 'fait', 'position' => 4000.0]),
    ];
}

function carte_de_la_copie(string $id): array
{
    return array_column(copie_lire('trello')['contenu']['cartes'], null, 'id')[$id];
}

test("terminer : carte en haut de Fait, échéance marquée faite, copie corrigée, carte_terminee notée avec urgent et échéance", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello(deux_cartes_etudes());
    faux_http([PUT_CARTE => ['json' => []]]);
    $r = corps(appeler('POST', 'taches/terminer', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 1)]));
    egal(['fait', 'en_cours', 5000.0, false], [$r['colonne'], $r['colonne_avant'], (float) $r['position_avant'], $r['echeance_faite_avant']]);
    egal(['PUT https://api.trello.com/1/cards/' . id_test('carte', 1)], appels_http());
    egal(['idList' => fabrique_trello_ids()['tableaux']['etudes']['colonnes']['Fait'], 'pos' => 'top', 'dueComplete' => true], json_decode((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], true));
    $copie = carte_de_la_copie(id_test('carte', 1));
    egal(['fait', 2000.0, true, '2026-10-09T12:00:00+02:00'], [$copie['colonne'], (float) $copie['position'], $copie['echeance_faite'], $copie['derniere_activite']]);
    egal([id_test('carte', 1), id_test('carte', 2)], array_column(copie_lire('trello')['contenu']['cartes'], 'id'), 'ordre par position');
    $action = db()->query('SELECT type, domaine, source, donnees FROM actions')->fetch();
    egal(['carte_terminee', 'etudes', 'app'], [$action['type'], $action['domaine'], $action['source']]);
    $donnees = json_decode($action['donnees'], true);
    ksort($donnees);
    egal(['echeance' => '2026-10-21T19:15:00+02:00', 'urgent' => true], $donnees);
});

test("terminer : sans échéance, pas de dueComplete ; déjà dans Fait, 409 sans appel", function () {
    base_de_test();
    copie_trello([carte_normalisee(['id' => id_test('carte', 3), 'colonne' => 'a_faire']), carte_normalisee(['id' => id_test('carte', 4), 'colonne' => 'fait'])]);
    faux_http([PUT_CARTE => ['json' => []]]);
    appeler('POST', 'taches/terminer', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 3)]);
    egal(['idList', 'pos'], array_keys(json_decode((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], true)));
    $r = appeler('POST', 'taches/terminer', ['id_client' => uuid_test(2), 'carte' => id_test('carte', 4)]);
    egal([409, 'deja_faite', 'Cette carte est déjà terminée.'], [$r['statut'], corps($r)['erreur']['code'], corps($r)['erreur']['message']]);
    egal(1, count(appels_http()));
});

test("annuler : dans les 10 s, la carte revient à sa colonne, sa position et son échéance d'avant, et l'action est annulée", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello(deux_cartes_etudes());
    faux_http([PUT_CARTE => ['json' => []]]);
    appeler('POST', 'taches/terminer', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 1)]);
    fixer_heure('2026-10-09 10:00:08');
    $r = corps(appeler('POST', 'taches/annuler', ['id_client' => uuid_test(2), 'id_client_origine' => uuid_test(1)]));
    egal([id_test('carte', 1), 'en_cours'], [$r['carte'], $r['colonne']]);
    $corps = json_decode((string) $GLOBALS['JR_APPELS_HTTP'][1]['corps'], true);
    egal([fabrique_trello_ids()['tableaux']['etudes']['colonnes']['En cours'], 5000.0, false], [$corps['idList'], (float) $corps['pos'], $corps['dueComplete']]);
    $copie = carte_de_la_copie(id_test('carte', 1));
    egal(['en_cours', 5000.0, false], [$copie['colonne'], (float) $copie['position'], $copie['echeance_faite']]);
    egal(1, (int) db()->query("SELECT annulee FROM actions WHERE type = 'carte_terminee'")->fetchColumn());
});

test("annuler : après 10 s, trop tard (409) ; origine inconnue ou d'une autre route, rien à annuler (404)", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello(deux_cartes_etudes() + [2 => carte_a_cocher_colonnes()]);
    faux_http([PUT_CARTE => ['json' => []]]);
    appeler('POST', 'taches/terminer', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 1)]);
    appeler('POST', 'taches/cocher', ['id_client' => uuid_test(2), 'carte' => id_test('carte', 9), 'element' => id_test('element', 91), 'coche' => true]);
    fixer_heure('2026-10-09 10:00:11');
    $tard = appeler('POST', 'taches/annuler', ['id_client' => uuid_test(3), 'id_client_origine' => uuid_test(1)]);
    egal([409, 'annulation_trop_tard', 'Trop tard pour annuler.'], [$tard['statut'], corps($tard)['erreur']['code'], corps($tard)['erreur']['message']]);
    foreach ([uuid_test(2), uuid_test(77), 'pas-un-uuid'] as $i => $origine) {
        $r = appeler('POST', 'taches/annuler', ['id_client' => uuid_test(10 + $i), 'id_client_origine' => $origine]);
        egal([404, 'annulation_impossible', 'Rien à annuler.'], [$r['statut'], corps($r)['erreur']['code'], corps($r)['erreur']['message']], $origine);
    }
    egal(400, appeler('POST', 'taches/annuler', ['id_client' => uuid_test(20), 'id_client_origine' => 5])['statut']);
    egal(0, (int) db()->query('SELECT COUNT(*) FROM actions WHERE annulee = 1')->fetchColumn());
});

test('déplacer : vers une autre colonne en haut ; même colonne sans appel ; sortir de Fait note carte_rouverte ; Fait et colonne inconnue refusés', function () {
    base_de_test();
    copie_trello(deux_cartes_etudes());
    faux_http([PUT_CARTE => ['json' => []]]);
    egal(['carte' => id_test('carte', 1), 'colonne' => 'en_attente'], array_diff_key(corps(appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 1), 'colonne' => 'en_attente'])), ['ok' => 1]));
    egal(['idList' => fabrique_trello_ids()['tableaux']['etudes']['colonnes']['En attente'], 'pos' => 'top'], json_decode((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], true));
    egal('en_attente', carte_de_la_copie(id_test('carte', 1))['colonne']);
    appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(2), 'carte' => id_test('carte', 1), 'colonne' => 'en_attente']);
    egal(1, count(appels_http()));
    appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(3), 'carte' => id_test('carte', 2), 'colonne' => 'a_faire']);
    egal(['carte_rouverte'], db()->query('SELECT type FROM actions')->fetchAll(PDO::FETCH_COLUMN));
    foreach (['fait', 'poubelle', null] as $i => $colonne) {
        egal(400, appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(10 + $i), 'carte' => id_test('carte', 1), 'colonne' => $colonne])['statut'], (string) $colonne);
    }
});

test("terminer et déplacer : une carte d'un autre tableau du compte est refusée (403) sans écriture", function () {
    base_de_test();
    copie_trello([]);
    $courses = id_test('carte', 99);
    faux_http(['GET https://api.trello.com/1/cards/' . $courses => ['json' => ['id' => $courses, 'idBoard' => id_test('autre', 2), 'idList' => id_test('autre', 3), 'name' => 'Lait', 'pos' => 1, 'dateLastActivity' => '2026-10-09T08:00:00.000Z']]]);
    egal(403, appeler('POST', 'taches/terminer', ['id_client' => uuid_test(1), 'carte' => $courses])['statut']);
    egal(403, appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(2), 'carte' => $courses, 'colonne' => 'en_cours'])['statut']);
    egal([], array_values(array_filter(appels_http(), fn(string $a): bool => !str_starts_with($a, 'GET '))));
});

function carte_a_cocher_colonnes(): array
{
    return carte_normalisee(['id' => id_test('carte', 9), 'colonne' => 'a_faire', 'checklists' => [checklist_normalisee(9, [false])]]);
}

test("déplacer : une carte sortie de Fait avec une échéance la rouvre (dueComplete false), pour qu'elle puisse être en retard", function () {
    base_de_test();
    copie_trello([carte_normalisee(['id' => id_test('carte', 3), 'colonne' => 'fait', 'echeance' => '2026-10-06T08:30:00+02:00', 'echeance_faite' => true])]);
    faux_http([PUT_CARTE => ['json' => []]]);
    egal(200, appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 3), 'colonne' => 'a_faire'])['statut']);
    egal(['idList' => fabrique_trello_ids()['tableaux']['etudes']['colonnes']['À faire'], 'pos' => 'top', 'dueComplete' => false], json_decode((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], true));
    $carte = carte_de_la_copie(id_test('carte', 3));
    egal(['a_faire', false], [$carte['colonne'], $carte['echeance_faite']]);
});
