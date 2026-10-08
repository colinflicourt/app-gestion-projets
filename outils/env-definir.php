<?php
// Écrit une clé dans un fichier .env. La valeur passe par la variable JR_VALEUR, jamais en argument.
//   JR_VALEUR=... php outils/env-definir.php FICHIER CLÉ

declare(strict_types=1);

require __DIR__ . '/lib-env.php';

$fichier = (string) ($argv[1] ?? '');
$cle = (string) ($argv[2] ?? '');
$valeur = getenv('JR_VALEUR');
if ($fichier === '' || $cle === '' || $valeur === false || $valeur === '') {
    fwrite(STDERR, "Usage : JR_VALEUR=... php outils/env-definir.php FICHIER CLÉ\n");
    exit(1);
}
try {
    env_definir($fichier, $cle, $valeur);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
