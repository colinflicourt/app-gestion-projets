<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/google.php';

return [
    'GET google/connexion' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        return reponse_redirection(google_url_connexion());
    }],

    // Adresse déclarée chez Google. Toujours une redirection vers Moi, qui affiche le résultat.
    'GET google-callback.php' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        $code = $requete['requete']['code'] ?? '';
        $state = $requete['requete']['state'] ?? '';
        try {
            if (isset($requete['requete']['error']) || !is_string($code) || !is_string($state)) {
                throw new ErreurSource('google_refuse', 'Google : connexion refusée ou adresse incomplète');
            }
            google_terminer_connexion($code, $state);
        } catch (ErreurSource $e) {
            journal_erreur('google-callback.php', $e);
            return reponse_redirection(config('origine') . '/#/moi?google=erreur');
        }
        return reponse_redirection(config('origine') . '/#/moi?google=connecte');
    }],
];
