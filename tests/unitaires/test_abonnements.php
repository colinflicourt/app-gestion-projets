<?php
declare(strict_types=1);

function abonnement_exemple(string $endpoint = 'https://web.push.apple.com/QGuQ-abc'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => RFC_UA_PUB, 'auth' => RFC_AUTH]];
}

test('abonnements : liste blanche des endpoints', function () {
    vrai(endpoint_accepte('https://web.push.apple.com/QGuQ'), 'https://web.push.apple.com/QGuQ');
    vrai(endpoint_accepte('https://api.push.apple.com/x'), 'https://api.push.apple.com/x');
    vrai(endpoint_accepte('https://fcm.googleapis.com/fcm/send/x'), 'https://fcm.googleapis.com/fcm/send/x');
    vrai(!endpoint_accepte('http://web.push.apple.com/x'), 'http://web.push.apple.com/x');
    vrai(!endpoint_accepte('https://web.push.apple.com.exemple.fr/x'), 'https://web.push.apple.com.exemple.fr/x');
    vrai(!endpoint_accepte('https://exemple.fr/web.push.apple.com'), 'https://exemple.fr/web.push.apple.com');
    vrai(!endpoint_accepte('https://user@web.push.apple.com:8443/x'), 'https://user@web.push.apple.com:8443/x');
    vrai(!endpoint_accepte('pas une adresse'), 'pas une adresse');
});

test('abonnements : le même abonnement renvoyé ne crée pas de doublon', function () {
    base_de_test();
    abonnement_enregistrer(abonnement_exemple(), null);
    abonnement_enregistrer(abonnement_exemple(), null);
    egal(1, (int) db()->query('SELECT COUNT(*) FROM abonnements')->fetchColumn());
});

test('abonnements : un endpoint hors liste est refusé', function () {
    base_de_test();
    leve(ErreurApi::class, fn() => abonnement_enregistrer(abonnement_exemple('https://exemple.fr/x'), null));
});

test('abonnements : un 410 supprime cet abonnement sans empêcher l\'envoi aux autres', function () {
    base_de_test();
    abonnement_enregistrer(abonnement_exemple('https://web.push.apple.com/mort'), null);
    abonnement_enregistrer(abonnement_exemple('https://web.push.apple.com/vivant'), null);
    faux_http([
        'POST https://web.push.apple.com/mort' => ['statut' => 410, 'corps' => ''],
        'POST https://web.push.apple.com/vivant' => ['statut' => 201, 'corps' => ''],
    ]);
    egal(['envoyes' => 1, 'supprimes' => 1, 'echecs' => 0, 'a_reessayer' => 0], notifier_tous(['titre' => 'T', 'texte' => 'x', 'url' => '/#/aujourdhui', 'tag' => 'test']));
    egal(1, (int) db()->query('SELECT COUNT(*) FROM abonnements')->fetchColumn());
});

test('abonnements : en-têtes d\'envoi', function () {
    base_de_test();
    abonnement_enregistrer(abonnement_exemple(), null);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    notifier_tous(['titre' => 'T', 'texte' => 'x', 'url' => '/', 'tag' => 't'], 'high', 900);
    $entetes = implode("\n", $GLOBALS['JR_APPELS_HTTP'][0]['entetes']);
    vrai(str_contains($entetes, 'Content-Encoding: aes128gcm'), 'Content-Encoding: aes128gcm');
    vrai(str_contains($entetes, 'TTL: 900'), 'TTL: 900');
    vrai(str_contains($entetes, 'Urgency: high'), 'Urgency: high');
    vrai(str_contains($entetes, 'Authorization: vapid t='), 'Authorization: vapid t=');
});

test("abonnements : 429, 5xx et panne réseau sont à réessayer, un 403 non", function () {
    base_de_test();
    abonnement_enregistrer(abonnement_exemple(), null);
    $message = ['titre' => 'T', 'texte' => 'x', 'url' => '/#/aujourdhui', 'tag' => 't'];
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 429, 'corps' => '']]);
    egal(['envoyes' => 0, 'supprimes' => 0, 'echecs' => 1, 'a_reessayer' => 1], notifier_tous($message));
    faux_http(['POST https://web.push.apple.com/' => new ErreurReseau('Réseau : délai dépassé')]);
    egal(1, notifier_tous($message)['a_reessayer']);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 403, 'corps' => '']]);
    egal(['envoyes' => 0, 'supprimes' => 0, 'echecs' => 1, 'a_reessayer' => 0], notifier_tous($message));
});
