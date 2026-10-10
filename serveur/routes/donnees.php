<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/donnees.php';

return [
    'GET donnees' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        return reponse_json(donnees_reponse(($requete['requete']['force'] ?? '0') === '1'));
    }],

    'GET agenda' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        $semaine = $requete['requete']['semaine'] ?? null;
        $premier = is_string($semaine) ? semaine_premier_jour($semaine) : null;
        if ($premier === null) {
            throw new ErreurApi('semaine_invalide', 400, 'Requête illisible.');
        }
        return reponse_json(['maintenant' => iso_paris(maintenant()), 'semaine' => $semaine, 'agenda' => donnees_agenda("agenda:$semaine", $premier, 7)]);
    }],
];
