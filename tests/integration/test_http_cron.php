<?php
declare(strict_types=1);

test("HTTP : POST /api/cron avec le jeton en Authorization répond 200 et fait le passage ; sans jeton, 404 et rien", function () {
    base_de_test();
    reglage_ecrire('rappels', ['brief' => ['jours' => []]]);
    copie_trello([]);  // copie neuve : le serveur d'aperçu n'appellera pas Trello
    [$origine, $serveur] = lancer_apercu();
    try {
        egal(404, requete_http('POST', "$origine/api/cron", [], '')['statut']);
        egal(null, reglage_lire('cron_dernier_passage'));
        egal(200, requete_http('POST', "$origine/api/cron", ['Authorization: Bearer ' . JETON_CRON_TEST], '')['statut']);
        vrai(reglage_lire('cron_dernier_passage') !== null, 'passage fait après la réponse');
    } finally {
        arreter_apercu($serveur);
    }
});
