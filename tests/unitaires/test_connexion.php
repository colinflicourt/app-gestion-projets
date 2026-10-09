<?php
declare(strict_types=1);

function connexion(string $mot_de_passe, string $ip = '203.0.113.7'): array
{
    return traiter(requete(['methode' => 'POST', 'chemin' => 'connexion', 'ip' => $ip, 'corps_brut' => json_encode(['mot_de_passe' => $mot_de_passe])]));
}

test('connexion : le bon mot de passe ouvre une session', function () {
    $pdo = base_de_test();
    $r = connexion(MOT_DE_PASSE_TEST);
    egal(200, $r['statut']);
    egal(JR_COOKIE_SESSION, $r['cookies'][0]['nom']);
    egal(1, (int) $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
});

test("connexion : un mauvais mot de passe dit combien d'essais il reste", function () {
    base_de_test();
    $r = connexion('mauvais');
    egal(401, $r['statut']);
    egal('Mot de passe incorrect. Encore 9 essais avant une pause de 15 min.', corps($r)['erreur']['message']);
    for ($i = 0; $i < 7; $i++) {
        connexion('mauvais');
    }
    egal('Mot de passe incorrect. Encore 1 essai avant une pause de 15 min.', corps(connexion('mauvais'))['erreur']['message']);
});

test("connexion : au 10e échec, puis au 11e essai même juste, c'est la pause", function () {
    base_de_test();
    for ($i = 0; $i < 9; $i++) {
        connexion('mauvais');
    }
    $r = connexion('mauvais');
    egal(429, $r['statut']);
    egal("Trop d'essais. Réessaie dans 15 min.", corps($r)['erreur']['message']);
    egal(429, connexion(MOT_DE_PASSE_TEST)['statut']);
});

test("connexion : la pause dure 15 min et ne touche que l'adresse IP fautive", function () {
    base_de_test();
    fixer_heure('2026-10-08 10:00:00');
    for ($i = 0; $i < 10; $i++) {
        connexion('mauvais');
    }
    egal(200, connexion(MOT_DE_PASSE_TEST, '198.51.100.1')['statut']);
    fixer_heure('2026-10-08 10:15:01');
    egal(200, connexion(MOT_DE_PASSE_TEST)['statut']);
});

test("connexion : un champ absent ou qui n'est pas du texte compte comme un échec", function () {
    base_de_test();
    egal(401, traiter(requete(['methode' => 'POST', 'chemin' => 'connexion', 'corps_brut' => '{}']))['statut']);
    egal(401, traiter(requete(['methode' => 'POST', 'chemin' => 'connexion', 'corps_brut' => '{"mot_de_passe":["x"]}']))['statut']);
    egal(2, connexion_echecs_recents('203.0.113.7'));
});

test("déconnexion : efface la session de l'appareil et son cookie, l'autre appareil reste connecté", function () {
    base_de_test();
    $cookie = session_creer('iPhone');
    $autre = session_creer('Mac');
    $r = traiter(requete(['methode' => 'POST', 'chemin' => 'deconnexion', 'cookies' => [JR_COOKIE_SESSION => $cookie['valeur']]]));
    egal(200, $r['statut']);
    egal('', end($r['cookies'])['valeur']);
    egal(null, session_trouver($cookie['valeur']));
    vrai(session_trouver($autre['valeur']) !== null, "l'autre appareil reste connecté");
});

test('déconnexion partout : plus aucune session', function () {
    base_de_test();
    $cookie = session_creer('iPhone');
    $autre = session_creer('Mac');
    $r = traiter(requete(['methode' => 'POST', 'chemin' => 'deconnexion-partout', 'cookies' => [JR_COOKIE_SESSION => $cookie['valeur']]]));
    egal(200, $r['statut']);
    egal(null, session_trouver($autre['valeur']));
});

test('connexion : definir-mot-de-passe.sh garde les espaces, accents, emoji, antislash et guillemets, et crée la clé de chiffrement', function () {
    base_de_test();
    $env = fichier_temporaire("DB_HOST=x\n");
    $mot_de_passe = "  é ü 🎿 \\ \$x 'q' \"d\"  ";
    $processus = proc_open(
        ['bash', dirname(__DIR__, 2) . '/outils/definir-mot-de-passe.sh'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $tubes,
        null,
        ['JR_ENV_FICHIER' => $env, 'PATH' => (string) getenv('PATH')]
    );
    fwrite($tubes[0], "$mot_de_passe\n$mot_de_passe\n");
    fclose($tubes[0]);
    stream_get_contents($tubes[1]);
    stream_get_contents($tubes[2]);
    egal(0, proc_close($processus));
    $valeurs = lire_env($env);
    vrai(password_verify($mot_de_passe, $valeurs['APP_MOT_DE_PASSE_EMPREINTE']), 'empreinte du mot de passe exact attendue');
    egal(32, strlen((string) base64_decode($valeurs['APP_CLE_CHIFFREMENT'], true)));
    egal('x', $valeurs['DB_HOST']);
    $GLOBALS['JR_CONFIG']['mot_de_passe_empreinte'] = $valeurs['APP_MOT_DE_PASSE_EMPREINTE'];
    egal(200, connexion($mot_de_passe)['statut']);
    egal(401, connexion(trim($mot_de_passe))['statut']);
});
