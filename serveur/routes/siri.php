<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/siri.php';

// Siri (SPEC § 15.7) : route machine, le raccourci iPhone n'envoie ni cookie ni origine. Mauvais jeton : 404,
// comme une adresse inconnue. Les réponses sont en texte brut : le raccourci les lit à voix haute.
return [
    'POST siri' => ['publique' => true, 'machine' => true, 'action' => function (array $requete, ?array $session): array {
        if (!siri_jeton_valide(jeton_bearer($requete))) {
            throw new ErreurApi('route_inconnue', 404, 'Adresse inconnue.');
        }
        $texte = $requete['corps']['texte'] ?? null;
        [$statut, $phrase] = is_string($texte) ? siri_noter($texte) : [400, "Je n'ai rien entendu."];
        return reponse_texte($phrase, $statut);
    }],

    'POST reglages/siri' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        return reponse_json(['jeton' => siri_jeton_creer()]);
    }],
];
