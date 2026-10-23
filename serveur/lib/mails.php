<?php
// Comptes mail (mot de passe chiffré) et corrections d'expéditeur. La lecture des boîtes est dans imap.php.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/chiffrement.php';
require_once __DIR__ . '/tri_mails.php';

const JR_MAILS_IDENTIFIANTS = [
    'gmail' => 'colin.flicourt@gmail.com',
    'icloud' => 'colin.flicourt@icloud.com',
    'colin' => 'colin@qyris.fr',
    'contact' => 'contact@qyris.fr',
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
