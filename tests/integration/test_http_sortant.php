<?php
declare(strict_types=1);

test("HTTP sortant : le vrai client rend le statut et le corps, et lève ErreurReseau si rien ne répond", function () {
    base_de_test();
    [$origine, $serveur] = lancer_apercu();
    unset($GLOBALS['JR_CLIENT_HTTP']);
    try {
        $r = http_requete('GET', "$origine/api/sante");
        egal(200, $r['statut']);
        egal(true, http_json($r)['ok']);
        egal(404, http_requete('GET', "$origine/api/nimporte")['statut']);
    } finally {
        arreter_apercu($serveur);
    }
    leve(ErreurReseau::class, fn() => http_requete('GET', 'http://127.0.0.1:9/', [], null, 1));
});
