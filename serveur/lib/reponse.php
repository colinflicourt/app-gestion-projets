<?php
// Réponses JSON de l'API, erreurs, en-têtes de sécurité.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';

final class ErreurApi extends RuntimeException
{
    public function __construct(public readonly string $codeErreur, public readonly int $statut, string $message)
    {
        parent::__construct($message);
    }
}

// Posés sur toutes les réponses de l'API. Le .htaccess pose les mêmes sur les fichiers statiques
// (tests/unitaires/test_htaccess.php vérifie qu'ils restent identiques), plus HSTS.
function entetes_securite(): array
{
    return [
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; font-src 'self'; manifest-src 'self'; worker-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex, nofollow',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];
}

function reponse_json(array $donnees, int $statut = 200, array $cookies = []): array
{
    return [
        'statut' => $statut,
        'entetes' => entetes_securite() + ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
        'cookies' => $cookies,
        'corps' => json_encode(['ok' => $statut < 400] + $donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ];
}

function reponse_erreur(string $code, string $message, int $statut, array $cookies = []): array
{
    return reponse_json(['erreur' => ['code' => $code, 'message' => $message]], $statut, $cookies);
}

function reponse_redirection(string $url, array $cookies = []): array
{
    return [
        'statut' => 302,
        'entetes' => entetes_securite() + ['Location' => $url, 'Cache-Control' => 'no-store'],
        'cookies' => $cookies,
        'corps' => '',
    ];
}

function emettre(array $reponse): void
{
    http_response_code($reponse['statut']);
    foreach ($reponse['entetes'] as $nom => $valeur) {
        header("$nom: $valeur");
    }
    foreach ($reponse['cookies'] as $cookie) {
        setcookie($cookie['nom'], $cookie['valeur'], $cookie['options']);
    }
    echo $reponse['corps'];
}
