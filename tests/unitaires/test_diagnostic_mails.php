<?php
declare(strict_types=1);

function brut_diagnostic_test(int $uid, array $entetes): array
{
    return ['uid' => $uid, 'message_id' => "<m$uid@x>", 'date' => '2026-10-23T10:00:00Z', 'de_nom' => 'Alice', 'de_adresse' => 'alice@exemple.fr',
        'objet' => 'Objet secret', 'extrait' => 'Extrait secret', 'non_lu' => true, 'entetes' => $entetes, 'gm_msgid' => null];
}

test('diagnostic des en-têtes Gmail : compte non enregistré, un état lisible et aucune lecture', function () {
    base_de_test();
    faux_imap([]);
    egal(['etat' => 'non_connecte'], mails_entetes_diagnostic(microtime(true) + 20));
    egal([], appels_imap());
});

test('diagnostic des en-têtes Gmail : erreur de la boîte, son code et pas de plantage', function () {
    base_de_test();
    compte_mail_enregistrer('gmail', 'moi@gmail.com', 'Mdp-secret-diag');
    faux_imap(['gmail' => 'refus']);
    egal(['etat' => 'erreur', 'code' => 'refus'], mails_entetes_diagnostic(microtime(true) + 20));
});

test('diagnostic des en-têtes Gmail : 5 derniers mails, en-têtes utiles, règle UTC, jamais le mot de passe ni le contenu', function () {
    base_de_test();
    compte_mail_enregistrer('gmail', 'moi@gmail.com', 'Mdp-secret-diag');
    $mails = [];
    for ($i = 7; $i >= 1; $i--) {
        $mails[] = brut_diagnostic_test($i, $i === 7
            ? ['to' => ['colin@etu.utc.fr'], 'received' => [str_repeat('a', 500)], 'list-id' => ['<liste.x>'], 'objet' => ['ignoré']]
            : ['to' => ['moi@gmail.com'], 'received' => ['from mx.google.com']]);
    }
    faux_imap(['gmail' => ['uidvalidity' => 3, 'mails' => $mails]]);
    $r = mails_entetes_diagnostic(microtime(true) + 20);
    egal('ok', $r['etat']);
    egal(5, count($r['mails']));
    egal([7, 6, 5, 4, 3], array_column($r['mails'], 'uid'));
    egal(true, $r['mails'][0]['utc']);
    egal(false, $r['mails'][1]['utc']);
    egal('alice@exemple.fr', $r['mails'][0]['from']);
    egal(['colin@etu.utc.fr'], $r['mails'][0]['to']);
    egal(['<liste.x>'], $r['mails'][0]['list-id']);
    vrai(strlen($r['mails'][0]['received'][0]) <= 301, 'Received tronqué');
    $json = json_encode($r);
    foreach (['Mdp-secret-diag', 'secret', 'Objet', 'ignoré'] as $interdit) {
        vrai(!str_contains($json, $interdit), "« $interdit » ne doit pas figurer dans la sortie");
    }
    egal(['lecture gmail'], appels_imap());
});
