<?php
declare(strict_types=1);

// Comme cron-job.org : POST sans origine, sans corps, sans type de contenu
function appel_cron(?string $jeton): array
{
    return traiter(requete([
        'methode' => 'POST',
        'chemin' => 'cron',
        'entetes' => $jeton === null ? [] : ['authorization' => "Bearer $jeton"],
    ]));
}

test('route cron : sans jeton, mauvais jeton ou jeton non configuré → 404 comme une adresse inconnue, et aucun passage', function () {
    base_de_test();
    foreach ([null, '', 'mauvais', JETON_CRON_TEST . 'x', substr(JETON_CRON_TEST, 0, -1)] as $jeton) {
        $r = appel_cron($jeton);
        egal(404, $r['statut'], var_export($jeton, true));
        egal('route_inconnue', corps($r)['erreur']['code']);
        vrai(!isset($r['apres']), 'aucun passage');
    }
    $GLOBALS['JR_CONFIG']['cron']['jeton'] = '';
    egal(404, appel_cron('')['statut']);
    unset($GLOBALS['JR_CONFIG']['cron']);
    egal(404, appel_cron(JETON_CRON_TEST)['statut']);
});

test('route cron : le bon jeton → 200 tout de suite, le passage se fait après la réponse', function () {
    base_de_test();
    sans_brief_en_base();
    fixer_heure('2026-10-14 08:00:00');
    copie_trello([]);
    $r = appel_cron(JETON_CRON_TEST);
    egal(200, $r['statut']);
    egal(['ok' => true], corps($r));
    egal(null, reglage_lire('cron_dernier_passage'));
    faux_http([]);
    egal(true, ($r['apres'])()['verrou']);
    egal('2026-10-14T10:00:00+02:00', reglage_lire('cron_dernier_passage'));
});

test("routeur : une route machine se passe de l'origine de l'app et lit un corps JSON s'il y en a un ; les autres routes exigent toujours l'origine", function () {
    base_de_test();
    $GLOBALS['JR_ROUTES_SUPPLEMENTAIRES'] = [
        'POST essai-machine' => ['publique' => true, 'machine' => true, 'action' => fn(array $r, ?array $s): array => reponse_json(['corps' => $r['corps'], 'jeton' => jeton_bearer($r)])],
        'POST essai-echo' => ['publique' => true, 'action' => fn(array $r, ?array $s): array => reponse_json(['corps' => $r['corps']])],
    ];
    $machine = fn(string $corps, array $entetes): array => traiter(requete(['methode' => 'POST', 'chemin' => 'essai-machine', 'corps_brut' => $corps, 'entetes' => $entetes]));
    egal(['ok' => true, 'corps' => ['texte' => 'QYRIS appeler'], 'jeton' => 'abc'], corps($machine('{"texte":"QYRIS appeler"}', ['content-type' => 'application/json', 'authorization' => 'Bearer abc'])));
    egal(['ok' => true, 'corps' => [], 'jeton' => ''], corps($machine('', [])));
    egal(415, $machine('texte', [])['statut']);
    egal(403, traiter(requete(['methode' => 'POST', 'chemin' => 'essai-echo', 'corps_brut' => '{}', 'entetes' => ['content-type' => 'application/json']]))['statut']);
});
