<?php
// Lecture et écriture des fichiers .env de Jarvis Jr., construction de serveur/config.php.
// Utilisé par generer-config.php, env-definir.php et les tests. Jamais chargé par l'app en ligne.

declare(strict_types=1);

const JR_ORIGINES = ['production' => 'https://jr.qyris.fr', 'dev' => 'http://localhost:8787'];

// Fichier CLÉ=valeur, sans expansion de variables (une empreinte bcrypt contient des $).
// Les guillemets simples ou doubles autour de la valeur sont retirés.
function lire_env(string $fichier): array
{
    if (!is_file($fichier)) {
        throw new RuntimeException('Fichier introuvable : ' . basename($fichier));
    }
    $valeurs = [];
    foreach (file($fichier, FILE_IGNORE_NEW_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#' || !str_contains($ligne, '=')) {
            continue;
        }
        [$cle, $valeur] = explode('=', $ligne, 2);
        $valeur = trim($valeur);
        if (strlen($valeur) >= 2 && ($valeur[0] === "'" || $valeur[0] === '"') && $valeur[-1] === $valeur[0]) {
            $valeur = substr($valeur, 1, -1);
        }
        $valeurs[trim($cle)] = $valeur;
    }
    return $valeurs;
}

// Remplace ou ajoute CLÉ='valeur' (entre guillemets simples, pour que bash ne touche pas aux $),
// en gardant les autres lignes. Le fichier reste en 600.
function env_definir(string $fichier, string $cle, string $valeur): void
{
    if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $cle)) {
        throw new InvalidArgumentException('Nom de clé invalide');
    }
    if (str_contains($valeur, "'") || str_contains($valeur, "\n")) {
        throw new InvalidArgumentException('Valeur impossible à écrire entre guillemets simples');
    }
    $lignes = is_file($fichier) ? file($fichier, FILE_IGNORE_NEW_LINES) : [];
    $nouvelle = $cle . "='" . $valeur . "'";
    $remplacee = false;
    foreach ($lignes as $i => $ligne) {
        if (preg_match('/^\s*' . $cle . '\s*=/', $ligne)) {
            $lignes[$i] = $nouvelle;
            $remplacee = true;
        }
    }
    if (!$remplacee) {
        $lignes[] = $nouvelle;
    }
    $ancien_umask = umask(077);
    $ok = file_put_contents($fichier, implode("\n", $lignes) . "\n", LOCK_EX);
    umask($ancien_umask);
    if ($ok === false) {
        throw new RuntimeException('Écriture impossible : ' . basename($fichier));
    }
    chmod($fichier, 0600);
}

// $app : secrets/jarvis-jr.env ; $trello : secrets/trello.env ; $local : secrets/jarvis-jr-local.env (dev).
function construire_config(string $mode, array $app, array $trello, array $local = []): array
{
    if (!isset(JR_ORIGINES[$mode])) {
        throw new InvalidArgumentException("Mode inconnu : $mode");
    }
    $base = $mode === 'production' ? $app : $local;
    $requis = [
        [$base, ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD']],
        [$app, ['TRELLO_TOKEN', 'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'APP_MOT_DE_PASSE_EMPREINTE', 'APP_CLE_CHIFFREMENT']],
        [$trello, ['TRELLO_KEY']],
    ];
    foreach ($requis as [$source, $cles]) {
        foreach ($cles as $cle) {
            if (($source[$cle] ?? '') === '') {
                throw new RuntimeException("Clé manquante ou vide : $cle");
            }
        }
    }
    $origine = JR_ORIGINES[$mode];
    $trello_reel = $mode === 'production' || ($local['JR_DEV_TRELLO_REEL'] ?? '') === '1';
    return [
        'mode' => $mode,
        'origine' => $origine,
        'base' => [
            'hote' => $base['DB_HOST'],
            'nom' => $base['DB_NAME'],
            'utilisateur' => $base['DB_USER'],
            'mot_de_passe' => $base['DB_PASSWORD'],
        ],
        'trello' => ['cle' => $trello['TRELLO_KEY'], 'jeton' => $app['TRELLO_TOKEN'], 'ecritures' => $trello_reel ? 'reelles' : 'simulees'],
        'google' => ['client_id' => $app['GOOGLE_CLIENT_ID'], 'client_secret' => $app['GOOGLE_CLIENT_SECRET'], 'retour' => $origine . '/api/google-callback.php'],
        'mot_de_passe_empreinte' => $app['APP_MOT_DE_PASSE_EMPREINTE'],
        'cle_chiffrement' => $app['APP_CLE_CHIFFREMENT'],
    ];
}

function ecrire_config(array $config, string $fichier): void
{
    $ancien_umask = umask(077);
    $ok = file_put_contents($fichier, "<?php\n// Généré par outils/generer-config.php. Ne pas modifier, ne pas versionner.\nreturn " . var_export($config, true) . ";\n", LOCK_EX);
    umask($ancien_umask);
    if ($ok === false) {
        throw new RuntimeException('Écriture impossible : ' . basename($fichier));
    }
    chmod($fichier, 0600);
}
