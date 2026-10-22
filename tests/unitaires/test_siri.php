<?php
declare(strict_types=1);

// Comme le raccourci Siri : POST sans origine, jeton en Bearer, corps JSON {texte}
function appel_siri(?string $jeton, mixed $texte = 'acheter du pain', ?string $corps_brut = null): array
{
    $entetes = ['content-type' => 'application/json'];
    if ($jeton !== null) {
        $entetes['authorization'] = "Bearer $jeton";
    }
    return traiter(requete([
        'methode' => 'POST',
        'chemin' => 'siri',
        'entetes' => $entetes,
        'corps_brut' => $corps_brut ?? json_encode(['texte' => $texte], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]));
}

function creer_jeton_siri(): string
{
    return corps(appeler('POST', 'reglages/siri'))['jeton'];
}

// Trello qui accepte n cartes ; rend la liste des corps envoyés
function trello_siri(int $cartes = 5): void
{
    copie_trello([]);
    faux_http(['POST https://api.trello.com/1/cards' => array_map(fn(int $i): array => ['json' => carte_trello('perso', 'À faire', $i)], range(1, $cartes))]);
}

function cartes_envoyees(): array
{
    return array_map(fn(array $a): array => json_decode((string) $a['corps'], true), $GLOBALS['JR_APPELS_HTTP']);
}

function appels_siri_en_base(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM siri_appels')->fetchColumn();
}

test('siri_destination : un nom de domaine en tête, sans accent ni majuscule, est retiré avec la ponctuation qui le suit', function () {
    egal(['domaine' => 'qyris', 'titre' => 'Relancer Sébastien'], siri_destination('QYRIS relancer Sébastien'));
    egal(['domaine' => 'etudes', 'titre' => 'Réviser'], siri_destination('etudes: réviser'));
    egal(['domaine' => 'etudes', 'titre' => 'Réviser le TD'], siri_destination('Études, réviser le TD'));
    egal(['domaine' => 'etudes', 'titre' => 'Réviser'], siri_destination('ÉTUDES réviser'));
    egal(['domaine' => 'asso', 'titre' => 'Appeler le trésorier'], siri_destination("  asso ... \n appeler le trésorier "));
    egal(['domaine' => 'perso', 'titre' => 'Acheter du pain'], siri_destination('acheter du pain'));
    egal(['domaine' => 'perso', 'titre' => 'Étudier le plan'], siri_destination('étudier le plan'));
});

test("siri_destination : un mot qui commence comme un domaine reste en Perso avec le texte entier ; un domaine seul laisse un titre vide", function () {
    egal(['domaine' => 'perso', 'titre' => 'Assomption à fêter'], siri_destination('Assomption à fêter'));
    egal(['domaine' => 'perso', 'titre' => 'Perso-truc à voir'], siri_destination('perso-truc à voir'));
    egal('', siri_destination('Perso')['titre']);
    egal('', siri_destination(" Asso : \n")['titre']);
    egal('', siri_destination('   ')['titre']);
});

test("siri_empreinte : la même phrase à la casse et aux espaces près donne la même empreinte", function () {
    egal(siri_empreinte('Acheter  du pain'), siri_empreinte("  acheter du\npain "));
    vrai(siri_empreinte('acheter du pain') !== siri_empreinte('acheter du lait'), 'phrases différentes');
    egal(64, strlen(siri_empreinte('x')));
});

test("POST reglages/siri : session exigée, jeton de 43 caractères base64url montré une fois, seule l'empreinte part en base", function () {
    base_de_test();
    egal(401, traiter(requete(['methode' => 'POST', 'chemin' => 'reglages/siri', 'corps_brut' => '{}']))['statut']);
    $r = appeler('POST', 'reglages/siri');
    egal(200, $r['statut']);
    $jeton = corps($r)['jeton'];
    egal(1, preg_match('/^[A-Za-z0-9_-]{43}$/', $jeton), 'format');
    egal(hash('sha256', $jeton), jeton_lire('siri'));
    $brut = (string) db()->query("SELECT contenu FROM jetons WHERE service = 'siri'")->fetchColumn();
    vrai(!str_contains($brut, $jeton) && !str_contains((string) base64_decode($brut, true), $jeton), 'jamais le jeton en clair');
    vrai($jeton !== creer_jeton_siri(), 'chaque appel crée un nouveau jeton');
    egal(1, (int) db()->query("SELECT COUNT(*) FROM jetons WHERE service = 'siri'")->fetchColumn());
    egal(403, traiter(requete(['methode' => 'POST', 'chemin' => 'reglages/siri', 'corps_brut' => '{}', 'cookies' => cookies_session(), 'entetes' => ['origin' => 'https://autre.exemple', 'content-type' => 'application/json']]))['statut'], 'origine');
});

test('siri : sans jeton, jeton faux, mal formé, ou aucun jeton créé → 404 comme une adresse inconnue, aucune carte', function () {
    base_de_test();
    trello_siri();
    $jamais_cree = appel_siri(str_repeat('a', 43));
    egal(404, $jamais_cree['statut'], 'aucun jeton créé');
    egal('route_inconnue', corps($jamais_cree)['erreur']['code']);
    $jeton = creer_jeton_siri();
    foreach ([null, '', 'mauvais', substr($jeton, 0, -1), $jeton . 'x', str_repeat('A', 43), strtoupper($jeton)] as $faux) {
        $r = appel_siri($faux);
        egal(404, $r['statut'], var_export($faux, true));
        egal('route_inconnue', corps($r)['erreur']['code']);
    }
    egal([], appels_http(), 'Trello jamais appelé');
    egal(0, appels_siri_en_base());
});

test("siri : un nouveau jeton rend l'ancien inutilisable", function () {
    base_de_test();
    trello_siri();
    $ancien = creer_jeton_siri();
    egal(200, appel_siri($ancien)['statut']);
    $nouveau = creer_jeton_siri();
    egal(404, appel_siri($ancien, 'autre chose')['statut']);
    egal(200, appel_siri($nouveau, 'autre chose')['statut']);
});

test("siri : le bon jeton crée une carte dans Perso · À faire, pas urgente, sans échéance, et répond en texte brut", function () {
    base_de_test();
    trello_siri();
    $jeton = creer_jeton_siri();
    $r = appel_siri($jeton, 'acheter du pain');
    egal(200, $r['statut']);
    egal('Ajouté dans Perso.', $r['corps']);
    egal('text/plain; charset=utf-8', $r['entetes']['Content-Type']);
    egal('no-store', $r['entetes']['Cache-Control']);
    egal('nosniff', $r['entetes']['X-Content-Type-Options']);
    egal(['POST https://api.trello.com/1/cards'], appels_http());
    egal([['idList' => fabrique_trello_ids()['tableaux']['perso']['colonnes']['À faire'], 'name' => 'Acheter du pain', 'pos' => 'top']], cartes_envoyees());
    egal(1, appels_siri_en_base());
});

test('siri : un nom de domaine en tête choisit le tableau et le texte lu', function () {
    base_de_test();
    foreach (['qyris' => ['QYRIS relancer Sébastien', 'Relancer Sébastien', 'QYRIS'], 'asso' => ['asso: appeler le BDE', 'Appeler le BDE', 'Asso'], 'etudes' => ['Études réviser le TD', 'Réviser le TD', 'Études']] as $domaine => [$dit, $titre, $lu]) {
        trello_siri();
        $jeton = creer_jeton_siri();
        $r = appel_siri($jeton, $dit);
        egal("Ajouté dans $lu.", $r['corps']);
        $carte = cartes_envoyees()[0];
        egal([fabrique_trello_ids()['tableaux'][$domaine]['colonnes']['À faire'], $titre], [$carte['idList'], $carte['name']]);
    }
});

test("siri : texte absent, pas une chaîne, vide, domaine seul ou de plus de 500 caractères → 400 « Je n'ai rien entendu. », sans carte ni appel noté", function () {
    base_de_test();
    trello_siri();
    $jeton = creer_jeton_siri();
    foreach ([[null, '{}'], [null, '{"texte":42}'], [null, '{"texte":["a"]}'], [null, '{"texte":null}'], ['', null], ['   ', null], ['Perso', null], [str_repeat('é', 501), null]] as [$texte, $brut]) {
        $r = appel_siri($jeton, $texte, $brut);
        egal(400, $r['statut'], (string) $brut . var_export($texte, true));
        egal("Je n'ai rien entendu.", $r['corps']);
        egal('text/plain; charset=utf-8', $r['entetes']['Content-Type']);
    }
    egal([], appels_http());
    egal(0, appels_siri_en_base());
    egal(200, appel_siri($jeton, str_repeat('é', 300))['statut'], '300 caractères passent');
    $long = appel_siri($jeton, str_repeat('é', 500));
    egal(400, $long['statut'], '500 caractères : le titre dépasse 300, refusé en texte brut');
    egal('text/plain; charset=utf-8', $long['entetes']['Content-Type']);
});

test('siri : 30 appels réussis dans l\'heure glissante → le suivant est refusé en 429 ; les plus anciens ne comptent plus', function () {
    base_de_test();
    fixer_heure('2026-10-22 10:00:00');
    trello_siri();
    $jeton = creer_jeton_siri();
    $ajouter = fn(string $quand, int $n) => db()->exec("INSERT INTO siri_appels (recu_le, empreinte) SELECT '$quand', SHA2(CONCAT('x', seq), 256) FROM (" . implode(' UNION ALL ', array_map(fn(int $i): string => "SELECT $i AS seq", range(1, $n))) . ') t');
    $ajouter('2026-10-22 08:59:59', 30);
    egal(200, appel_siri($jeton, 'un')['statut'], 'ceux de plus d\'une heure ne comptent pas');
    db()->exec('DELETE FROM siri_appels');
    $ajouter('2026-10-22 09:30:00', 29);
    egal(200, appel_siri($jeton, 'deux')['statut'], 'le 30e passe');
    $r = appel_siri($jeton, 'trois');
    egal(429, $r['statut']);
    egal('Trop de notes d\'un coup. Réessaie dans une heure.', $r['corps']);
    egal('text/plain; charset=utf-8', $r['entetes']['Content-Type']);
    egal(2, count($GLOBALS['JR_APPELS_HTTP']), 'pas de carte pour le refusé');
    fixer_heure('2026-10-22 10:31:00');
    trello_siri();
    egal(200, appel_siri($jeton, 'quatre')['statut'], 'une heure plus tard, les 29 de 9 h 30 sont sortis de la fenêtre');
});

test('siri : la même phrase (casse et espaces près) dans les 2 minutes → « Déjà noté. », une seule carte ; au-delà de 2 minutes, une nouvelle', function () {
    base_de_test();
    fixer_heure('2026-10-22 10:00:00');
    trello_siri();
    $jeton = creer_jeton_siri();
    egal('Ajouté dans Perso.', appel_siri($jeton, 'Acheter du pain')['corps']);
    fixer_heure('2026-10-22 10:01:59');
    $r = appel_siri($jeton, "  acheter  du PAIN ");
    egal([200, 'Déjà noté.'], [$r['statut'], $r['corps']]);
    egal('text/plain; charset=utf-8', $r['entetes']['Content-Type']);
    egal(1, count($GLOBALS['JR_APPELS_HTTP']), 'une seule carte');
    egal(1, appels_siri_en_base());
    egal('Ajouté dans Perso.', appel_siri($jeton, 'Acheter du lait')['corps'], 'autre phrase');
    fixer_heure('2026-10-22 10:02:01');
    egal('Ajouté dans Perso.', appel_siri($jeton, 'acheter du pain')['corps'], 'plus de 2 minutes après la première');
    egal(3, count($GLOBALS['JR_APPELS_HTTP']));
});

test("siri : Trello en panne → 502 en texte brut, aucune ligne notée, et le renvoi de la phrase n'est pas pris pour un doublon", function () {
    base_de_test();
    copie_trello([]);
    $jeton = creer_jeton_siri();
    faux_http(['POST https://api.trello.com/1/cards' => ['statut' => 503, 'corps' => 'down']]);
    $r = appel_siri($jeton, 'acheter du pain');
    egal(502, $r['statut']);
    egal('Trello ne répond pas. Réessaie dans un moment.', $r['corps']);
    egal('text/plain; charset=utf-8', $r['entetes']['Content-Type']);
    egal(0, appels_siri_en_base());
    faux_http(['POST https://api.trello.com/1/cards' => ['json' => carte_trello('perso', 'À faire', 1)]]);
    egal('Ajouté dans Perso.', appel_siri($jeton, 'acheter du pain')['corps']);
    faux_http(['POST https://api.trello.com/1/cards' => new ErreurReseau('délai dépassé')]);
    egal(502, appel_siri($jeton, 'autre phrase')['statut'], 'réseau muet');
});

test("siri : le travail en base se fait sous le verrou jr_siri, rendu ensuite, et la purge retire les appels de plus de 24 h", function () {
    base_de_test();
    fixer_heure('2026-10-22 10:00:00');
    trello_siri();
    $jeton = creer_jeton_siri();
    db()->exec("INSERT INTO siri_appels (recu_le, empreinte) VALUES ('2026-10-21 09:59:59', 'a'), ('2026-10-21 10:00:01', 'b')");
    $autre = nouvelle_connexion_test();
    $vu = [];
    $client = $GLOBALS['JR_CLIENT_HTTP'];
    $GLOBALS['JR_CLIENT_HTTP'] = function (...$args) use ($client, $autre, &$vu): array {
        $vu[] = (int) $autre->query("SELECT GET_LOCK('jr_siri', 0)")->fetchColumn();
        return $client(...$args);
    };
    egal(200, appel_siri($jeton)['statut']);
    egal([0], $vu, 'verrou pris pendant la création de la carte');
    egal(1, (int) $autre->query("SELECT GET_LOCK('jr_siri', 0)")->fetchColumn(), 'verrou rendu');
    egal(['b' => 1], array_fill_keys(db()->query("SELECT empreinte FROM siri_appels WHERE empreinte IN ('a','b')")->fetchAll(PDO::FETCH_COLUMN), 1));
    egal(2, appels_siri_en_base());
});

test("siri : ni le jeton, ni son empreinte, ni le texte dicté ne finissent dans le journal ou dans la base", function () {
    base_de_test();
    copie_trello([]);
    $jeton = creer_jeton_siri();
    $texte = 'secret-dicte-xyz';
    faux_http(['POST https://api.trello.com/1/cards' => ['statut' => 500, 'corps' => 'boum']]);
    appel_siri($jeton, $texte);
    appel_siri('faux-' . $jeton, $texte);
    $journal = is_file(dossier_data() . '/erreurs.log') ? (string) file_get_contents(dossier_data() . '/erreurs.log') : '';
    foreach ([$jeton, hash('sha256', $jeton), $texte, 'Authorization'] as $interdit) {
        vrai(!str_contains($journal, $interdit), 'journal : ' . substr($interdit, 0, 4));
    }
    faux_http(['POST https://api.trello.com/1/cards' => ['json' => carte_trello('perso', 'À faire', 1)]]);
    appel_siri($jeton, $texte);
    $base = json_encode(db()->query('SELECT * FROM siri_appels')->fetchAll(PDO::FETCH_ASSOC));
    vrai(!str_contains($base, $texte) && !str_contains($base, $jeton), 'siri_appels ne garde que l\'empreinte du texte');
});
