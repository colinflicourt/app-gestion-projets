<?php
// Comptes mail (mot de passe chiffré) et corrections d'expéditeur. La lecture des boîtes est dans imap.php.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/chiffrement.php';
require_once __DIR__ . '/imap.php';
require_once __DIR__ . '/rafraichir.php';
require_once __DIR__ . '/reponse.php';
require_once __DIR__ . '/tri_mails.php';

const JR_MAILS_AGE_ROUTE = 300;   // une copie de plus de 5 min est relue quand l'iPhone le demande
const JR_MAILS_AGE_CRON = 900;    // et de plus de 15 min par le planificateur
const JR_MAILS_LIMITE_ROUTE = 25; // secondes de lecture IMAP au plus pour une requête (set_time_limit(30) dans la route)
const JR_MAILS_RESTE_CRON = 12;   // le planificateur ne lance une lecture que s'il lui reste ce temps
const JR_MAILS_SECTIONS_DU_COMPTE = ['utc' => ['utc'], 'gmail' => ['gmail'], 'icloud' => ['icloud'], 'colin' => ['qyris']];
const JR_MAILS_NOMS_BOITES = ['utc' => 'le webmail UTC', 'gmail' => 'Gmail', 'icloud' => 'iCloud Mail', 'colin' => 'le webmail OVH'];
const JR_MAILS_MESSAGES = [
    'refus' => 'Mot de passe refusé',
    'injoignable' => "Boîte injoignable pour l'instant.",
    'delai' => 'La boîte a mis trop de temps à répondre.',
];

const JR_MAILS_IDENTIFIANTS = [
    'utc' => 'cflicour',
    'gmail' => 'colin.flicourt@gmail.com',
    'icloud' => 'colin.flicourt@icloud.com',
    'colin' => 'colin@qyris.fr',
];

function compte_mail_verifier(string $compte): void
{
    if (!in_array($compte, JR_MAILS_COMPTES, true)) {
        throw new InvalidArgumentException('Compte mail inconnu');
    }
}

function mail_date_iso(?string $utc): ?string
{
    return $utc === null ? null : depuis_utc($utc)->format('Y-m-d\TH:i:s\Z');
}

// Jamais le mot de passe : seulement s'il est enregistré
function comptes_mail_lire(): array
{
    $lignes = [];
    foreach (db()->query('SELECT compte, identifiant, dernier_succes, derniere_erreur FROM comptes_mail') as $ligne) {
        $lignes[$ligne['compte']] = $ligne;
    }
    $comptes = [];
    foreach (JR_MAILS_COMPTES as $compte) {
        $ligne = $lignes[$compte] ?? null;
        $comptes[$compte] = [
            'identifiant' => $ligne['identifiant'] ?? JR_MAILS_IDENTIFIANTS[$compte],
            'enregistre' => $ligne !== null,
            'dernier_succes' => mail_date_iso($ligne['dernier_succes'] ?? null),
            'derniere_erreur' => $ligne['derniere_erreur'] ?? null,
        ];
    }
    return $comptes;
}

function compte_mail_enregistrer(string $compte, string $identifiant, #[\SensitiveParameter] string $mot_de_passe): void
{
    compte_mail_verifier($compte);
    db()->prepare('INSERT INTO comptes_mail (compte, identifiant, mot_de_passe, modifie_le, dernier_succes, derniere_erreur)
        VALUES (?, ?, ?, ?, NULL, NULL) AS n
        ON DUPLICATE KEY UPDATE identifiant = n.identifiant, mot_de_passe = n.mot_de_passe, modifie_le = n.modifie_le, derniere_erreur = NULL')
        ->execute([$compte, $identifiant, chiffrer($mot_de_passe), utc(maintenant())]);
}

// Mot de passe en clair, seulement pour la connexion IMAP
function compte_mail_acces(string $compte): ?array
{
    compte_mail_verifier($compte);
    $requete = db()->prepare('SELECT identifiant, mot_de_passe FROM comptes_mail WHERE compte = ?');
    $requete->execute([$compte]);
    $ligne = $requete->fetch();
    if ($ligne === false) {
        return null;
    }
    return ['identifiant' => $ligne['identifiant'], 'mot_de_passe' => dechiffrer($ligne['mot_de_passe'])];
}

function compte_mail_noter(string $compte, ?string $erreur): void
{
    compte_mail_verifier($compte);
    if ($erreur === null) {
        db()->prepare('UPDATE comptes_mail SET dernier_succes = ?, derniere_erreur = NULL WHERE compte = ?')
            ->execute([utc(maintenant()), $compte]);
    } else {
        db()->prepare('UPDATE comptes_mail SET derniere_erreur = ? WHERE compte = ?')->execute([substr($erreur, 0, 20), $compte]);
    }
}

function compte_mail_supprimer(string $compte): void
{
    compte_mail_verifier($compte);
    db()->prepare('DELETE FROM comptes_mail WHERE compte = ?')->execute([$compte]);
    db()->prepare('DELETE FROM copies WHERE source = ?')->execute(['mail:' . $compte]);
}

function mail_adresse_normalisee(string $adresse): string
{
    return mb_strtolower(preg_replace('/\s+/u', '', $adresse) ?? '');
}

function corrections_lire(): array
{
    $corrections = [];
    foreach (db()->query('SELECT adresse, choix FROM expediteurs_tri') as $ligne) {
        $corrections[$ligne['adresse']] = $ligne['choix'];
    }
    return $corrections;
}

// 'auto' efface la correction
function correction_ecrire(string $adresse, string $choix): void
{
    $adresse = mail_adresse_normalisee($adresse);
    if ($adresse === '' || mb_strlen($adresse) > 254 || !in_array($choix, ['afficher', 'pub', 'auto'], true)) {
        throw new InvalidArgumentException('Correction invalide');
    }
    if ($choix === 'auto') {
        db()->prepare('DELETE FROM expediteurs_tri WHERE adresse = ?')->execute([$adresse]);
        return;
    }
    db()->prepare('INSERT INTO expediteurs_tri (adresse, choix, cree_le) VALUES (?, ?, ?) AS n
        ON DUPLICATE KEY UPDATE choix = n.choix')->execute([$adresse, $choix, utc(maintenant())]);
}

// --- Lecture des boîtes et copies ---------------------------------------------------------------

// Lit la boîte (JR_LECTEUR_IMAP en test), garde la copie et note le succès ou l'erreur dans comptes_mail.
// Compte non enregistré : rien n'est lu. $limite : microtime absolu.
function mails_rafraichir(string $compte, int $age, float $limite): array
{
    $acces = compte_mail_acces($compte);
    if ($acces === null) {
        return ['contenu' => null, 'recupere_le' => null, 'erreur' => 'non_connecte'];
    }
    $resultat = rafraichir('mail:' . $compte, $age, function () use ($compte, $acces, $limite): array {
        try {
            $brut = ($GLOBALS['JR_LECTEUR_IMAP'] ?? 'imap_lire_boite')($compte, $acces['identifiant'], $acces['mot_de_passe'], $limite);
        } catch (ErreurSource $e) {
            compte_mail_noter($compte, $e->codeErreur);
            throw $e;
        }
        compte_mail_noter($compte, null);
        return [
            'uidvalidity' => (int) $brut['uidvalidity'],
            'mails' => array_map(fn(array $m): array => mail_pour_copie($m, $compte), $brut['mails']),
        ];
    });
    // Verrou de la source pris trop longtemps par une autre lecture : la boîte n'y est pour rien
    if ($resultat['erreur'] !== null && !isset(JR_MAILS_MESSAGES[$resultat['erreur']])) {
        $resultat['erreur'] = 'delai';
    }
    return $resultat;
}

// compte => ['contenu', 'recupere_le'] ou null (compte non enregistré ou jamais lu)
function mails_copies_datees(): array
{
    $copies = [];
    foreach (comptes_mail_lire() as $compte => $etat) {
        $copie = $etat['enregistre'] ? copie_lire('mail:' . $compte) : null;
        $copies[$compte] = $copie === null ? null : ['contenu' => $copie['contenu'], 'recupere_le' => $copie['recupere_le']];
    }
    return $copies;
}

function mails_copies(): array
{
    return array_map(fn(?array $c): ?array => $c['contenu'] ?? null, mails_copies_datees());
}

// Ce que rend GET mails : sections triées et état de chaque compte, tout depuis les copies (aucun appel IMAP)
function mails_reponse(): array
{
    $datees = mails_copies_datees();
    $etats = comptes_mail_lire();
    $comptes = [];
    foreach (JR_MAILS_COMPTES as $compte) {
        $comptes[$compte] = [
            'recupere_le' => isset($datees[$compte]) ? iso_paris($datees[$compte]['recupere_le']) : null,
            'erreur' => $etats[$compte]['enregistre'] ? $etats[$compte]['derniere_erreur'] : 'non_connecte',
        ];
    }
    $copies = array_map(fn(?array $c): ?array => $c['contenu'] ?? null, $datees);
    return ['sections' => mails_sections($copies, corrections_lire()), 'comptes' => $comptes];
}

// Non lus hors pubs, toutes sections
function mails_non_lus_total(): int
{
    return mails_resume(mails_sections(mails_copies(), corrections_lire()))['total'];
}

// --- Aides des routes ---------------------------------------------------------------------------

// Les InvalidArgumentException de ce fichier sont des erreurs de requête
function mails_ou_400(callable $action): array
{
    try {
        return $action();
    } catch (InvalidArgumentException) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
}

function mails_compte_du_corps(array $corps): string
{
    $compte = $corps['compte'] ?? null;
    if (!is_string($compte) || !in_array($compte, JR_MAILS_COMPTES, true)) {
        throw new ErreurApi('compte_invalide', 400, 'Compte mail inconnu.');
    }
    return $compte;
}

// Texte de 1 à 200 caractères sans caractère de contrôle
function mails_texte_du_corps(array $corps, string $cle): string
{
    $texte = $corps[$cle] ?? null;
    if (!is_string($texte)) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    $texte = $cle === 'identifiant' ? trim($texte) : $texte;
    if ($texte === '' || mb_strlen($texte) > 200 || preg_match('/[\x00-\x1f\x7f]/', $texte) === 1) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    return $texte;
}

// Connexion d'essai (JR_ESSAI_IMAP en test). Échec : ErreurApi 400 avec le code refus, injoignable ou delai.
function mails_essayer(string $compte, string $identifiant, #[\SensitiveParameter] string $mot_de_passe): void
{
    set_time_limit(30);
    try {
        ($GLOBALS['JR_ESSAI_IMAP'] ?? 'imap_essayer')($compte, $identifiant, $mot_de_passe, microtime(true) + JR_MAILS_LIMITE_ROUTE);
    } catch (ErreurSource $e) {
        $code = isset(JR_MAILS_MESSAGES[$e->codeErreur]) ? $e->codeErreur : 'injoignable';
        journal_erreur("comptes-mail/$compte", $e);
        throw new ErreurApi($code, 400, JR_MAILS_MESSAGES[$code]);
    }
}

// Teste un compte enregistré et note le résultat
function mails_tester(string $compte): void
{
    $acces = compte_mail_acces($compte);
    if ($acces === null) {
        throw new ErreurApi('non_connecte', 400, 'Pas encore connecté');
    }
    try {
        mails_essayer($compte, $acces['identifiant'], $acces['mot_de_passe']);
    } catch (ErreurApi $e) {
        compte_mail_noter($compte, $e->codeErreur);
        throw $e;
    }
    compte_mail_noter($compte, null);
}

// Enregistre après une connexion d'essai réussie ; sinon rien n'est gardé. Une autre adresse : l'ancienne copie part.
function mails_enregistrer(string $compte, string $identifiant, #[\SensitiveParameter] string $mot_de_passe): void
{
    $avant = comptes_mail_lire()[$compte];
    mails_essayer($compte, $identifiant, $mot_de_passe);
    if ($avant['enregistre'] && $avant['identifiant'] !== $identifiant) {
        db()->prepare('DELETE FROM copies WHERE source = ?')->execute(['mail:' . $compte]);
    }
    compte_mail_enregistrer($compte, $identifiant, $mot_de_passe);
    compte_mail_noter($compte, null);
}
