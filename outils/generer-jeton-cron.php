<?php
// Crée le jeton du planificateur (CRON_JETON, 32 octets aléatoires) dans le fichier de secrets de l'app.
//   php outils/generer-jeton-cron.php
// N'affiche jamais le jeton. Ne remplace jamais un jeton déjà présent.

declare(strict_types=1);

require __DIR__ . '/lib-env.php';

$fichier = dirname(__DIR__, 3) . '/secrets/jarvis-jr.env';
try {
    if ((lire_env($fichier)['CRON_JETON'] ?? '') !== '') {
        fwrite(STDERR, "Jeton du planificateur déjà présent, rien n'est changé.\n");
        exit(1);
    }
    env_definir($fichier, 'CRON_JETON', rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
echo "Jeton du planificateur ajouté (CRON_JETON). Il sera donné à cron-job.org au plan 3.\n";
