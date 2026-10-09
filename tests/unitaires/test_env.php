<?php
declare(strict_types=1);

function env_exemple_app(): array
{
    return [
        'DB_HOST' => 'base.ovh', 'DB_NAME' => 'jr', 'DB_USER' => 'u', 'DB_PASSWORD' => 'p',
        'TRELLO_TOKEN' => 't', 'GOOGLE_CLIENT_ID' => 'g', 'GOOGLE_CLIENT_SECRET' => 's',
        'APP_MOT_DE_PASSE_EMPREINTE' => '$2y$04$abc', 'APP_CLE_CHIFFREMENT' => 'Y2xl',
    ];
}

test('env : lit CLÉ=valeur, ignore commentaires et lignes vides, garde les $ et les =', function () {
    $f = fichier_temporaire("# commentaire\n\nA=simple\nB='\$2y\$12\$abc=def'\nC=\"entre guillemets\"\n  D = espaces  \nE=\n");
    egal(['A' => 'simple', 'B' => '$2y$12$abc=def', 'C' => 'entre guillemets', 'D' => 'espaces', 'E' => ''], lire_env($f));
});

test('env : un fichier absent lève une exception qui ne donne que le nom du fichier', function () {
    $e = leve(RuntimeException::class, fn() => lire_env('/chemin/secret/absent.env'));
    egal('Fichier introuvable : absent.env', $e->getMessage());
});

test('env : env_definir remplace une clé, en ajoute une autre et laisse le fichier en 600', function () {
    $f = fichier_temporaire("A=1\nB=2\n");
    env_definir($f, 'B', '$2y$12$x');
    env_definir($f, 'C', 'trois');
    egal(['A' => '1', 'B' => '$2y$12$x', 'C' => 'trois'], lire_env($f));
    egal('0600', substr(sprintf('%o', fileperms($f)), -4));
});

test('env : env_definir refuse un guillemet simple, un retour à la ligne ou un nom de clé bizarre', function () {
    $f = fichier_temporaire("A=1\n");
    leve(InvalidArgumentException::class, fn() => env_definir($f, 'A', "it's"));
    leve(InvalidArgumentException::class, fn() => env_definir($f, 'A', "a\nb"));
    leve(InvalidArgumentException::class, fn() => env_definir($f, 'a b', 'x'));
});

test('config : en production, base de jarvis-jr.env, origine https, écritures Trello réelles', function () {
    $c = construire_config('production', env_exemple_app(), ['TRELLO_KEY' => 'k']);
    egal('https://jr.qyris.fr', $c['origine']);
    egal(['hote' => 'base.ovh', 'nom' => 'jr', 'utilisateur' => 'u', 'mot_de_passe' => 'p'], $c['base']);
    egal('reelles', $c['trello']['ecritures']);
    egal('k', $c['trello']['cle']);
    egal('https://jr.qyris.fr/api/google-callback.php', $c['google']['retour']);
});

test('config : en dev, base locale, origine localhost, écritures simulées sauf JR_DEV_TRELLO_REEL=1', function () {
    $local = ['DB_HOST' => 'localhost', 'DB_NAME' => 'jarvisjr_dev', 'DB_USER' => 'jarvisjr', 'DB_PASSWORD' => 'l'];
    $c = construire_config('dev', env_exemple_app(), ['TRELLO_KEY' => 'k'], $local);
    egal('http://localhost:8787', $c['origine']);
    egal('jarvisjr_dev', $c['base']['nom']);
    egal('simulees', $c['trello']['ecritures']);
    $c = construire_config('dev', env_exemple_app(), ['TRELLO_KEY' => 'k'], $local + ['JR_DEV_TRELLO_REEL' => '1']);
    egal('reelles', $c['trello']['ecritures']);
});

test('config : une clé manquante est nommée, sans aucune valeur dans le message ; un mode inconnu est refusé', function () {
    $app = env_exemple_app();
    unset($app['GOOGLE_CLIENT_SECRET']);
    $e = leve(RuntimeException::class, fn() => construire_config('production', $app, ['TRELLO_KEY' => 'k']));
    egal('Clé manquante ou vide : GOOGLE_CLIENT_SECRET', $e->getMessage());
    leve(InvalidArgumentException::class, fn() => construire_config('autre', env_exemple_app(), ['TRELLO_KEY' => 'k']));
});

test('config : ecrire_config produit un fichier PHP relisible, en droits 600', function () {
    $f = fichier_temporaire();
    $config = construire_config('production', env_exemple_app(), ['TRELLO_KEY' => 'k']);
    ecrire_config($config, $f);
    egal($config, require $f);
    egal('0600', substr(sprintf('%o', fileperms($f)), -4));
});

test('env : env_definir écrit à côté puis renomme, sans laisser de fichier temporaire, en 600', function () {
    $fichier = fichier_temporaire("A='1'\n");
    $inode = fileinode($fichier);
    env_definir($fichier, 'B', '2');
    clearstatcache();
    vrai(fileinode($fichier) !== $inode, 'le fichier doit être remplacé par renommage');
    egal(['A' => '1', 'B' => '2'], lire_env($fichier));
    egal('0600', substr(sprintf('%o', fileperms($fichier)), -4));
    egal([], glob($fichier . '.tmp-*'));
});
