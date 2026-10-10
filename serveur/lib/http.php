<?php
// Appels sortants (Trello, Google) : un seul client, remplacé dans les tests par $GLOBALS['JR_CLIENT_HTTP'].

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';

const JR_HTTP_DELAI = 8;

// Messages pour l'iPhone quand une source manque une écriture (le code part avec)
const JR_MESSAGES_SOURCES = [
    'trello_indisponible' => 'Trello ne répond pas. Réessaie dans un instant.',
    'google_indisponible' => 'Google ne répond pas. Réessaie dans un instant.',
    'google_deconnecte' => "Google m'a déconnecté de ton agenda. Reconnecte-le dans Réglages, ça prend 30 secondes.",
];

// Rien n'est revenu du réseau (délai dépassé, DNS, TLS…)
final class ErreurReseau extends RuntimeException {}

// Une source (Trello, Google) n'a pas répondu ou a refusé : la dernière copie est montrée avec ce code
final class ErreurSource extends RuntimeException
{
    public function __construct(public readonly string $codeErreur, string $message, public readonly int $statutHttp = 0)
    {
        parent::__construct($message);
    }
}

// Renvoie ['statut' => code HTTP, 'corps' => texte]. Pas de redirection suivie.
function http_requete(string $methode, string $url, array $entetes = [], ?string $corps = null, int $delai = JR_HTTP_DELAI): array
{
    if (isset($GLOBALS['JR_CLIENT_HTTP'])) {
        return ($GLOBALS['JR_CLIENT_HTTP'])($methode, $url, $entetes, $corps, $delai);
    }
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_CUSTOMREQUEST => $methode,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $entetes,
        CURLOPT_CONNECTTIMEOUT => min(4, $delai),
        CURLOPT_TIMEOUT => $delai,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($corps !== null) {
        curl_setopt($c, CURLOPT_POSTFIELDS, $corps);
    }
    $reponse = curl_exec($c);
    if (!is_string($reponse)) {
        throw new ErreurReseau('Réseau : ' . curl_error($c));
    }
    return ['statut' => (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE), 'corps' => $reponse];
}

// Corps JSON d'une réponse, ou null s'il est illisible
function http_json(array $reponse): mixed
{
    return json_decode($reponse['corps'], true);
}
