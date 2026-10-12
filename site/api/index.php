<?php
// Point d'entrée unique de l'API : le .htaccess (ou outils/apercu.php en local) y renvoie toute adresse /api/…

declare(strict_types=1);

ini_set('display_errors', '0');
require __DIR__ . '/../../serveur/lib/routeur.php';

set_error_handler('erreur_php');

$chemin = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$reponse = traiter([
    'methode' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'chemin' => (string) preg_replace('#^/api/#', '', $chemin),
    'requete' => $_GET,
    'corps_brut' => (string) file_get_contents('php://input', false, null, 0, JR_CORPS_MAX + 1),
    'entetes' => [
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
        'content-type' => $_SERVER['CONTENT_TYPE'] ?? '',
        // Apache ne transmet pas toujours Authorization : le .htaccess le recopie (REDIRECT_ après la réécriture)
        'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
    ],
    'cookies' => $_COOKIE,
    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'appareil' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
]);
emettre($reponse);

// Travail après la réponse (le planificateur) : l'appelant n'attend ni Trello, ni Google, ni les envois
if (isset($reponse['apres'])) {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    ignore_user_abort(true);
    try {
        ($reponse['apres'])();
    } catch (Throwable $e) {
        journal_erreur('apres', $e);
    }
}
