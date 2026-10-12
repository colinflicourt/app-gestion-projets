<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/planificateur.php';

// Planificateur (SPEC § 15.4) : cron-job.org appelle chaque minute avec son jeton. Mauvais jeton : 404, comme
// une adresse inconnue. La réponse part tout de suite, le passage se fait après (site/api/index.php).
return [
    'POST cron' => ['publique' => true, 'machine' => true, 'action' => function (array $requete, ?array $session): array {
        $attendu = (string) (config('cron')['jeton'] ?? '');
        if ($attendu === '' || !hash_equals($attendu, jeton_bearer($requete))) {
            throw new ErreurApi('route_inconnue', 404, 'Adresse inconnue.');
        }
        return reponse_json([]) + ['apres' => fn(): array => planificateur_passage()];
    }],
];
