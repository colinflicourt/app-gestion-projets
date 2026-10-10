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
