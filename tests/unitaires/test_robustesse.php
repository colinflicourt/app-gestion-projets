<?php
declare(strict_types=1);

test("robustesse : le verrou de la copie Trello est tenu pendant chaque écriture vers Trello, puis rendu", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello([carte_a_cocher(), carte_normalisee(['id' => id_test('carte', 2), 'colonne' => 'a_faire'])]);
    $vu = [];
    // Un rafraîchissement venu d'une autre requête ne doit pas pouvoir lire Trello entre l'écriture et sa notation
    $GLOBALS['JR_CLIENT_HTTP'] = function (string $methode, string $url) use (&$vu): array {
        $vu[] = [$methode, verrou_libre('trello')];
        return ['statut' => 200, 'corps' => $methode === 'POST' ? (string) json_encode(carte_trello('perso', 'À faire', 7)) : '{}'];
    };
    cocher(1);
    appeler('POST', 'taches/terminer', ['id_client' => uuid_test(2), 'carte' => id_test('carte', 2)]);
    appeler('POST', 'taches/annuler', ['id_client' => uuid_test(3), 'id_client_origine' => uuid_test(2)]);
    appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(4), 'carte' => id_test('carte', 1), 'colonne' => 'en_attente']);
    appeler('POST', 'taches/ajouter', ['id_client' => uuid_test(5), 'domaine' => 'perso', 'titre' => 'Essai']);
    egal([['PUT', false], ['PUT', false], ['PUT', false], ['PUT', false], ['POST', false]], $vu);
    vrai(verrou_libre('trello'), 'verrou rendu');
});

test("robustesse : carte ou étape supprimée dans Trello mais encore dans la copie : 404, carte retirée de la copie, rien de noté", function () {
    base_de_test();
    copie_trello([carte_a_cocher(), carte_normalisee(['id' => id_test('carte', 2), 'colonne' => 'a_faire'])]);
    faux_http(['PUT https://api.trello.com/1/cards/' => ['statut' => 404, 'corps' => 'The requested resource was not found.']]);
    $r = cocher(1);
    egal([404, 'element_introuvable', "Cette étape n'existe plus dans Trello."], [$r['statut'], corps($r)['erreur']['code'], corps($r)['erreur']['message']]);
    $r = appeler('POST', 'taches/terminer', ['id_client' => uuid_test(2), 'carte' => id_test('carte', 2)]);
    egal([404, 'carte_introuvable', "Cette carte n'existe plus dans Trello."], [$r['statut'], corps($r)['erreur']['code'], corps($r)['erreur']['message']]);
    egal([id_test('carte', 1)], array_column(copie_lire('trello')['contenu']['cartes'], 'id'));
    faux_http(['PUT https://api.trello.com/1/cards/' => ['statut' => 400, 'corps' => 'invalid id']]);
    egal(404, appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(3), 'carte' => id_test('carte', 1), 'colonne' => 'a_faire'])['statut']);
    egal([], copie_lire('trello')['contenu']['cartes']);
    egal(0, nombre_actions());
    egal(null, ecriture_trouver(uuid_test(2)));
    // Une panne de Trello reste une 502 : l'iPhone réessaiera
    copie_trello([carte_a_cocher()]);
    faux_http(['PUT https://api.trello.com/1/cards/' => ['statut' => 500, 'corps' => 'oops']]);
    egal([502, 'trello_indisponible'], [cocher(4)['statut'], corps(cocher(5))['erreur']['code']]);
});

test("robustesse : verrou de la copie occupé trop longtemps : GET donnees rend la dernière copie et trello_indisponible, une écriture rend 502, jamais une 500", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    faux_http([BATCH => ['json' => batch_trello(['perso' => [carte_trello('perso', 'À faire', 1)]])]]);
    appeler('GET', 'donnees');
    fixer_heure('2026-10-09 10:05:00');
    $autre = nouvelle_connexion_test();
    $autre->query("SELECT GET_LOCK('jr_source_trello', 0)");
    $GLOBALS['JR_VERROU_ATTENTE'] = 0;
    faux_http([]);
    $r = appeler('GET', 'donnees');
    egal(200, $r['statut']);
    $t = corps($r)['trello'];
    egal(['trello_indisponible', '2026-10-09T12:00:00+02:00', [id_test('carte', 1)]], [$t['erreur'], $t['recupere_le'], array_column($t['cartes'], 'id')]);
    $e = appeler('POST', 'taches/terminer', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 1)]);
    egal([502, 'trello_indisponible'], [$e['statut'], corps($e)['erreur']['code']]);
    egal([], appels_http());
    $autre->query("SELECT RELEASE_LOCK('jr_source_trello')");
});
