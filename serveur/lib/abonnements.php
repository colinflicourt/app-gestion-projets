<?php
declare(strict_types=1);

// Abonnements aux notifications (un par appareil) et envoi chiffré à chacun.

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/push.php';
require_once __DIR__ . '/reponse.php';

const JR_PUSH_HOTES = ['web.push.apple.com', 'fcm.googleapis.com', 'updates.push.services.mozilla.com'];

function endpoint_accepte(string $url): bool
{
    $p = parse_url($url);
    if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['port']) || !isset($p['host'])) {
        return false;
    }
    $hote = strtolower($p['host']);
    return in_array($hote, JR_PUSH_HOTES, true) || str_ends_with($hote, '.push.apple.com');
}

function abonnement_enregistrer(array $abonnement, ?int $session_id): int
{
    $texte = fn(mixed $v): string => is_string($v) ? $v : '';
    $cles = is_array($abonnement['keys'] ?? null) ? $abonnement['keys'] : [];
    $endpoint = $texte($abonnement['endpoint'] ?? null);
    $p256dh = $texte($cles['p256dh'] ?? null);
    $auth = $texte($cles['auth'] ?? null);
    try {
        $valide = endpoint_accepte($endpoint) && strlen($endpoint) <= 1000
            && strlen(b64url_decoder($p256dh)) === 65 && strlen(b64url_decoder($auth)) === 16;
    } catch (InvalidArgumentException) {
        $valide = false;
    }
    if (!$valide) {
        throw new ErreurApi('abonnement_invalide', 400, 'Abonnement aux notifications invalide.');
    }
    $maintenant = utc(maintenant());
    db()->prepare('INSERT INTO abonnements (endpoint, empreinte, p256dh, auth, session_id, cree_le, vu_le)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), session_id = VALUES(session_id), vu_le = VALUES(vu_le), echecs = 0')
        ->execute([$endpoint, hash('sha256', $endpoint), $p256dh, $auth, $session_id, $maintenant, $maintenant]);
    return (int) db()->query('SELECT id FROM abonnements WHERE empreinte = ' . db()->quote(hash('sha256', $endpoint)))->fetchColumn();
}

function abonnement_supprimer(string $endpoint): void
{
    db()->prepare('DELETE FROM abonnements WHERE empreinte = ?')->execute([hash('sha256', $endpoint)]);
}

// « Déconnecter tous les appareils » : un appareil perdu ne doit plus rien recevoir
function abonnements_supprimer_tous(): void
{
    db()->exec('DELETE FROM abonnements');
}

function notifier_tous(array $message, string $urgence = 'normal', int $ttl = 43200): array
{
    $bilan = ['envoyes' => 0, 'supprimes' => 0, 'echecs' => 0, 'a_reessayer' => 0];
    $texte = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    foreach (db()->query('SELECT id, endpoint, p256dh, auth FROM abonnements')->fetchAll() as $a) {
        try {
            $corps = chiffrer_message($texte, b64url_decoder($a['p256dh']), b64url_decoder($a['auth']));
            $r = http_requete('POST', $a['endpoint'], [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                "TTL: $ttl",
                "Urgency: $urgence",
                'Authorization: ' . vapid_entete($a['endpoint'], config('vapid'), maintenant()->getTimestamp()),
            ], $corps, 10);
            $statut = $r['statut'];
        } catch (ErreurReseau) {
            $statut = 0;
        }
        if ($statut === 200 || $statut === 201) {
            $bilan['envoyes']++;
        } elseif ($statut === 404 || $statut === 410) {
            db()->prepare('DELETE FROM abonnements WHERE id = ?')->execute([$a['id']]);
            $bilan['supprimes']++;
        } else {
            db()->prepare('UPDATE abonnements SET echecs = LEAST(echecs + 1, 255) WHERE id = ?')->execute([$a['id']]);
            journal_erreur('notifications', new RuntimeException("notification refusée : HTTP $statut (abonnement {$a['id']})"));
            $bilan['echecs']++;
            if ($statut === 0 || $statut === 429 || $statut >= 500) {
                $bilan['a_reessayer']++;
            }
        }
    }
    return $bilan;
}
