<?php
// Point d'entrée unique de l'API : le .htaccess (ou outils/apercu.php en local) y renvoie toute adresse /api/…

declare(strict_types=1);

ini_set('display_errors', '0');
require __DIR__ . '/../../serveur/lib/routeur.php';

set_error_handler(function (int $niveau, string $message, string $fichier, int $ligne): bool {
    throw new ErrorException($message, 0, $niveau, $fichier, $ligne);
});

$chemin = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
emettre(traiter([
    'methode' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'chemin' => (string) preg_replace('#^/api/#', '', $chemin),
    'requete' => $_GET,
    'corps_brut' => (string) file_get_contents('php://input', false, null, 0, JR_CORPS_MAX + 1),
    'entetes' => ['origin' => $_SERVER['HTTP_ORIGIN'] ?? null, 'content-type' => $_SERVER['CONTENT_TYPE'] ?? ''],
    'cookies' => $_COOKIE,
    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'appareil' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
]));
