<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/connexion.php';
require_once __DIR__ . '/../lib/abonnements.php';

return [
    'POST connexion' => ['publique' => true, 'action' => function (array $requete, ?array $session): array {
        $ip = $requete['ip'];
        $essai = connexion_noter_essai($ip);
        $essais = connexion_echecs_recents($ip);  // celui-ci compris
        if ($essais > JR_CONNEXION_MAX_ECHECS) {
            connexion_oublier_essai($essai);
            throw new ErreurApi('trop_d_essais', 429, "Trop d'essais. Réessaie dans 15 min.");
        }
        $mot_de_passe = $requete['corps']['mot_de_passe'] ?? null;
        if (!is_string($mot_de_passe) || !connexion_mot_de_passe_valide($mot_de_passe)) {
            $restants = JR_CONNEXION_MAX_ECHECS - $essais;
            if ($restants <= 0) {
                throw new ErreurApi('trop_d_essais', 429, "Trop d'essais. Réessaie dans 15 min.");
            }
            throw new ErreurApi('mot_de_passe_incorrect', 401, message_essais_restants($restants));
        }
        connexion_oublier_essai($essai);
        return reponse_json([], 200, [session_creer($requete['appareil'])]);
    }],

    'POST deconnexion' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        session_supprimer((int) $session['id']);
        return reponse_json([], 200, [cookie_session_efface()]);
    }],

    'POST deconnexion-partout' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        session_supprimer_toutes();
        abonnements_supprimer_tous();
        return reponse_json([], 200, [cookie_session_efface()]);
    }],
];
