<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/connexion.php';

return [
    'POST connexion' => ['publique' => true, 'action' => function (array $requete, ?array $session): array {
        $ip = $requete['ip'];
        $echecs = connexion_echecs_recents($ip);
        if ($echecs >= JR_CONNEXION_MAX_ECHECS) {
            throw new ErreurApi('trop_d_essais', 429, "Trop d'essais. Réessaie dans 15 min.");
        }
        $mot_de_passe = $requete['corps']['mot_de_passe'] ?? null;
        if (!is_string($mot_de_passe) || !connexion_mot_de_passe_valide($mot_de_passe)) {
            connexion_noter_echec($ip);
            $restants = JR_CONNEXION_MAX_ECHECS - $echecs - 1;
            if ($restants <= 0) {
                throw new ErreurApi('trop_d_essais', 429, "Trop d'essais. Réessaie dans 15 min.");
            }
            throw new ErreurApi('mot_de_passe_incorrect', 401, message_essais_restants($restants));
        }
        return reponse_json([], 200, [session_creer($requete['appareil'])]);
    }],

    'POST deconnexion' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        session_supprimer((int) $session['id']);
        return reponse_json([], 200, [cookie_session_efface()]);
    }],

    'POST deconnexion-partout' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        session_supprimer_toutes();
        return reponse_json([], 200, [cookie_session_efface()]);
    }],
];
