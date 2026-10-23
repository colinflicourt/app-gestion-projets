<?php
// Client IMAP en lecture seule (SPEC § 16.5) : pas d'extension imap, une socket TLS, six commandes possibles.
// Tout ce qui part vers le serveur passe par imap_envoyer(), qui refuse le reste avant d'écrire.

declare(strict_types=1);

require_once __DIR__ . '/imap_decodage.php';

const JR_IMAP_SERVEURS = ['gmail' => 'imap.gmail.com', 'icloud' => 'imap.mail.me.com', 'colin' => 'ssl0.ovh.net', 'contact' => 'ssl0.ovh.net'];
const JR_IMAP_PORT = 993;
const JR_IMAP_DELAI = 8;
const JR_IMAP_NON_LUS_MAX = 200;
const JR_IMAP_DERNIERS = 60;
const JR_IMAP_LOT = 60;
const JR_IMAP_OCTETS_EXTRAIT = 2000;

// Éléments d'un UID FETCH permis : jamais BODY[ sans PEEK, jamais le message entier
const JR_IMAP_ELEMENT_FETCH = 'UID|FLAGS|INTERNALDATE|ENVELOPE|BODYSTRUCTURE|X-GM-MSGID'
    . '|BODY\.PEEK\[(?:HEADER\.FIELDS \([A-Za-z-]+(?: [A-Za-z-]+)*\)|\d+(?:\.\d+)*)\](?:<\d+\.\d+>)?';

// Une connexion ouverte : la socket, ce qui est lu mais pas encore consommé, les délais
final class ConnexionImap
{
    public string $tampon = '';
    public int $position = 0;
    public int $numero = 0;
    public float $echeance;
    public array $capacites = [];
    public int $uidvalidity = 0;
    public int $taille_lecture = 8192;

    public function __construct(public $prise, public float $limite)
    {
        $this->echeance = $limite;
    }
}

// --- Liste blanche ------------------------------------------------------------------------------

// Une commande IMAP (sans étiquette) coupée aux littéraux {n}, ou null si elle n'est pas permise.
// LOGIN seul peut contenir des littéraux : « LOGIN {5}\r\nalice "mot de passe" » → ['LOGIN {5}\r\n', 'alice "mot de passe"'].
function imap_segments_commande(string $commande): ?array
{
    if (str_starts_with($commande, 'LOGIN ')) {
        return imap_segments_login($commande);
    }
    if (in_array($commande, ['CAPABILITY', 'LOGOUT', 'EXAMINE "INBOX"', 'UID SEARCH UNSEEN', 'UID SEARCH ALL'], true)) {
        return [$commande];
    }
    $element = JR_IMAP_ELEMENT_FETCH;
    if (preg_match("~\\AUID FETCH \\d+(?::\\d+)?(?:,\\d+(?::\\d+)?)* \\((?:$element)(?: (?:$element))*\\)\\z~", $commande)) {
        return [$commande];
    }
    return null;
}

function imap_segments_login(string $commande): ?array
{
    $position = 6;
    $debut = 0;
    $segments = [];
    for ($i = 0; $i < 2; $i++) {
        if ($i === 1) {
            if (($commande[$position] ?? '') !== ' ') {
                return null;
            }
            $position++;
        }
        if (preg_match('/\G"[\x20\x21\x23-\x5B\x5D-\x7E]*"/', $commande, $m, 0, $position)) {
            $position += strlen($m[0]);
        } elseif (preg_match('/\G\{(\d{1,6})\}\r\n/', $commande, $m, 0, $position)) {
            $position += strlen($m[0]);
            $segments[] = substr($commande, $debut, $position - $debut);
            $debut = $position;
            $position += (int) $m[1];
            if ($position > strlen($commande) || str_contains(substr($commande, $debut, $position - $debut), "\0")) {
                return null;
            }
        } else {
            return null;
        }
    }
    if ($position !== strlen($commande)) {
        return null;
    }
    $segments[] = substr($commande, $debut);
    return $segments;
}

function imap_commande_autorisee(string $commande): bool
{
    return imap_segments_commande($commande) !== null;
}

// --- Envoi et lecture ---------------------------------------------------------------------------

// Temps qu'il reste pour la réponse attendue (8 s au plus, et jamais au-delà de la limite globale)
function imap_reste(ConnexionImap $c): float
{
    $reste = min($c->echeance, $c->limite) - microtime(true);
    if ($reste <= 0) {
        throw new ErreurSource('delai', 'IMAP : délai dépassé');
    }
    return $reste;
}

// Seul endroit qui écrit sur la socket, appelé par imap_envoyer() après la liste blanche
function imap_ecrire(ConnexionImap $c, string $octets): void
{
    while ($octets !== '') {
        imap_reste($c);
        $ecrits = @fwrite($c->prise, $octets);
        if ($ecrits === false || $ecrits === 0) {
            throw new ErreurSource('injoignable', 'IMAP : écriture impossible');
        }
        $octets = substr($octets, $ecrits);
    }
}

// Lit un morceau de plus dans le tampon
function imap_remplir(ConnexionImap $c): void
{
    $reste = imap_reste($c);
    $secondes = (int) floor($reste);
    stream_set_timeout($c->prise, $secondes, $secondes === 0 ? max(1000, (int) (($reste - $secondes) * 1e6)) : (int) (($reste - $secondes) * 1e6));
    $donnees = @fread($c->prise, $c->taille_lecture);
    if ($donnees === false || $donnees === '') {
        $meta = stream_get_meta_data($c->prise);
        if (!empty($meta['timed_out'])) {
            throw new ErreurSource('delai', 'IMAP : le serveur ne répond pas');
        }
        if (!empty($meta['eof']) || feof($c->prise)) {
            throw new ErreurSource('injoignable', 'IMAP : connexion coupée par le serveur');
        }
        usleep(2000);
        return;
    }
    if ($c->position > 65536) {
        $c->tampon = substr($c->tampon, $c->position);
        $c->position = 0;
    }
    $c->tampon .= $donnees;
}

function imap_ligne(ConnexionImap $c): string
{
    while (($fin = strpos($c->tampon, "\r\n", $c->position)) === false) {
        if (strlen($c->tampon) - $c->position > JR_IMAP_LITTERAL_MAX) {
            throw new ErreurProtocoleImap('Ligne IMAP trop longue');
        }
        imap_remplir($c);
    }
    $ligne = substr($c->tampon, $c->position, $fin - $c->position);
    $c->position = $fin + 2;
    return $ligne;
}

function imap_octets(ConnexionImap $c, int $n): string
{
    while (strlen($c->tampon) - $c->position < $n) {
        imap_remplir($c);
    }
    $octets = substr($c->tampon, $c->position, $n);
    $c->position += $n;
    return $octets;
}

// Une réponse non sollicitée entière : la ligne, et pour chaque littéral {n} ses n octets puis la suite de la ligne
function imap_lire_logique(ConnexionImap $c): string
{
    $texte = imap_ligne($c);
    while (str_starts_with($texte, '* ') && preg_match('/\{(\d+)\}\z/', $texte, $m)) {
        $taille = (int) $m[1];
        if ($taille > JR_IMAP_LITTERAL_MAX) {
            throw new ErreurProtocoleImap('Littéral IMAP trop grand');
        }
        $texte .= "\r\n" . imap_octets($c, $taille) . imap_ligne($c);
    }
    return $texte;
}

// Lit jusqu'à la réponse étiquetée. Rend ['statut' => OK|NO|BAD, 'texte' => fin de ligne, 'non_sollicitees' => [...]],
// ou ['continuation' => true] si le serveur attend la suite d'un littéral et que $continuation est vrai.
function imap_lire_reponse(ConnexionImap $c, string $etiquette, bool $continuation = false): array
{
    $non_sollicitees = [];
    while (true) {
        $ligne = imap_lire_logique($c);
        if (str_starts_with($ligne, '* ')) {
            $non_sollicitees[] = $ligne;
        } elseif ($continuation && ($ligne === '+' || str_starts_with($ligne, '+ '))) {
            return ['continuation' => true, 'non_sollicitees' => $non_sollicitees];
        } elseif (str_starts_with($ligne, "$etiquette ")) {
            if (!preg_match('/\A\S+ (OK|NO|BAD)\b(.*)\z/is', $ligne, $m)) {
                throw new ErreurProtocoleImap('Réponse étiquetée illisible');
            }
            return ['statut' => strtoupper($m[1]), 'texte' => trim($m[2]), 'non_sollicitees' => $non_sollicitees];
        }
    }
}

// Seul chemin des commandes vers le serveur. Une commande hors liste blanche lève LogicException AVANT
// toute écriture ; le message ne cite que le verbe (une commande LOGIN contient le mot de passe).
function imap_envoyer(ConnexionImap $c, string $commande): array
{
    $segments = imap_segments_commande($commande);
    if ($segments === null) {
        $verbe = preg_match('/\A[A-Za-z]+/', $commande, $m) ? strtoupper(substr($m[0], 0, 12)) : '?';
        throw new LogicException("Commande IMAP interdite : $verbe");
    }
    $c->echeance = min(microtime(true) + JR_IMAP_DELAI, $c->limite);
    $etiquette = 'J' . ++$c->numero;
    $dernier = count($segments) - 1;
    foreach ($segments as $i => $segment) {
        imap_ecrire($c, ($i === 0 ? "$etiquette " : '') . $segment . ($i === $dernier ? "\r\n" : ''));
        if ($i < $dernier) {
            $reponse = imap_lire_reponse($c, $etiquette, true);
            if (!isset($reponse['continuation'])) {
                return $reponse;
            }
        }
    }
    return imap_lire_reponse($c, $etiquette);
}

// --- Session ------------------------------------------------------------------------------------

function imap_adresse(string $compte): string
{
    if (isset($GLOBALS['JR_IMAP_ADRESSE'])) {
        return $GLOBALS['JR_IMAP_ADRESSE'];
    }
    if (!isset(JR_IMAP_SERVEURS[$compte])) {
        throw new InvalidArgumentException('Compte mail inconnu');
    }
    return 'tls://' . JR_IMAP_SERVEURS[$compte] . ':' . JR_IMAP_PORT;
}

function imap_connecter(string $compte, float $limite): ConnexionImap
{
    $adresse = imap_adresse($compte);
    $delai = min((float) JR_IMAP_DELAI, $limite - microtime(true));
    if ($delai <= 0) {
        throw new ErreurSource('delai', 'IMAP : délai dépassé');
    }
    $contexte = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
        'peer_name' => (string) parse_url($adresse, PHP_URL_HOST),
        'SNI_enabled' => true,
    ]]);
    $debut = microtime(true);
    $prise = @stream_socket_client($adresse, $code, $message, $delai, STREAM_CLIENT_CONNECT, $contexte);
    if ($prise === false) {
        $trop_long = microtime(true) - $debut >= $delai - 0.25;
        throw new ErreurSource($trop_long ? 'delai' : 'injoignable', $trop_long ? 'IMAP : connexion trop lente' : 'IMAP : connexion impossible');
    }
    stream_set_blocking($prise, true);
    return new ConnexionImap($prise, $limite);
}

function imap_capacites(array $reponse): array
{
    $liste = [];
    foreach ($reponse['non_sollicitees'] ?? [] as $ligne) {
        if (preg_match('/\A\* CAPABILITY (.*)\z/i', $ligne, $m)) {
            array_push($liste, ...preg_split('/\s+/', strtoupper(trim($m[1])), -1, PREG_SPLIT_NO_EMPTY));
        }
    }
    if (preg_match('/\[CAPABILITY ([^\]]*)\]/i', $reponse['texte'] ?? '', $m)) {
        array_push($liste, ...preg_split('/\s+/', strtoupper(trim($m[1])), -1, PREG_SPLIT_NO_EMPTY));
    }
    return $liste;
}

function imap_exiger(array $reponse, string $quoi): void
{
    if ($reponse['statut'] !== 'OK') {
        throw new ErreurSource('injoignable', "IMAP : $quoi refusé par le serveur");
    }
}

// Argument de LOGIN : entre guillemets s'il est en ASCII imprimable sans « " » ni « \ », sinon littéral {n}
function imap_argument(#[\SensitiveParameter] string $valeur): string
{
    if (preg_match('/\A[\x20\x21\x23-\x5B\x5D-\x7E]*\z/', $valeur)) {
        return '"' . $valeur . '"';
    }
    return '{' . strlen($valeur) . "}\r\n" . $valeur;
}

function imap_login(ConnexionImap $c, string $identifiant, #[\SensitiveParameter] string $mot_de_passe): void
{
    if (in_array('LOGINDISABLED', $c->capacites, true) || str_contains($identifiant, "\0") || str_contains($mot_de_passe, "\0")) {
        throw new ErreurSource('refus', 'IMAP : connexion par mot de passe impossible');
    }
    $reponse = imap_envoyer($c, 'LOGIN ' . imap_argument($identifiant) . ' ' . imap_argument($mot_de_passe));
    if ($reponse['statut'] !== 'OK') {
        throw new ErreurSource('refus', 'IMAP : identifiants refusés');
    }
    array_push($c->capacites, ...imap_capacites($reponse));
}

// Connexion, accueil, CAPABILITY, LOGIN, EXAMINE "INBOX" (lecture seule) ; $travail reçoit la connexion prête.
// Toute erreur devient une ErreurSource (refus, injoignable, delai) sans texte venu du serveur.
function imap_session(string $compte, string $identifiant, #[\SensitiveParameter] string $mot_de_passe, float $limite, callable $travail): mixed
{
    $c = null;
    try {
        $c = imap_connecter($compte, $limite);
        $c->echeance = min(microtime(true) + JR_IMAP_DELAI, $limite);
        if (!preg_match('/\A\* (?:OK|PREAUTH)\b/i', imap_ligne($c))) {
            throw new ErreurSource('injoignable', "IMAP : accueil inattendu");
        }
        $reponse = imap_envoyer($c, 'CAPABILITY');
        imap_exiger($reponse, 'CAPABILITY');
        $c->capacites = imap_capacites($reponse);
        imap_login($c, $identifiant, $mot_de_passe);
        $reponse = imap_envoyer($c, 'EXAMINE "INBOX"');
        imap_exiger($reponse, 'EXAMINE');
        foreach ($reponse['non_sollicitees'] as $ligne) {
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $ligne, $m)) {
                $c->uidvalidity = (int) $m[1];
            }
        }
        $resultat = $travail($c);
        try {
            imap_envoyer($c, 'LOGOUT');
        } catch (Throwable) {
        }
        return $resultat;
    } catch (ErreurProtocoleImap) {
        throw new ErreurSource('injoignable', 'IMAP : réponse illisible');
    } finally {
        if ($c !== null) {
            @fclose($c->prise);
        }
    }
}

function imap_essayer(string $compte, string $identifiant, #[\SensitiveParameter] string $mot_de_passe, float $limite): void
{
    imap_session($compte, $identifiant, $mot_de_passe, $limite, fn(ConnexionImap $c): null => null);
}

// --- Lecture d'une boîte ------------------------------------------------------------------------

function imap_chercher(ConnexionImap $c, string $critere): array
{
    $reponse = imap_envoyer($c, "UID SEARCH $critere");
    imap_exiger($reponse, 'recherche');
    $uids = [];
    foreach ($reponse['non_sollicitees'] as $ligne) {
        array_push($uids, ...(imap_reponse_recherche($ligne) ?? []));
    }
    $uids = array_values(array_unique($uids));
    sort($uids);
    return $uids;
}

// Les éléments de chaque FETCH reçu pour les UID demandés (les réponses d'autres UID ou sans UID sont ignorées)
function imap_fetch(ConnexionImap $c, array $lot, string $elements): array
{
    $reponse = imap_envoyer($c, 'UID FETCH ' . imap_ensemble_uid($lot) . " ($elements)");
    imap_exiger($reponse, 'lecture');
    $demandes = array_flip($lot);
    $recus = [];
    foreach ($reponse['non_sollicitees'] as $ligne) {
        try {
            $fetch = imap_reponse_fetch($ligne);
        } catch (ErreurProtocoleImap) {
            continue;
        }
        $uid = isset($fetch['UID']) && is_string($fetch['UID']) ? (int) $fetch['UID'] : 0;
        if ($fetch !== null && isset($demandes[$uid])) {
            $recus[$uid] = array_replace($recus[$uid] ?? [], $fetch);
        }
    }
    return $recus;
}

// Un passage par compte : non lus récents (200 au plus) et 60 derniers mails, puis extraits groupés par partie.
// Rend ['uidvalidity' => int, 'mails' => liste de bruts, du plus récent au plus ancien].
function imap_lire_boite(string $compte, string $identifiant, #[\SensitiveParameter] string $mot_de_passe, float $limite): array
{
    return imap_session($compte, $identifiant, $mot_de_passe, $limite, function (ConnexionImap $c) use ($compte): array {
        $non_lus = array_slice(array_reverse(imap_chercher($c, 'UNSEEN')), 0, JR_IMAP_NON_LUS_MAX);
        $derniers = array_slice(imap_chercher($c, 'ALL'), -JR_IMAP_DERNIERS);
        $uids = array_values(array_unique(array_merge($non_lus, $derniers)));
        if ($uids === []) {
            return ['uidvalidity' => $c->uidvalidity, 'mails' => []];
        }
        sort($uids);

        $gmail = $compte === 'gmail' && in_array('X-GM-EXT-1', $c->capacites, true);
        $elements = 'UID FLAGS INTERNALDATE ENVELOPE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS ('
            . implode(' ', array_map('strtoupper', JR_IMAP_ENTETES_UTILES)) . ')]' . ($gmail ? ' X-GM-MSGID' : '');
        $mails = [];
        $parties = [];
        foreach (array_chunk($uids, JR_IMAP_LOT) as $lot) {
            foreach (imap_fetch($c, $lot, $elements) as $uid => $fetch) {
                try {
                    $construit = imap_brut_depuis_fetch($fetch);
                } catch (ErreurProtocoleImap) {
                    $construit = null;
                }
                if ($construit !== null) {
                    [$mails[$uid], $parties[$uid]] = $construit;
                }
            }
        }

        $par_section = [];
        foreach ($parties as $uid => $partie) {
            if ($partie !== null) {
                $par_section[(string) $partie['section']][] = $uid;
            }
        }
        foreach ($par_section as $section => $uids_section) {
            $section = (string) $section;
            foreach (array_chunk($uids_section, JR_IMAP_LOT) as $lot) {
                $octets = JR_IMAP_OCTETS_EXTRAIT;
                foreach (imap_fetch($c, $lot, "UID BODY.PEEK[$section]<0.$octets>") as $uid => $fetch) {
                    $corps = $fetch["CORPS:$section"] ?? null;
                    if (is_string($corps)) {
                        $p = $parties[$uid];
                        $mails[$uid]['extrait'] = imap_extrait($corps, $p['encodage'], $p['jeu'], $p['sous_type']);
                    }
                }
            }
        }

        usort($mails, fn(array $a, array $b): int => [$b['date'], $b['uid']] <=> [$a['date'], $a['uid']]);
        return ['uidvalidity' => $c->uidvalidity, 'mails' => array_values($mails)];
    });
}
