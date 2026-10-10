<?php
declare(strict_types=1);

const POST_CARTE = 'POST https://api.trello.com/1/cards';
const LISTE_A_FAIRE = 'GET https://api.trello.com/1/lists/';

function ajouter(int $id_client, array $champs): array
{
    return appeler('POST', 'taches/ajouter', ['id_client' => uuid_test($id_client)] + $champs);
}

test("ajouter : carte en haut de « À faire », étiquette Urgent, échéance d'une date seule à 18 h 00 à Paris, copie et action", function () {
    base_de_test();
    fixer_heure('2026-10-09 08:00:00');
    copie_trello([]);
    $ids = fabrique_trello_ids()['tableaux']['etudes'];
    faux_http([POST_CARTE => ['json' => carte_trello('etudes', 'À faire', 5, ['name' => 'Réviser le chapitre 3', 'due' => '2026-10-20T16:00:00.000Z', 'idLabels' => [$ids['etiquettes']['Urgent']], 'pos' => 512])]]);
    $r = ajouter(1, ['domaine' => 'etudes', 'titre' => 'Réviser le chapitre 3', 'urgent' => true, 'echeance' => '2026-10-20']);
    egal(200, $r['statut']);
    egal([POST_CARTE], appels_http());
    egal(['idList' => $ids['colonnes']['À faire'], 'name' => 'Réviser le chapitre 3', 'pos' => 'top', 'idLabels' => [$ids['etiquettes']['Urgent']], 'due' => '2026-10-20T16:00:00.000Z'],
        json_decode((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], true));
    $carte = corps($r)['carte'];
    egal([id_test('carte', 5), 'etudes', 'a_faire', true, '2026-10-20T18:00:00+02:00'], [$carte['id'], $carte['domaine'], $carte['colonne'], $carte['urgent'], $carte['echeance']]);
    egal([id_test('carte', 5)], array_column(copie_lire('trello')['contenu']['cartes'], 'id'));
    $action = db()->query('SELECT type, domaine, carte_id, donnees FROM actions')->fetch();
    egal(['carte_ajoutee', 'etudes', id_test('carte', 5)], [$action['type'], $action['domaine'], $action['carte_id']]);
    $donnees = json_decode($action['donnees'], true);
    ksort($donnees);
    egal(['echeance' => '2026-10-20T18:00:00+02:00', 'urgent' => true], $donnees);
});

test("ajouter : une échéance avec heure après le passage à l'heure d'hiver ; sans échéance ni Urgent, ni due ni étiquette", function () {
    base_de_test();
    copie_trello([]);
    faux_http([POST_CARTE => ['json' => carte_trello('perso', 'À faire', 6)]]);
    ajouter(1, ['domaine' => 'perso', 'titre' => 'Dentiste', 'echeance' => '2026-10-27T08:30']);
    egal('2026-10-27T07:30:00.000Z', json_decode((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], true)['due']);
    ajouter(2, ['domaine' => 'perso', 'titre' => 'Rappeler Sébastien', 'urgent' => false, 'echeance' => null]);
    egal(['idList', 'name', 'pos'], array_keys(json_decode((string) $GLOBALS['JR_APPELS_HTTP'][1]['corps'], true)));
});

test("ajouter : la date seule du 27/10 tombe à 18 h à Paris, soit 17 h UTC", function () {
    egal('2026-10-27 17:00:00', utc(echeance_lire('2026-10-27')));
    egal('2026-10-20 16:00:00', utc(echeance_lire('2026-10-20')));
    egal(null, echeance_lire(null));
    egal(null, echeance_lire(''));
});

test('ajouter : titre nettoyé (espaces et retours à la ligne), emoji et guillemets gardés', function () {
    base_de_test();
    copie_trello([]);
    faux_http([POST_CARTE => ['json' => carte_trello('asso', 'À faire', 7)]]);
    ajouter(1, ['domaine' => 'asso', 'titre' => "  Appeler\n  le « Cabaret » 🎭  "]);
    egal('Appeler le « Cabaret » 🎭', json_decode((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], true)['name']);
});

test("ajouter : domaine, titre, urgent ou échéance invalides donnent 400, sans appel à Trello", function () {
    base_de_test();
    copie_trello([]);
    faux_http([]);
    $cas = [
        [['domaine' => 'courses', 'titre' => 'Lait'], 'requete_invalide'],
        [['titre' => 'Sans domaine'], 'requete_invalide'],
        [['domaine' => 'perso', 'titre' => 42], 'requete_invalide'],
        [['domaine' => 'perso', 'titre' => 'X', 'urgent' => 'oui'], 'requete_invalide'],
        [['domaine' => 'perso', 'titre' => '   '], 'titre_invalide'],
        [['domaine' => 'perso', 'titre' => str_repeat('é', 301)], 'titre_invalide'],
        [['domaine' => 'perso', 'titre' => 'X', 'echeance' => '2026-02-30'], 'echeance_invalide'],
        [['domaine' => 'perso', 'titre' => 'X', 'echeance' => '21/10/2026'], 'echeance_invalide'],
        [['domaine' => 'perso', 'titre' => 'X', 'echeance' => '2026-10-21T25:00'], 'echeance_invalide'],
        [['domaine' => 'perso', 'titre' => 'X', 'echeance' => 20261021], 'echeance_invalide'],
    ];
    foreach ($cas as $i => [$champs, $code]) {
        $r = ajouter($i + 1, $champs);
        egal([400, $code], [$r['statut'], corps($r)['erreur']['code']], json_encode($champs));
    }
    egal('Il me faut un titre (300 caractères au plus).', corps(ajouter(50, ['domaine' => 'perso', 'titre' => '']))['erreur']['message']);
    egal('Date illisible.', corps(ajouter(51, ['domaine' => 'perso', 'titre' => 'X', 'echeance' => 'demain']))['erreur']['message']);
    egal([], appels_http());
});

test('ajouter : un renvoi de la capture depuis la file hors ligne ne crée pas de seconde carte', function () {
    base_de_test();
    copie_trello([]);
    faux_http([POST_CARTE => ['json' => carte_trello('qyris', 'À faire', 8)]]);
    $premiere = corps(ajouter(1, ['domaine' => 'qyris', 'titre' => 'Relancer Sébastien']));
    $renvoi = corps(ajouter(1, ['domaine' => 'qyris', 'titre' => 'Relancer Sébastien']));
    egal($premiere['carte']['id'], $renvoi['carte']['id']);
    egal(1, count(appels_http()));
    egal(1, count(copie_lire('trello')['contenu']['cartes']));
    egal(1, (int) db()->query('SELECT COUNT(*) FROM actions')->fetchColumn());
});

test('ajouter : en écritures simulées, la carte n\'existe que dans la copie, avec un identifiant au format Trello, en haut de « À faire »', function () {
    base_de_test();
    $GLOBALS['JR_CONFIG'] = config_de_test(['trello' => ['ecritures' => 'simulees']]);
    copie_trello([carte_normalisee(['id' => id_test('carte', 1), 'domaine' => 'perso', 'colonne' => 'a_faire', 'position' => 4000.0])]);
    faux_http([]);
    $carte = corps(ajouter(1, ['domaine' => 'perso', 'titre' => 'Essai', 'urgent' => true]))['carte'];
    egal([], appels_http());
    vrai((bool) preg_match(JR_REGEX_ID_TRELLO, $carte['id']), 'identifiant au format Trello attendu');
    egal([2000.0, true, 'a_faire'], [(float) $carte['position'], $carte['urgent'], $carte['colonne']]);
    egal([$carte['id'], id_test('carte', 1)], array_column(copie_lire('trello')['contenu']['cartes'], 'id'));
});

test("ajouter : réponse de Trello perdue (délai dépassé), le renvoi retrouve la carte créée au lieu d'en créer une seconde", function () {
    base_de_test();
    fixer_heure('2026-10-10 08:00:00');
    copie_trello([]);
    faux_http([POST_CARTE => new ErreurReseau('délai dépassé')]);
    egal(502, ajouter(1, ['domaine' => 'etudes', 'titre' => 'Réviser le chapitre 3'])['statut']);
    fixer_heure('2026-10-10 08:00:30');
    faux_http([LISTE_A_FAIRE => ['json' => [carte_trello('etudes', 'À faire', 5, ['name' => 'Réviser le chapitre 3'])]]]);
    $r = ajouter(1, ['domaine' => 'etudes', 'titre' => 'Réviser le chapitre 3']);
    egal(200, $r['statut']);
    vrai(str_starts_with(appels_http()[0], LISTE_A_FAIRE . fabrique_trello_ids()['tableaux']['etudes']['colonnes']['À faire'] . '/cards'), 'la liste « À faire » des Études est lue');
    egal([], array_values(array_filter(appels_http(), fn(string $a): bool => str_starts_with($a, 'POST '))));
    egal(id_test('carte', 5), corps($r)['carte']['id']);
    egal([id_test('carte', 5)], array_column(copie_lire('trello')['contenu']['cartes'], 'id'));
    egal(['carte_ajoutee'], db()->query('SELECT type FROM actions')->fetchAll(PDO::FETCH_COLUMN));
    egal(0, (int) db()->query('SELECT COUNT(*) FROM tentatives')->fetchColumn());
});

test("ajouter : au renvoi, sans carte de ce titre créée depuis la tentative (même titre mais ancienne, ou autre titre), la carte est créée", function () {
    base_de_test();
    fixer_heure('2026-10-10 08:00:00');
    copie_trello([]);
    faux_http([POST_CARTE => new ErreurReseau('délai dépassé')]);
    ajouter(1, ['domaine' => 'perso', 'titre' => 'Dentiste']);
    // Les 8 premiers caractères de l'identifiant : créée en 2020, ce n'est pas celle de la tentative
    $ancienne = carte_trello('perso', 'À faire', 6, ['id' => '5f0000000000000000000006', 'name' => 'Dentiste']);
    faux_http([
        LISTE_A_FAIRE => ['json' => [$ancienne, carte_trello('perso', 'À faire', 7, ['name' => 'Autre chose'])]],
        POST_CARTE => ['json' => carte_trello('perso', 'À faire', 8, ['name' => 'Dentiste'])],
    ]);
    $r = ajouter(1, ['domaine' => 'perso', 'titre' => 'Dentiste']);
    egal(200, $r['statut']);
    egal(id_test('carte', 8), corps($r)['carte']['id']);
    egal(1, count(array_filter(appels_http(), fn(string $a): bool => str_starts_with($a, 'POST '))));
});

test('ajouter : en écritures simulées (dev), ni tentative ni lecture de la liste', function () {
    base_de_test();
    $GLOBALS['JR_CONFIG'] = config_de_test(['trello' => ['ecritures' => 'simulees']]);
    copie_trello([]);
    faux_http([]);
    egal(200, ajouter(1, ['domaine' => 'asso', 'titre' => 'Essai'])['statut']);
    egal(0, (int) db()->query('SELECT COUNT(*) FROM tentatives')->fetchColumn());
});
