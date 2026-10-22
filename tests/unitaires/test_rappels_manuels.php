<?php
declare(strict_types=1);

function creer_rappel(int $n, array $champs): array
{
    return appeler('POST', 'rappels', ['id_client' => uuid_test($n)] + $champs);
}

function envoi_ligne(string $cle, string $type, string $prevu_pour_utc, string $titre, string $statut = 'prevu'): array
{
    return ['cle' => $cle, 'type' => $type, 'prevu_pour' => utc_test($prevu_pour_utc), 'titre' => $titre, 'statut' => $statut];
}

test("rappels manuels : création, réponse en heure de Paris, ligne en base en UTC, source app", function () {
    base_de_test();
    fixer_heure('2026-10-22 08:00:00');
    $r = creer_rappel(1, ['texte' => "  Appeler Sébastien \n", 'quand' => '2026-10-22T18:30:00+02:00']);
    egal(200, $r['statut']);
    $rappel = corps($r)['rappel'];
    egal([1, 'Appeler Sébastien', '2026-10-22T18:30:00+02:00'], [$rappel['id'], $rappel['texte'], $rappel['quand']]);
    $ligne = db()->query('SELECT id_client, texte, quand, cree_le, source, annule FROM rappels')->fetch();
    egal([uuid_test(1), 'Appeler Sébastien', '2026-10-22 16:30:00', '2026-10-22 08:00:00', 'app', 0], array_values($ligne));
});

test("rappels manuels : un décalage UTC (Z) est accepté et converti à l'heure de Paris", function () {
    base_de_test();
    fixer_heure('2026-10-22 08:00:00');
    egal('2026-10-22T19:00:00+02:00', corps(creer_rappel(1, ['texte' => 'x', 'quand' => '2026-10-22T17:00:00Z']))['rappel']['quand']);
});

test("rappels manuels : texte vide, trop long, ou quand illisible, passé, trop lointain donnent 400 sans rien écrire", function () {
    base_de_test();
    fixer_heure('2026-10-22 08:00:00');
    $bon = '2026-10-22T18:00:00+02:00';
    $cas = [
        ['texte' => '   ', 'quand' => $bon],
        ['texte' => str_repeat('a', 501), 'quand' => $bon],
        ['texte' => 12, 'quand' => $bon],
        ['quand' => $bon],
        ['texte' => 'x', 'quand' => 'demain'],
        ['texte' => 'x', 'quand' => '2026-10-22T18:00:00'],
        ['texte' => 'x', 'quand' => '2026-02-31T18:00:00+01:00'],
        ['texte' => 'x', 'quand' => null],
        ['texte' => 'x', 'quand' => '2026-10-22T09:57:00+02:00'],
        ['texte' => 'x', 'quand' => '2027-11-26T10:00:00+01:00'],
    ];
    foreach ($cas as $i => $champs) {
        $r = creer_rappel($i + 1, $champs);
        egal(400, $r['statut'], 'cas ' . $i);
    }
    egal(0, (int) db()->query('SELECT COUNT(*) FROM rappels')->fetchColumn());
});

test("rappels manuels : 500 caractères accentués et un rappel passé depuis 30 s sont acceptés", function () {
    base_de_test();
    fixer_heure('2026-10-22 08:00:00');
    egal(200, creer_rappel(1, ['texte' => str_repeat('é', 500), 'quand' => '2026-10-22T09:59:30+02:00'])['statut']);
});

test("rappels manuels : le même id_client deux fois ne crée qu'une ligne et rend la même réponse", function () {
    base_de_test();
    fixer_heure('2026-10-22 08:00:00');
    $a = creer_rappel(1, ['texte' => 'Une fois', 'quand' => '2026-10-23T09:00:00+02:00']);
    $b = creer_rappel(1, ['texte' => 'Une fois', 'quand' => '2026-10-23T09:00:00+02:00']);
    egal([1, 'Une fois', '2026-10-23T09:00:00+02:00'], [corps($b)['rappel']['id'], corps($b)['rappel']['texte'], corps($b)['rappel']['quand']]);
    egal(corps($a)['rappel']['id'], corps($b)['rappel']['id']);
    egal(1, (int) db()->query('SELECT COUNT(*) FROM rappels')->fetchColumn());
});

test("rappels manuels : sans session, la création est refusée", function () {
    base_de_test();
    $r = traiter(requete(['methode' => 'POST', 'chemin' => 'rappels', 'corps_brut' => '{}']));
    egal(401, $r['statut']);
});

test("rappels manuels : annuler met annule à 1 ; deux fois, c'est un succès ; id inconnu, 404 rappel_introuvable", function () {
    base_de_test();
    fixer_heure('2026-10-22 08:00:00');
    $id = corps(creer_rappel(1, ['texte' => 'x', 'quand' => '2026-10-23T09:00:00+02:00']))['rappel']['id'];
    $r = appeler('POST', 'rappels/annuler', ['id_client' => uuid_test(2), 'id' => $id]);
    egal(200, $r['statut']);
    egal(1, (int) db()->query('SELECT annule FROM rappels')->fetchColumn());
    egal(200, appeler('POST', 'rappels/annuler', ['id_client' => uuid_test(3), 'id' => $id])['statut']);
    $r = appeler('POST', 'rappels/annuler', ['id_client' => uuid_test(4), 'id' => 999]);
    egal([404, 'rappel_introuvable'], [$r['statut'], corps($r)['erreur']['code']]);
    egal(400, appeler('POST', 'rappels/annuler', ['id_client' => uuid_test(5), 'id' => 'abc'])['statut']);
});

test("rappels du jour : la journée de Paris du dimanche 25/10/2026 (heure d'hiver, 25 h), tri, passe, annulable, sans doublon ni annulé ni retiré", function () {
    $maintenant = utc_test('2026-10-25 08:00:00');  // 09:00 à Paris (UTC+1)
    $rappels = [
        ['id' => 3, 'texte' => 'Soir', 'quand' => utc_test('2026-10-25 22:59:00')],      // 23:59 Paris
        ['id' => 4, 'texte' => 'Minuit passé', 'quand' => utc_test('2026-10-24 22:00:00')], // 00:00 Paris le 25
        ['id' => 5, 'texte' => 'Demain', 'quand' => utc_test('2026-10-25 23:00:00')],       // 00:00 Paris le 26 : exclu
        ['id' => 6, 'texte' => 'Hier soir', 'quand' => utc_test('2026-10-24 21:59:00')],    // 23:59 Paris le 24 : exclu
        ['id' => 7, 'texte' => 'Matin', 'quand' => utc_test('2026-10-25 08:30:00')],        // 09:30 Paris
    ];
    $envois = [
        envoi_ligne('cours:1', 'cours', '2026-10-25 07:45:00', 'Cours dans 15 min', 'envoye'),
        envoi_ligne('brief:2026-10-25', 'brief', '2026-10-25 06:30:00', 'Brief du matin', 'envoye'),
        envoi_ligne('echeance:x', 'echeance', '2026-10-25 19:00:00', 'Échéance', 'prevu'),
        envoi_ligne('median:y', 'median', '2026-10-25 08:00:00', 'Médian', 'retire'),
        envoi_ligne('cours:z', 'cours', '2026-10-25 09:00:00', 'Cours raté', 'echoue'),
        envoi_ligne('manuel:7', 'manuel', '2026-10-25 08:30:00', 'Matin', 'prevu'),
        envoi_ligne('cours:hier', 'cours', '2026-10-24 12:00:00', 'Hier', 'envoye'),
    ];
    $liste = rappels_du_jour_construire($rappels, $envois, $maintenant);
    egal([4, 'brief:2026-10-25', 'cours:1', 7, 'echeance:x', 3], array_column($liste, 'id'));
    egal(['manuel', 'brief', 'cours', 'manuel', 'echeance', 'manuel'], array_column($liste, 'type'));
    egal(['2026-10-25T00:00:00+02:00', '2026-10-25T07:30:00+01:00', '2026-10-25T08:45:00+01:00', '2026-10-25T09:30:00+01:00', '2026-10-25T20:00:00+01:00', '2026-10-25T23:59:00+01:00'], array_column($liste, 'heure'));
    egal(['Minuit passé', 'Brief du matin', 'Cours dans 15 min', 'Matin', 'Échéance', 'Soir'], array_column($liste, 'titre'));
    egal([true, true, true, false, false, false], array_column($liste, 'passe'));
    egal([false, false, false, true, false, true], array_column($liste, 'annulable'));
    egal(['id', 'type', 'heure', 'titre', 'passe', 'annulable'], array_keys($liste[0]));
});

test("rappels du jour : lecture en base, un rappel créé est visible tout de suite, un annulé non", function () {
    base_de_test();
    fixer_heure('2026-10-25 08:00:00');
    creer_rappel(1, ['texte' => 'Visible', 'quand' => '2026-10-25T18:00:00+01:00']);
    $id2 = corps(creer_rappel(2, ['texte' => 'Annulé', 'quand' => '2026-10-25T19:00:00+01:00']))['rappel']['id'];
    creer_rappel(3, ['texte' => 'Demain', 'quand' => '2026-10-26T10:00:00+01:00']);
    appeler('POST', 'rappels/annuler', ['id_client' => uuid_test(9), 'id' => $id2]);
    db()->exec("INSERT INTO envois (cle, type, prevu_pour, titre, texte, url, statut) VALUES
        ('manuel:1', 'manuel', '2026-10-25 17:00:00', 'Visible', 't', '/', 'prevu'),
        ('cours:a', 'cours', '2026-10-25 09:00:00', 'Cours', 't', '/', 'prevu')");
    $liste = rappels_du_jour_lire();
    egal([['cours:a', 'cours'], [1, 'manuel']], array_map(fn($e) => [$e['id'], $e['type']], $liste));
});

test("données : la réponse porte rappels_du_jour", function () {
    base_de_test();
    fixer_heure('2026-10-25 08:00:00');
    copie_trello([]);
    creer_rappel(1, ['texte' => 'Visible', 'quand' => '2026-10-25T18:00:00+01:00']);
    faux_http([BATCH => ['json' => batch_trello([])]]);
    $d = corps(appeler('GET', 'donnees'));
    egal([['id' => 1, 'type' => 'manuel', 'heure' => '2026-10-25T18:00:00+01:00', 'titre' => 'Visible', 'passe' => false, 'annulable' => true]], $d['rappels_du_jour']);
});

test("rappels manuels : le texte d'un rappel n'est jamais écrit au journal", function () {
    $sources = file_get_contents(__DIR__ . '/../../serveur/lib/rappels_manuels.php') . file_get_contents(__DIR__ . '/../../serveur/routes/rappels.php');
    vrai(preg_match('/journal_\w+\([^;]*texte/i', $sources) !== 1, 'journal et texte dans le même appel');
});
