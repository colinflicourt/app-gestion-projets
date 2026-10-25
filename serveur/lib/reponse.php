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

// Posés sur toutes les réponses de l'API, sauf corps et pièces de mail (reponse_brute). Le .htaccess pose les mêmes sur les fichiers statiques
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

// Pour un client qui lit le corps tel quel (le raccourci Siri le fait dire à voix haute)
function reponse_texte(string $texte, int $statut = 200): array
{
    return [
        'statut' => $statut,
        'entetes' => entetes_securite() + ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'],
        'cookies' => [],
        'corps' => $texte,
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

// Réponse dont les en-têtes sont exactement ceux donnés : sans entetes_securite(), pour les routes dont le
// .htaccess ne pose pas non plus les siens (corps d'un mail, pièces : SPEC § 17.6 et § 17.7)
function reponse_brute(int $statut, array $entetes, string $corps): array
{
    return [
        'statut' => $statut,
        'entetes' => $entetes,
        'cookies' => [],
        'corps' => $corps,
    ];
}

// Statut d'une ErreurSource qui n'est pas une panne de la source : le reste vaut 502
const JR_STATUTS_SOURCE = [
    'absent' => 404,
    'hors_copie' => 404,
    'partie_inconnue' => 404,
    'trop_lourde' => 413,
];

function statut_erreur_source(string $code): int
{
    return JR_STATUTS_SOURCE[$code] ?? 502;
}

// Réponse écrite au fur et à mesure : emettre() appelle $producteur($commencer, $ecrire).
// $commencer(statut, en-têtes) une seule fois avant le premier $ecrire(octets). Une exception avant $commencer
// donne un statut d'erreur avec $entetes_erreur ; une exception après coupe la réponse.
function reponse_flux(callable $producteur, array $entetes_erreur): array
{
    return [
        'statut' => 502,
        'entetes' => $entetes_erreur,
        'cookies' => [],
        'corps' => '',
        'flux' => $producteur,
    ];
}

// Joue le producteur d'une réponse en flux avec les fonctions d'émission données (testable sans header())
function flux_executer(array $reponse, callable $poser_statut_et_entetes, callable $sortir): void
{
    $commence = false;
    $commencer = function (int $statut, array $entetes) use (&$commence, $poser_statut_et_entetes): void {
        if ($commence) {
            throw new LogicException('Flux : la réponse a déjà commencé');
        }
        $commence = true;
        $poser_statut_et_entetes($statut, $entetes);
    };
    $ecrire = function (string $octets) use (&$commence, $sortir): void {
        if (!$commence) {
            throw new LogicException('Flux : écriture avant le départ de la réponse');
        }
        $sortir($octets);
    };
    try {
        ($reponse['flux'])($commencer, $ecrire);
    } catch (Throwable $e) {
        if ($commence) {
            journal_erreur('flux', $e);
            return;
        }
        $statut = $e instanceof ErreurApi ? $e->statut : ($e instanceof ErreurSource ? statut_erreur_source($e->codeErreur) : 502);
        if (!$e instanceof ErreurApi && !in_array($statut, [404, 413], true)) {
            journal_erreur('flux', $e);
        }
        $poser_statut_et_entetes($statut, $reponse['entetes']);
        $sortir(match (true) {
            $statut === 404 => 'Introuvable.',
            $statut === 413 => 'Trop volumineux.',
            $statut >= 500 => 'Indisponible pour le moment.',
            default => 'Requête refusée.',
        });
    }
}

function emettre(array $reponse): void
{
    header_remove('X-Powered-By');
    if (isset($reponse['flux'])) {
        // Cookies d'abord : ils font partie des en-têtes, qui partent avec le premier octet
        foreach ($reponse['cookies'] as $cookie) {
            setcookie($cookie['nom'], $cookie['valeur'], $cookie['options']);
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flux_executer(
            $reponse,
            function (int $statut, array $entetes): void {
                http_response_code($statut);
                foreach ($entetes as $nom => $valeur) {
                    header("$nom: $valeur");
                }
            },
            function (string $octets): void {
                echo $octets;
                flush();
            }
        );
        return;
    }
    http_response_code($reponse['statut']);
    foreach ($reponse['entetes'] as $nom => $valeur) {
        header("$nom: $valeur");
    }
    foreach ($reponse['cookies'] as $cookie) {
        setcookie($cookie['nom'], $cookie['valeur'], $cookie['options']);
    }
    echo $reponse['corps'];
}
