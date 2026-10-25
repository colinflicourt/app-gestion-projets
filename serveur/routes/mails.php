<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ecritures.php';
require_once __DIR__ . '/../lib/mail_lecture.php';
require_once __DIR__ . '/../lib/mails.php';

// Mails en lecture seule (SPEC § 16.7). GET mails ne lit que les copies : aucun appel IMAP.
return [
    'GET mails' => ['publique' => false, 'action' => fn(array $requete, ?array $session): array => reponse_json(mails_reponse())],

    // Un compte à la fois (l'iPhone en lance un par compte, en parallèle) : la ou les sections concernées
    'POST mails/rafraichir' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        $compte = mails_compte_du_corps($requete['corps']);
        set_time_limit(30);
        $resultat = mails_rafraichir($compte, JR_MAILS_AGE_ROUTE, microtime(true) + JR_MAILS_LIMITE_ROUTE);
        $sections = array_values(array_filter(mails_reponse()['sections'], fn(array $s): bool => in_array($s['section'], JR_MAILS_SECTIONS_DU_COMPTE[$compte], true)));
        return reponse_json([
            'compte' => $compte,
            'sections' => $sections,
            'comptes' => [$compte => [
                'recupere_le' => $resultat['recupere_le'] === null ? null : iso_paris($resultat['recupere_le']),
                'erreur' => $resultat['erreur'],
            ]],
        ]);
    }],

    'GET comptes-mail' => ['publique' => false, 'action' => fn(array $requete, ?array $session): array => reponse_json(['comptes' => comptes_mail_lire()])],

    // Réseau exigé et réponse immédiate : pas de file (décision 6)
    'POST comptes-mail' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        $c = $requete['corps'];
        mails_enregistrer(mails_compte_du_corps($c), mails_texte_du_corps($c, 'identifiant'), mails_texte_du_corps($c, 'mot_de_passe'));
        return reponse_json(['comptes' => comptes_mail_lire()]);
    }],

    'POST comptes-mail/tester' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        mails_tester(mails_compte_du_corps($requete['corps']));
        return reponse_json(['comptes' => comptes_mail_lire()]);
    }],

    'POST comptes-mail/supprimer' => route_ecriture('comptes-mail/supprimer', function (array $c): array {
        compte_mail_supprimer(mails_compte_du_corps($c));
        return ['comptes' => comptes_mail_lire()];
    }),

    'POST mails/expediteur' => route_ecriture('mails/expediteur', function (array $c): array {
        $adresse = $c['adresse'] ?? null;
        $choix = $c['choix'] ?? null;
        if (!is_string($adresse) || !is_string($choix)) {
            throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
        }
        mails_ou_400(function () use ($adresse, $choix): array {
            correction_ecrire($adresse, $choix);
            return [];
        });
        return ['adresse' => mail_adresse_normalisee($adresse), 'choix' => $choix];
    }),

    // Ouvre un mail et le marque lu dans la boîte (SPEC § 17.5) : réseau exigé, réponse immédiate, pas de file (décision 3)
    'POST mails/ouvrir' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        $compte = mails_compte_du_corps($requete['corps']);
        $uid = $requete['corps']['uid'] ?? null;
        if (!is_int($uid) || $uid < 1 || $uid > 4294967295) {
            throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
        }
        set_time_limit(30);
        return reponse_json(mail_ouvrir($compte, $uid));
    }],

    // Réponses brutes : leurs propres en-têtes, y compris pour les erreurs (décision 5)
    'GET mails/corps' => [
        'publique' => false,
        'action' => function (array $requete, ?array $session): array {
            [$compte, $uid, $images] = mail_parametres_get($requete, false);
            set_time_limit(30);
            return mail_page_corps($compte, $uid, $images);
        },
        'erreur' => fn(int $statut, string $code): array => reponse_brute($statut, entetes_corps_mail(false), page_corps_erreur("Ce mail ne s'ouvre pas pour l'instant.")),
    ],

    'GET mails/piece' => [
        'publique' => false,
        'action' => function (array $requete, ?array $session): array {
            [$compte, $uid, $partie] = mail_parametres_get($requete, true);
            return mail_flux_piece($compte, $uid, $partie);
        },
        'erreur' => fn(int $statut, string $code): array => reponse_brute($statut, entetes_piece_erreur(), match (true) {
            $statut === 404 => 'Introuvable.',
            $statut === 413 => 'Trop volumineux.',
            $statut === 401 => 'Session expirée.',
            $statut >= 500 => 'Indisponible pour le moment.',
            default => 'Requête refusée.',
        }),
    ],
];
