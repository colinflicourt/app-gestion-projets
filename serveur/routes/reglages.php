<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ecritures.php';
require_once __DIR__ . '/../lib/google.php';
require_once __DIR__ . '/../lib/rappels.php';

// Réglages de l'écran Réglages. POST reglages/siri est dans siri.php.
return [
    'GET reglages' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        $passage = reglage_lire('cron_dernier_passage');
        return reponse_json([
            'rappels' => reglages_rappels(),
            'planificateur' => ['dernier_passage' => is_string($passage) ? $passage : null],
            'siri' => ['jeton_cree' => jeton_lire('siri') !== null],
        ]);
    }],

    'POST reglages/rappels' => route_ecriture('reglages/rappels', function (array $c): array {
        $rappels = reglages_rappels_stricts($c['rappels'] ?? null);
        if ($rappels === null) {
            throw new ErreurApi('reglages_invalides', 400, 'Réglages illisibles.');
        }
        reglage_ecrire('rappels', $rappels);
        return ['rappels' => reglages_rappels()];
    }),
];
