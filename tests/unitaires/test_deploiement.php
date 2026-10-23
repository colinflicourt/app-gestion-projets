<?php
declare(strict_types=1);

test("déploiement : le serveur et sa configuration partent avant le site, et /api/sante est vérifié", function () {
    $script = (string) file_get_contents(dirname(__DIR__, 2) . '/outils/deploy-ovh.sh');
    $envoi = substr($script, (int) strpos($script, '5/6'));
    $positions = array_map(fn(string $morceau) => strpos($envoi, $morceau), ['miroir_serveur', "config.php' -o", 'miroir_site']);
    vrai(!in_array(false, $positions, true), "étapes de l'envoi introuvables");
    vrai($positions[0] < $positions[1] && $positions[1] < $positions[2], 'ordre attendu : serveur, config.php, site');
    vrai(str_contains($script, '"base_ok":true'), 'vérification de base_ok attendue');
});

test("déploiement : sw.js part après tout le site, pour qu'un iPhone ne garde jamais un mélange d'anciens et de nouveaux fichiers", function () {
    $script = (string) file_get_contents(dirname(__DIR__, 2) . '/outils/deploy-ovh.sh');
    vrai(str_contains($script, '--exclude-glob sw.js'), 'sw.js attendu hors du miroir du site');
    $envoi = substr($script, (int) strpos($script, '5/6'));
    $site = strpos($envoi, 'miroir_site');
    $sw = strpos($envoi, "site/sw.js' -o 'jr.qyris.fr/www/sw.js'");
    vrai($site !== false && $sw !== false && $site < $sw, 'sw.js attendu en dernier, après le miroir du site');
});

test("déploiement : le lot 2 part avec le serveur (migrations, cron-ovh.php) et la config de production porte ses clés", function () {
    $script = (string) file_get_contents(dirname(__DIR__, 2) . '/outils/deploy-ovh.sh');
    preg_match('/EXCLUS_SERVEUR="([^"]*)"/', $script, $m);
    foreach (['cron-ovh', 'migrations', 'lib', 'routes', '.sql'] as $morceau) {
        vrai(!str_contains($m[1] ?? '', $morceau), "$morceau ne doit pas être exclu de l'envoi du serveur");
    }
    vrai(str_contains($script, 'jr.qyris.fr/serveur/cron-ovh.php'), 'liste de cron-ovh.php attendue après envoi');
    $config = (string) file_get_contents(dirname(__DIR__, 2) . '/outils/lib-env.php');
    foreach (['VAPID_PUBLIQUE', 'VAPID_PRIVEE', 'VAPID_SUJET', 'CRON_JETON'] as $cle) {
        vrai(str_contains($config, $cle), "$cle attendue dans la config générée");
    }
});

test("déploiement : la règle .htaccess qui transmet Authorization précède la réécriture de l'API", function () {
    $ht = (string) file_get_contents(dirname(__DIR__, 2) . '/site/.htaccess');
    $auth = strpos($ht, 'E=HTTP_AUTHORIZATION:%{HTTP:Authorization}');
    $api = strpos($ht, 'RewriteRule ^api/ api/index.php');
    vrai($auth !== false && $api !== false && $auth < $api, 'règle Authorization attendue avant la réécriture de /api/');
});

test("déploiement : les migrations 003 à 006 existent et le serveur en attend la dernière", function () {
    $dossier = dirname(__DIR__, 2) . '/serveur/migrations';
    foreach (['003_', '004_', '005_', '006_'] as $debut) {
        vrai(count(glob("$dossier/$debut*.sql")) === 1, "migration $debut introuvable");
    }
    vrai(version_attendue($dossier) >= 6, 'version attendue au moins 6');
});
