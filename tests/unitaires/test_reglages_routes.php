<?php
declare(strict_types=1);

function rappels_valides(): array
{
    return [
        'brief' => ['heure' => '06:45', 'jours' => [5, 1, 3]],
        'cours' => ['actif' => false, 'minutes' => 30],
        'echeances' => ['actif' => true, 'heure' => '21:15'],
        'medians' => ['actif' => true, 'jours' => [3, 14, 1], 'heure' => '08:00'],
    ];
}

// MySQL range les clés d'un JSON dans son ordre : on compare sans tenir compte de l'ordre des clés
function trie(array $a): array
{
    ksort($a);
    return array_map(fn(mixed $v): mixed => is_array($v) && !array_is_list($v) ? trie($v) : $v, $a);
}

function sans_ok(array $corps): array
{
    unset($corps['ok']);
    return trie($corps);
}

function poster_rappels(int $n, mixed $rappels): array
{
    return appeler('POST', 'reglages/rappels', ['id_client' => uuid_test($n), 'rappels' => $rappels]);
}

test('réglages : sans session, lecture et écriture refusées (401)', function () {
    base_de_test();
    egal(401, traiter(requete(['methode' => 'GET', 'chemin' => 'reglages']))['statut']);
    egal(401, traiter(requete(['methode' => 'POST', 'chemin' => 'reglages/rappels', 'corps_brut' => '{}']))['statut']);
});

test('réglages : par défaut, rappels par défaut, planificateur jamais passé, pas de jeton Siri', function () {
    base_de_test();
    $r = appeler('GET', 'reglages');
    egal(200, $r['statut']);
    egal(trie(['rappels' => JR_REGLAGES_RAPPELS, 'planificateur' => ['dernier_passage' => null], 'siri' => ['jeton_cree' => false]]), sans_ok(corps($r)));
});

test('réglages : le dernier passage du planificateur et le jeton Siri apparaissent une fois créés', function () {
    base_de_test();
    fixer_heure('2026-10-22 08:00:00');
    reglage_ecrire('cron_dernier_passage', iso_paris(maintenant()));
    siri_jeton_creer();
    $c = corps(appeler('GET', 'reglages'));
    egal('2026-10-22T10:00:00+02:00', $c['planificateur']['dernier_passage']);
    egal(true, $c['siri']['jeton_cree']);
});

test('réglages : un POST valide est écrit tel quel (jours triés) et relu', function () {
    base_de_test();
    $r = poster_rappels(1, rappels_valides());
    egal(200, $r['statut']);
    $attendu = rappels_valides();
    $attendu['brief']['jours'] = [1, 3, 5];
    $attendu['medians']['jours'] = [1, 3, 14];
    egal(trie(['rappels' => $attendu]), sans_ok(corps($r)));
    egal(trie($attendu), trie(reglage_lire('rappels')));
    egal(trie($attendu), trie(corps(appeler('GET', 'reglages'))['rappels']));
});

test('réglages : le même id_client deux fois rend la même réponse sans réécrire', function () {
    base_de_test();
    $a = poster_rappels(1, rappels_valides());
    $autre = rappels_valides();
    $autre['cours']['minutes'] = 5;
    $b = poster_rappels(1, $autre);
    egal(sans_ok(corps($a)), sans_ok(corps($b)));
    egal(30, reglage_lire('rappels')['cours']['minutes']);
});

test('réglages : un POST illisible donne 400 reglages_invalides sans rien écrire', function () {
    base_de_test();
    $modif = function (callable $f): array {
        $r = rappels_valides();
        $f($r);
        return $r;
    };
    $cas = [
        'heure 25:00' => $modif(function (&$r) { $r['brief']['heure'] = '25:00'; }),
        'heure 7:30' => $modif(function (&$r) { $r['echeances']['heure'] = '7:30'; }),
        'heure 12:60' => $modif(function (&$r) { $r['medians']['heure'] = '12:60'; }),
        'heure non texte' => $modif(function (&$r) { $r['brief']['heure'] = 730; }),
        'jours vides' => $modif(function (&$r) { $r['brief']['jours'] = []; }),
        'jour 0' => $modif(function (&$r) { $r['brief']['jours'] = [0]; }),
        'jour 8' => $modif(function (&$r) { $r['brief']['jours'] = [8]; }),
        'doublon' => $modif(function (&$r) { $r['brief']['jours'] = [1, 1]; }),
        'jour texte' => $modif(function (&$r) { $r['brief']['jours'] = ['1']; }),
        'jours objet' => $modif(function (&$r) { $r['brief']['jours'] = ['a' => 1]; }),
        'minutes 20' => $modif(function (&$r) { $r['cours']['minutes'] = 20; }),
        'médians 2' => $modif(function (&$r) { $r['medians']['jours'] = [2]; }),
        'médians vides' => $modif(function (&$r) { $r['medians']['jours'] = []; }),
        'médians doublon' => $modif(function (&$r) { $r['medians']['jours'] = [3, 3]; }),
        'clé en trop (champ)' => $modif(function (&$r) { $r['cours']['autre'] = 1; }),
        'clé en trop (partie)' => $modif(function (&$r) { $r['autre'] = []; }),
        'clé manquante (champ)' => $modif(function (&$r) { unset($r['cours']['minutes']); }),
        'clé manquante (partie)' => $modif(function (&$r) { unset($r['medians']); }),
        'actif oui' => $modif(function (&$r) { $r['cours']['actif'] = 'oui'; }),
        'actif 1' => $modif(function (&$r) { $r['echeances']['actif'] = 1; }),
        'partie non objet' => $modif(function (&$r) { $r['brief'] = 'x'; }),
        'rappels liste' => [],
        'rappels texte' => 'x',
        'rappels null' => null,
    ];
    $n = 0;
    foreach ($cas as $nom => $rappels) {
        $r = poster_rappels(++$n, $rappels);
        egal(400, $r['statut'], $nom);
        egal('reglages_invalides', corps($r)['erreur']['code'] ?? corps($r)['code'] ?? null, $nom);
    }
    egal(null, reglage_lire('rappels'));
});

test('réglages : une déconnexion simple garde l\'abonnement, session_id remis à nul', function () {
    base_de_test();
    $cookie = session_creer('iPhone');
    $session = session_trouver($cookie['valeur']);
    abonnement_enregistrer(abonnement_exemple(), (int) $session['id']);
    session_supprimer((int) $session['id']);
    egal(1, (int) db()->query('SELECT COUNT(*) FROM abonnements')->fetchColumn());
    egal(null, db()->query('SELECT session_id FROM abonnements')->fetchColumn() ?: null);
    egal(0, (int) db()->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
});
