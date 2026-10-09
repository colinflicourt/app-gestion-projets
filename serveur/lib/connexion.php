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

function connexion_noter_echec(string $ip): void
{
    $maintenant = maintenant();
    db()->prepare('INSERT INTO essais_connexion (ip, quand) VALUES (?, ?)')->execute([mb_substr($ip, 0, 45), utc($maintenant)]);
    db()->prepare('DELETE FROM essais_connexion WHERE quand < ?')->execute([utc($maintenant->modify('-1 day'))]);
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
