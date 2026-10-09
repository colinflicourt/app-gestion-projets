<?php
// Sessions maison (les sessions PHP d'OVH expirent après 24 min d'inactivité) : un jeton aléatoire dans
// le cookie, son empreinte SHA-256 en base, 90 jours glissants.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';

const JR_COOKIE_SESSION = '__Host-jr';
const JR_SESSION_DUREE = 7776000;
const JR_SESSION_RAFRAICHIR = 86400;

function session_creer(string $appareil): array
{
    $jeton = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $maintenant = maintenant();
    db()->prepare('INSERT INTO sessions (empreinte, cree_le, vu_le, expire_le, appareil) VALUES (?, ?, ?, ?, ?)')
        ->execute([
            hash('sha256', $jeton),
            utc($maintenant),
            utc($maintenant),
            utc($maintenant->modify('+' . JR_SESSION_DUREE . ' seconds')),
            mb_substr($appareil, 0, 120),
        ]);
    return cookie_session($jeton, JR_SESSION_DUREE);
}

// Ce qui ne ressemble pas à un jeton (43 caractères base64url) est ignoré sans requête.
function session_trouver(?string $jeton): ?array
{
    if ($jeton === null || !preg_match('/^[A-Za-z0-9_-]{43}$/', $jeton)) {
        return null;
    }
    $requete = db()->prepare('SELECT id, cree_le, vu_le, expire_le FROM sessions WHERE empreinte = ?');
    $requete->execute([hash('sha256', $jeton)]);
    $session = $requete->fetch();
    if ($session === false) {
        return null;
    }
    if (depuis_utc($session['expire_le']) <= maintenant()) {
        db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$session['id']]);
        return null;
    }
    return $session + ['jeton' => $jeton];
}

function session_prolonger_si_besoin(array $session): ?array
{
    $maintenant = maintenant();
    if ($maintenant->getTimestamp() - depuis_utc($session['vu_le'])->getTimestamp() < JR_SESSION_RAFRAICHIR) {
        return null;
    }
    db()->prepare('UPDATE sessions SET vu_le = ?, expire_le = ? WHERE id = ?')
        ->execute([utc($maintenant), utc($maintenant->modify('+' . JR_SESSION_DUREE . ' seconds')), $session['id']]);
    return cookie_session($session['jeton'], JR_SESSION_DUREE);
}

function session_supprimer(int $id): void
{
    db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
}

function session_supprimer_toutes(): void
{
    db()->exec('DELETE FROM sessions');
}

// SameSite=Lax et non Strict : au retour de la connexion Google (navigation venue d'un autre site), le
// cookie doit être envoyé. Les écritures restent protégées : POST en JSON depuis la bonne origine.
function cookie_session(string $jeton, int $duree): array
{
    return ['nom' => JR_COOKIE_SESSION, 'valeur' => $jeton, 'options' => [
        'expires' => $duree > 0 ? maintenant()->getTimestamp() + $duree : 1,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]];
}

function cookie_session_efface(): array
{
    return cookie_session('', 0);
}
