<?php
// Écritures uniques : chaque écriture venue de l'iPhone porte un identifiant (UUID v4). Un renvoi depuis
// la file hors ligne rend le résultat déjà obtenu, sans refaire l'action.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/reponse.php';

const JR_ECRITURES_JOURS = 30;

function ecriture_unique(string $id_client, string $route, callable $action): array
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id_client)) {
        throw new ErreurApi('id_client_invalide', 400, 'Requête illisible.');
    }
    $pdo = db();
    $verrou = 'jr_ecriture_' . $id_client;
    $prise = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $prise->execute([$verrou]);
    if ((int) $prise->fetchColumn() !== 1) {
        throw new RuntimeException('Verrou de l\'écriture indisponible');
    }
    try {
        $deja = ecriture_trouver($id_client);
        if ($deja !== null) {
            // Un identifiant ne sert qu'à une écriture : le même pour une autre route est une erreur de l'app
            if ($deja['route'] !== $route) {
                throw new ErreurApi('id_client_reutilise', 409, 'Requête refusée.');
            }
            return $deja['resultat'];
        }
        $resultat = $action();
        $maintenant = maintenant();
        $pdo->prepare('INSERT INTO ecritures (id_client, route, recu_le, resultat) VALUES (?, ?, ?, ?)')
            ->execute([$id_client, $route, utc($maintenant), json_encode($resultat, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $pdo->prepare('DELETE FROM ecritures WHERE recu_le < ? LIMIT 50')
            ->execute([utc($maintenant->modify('-' . JR_ECRITURES_JOURS . ' days'))]);
        return $resultat;
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$verrou]);
    }
}

function ecriture_trouver(string $id_client): ?array
{
    $requete = db()->prepare('SELECT route, recu_le, resultat FROM ecritures WHERE id_client = ?');
    $requete->execute([$id_client]);
    $ligne = $requete->fetch();
    if ($ligne === false) {
        return null;
    }
    return ['route' => $ligne['route'], 'recu_le' => $ligne['recu_le'], 'resultat' => json_decode($ligne['resultat'], true, 512, JSON_THROW_ON_ERROR)];
}

// Route d'écriture : session exigée (par défaut), id_client exigé, action exécutée une seule fois.
// $action reçoit le corps JSON et rend le résultat à renvoyer (et à rejouer).
function route_ecriture(string $route, callable $action): array
{
    return ['publique' => false, 'action' => function (array $requete, ?array $session) use ($route, $action): array {
        $id_client = $requete['corps']['id_client'] ?? null;
        if (!is_string($id_client)) {
            throw new ErreurApi('id_client_invalide', 400, 'Requête illisible.');
        }
        return reponse_json(ecriture_unique($id_client, $route, fn(): array => $action($requete['corps'])));
    }];
}
