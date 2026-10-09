<?php
declare(strict_types=1);

test("session : créer pose un cookie __Host-jr sûr, la base ne garde que l'empreinte", function () {
    $pdo = base_de_test();
    $cookie = session_creer('iPhone');
    egal('__Host-jr', $cookie['nom']);
    vrai((bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $cookie['valeur']), 'jeton de 43 caractères attendu');
    $options = $cookie['options'];
    unset($options['expires']);
    egal(['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'], $options);
    egal(0, (int) $pdo->query('SELECT COUNT(*) FROM sessions WHERE empreinte = ' . $pdo->quote($cookie['valeur']))->fetchColumn());
    vrai(session_trouver($cookie['valeur']) !== null, 'session retrouvée attendue');
});

test('session : un cookie absent, inconnu ou forgé ne donne aucune session, sans erreur', function () {
    base_de_test();
    $faux = [null, '', 'abc', str_repeat('a', 43), "' OR 1=1 -- " . str_repeat('x', 31), str_repeat('é', 43), str_repeat('a', 5000)];
    foreach ($faux as $jeton) {
        egal(null, session_trouver($jeton));
    }
});

test('session : expirée après 90 jours sans visite, et effacée', function () {
    $pdo = base_de_test();
    fixer_heure('2026-10-08 10:00:00');
    $cookie = session_creer('iPhone');
    fixer_heure('2027-01-06 10:00:01');
    egal(null, session_trouver($cookie['valeur']));
    egal(0, (int) $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
});

test('session : prolongée au plus une fois par jour, pour 90 jours à partir de la visite', function () {
    $pdo = base_de_test();
    fixer_heure('2026-10-08 10:00:00');
    $cookie = session_creer('iPhone');
    fixer_heure('2026-10-09 09:00:00');
    egal(null, session_prolonger_si_besoin(session_trouver($cookie['valeur'])));
    fixer_heure('2026-10-09 11:00:00');
    $renvoye = session_prolonger_si_besoin(session_trouver($cookie['valeur']));
    egal($cookie['valeur'], $renvoye['valeur']);
    egal('2027-01-07 11:00:00', $pdo->query('SELECT expire_le FROM sessions')->fetchColumn());
});

test('session : supprimer_toutes déconnecte tous les appareils', function () {
    base_de_test();
    $a = session_creer('iPhone');
    $b = session_creer('Mac');
    session_supprimer_toutes();
    egal(null, session_trouver($a['valeur']));
    egal(null, session_trouver($b['valeur']));
});

test('session : le cookie effacé a une valeur vide et une date passée', function () {
    $cookie = cookie_session_efface();
    egal('', $cookie['valeur']);
    egal(1, $cookie['options']['expires']);
});
