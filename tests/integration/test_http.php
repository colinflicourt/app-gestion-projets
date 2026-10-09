<?php
declare(strict_types=1);

test("HTTP : l'aperçu local sert l'API et les fichiers avec les bons en-têtes, connexion et déconnexion comprises", function () {
    base_de_test();
    [$origine, $serveur] = lancer_apercu();
    try {
        $json = ['Origin: ' . $origine, 'Content-Type: application/json'];

        $sante = requete_http('GET', "$origine/api/sante");
        egal(200, $sante['statut']);
        egal(true, json_decode($sante['corps'], true)['base_ok']);

        $robots = requete_http('GET', "$origine/robots.txt");
        egal(200, $robots['statut']);
        egal('no-cache', $robots['entetes']['cache-control'][0] ?? null);
        vrai(isset($robots['entetes']['content-security-policy']), 'CSP attendue sur les fichiers statiques');

        egal(404, requete_http('GET', "$origine/.htaccess")['statut']);
        egal(401, requete_http('POST', "$origine/api/deconnexion", $json, '{}')['statut']);
        egal(403, requete_http('POST', "$origine/api/connexion", ['Origin: https://autre.site', 'Content-Type: application/json'], '{"mot_de_passe":"x"}')['statut']);
        egal(401, requete_http('POST', "$origine/api/connexion", $json, '{"mot_de_passe":"mauvais"}')['statut']);

        $ok = requete_http('POST', "$origine/api/connexion", $json, json_encode(['mot_de_passe' => MOT_DE_PASSE_TEST]));
        egal(200, $ok['statut']);
        $cookie = $ok['entetes']['set-cookie'][0] ?? '';
        foreach (['__host-jr=', 'path=/', 'secure', 'httponly', 'samesite=lax'] as $attendu) {
            vrai(str_contains(strtolower($cookie), $attendu), "cookie sans $attendu");
        }
        vrai((bool) preg_match('/Max-Age=777[56]\d{3}/', $cookie), 'Max-Age de 90 jours attendu');

        $jeton = explode(';', substr($cookie, strlen('__Host-jr=')))[0];
        egal(200, requete_http('POST', "$origine/api/deconnexion", array_merge($json, ['Cookie: __Host-jr=' . $jeton]), '')['statut']);
    } finally {
        arreter_apercu($serveur);
    }
});

test('HTTP : 15 essais de connexion envoyés en même temps ne testent pas plus de 10 mots de passe', function () {
    $pdo = base_de_test();
    // Coût bcrypt réaliste : chaque vérification dure assez longtemps pour que les requêtes se chevauchent
    $empreinte = password_hash(MOT_DE_PASSE_TEST, PASSWORD_DEFAULT, ['cost' => 11]);
    [$origine, $serveur] = lancer_apercu(['mot_de_passe_empreinte' => $empreinte], 8);
    try {
        $multi = curl_multi_init();
        $poignees = [];
        for ($i = 0; $i < 15; $i++) {
            $c = curl_init("$origine/api/connexion");
            curl_setopt_array($c, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Origin: ' . $origine, 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => '{"mot_de_passe":"mauvais"}',
            ]);
            curl_multi_add_handle($multi, $c);
            $poignees[] = $c;
        }
        do {
            curl_multi_exec($multi, $en_cours);
            curl_multi_select($multi);
        } while ($en_cours > 0);
        $statuts = array_count_values(array_map(fn($c): int => (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE), $poignees));
        egal(15, ($statuts[401] ?? 0) + ($statuts[429] ?? 0), 'réponses 401 ou 429 attendues : ' . json_encode($statuts));
        // Le 10e mot de passe faux répond déjà 429 : au plus 9 « Mot de passe incorrect »
        vrai(($statuts[401] ?? 0) <= 9, 'trop de « Mot de passe incorrect » : ' . json_encode($statuts));
        // Chaque mot de passe testé et faux laisse une ligne dans essais_connexion
        $testes = (int) $pdo->query('SELECT COUNT(*) FROM essais_connexion')->fetchColumn();
        vrai($testes <= 10, "$testes mots de passe testés, 10 au plus attendus");
    } finally {
        arreter_apercu($serveur);
    }
});
