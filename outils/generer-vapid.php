<?php
// Génère la paire VAPID des notifications, une fois pour toutes, dans le fichier de secrets de l'app.
//   php outils/generer-vapid.php
// N'affiche que la clé publique. Ne remplace jamais des clés déjà présentes.

declare(strict_types=1);

require __DIR__ . '/lib-env.php';

$fichier = dirname(__DIR__, 3) . '/secrets/jarvis-jr.env';
try {
    if ((lire_env($fichier)['VAPID_PRIVEE'] ?? '') !== '') {
        fwrite(STDERR, "Clés VAPID déjà présentes, rien n'est changé.\n");
        exit(1);
    }
    $cle = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $ec = openssl_pkey_get_details($cle)['ec'];
    $b64 = fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $publique = $b64("\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT));
    env_definir($fichier, 'VAPID_PUBLIQUE', $publique);
    env_definir($fichier, 'VAPID_PRIVEE', $b64(str_pad($ec['d'], 32, "\0", STR_PAD_LEFT)));
    env_definir($fichier, 'VAPID_SUJET', 'mailto:contact@qyris.fr');
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
echo "Clés VAPID ajoutées. Clé publique : $publique\n";
