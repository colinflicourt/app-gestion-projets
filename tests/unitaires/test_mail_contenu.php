<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/outils/aide-imap.php';

const CONTENU_ID = 'colin.test@exemple.org';
const CONTENU_MDP = 'phrase de test assez longue';
const CONTENU_TRANCHE = 1048576;

// --- Constructeurs de BODYSTRUCTURE (texte IMAP, analysé comme le fait le client) -------------------

function bs_texte(string $sous, string $encodage, int $taille, ?string $jeu = 'utf-8', string $disposition = 'NIL'): string
{
    $parametres = $jeu === null ? 'NIL' : '("CHARSET" "' . $jeu . '")';
    return '("TEXT" "' . strtoupper($sous) . '" ' . $parametres . ' NIL NIL "' . strtoupper($encodage) . '" ' . $taille . ' 10 NIL ' . $disposition . ' NIL NIL)';
}

function bs_fichier(string $type, string $sous, string $encodage, int $taille, string $parametres = 'NIL', string $id = 'NIL', string $disposition = 'NIL'): string
{
    return '("' . strtoupper($type) . '" "' . strtoupper($sous) . '" ' . $parametres . ' ' . $id . ' NIL "' . strtoupper($encodage) . '" ' . $taille . ' NIL ' . $disposition . ' NIL NIL)';
}

function bs_pdf(int $taille, string $nom = 'Facture.pdf', string $encodage = 'base64'): string
{
    return bs_fichier('application', 'pdf', $encodage, $taille, 'NIL', 'NIL', '("ATTACHMENT" ("FILENAME" "' . $nom . '"))');
}

function bs_multi(string $sous, string ...$enfants): string
{
    return '(' . implode('', $enfants) . ' "' . strtoupper($sous) . '" ("BOUNDARY" "b") NIL NIL NIL)';
}

// Un mail joint, avec son enveloppe et ses propres parties texte (que le corps ne doit jamais lire)
function bs_mail_joint(int $taille, string $disposition = 'NIL'): string
{
    $enveloppe = '("Mon, 19 Oct 2026 10:00:00 +0000" "Objet du mail joint" NIL NIL NIL NIL NIL NIL NIL "<joint@exemple.org>")';
    $corps = bs_multi('alternative', bs_texte('plain', '7bit', 50), bs_texte('html', '7bit', 80));
    return '("MESSAGE" "RFC822" NIL NIL NIL "7BIT" ' . $taille . ' ' . $enveloppe . ' ' . $corps . ' 30 NIL ' . $disposition . ' NIL NIL)';
}

function structure_de(string $bodystructure): array
{
    $p = 0;
    return imap_analyser($bodystructure, $p);
}

function noms_de(array $pieces): array
{
    return array_map(fn(array $p): string => $p['partie'] . '=' . $p['nom'], $pieces);
}

// --- Choix des parties du corps, pièces, images cid -------------------------------------------------

test('Contenu Focus 3 : mixed > (related > (alternative > plain + html) + image cid) + mail joint + PDF : le html seul, deux pièces, l\'image cid à part', function () {
    $structure = structure_de(bs_multi(
        'mixed',
        bs_multi(
            'related',
            bs_multi('alternative', bs_texte('plain', 'quoted-printable', 300), bs_texte('html', 'quoted-printable', 900, 'iso-8859-1')),
            bs_fichier('image', 'png', 'base64', 4000, '("NAME" "logo.png")', '"<Logo@Club.FR>"', '("INLINE" ("FILENAME" "logo.png"))')
        ),
        bs_mail_joint(2000),
        bs_pdf(8000)
    ));
    egal([['section' => '1.1.2', 'sous_type' => 'html', 'encodage' => 'quoted-printable', 'jeu' => 'iso-8859-1', 'taille' => 900]], mail_parties_corps($structure));
    egal(['2=mail-joint.eml', '3=Facture.pdf'], noms_de(mail_pieces($structure)));
    egal(
        [
            ['partie' => '2', 'nom' => 'mail-joint.eml', 'type' => 'message/rfc822', 'encodage' => '7bit', 'taille' => 2000, 'trop_lourde' => false],
            ['partie' => '3', 'nom' => 'Facture.pdf', 'type' => 'application/pdf', 'encodage' => 'base64', 'taille' => 6000, 'trop_lourde' => false],
        ],
        mail_pieces($structure)
    );
    egal(['logo@club.fr' => ['partie' => '1.2', 'type' => 'image/png', 'encodage' => 'base64', 'taille' => 3000]], mail_images_cid($structure));
});

test('Contenu : alternative html + plain donne le html seul, alternative plain seul donne le plain, le texte non choisi n\'est pas une pièce', function () {
    $structure = structure_de(bs_multi('alternative', bs_texte('plain', '7bit', 100), bs_texte('html', '7bit', 200)));
    egal([['section' => '2', 'sous_type' => 'html', 'encodage' => '7bit', 'jeu' => 'utf-8', 'taille' => 200]], mail_parties_corps($structure));
    egal([], mail_pieces($structure));

    $structure = structure_de(bs_multi('alternative', bs_texte('html', '7bit', 200), bs_texte('plain', '7bit', 100)));
    egal(['1'], array_column(mail_parties_corps($structure), 'section'));

    $structure = structure_de(bs_multi('alternative', bs_texte('plain', '7bit', 100)));
    egal([['section' => '1', 'sous_type' => 'plain', 'encodage' => '7bit', 'jeu' => 'utf-8', 'taille' => 100]], mail_parties_corps($structure));
});

test('Contenu : alternative dont une branche est un related contenant le html, et dont l\'autre est le plain', function () {
    $structure = structure_de(bs_multi(
        'alternative',
        bs_texte('plain', '7bit', 100),
        bs_multi('related', bs_texte('html', 'base64', 500), bs_fichier('image', 'jpeg', 'base64', 900, 'NIL', '"<a1>"'))
    ));
    egal(['2.1'], array_column(mail_parties_corps($structure), 'section'));
    egal([], mail_pieces($structure));
    egal(['a1'], array_keys(mail_images_cid($structure)));
});

test('Contenu : mixed avec deux text/plain donne les deux dans l\'ordre', function () {
    $structure = structure_de(bs_multi('mixed', bs_texte('plain', '7bit', 10), bs_texte('plain', 'base64', 20, null), bs_pdf(100)));
    $parties = mail_parties_corps($structure);
    egal(['1', '2'], array_column($parties, 'section'));
    egal(['utf-8', 'utf-8'], array_column($parties, 'jeu'));
    egal(['3=Facture.pdf'], noms_de(mail_pieces($structure)));
});

test('Contenu : une partie text/plain en attachment est une pièce, pas du corps', function () {
    $structure = structure_de(bs_multi(
        'mixed',
        bs_texte('plain', '7bit', 10),
        bs_texte('plain', '7bit', 300, 'utf-8', '("ATTACHMENT" ("FILENAME" "notes.txt"))')
    ));
    egal(['1'], array_column(mail_parties_corps($structure), 'section'));
    egal(['2=notes.txt'], noms_de(mail_pieces($structure)));
    egal('text/plain', mail_pieces($structure)[0]['type']);
});

test('Contenu : un mail d\'une seule partie (texte, ou fichier) a la section 1', function () {
    egal(['1'], array_column(mail_parties_corps(structure_de(bs_texte('html', '7bit', 100))), 'section'));
    $structure = structure_de(bs_pdf(400));
    egal([], mail_parties_corps($structure));
    egal(['1=Facture.pdf'], noms_de(mail_pieces($structure)));
});

test('Contenu Focus 5 : aucune partie texte (un PDF seul, ou une image sans cid) : corps vide, pièce listée', function () {
    $structure = structure_de(bs_multi('mixed', bs_pdf(400)));
    egal([], mail_parties_corps($structure));
    egal(['1=Facture.pdf'], noms_de(mail_pieces($structure)));
    egal([], mail_images_cid($structure));

    $structure = structure_de(bs_multi('mixed', bs_fichier('image', 'jpeg', 'base64', 1200)));
    egal([], mail_parties_corps($structure));
    egal(['1=piece-jointe-1.jpg'], noms_de(mail_pieces($structure)));
    egal([], mail_images_cid($structure));

    egal([], mail_parties_corps([]));
    egal([], mail_pieces([]));
    egal([], mail_images_cid([]));
});

test('Contenu : une image avec Content-ID hors d\'un related est une pièce ; une pièce non image avec Content-ID dans un related aussi', function () {
    $structure = structure_de(bs_multi(
        'mixed',
        bs_texte('html', '7bit', 100),
        bs_fichier('image', 'png', 'base64', 400, 'NIL', '"<x1>"'),
        bs_multi('related', bs_texte('html', '7bit', 10), bs_fichier('text', 'css', 'base64', 40, 'NIL', '"<style1>"'))
    ));
    egal(['2=piece-jointe-2.png', '3.2=piece-jointe-3.2.bin'], noms_de(mail_pieces($structure)));
    egal([], mail_images_cid($structure));
});

test('Contenu : cid numérique gardé tel quel, premier cid retenu en cas de doublon', function () {
    $structure = structure_de(bs_multi(
        'related',
        bs_texte('html', '7bit', 100),
        bs_fichier('image', 'png', 'base64', 400, 'NIL', '"<12345>"'),
        bs_fichier('image', 'gif', 'base64', 800, 'NIL', '"<12345>"')
    ));
    $cid = mail_images_cid($structure);
    egal(1, count($cid));
    egal('2', $cid[12345]['partie']);
});

// --- Tailles ----------------------------------------------------------------------------------------

test('Contenu : taille décodée estimée (base64 : 3/4, sinon la taille annoncée) et trop_lourde au-delà de 20 971 520', function () {
    $pieces = fn(string $bs): array => mail_pieces(structure_de(bs_multi('mixed', $bs)));
    egal(21, $pieces(bs_pdf(28))[0]['taille']);
    egal(28, $pieces(bs_pdf(28, 'a.pdf', '7bit'))[0]['taille']);
    egal(20971519, $pieces(bs_pdf(27962026))[0]['taille']);
    egal(false, $pieces(bs_pdf(27962026))[0]['trop_lourde']);
    egal(20971520, $pieces(bs_pdf(27962027))[0]['taille']);
    egal(false, $pieces(bs_pdf(27962027))[0]['trop_lourde']);
    egal(20971521, $pieces(bs_pdf(27962028))[0]['taille']);
    egal(true, $pieces(bs_pdf(27962028))[0]['trop_lourde']);
    egal(false, $pieces(bs_pdf(20971520, 'a.bin', 'binary'))[0]['trop_lourde']);
    egal(true, $pieces(bs_pdf(20971521, 'a.bin', 'binary'))[0]['trop_lourde']);
});

// --- Noms -------------------------------------------------------------------------------------------

function nom_de(string $disposition, string $parametres = 'NIL', string $type = 'application/pdf', string $partie = '2'): string
{
    [$t, $s] = explode('/', $type);
    return mail_nom_piece(structure_de(bs_fichier($t, $s, 'base64', 100, $parametres, 'NIL', $disposition)), $partie, $type);
}

test('Contenu noms : RFC 2231 simple (filename*=UTF-8\'\'…) et en suites encodées ou non', function () {
    egal('résumé.pdf', nom_de('("ATTACHMENT" ("FILENAME*" "UTF-8\'\'r%C3%A9sum%C3%A9.pdf"))'));
    egal('résumé.pdf', nom_de('("ATTACHMENT" ("FILENAME*0*" "UTF-8\'fr\'r%C3%A9" "FILENAME*1*" "sum%C3%A9.pdf"))'));
    egal('rapport-final.pdf', nom_de('("ATTACHMENT" ("FILENAME*0" "rapport-" "FILENAME*1" "final.pdf"))'));
    egal('rapport-été.pdf', nom_de('("ATTACHMENT" ("FILENAME*1*" "%C3%A9t%C3%A9.pdf" "FILENAME*0" "rapport-"))'));
    egal('résumé.pdf', nom_de('("ATTACHMENT" ("FILENAME*" "iso-8859-1\'\'r%E9sum%E9.pdf"))'));
    egal('résumé.pdf', nom_de('("ATTACHMENT" ("FILENAME*0*" "iso-8859-1\'\'r%E9" "FILENAME*1*" "sum%E9.pdf"))'));
});

test('Contenu noms : RFC 2047 (UTF-8 en B, ISO-8859-1 en Q), filename avant name, name quand il n\'y a pas de disposition', function () {
    egal('résumé.pdf', nom_de('("ATTACHMENT" ("FILENAME" "=?UTF-8?B?' . base64_encode('résumé.pdf') . '?="))'));
    egal('résumé.pdf', nom_de('("ATTACHMENT" ("FILENAME" "=?ISO-8859-1?Q?r=E9sum=E9.pdf?="))'));
    egal('photo.jpg', nom_de('NIL', '("NAME" "photo.jpg")'));
    egal('vrai.pdf', nom_de('("ATTACHMENT" ("FILENAME" "vrai.pdf"))', '("NAME" "autre.pdf")'));
    egal('étoile.pdf', nom_de('NIL', '("NAME*" "UTF-8\'\'%C3%A9toile.pdf")'));
    egal('nom-du-type.pdf', nom_de('("INLINE" NIL)', '("NAME" "nom-du-type.pdf")'));
});

test('Contenu noms : sans nom, piece-jointe-<partie>.<extension du type>, et mail-joint.eml pour un mail joint', function () {
    egal('piece-jointe-2.pdf', nom_de('NIL'));
    egal('piece-jointe-1.3.docx', nom_de('NIL', 'NIL', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '1.3'));
    foreach ([
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/heic' => 'heic',
        'application/msword' => 'doc', 'application/vnd.ms-excel' => 'xls', 'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'text/plain' => 'txt', 'text/csv' => 'csv', 'text/calendar' => 'ics', 'application/zip' => 'bin',
    ] as $type => $extension) {
        egal("piece-jointe-4.$extension", nom_de('NIL', 'NIL', $type, '4'), $type);
    }
    egal('mail-joint.eml', mail_nom_piece(structure_de(bs_mail_joint(500)), '2', 'message/rfc822'));
    egal('Transfert.eml', mail_nom_piece(structure_de(bs_mail_joint(500, '("ATTACHMENT" ("FILENAME" "Transfert.eml"))')), '2', 'message/rfc822'));
    egal('piece-jointe-2.pdf', nom_de('("ATTACHMENT" ("FILENAME" ""))'));
    egal('piece-jointe-2.pdf', nom_de('("ATTACHMENT" ("FILENAME" ".."))'));
});

test('Contenu Focus 4 : nom hostile (chemin, guillemet, CRLF, 300 caractères, emoji) : une seule ligne, sans / ni \\, 150 caractères au plus', function () {
    $noeud = fn(string $nom): array => ['APPLICATION', 'PDF', null, null, null, 'BASE64', '100', null, ['ATTACHMENT', ['FILENAME', $nom]], null, null];

    $nom = mail_nom_piece($noeud('../../etc/passwd'), '2', 'application/pdf');
    egal('.._.._etc_passwd', $nom);
    egal('a"b__c.pdf', mail_nom_piece($noeud("a\"b\r\nc.pdf"), '2', 'application/pdf'));
    egal('a_b__Set-Cookie: x.pdf', mail_nom_piece($noeud("a\\b\r\nSet-Cookie: x.pdf"), '2', 'application/pdf'));
    egal('dossier_fichier.pdf', mail_nom_piece($noeud("dossier\\fichier.pdf"), '2', 'application/pdf'));
    egal('a_b.pdf', mail_nom_piece($noeud("a\x00b.pdf"), '2', 'application/pdf'));
    egal('invoice_fdp.exe', mail_nom_piece($noeud("invoice\u{202E}fdp.exe"), '2', 'application/pdf'));

    $long = mail_nom_piece($noeud(str_repeat('a', 300) . '.pdf'), '2', 'application/pdf');
    egal(150, mb_strlen($long));
    vrai(str_ends_with($long, '.pdf'), 'extension gardée : ' . substr($long, -10));
    $long = mail_nom_piece($noeud(str_repeat('é', 300)), '2', 'application/pdf');
    egal(150, mb_strlen($long));
    vrai(mb_check_encoding($long, 'UTF-8'), 'UTF-8 valide');

    egal('🎉 fête 🎉.pdf', mail_nom_piece($noeud('🎉 fête 🎉.pdf'), '2', 'application/pdf'));
    egal('fete_ok.pdf', mail_nom_piece($noeud("fete\x1Fok.pdf"), '2', 'application/pdf'));
    vrai(mb_check_encoding(mail_nom_piece($noeud("abc.pdf\xFF"), '2', 'application/pdf'), 'UTF-8'), 'octets invalides remplacés');
});

// --- Décodage par morceaux --------------------------------------------------------------------------

function decoder_par_morceaux(string $encodage, string $octets, int $taille): string
{
    $decodeur = new DecodeurMorceaux($encodage);
    $sortie = '';
    foreach (str_split($octets, $taille) as $morceau) {
        $sortie .= $decodeur->ajouter($morceau);
    }
    return $sortie . $decodeur->finir();
}

test('DecodeurMorceaux : base64 en lignes de 76, morceaux de 1, 3, 7 et 1 048 576 octets, égal au décodage d\'un bloc', function () {
    $original = random_bytes(5000);
    $encode = chunk_split(base64_encode($original), 76, "\r\n");
    egal($original, imap_decoder_corps($encode, 'base64'));
    foreach ([1, 3, 7, 1048576] as $taille) {
        egal($original, decoder_par_morceaux('base64', $encode, $taille), "morceaux de $taille");
    }
    $grand = random_bytes(1900000);
    $encode = chunk_split(base64_encode($grand), 76, "\r\n");
    egal($grand, decoder_par_morceaux('base64', $encode, 1048576));
});

test('DecodeurMorceaux : base64 sans remplissage, avec « = », avec caractères parasites, ou coupé court : comme un bloc', function () {
    foreach (['QQ', 'QQ==', 'QUI', 'QUI=', 'QUJD', 'QUJDR', "QU\r\nJD RA==\r\n", 'QU*JD!RA', '', 'QUJDREVGRw==QUJD', 'A'] as $encode) {
        foreach ([1, 2, 3, 5, 100] as $taille) {
            egal(imap_decoder_corps($encode, 'base64'), $encode === '' ? '' : decoder_par_morceaux('base64', $encode, $taille), json_encode($encode) . " par $taille");
        }
    }
});

test('DecodeurMorceaux : quoted-printable, « =C3=A9 » coupé entre =C et 3, « =\\r\\n » coupé entre = et \\r, comme un bloc', function () {
    $decodeur = new DecodeurMorceaux('quoted-printable');
    egal('caf', $decodeur->ajouter('caf=C'));
    egal('é', $decodeur->ajouter('3=A9'));
    egal('', $decodeur->finir());

    $decodeur = new DecodeurMorceaux('quoted-printable');
    egal('ab', $decodeur->ajouter('ab='));
    egal('cd', $decodeur->ajouter("\r\ncd"));

    $decodeur = new DecodeurMorceaux('quoted-printable');
    egal('ab', $decodeur->ajouter("ab=\r"));
    egal('cd', $decodeur->ajouter("\ncd"));
    egal('', $decodeur->finir());

    $decodeur = new DecodeurMorceaux('quoted-printable');
    egal('x', $decodeur->ajouter('x='));
    egal(quoted_printable_decode('='), $decodeur->finir());

    $texte = "Caf=C3=A9 cr=C3=A8me =3D fin de ligne =\r\nsuite =\t \r\nencore =E2=82=AC et ==A =4";
    foreach (range(1, 45) as $taille) {
        egal(quoted_printable_decode($texte), decoder_par_morceaux('quoted-printable', $texte, $taille), "morceaux de $taille");
    }
});

test('DecodeurMorceaux : 7bit, 8bit, binary et inconnu passent tels quels', function () {
    foreach (['7bit', '8bit', 'binary', 'x-inconnu', ''] as $encodage) {
        egal("a=C3\r\nb", decoder_par_morceaux($encodage, "a=C3\r\nb", 2), $encodage);
    }
});

// --- Texte en UTF-8 ----------------------------------------------------------------------------------

test('Contenu Focus 2 : texte sans charset en Windows-1252 donne de l\'UTF-8 lisible ; coupé au milieu d\'un « é », UTF-8 valide', function () {
    egal('Café à demain', mail_texte_utf8("Caf\xE9 \xE0 demain", 'utf-8', false));
    egal('Café', mail_texte_utf8("Caf\xE9", 'iso-8859-1', false));
    egal('Café', mail_texte_utf8("Caf\xE9", 'charset-inconnu', false));
    egal('日本', mail_texte_utf8(mb_convert_encoding('日本', 'Shift_JIS', 'UTF-8'), 'shift_jis', false));
    egal('Caf', mail_texte_utf8("Caf\xC3", 'utf-8', true));
    egal('Café', mail_texte_utf8('Café', 'utf-8', true));
    egal('a€', mail_texte_utf8("a€\xE2\x82", 'UTF-8', true));
    egal('ab', mail_texte_utf8("a\x00b", 'utf-8', false));
});

// --- Sessions (faux serveur) -------------------------------------------------------------------------

const CONTENU_UID = 4812;

function contenu_login(): string
{
    return 'LOGIN "' . CONTENU_ID . '" "' . CONTENU_MDP . '"';
}

// Règles : BODYSTRUCTURE de CONTENU_UID, puis pour chaque [section => octets encodés] une règle par tranche de 1 Mo
function contenu_scenario(string $bodystructure, array $parties = []): array
{
    $regles = [[
        'motif' => '/\AUID FETCH ' . CONTENU_UID . ' \(UID BODYSTRUCTURE\)\z/',
        'reponse' => '* 7 FETCH (UID ' . CONTENU_UID . " BODYSTRUCTURE $bodystructure)\r\n{ETIQUETTE} OK Fait\r\n",
    ]];
    foreach ($parties as $section => $encode) {
        for ($debut = 0; $debut <= strlen($encode); $debut += CONTENU_TRANCHE) {
            $tranche = substr($encode, $debut, CONTENU_TRANCHE);
            $regles[] = [
                'motif' => '/\AUID FETCH ' . CONTENU_UID . ' \(UID BODY\.PEEK\[' . preg_quote((string) $section, '/') . '\]<' . $debut . '\.' . CONTENU_TRANCHE . '>\)\z/',
                'reponse' => '* 7 FETCH (UID ' . CONTENU_UID . " BODY[$section]<$debut> {" . strlen($tranche) . "}\r\n$tranche)\r\n{ETIQUETTE} OK Fait\r\n",
            ];
        }
    }
    $regles[] = ['motif' => '/\AUID FETCH \d+ \(UID BODYSTRUCTURE\)\z/', 'reponse' => "{ETIQUETTE} OK Fait\r\n"];
    return scenario_imap_standard(['regles_avant' => $regles]);
}

function contenu_base64(string $octets): string
{
    return chunk_split(base64_encode($octets), 76, "\r\n");
}

// Toute commande envoyée est permise en usage « lecture », jamais de SELECT ni de STORE
function contenu_commandes_de_lecture(array $commandes): void
{
    foreach ($commandes as $commande) {
        vrai(imap_commande_autorisee($commande, 'lecture'), 'hors usage lecture : ' . substr($commande, 0, 40));
        vrai(!preg_match('/\A(SELECT|UID STORE|STORE|CLOSE|EXPUNGE|COPY|MOVE)\b/', $commande), 'commande interdite : ' . $commande);
        if (str_contains($commande, 'BODY.PEEK[') && !str_contains($commande, 'HEADER.FIELDS')) {
            vrai((bool) preg_match('/\AUID FETCH \d+ \(UID BODY\.PEEK\[[\d.]+\]<\d+\.1048576>\)\z/', $commande), 'lecture de partie sans tranche : ' . $commande);
        }
    }
}

function corps_chez(string $adresse, int $uid = CONTENU_UID, int $uidvalidity = 424242): array
{
    $GLOBALS['JR_IMAP_ADRESSE'] = $adresse;
    return imap_lire_corps('colin', CONTENU_ID, CONTENU_MDP, microtime(true) + 25, $uid, $uidvalidity);
}

function piece_chez(string $adresse, string $partie, array &$evenements, int $uid = CONTENU_UID, int $uidvalidity = 424242): void
{
    $GLOBALS['JR_IMAP_ADRESSE'] = $adresse;
    imap_lire_piece(
        'colin',
        CONTENU_ID,
        CONTENU_MDP,
        microtime(true) + 25,
        $uid,
        $uidvalidity,
        $partie,
        function (array $piece) use (&$evenements): void {
            $evenements[] = ['avant', $piece];
        },
        function (string $octets) use (&$evenements): void {
            $evenements[] = ['ecrire', strlen($octets), md5($octets)];
        }
    );
}

function octets_ecrits(array $evenements): int
{
    return array_sum(array_map(fn(array $e): int => $e[0] === 'ecrire' ? $e[1] : 0, $evenements));
}

test('Contenu session : la liste blanche « lecture » permet UID BODYSTRUCTURE et BODY.PEEK[n]<début.1048576>', function () {
    foreach (['UID FETCH 4812 (UID BODYSTRUCTURE)', 'UID FETCH 4812 (UID BODY.PEEK[1.2]<0.1048576>)', 'UID FETCH 4812 (UID BODY.PEEK[2]<2097152.1048576>)'] as $commande) {
        vrai(imap_commande_autorisee($commande), $commande);
        vrai(!imap_commande_autorisee($commande, 'inconnu'), 'usage inconnu : ' . $commande);
    }
    vrai(!imap_commande_autorisee('UID FETCH 4812 (UID BODY[2]<0.1048576>)'), 'BODY sans PEEK');
});

test('Contenu session : une pièce de 2,5 Mo en base64 arrive en 3 lectures de 1 Mo, identique, pièce annoncée avant le premier octet', function () {
    $original = random_bytes(1900000);
    $encode = contenu_base64($original);
    vrai(strlen($encode) > 2 * CONTENU_TRANCHE && strlen($encode) < 3 * CONTENU_TRANCHE, 'taille encodée : ' . strlen($encode));
    $bs = bs_multi('mixed', bs_texte('plain', '7bit', 40), bs_pdf(strlen($encode), 'Rapport annuel.pdf'));
    avec_faux_imap(contenu_scenario($bs, ['2' => $encode]), function (string $adresse, callable $commandes) use ($original) {
        $evenements = [];
        piece_chez($adresse, '2', $evenements);
        egal('avant', $evenements[0][0]);
        egal('Rapport annuel.pdf', $evenements[0][1]['nom']);
        egal('2', $evenements[0][1]['partie']);
        $morceaux = array_filter($evenements, fn(array $e): bool => $e[0] === 'ecrire');
        egal(strlen($original), octets_ecrits($evenements));
        vrai(max(array_map(fn(array $e): int => $e[1], $morceaux)) <= CONTENU_TRANCHE, 'un morceau dépasse 1 Mo');
        $lectures = array_values(array_filter($commandes(), fn(string $c): bool => str_contains($c, 'BODY.PEEK[2]')));
        egal([
            'UID FETCH 4812 (UID BODY.PEEK[2]<0.1048576>)',
            'UID FETCH 4812 (UID BODY.PEEK[2]<1048576.1048576>)',
            'UID FETCH 4812 (UID BODY.PEEK[2]<2097152.1048576>)',
        ], $lectures);
        egal(['CAPABILITY', contenu_login(), 'EXAMINE "INBOX"', 'UID FETCH 4812 (UID BODYSTRUCTURE)'], array_slice($commandes(), 0, 4));
        egal('LOGOUT', $commandes()[count($commandes()) - 1]);
        contenu_commandes_de_lecture($commandes());
    });
});

test('Contenu session : les octets écrits de la pièce, mis bout à bout, sont ceux de l\'original', function () {
    $original = random_bytes(1500000);
    $encode = contenu_base64($original);
    $bs = bs_multi('mixed', bs_texte('plain', '7bit', 40), bs_pdf(strlen($encode)));
    avec_faux_imap(contenu_scenario($bs, ['2' => $encode]), function (string $adresse) use ($original) {
        $GLOBALS['JR_IMAP_ADRESSE'] = $adresse;
        $recu = '';
        imap_lire_piece('colin', CONTENU_ID, CONTENU_MDP, microtime(true) + 25, CONTENU_UID, 424242, '2', fn(array $p) => null, function (string $octets) use (&$recu): void {
            $recu .= $octets;
        });
        egal(md5($original), md5($recu));
    });
});

test('Contenu session : pièce dont la taille est un multiple exact de 1 Mo : une lecture vide termine, rien de perdu', function () {
    $original = str_repeat('x', 2 * CONTENU_TRANCHE);
    $bs = bs_multi('mixed', bs_texte('plain', '7bit', 40), bs_fichier('application', 'octet-stream', '8bit', strlen($original), 'NIL', 'NIL', '("ATTACHMENT" ("FILENAME" "a.bin"))'));
    avec_faux_imap(contenu_scenario($bs, ['2' => $original]), function (string $adresse, callable $commandes) use ($original) {
        $evenements = [];
        piece_chez($adresse, '2', $evenements);
        egal(strlen($original), octets_ecrits($evenements));
        egal(3, count(array_filter($commandes(), fn(string $c): bool => str_contains($c, 'BODY.PEEK[2]'))));
    });
});

test('Contenu session : UIDVALIDITY différente ou UID introuvable donne absent (corps et pièce), sans lecture de partie ni $avant', function () {
    $bs = bs_multi('mixed', bs_texte('plain', '7bit', 40), bs_pdf(100));
    avec_faux_imap(contenu_scenario($bs, ['1' => 'Bonjour']), function (string $adresse, callable $commandes) {
        $evenements = [];
        egal('absent', leve(ErreurSource::class, fn() => corps_chez($adresse, CONTENU_UID, 111))->codeErreur);
        egal(['CAPABILITY', contenu_login(), 'EXAMINE "INBOX"', 'LOGOUT'], $commandes());
    });
    avec_faux_imap(contenu_scenario($bs), function (string $adresse, callable $commandes) {
        $evenements = [];
        egal('absent', leve(ErreurSource::class, function () use ($adresse, &$evenements) {
            piece_chez($adresse, '2', $evenements, CONTENU_UID, 111);
        })->codeErreur);
        egal([], $evenements);
        egal(['CAPABILITY', contenu_login(), 'EXAMINE "INBOX"', 'LOGOUT'], $commandes());
    });
    avec_faux_imap(contenu_scenario($bs), function (string $adresse) {
        egal('absent', leve(ErreurSource::class, fn() => corps_chez($adresse, 999))->codeErreur);
    });
    avec_faux_imap(contenu_scenario($bs), function (string $adresse) {
        $evenements = [];
        egal('absent', leve(ErreurSource::class, function () use ($adresse, &$evenements) {
            piece_chez($adresse, '2', $evenements, 999);
        })->codeErreur);
        egal([], $evenements);
    });
});

test('Contenu session : partie inconnue (ou partie du corps) donne partie_inconnue sans $avant ni lecture de partie', function () {
    $bs = bs_multi('mixed', bs_texte('plain', '7bit', 40), bs_pdf(100));
    foreach (['9', '1', '2.1', ''] as $partie) {
        avec_faux_imap(contenu_scenario($bs), function (string $adresse, callable $commandes) use ($partie) {
            $evenements = [];
            egal('partie_inconnue', leve(ErreurSource::class, function () use ($adresse, $partie, &$evenements) {
                piece_chez($adresse, $partie, $evenements);
            })->codeErreur, "partie « $partie »");
            egal([], $evenements);
            egal(0, count(array_filter($commandes(), fn(string $c): bool => str_contains($c, 'BODY.PEEK'))));
            contenu_commandes_de_lecture($commandes());
        });
    }
});

test('Contenu session : pièce trop lourde (estimée) donne trop_lourde sans $avant ni lecture de partie', function () {
    $bs = bs_multi('mixed', bs_texte('plain', '7bit', 40), bs_pdf(27962028));
    avec_faux_imap(contenu_scenario($bs), function (string $adresse, callable $commandes) {
        $evenements = [];
        egal('trop_lourde', leve(ErreurSource::class, function () use ($adresse, &$evenements) {
            piece_chez($adresse, '2', $evenements);
        })->codeErreur);
        egal([], $evenements);
        egal(0, count(array_filter($commandes(), fn(string $c): bool => str_contains($c, 'BODY.PEEK'))));
        egal('LOGOUT', $commandes()[count($commandes()) - 1]);
    });
});

test('Contenu session : une pièce plus lourde que prévu s\'arrête à 20 971 520 octets décodés et lève trop_lourde', function () {
    $vrai_contenu = str_repeat('y', 21 * CONTENU_TRANCHE + 5);
    $bs = bs_multi('mixed', bs_texte('plain', '7bit', 40), bs_fichier('application', 'octet-stream', '8bit', 1000, 'NIL', 'NIL', '("ATTACHMENT" ("FILENAME" "a.bin"))'));
    avec_faux_imap(contenu_scenario($bs, ['2' => $vrai_contenu]), function (string $adresse) {
        $evenements = [];
        egal('trop_lourde', leve(ErreurSource::class, function () use ($adresse, &$evenements) {
            piece_chez($adresse, '2', $evenements);
        })->codeErreur);
        egal(JR_MAIL_PIECE_MAX, octets_ecrits($evenements));
    });
});

test('Contenu session : imap_lire_partie transmet seulement ce qui tient dans $max_decode et rend faux', function () {
    $bs = bs_multi('mixed', bs_texte('plain', '7bit', 100));
    foreach ([[40, false, 40], [100, true, 100], [101, true, 100], [0, false, 0]] as [$max, $entier, $recu_attendu]) {
        avec_faux_imap(contenu_scenario($bs, ['1' => str_repeat('0123456789', 10)]), function (string $adresse) use ($max, $entier, $recu_attendu) {
            $GLOBALS['JR_IMAP_ADRESSE'] = $adresse;
            $recu = '';
            $resultat = imap_session('colin', CONTENU_ID, CONTENU_MDP, microtime(true) + 25, function (ConnexionImap $c) use ($max, &$recu): bool {
                return imap_lire_partie($c, CONTENU_UID, '1', '7bit', $max, function (string $octets) use (&$recu): void {
                    $recu .= $octets;
                });
            });
            egal($entier, $resultat, "max $max");
            egal($recu_attendu, strlen($recu), "max $max");
        });
    }
});

test('Contenu session : imap_lire_corps lit le html (quoted-printable, ISO-8859-1) puis l\'image cid dans la même connexion, uniquement en lecture', function () {
    $html = '<p>Caf' . "\xE9" . ' =3D oui</p>';
    $png = random_bytes(3000);
    $bs = bs_multi(
        'mixed',
        bs_multi(
            'related',
            bs_multi('alternative', bs_texte('plain', '7bit', 40), bs_texte('html', 'quoted-printable', strlen($html), 'iso-8859-1')),
            bs_fichier('image', 'png', 'base64', strlen(contenu_base64($png)), 'NIL', '"<Logo@Club>"'),
            bs_fichier('image', 'svg+xml', 'base64', 200, 'NIL', '"<vectoriel>"'),
            bs_fichier('image', 'jpeg', 'base64', 7000000, 'NIL', '"<enorme>"')
        ),
        bs_pdf(9000)
    );
    $qp = str_replace(['=', "\xE9"], ['=3D', '=E9'], '<p>Caf' . "\xE9" . ' = oui</p>');
    avec_faux_imap(contenu_scenario($bs, ['1.1.2' => $qp, '1.2' => contenu_base64($png)]), function (string $adresse, callable $commandes) use ($png) {
        $corps = corps_chez($adresse);
        egal([['sous_type' => 'html', 'texte' => '<p>Café = oui</p>']], $corps['parties']);
        egal(false, $corps['coupe']);
        egal(['logo@club' => 'data:image/png;base64,' . base64_encode($png), 'vectoriel' => null, 'enorme' => null], $corps['images_cid']);
        egal([
            'CAPABILITY',
            contenu_login(),
            'EXAMINE "INBOX"',
            'UID FETCH 4812 (UID BODYSTRUCTURE)',
            'UID FETCH 4812 (UID BODY.PEEK[1.1.2]<0.1048576>)',
            'UID FETCH 4812 (UID BODY.PEEK[1.2]<0.1048576>)',
            'LOGOUT',
        ], $commandes());
        contenu_commandes_de_lecture($commandes());
    });
});

test('Contenu Focus 2 : partie sans charset en Windows-1252 : UTF-8 valide « é », via le serveur', function () {
    $octets = "Caf\xE9 \xE0 demain, l'\xE9t\xE9 \x80 5";
    $bs = bs_multi('mixed', bs_texte('plain', '8bit', strlen($octets), null));
    avec_faux_imap(contenu_scenario($bs, ['1' => $octets]), function (string $adresse) {
        $corps = corps_chez($adresse);
        egal([['sous_type' => 'plain', 'texte' => "Café à demain, l'été € 5"]], $corps['parties']);
        egal(false, $corps['coupe']);
        egal([], $corps['images_cid']);
    });
});

test('Contenu Focus 2 : 1,2 Mo de texte UTF-8 dont un « é » chevauche 1 048 576 : texte UTF-8 valide, coupe vrai, rien de plus lu', function () {
    $texte = str_repeat('a', CONTENU_TRANCHE - 1) . 'é' . str_repeat('b', 150000);
    $bs = bs_multi('alternative', bs_texte('plain', '8bit', strlen($texte)));
    avec_faux_imap(contenu_scenario($bs, ['1' => $texte]), function (string $adresse, callable $commandes) {
        $corps = corps_chez($adresse);
        egal(1, count($corps['parties']));
        $recu = $corps['parties'][0]['texte'];
        vrai(mb_check_encoding($recu, 'UTF-8'), 'UTF-8 valide');
        egal(str_repeat('a', CONTENU_TRANCHE - 1), $recu);
        egal(true, $corps['coupe']);
        contenu_commandes_de_lecture($commandes());
    });
});

test('Contenu : plusieurs parties texte partagent 1 Mo décodé : la deuxième est coupée, les suivantes ne sont pas lues', function () {
    $un = str_repeat('u', 700000);
    $deux = str_repeat('d', 700000);
    $bs = bs_multi('mixed', bs_texte('plain', '8bit', strlen($un)), bs_texte('plain', '8bit', strlen($deux)), bs_texte('plain', '8bit', 10));
    avec_faux_imap(contenu_scenario($bs, ['1' => $un, '2' => $deux, '3' => 'fin du mail']), function (string $adresse, callable $commandes) use ($un) {
        $corps = corps_chez($adresse);
        egal(2, count($corps['parties']));
        egal($un, $corps['parties'][0]['texte']);
        egal(JR_MAIL_CORPS_MAX - 700000, strlen($corps['parties'][1]['texte']));
        egal(true, $corps['coupe']);
        egal(0, count(array_filter($commandes(), fn(string $c): bool => str_contains($c, 'BODY.PEEK[3]'))));
    });
});

test('Contenu Focus 5 : mail sans partie texte, imap_lire_corps rend des parties vides sans lire de partie', function () {
    $bs = bs_multi('mixed', bs_pdf(400), bs_fichier('image', 'jpeg', 'base64', 1200));
    avec_faux_imap(contenu_scenario($bs), function (string $adresse, callable $commandes) {
        egal(['parties' => [], 'coupe' => false, 'images_cid' => []], corps_chez($adresse));
        egal(0, count(array_filter($commandes(), fn(string $c): bool => str_contains($c, 'BODY.PEEK'))));
    });
});

test('Contenu : les images cid dépassant 5 Mo au total valent null (celles qui tiennent sont gardées)', function () {
    $grosse = str_repeat('g', 3000000);
    $bs = bs_multi(
        'related',
        bs_texte('html', '7bit', 5),
        bs_fichier('image', 'png', '8bit', strlen($grosse), 'NIL', '"<i1>"'),
        bs_fichier('image', 'png', '8bit', strlen($grosse), 'NIL', '"<i2>"'),
        bs_fichier('image', 'gif', '8bit', 50, 'NIL', '"<i3>"')
    );
    avec_faux_imap(contenu_scenario($bs, ['1' => '<b/>', '2' => $grosse, '3' => $grosse, '4' => str_repeat('p', 50)]), function (string $adresse) use ($grosse) {
        $corps = corps_chez($adresse);
        egal('data:image/png;base64,' . base64_encode($grosse), $corps['images_cid']['i1']);
        egal(null, $corps['images_cid']['i2']);
        egal('data:image/gif;base64,' . base64_encode(str_repeat('p', 50)), $corps['images_cid']['i3']);
    });
});
