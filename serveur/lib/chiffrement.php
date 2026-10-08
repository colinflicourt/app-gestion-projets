<?php
// Chiffrement des secrets gardés en base (jetons Google, plus tard mots de passe d'application) : libsodium.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';

function cle_chiffrement(): string
{
    $cle = base64_decode((string) config('cle_chiffrement'), true);
    if ($cle === false || strlen($cle) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('Clé de chiffrement absente ou invalide');
    }
    return $cle;
}

function chiffrer(string $clair): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return $nonce . sodium_crypto_secretbox($clair, $nonce, cle_chiffrement());
}

function dechiffrer(string $chiffre): string
{
    if (strlen($chiffre) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
        throw new RuntimeException('Texte chiffré invalide');
    }
    $clair = sodium_crypto_secretbox_open(
        substr($chiffre, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        substr($chiffre, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        cle_chiffrement()
    );
    if ($clair === false) {
        throw new RuntimeException('Déchiffrement impossible');
    }
    return $clair;
}
