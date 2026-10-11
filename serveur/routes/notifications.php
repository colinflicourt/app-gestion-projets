<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/abonnements.php';

// Notifications (SPEC § 15.8) : l'iPhone s'abonne (à chaque ouverture), s'oublie à la déconnexion,
// et demande une notification de test, envoyée par le même chemin que le planificateur.
return [
    'POST abonnements' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        $abonnement = $requete['corps']['abonnement'] ?? null;
        abonnement_enregistrer(is_array($abonnement) ? $abonnement : [], (int) $session['id']);
        return reponse_json([]);
    }],

    'POST abonnements/supprimer' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        $endpoint = $requete['corps']['endpoint'] ?? null;
        if (is_string($endpoint)) {
            abonnement_supprimer($endpoint);
        }
        return reponse_json([]);
    }],

    'POST notifications/test' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        return reponse_json(notifier_tous(['titre' => 'Notification de test', 'texte' => 'Si tu lis ça, tes rappels arriveront bien.', 'url' => '/#/reglages', 'tag' => 'test']));
    }],
];
