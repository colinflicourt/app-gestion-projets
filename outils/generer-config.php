<?php
// Écrit config.php et la copie de trello-ids.json dans un dossier, pour Jarvis Jr.
//   php outils/generer-config.php --mode=dev|production --dossier=serveur
// Toujours à travers le filtre des secrets.

declare(strict_types=1);

require __DIR__ . '/lib-env.php';

$options = getopt('', ['mode:', 'dossier:']);
$mode = (string) ($options['mode'] ?? '');
$dossier = (string) ($options['dossier'] ?? '');
if ($dossier === '' || !is_dir($dossier)) {
    fwrite(STDERR, "Usage : php outils/generer-config.php --mode=dev|production --dossier=DOSSIER\n");
    exit(1);
}

$workspace = dirname(__DIR__, 3);
try {
    $app = lire_env("$workspace/secrets/jarvis-jr.env");
    $trello = lire_env("$workspace/secrets/trello.env");
    $local = $mode === 'dev' ? lire_env("$workspace/secrets/jarvis-jr-local.env") : [];
    ecrire_config(construire_config($mode, $app, $trello, $local), "$dossier/config.php");
    if (!copy("$workspace/.claude/scripts/trello-ids.json", "$dossier/trello-ids.json")) {
        throw new RuntimeException('Copie de trello-ids.json impossible');
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
echo "config.php et trello-ids.json écrits dans $dossier (mode $mode)\n";
