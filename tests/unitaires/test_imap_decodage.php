<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/outils/aide-imap.php';

// Éléments d'un FETCH enregistré, puis le brut qu'on en tire
function brut_enregistre(string $fixture): array
{
    $elements = imap_reponse_fetch(rtrim(fixture_imap($fixture), "\r\n"));
    vrai($elements !== null, "FETCH illisible dans $fixture");
    return imap_brut_depuis_fetch($elements);
}

function vrai_utf8_partout(mixed $valeur, string $chemin = 'brut'): void
{
    if (is_string($valeur)) {
        vrai(mb_check_encoding($valeur, 'UTF-8'), "$chemin n'est pas de l'UTF-8 valide");
    } elseif (is_array($valeur)) {
        foreach ($valeur as $cle => $v) {
            vrai_utf8_partout((string) $cle, "$chemin.clé");
            vrai_utf8_partout($v, "$chemin.$cle");
        }
    }
}

test('IMAP analyse : listes, NIL, chaînes échappées, littéraux {n} (même avec parenthèses et guillemets dedans)', function () {
    $p = 0;
    egal(
        ['UID', '5', 'FLAGS', ['\\Seen', '\\Flagged'], null, 'a "b" \\ c', 'hello'],
        imap_analyser("(UID 5 FLAGS (\\Seen \\Flagged) NIL \"a \\\"b\\\" \\\\ c\" {5}\r\nhello)", $p)
    );
    $p = 0;
    egal(['x', ")( \"\r\n", 'y'], imap_analyser("(x {6}\r\n)( \"\r\n y)", $p));
    $p = 0;
    egal(['BODY[HEADER.FIELDS (A B)]', 'abc'], imap_analyser("(BODY[HEADER.FIELDS (A B)] {3}\r\nabc)", $p));
});

test('IMAP analyse : une réponse tronquée ou incohérente lève ErreurProtocoleImap, jamais une autre erreur', function () {
    foreach (['(a b', "(a {99}\r\nabc)", '(a "b', ')', '(a [b)', "(a {x}\r\n)"] as $texte) {
        $p = 0;
        leve(ErreurProtocoleImap::class, function () use ($texte, &$p) {
            imap_analyser($texte, $p);
        });
    }
});

test('IMAP réponses : FETCH rangé par nom (HEADER, CORPS:n), SEARCH en nombres, le reste ignoré', function () {
    $e = imap_reponse_fetch(rtrim(fixture_imap('fetch-101-texte-simple.txt'), "\r\n"));
    egal(['UID', 'FLAGS', 'INTERNALDATE', 'X-GM-MSGID', 'ENVELOPE', 'BODYSTRUCTURE', 'HEADER'], array_keys($e));
    egal('101', $e['UID']);
    egal([], $e['FLAGS']);
    vrai(str_starts_with($e['HEADER'], "To: Colin"), "l'en-tête doit être le littéral");
    $c = imap_reponse_fetch(rtrim(fixture_imap('corps-102-1.txt'), "\r\n"));
    egal(['UID', 'CORPS:1'], array_keys($c));
    egal(['UID', 'CORPS:1.1'], array_keys(imap_reponse_fetch('* 3 FETCH (UID 103 BODY[1.1]<0> "x")')));
    egal(null, imap_reponse_fetch('* 3 EXISTS'));
    egal(null, imap_reponse_fetch('* OK [UIDVALIDITY 5] ok'));
    egal([4, 7, 9], imap_reponse_recherche('* SEARCH 4 7 9'));
    egal([], imap_reponse_recherche('* SEARCH'));
    egal(null, imap_reponse_recherche('* 3 EXISTS'));
});

test('IMAP : ensemble d\'UID compact', function () {
    egal('101:103,105:107', imap_ensemble_uid([101, 102, 103, 107, 105, 106]));
    egal('5', imap_ensemble_uid([5]));
    egal('1,3,5', imap_ensemble_uid([5, 3, 1, 3]));
});

test('ENVELOPE : nom, adresse en minuscules, objet RFC 2047, Message-ID, date en UTC', function () {
    [$brut, $partie] = brut_enregistre('fetch-101-texte-simple.txt');
    egal([
        'uid' => 101,
        'message_id' => '<msg-101@exemple.fr>',
        'date' => '2026-10-23T12:32:05Z',
        'de_nom' => 'Camille Martin',
        'de_adresse' => 'camille.martin@exemple.fr',
        'objet' => "Réunion de l'équipe",
        'extrait' => '',
        'non_lu' => true,
        'entetes' => [
            'to' => ['Colin <colin.test@exemple.org>'],
            'list-unsubscribe' => ['<mailto:stop@exemple.fr>, <https://exemple.fr/stop>'],
            'received' => ['from relais.etu.utc.fr (relais.etu.utc.fr [192.0.2.10]) by mx.exemple.org with ESMTPS id abc123'],
        ],
        'gm_msgid' => '1778877365540085600',
    ], $brut);
    egal(['section' => '1', 'sous_type' => 'plain', 'encodage' => 'quoted-printable', 'jeu' => 'utf-8'], $partie);
});

test('ENVELOPE : expéditeur sans nom, objet encodé en base64, drapeau \\Seen, en-têtes de pub', function () {
    [$brut, $partie] = brut_enregistre('fetch-102-alternative.txt');
    egal('', $brut['de_nom']);
    egal('boutique@newsletter.exemple.com', $brut['de_adresse']);
    egal('Promo d’automne : -20 %', $brut['objet']);
    egal(false, $brut['non_lu']);
    egal('2026-10-22T09:05:00Z', $brut['date']);
    egal(null, $brut['gm_msgid']);
    egal(['delivered-to', 'list-id', 'precedence', 'list-unsubscribe'], array_keys($brut['entetes']));
    egal(['bulk'], $brut['entetes']['precedence']);
    egal(['section' => '1', 'sous_type' => 'plain', 'encodage' => 'base64', 'jeu' => 'UTF-8'], $partie);
});

test('ENVELOPE : objet en littéral, nom accentué, message sans drapeau \\Seen', function () {
    [$brut] = brut_enregistre('fetch-103-mixte-piece-jointe.txt');
    egal('Facture de septembre', $brut['objet']);
    egal('Facturation Atelier Éclair', $brut['de_nom']);
    egal('factures@atelier-eclair.exemple', $brut['de_adresse']);
    egal(true, $brut['non_lu']);
    egal('2026-10-21T16:40:10Z', $brut['date']);
    egal(['to' => ['colin.test@exemple.org'], 'cc' => ['compta@exemple.org']], $brut['entetes']);
});

test('ENVELOPE : tout à NIL (objet, expéditeur, Message-ID) donne des textes vides, la date vient de INTERNALDATE', function () {
    [$brut, $partie] = brut_enregistre('fetch-105-vide.txt');
    egal(['uid' => 105, 'message_id' => '', 'date' => '2026-10-03T08:00:00Z', 'de_nom' => '', 'de_adresse' => '', 'objet' => '',
        'extrait' => '', 'non_lu' => false, 'entetes' => [], 'gm_msgid' => null], $brut);
    egal(null, $partie);
});

test('ENVELOPE : sans INTERNALDATE, la date de l\'en-tête Date (avec son fuseau) sert', function () {
    egal('2026-10-23T12:31:58Z', imap_date_iso(null, 'Fri, 23 Oct 2026 14:31:58 +0200'));
    egal(null, imap_date_iso(null, 'n\'importe quoi'));
    egal(null, imap_date_iso(null, null));
});

test('BODYSTRUCTURE : partie simple, multipart/alternative, mixed avec pièce jointe (section 1.1), HTML seul, aucune partie texte', function () {
    $partie = fn(string $f): ?array => brut_enregistre($f)[1];
    egal('1', $partie('fetch-101-texte-simple.txt')['section']);
    egal('1', $partie('fetch-102-alternative.txt')['section']);
    egal(['section' => '1.1', 'sous_type' => 'plain', 'encodage' => 'quoted-printable', 'jeu' => 'iso-8859-1'], $partie('fetch-103-mixte-piece-jointe.txt'));
    egal(['section' => '1', 'sous_type' => 'html', 'encodage' => '7bit', 'jeu' => 'utf-8'], $partie('fetch-104-html-seul.txt'));
    egal(null, $partie('fetch-105-vide.txt'));
});

test('BODYSTRUCTURE : un text/plain en pièce jointe est ignoré, le text/html du corps est préféré à rien', function () {
    $p = 0;
    $structure = imap_analyser('(("TEXT" "PLAIN" ("NAME" "notes.txt") NIL NIL "7BIT" 10 1 NIL ("ATTACHMENT" ("FILENAME" "notes.txt")) NIL NIL)("TEXT" "HTML" ("CHARSET" "utf-8") NIL NIL "7BIT" 10 1 NIL NIL NIL NIL) "MIXED" NIL NIL NIL)', $p);
    egal('2', imap_partie_extrait($structure)['section']);
    egal('html', imap_partie_extrait($structure)['sous_type']);
});

test('RFC 2047 : B, Q, mots collés, mots séparés, texte autour', function () {
    egal('Éléphant', imap_decoder_entete('=?UTF-8?B?w4lsw6lwaGFudA==?='));
    egal('café au lait', imap_decoder_entete('=?utf-8?Q?caf=C3=A9_au_lait?='));
    egal('abcdef', imap_decoder_entete('=?UTF-8?Q?abc?==?UTF-8?Q?def?='));
    egal('Bonjour le monde', imap_decoder_entete('=?UTF-8?Q?Bonjour?= =?UTF-8?Q?_le_monde?='));
    egal('Re: café du matin', imap_decoder_entete('Re: =?UTF-8?Q?caf=C3=A9?= du matin'));
    egal('Une ligne dépliée', imap_decoder_entete("Une ligne\r\n =?UTF-8?Q?d=C3=A9pli=C3=A9e?="));
});

test('RFC 2047 : un caractère coupé entre deux mots est rétabli, jeux Latin-1 et Windows-1252, jeu inconnu', function () {
    egal('é', imap_decoder_entete('=?UTF-8?B?ww==?= =?UTF-8?B?qQ==?='));
    egal('café', imap_decoder_entete('=?ISO-8859-1?Q?caf=E9?='));
    egal('€ 5', imap_decoder_entete('=?windows-1252?Q?=80_5?='));
    egal('café', imap_decoder_entete('=?x-inconnu-9?Q?caf=E9?='));
    egal('café', imap_decoder_entete('=?UTF-8*fr?Q?caf=C3=A9?='));
});

test('RFC 2047 : un mot cassé reste tel quel, sans erreur', function () {
    egal('=?UTF-8?B?4pyT', imap_decoder_entete('=?UTF-8?B?4pyT'));
    egal('=?UTF-8?X?abc?=', imap_decoder_entete('=?UTF-8?X?abc?='));
    egal('avant =?UTF-8?B?!!!?= après', imap_decoder_entete('avant =?UTF-8?B?!!!?= après'));
    egal('', imap_decoder_entete(''));
});

test('En-têtes bruts : octets Latin-1 non déclarés convertis, UTF-8 brut conservé', function () {
    egal('Café du coin', imap_decoder_entete("Caf\xE9 du coin"));
    egal('Café du coin', imap_decoder_entete('Café du coin'));
    egal('', imap_decoder_entete("\x00\x01"));
});

test('Jeux de caractères vers UTF-8 : Latin-1, Windows-1252, UTF-8 déclaré mais Latin-1 réel, jeu inconnu, jeux asiatiques et cyrillique', function () {
    egal('café', imap_vers_utf8("caf\xE9", 'iso-8859-1'));
    egal('€', imap_vers_utf8("\x80", 'windows-1252'));
    egal('café', imap_vers_utf8("caf\xE9", 'utf-8'));
    egal('café', imap_vers_utf8("caf\xC3\xA9", 'utf-8'));
    egal('café', imap_vers_utf8("caf\xE9", 'x-jeu-inconnu'));
    egal('あ', imap_vers_utf8("\x82\xA0", 'shift_jis'));
    egal('привет', imap_vers_utf8("\xD0\xD2\xC9\xD7\xC5\xD4", 'koi8-r'));
    egal('', imap_vers_utf8('', 'iso-8859-1'));
    foreach (['utf-8', 'iso-8859-1', 'x-inconnu', 'gb2312', 'iso-2022-jp', ''] as $jeu) {
        vrai(mb_check_encoding(imap_vers_utf8("\xFF\xFE\x80\xC3 octets\x00 absurdes", $jeu), 'UTF-8'), "UTF-8 invalide avec le jeu « $jeu »");
    }
});

test('Corps : quoted-printable, base64 (même coupé en route), 7bit inchangé', function () {
    egal("café au lait", imap_decoder_corps("caf=C3=A9 =\r\nau lait", 'quoted-printable'));
    egal('Bonjour', imap_decoder_corps(base64_encode('Bonjour'), 'base64'));
    egal('Bonjo', imap_decoder_corps(substr(base64_encode('Bonjour'), 0, 7), 'base64'));
    egal('Bonjour', imap_decoder_corps(chunk_split(base64_encode('Bonjour'), 4, "\r\n"), 'base64'));
    egal('a=b', imap_decoder_corps('a=b', '7bit'));
});

test('Extrait : une ligne, espaces simples, début et fin nettoyés', function () {
    egal('Bonjour Colin , à demain', imap_extrait("  Bonjour   \r\n\r\n  Colin \t ,\u{00A0}\u{200B} à demain\n", '7bit', 'utf-8', 'plain'));
});

test('Extrait enregistré : quoted-printable UTF-8, base64, Latin-1 avec saut doux, HTML seul sans balises ni style ni script', function () {
    $corps = function (string $fixture, string $cle): string {
        $e = imap_reponse_fetch(rtrim(fixture_imap($fixture), "\r\n"));
        return $e[$cle];
    };
    $partie = fn(string $f): array => brut_enregistre($f)[1];
    $extrait = fn(string $f, string $c, string $fixture_corps): string => imap_extrait(
        $corps($fixture_corps, $c), $partie($f)['encodage'], $partie($f)['jeu'], $partie($f)['sous_type']
    );
    egal("Bonjour Colin, On se retrouve demain à 9 h pour la réunion de l'équipe. Pense à apporter tes notes. -- Camille",
        $extrait('fetch-101-texte-simple.txt', 'CORPS:1', 'corps-101-1.txt'));
    egal('Profitez de -20 % sur toute la boutique jusqu’à dimanche. Désabonnement possible en bas de page.',
        $extrait('fetch-102-alternative.txt', 'CORPS:1', 'corps-102-1.txt'));
    egal('Voilà la facture de septembre, merci de votre confiance.',
        $extrait('fetch-103-mixte-piece-jointe.txt', 'CORPS:1.1', 'corps-103-1.1.txt'));
    egal('Bonjour Colin, Votre colis est en route & arrivera demain.',
        $extrait('fetch-104-html-seul.txt', 'CORPS:1', 'corps-104-1.txt'));
});

test('Extrait : coupé à 200 caractères sans casser un caractère (accents, emoji), jamais plus', function () {
    foreach (['é', '😀', 'ab ', '日'] as $motif) {
        $extrait = imap_extrait(str_repeat($motif, 400), '8bit', 'utf-8', 'plain');
        vrai(mb_strlen($extrait) <= 200, "plus de 200 caractères avec « $motif »");
        vrai(mb_check_encoding($extrait, 'UTF-8'), "UTF-8 cassé avec « $motif »");
    }
    egal(200, mb_strlen(imap_extrait(str_repeat('é', 400), '8bit', 'utf-8', 'plain')));
    egal(200, mb_strlen(imap_extrait(str_repeat('😀', 400), '8bit', 'utf-8', 'plain')));
});

test('Extrait : une coupure à 2 000 octets au milieu d\'un caractère ou d\'une balise ne casse rien', function () {
    egal('Voil', imap_extrait(substr('Voilà', 0, 5), '8bit', 'utf-8', 'plain'));
    egal('Voilà', imap_extrait('Voilà', '8bit', 'utf-8', 'plain'));
    egal('日本', imap_extrait(substr('日本語', 0, 8), '8bit', 'utf-8', 'plain'));
    egal('ab', imap_extrait(substr(base64_encode('abé'), 0, 5), 'base64', 'utf-8', 'plain'));
    egal('Salut', imap_extrait('<p>Salut <b', '8bit', 'utf-8', 'html'));
    egal('', imap_extrait('<style>p{color:red}', '8bit', 'utf-8', 'html'));
    egal('é<', imap_extrait('&eacute;&lt;', '8bit', 'utf-8', 'html'));
});

test('En-têtes demandés : dépliés, noms en minuscules, autres en-têtes ignorés', function () {
    $bloc = "To: a@x.fr\r\nSubject: ignoré\r\nReceived: from a\r\n\tby b\r\nReceived: from c\r\nX-Autre: non\r\nCC: =?UTF-8?Q?Andr=C3=A9?= <a@x.fr>\r\n\r\n";
    egal(['to' => ['a@x.fr'], 'received' => ['from a by b', 'from c'], 'cc' => ['André <a@x.fr>']], imap_entetes_depuis_bloc($bloc));
    egal([], imap_entetes_depuis_bloc(''));
});

test('Texte mal encodé de bout en bout : le brut reste en UTF-8 valide et json_encode réussit', function () {
    $bloc = "To: Andr\xE9 <a@x.fr>\xFF\r\n\r\n";
    $reponse = "* 6 FETCH (UID 106 FLAGS () INTERNALDATE \"23-Oct-2026 10:00:00 +0000\" ENVELOPE (NIL \"=?UTF-8?B?4pyT =?x-inconnu-9?Q?caf=E9?= Caf\xE9\" ((\"Jos\xE9 \xFF\" NIL \"jos\xE9\" \"EXEMPLE.fr\")) NIL NIL NIL NIL NIL NIL \"<id-\xE9@exemple.fr>\") "
        . "BODYSTRUCTURE (\"TEXT\" \"PLAIN\" (\"CHARSET\" \"x-jeu-bizarre\") NIL NIL \"7BIT\" 10 1 NIL NIL NIL NIL) BODY[HEADER.FIELDS (TO)] {" . strlen($bloc) . "}\r\n$bloc)";
    $e = imap_reponse_fetch($reponse);
    [$brut, $partie] = imap_brut_depuis_fetch($e);
    vrai_utf8_partout($brut);
    vrai(json_encode($brut) !== false, 'json_encode doit réussir : ' . json_last_error_msg());
    egal('josé@exemple.fr', $brut['de_adresse']);
    vrai(str_contains($brut['objet'], 'Café'), 'objet : ' . $brut['objet']);
    vrai(str_contains($brut['entetes']['to'][0], 'André'), 'to : ' . $brut['entetes']['to'][0]);
    $extrait = imap_extrait("caf\xE9 \xFF\xFE", '7bit', $partie['jeu'], $partie['sous_type']);
    vrai(mb_check_encoding($extrait, 'UTF-8') && str_starts_with($extrait, 'café'), 'extrait : ' . $extrait);
});

test('Texte mal encodé : toutes les réponses enregistrées donnent un brut valide', function () {
    foreach (glob(dirname(__DIR__) . '/fixtures/imap/fetch-*.txt') as $fichier) {
        [$brut] = brut_enregistre(basename($fichier));
        vrai_utf8_partout($brut, basename($fichier));
        vrai(json_encode($brut) !== false, "json_encode échoue pour " . basename($fichier));
    }
});

test('Un FETCH sans UID ne donne pas de mail', function () {
    egal(null, imap_brut_depuis_fetch(imap_reponse_fetch('* 9 FETCH (FLAGS (\\Seen))')));
});
