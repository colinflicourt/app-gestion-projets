<?php
declare(strict_types=1);

test('routeur : GET sante répond sans session, avec la version et les en-têtes de sécurité', function () {
    base_de_test();
    $r = traiter(requete());
    egal(200, $r['statut']);
    egal(['ok' => true, 'version_app' => JR_VERSION, 'version_base' => 6, 'base_ok' => true], corps($r));
    egal('no-store', $r['entetes']['Cache-Control']);
    vrai(str_contains($r['entetes']['Content-Security-Policy'], "frame-ancestors 'none'"), 'CSP attendue');
});

test('routeur : une adresse inconnue répond 404', function () {
    base_de_test();
    $r = traiter(requete(['chemin' => 'nimporte']));
    egal(404, $r['statut']);
    egal('route_inconnue', corps($r)['erreur']['code']);
});

test('routeur : toute route non publique refuse sans session (401)', function () {
    base_de_test();
    routes_d_essai();
    $protegees = array_filter(routes(), fn(array $route) => !$route['publique']);
    vrai(count($protegees) >= 1, 'au moins une route protégée attendue');
    foreach (array_keys($protegees) as $cle) {
        [$methode, $chemin] = explode(' ', $cle, 2);
        $r = traiter(requete(['methode' => $methode, 'chemin' => $chemin, 'corps_brut' => '{}']));
        egal(401, $r['statut'], $cle);
        egal('session_expiree', corps($r)['erreur']['code'], $cle);
        egal('Ta session a expiré. Reconnecte-toi.', corps($r)['erreur']['message'], $cle);
    }
});

test('routeur : un cookie forgé sur une route protégée donne 401, jamais 500', function () {
    base_de_test();
    routes_d_essai();
    foreach (["' OR 1=1 --", str_repeat('a', 43), str_repeat('x', 10000)] as $faux) {
        egal(401, traiter(requete(['chemin' => 'essai-protegee', 'cookies' => [JR_COOKIE_SESSION => $faux]]))['statut']);
    }
});

test('routeur : une écriture exige la bonne origine et du JSON', function () {
    base_de_test();
    routes_d_essai();
    $post = ['methode' => 'POST', 'chemin' => 'essai-echo', 'corps_brut' => '{}'];
    egal(403, traiter(requete($post + ['entetes' => ['origin' => 'https://autre.site', 'content-type' => 'application/json']]))['statut']);
    egal(403, traiter(requete($post + ['entetes' => ['origin' => null, 'content-type' => 'application/json']]))['statut']);
    egal(415, traiter(requete($post + ['entetes' => ['origin' => 'http://localhost:8787', 'content-type' => 'text/plain']]))['statut']);
});

test('routeur : un corps vide, non objet ou trop gros donne 400 ou 413, jamais 500', function () {
    base_de_test();
    routes_d_essai();
    $post = fn(string $corps): array => traiter(requete(['methode' => 'POST', 'chemin' => 'essai-echo', 'corps_brut' => $corps]));
    egal(200, $post('')['statut']);
    egal([], corps($post(''))['corps']);
    egal(['a' => 1], corps($post('{"a":1}'))['corps']);
    foreach (['[1,2]', 'null', '123', '"texte"', '{"a":', "\xff\xfe"] as $mauvais) {
        egal(400, $post($mauvais)['statut'], 'corps ' . bin2hex($mauvais));
    }
    egal(413, $post('{"a":"' . str_repeat('x', JR_CORPS_MAX) . '"}')['statut']);
});

test('routeur : une panne dans une route donne 500 sobre et une ligne de journal', function () {
    base_de_test();
    routes_d_essai();
    $r = traiter(requete(['chemin' => 'essai-panne']));
    egal(500, $r['statut']);
    egal('erreur_serveur', corps($r)['erreur']['code']);
    vrai(str_contains(file_get_contents(dossier_data() . '/erreurs.log'), '| essai-panne | RuntimeException | panne simulée'), 'ligne de journal attendue');
});

test('routeur : avec une session valide, la route protégée répond, et la session est prolongée après 24 h', function () {
    base_de_test();
    routes_d_essai();
    fixer_heure('2026-10-08 10:00:00');
    $cookie = session_creer('Tests');
    $avec_cookie = ['chemin' => 'essai-protegee', 'cookies' => [JR_COOKIE_SESSION => $cookie['valeur']]];
    $r = traiter(requete($avec_cookie));
    egal(200, $r['statut']);
    egal([], $r['cookies']);
    fixer_heure('2026-10-09 11:00:00');
    $r = traiter(requete($avec_cookie));
    egal(JR_COOKIE_SESSION, $r['cookies'][0]['nom']);
    egal($cookie['valeur'], $r['cookies'][0]['valeur']);
});

test("routeur : si l'action échoue, la session prolongée garde son cookie", function () {
    base_de_test();
    fixer_heure('2026-10-08 10:00:00');
    $cookie = session_creer('Tests');
    fixer_heure('2026-10-09 11:00:00');
    $GLOBALS['JR_ROUTES_SUPPLEMENTAIRES'] = ['GET essai-panne-protegee' => ['publique' => false, 'action' => function (array $r, ?array $s): array {
        throw new RuntimeException('panne simulée');
    }]];
    $r = traiter(requete(['chemin' => 'essai-panne-protegee', 'cookies' => [JR_COOKIE_SESSION => $cookie['valeur']]]));
    egal(500, $r['statut']);
    egal(JR_COOKIE_SESSION, $r['cookies'][0]['nom'] ?? null);
    egal($cookie['valeur'], $r['cookies'][0]['valeur'] ?? null);
});

test("routeur : un journal impossible à écrire n'empêche pas la réponse 500 en JSON", function () {
    base_de_test();
    routes_d_essai();
    $GLOBALS['JR_DOSSIER_DATA'] = fichier_temporaire('un fichier, pas un dossier');
    set_error_handler('erreur_php');
    try {
        $r = traiter(requete(['chemin' => 'essai-panne']));
    } finally {
        restore_error_handler();
    }
    egal(500, $r['statut']);
    egal('erreur_serveur', corps($r)['erreur']['code']);
});

function routes_avec_erreur_dediee(): void
{
    $erreur = fn(int $statut, string $code): array => reponse_brute($statut, ['Content-Type' => 'text/plain; charset=utf-8'], "erreur $statut $code");
    $lever = fn(Throwable $e) => ['publique' => false, 'erreur' => $erreur, 'action' => function (array $r, ?array $s) use ($e): array {
        throw $e;
    }];
    $GLOBALS['JR_ROUTES_SUPPLEMENTAIRES'] = [
        'GET essai-brute' => ['publique' => false, 'erreur' => $erreur, 'action' => fn(array $r, ?array $s): array => reponse_brute(200, ['Content-Type' => 'text/html; charset=utf-8'], '<p>ok</p>')],
        'GET essai-erreur-api' => $lever(new ErreurApi('compte_invalide', 400, 'Compte inconnu.')),
        'GET essai-erreur-absent' => $lever(new ErreurSource('absent', 'IMAP : mail absent')),
        'GET essai-erreur-trop-lourde' => $lever(new ErreurSource('trop_lourde', 'IMAP : trop lourde')),
        'GET essai-erreur-delai' => $lever(new ErreurSource('delai', 'IMAP : délai dépassé')),
        'GET essai-erreur-panne' => $lever(new RuntimeException('panne simulée')),
        'GET essai-sans-erreur' => ['publique' => false, 'action' => function (array $r, ?array $s): array {
            throw new ErreurSource('absent', 'IMAP : mail absent');
        }],
    ];
}

test("routeur : une route avec 'erreur' rend sa propre réponse, session expirée (401) comprise, sans JSON", function () {
    base_de_test();
    routes_avec_erreur_dediee();
    $r = traiter(requete(['chemin' => 'essai-brute']));
    egal(401, $r['statut']);
    egal('erreur 401 session_expiree', $r['corps']);
    egal(['Content-Type' => 'text/plain; charset=utf-8'], $r['entetes']);
    $cookie = session_creer('Tests');
    $avec = fn(string $chemin): array => traiter(requete(['chemin' => $chemin, 'cookies' => [JR_COOKIE_SESSION => $cookie['valeur']]]));
    egal('<p>ok</p>', $avec('essai-brute')['corps']);
    $attendus = [
        'essai-erreur-api' => [400, 'erreur 400 compte_invalide'],
        'essai-erreur-absent' => [404, 'erreur 404 absent'],
        'essai-erreur-trop-lourde' => [413, 'erreur 413 trop_lourde'],
        'essai-erreur-delai' => [502, 'erreur 502 delai'],
        'essai-erreur-panne' => [500, 'erreur 500 erreur_serveur'],
    ];
    foreach ($attendus as $chemin => [$statut, $corps]) {
        $r = $avec($chemin);
        egal($statut, $r['statut'], $chemin);
        egal($corps, $r['corps'], $chemin);
        egal(['Content-Type' => 'text/plain; charset=utf-8'], $r['entetes'], $chemin);
    }
    $journal = file_get_contents(dossier_data() . '/erreurs.log');
    vrai(str_contains($journal, '| RuntimeException | panne simulée'), 'la panne reste au journal');
});

test("routeur : une route avec 'erreur' garde le cookie de prolongation de la session", function () {
    base_de_test();
    routes_avec_erreur_dediee();
    fixer_heure('2026-10-08 10:00:00');
    $cookie = session_creer('Tests');
    fixer_heure('2026-10-09 11:00:00');
    $r = traiter(requete(['chemin' => 'essai-erreur-panne', 'cookies' => [JR_COOKIE_SESSION => $cookie['valeur']]]));
    egal(500, $r['statut']);
    egal(JR_COOKIE_SESSION, $r['cookies'][0]['nom'] ?? null);
    egal($cookie['valeur'], $r['cookies'][0]['valeur'] ?? null);
});

test("routeur : une route sans 'erreur' rend toujours du JSON (ErreurSource en 502), et une route inconnue aussi", function () {
    base_de_test();
    routes_avec_erreur_dediee();
    $cookie = session_creer('Tests');
    $r = traiter(requete(['chemin' => 'essai-sans-erreur', 'cookies' => [JR_COOKIE_SESSION => $cookie['valeur']]]));
    egal(502, $r['statut']);
    egal('absent', corps($r)['erreur']['code']);
    egal(false, corps($r)['ok']);
    $r = traiter(requete(['chemin' => 'essai-sans-erreur']));
    egal(401, $r['statut']);
    egal('session_expiree', corps($r)['erreur']['code']);
    $r = traiter(requete(['chemin' => 'nimporte']));
    egal(404, $r['statut']);
    egal('route_inconnue', corps($r)['erreur']['code']);
    vrai(isset($r['entetes']['Content-Security-Policy']), 'en-têtes de sécurité de l API');
});
