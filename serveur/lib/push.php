<?php
declare(strict_types=1);

// Notifications web : chiffrement aes128gcm (RFC 8291 et 8188) et JWT VAPID (RFC 8292). Calcul pur.

function b64url_encoder(string $octets): string
{
    return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
}

function b64url_decoder(string $texte): string
{
    $octets = preg_match('/^[A-Za-z0-9_-]*$/', $texte) ? base64_decode(strtr($texte, '-_', '+/'), true) : false;
    if ($octets === false) {
        throw new InvalidArgumentException('base64url invalide');
    }
    return $octets;
}

function cle_p256(string $publique65, ?string $privee32 = null): OpenSSLAsymmetricKey
{
    if (strlen($publique65) !== 65 || $publique65[0] !== "\x04") {
        throw new InvalidArgumentException('clé publique P-256 invalide');
    }
    $ec = ['curve_name' => 'prime256v1', 'x' => substr($publique65, 1, 32), 'y' => substr($publique65, 33, 32)];
    if ($privee32 !== null) {
        $ec['d'] = $privee32;
    }
    $cle = openssl_pkey_new(['ec' => $ec]);
    if ($cle === false) {
        throw new InvalidArgumentException('clé P-256 refusée');
    }
    return $cle;
}

function publique_brute(OpenSSLAsymmetricKey $cle): string
{
    $ec = openssl_pkey_get_details($cle)['ec'];
    return "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
}

function chiffrer_message(string $texte, string $p256dh, string $auth, ?OpenSSLAsymmetricKey $ephemere = null, ?string $sel = null): string
{
    $ephemere ??= openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $sel ??= random_bytes(16);
    $as_pub = publique_brute($ephemere);
    $secret = openssl_pkey_derive(cle_p256($p256dh), $ephemere);
    $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $p256dh . $as_pub, $auth);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sel);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sel);
    $chiffre = openssl_encrypt($texte . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $sel . pack('N', 4096) . chr(65) . $as_pub . $chiffre . $tag;
}

// Signature ES256 : openssl rend du DER, JWT attend r‖s sur 64 octets
function brut_depuis_der(string $der): string
{
    $o = 3;
    $r = substr($der, $o + 1, ord($der[$o]));
    $o += 1 + ord($der[$o]) + 1;
    $s = substr($der, $o + 1, ord($der[$o]));
    $bloc = fn(string $n): string => str_pad(ltrim($n, "\0"), 32, "\0", STR_PAD_LEFT);
    return $bloc($r) . $bloc($s);
}

function der_depuis_brut(string $brut): string
{
    $entier = function (string $n): string {
        $n = ltrim($n, "\0");
        if ($n === '' || ord($n[0]) & 0x80) {
            $n = "\0" . $n;
        }
        return "\x02" . chr(strlen($n)) . $n;
    };
    $corps = $entier(substr($brut, 0, 32)) . $entier(substr($brut, 32, 32));
    return "\x30" . chr(strlen($corps)) . $corps;
}

function vapid_entete(string $endpoint, array $vapid, int $maintenant): string
{
    $p = parse_url($endpoint);
    $charge = ['aud' => $p['scheme'] . '://' . $p['host'], 'exp' => $maintenant + 12 * 3600, 'sub' => $vapid['sujet']];
    $signe = b64url_encoder('{"typ":"JWT","alg":"ES256"}') . '.' . b64url_encoder(json_encode($charge, JSON_UNESCAPED_SLASHES));
    $cle = cle_p256(b64url_decoder($vapid['publique']), b64url_decoder($vapid['privee']));
    openssl_sign($signe, $der, $cle, OPENSSL_ALGO_SHA256);
    return 'vapid t=' . $signe . '.' . b64url_encoder(brut_depuis_der($der)) . ', k=' . $vapid['publique'];
}
