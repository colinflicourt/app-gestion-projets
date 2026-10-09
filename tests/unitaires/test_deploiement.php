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
