<?php
// Diagnostic de l'hébergement OVH pour Jarvis Jr. : PHP, extensions, crypto, base, réseau sortant.
// Envoyé sous un nom aléatoire dans jr.qyris.fr/www/ le temps du test, puis supprimé.
// Lit les accès à la base dans ../serveur/config-test.php (hors du web, généré depuis secrets/jarvis-jr.env).
// N'affiche aucun secret : ni mot de passe, ni jeton, ni contenu personnel.

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$debut = microtime(true);
$r = [];

// Les avertissements deviennent des exceptions, rapportées par essai()
set_error_handler(function (int $n, string $msg, string $f, int $l): bool {
    throw new ErrorException($msg, 0, $n, $f, $l);
});

function essai(callable $f): array
{
    try {
        return ['ok' => true] + (array) $f();
    } catch (Throwable $e) {
        return ['ok' => false, 'erreur' => get_class($e) . ' : ' . $e->getMessage()];
    }
}

// --- PHP --------------------------------------------------------------------

$ini = ['memory_limit', 'max_execution_time', 'max_input_time', 'upload_max_filesize', 'post_max_size',
    'allow_url_fopen', 'open_basedir', 'disable_functions', 'date.timezone', 'display_errors', 'log_errors',
    'default_socket_timeout', 'session.gc_maxlifetime', 'session.cookie_secure', 'session.use_strict_mode',
    'opcache.enable', 'output_buffering', 'zend.assertions'];

$r['php'] = [
    'version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'os' => PHP_OS_FAMILY,
    'entiers_64_bits' => PHP_INT_SIZE === 8,
    'ini' => array_combine($ini, array_map(fn($k) => ini_get($k), $ini)),
    'serveur_web' => $_SERVER['SERVER_SOFTWARE'] ?? null,
    'https' => ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
];

$besoins = ['pdo_mysql', 'openssl', 'sodium', 'curl', 'mbstring', 'json', 'intl', 'fileinfo', 'zlib',
    'iconv', 'ctype', 'filter', 'hash', 'session', 'random', 'gd', 'imap', 'apcu', 'Zend OPcache'];
$r['extensions'] = [
    'besoins' => array_combine($besoins, array_map('extension_loaded', $besoins)),
    'toutes' => (function () { $l = get_loaded_extensions(); sort($l); return $l; })(),
];

$fonctions = ['openssl_pkey_new', 'openssl_pkey_derive', 'openssl_sign', 'hash_hkdf', 'sodium_crypto_secretbox',
    'random_bytes', 'curl_init', 'stream_socket_client', 'stream_socket_enable_crypto', 'flock',
    'set_time_limit', 'ignore_user_abort', 'fastcgi_finish_request', 'mb_decode_mimeheader',
    'iconv_mime_decode', 'quoted_printable_decode', 'apache_get_modules'];
$r['fonctions'] = array_combine($fonctions, array_map('function_exists', $fonctions));

// --- Cryptographie des notifications web (VAPID, aes128gcm) et chiffrement des jetons (libsodium) --

$r['crypto'] = [
    'openssl' => OPENSSL_VERSION_TEXT,
    'cle_ec_p256' => essai(function () {
        $opts = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        $a = openssl_pkey_new($opts);
        $b = openssl_pkey_new($opts);
        if (!$a || !$b) throw new RuntimeException('openssl_pkey_new a échoué : ' . openssl_error_string());
        $pubA = openssl_pkey_get_public(openssl_pkey_get_details($a)['key']);
        $pubB = openssl_pkey_get_public(openssl_pkey_get_details($b)['key']);
        $s1 = openssl_pkey_derive($pubB, $a);
        $s2 = openssl_pkey_derive($pubA, $b);
        $msg = 'jeton VAPID';
        openssl_sign($msg, $sig, $a, OPENSSL_ALGO_SHA256);
        $verif = openssl_verify($msg, $sig, $pubA, OPENSSL_ALGO_SHA256);
        $details = openssl_pkey_get_details($a)['ec'] ?? [];
        return [
            'ecdh_identique' => $s1 !== false && $s1 === $s2,
            'ecdsa_sha256' => $verif === 1,
            'coordonnees_xy' => isset($details['x'], $details['y']) && strlen($details['x']) === 32,
        ];
    }),
    'aes_128_gcm' => essai(function () {
        $cle = random_bytes(16);
        $iv = random_bytes(12);
        $c = openssl_encrypt('Junior', 'aes-128-gcm', $cle, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        return ['aller_retour' => openssl_decrypt($c, 'aes-128-gcm', $cle, OPENSSL_RAW_DATA, $iv, $tag) === 'Junior'];
    }),
    'hkdf_sha256' => essai(fn() => ['longueur' => strlen(hash_hkdf('sha256', random_bytes(32), 16, 'info', 'sel'))]),
    'sodium_secretbox' => essai(function () {
        $cle = sodium_crypto_secretbox_keygen();
        $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $c = sodium_crypto_secretbox('jeton Google', $n, $cle);
        return ['aller_retour' => sodium_crypto_secretbox_open($c, $n, $cle) === 'jeton Google'];
    }),
    'bcrypt' => essai(fn() => ['verif' => password_verify('x', password_hash('x', PASSWORD_DEFAULT))]),
];

// --- Lot 2 : le code réel des notifications rejoué sur ce serveur ------------------------------
// Vecteur de l'annexe A de la RFC 8291 (valeurs publiques de la norme, pas des secrets), signature VAPID ES256
// vérifiée par openssl_verify, fastcgi_finish_request (le planificateur répond avant de travailler).
// Utilise serveur/lib/push.php : le lot 2 doit être en ligne.

$r['lot2'] = essai(function () {
    $push = null;
    foreach ([dirname(__DIR__) . '/serveur/lib/push.php', dirname(__DIR__, 2) . '/serveur/lib/push.php'] as $c) {
        if (is_file($c)) { $push = $c; break; }
    }
    if ($push === null) throw new RuntimeException('serveur/lib/push.php introuvable : mets d\'abord le lot 2 en ligne');
    require_once $push;

    $pub = 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8';
    $priv = 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw';
    $attendu = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';
    $ephemere = cle_p256(b64url_decoder($pub), b64url_decoder($priv));
    $corps = chiffrer_message('When I grow up, I want to be a watermelon', b64url_decoder('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'),
        b64url_decoder('BTBZMqHH6r4Tts7J_aSIgg'), $ephemere, b64url_decoder('DGv6ra1nlYgDCS1FRnbzlw'));
    $vecteur = b64url_encoder($corps) === $attendu;

    $entete = vapid_entete('https://web.push.apple.com/QGuQ', ['publique' => $pub, 'privee' => $priv, 'sujet' => 'mailto:contact@qyris.fr'], 1_760_000_000);
    $es256 = false;
    if (preg_match('/^vapid t=([^.]+)\.([^.]+)\.([^,]+), k=(.+)$/', $entete, $m)) {
        $sig = b64url_decoder($m[3]);
        $cle = openssl_pkey_get_details($ephemere)['key'];
        $es256 = strlen($sig) === 64 && openssl_verify("$m[1].$m[2]", der_depuis_brut($sig), $cle, OPENSSL_ALGO_SHA256) === 1;
    }
    $php84 = version_compare(PHP_VERSION, '8.4.0', '>=');
    $fin = function_exists('fastcgi_finish_request');
    return [
        'php_8_4_ou_plus' => $php84,
        'vecteur_rfc8291_octet_pour_octet' => $vecteur,
        'signature_es256_openssl_verify' => $es256,
        'fastcgi_finish_request' => $fin,
        'sapi' => PHP_SAPI,
        'TOUT_EST_BON' => $php84 && $vecteur && $es256 && ($fin || PHP_SAPI === 'cli'),  // en ligne de commande, pas de FPM
    ];
});

// --- Lot 2 : contrôle de la table envois (lecture seule) ----------------------------------------
// Recette du SPEC § 15.9 : aucun doublon après 24 h de planificateur. Lit la vraie base par serveur/lib/base.php.
// N'affiche ni titre ni texte (contenu personnel) : clés, types, heures et statuts seulement.

$r['envois'] = essai(function () {
    $base = null;
    foreach ([dirname(__DIR__) . '/serveur/lib/base.php', dirname(__DIR__, 2) . '/serveur/lib/base.php'] as $c) {
        if (is_file($c)) { $base = $c; break; }
    }
    if ($base === null) throw new RuntimeException('serveur/lib/base.php introuvable : mets d\'abord le lot 2 en ligne');
    require_once $base;
    $pdo = db();
    $q = fn (string $sql) => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    // Même notification sous deux clés différentes : même type, même heure prévue, même titre
    $doublons = $q("SELECT type, prevu_pour, COUNT(*) n, GROUP_CONCAT(cle ORDER BY cle SEPARATOR ' | ') cles
        FROM envois GROUP BY type, prevu_pour, titre HAVING n > 1 ORDER BY prevu_pour");
    // Deux envois réussis du même type à moins de 2 minutes d'écart
    $rapproches = $q("SELECT a.type, a.cle cle_a, b.cle cle_b, a.envoye_le a_le, b.envoye_le b_le FROM envois a
        JOIN envois b ON a.type = b.type AND a.titre = b.titre AND a.cle < b.cle
        WHERE a.statut = 'envoye' AND b.statut = 'envoye' AND ABS(TIMESTAMPDIFF(SECOND, a.envoye_le, b.envoye_le)) < 120");
    return [
        'heure_utc' => gmdate('Y-m-d H:i:s'),
        'total' => (int) $pdo->query('SELECT COUNT(*) FROM envois')->fetchColumn(),
        'par_type_et_statut' => $q('SELECT type, statut, COUNT(*) n FROM envois GROUP BY type, statut ORDER BY type, statut'),
        'doublons_meme_heure' => $doublons,
        'envoyes_a_moins_de_2_min' => $rapproches,
        'plusieurs_essais' => $q("SELECT cle, type, statut, essais, prevu_pour FROM envois WHERE essais > 1 ORDER BY prevu_pour"),
        'retard_envoi_max_s' => $q("SELECT type, MAX(TIMESTAMPDIFF(SECOND, prevu_pour, envoye_le)) s FROM envois
            WHERE statut = 'envoye' GROUP BY type"),
        'derniers_48h' => $q("SELECT cle, type, prevu_pour, statut, essais, envoye_le FROM envois
            WHERE prevu_pour >= UTC_TIMESTAMP() - INTERVAL 48 HOUR ORDER BY prevu_pour"),
        'rappels_manuels' => $q('SELECT COUNT(*) n, SUM(annule) annules, SUM(source = \'siri\') siri FROM rappels'),
        'AUCUN_DOUBLON' => $doublons === [] && $rapproches === [],
    ];
});

// --- Fichiers : dossier serveur/ hors du web ----------------------------------

$serveur = dirname(__DIR__) . '/serveur';
$r['fichiers'] = [
    'serveur_lisible' => is_readable($serveur),
    'serveur_ecriture' => essai(function () use ($serveur) {
        $f = $serveur . '/.essai-ecriture-' . bin2hex(random_bytes(4));
        $ok = file_put_contents($f, 'ok', LOCK_EX) === 2 && file_get_contents($f) === 'ok';
        $verrou = false;
        if ($h = fopen($f, 'c')) { $verrou = flock($h, LOCK_EX | LOCK_NB); flock($h, LOCK_UN); fclose($h); }
        unlink($f);
        return ['ecriture_lecture' => $ok, 'verrou_flock' => $verrou, 'nettoye' => !file_exists($f)];
    }),
    'temp_ecriture' => is_writable(sys_get_temp_dir()),
];

// --- Heure -----------------------------------------------------------------

$r['heure'] = essai(function () {
    $paris = new DateTimeZone('Europe/Paris');
    $t = $paris->getTransitions((new DateTime('2026-10-01'))->getTimestamp(), (new DateTime('2026-11-01'))->getTimestamp());
    return [
        'fuseau_defaut' => date_default_timezone_get(),
        'maintenant_paris' => (new DateTime('now', $paris))->format('Y-m-d H:i:s T'),
        'passage_heure_hiver_2026' => isset($t[1]) ? (new DateTime('@' . $t[1]['ts']))->setTimezone($paris)->format('Y-m-d H:i T') : null,
        'version_tzdata' => function_exists('timezone_version_get') ? timezone_version_get() : null,
    ];
});

// --- Base MySQL --------------------------------------------------------------

$r['base'] = essai(function () use ($serveur) {
    $cfg = require $serveur . '/config-test.php';
    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4",
        $cfg['user'], $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $v = $pdo->query("SELECT VERSION() v, @@character_set_server cs, @@collation_server co, @@time_zone tz,
        @@system_time_zone stz, NOW() maintenant, UTC_TIMESTAMP() utc, @@max_allowed_packet map, @@sql_mode sm,
        CONVERT_TZ('2026-10-25 12:00:00', 'UTC', 'Europe/Paris') tz_nomme,
        (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()) nb_tables")->fetch(PDO::FETCH_ASSOC);

    $table = 'jr_essai_' . bin2hex(random_bytes(3));
    $pdo->exec("CREATE TABLE `$table` (id INT AUTO_INCREMENT PRIMARY KEY, txt VARCHAR(50) NOT NULL,
        doc JSON NULL, quand DATETIME(3) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    try {
        $ins = $pdo->prepare("INSERT INTO `$table` (txt, doc, quand) VALUES (?, ?, NOW(3))");
        $pdo->beginTransaction();
        $ins->execute(['Junior 🎿 é', json_encode(['xp' => 42])]);
        $pdo->commit();
        $l = $pdo->query("SELECT txt, JSON_EXTRACT(doc, '$.xp') xp FROM `$table`")->fetch(PDO::FETCH_ASSOC);
        $ecriture = ['emoji_utf8mb4' => $l['txt'] === 'Junior 🎿 é', 'json' => (int) $l['xp'] === 42, 'transaction' => true];
        $verrou = $pdo->query("SELECT GET_LOCK('jr_essai', 0) a, RELEASE_LOCK('jr_essai') b")->fetch(PDO::FETCH_NUM);
        $ecriture['get_lock'] = $verrou === ['1', '1'] || $verrou === [1, 1];
    } finally {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    return [
        'version' => $v['v'], 'jeu_caracteres' => $v['cs'], 'collation' => $v['co'],
        'fuseau_session' => $v['tz'], 'fuseau_systeme' => $v['stz'], 'now' => $v['maintenant'], 'utc' => $v['utc'],
        'fuseaux_nommes_charges' => $v['tz_nomme'] !== null, 'max_allowed_packet' => (int) $v['map'],
        'sql_mode' => $v['sm'], 'tables_existantes' => (int) $v['nb_tables'], 'essai_ecriture' => $ecriture,
    ];
});

// --- Réseau sortant : HTTPS (APIs) et IMAP ------------------------------------

function https(string $url): array
{
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => false, CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_USERAGENT => 'JarvisJr-diagnostic', CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS]);
    curl_exec($c);
    $res = ['code_http' => curl_getinfo($c, CURLINFO_RESPONSE_CODE), 'http_version' => curl_getinfo($c, CURLINFO_HTTP_VERSION),
        'ms' => (int) round(curl_getinfo($c, CURLINFO_TOTAL_TIME) * 1000)];
    if (curl_errno($c)) $res['erreur'] = curl_error($c);
    return $res;
}

function imap(string $hote): array
{
    $t = microtime(true);
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $s = @stream_socket_client("ssl://$hote:993", $errno, $errstr, 6, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) return ['ok' => false, 'erreur' => "$errno $errstr"];
    stream_set_timeout($s, 6);
    $accueil = trim((string) fgets($s, 512));
    fwrite($s, "a1 LOGOUT\r\n");
    fclose($s);
    return ['ok' => str_starts_with($accueil, '* OK'), 'accueil' => substr($accueil, 0, 24), 'ms' => (int) round((microtime(true) - $t) * 1000)];
}

$r['reseau'] = [
    'https' => [
        'trello' => https('https://api.trello.com/1/members/me'),
        'google_agenda' => https('https://www.googleapis.com/calendar/v3/users/me/calendarList'),
        'google_oauth' => https('https://oauth2.googleapis.com/tokeninfo'),
        'push_apple' => https('https://web.push.apple.com/'),
        'push_google' => https('https://fcm.googleapis.com/'),
    ],
    'imap_993' => [
        'gmail' => imap('imap.gmail.com'),
        'icloud' => imap('imap.mail.me.com'),
        'ovh_mx' => imap('ssl0.ovh.net'),
    ],
];

$r['duree_ms'] = (int) round((microtime(true) - $debut) * 1000);

echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
