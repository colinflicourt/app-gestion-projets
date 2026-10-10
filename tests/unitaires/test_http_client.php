<?php
declare(strict_types=1);

test("faux client HTTP : par défaut, tout appel sortant fait échouer le test", function () {
    $e = leve(EchecTest::class, fn() => http_requete('GET', 'https://api.trello.com/1/batch'));
    vrai(str_contains($e->getMessage(), 'GET https://api.trello.com/1/batch'), "l'appel doit être nommé");
});

test('faux client HTTP : réponse par début d\'adresse, liste consommée dans l\'ordre, exception levée, appels notés', function () {
    faux_http([
        'GET https://exemple.fr/a' => [['statut' => 401, 'corps' => 'non'], ['json' => ['ok' => true]]],
        'POST https://exemple.fr/' => new ErreurReseau('délai dépassé'),
    ]);
    egal(['statut' => 401, 'corps' => 'non'], http_requete('GET', 'https://exemple.fr/a?x=1'));
    egal(['ok' => true], http_json(http_requete('GET', 'https://exemple.fr/a')));
    egal(['ok' => true], http_json(http_requete('GET', 'https://exemple.fr/a')));
    leve(ErreurReseau::class, fn() => http_requete('POST', 'https://exemple.fr/b', ['Content-Type: application/json'], '{}'));
    egal(['GET https://exemple.fr/a?x=1', 'GET https://exemple.fr/a', 'GET https://exemple.fr/a', 'POST https://exemple.fr/b'], appels_http());
    egal('{}', $GLOBALS['JR_APPELS_HTTP'][3]['corps']);
    leve(EchecTest::class, fn() => http_requete('PUT', 'https://exemple.fr/a'));
});
