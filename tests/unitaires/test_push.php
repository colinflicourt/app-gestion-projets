<?php
declare(strict_types=1);

test('push : le vecteur de la RFC 8291 est rejoué octet pour octet', function () {
    $ephemere = cle_p256(b64url_decoder(RFC_AS_PUB), b64url_decoder(RFC_AS_PRIV));
    $corps = chiffrer_message(RFC_TEXTE, b64url_decoder(RFC_UA_PUB), b64url_decoder(RFC_AUTH), $ephemere, b64url_decoder(RFC_SEL));
    egal(RFC_CORPS, b64url_encoder($corps));
});

test('push : sans éphémère ni sel imposés, deux chiffrements diffèrent', function () {
    $a = chiffrer_message('x', b64url_decoder(RFC_UA_PUB), b64url_decoder(RFC_AUTH));
    $b = chiffrer_message('x', b64url_decoder(RFC_UA_PUB), b64url_decoder(RFC_AUTH));
    vrai($a !== $b, 'clé éphémère et sel aléatoires attendus');
});

test('push : en-tête VAPID valide, audience = origine de l\'endpoint, signature vérifiable', function () {
    $vapid = ['publique' => RFC_AS_PUB, 'privee' => RFC_AS_PRIV, 'sujet' => 'mailto:contact@qyris.fr'];
    $entete = vapid_entete('https://web.push.apple.com/QGuQ', $vapid, 1_760_000_000);
    vrai((bool) preg_match('/^vapid t=([^.]+)\.([^.]+)\.([^,]+), k=(.+)$/', $entete, $m), $entete);
    egal(RFC_AS_PUB, $m[4]);
    $charge = json_decode(b64url_decoder($m[2]), true);
    egal('https://web.push.apple.com', $charge['aud']);
    egal(1_760_000_000 + 12 * 3600, $charge['exp']);
    $sig = b64url_decoder($m[3]);
    egal(64, strlen($sig));
    $der = der_depuis_brut($sig);
    $publique = openssl_pkey_get_details(cle_p256(b64url_decoder(RFC_AS_PUB), b64url_decoder(RFC_AS_PRIV)))['key'];
    egal(1, openssl_verify("$m[1].$m[2]", $der, $publique, OPENSSL_ALGO_SHA256));
});

test('push : r ou s de 33 octets (octet nul en tête) donne 64 octets', function () {
    $r = "\x00" . str_repeat("\x80", 32);
    $s = str_repeat("\x01", 32);
    $der = "\x30" . chr(4 + 33 + 32) . "\x02\x21" . $r . "\x02\x20" . $s;
    egal(str_repeat("\x80", 32) . $s, brut_depuis_der($der));
});

test('push : base64url invalide refusé', function () {
    leve(InvalidArgumentException::class, fn() => b64url_decoder('pas*valide'));
});
