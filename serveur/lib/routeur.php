<?php
// Logique de l'API : une requête (tableau) entre, une réponse (tableau) sort.
// Toute route exige une session, sauf si elle est déclarée publique.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/reponse.php';
require_once __DIR__ . '/session.php';

const JR_CORPS_MAX = 65536;

// Chaque fichier de serveur/routes/ renvoie ['MÉTHODE chemin' => ['publique' => bool, 'action' => callable]].
function routes(): array
{
    $routes = [];
    foreach (glob(__DIR__ . '/../routes/*.php') as $fichier) {
        $routes += require $fichier;
    }
    return $routes + ($GLOBALS['JR_ROUTES_SUPPLEMENTAIRES'] ?? []);
}

function traiter(array $requete): array
{
    try {
        $route = routes()[$requete['methode'] . ' ' . $requete['chemin']] ?? null;
        if ($route === null) {
            throw new ErreurApi('route_inconnue', 404, 'Adresse inconnue.');
        }
        $requete['corps'] = $requete['methode'] === 'GET' ? [] : lire_corps_json($requete);
        assurer_migrations();
        $session = session_trouver($requete['cookies'][JR_COOKIE_SESSION] ?? null);
        if (!$route['publique'] && $session === null) {
            throw new ErreurApi('session_expiree', 401, 'Ta session a expiré. Reconnecte-toi.');
        }
        // Prolongée avant l'action : une déconnexion pose son cookie effacé en dernier
        $prolongation = $session !== null ? session_prolonger_si_besoin($session) : null;
        $reponse = ($route['action'])($requete, $session);
        if ($prolongation !== null) {
            array_unshift($reponse['cookies'], $prolongation);
        }
        return $reponse;
    } catch (ErreurApi $e) {
        return reponse_erreur($e->codeErreur, $e->getMessage(), $e->statut);
    } catch (Throwable $e) {
        journal_erreur($requete['chemin'], $e);
        return reponse_erreur('erreur_serveur', 'Erreur du serveur. Réessaie dans un instant.', 500);
    }
}

// Écritures : POST en JSON, depuis l'origine de l'app, corps objet de 64 Ko au plus (vide accepté).
function lire_corps_json(array $requete): array
{
    if (($requete['entetes']['origin'] ?? null) !== config('origine')) {
        throw new ErreurApi('origine_refusee', 403, 'Requête refusée.');
    }
    if (!str_starts_with(strtolower((string) ($requete['entetes']['content-type'] ?? '')), 'application/json')) {
        throw new ErreurApi('type_refuse', 415, 'Requête refusée.');
    }
    if (strlen($requete['corps_brut']) > JR_CORPS_MAX) {
        throw new ErreurApi('corps_trop_gros', 413, 'Requête trop grosse.');
    }
    if ($requete['corps_brut'] === '') {
        return [];
    }
    $corps = json_decode($requete['corps_brut'], true);
    if (!is_array($corps) || ($corps !== [] && array_is_list($corps))) {
        throw new ErreurApi('json_invalide', 400, 'Requête illisible.');
    }
    return $corps;
}
