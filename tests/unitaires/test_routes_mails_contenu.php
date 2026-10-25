<?php
declare(strict_types=1);

// Routes mails/ouvrir, mails/corps, mails/piece : fonctions IMAP remplacées (faux_imap_mail), jamais de réseau

function contenu_mail_prepare(string $compte = 'gmail', array $mails = []): void
{
    base_de_test();
    faux_imap(array_fill_keys(JR_MAILS_COMPTES, contenu_brut_test([])));
    compte_enregistre_test($compte);
    $mails = $mails ?: [brut_test(1, ['non_lu' => true]), brut_test(2, ['non_lu' => true]), brut_test(3)];
    copie_ecrire('mail:' . $compte, ['uidvalidity' => 7, 'mails' => array_map(fn(array $b): array => mail_pour_copie($b, $compte), $mails)], maintenant());
}

function mail_ouvert_test(array $surcharges = []): array
{
    return array_replace([
        'objet' => 'Café & co ?', 'de_nom' => 'Alice', 'de_adresse' => 'alice@exemple.fr',
        'a' => [['nom' => 'Colin', 'adresse' => 'colin@exemple.fr']], 'cc' => [], 'repondre_a' => null,
        'date' => '2026-10-23T10:00:00Z', 'entetes' => [],
        'structure' => structure_de(bs_multi('mixed', bs_texte('html', '7bit', 100), bs_pdf(1000), bs_pdf(30000000, 'Gros.pdf'))),
        'marque_lu' => true,
    ], $surcharges);
}

function get_mail(string $chemin, array $parametres, bool $session = true): array
{
    return traiter(requete(['methode' => 'GET', 'chemin' => $chemin, 'requete' => $parametres, 'cookies' => $session ? cookies_session() : []]));
}

// Joue la réponse en flux : [statut, en-têtes, octets]
function flux_joue(array $reponse): array
{
    $r = ['statut' => null, 'entetes' => null, 'octets' => ''];
    flux_executer(
        $reponse,
        function (int $statut, array $entetes) use (&$r): void {
            $r['statut'] = $statut;
            $r['entetes'] = $entetes;
        },
        function (string $octets) use (&$r): void {
            $r['octets'] .= $octets;
        }
    );
    return $r;
}

function mails_non_lus_gmail(): int
{
    return array_sum(array_column(array_filter(mails_reponse()['sections'], fn(array $s): bool => $s['section'] === 'gmail'), 'non_lus'));
}

const PARAMS_MAIL = ['compte' => 'gmail', 'uid' => '1'];

test('ouvrir : marque_lu vrai → non_lu faux pour cet UID seulement, un non lu de moins, forme exacte de la réponse', function () {
    contenu_mail_prepare();
    faux_imap_mail(['ouvrir' => mail_ouvert_test()]);
    egal(2, mails_non_lus_gmail());
    $avant = copie_lire('mail:gmail');
    $r = appeler('POST', 'mails/ouvrir', ['compte' => 'gmail', 'uid' => 1]);
    egal(200, $r['statut'], $r['corps']);
    $d = corps($r);
    egal(['ok', 'objet', 'de_nom', 'de_adresse', 'a', 'cc', 'date', 'pour_contact', 'pieces', 'repondre_url', 'repondre_repli', 'marque_lu'], array_keys($d));
    egal('Café & co ?', $d['objet']);
    egal('2026-10-23T10:00:00Z', $d['date']);
    egal([['nom' => 'Colin', 'adresse' => 'colin@exemple.fr']], $d['a']);
    egal(false, $d['pour_contact']);
    egal(true, $d['marque_lu']);
    egal([
        ['partie' => '2', 'nom' => 'Facture.pdf', 'type' => 'application/pdf', 'taille' => 750, 'trop_lourde' => false],
        ['partie' => '3', 'nom' => 'Gros.pdf', 'type' => 'application/pdf', 'taille' => 22500000, 'trop_lourde' => true],
    ], $d['pieces']);
    $s = rawurlencode('Re: Café & co ?');
    egal("googlegmail://co?to=alice%40exemple.fr&subject=$s", $d['repondre_url']);
    egal("https://mail.google.com/mail/?view=cm&to=alice%40exemple.fr&su=$s", $d['repondre_repli']);
    $apres = copie_lire('mail:gmail');
    egal([1 => false, 2 => true, 3 => false], array_column(array_map(fn($m) => ['uid' => $m['uid'], 'non_lu' => $m['non_lu']], $apres['contenu']['mails']), 'non_lu', 'uid'));
    $sans_non_lu = fn(array $c): array => array_map(fn($m) => array_diff_key($m, ['non_lu' => 1]), $c['contenu']['mails']);
    egal($sans_non_lu($avant), $sans_non_lu($apres));
    egal($avant['recupere_le']->getTimestamp(), $apres['recupere_le']->getTimestamp());
    egal(1, mails_non_lus_gmail());
    egal(['ouvrir gmail'], appels_imap());
    egal([MDP_MAIL_TEST, 7, 1], [$GLOBALS['JR_APPELS_IMAP'][0]['mot_de_passe'], $GLOBALS['JR_APPELS_IMAP'][0]['uidvalidity'], $GLOBALS['JR_APPELS_IMAP'][0]['uid']]);
    vrai($GLOBALS['JR_APPELS_IMAP'][0]['limite'] > microtime(true) + 15 && $GLOBALS['JR_APPELS_IMAP'][0]['limite'] <= microtime(true) + 26, 'limite IMAP de la route');
});

test('ouvrir : marque_lu faux → copie inchangée ; ni corps, ni structure, ni mot de passe dans la réponse', function () {
    contenu_mail_prepare();
    faux_imap_mail(['ouvrir' => mail_ouvert_test(['marque_lu' => false, 'entetes' => ['x' => ['secret']]])]);
    $avant = copie_lire('mail:gmail');
    $r = appeler('POST', 'mails/ouvrir', ['compte' => 'gmail', 'uid' => 1]);
    egal(200, $r['statut']);
    egal(false, corps($r)['marque_lu']);
    egal($avant['contenu'], copie_lire('mail:gmail')['contenu']);
    foreach (['Zx9Marqueur', 'structure', 'entetes', 'secret', 'repondre_a', 'texte'] as $interdit) {
        vrai(!str_contains($r['corps'], $interdit), "la réponse contient « $interdit »");
    }
});

test('ouvrir : Répondre va à Reply-To, l\'adresse avec + est encodée ; pour_contact vient de la copie', function () {
    contenu_mail_prepare('colin', [brut_test(1, ['non_lu' => true, 'entetes' => ['to' => ['contact@qyris.fr']]])]);
    faux_imap_mail(['ouvrir' => mail_ouvert_test(['repondre_a' => ['nom' => 'Bob', 'adresse' => 'bob+qyris@exemple.fr']])]);
    $d = corps(appeler('POST', 'mails/ouvrir', ['compte' => 'colin', 'uid' => 1]));
    egal('mailto:bob%2Bqyris%40exemple.fr?subject=' . rawurlencode('Re: Café & co ?'), $d['repondre_url']);
    egal(null, $d['repondre_repli']);
    egal(mail_pour_copie(brut_test(1, ['entetes' => ['to' => ['contact@qyris.fr']]]), 'colin')['pour_contact'], $d['pour_contact']);
});

test('ouvrir : uid absent de la copie → 404 hors_copie sans appel IMAP ; absent → 404 ; non enregistré → non_connecte', function () {
    contenu_mail_prepare();
    faux_imap_mail([]);
    $r = appeler('POST', 'mails/ouvrir', ['compte' => 'gmail', 'uid' => 99]);
    egal([404, 'hors_copie'], [$r['statut'], corps($r)['erreur']['code']]);
    egal([], appels_imap());
    faux_imap_mail(['ouvrir' => 'absent']);
    $r = appeler('POST', 'mails/ouvrir', ['compte' => 'gmail', 'uid' => 1]);
    egal([404, 'absent'], [$r['statut'], corps($r)['erreur']['code']]);
    copie_ecrire('mail:icloud', ['uidvalidity' => 7, 'mails' => [mail_pour_copie(brut_test(1), 'icloud')]], maintenant());
    faux_imap_mail([]);
    $r = appeler('POST', 'mails/ouvrir', ['compte' => 'icloud', 'uid' => 1]);
    egal([400, 'non_connecte'], [$r['statut'], corps($r)['erreur']['code']]);
    egal([], appels_imap());
});

test('ouvrir : refus, injoignable et délai → 400 avec leur code ; autre erreur de source → injoignable', function () {
    contenu_mail_prepare();
    foreach (['refus' => 'refus', 'injoignable' => 'injoignable', 'delai' => 'delai', 'bizarre' => 'injoignable'] as $source => $code) {
        faux_imap_mail(['ouvrir' => $source]);
        $r = appeler('POST', 'mails/ouvrir', ['compte' => 'gmail', 'uid' => 1]);
        egal([400, $code], [$r['statut'], corps($r)['erreur']['code']], $source);
    }
});

test('ouvrir : requêtes invalides → 400 sans appel IMAP', function () {
    contenu_mail_prepare();
    faux_imap_mail([]);
    foreach ([
        ['compte' => 'autre', 'uid' => 1], ['uid' => 1], ['compte' => 'gmail'], ['compte' => 'gmail', 'uid' => 0], ['compte' => 'gmail', 'uid' => -1],
        ['compte' => 'gmail', 'uid' => '1'], ['compte' => 'gmail', 'uid' => 1.5], ['compte' => 'gmail', 'uid' => 4294967296],
    ] as $corps) {
        $r = appeler('POST', 'mails/ouvrir', $corps);
        egal(400, $r['statut'], json_encode($corps));
    }
    egal([], appels_imap());
});

test('ouvrir : POST sans l\'origine de l\'app → refusé ; sans session → 401', function () {
    contenu_mail_prepare();
    faux_imap_mail([]);
    $base = ['methode' => 'POST', 'chemin' => 'mails/ouvrir', 'corps_brut' => '{"compte":"gmail","uid":1}', 'cookies' => cookies_session()];
    $r = traiter(requete($base + ['entetes' => ['origin' => 'https://autre.exemple', 'content-type' => 'application/json']]));
    egal([403, 'origine_refusee'], [$r['statut'], corps($r)['erreur']['code']]);
    $r = traiter(requete(['cookies' => []] + $base));
    egal(401, $r['statut']);
    egal([], appels_imap());
});

test('corps : en-têtes exacts avec et sans images=1, jamais ceux de l\'app', function () {
    contenu_mail_prepare();
    faux_imap_mail(['corps' => ['parties' => [['sous_type' => 'plain', 'texte' => 'Bonjour']], 'coupe' => false, 'images_cid' => []]]);
    $r = get_mail('mails/corps', PARAMS_MAIL);
    egal(200, $r['statut']);
    egal(entetes_corps_mail(false), $r['entetes']);
    $r = get_mail('mails/corps', PARAMS_MAIL + ['images' => '1']);
    egal(entetes_corps_mail(true), $r['entetes']);
    vrai(str_contains($r['entetes']['Content-Security-Policy'], "img-src 'self' data: https:"), 'CSP avec images');
    foreach (array_keys(array_diff_key(entetes_securite(), entetes_corps_mail(false))) as $nom) {
        vrai(!isset($r['entetes'][$nom]), "en-tête de l'app présent : $nom");
    }
    vrai(!str_contains(entetes_corps_mail(false)['Content-Security-Policy'], 'https:'), 'sans images : pas de https:');
});

test('corps : un mail HTML piégé est nettoyé, les images distantes sont signalées, images=1 les laisse', function () {
    contenu_mail_prepare();
    $html = '<html><body><p onclick="x()">Salut</p><script>alert(1)</script><img src="/api/session/deconnexion"><img src="https://pub.exemple/p.gif">'
        . '<a href="javascript:x()">piège</a><a href="https://exemple.fr/ok">ok</a><img src="cid:42"></body></html>';
    faux_imap_mail(['corps' => ['parties' => [['sous_type' => 'html', 'texte' => $html]], 'coupe' => false, 'images_cid' => [42 => 'data:image/png;base64,AAAA']]]);
    $page = get_mail('mails/corps', PARAMS_MAIL)['corps'];
    foreach (['<script', 'onclick', 'deconnexion', 'javascript:', 'pub.exemple'] as $interdit) {
        vrai(!str_contains($page, $interdit), "page contient $interdit");
    }
    vrai(str_contains($page, 'Salut') && str_contains($page, 'https://exemple.fr/ok'), 'contenu gardé');
    vrai(str_contains($page, 'data:image/png;base64,AAAA'), 'image cid insérée (clé entière)');
    vrai(str_contains($page, 'data-images-bloquees="1"'), 'images bloquées signalées');
    $page = get_mail('mails/corps', PARAMS_MAIL + ['images' => '1'])['corps'];
    vrai(str_contains($page, 'https://pub.exemple/p.gif') && !str_contains($page, 'data-images-bloquees'), 'images=1');
    egal(['corps gmail', 'corps gmail'], appels_imap());
});

test('corps : parties plain à côté du HTML en <pre> échappé ; mail texte échappé avec liens', function () {
    contenu_mail_prepare();
    faux_imap_mail(['corps' => ['parties' => [
        ['sous_type' => 'html', 'texte' => '<p>Partie HTML</p>'],
        ['sous_type' => 'plain', 'texte' => 'Suite <b>texte</b>'],
    ], 'coupe' => false, 'images_cid' => []]]);
    $page = get_mail('mails/corps', PARAMS_MAIL)['corps'];
    vrai(str_contains($page, '<p>Partie HTML</p><pre>Suite &lt;b&gt;texte&lt;/b&gt;</pre>'), 'pre à la suite');
    faux_imap_mail(['corps' => ['parties' => [['sous_type' => 'plain', 'texte' => "Salut <i>\nvoir https://exemple.fr/x"]], 'coupe' => false, 'images_cid' => []]]);
    $page = get_mail('mails/corps', PARAMS_MAIL)['corps'];
    vrai(str_contains($page, '&lt;i&gt;') && str_contains($page, 'href="https://exemple.fr/x"'), 'texte échappé, lien');
});

test('corps : mail sans aucune partie texte → 200, page vide neutre (Focus 5)', function () {
    contenu_mail_prepare();
    faux_imap_mail(['corps' => ['parties' => [], 'coupe' => false, 'images_cid' => []]]);
    $r = get_mail('mails/corps', PARAMS_MAIL);
    egal(200, $r['statut']);
    egal(entetes_corps_mail(false), $r['entetes']);
    vrai(str_starts_with($r['corps'], '<!doctype html>') && str_contains($r['corps'], '<body></body>'), 'page vide');
    vrai(!str_contains($r['corps'], 'jr-bandeau"') && !str_contains(html_entity_decode($r['corps']), "ne s'ouvre pas"), 'ni bandeau ni erreur');
});

test('corps : bandeau « Mail coupé » avec le nom de la boîte, en texte et en HTML', function () {
    contenu_mail_prepare('colin', [brut_test(1)]);
    foreach ([['plain', 'Long texte'], ['html', '<p>Long</p>']] as [$type, $texte]) {
        faux_imap_mail(['corps' => ['parties' => [['sous_type' => $type, 'texte' => $texte]], 'coupe' => true, 'images_cid' => []]]);
        $page = get_mail('mails/corps', ['compte' => 'colin', 'uid' => '1'])['corps'];
        vrai(str_contains($page, 'Mail coupé : la suite est dans le webmail OVH.'), $type);
    }
});

test('corps : erreurs → page HTML neutre avec les en-têtes du corps (404, 502, 400, 401)', function () {
    contenu_mail_prepare();
    faux_imap_mail([]);
    $cas = [
        [404, get_mail('mails/corps', ['compte' => 'gmail', 'uid' => '99'])],
        [400, get_mail('mails/corps', ['compte' => 'autre', 'uid' => '1'])],
        [400, get_mail('mails/corps', PARAMS_MAIL + ['images' => '2'])],
        [400, get_mail('mails/corps', ['compte' => 'gmail', 'uid' => '0'])],
        [400, get_mail('mails/corps', ['compte' => 'gmail', 'uid' => '-1'])],
        [400, get_mail('mails/corps', ['compte' => 'gmail', 'uid' => '1e3'])],
        [400, get_mail('mails/corps', ['compte' => 'gmail', 'uid' => '12345678901'])],
        [401, get_mail('mails/corps', PARAMS_MAIL, false)],
    ];
    foreach ($cas as $i => [$statut, $r]) {
        egal($statut, $r['statut'], "cas $i");
        egal(entetes_corps_mail(false), $r['entetes'], "cas $i");
        vrai(str_starts_with($r['corps'], '<!doctype html>') && str_contains(html_entity_decode($r['corps'], ENT_QUOTES), "Ce mail ne s'ouvre pas pour l'instant."), "cas $i");
    }
    egal([], appels_imap());
    faux_imap_mail(['corps' => 'absent']);
    egal(404, get_mail('mails/corps', PARAMS_MAIL)['statut']);
    faux_imap_mail(['corps' => 'injoignable']);
    $r = get_mail('mails/corps', PARAMS_MAIL);
    egal([502, entetes_corps_mail(false)], [$r['statut'], $r['entetes']]);
    copie_ecrire('mail:icloud', ['uidvalidity' => 7, 'mails' => [mail_pour_copie(brut_test(1), 'icloud')]], maintenant());
    egal(400, get_mail('mails/corps', ['compte' => 'icloud', 'uid' => '1'])['statut']);
});

test('pièce : en-têtes et octets passent par le flux, une seule lecture', function () {
    contenu_mail_prepare();
    faux_imap_mail(['piece' => ['piece' => ['partie' => '2', 'nom' => 'Facture été.pdf', 'type' => 'application/pdf'], 'octets' => ['%PDF-', '1.4 ', "\x00\xff"]]]);
    $r = get_mail('mails/piece', PARAMS_MAIL + ['partie' => '2']);
    vrai(isset($r['flux']), 'réponse en flux');
    egal(entetes_piece_erreur(), $r['entetes']);
    egal([], appels_imap(), 'rien avant le flux');
    $f = flux_joue($r);
    egal(200, $f['statut']);
    egal(entetes_piece('application/pdf', 'Facture été.pdf'), $f['entetes']);
    egal("%PDF-1.4 \x00\xff", $f['octets']);
    egal(['piece gmail'], appels_imap());
    $a = $GLOBALS['JR_APPELS_IMAP'][0];
    egal([MDP_MAIL_TEST, 7, 1], [$a['mot_de_passe'], $a['uidvalidity'], $a['uid']]);
    vrai($a['limite'] > microtime(true) + 100 && $a['limite'] <= microtime(true) + 111, 'limite IMAP de 110 s');
});

test('pièce : type hors liste blanche servi en octet-stream avec le nom', function () {
    contenu_mail_prepare();
    faux_imap_mail(['piece' => ['piece' => ['partie' => '1', 'nom' => 'page.html', 'type' => 'text/html'], 'octets' => ['<script>x</script>']]]);
    $f = flux_joue(get_mail('mails/piece', PARAMS_MAIL + ['partie' => '1']));
    egal('application/octet-stream', $f['entetes']['Content-Type']);
    vrai(str_starts_with($f['entetes']['Content-Disposition'], 'attachment'), 'attachment');
});

test('pièce : trop_lourde → 413, partie_inconnue et absent → 404, source en panne → 502, tout en texte court', function () {
    contenu_mail_prepare();
    foreach (['trop_lourde' => 413, 'partie_inconnue' => 404, 'absent' => 404, 'injoignable' => 502] as $code => $statut) {
        faux_imap_mail(['piece' => $code]);
        $f = flux_joue(get_mail('mails/piece', PARAMS_MAIL + ['partie' => '2']));
        egal($statut, $f['statut'], $code);
        egal(entetes_piece_erreur(), $f['entetes'], $code);
        vrai(strlen($f['octets']) > 0 && strlen($f['octets']) < 60 && !str_contains($f['octets'], '<'), $code);
    }
});

test('pièce : hors copie, non enregistré, requêtes invalides, 401 → texte court avec entetes_piece_erreur(), sans flux', function () {
    contenu_mail_prepare();
    faux_imap_mail([]);
    copie_ecrire('mail:icloud', ['uidvalidity' => 7, 'mails' => [mail_pour_copie(brut_test(1), 'icloud')]], maintenant());
    $cas = [
        [404, get_mail('mails/piece', ['compte' => 'gmail', 'uid' => '99', 'partie' => '2'])],
        [400, get_mail('mails/piece', ['compte' => 'icloud', 'uid' => '1', 'partie' => '2'])],
        [400, get_mail('mails/piece', PARAMS_MAIL)],
        [400, get_mail('mails/piece', PARAMS_MAIL + ['partie' => '1;rm'])],
        [400, get_mail('mails/piece', PARAMS_MAIL + ['partie' => '1.'])],
        [400, get_mail('mails/piece', PARAMS_MAIL + ['partie' => '1.2.3.4.5.6.7.8.9.10.11'])],
        [400, get_mail('mails/piece', PARAMS_MAIL + ['partie' => '1234'])],
        [400, get_mail('mails/piece', ['compte' => 'autre', 'uid' => '1', 'partie' => '2'])],
        [400, get_mail('mails/piece', ['compte' => 'gmail', 'uid' => '0', 'partie' => '2'])],
        [400, get_mail('mails/piece', ['compte' => 'gmail', 'uid' => '-1', 'partie' => '2'])],
        [400, get_mail('mails/piece', ['compte' => 'gmail', 'uid' => '1e3', 'partie' => '2'])],
        [401, get_mail('mails/piece', PARAMS_MAIL + ['partie' => '2'], false)],
    ];
    foreach ($cas as $i => [$statut, $r]) {
        egal($statut, $r['statut'], "cas $i");
        egal(entetes_piece_erreur(), $r['entetes'], "cas $i");
        vrai(!isset($r['flux']) && $r['corps'] !== '' && !str_contains($r['corps'], '<'), "cas $i");
    }
    egal([], appels_imap());
});

test('mails de contenu : après des erreurs, le journal ne contient ni objet, ni adresse, ni nom de pièce, ni mot de passe', function () {
    contenu_mail_prepare();
    $ouvert = mail_ouvert_test(['objet' => 'Objet secret 77']);
    faux_imap_mail(['ouvrir' => function () use ($ouvert): never {
        throw new ErreurSource('injoignable', 'IMAP : injoignable');
    }, 'corps' => 'injoignable', 'piece' => 'injoignable']);
    appeler('POST', 'mails/ouvrir', ['compte' => 'gmail', 'uid' => 1]);
    get_mail('mails/corps', PARAMS_MAIL);
    flux_joue(get_mail('mails/piece', PARAMS_MAIL + ['partie' => '2']));
    $journal = journal_test();
    vrai($journal !== '', 'des erreurs sont journalisées');
    foreach (['Objet secret', 'Objet 1', 'alice@exemple.fr', 'Facture', 'Zx9Marqueur'] as $interdit) {
        vrai(!str_contains($journal, $interdit), "journal contient « $interdit »");
    }
});
