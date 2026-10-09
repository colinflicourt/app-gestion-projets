<?php
// Routeur de l'aperçu local : php -S localhost:8787 -t site outils/apercu.php
// Imite le .htaccess : /api/… vers le point d'entrée, en-têtes de sécurité, pas de cache, fichiers cachés refusés.

declare(strict_types=1);

$chemin = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (str_starts_with($chemin, '/api/')) {
    require __DIR__ . '/../site/api/index.php';
    return true;
}

require_once __DIR__ . '/../serveur/lib/reponse.php';
foreach (entetes_securite() as $nom => $valeur) {
    header("$nom: $valeur");
}
header('Cache-Control: no-cache');

$site = (string) realpath(__DIR__ . '/../site');
$fichier = realpath($site . ($chemin === '/' ? '/index.html' : $chemin));
if ($fichier === false || !str_starts_with($fichier, $site . '/') || is_dir($fichier) || str_contains($chemin, '/.')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Introuvable';
    return true;
}

$types = [
    'html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
    'json' => 'application/json', 'webmanifest' => 'application/manifest+json', 'png' => 'image/png',
    'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'txt' => 'text/plain; charset=utf-8',
    'ico' => 'image/x-icon',
];
header('Content-Type: ' . ($types[strtolower(pathinfo($fichier, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
readfile($fichier);
return true;
