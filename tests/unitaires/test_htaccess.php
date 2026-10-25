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
        'AddType font/woff2 .woff2',
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

test("htaccess : l'en-tête Authorization (jeton du planificateur) est recopié pour PHP, avant la réécriture de l'API", function () {
    $htaccess = lire_htaccess();
    $regle = 'RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]';
    vrai(str_contains($htaccess, 'RewriteCond %{HTTP:Authorization} .'), 'condition attendue');
    vrai(str_contains($htaccess, $regle), 'règle attendue');
    vrai(strpos($htaccess, $regle) < strpos($htaccess, 'RewriteRule ^api/ api/index.php'), "avant la réécriture de l'API");
});

// La condition du bloc <If> : les en-têtes de sécurité de l'app sont posés quand la ligne de requête ne correspond PAS
function lire_condition_htaccess(): string
{
    vrai(preg_match('/^\s*<If "%\{THE_REQUEST\} !~ m#(.+)#">$/m', lire_htaccess(), $m) === 1, 'bloc <If> sur THE_REQUEST attendu');
    return '~' . $m[1] . '~';
}

test("htaccess : les en-têtes de sécurité de l'app sont dans un bloc <If>, HSTS et le retrait de X-Powered-By restent partout", function () {
    $htaccess = lire_htaccess();
    vrai(preg_match('/<If [^\n]*>\n(.*?)<\/If>/s', $htaccess, $bloc) === 1, 'bloc <If> attendu');
    $dans_le_bloc = [];
    preg_match_all('/^\s*Header always set (\S+) "/m', $bloc[1], $trouves);
    foreach ($trouves[1] as $nom) {
        $dans_le_bloc[] = $nom;
    }
    $attendus = array_keys(entetes_securite());
    sort($dans_le_bloc);
    sort($attendus);
    egal($attendus, $dans_le_bloc);
    $dehors = str_replace($bloc[0], '', $htaccess);
    vrai(str_contains($dehors, 'Header always set Strict-Transport-Security "max-age=31536000"'), 'HSTS hors du bloc');
    vrai(str_contains($dehors, 'Header always unset X-Powered-By'), 'retrait de X-Powered-By hors du bloc');
    vrai(!str_contains($htaccess, '<Else'), 'pas de <Else> : rien à poser sur les deux routes de mail');
});

test("htaccess : la condition d'exception ne vise que GET corps et GET piece d'un mail, à partir de la ligne de requête d'origine", function () {
    $regex = lire_condition_htaccess();
    $exclues = [
        'GET /api/mails/corps?compte=gmail&uid=3 HTTP/1.1',
        'GET /api/mails/piece?compte=gmail&uid=3&partie=2 HTTP/1.1',
        'GET /api/mails/corps?compte=gmail&uid=3&images=1 HTTP/2.0',
    ];
    $non_exclues = [
        'GET /api/mails HTTP/1.1',
        'GET /api/mails/corpsx HTTP/1.1',
        'GET /api/mails/corpsx?a=1 HTTP/1.1',
        'POST /api/mails/corps HTTP/1.1',
        'POST /api/mails/corps?compte=gmail HTTP/1.1',
        'HEAD /api/mails/corps?compte=gmail HTTP/1.1',
        'GET /index.html?x=/api/mails/corps HTTP/1.1',
        'GET /index.html?x=/api/mails/corps?y HTTP/1.1',
        'GET /api/mails/ouvrir HTTP/1.1',
        'GET /api/mails/corps HTTP/1.1',
        'GET /api/mails/piece HTTP/1.1',
        'GET /xapi/mails/corps?a=1 HTTP/1.1',
        'GET /api/mails/corps/?a=1 HTTP/1.1',
        'GET /api/sante HTTP/1.1',
    ];
    foreach ($exclues as $ligne) {
        egal(1, preg_match($regex, $ligne), "devrait être exclue : $ligne");
    }
    foreach ($non_exclues as $ligne) {
        egal(0, preg_match($regex, $ligne), "ne devrait pas être exclue : $ligne");
    }
});
