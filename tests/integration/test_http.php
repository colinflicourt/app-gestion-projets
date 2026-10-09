<?php
declare(strict_types=1);

test("HTTP : l'aperçu local sert l'API et les fichiers avec les bons en-têtes, connexion et déconnexion comprises", function () {
    base_de_test();
    $port = 18000 + random_int(0, 999);
    $origine = "http://127.0.0.1:$port";
    $config = fichier_temporaire('<?php return ' . var_export(config_de_test(['origine' => $origine]), true) . ';');
    chmod($config, 0600);
    $racine = dirname(__DIR__, 2);
    $env = getenv();
    $env['JR_CONFIG_FICHIER'] = $config;
    $serveur = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', "$racine/site", "$racine/outils/apercu.php"],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $tubes,
        $racine,
        $env
    );
    try {
        attendre_port($port);
        $json = ['Origin: ' . $origine, 'Content-Type: application/json'];

        $sante = requete_http('GET', "$origine/api/sante");
        egal(200, $sante['statut']);
        egal(true, json_decode($sante['corps'], true)['base_ok']);

        $robots = requete_http('GET', "$origine/robots.txt");
        egal(200, $robots['statut']);
        egal('no-cache', $robots['entetes']['cache-control'][0] ?? null);
        vrai(isset($robots['entetes']['content-security-policy']), 'CSP attendue sur les fichiers statiques');

        egal(404, requete_http('GET', "$origine/.htaccess")['statut']);
        egal(401, requete_http('POST', "$origine/api/deconnexion", $json, '{}')['statut']);
        egal(403, requete_http('POST', "$origine/api/connexion", ['Origin: https://autre.site', 'Content-Type: application/json'], '{"mot_de_passe":"x"}')['statut']);
        egal(401, requete_http('POST', "$origine/api/connexion", $json, '{"mot_de_passe":"mauvais"}')['statut']);

        $ok = requete_http('POST', "$origine/api/connexion", $json, json_encode(['mot_de_passe' => MOT_DE_PASSE_TEST]));
        egal(200, $ok['statut']);
        $cookie = $ok['entetes']['set-cookie'][0] ?? '';
        foreach (['__host-jr=', 'path=/', 'secure', 'httponly', 'samesite=lax'] as $attendu) {
            vrai(str_contains(strtolower($cookie), $attendu), "cookie sans $attendu");
        }
        vrai((bool) preg_match('/Max-Age=777[56]\d{3}/', $cookie), 'Max-Age de 90 jours attendu');

        $jeton = explode(';', substr($cookie, strlen('__Host-jr=')))[0];
        egal(200, requete_http('POST', "$origine/api/deconnexion", array_merge($json, ['Cookie: __Host-jr=' . $jeton]), '')['statut']);
    } finally {
        proc_terminate($serveur);
        proc_close($serveur);
    }
});
