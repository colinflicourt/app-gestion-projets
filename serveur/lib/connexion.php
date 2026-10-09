<?php
// Connexion par mot de passe : 10 échecs par adresse IP en 15 min, puis pause.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';

const JR_CONNEXION_MAX_ECHECS = 10;
const JR_CONNEXION_FENETRE = 900;

function connexion_echecs_recents(string $ip): int
{
    $requete = db()->prepare('SELECT COUNT(*) FROM essais_connexion WHERE ip = ? AND quand > ?');
    $requete->execute([$ip, utc(maintenant()->modify('-' . JR_CONNEXION_FENETRE . ' seconds'))]);
    return (int) $requete->fetchColumn();
}

// L'essai est noté avant la vérification du mot de passe : des requêtes envoyées en même temps se
// comptent les unes les autres, et pas plus de 10 mots de passe ne sont testés. Renvoie son id.
function connexion_noter_essai(string $ip): int
{
    $maintenant = maintenant();
    db()->prepare('INSERT INTO essais_connexion (ip, quand) VALUES (?, ?)')->execute([mb_substr($ip, 0, 45), utc($maintenant)]);
    $id = (int) db()->lastInsertId();
    db()->prepare('DELETE FROM essais_connexion WHERE quand < ?')->execute([utc($maintenant->modify('-1 day'))]);
    return $id;
}

// Un essai réussi, ou refusé sans vérification, ne compte pas comme un échec.
function connexion_oublier_essai(int $id): void
{
    db()->prepare('DELETE FROM essais_connexion WHERE id = ?')->execute([$id]);
}

function connexion_mot_de_passe_valide(string $mot_de_passe): bool
{
    $empreinte = (string) config('mot_de_passe_empreinte');
    return $empreinte !== '' && password_verify($mot_de_passe, $empreinte);
}

function message_essais_restants(int $restants): string
{
    return 'Mot de passe incorrect. Encore ' . $restants . ($restants > 1 ? ' essais' : ' essai') . ' avant une pause de 15 min.';
}
