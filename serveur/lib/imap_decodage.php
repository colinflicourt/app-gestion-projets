<?php
// Décodage des réponses IMAP : fonctions pures (aucun réseau). Le client est dans imap.php.
// Tout texte rendu est en UTF-8 valide, quoi que le serveur ou l'expéditeur ait envoyé.

declare(strict_types=1);

require_once __DIR__ . '/http.php';

const JR_IMAP_EXTRAIT_MAX = 200;
const JR_IMAP_ENTETES_UTILES = ['to', 'cc', 'delivered-to', 'x-forwarded-to', 'received', 'list-unsubscribe', 'list-id', 'precedence'];
const JR_IMAP_LITTERAL_MAX = 5000000;

// Réponse du serveur illisible ou incohérente (le client la transforme en ErreurSource « injoignable »)
final class ErreurProtocoleImap extends RuntimeException {}

// Analyse un élément IMAP à partir de $p : liste → tableau, NIL → null, chaîne, littéral ou atome → texte
function imap_analyser(string $s, int &$p): mixed
{
    $n = strlen($s);
    while ($p < $n && $s[$p] === ' ') {
        $p++;
    }
    if ($p >= $n) {
        throw new ErreurProtocoleImap('Réponse IMAP tronquée');
    }
    $c = $s[$p];
    if ($c === '(') {
        $p++;
        $liste = [];
        while (true) {
            while ($p < $n && $s[$p] === ' ') {
                $p++;
            }
            if ($p >= $n) {
                throw new ErreurProtocoleImap('Réponse IMAP tronquée');
            }
            if ($s[$p] === ')') {
                $p++;
                return $liste;
            }
            $liste[] = imap_analyser($s, $p);
        }
    }
    if ($c === '"') {
        $p++;
        $texte = '';
        while ($p < $n && $s[$p] !== '"') {
            if ($s[$p] === '\\' && $p + 1 < $n) {
                $p++;
            }
            $texte .= $s[$p++];
        }
        if ($p >= $n) {
            throw new ErreurProtocoleImap('Chaîne IMAP non terminée');
        }
        $p++;
        return $texte;
    }
    if ($c === '{') {
        if (!preg_match('/\G\{(\d+)\}\r\n/', $s, $m, 0, $p) || (int) $m[1] > JR_IMAP_LITTERAL_MAX) {
            throw new ErreurProtocoleImap('Littéral IMAP illisible');
        }
        $debut = $p + strlen($m[0]);
        $taille = (int) $m[1];
        if ($debut + $taille > $n) {
            throw new ErreurProtocoleImap('Littéral IMAP tronqué');
        }
        $p = $debut + $taille;
        return substr($s, $debut, $taille);
    }
    $debut = $p;
    while ($p < $n && !in_array($s[$p], [' ', '(', ')', "\r", "\n"], true)) {
        if ($s[$p] === '[') {
            $fin = strpos($s, ']', $p);
            if ($fin === false) {
                throw new ErreurProtocoleImap('Crochet IMAP non fermé');
            }
            $p = $fin + 1;
            continue;
        }
        $p++;
    }
    if ($p === $debut) {
        throw new ErreurProtocoleImap('Élément IMAP inattendu');
    }
    $atome = substr($s, $debut, $p - $debut);
    return strcasecmp($atome, 'NIL') === 0 ? null : $atome;
}

// « * 12 FETCH (…) » → éléments par nom en majuscules ; l'en-tête demandé devient « HEADER »,
// une partie « BODY[1]<0> » devient « CORPS:1 ». Null si ce n'est pas un FETCH.
function imap_reponse_fetch(string $texte): ?array
{
    if (!preg_match('/\A\* (\d+) FETCH /i', $texte, $m)) {
        return null;
    }
    $p = strlen($m[0]);
    $liste = imap_analyser($texte, $p);
    if (!is_array($liste)) {
        return null;
    }
    $elements = [];
    for ($i = 0; $i + 1 < count($liste); $i += 2) {
        if (!is_string($liste[$i])) {
            continue;
        }
        $cle = strtoupper($liste[$i]);
        if (str_starts_with($cle, 'BODY[HEADER.FIELDS')) {
            $cle = 'HEADER';
        } elseif (preg_match('/\ABODY\[([\d.]+)\](?:<\d+>)?\z/', $cle, $mm)) {
            $cle = 'CORPS:' . $mm[1];
        }
        $elements[$cle] = $liste[$i + 1];
    }
    return $elements;
}

// Numéros d'une réponse « * SEARCH 4 7 9 »
function imap_reponse_recherche(string $texte): ?array
{
    if (!preg_match('/\A\* SEARCH((?: \d+)*)\s*\z/i', $texte, $m)) {
        return null;
    }
    return array_map('intval', preg_split('/ /', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY));
}

// Ensemble d'UID compact : [3,4,5,9] → « 3:5,9 »
function imap_ensemble_uid(array $uids): string
{
    $uids = array_values(array_unique(array_map('intval', $uids)));
    sort($uids);
    $morceaux = [];
    $i = 0;
    while ($i < count($uids)) {
        $j = $i;
        while ($j + 1 < count($uids) && $uids[$j + 1] === $uids[$j] + 1) {
            $j++;
        }
        $morceaux[] = $j > $i ? "{$uids[$i]}:{$uids[$j]}" : (string) $uids[$i];
        $i = $j + 1;
    }
    return implode(',', $morceaux);
}

// --- Texte vers UTF-8 -------------------------------------------------------------------------

function imap_utf8_sur(string $texte): string
{
    if (!mb_check_encoding($texte, 'UTF-8')) {
        return mb_scrub($texte, 'UTF-8');
    }
    return $texte;
}

function imap_nettoyer(string $texte): string
{
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $texte) ?? '';
}

// Octets d'un jeu de caractères donné vers UTF-8. Jeu inconnu ou octets invalides : UTF-8 s'il l'est,
// sinon Windows-1252 (le Latin-1 non déclaré des vieux mails), jamais d'erreur.
function imap_vers_utf8(string $octets, string $jeu = 'utf-8'): string
{
    $jeu = strtolower(trim(explode('*', $jeu, 2)[0], " \t\"'"));
    $alias = ['iso-8859-1' => 'Windows-1252', 'iso8859-1' => 'Windows-1252', 'latin1' => 'Windows-1252', 'latin-1' => 'Windows-1252',
        'l1' => 'Windows-1252', 'cp1252' => 'Windows-1252', 'windows-1252' => 'Windows-1252', 'x-cp1252' => 'Windows-1252'];
    if ($octets === '') {
        return '';
    }
    if (in_array($jeu, ['', 'utf-8', 'utf8', 'us-ascii', 'ascii', 'ansi_x3.4-1968'], true)) {
        return mb_check_encoding($octets, 'UTF-8') ? $octets : imap_utf8_sur(mb_convert_encoding($octets, 'UTF-8', 'Windows-1252'));
    }
    $source = $alias[$jeu] ?? $jeu;
    try {
        $converti = mb_convert_encoding($octets, 'UTF-8', $source);
    } catch (ValueError) {
        $converti = @iconv($jeu, 'UTF-8//IGNORE', $octets);
        if (!is_string($converti)) {
            return mb_check_encoding($octets, 'UTF-8') ? $octets : imap_utf8_sur(mb_convert_encoding($octets, 'UTF-8', 'Windows-1252'));
        }
    }
    return imap_utf8_sur($converti);
}

// --- En-têtes (RFC 2047) ----------------------------------------------------------------------

function imap_octets_mot_encode(string $codage, string $texte): string
{
    if (strtoupper($codage) === 'B') {
        $propre = preg_replace('/[^A-Za-z0-9+\/]/', '', $texte) ?? '';
        return (string) base64_decode(substr($propre, 0, strlen($propre) - (strlen($propre) % 4 === 1 ? 1 : 0)));
    }
    return quoted_printable_decode(str_replace('_', ' ', $texte));
}

// Valeur d'en-tête décodée : lignes dépliées, mots =?jeu?B|Q?…?= décodés (mots collés ou séparés d'espaces
// recollés, caractère coupé entre deux mots rétabli), octets bruts convertis. Un mot cassé reste tel quel.
function imap_decoder_entete(string $valeur): string
{
    $valeur = preg_replace('/\r?\n[ \t]+/', ' ', $valeur) ?? $valeur;
    $valeur = str_replace(["\r", "\n"], ' ', $valeur);
    $parties = preg_split('/(=\?[^?\s]{1,60}\?[BbQq]\?[^?\s]*\?=)/', $valeur, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$valeur];
    $sortie = '';
    $groupe = null;
    $vider = function () use (&$groupe, &$sortie): void {
        if ($groupe !== null) {
            $sortie .= imap_vers_utf8($groupe['octets'], $groupe['jeu']);
            $groupe = null;
        }
    };
    foreach ($parties as $i => $partie) {
        if ($i % 2 === 1) {
            preg_match('/\A=\?([^?]+)\?([BbQq])\?([^?]*)\?=\z/', $partie, $m);
            $octets = imap_octets_mot_encode($m[2], $m[3]);
            if ($octets === '' && $m[3] !== '') {
                $vider();
                $sortie .= $partie;
                continue;
            }
            $jeu = strtolower(explode('*', $m[1], 2)[0]);
            if ($groupe !== null && $groupe['jeu'] !== $jeu) {
                $vider();
            }
            $groupe ??= ['jeu' => $jeu, 'octets' => ''];
            $groupe['octets'] .= $octets;
            continue;
        }
        if ($groupe !== null && trim($partie) === '' && $i + 1 < count($parties)) {
            continue;
        }
        $vider();
        $sortie .= imap_vers_utf8($partie, 'utf-8');
    }
    $vider();
    return trim(preg_replace('/\s+/u', ' ', imap_nettoyer(imap_utf8_sur($sortie))) ?? '');
}

// Bloc « Nom: valeur » (BODY[HEADER.FIELDS]) → nom en minuscules => valeurs décodées, noms utiles seulement
function imap_entetes_depuis_bloc(string $bloc): array
{
    $bloc = preg_replace('/\r?\n[ \t]+/', ' ', $bloc) ?? $bloc;
    $entetes = [];
    foreach (preg_split('/\r?\n/', $bloc) ?: [] as $ligne) {
        if (!preg_match('/\A([A-Za-z0-9-]+):[ \t]*(.*)\z/s', $ligne, $m)) {
            continue;
        }
        $nom = strtolower($m[1]);
        if (!in_array($nom, JR_IMAP_ENTETES_UTILES, true) || count($entetes[$nom] ?? []) >= 30) {
            continue;
        }
        $entetes[$nom][] = mb_substr(imap_decoder_entete($m[2]), 0, 2000);
    }
    return $entetes;
}

// --- Dates -------------------------------------------------------------------------------------

function imap_iso_utc(DateTimeInterface $moment): string
{
    return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
}

// INTERNALDATE « 23-Oct-2026 14:32:00 +0200 », sinon date de l'ENVELOPE (RFC 2822) → ISO 8601 UTC
function imap_date_iso(?string $interne, ?string $enveloppe): ?string
{
    if ($interne !== null) {
        $moment = DateTimeImmutable::createFromFormat('j-M-Y H:i:s O', trim($interne));
        if ($moment !== false) {
            return imap_iso_utc($moment);
        }
    }
    if ($enveloppe !== null && trim($enveloppe) !== '') {
        try {
            return imap_iso_utc(new DateTimeImmutable(trim($enveloppe)));
        } catch (Exception) {
        }
    }
    return null;
}

// --- ENVELOPE et BODYSTRUCTURE -----------------------------------------------------------------

// Premier expéditeur d'une ENVELOPE : [nom décodé, adresse en minuscules]
function imap_expediteur(mixed $adresses): array
{
    if (!is_array($adresses) || !isset($adresses[0]) || !is_array($adresses[0])) {
        return ['', ''];
    }
    [$nom, , $boite, $hote] = array_pad($adresses[0], 4, null);
    $adresse = is_string($boite) ? $boite . (is_string($hote) && $hote !== '' ? "@$hote" : '') : '';
    return [is_string($nom) ? imap_decoder_entete($nom) : '', strtolower(trim(imap_vers_utf8($adresse)))];
}

function imap_parametres(mixed $liste): array
{
    $parametres = [];
    if (is_array($liste)) {
        for ($i = 0; $i + 1 < count($liste); $i += 2) {
            if (is_string($liste[$i]) && is_string($liste[$i + 1])) {
                $parametres[strtolower($liste[$i])] = $liste[$i + 1];
            }
        }
    }
    return $parametres;
}

// Parties texte (hors pièces jointes) d'une BODYSTRUCTURE, dans l'ordre : [{section, sous_type, encodage, jeu}]
function imap_parties_texte(array $noeud, string $chemin = ''): array
{
    $parties = [];
    if (isset($noeud[0]) && is_array($noeud[0])) {
        foreach ($noeud as $i => $enfant) {
            if (!is_array($enfant)) {
                break;
            }
            $section = ($chemin === '' ? '' : "$chemin.") . ($i + 1);
            $parties = array_merge($parties, imap_parties_texte($enfant, $section));
        }
        return $parties;
    }
    if (strtoupper((string) ($noeud[0] ?? '')) !== 'TEXT') {
        return [];
    }
    $disposition = $noeud[9] ?? null;
    if (is_array($disposition) && strtoupper((string) ($disposition[0] ?? '')) === 'ATTACHMENT') {
        return [];
    }
    return [[
        'section' => $chemin === '' ? '1' : $chemin,
        'sous_type' => strtolower((string) ($noeud[1] ?? 'plain')),
        'encodage' => strtolower((string) ($noeud[5] ?? '7bit')),
        'jeu' => imap_parametres($noeud[2] ?? null)['charset'] ?? 'utf-8',
    ]];
}

// La partie dont on tire l'extrait : le premier text/plain, sinon le premier text/html
function imap_partie_extrait(mixed $structure): ?array
{
    if (!is_array($structure)) {
        return null;
    }
    $parties = imap_parties_texte($structure);
    foreach (['plain', 'html'] as $type) {
        foreach ($parties as $partie) {
            if ($partie['sous_type'] === $type) {
                return $partie;
            }
        }
    }
    return null;
}

// --- Extrait -----------------------------------------------------------------------------------

function imap_decoder_corps(string $octets, string $encodage): string
{
    if ($encodage === 'base64') {
        $propre = preg_replace('/[^A-Za-z0-9+\/]/', '', $octets) ?? '';
        return (string) base64_decode(substr($propre, 0, strlen($propre) - (strlen($propre) % 4 === 1 ? 1 : 0)));
    }
    if ($encodage === 'quoted-printable') {
        return quoted_printable_decode($octets);
    }
    return $octets;
}

function imap_html_en_texte(string $html): string
{
    $texte = preg_replace('~<(script|style|title)\b[^>]*>.*?(?:</\1\s*>|\z)~is', ' ', $html) ?? $html;
    $texte = preg_replace('~<!--.*?(?:-->|\z)~s', ' ', $texte) ?? $texte;
    $texte = preg_replace('~</?(?:p|div|br|tr|td|th|li|ul|ol|table|blockquote|h[1-6])\b[^>]*>~i', ' ', $texte) ?? $texte;
    $texte = preg_replace('~<[^>]*(?:>|\z)~s', '', $texte) ?? $texte;
    return html_entity_decode($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Une coupure à 2 000 octets peut laisser un caractère UTF-8 incomplet en fin de texte : on l'enlève
function imap_couper_utf8_incomplet(string $octets): string
{
    $n = strlen($octets);
    for ($i = 1; $i <= min(3, $n); $i++) {
        $o = ord($octets[$n - $i]);
        if ($o < 0x80) {
            break;
        }
        if ($o >= 0xC0) {
            $attendu = $o >= 0xF0 ? 4 : ($o >= 0xE0 ? 3 : 2);
            return $i < $attendu ? substr($octets, 0, $n - $i) : $octets;
        }
    }
    return $octets;
}

// Début d'un corps (octets bruts d'une partie) → une ligne, espaces simples, 200 caractères au plus
function imap_extrait(string $octets, string $encodage, string $jeu, string $sous_type): string
{
    $octets = imap_decoder_corps($octets, strtolower($encodage));
    if (in_array(strtolower($jeu), ['utf-8', 'utf8'], true)) {
        $octets = imap_couper_utf8_incomplet($octets);
    }
    $texte = imap_vers_utf8($octets, $jeu);
    if (strtolower($sous_type) === 'html') {
        $texte = imap_html_en_texte($texte);
    }
    $texte = imap_nettoyer($texte);
    $texte = preg_replace('/[\s\x{00A0}\x{00AD}\x{034F}\x{200B}-\x{200F}\x{2028}-\x{202F}\x{205F}-\x{2064}\x{3000}\x{FEFF}]+/u', ' ', $texte) ?? '';
    return rtrim(mb_substr(trim($texte), 0, JR_IMAP_EXTRAIT_MAX));
}

// --- Un mail brut à partir des éléments d'un FETCH ---------------------------------------------

// Rend [brut, partie] (partie : où lire l'extrait, ou null), ou null si le mail est inutilisable (sans UID)
function imap_brut_depuis_fetch(array $elements): ?array
{
    $uid = isset($elements['UID']) && is_string($elements['UID']) ? (int) $elements['UID'] : 0;
    if ($uid <= 0) {
        return null;
    }
    $enveloppe = is_array($elements['ENVELOPE'] ?? null) ? array_pad($elements['ENVELOPE'], 10, null) : array_fill(0, 10, null);
    [$nom, $adresse] = imap_expediteur($enveloppe[2]);
    $drapeaux = is_array($elements['FLAGS'] ?? null) ? array_map(fn($d): string => strtolower((string) $d), $elements['FLAGS']) : [];
    $date = imap_date_iso(
        is_string($elements['INTERNALDATE'] ?? null) ? $elements['INTERNALDATE'] : null,
        is_string($enveloppe[0]) ? $enveloppe[0] : null
    );
    $brut = [
        'uid' => $uid,
        'message_id' => is_string($enveloppe[9]) ? trim(imap_vers_utf8($enveloppe[9])) : '',
        'date' => $date ?? imap_iso_utc(maintenant()),
        'de_nom' => $nom,
        'de_adresse' => $adresse,
        'objet' => is_string($enveloppe[1]) ? imap_decoder_entete($enveloppe[1]) : '',
        'extrait' => '',
        'non_lu' => !in_array('\\seen', $drapeaux, true),
        'entetes' => is_string($elements['HEADER'] ?? null) ? imap_entetes_depuis_bloc($elements['HEADER']) : [],
        'gm_msgid' => isset($elements['X-GM-MSGID']) && is_string($elements['X-GM-MSGID']) ? $elements['X-GM-MSGID'] : null,
    ];
    return [$brut, imap_partie_extrait($elements['BODYSTRUCTURE'] ?? null)];
}
