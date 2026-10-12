<?php
declare(strict_types=1);

function nombre_abonnements(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM abonnements')->fetchColumn();
}

test('routes notifications : sans session, abonnement, oubli et test refusés (401)', function () {
    base_de_test();
    foreach (['abonnements', 'abonnements/supprimer', 'notifications/test'] as $chemin) {
        $r = traiter(requete(['methode' => 'POST', 'chemin' => $chemin, 'corps_brut' => '{}']));
        egal(401, $r['statut'], $chemin);
    }
});

test("routes notifications : un abonnement valide est gardé avec sa session ; renvoyé, il reste unique et vu_le avance", function () {
    base_de_test();
    fixer_heure('2026-10-12 08:00:00');
    egal(['ok' => true], corps(appeler('POST', 'abonnements', ['abonnement' => abonnement_exemple()])));
    egal(1, nombre_abonnements());
    $ligne = db()->query('SELECT session_id, vu_le FROM abonnements')->fetch();
    egal((int) db()->query('SELECT MAX(id) FROM sessions')->fetchColumn(), (int) $ligne['session_id']);
    egal('2026-10-12 08:00:00', $ligne['vu_le']);
    fixer_heure('2026-10-13 09:30:00');
    egal(200, appeler('POST', 'abonnements', ['abonnement' => abonnement_exemple()])['statut']);
    egal(1, nombre_abonnements());
    egal('2026-10-13 09:30:00', db()->query('SELECT vu_le FROM abonnements')->fetchColumn());
});

test('routes notifications : endpoint hors liste ou abonnement absent → 400 abonnement_invalide, rien de gardé', function () {
    base_de_test();
    // Comme en ligne (site/api/index.php) : un avertissement PHP devient une erreur, donc une réponse 500
    set_error_handler('erreur_php');
    try {
        foreach ([['abonnement' => abonnement_exemple('https://exemple.fr/x')], ['abonnement' => 'texte'], [], ['abonnement' => ['endpoint' => ['x'], 'keys' => 'k']]] as $corps) {
            $r = appeler('POST', 'abonnements', $corps);
            egal(400, $r['statut'], json_encode($corps));
            egal('abonnement_invalide', corps($r)['erreur']['code']);
        }
    } finally {
        restore_error_handler();
    }
    egal(0, nombre_abonnements());
});

test("routes notifications : l'oubli supprime cet abonnement seulement ; un endpoint inconnu ne gêne pas", function () {
    base_de_test();
    appeler('POST', 'abonnements', ['abonnement' => abonnement_exemple('https://web.push.apple.com/un')]);
    appeler('POST', 'abonnements', ['abonnement' => abonnement_exemple('https://web.push.apple.com/deux')]);
    egal(['ok' => true], corps(appeler('POST', 'abonnements/supprimer', ['endpoint' => 'https://web.push.apple.com/un'])));
    egal(['https://web.push.apple.com/deux'], db()->query('SELECT endpoint FROM abonnements')->fetchAll(PDO::FETCH_COLUMN));
    egal(200, appeler('POST', 'abonnements/supprimer', ['endpoint' => 'https://web.push.apple.com/inconnu'])['statut']);
    egal(200, appeler('POST', 'abonnements/supprimer', [])['statut']);
    set_error_handler('erreur_php');
    try {
        egal(200, appeler('POST', 'abonnements/supprimer', ['endpoint' => ['x']])['statut']);
    } finally {
        restore_error_handler();
    }
    egal(1, nombre_abonnements());
});

test('routes notifications : test sans abonnement → rien envoyé ; avec un abonnement → un envoi à son endpoint', function () {
    base_de_test();
    faux_http([]);
    egal(['ok' => true, 'envoyes' => 0, 'supprimes' => 0, 'echecs' => 0, 'a_reessayer' => 0], corps(appeler('POST', 'notifications/test')));
    appeler('POST', 'abonnements', ['abonnement' => abonnement_exemple()]);
    faux_http(['POST https://web.push.apple.com/QGuQ-abc' => ['statut' => 201, 'corps' => '']]);
    egal(['ok' => true, 'envoyes' => 1, 'supprimes' => 0, 'echecs' => 0, 'a_reessayer' => 0], corps(appeler('POST', 'notifications/test')));
    egal(['POST https://web.push.apple.com/QGuQ-abc'], appels_http());
});

test("routes notifications : GET donnees donne la clé publique VAPID à l'iPhone, jamais la privée", function () {
    base_de_test();
    fixer_heure('2026-10-09 13:40:00');
    faux_http([BATCH => ['json' => batch_trello()]]);
    $r = appeler('GET', 'donnees');
    egal(['cle' => RFC_AS_PUB], corps($r)['notifications']);
    vrai(!str_contains($r['corps'], RFC_AS_PRIV), 'clé privée absente de la réponse');
});

test("routes notifications : « Déconnecter tous les appareils » supprime aussi les abonnements (un iPhone perdu ne reçoit plus rien)", function () {
    base_de_test();
    appeler('POST', 'abonnements', ['abonnement' => abonnement_exemple('https://web.push.apple.com/un')]);
    appeler('POST', 'abonnements', ['abonnement' => abonnement_exemple('https://web.push.apple.com/deux')]);
    egal(200, appeler('POST', 'deconnexion-partout')['statut']);
    egal(0, nombre_abonnements());
});
