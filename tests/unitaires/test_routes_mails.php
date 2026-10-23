<?php
declare(strict_types=1);

const MDP_MAIL_TEST = 'Zx9Marqueur "à\\risque"';

function contenu_brut_test(array $mails): array
{
    return ['uidvalidity' => 7, 'mails' => $mails];
}

// Enregistre un compte comme le fait l'app (essai réussi), avec le mot de passe repérable
function compte_enregistre_test(string $compte, ?string $identifiant = null): void
{
    $c = comptes_mail_lire()[$compte];
    $r = appeler('POST', 'comptes-mail', ['compte' => $compte, 'identifiant' => $identifiant ?? $c['identifiant'], 'mot_de_passe' => MDP_MAIL_TEST]);
    egal(200, $r['statut'], "enregistrement de $compte : " . $r['corps']);
}

function ecriture_mail_test(string $chemin, array $corps, int $n = 1): array
{
    return appeler('POST', $chemin, $corps + ['id_client' => uuid_test($n)]);
}

function section_de(array $sections, string $nom): array
{
    foreach ($sections as $s) {
        if ($s['section'] === $nom) {
            return $s;
        }
    }
    throw new EchecTest("Section absente : $nom");
}

function journal_test(): string
{
    $fichier = dossier_data() . '/erreurs.log';
    return is_file($fichier) ? (string) file_get_contents($fichier) : '';
}

test('mails : GET mails sans aucun compte rend les 4 sections vides et non_connecte, sans lecture IMAP', function () {
    base_de_test();
    faux_imap([]);
    $r = appeler('GET', 'mails');
    egal(200, $r['statut']);
    $d = corps($r);
    egal(['utc', 'qyris', 'gmail', 'icloud'], array_column($d['sections'], 'section'));
    egal(['utc', 'gmail', 'icloud', 'colin', 'contact'], array_keys($d['comptes']));
    foreach ($d['comptes'] as $etat) {
        egal(['recupere_le' => null, 'erreur' => 'non_connecte'], $etat);
    }
    egal([], appels_imap());
});

test('mails : une boîte en refus, les autres affichées ; QYRIS réunit colin@ et contact@', function () {
    base_de_test();
    fixer_heure('2026-10-23 10:00:00');
    $boites = [
        'utc' => contenu_brut_test([brut_test(1, ['non_lu' => true, 'entetes' => ['list-id' => ['<liste.x>']]])]),
        'gmail' => contenu_brut_test([
            brut_test(2, ['non_lu' => true, 'entetes' => ['to' => ['Colin <c@etu.utc.fr>'], 'received' => ['from mx.utc.fr by mx.google.com']]]),
        ]),
        'icloud' => contenu_brut_test([brut_test(6)]),
        'colin' => contenu_brut_test([brut_test(3, ['message_id' => '<pareil@x.fr>', 'non_lu' => true])]),
        'contact' => contenu_brut_test([brut_test(4, ['message_id' => '<pareil@x.fr>']), brut_test(5, ['non_lu' => true])]),
    ];
    faux_imap($boites);
    foreach (JR_MAILS_COMPTES as $compte) {
        compte_enregistre_test($compte);
    }
    faux_imap(['icloud' => 'refus'] + $boites);
    foreach (JR_MAILS_COMPTES as $compte) {
        $r = appeler('POST', 'mails/rafraichir', ['compte' => $compte]);
        egal(200, $r['statut'], $compte);
    }
    $d = corps(appeler('GET', 'mails'));
    egal('refus', $d['comptes']['icloud']['erreur']);
    egal([null, null, null, null], [$d['comptes']['utc']['erreur'], $d['comptes']['gmail']['erreur'], $d['comptes']['colin']['erreur'], $d['comptes']['contact']['erreur']]);
    egal('2026-10-23T12:00:00+02:00', $d['comptes']['gmail']['recupere_le']);
    egal(null, $d['comptes']['icloud']['recupere_le']);
    egal([1], array_column(section_de($d['sections'], 'utc')['mails'], 'uid'));
    egal([2], array_column(section_de($d['sections'], 'gmail')['mails'], 'uid'));
    egal([], section_de($d['sections'], 'icloud')['mails']);
    $qyris = section_de($d['sections'], 'qyris');
    egal(2, $qyris['non_lus']);
    $comptes_qyris = array_values(array_unique(array_column($qyris['mails'], 'compte')));
    sort($comptes_qyris);
    egal(['colin', 'contact'], $comptes_qyris);
    egal(1, count(array_filter($qyris['mails'], fn(array $m): bool => $m['message_id'] === '<pareil@x.fr>')));
    egal(['lecture utc', 'lecture gmail', 'lecture icloud', 'lecture colin', 'lecture contact'], array_slice(appels_imap(), 0, 5));
});

test('mails : POST mails/rafraichir rend la section du compte (gmail pour gmail, utc pour utc) et son état', function () {
    base_de_test();
    $boites = ['utc' => contenu_brut_test([brut_test(3)]), 'gmail' => contenu_brut_test([brut_test(1, ['non_lu' => true, 'entetes' => ['to' => ['c@etu.utc.fr']]]), brut_test(2)]), 'icloud' => contenu_brut_test([])];
    faux_imap($boites);
    compte_enregistre_test('utc');
    compte_enregistre_test('gmail');
    compte_enregistre_test('icloud');
    faux_imap(['icloud' => 'delai'] + $boites);
    $d = corps(appeler('POST', 'mails/rafraichir', ['compte' => 'gmail']));
    egal('gmail', $d['compte']);
    egal(['gmail'], array_column($d['sections'], 'section'));
    egal(['gmail'], array_keys($d['comptes']));
    egal(null, $d['comptes']['gmail']['erreur']);
    $d_utc = corps(appeler('POST', 'mails/rafraichir', ['compte' => 'utc']));
    egal(['utc'], array_column($d_utc['sections'], 'section'));
    egal([3], array_column($d_utc['sections'][0]['mails'], 'uid'));
    $d = corps(appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']));
    egal(['icloud'], array_column($d['sections'], 'section'));
    egal(['recupere_le' => null, 'erreur' => 'delai'], $d['comptes']['icloud']);
});

test('mails : compte non enregistré, rien n est lu ; compte inconnu ou absent, 400', function () {
    base_de_test();
    faux_imap([]);
    $d = corps(appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']));
    egal(['recupere_le' => null, 'erreur' => 'non_connecte'], $d['comptes']['icloud']);
    egal([], appels_imap());
    foreach ([['compte' => 'yahoo'], [], ['compte' => 7], ['compte' => ['gmail']]] as $corps) {
        $r = appeler('POST', 'mails/rafraichir', $corps);
        egal(400, $r['statut']);
        egal('compte_invalide', corps($r)['erreur']['code']);
    }
});

test('mails : derniere_erreur notée puis effacée au succès suivant', function () {
    base_de_test();
    fixer_heure('2026-10-23 10:00:00');
    faux_imap(['icloud' => contenu_brut_test([])]);
    compte_enregistre_test('icloud');
    faux_imap(['icloud' => 'injoignable']);
    appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']);
    egal('injoignable', corps(appeler('GET', 'comptes-mail'))['comptes']['icloud']['derniere_erreur']);
    egal('injoignable', corps(appeler('GET', 'mails'))['comptes']['icloud']['erreur']);
    fixer_heure('2026-10-23 10:10:00');
    faux_imap(['icloud' => contenu_brut_test([brut_test(1)])]);
    appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']);
    $c = corps(appeler('GET', 'comptes-mail'))['comptes']['icloud'];
    egal([null, '2026-10-23T10:10:00Z'], [$c['derniere_erreur'], $c['dernier_succes']]);
    egal(null, corps(appeler('GET', 'mails'))['comptes']['icloud']['erreur']);
});

test('mails : une copie de moins de 5 min n est pas relue, au-delà elle l est ; la limite IMAP est à 25 s', function () {
    base_de_test();
    fixer_heure('2026-10-23 10:00:00');
    faux_imap(['icloud' => contenu_brut_test([brut_test(1)])]);
    compte_enregistre_test('icloud');
    $avant = microtime(true);
    appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']);
    $limite = $GLOBALS['JR_APPELS_IMAP'][1]['limite'] - $avant;
    vrai($limite > 24.5 && $limite < 25.5, "limite : $limite");
    fixer_heure('2026-10-23 10:04:59');
    appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']);
    egal(['essai icloud', 'lecture icloud'], appels_imap());
    fixer_heure('2026-10-23 10:05:00');
    appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']);
    egal(['essai icloud', 'lecture icloud', 'lecture icloud'], appels_imap());
});

test('mails : GET mails n appelle jamais IMAP, même avec des copies vieilles', function () {
    base_de_test();
    fixer_heure('2026-10-23 10:00:00');
    faux_imap(['gmail' => contenu_brut_test([brut_test(1, ['non_lu' => true])])]);
    compte_enregistre_test('gmail');
    appeler('POST', 'mails/rafraichir', ['compte' => 'gmail']);
    $avant = appels_imap();
    fixer_heure('2026-10-25 10:00:00');
    $d = corps(appeler('GET', 'mails'));
    egal([1], array_column(section_de($d['sections'], 'gmail')['mails'], 'uid'));
    egal($avant, appels_imap());
    appeler('GET', 'donnees');
    egal($avant, appels_imap());
});

test('mails : aucune réponse de route ne contient le mot de passe, ni le journal après un refus', function () {
    base_de_test();
    faux_imap(['gmail' => contenu_brut_test([brut_test(1, ['non_lu' => true])]), 'icloud' => 'refus']);
    $reponses = [];
    $reponses[] = appeler('POST', 'comptes-mail', ['compte' => 'gmail', 'identifiant' => 'moi@gmail.com', 'mot_de_passe' => MDP_MAIL_TEST]);
    $reponses[] = appeler('POST', 'comptes-mail', ['compte' => 'icloud', 'identifiant' => 'moi@icloud.com', 'mot_de_passe' => MDP_MAIL_TEST]);
    faux_imap(['gmail' => contenu_brut_test([brut_test(1, ['non_lu' => true])]), 'icloud' => 'refus']);
    $reponses[] = appeler('POST', 'comptes-mail', ['compte' => 'gmail', 'identifiant' => 'moi@gmail.com', 'mot_de_passe' => MDP_MAIL_TEST]);
    $reponses[] = appeler('POST', 'comptes-mail/tester', ['compte' => 'gmail']);
    $reponses[] = appeler('POST', 'mails/rafraichir', ['compte' => 'gmail']);
    $reponses[] = appeler('GET', 'mails');
    $reponses[] = appeler('GET', 'comptes-mail');
    $reponses[] = appeler('GET', 'donnees', [], []);
    $reponses[] = ecriture_mail_test('mails/expediteur', ['adresse' => 'a@b.fr', 'choix' => 'pub']);
    $reponses[] = ecriture_mail_test('comptes-mail/supprimer', ['compte' => 'gmail'], 2);
    $reponses[] = appeler('POST', 'comptes-mail', ['compte' => 'gmail', 'identifiant' => '', 'mot_de_passe' => MDP_MAIL_TEST]);
    foreach ($reponses as $i => $r) {
        $json = json_encode(MDP_MAIL_TEST, JSON_UNESCAPED_UNICODE);
        vrai(!str_contains($r['corps'], 'Zx9Marqueur') && !str_contains($r['corps'], MDP_MAIL_TEST) && !str_contains($r['corps'], trim($json, '"')), "mot de passe dans la réponse $i : " . substr($r['corps'], 0, 80));
        vrai(!str_contains(json_encode($r['entetes']), 'Zx9Marqueur'), "mot de passe dans les en-têtes $i");
    }
    egal(400, $reponses[1]['statut']);
    vrai(!str_contains(journal_test(), 'Zx9Marqueur'), 'mot de passe dans le journal');
    vrai(str_contains(journal_test(), 'comptes-mail/icloud'), 'le refus doit être au journal');
});

test('mails : le journal ne contient ni mot de passe, ni objet, ni expéditeur après un refus à la lecture', function () {
    base_de_test();
    faux_imap(['gmail' => contenu_brut_test([brut_test(1, ['objet' => 'Objet-secret', 'de_adresse' => 'inconnu@secret.fr', 'extrait' => 'Extrait-secret'])])]);
    compte_enregistre_test('gmail');
    faux_imap(['gmail' => 'refus']);
    appeler('POST', 'mails/rafraichir', ['compte' => 'gmail']);
    $log = journal_test();
    vrai(str_contains($log, 'rafraichir/mail?gmail'), 'refus noté au journal : ' . $log);
    foreach (['Zx9Marqueur', 'Objet-secret', 'secret.fr', 'Extrait-secret', 'moi@'] as $interdit) {
        vrai(!str_contains($log, $interdit), "$interdit dans le journal");
    }
    egal(MDP_MAIL_TEST, $GLOBALS['JR_APPELS_IMAP'][0]['mot_de_passe'] ?? null);
});

test('comptes mail : POST enregistre après un essai réussi, avec le mot de passe donné', function () {
    base_de_test();
    faux_imap(['gmail' => contenu_brut_test([])]);
    $r = appeler('POST', 'comptes-mail', ['compte' => 'gmail', 'identifiant' => ' moi@gmail.com ', 'mot_de_passe' => MDP_MAIL_TEST]);
    egal(200, $r['statut']);
    $d = corps($r)['comptes']['gmail'];
    egal([true, 'moi@gmail.com', null], [$d['enregistre'], $d['identifiant'], $d['derniere_erreur']]);
    vrai($d['dernier_succes'] !== null, 'dernier succès noté');
    egal(['essai gmail'], appels_imap());
    egal(['identifiant' => 'moi@gmail.com', 'mot_de_passe' => MDP_MAIL_TEST], compte_mail_acces('gmail'));
});

test('comptes mail : POST avec un essai en échec, 400 avec le code, rien de gardé', function () {
    base_de_test();
    foreach (['refus', 'injoignable', 'delai'] as $code) {
        faux_imap(['gmail' => $code]);
        $r = appeler('POST', 'comptes-mail', ['compte' => 'gmail', 'identifiant' => 'moi@gmail.com', 'mot_de_passe' => MDP_MAIL_TEST]);
        egal(400, $r['statut'], $code);
        egal($code, corps($r)['erreur']['code']);
        egal(0, (int) db()->query('SELECT COUNT(*) FROM comptes_mail')->fetchColumn(), $code);
    }
    faux_imap(['gmail' => 'refus']);
    egal('Mot de passe refusé', corps(appeler('POST', 'comptes-mail', ['compte' => 'gmail', 'identifiant' => 'a@b.fr', 'mot_de_passe' => 'x']))['erreur']['message']);
});

test('comptes mail : un essai en échec ne remplace pas le compte déjà enregistré', function () {
    base_de_test();
    faux_imap(['icloud' => contenu_brut_test([])]);
    compte_enregistre_test('icloud');
    faux_imap(['icloud' => 'refus']);
    $r = appeler('POST', 'comptes-mail', ['compte' => 'icloud', 'identifiant' => 'autre@icloud.com', 'mot_de_passe' => 'nouveau']);
    egal(400, $r['statut']);
    egal(['identifiant' => 'colin.flicourt@icloud.com', 'mot_de_passe' => MDP_MAIL_TEST], compte_mail_acces('icloud'));
});

test('comptes mail : POST refuse un corps incomplet ou invalide (400) avant tout essai', function () {
    base_de_test();
    faux_imap([]);
    $corps_ok = ['compte' => 'gmail', 'identifiant' => 'a@b.fr', 'mot_de_passe' => 'x'];
    foreach ([
        ['compte' => 'yahoo'] + $corps_ok, ['compte' => null] + $corps_ok, ['identifiant' => ''] + $corps_ok, ['identifiant' => "a\r\nb"] + $corps_ok,
        ['identifiant' => 12] + $corps_ok, ['mot_de_passe' => ''] + $corps_ok, ['mot_de_passe' => str_repeat('a', 201)] + $corps_ok,
        ['mot_de_passe' => "a\nb"] + $corps_ok, ['mot_de_passe' => ['x']] + $corps_ok,
    ] as $i => $corps) {
        egal(400, appeler('POST', 'comptes-mail', $corps)['statut'], "cas $i");
    }
    egal([], appels_imap());
    $sans_mdp = $corps_ok;
    unset($sans_mdp['mot_de_passe']);
    egal(400, appeler('POST', 'comptes-mail', $sans_mdp)['statut']);
});

test('comptes mail : changer l identifiant efface l ancienne copie', function () {
    base_de_test();
    faux_imap(['icloud' => contenu_brut_test([brut_test(1)])]);
    compte_enregistre_test('icloud');
    appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']);
    vrai(copie_lire('mail:icloud') !== null, 'copie lue');
    compte_enregistre_test('icloud');
    vrai(copie_lire('mail:icloud') !== null, 'même identifiant : copie gardée');
    compte_enregistre_test('icloud', 'autre@icloud.com');
    egal(null, copie_lire('mail:icloud'));
});

test('comptes mail : tester note le succès ou le refus ; un compte non enregistré, 400', function () {
    base_de_test();
    fixer_heure('2026-10-23 10:00:00');
    $r = appeler('POST', 'comptes-mail/tester', ['compte' => 'gmail']);
    egal([400, 'non_connecte'], [$r['statut'], corps($r)['erreur']['code']]);
    faux_imap(['gmail' => contenu_brut_test([])]);
    compte_enregistre_test('gmail');
    fixer_heure('2026-10-23 11:00:00');
    $r = appeler('POST', 'comptes-mail/tester', ['compte' => 'gmail']);
    egal(200, $r['statut']);
    egal('2026-10-23T11:00:00Z', corps($r)['comptes']['gmail']['dernier_succes']);
    faux_imap(['gmail' => 'refus']);
    $r = appeler('POST', 'comptes-mail/tester', ['compte' => 'gmail']);
    egal([400, 'refus'], [$r['statut'], corps($r)['erreur']['code']]);
    egal('refus', comptes_mail_lire()['gmail']['derniere_erreur']);
    egal(400, appeler('POST', 'comptes-mail/tester', ['compte' => 'autre'])['statut']);
});

test('comptes mail : supprimer efface le compte et sa copie, se rejoue sans refaire, exige id_client', function () {
    base_de_test();
    faux_imap(['icloud' => contenu_brut_test([brut_test(1)])]);
    compte_enregistre_test('icloud');
    appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']);
    $r = ecriture_mail_test('comptes-mail/supprimer', ['compte' => 'icloud']);
    egal(200, $r['statut']);
    egal(false, corps($r)['comptes']['icloud']['enregistre']);
    egal(null, copie_lire('mail:icloud'));
    compte_enregistre_test('icloud');
    $rejeu = ecriture_mail_test('comptes-mail/supprimer', ['compte' => 'icloud']);
    egal(200, $rejeu['statut']);
    egal(true, comptes_mail_lire()['icloud']['enregistre'], 'le rejeu ne supprime pas une seconde fois');
    egal(400, appeler('POST', 'comptes-mail/supprimer', ['compte' => 'icloud'])['statut']);
    $r = ecriture_mail_test('comptes-mail/supprimer', ['compte' => 'yahoo'], 2);
    egal([400, 'compte_invalide'], [$r['statut'], corps($r)['erreur']['code']]);
});

test('mails : une correction pub déplace le mail dans les pubs sans relire la boîte ; auto la retire', function () {
    base_de_test();
    faux_imap(['icloud' => contenu_brut_test([brut_test(1, ['de_adresse' => 'Promo@Boutique.fr', 'non_lu' => true]), brut_test(2)])]);
    compte_enregistre_test('icloud');
    appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']);
    $avant = appels_imap();
    egal([2, 1], array_column(section_de(corps(appeler('GET', 'mails'))['sections'], 'icloud')['mails'], 'uid'));
    $r = ecriture_mail_test('mails/expediteur', ['adresse' => ' Promo@boutique.fr ', 'choix' => 'pub']);
    egal(200, $r['statut']);
    egal(['adresse' => 'promo@boutique.fr', 'choix' => 'pub'], array_intersect_key(corps($r), ['adresse' => 1, 'choix' => 1]));
    $s = section_de(corps(appeler('GET', 'mails'))['sections'], 'icloud');
    egal([2], array_column($s['mails'], 'uid'));
    egal([[1], 1, 0], [array_column($s['pubs'], 'uid'), $s['pubs_total'], $s['non_lus']]);
    egal($avant, appels_imap());
    ecriture_mail_test('mails/expediteur', ['adresse' => 'promo@boutique.fr', 'choix' => 'auto'], 2);
    egal([2, 1], array_column(section_de(corps(appeler('GET', 'mails'))['sections'], 'icloud')['mails'], 'uid'));
});

test('mails : expediteur refuse un choix invalide, une adresse vide ou mal typée (400) et exige id_client', function () {
    base_de_test();
    foreach ([['adresse' => 'a@b.fr', 'choix' => 'peut-etre'], ['adresse' => ' ', 'choix' => 'pub'], ['adresse' => 5, 'choix' => 'pub'], ['adresse' => 'a@b.fr'], ['choix' => 'pub']] as $i => $corps) {
        egal(400, ecriture_mail_test('mails/expediteur', $corps, $i + 1)['statut'], "cas $i");
    }
    egal(400, appeler('POST', 'mails/expediteur', ['adresse' => 'a@b.fr', 'choix' => 'pub'])['statut']);
    egal([], corrections_lire());
});

test('mails : texte mal encodé de bout en bout, la route répond 200 en UTF-8 valide', function () {
    base_de_test();
    $bloc = "To: Andr\xE9 <a@x.fr>\xFF\r\n\r\n";
    $reponse = "* 6 FETCH (UID 106 FLAGS () INTERNALDATE \"23-Oct-2026 10:00:00 +0000\" ENVELOPE (NIL \"=?UTF-8?B?4pyT =?x-inconnu-9?Q?caf=E9?= Caf\xE9\" ((\"Jos\xE9 \xFF\" NIL \"jos\xE9\" \"EXEMPLE.fr\")) NIL NIL NIL NIL NIL NIL \"<id-\xE9@exemple.fr>\") "
        . "BODYSTRUCTURE (\"TEXT\" \"PLAIN\" (\"CHARSET\" \"x-jeu-bizarre\") NIL NIL \"7BIT\" 10 1 NIL NIL NIL NIL) BODY[HEADER.FIELDS (TO)] {" . strlen($bloc) . "}\r\n$bloc)";
    [$brut] = imap_brut_depuis_fetch(imap_reponse_fetch($reponse));
    $brut['non_lu'] = true;
    faux_imap(['icloud' => contenu_brut_test([$brut])]);
    compte_enregistre_test('icloud');
    $r = appeler('POST', 'mails/rafraichir', ['compte' => 'icloud']);
    egal(200, $r['statut']);
    $get = appeler('GET', 'mails');
    egal(200, $get['statut']);
    vrai(mb_check_encoding($get['corps'], 'UTF-8'), 'UTF-8 valide');
    vrai(str_contains(section_de(corps($get)['sections'], 'icloud')['mails'][0]['objet'], 'Café'), 'objet décodé');
});

test('mails : mails_resume dans GET donnees, lu dans les copies', function () {
    base_de_test();
    fixer_heure('2026-10-23 10:00:00');
    faux_imap([
        'utc' => contenu_brut_test([brut_test(1, ['non_lu' => true, 'entetes' => ['list-unsubscribe' => ['<x>']]])]),
        'gmail' => contenu_brut_test([brut_test(5, ['non_lu' => true, 'entetes' => ['list-unsubscribe' => ['<x>']]])]),
        'colin' => contenu_brut_test([brut_test(3, ['non_lu' => true]), brut_test(4, ['non_lu' => true])]),
    ]);
    compte_enregistre_test('utc');
    compte_enregistre_test('gmail');
    compte_enregistre_test('colin');
    appeler('POST', 'mails/rafraichir', ['compte' => 'utc']);
    appeler('POST', 'mails/rafraichir', ['compte' => 'gmail']);
    appeler('POST', 'mails/rafraichir', ['compte' => 'colin']);
    $avant = appels_imap();
    faux_http([BATCH => ['json' => batch_trello()]]);
    $d = corps(appeler('GET', 'donnees'));
    egal(['total' => 3, 'par_section' => ['utc' => 1, 'qyris' => 2, 'gmail' => 0, 'icloud' => 0]], $d['mails_resume']);
    egal($avant, appels_imap());
    egal(3, mails_non_lus_total());
});

test('mails : GET donnees sans compte mail rend un résumé à 0', function () {
    base_de_test();
    faux_http([BATCH => ['json' => batch_trello()]]);
    egal(['total' => 0, 'par_section' => ['utc' => 0, 'qyris' => 0, 'gmail' => 0, 'icloud' => 0]], corps(appeler('GET', 'donnees'))['mails_resume']);
});

test('mails : les routes exigent la session et l origine de l app', function () {
    base_de_test();
    faux_imap([]);
    foreach (['GET mails', 'POST mails/rafraichir', 'GET comptes-mail', 'POST comptes-mail', 'POST comptes-mail/tester', 'POST comptes-mail/supprimer', 'POST mails/expediteur'] as $cle) {
        [$methode, $chemin] = explode(' ', $cle, 2);
        egal(401, traiter(requete(['methode' => $methode, 'chemin' => $chemin, 'corps_brut' => '{}']))['statut'], $cle);
        if ($methode === 'POST') {
            $r = traiter(requete(['methode' => 'POST', 'chemin' => $chemin, 'corps_brut' => '{}', 'cookies' => cookies_session(), 'entetes' => ['origin' => 'http://autre.fr', 'content-type' => 'application/json']]));
            egal(403, $r['statut'], $cle);
        }
    }
    egal([], appels_imap());
});
