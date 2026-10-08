<?php
declare(strict_types=1);

test('chiffrement : aller-retour, et deux chiffrements du même texte diffèrent', function () {
    $a = chiffrer('jeton Google');
    $b = chiffrer('jeton Google');
    vrai($a !== $b, 'nonce aléatoire attendu');
    egal('jeton Google', dechiffrer($a));
});

test('chiffrement : un texte modifié est refusé', function () {
    $c = chiffrer('jeton Google');
    $c[30] = $c[30] === 'a' ? 'b' : 'a';
    leve(RuntimeException::class, fn() => dechiffrer($c));
});

test('chiffrement : un texte trop court est refusé', function () {
    leve(RuntimeException::class, fn() => dechiffrer('court'));
});

test('chiffrement : avec une autre clé, le déchiffrement échoue', function () {
    $c = chiffrer('jeton Google');
    $GLOBALS['JR_CONFIG']['cle_chiffrement'] = base64_encode(str_repeat("\x02", 32));
    leve(RuntimeException::class, fn() => dechiffrer($c));
});

test('chiffrement : une clé absente ou de mauvaise taille est refusée', function () {
    $GLOBALS['JR_CONFIG']['cle_chiffrement'] = base64_encode('trop courte');
    leve(RuntimeException::class, fn() => chiffrer('x'));
    $GLOBALS['JR_CONFIG']['cle_chiffrement'] = '';
    leve(RuntimeException::class, fn() => chiffrer('x'));
});
