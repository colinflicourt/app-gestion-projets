<?php
// Tests PHP de Jarvis Jr. : php tests/run.php [morceau du nom d'un test]
// Toujours à travers le filtre : { php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets

declare(strict_types=1);

require __DIR__ . '/aide.php';
require __DIR__ . '/fabriques.php';
foreach (glob(dirname(__DIR__) . '/serveur/lib/*.php') as $fichier) {
    require_once $fichier;
}
foreach (array_merge(glob(__DIR__ . '/unitaires/test_*.php'), glob(__DIR__ . '/integration/test_*.php')) as $fichier) {
    require $fichier;
}
exit(lancer_tests($argv[1] ?? ''));
