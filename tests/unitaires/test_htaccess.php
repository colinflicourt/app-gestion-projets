<?php
declare(strict_types=1);

function lire_htaccess(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2) . '/site/.htaccess');
}

test("htaccess : les en-têtes de sécurité sont ceux de l'API, plus HSTS", function () {
    preg_match_all('/^\s*Header always set (\S+) "([^"]*)"$/m', lire_htaccess(), $trouves, PREG_SET_ORDER);
    $entetes = [];
    foreach ($trouves as [, $nom, $valeur]) {
        $entetes[$nom] = $valeur;
    }
    $attendus = entetes_securite() + ['Strict-Transport-Security' => 'max-age=31536000'];
    ksort($entetes);
    ksort($attendus);
    egal($attendus, $entetes);
});

test('htaccess : manifeste typé, HTTPS forcé, API réécrite, pas de liste de dossier, pas de cache sur index.html, sw.js et le manifeste', function () {
    $htaccess = lire_htaccess();
    $attendus = [
        'AddType application/manifest+json .webmanifest',
        'RewriteCond %{SERVER_PORT} ^80$',
        'RewriteRule ^api/index\.php$ - [L]',
        'RewriteRule ^api/ api/index.php [L,QSA]',
        'Options -Indexes',
        '<FilesMatch "^(index\.html|sw\.js|manifest\.webmanifest)$">',
        'Header set Cache-Control "no-cache"',
    ];
    foreach ($attendus as $attendu) {
        vrai(str_contains($htaccess, $attendu), "manque dans .htaccess : $attendu");
    }
});

test('htaccess : les fichiers en point (.ovhconfig…) sont refusés et X-Powered-By est retiré', function () {
    $htaccess = lire_htaccess();
    vrai(str_contains($htaccess, 'RewriteRule (^|/)\.(?!well-known/) - [R=404,L]'), 'règle des fichiers en point attendue');
    vrai(str_contains($htaccess, 'Header always unset X-Powered-By'), 'retrait de X-Powered-By attendu');
    vrai(strpos($htaccess, 'RewriteRule (^|/)\.') < strpos($htaccess, 'RewriteRule ^api/'), 'la règle des fichiers en point passe avant l\'API');
});
