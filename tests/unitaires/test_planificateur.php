<?php
declare(strict_types=1);

// Copies Trello et agenda toutes neuves (rien à relire) et Google connecté, comme après une ouverture de l'app
function copies_fraiches(array $cartes, array $evenements_utc = [], array $evenements_perso = []): void
{
    $premier = aujourdhui_paris()->format('Y-m-d');
    copie_trello($cartes);
    copie_ecrire('agenda', ['premier' => $premier, 'jours' => jours_agenda($evenements_utc, $evenements_perso, $premier, JR_JOURS_DONNEES)], maintenant());
    google_connecte_pour_test();
}

function sans_brief_en_base(): void
{
    reglage_ecrire('rappels', ['brief' => ['jours' => []]]);
}

function appel_google(): bool
{
    return array_filter(appels_http(), fn(string $a): bool => str_contains($a, 'googleapis.com/calendar')) !== [];
}

test('planificateur : un passage envoie le rappel du cours une fois ; le passage suivant ne renvoie rien (aucun doublon)', function () {
    base_de_test();
    sans_brief_en_base();
    fixer_heure('2026-10-14 14:31:00');  // 16 h 31 à Paris
    copies_fraiches([], [evenement_google('TN01 - TD', '2026-10-14T16:45', '2026-10-14T18:45', 'FA510')]);
    abonnement_enregistrer(abonnement_exemple(), null);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    egal(['verrou' => true, 'envoyees' => 1, 'perimees' => 0, 'echouees' => 0, 'reportees' => 0], planificateur_passage());
    fixer_heure('2026-10-14 14:32:00');
    egal(['verrou' => true, 'envoyees' => 0, 'perimees' => 0, 'echouees' => 0, 'reportees' => 0], planificateur_passage());
    egal(1, count($GLOBALS['JR_APPELS_HTTP']));
    egal([['TN01 · TD dans 15 min', 'envoye']], db()->query('SELECT titre, statut FROM envois')->fetchAll(PDO::FETCH_NUM));
    egal('2026-10-14T16:32:00+02:00', reglage_lire('cron_dernier_passage'));
});

test('planificateur : si un autre passage tient le verrou, celui-ci repart aussitôt sans rien faire', function () {
    base_de_test();
    $autre = nouvelle_connexion_test();
    egal(1, (int) $autre->query("SELECT GET_LOCK('jr_cron', 0)")->fetchColumn());
    faux_http([]);
    egal(['verrou' => false], planificateur_passage());
    egal(null, reglage_lire('cron_dernier_passage'));
    $autre->query("SELECT RELEASE_LOCK('jr_cron')");
});

test("planificateur : Trello relu au-delà de 15 min, l'agenda au-delà d'une heure, pas avant", function () {
    base_de_test();
    sans_brief_en_base();
    fixer_heure('2026-10-14 10:00:00');
    copies_fraiches([]);
    fixer_heure('2026-10-14 10:14:59');
    faux_http([]);
    planificateur_passage();
    egal([], appels_http());
    fixer_heure('2026-10-14 10:59:59');
    faux_http([BATCH => ['json' => batch_trello()]]);
    planificateur_passage();
    vrai(str_starts_with(appels_http()[0] ?? '', BATCH), 'Trello relu après 15 min');
    vrai(!appel_google(), 'agenda pas relu avant 1 h');
    fixer_heure('2026-10-14 11:00:00');
    google_connecte_pour_test();  // jeton d'accès Google encore valable
    faux_http(['GET https://www.googleapis.com/calendar/v3/calendars/' => ['json' => ['items' => []]]]);
    planificateur_passage();
    vrai(appel_google(), 'agenda relu après 1 h');
});

test("planificateur : Trello en panne, l'agenda et l'envoi continuent avec la dernière copie, et la panne va au journal", function () {
    base_de_test();
    sans_brief_en_base();
    fixer_heure('2026-10-14 14:00:00');
    copies_fraiches([carte_normalisee(['echeance' => '2026-10-15T08:30:00+02:00'])], [evenement_google('TN01 - TD', '2026-10-14T16:45', '2026-10-14T18:45', 'FA510')]);
    fixer_heure('2026-10-14 14:31:00');  // copie Trello de 31 min : relue, mais Trello répond 503
    abonnement_enregistrer(abonnement_exemple(), null);
    faux_http([BATCH => ['statut' => 503, 'corps' => ''], 'POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    egal(1, planificateur_passage()['envoyees']);
    egal(['cours' => 'envoye', 'echeance' => 'prevu'], db()->query('SELECT type, statut FROM envois ORDER BY type')->fetchAll(PDO::FETCH_KEY_PAIR));
    vrai(str_contains((string) file_get_contents(dossier_data() . '/erreurs.log'), 'rafraichir/trello'), 'panne de Trello au journal');
});

test('planificateur : un rappel manuel en base part à son heure ; annulé, il ne part pas', function () {
    base_de_test();
    sans_brief_en_base();
    fixer_heure('2026-10-14 08:00:00');
    copies_fraiches([]);
    $ajout = db()->prepare('INSERT INTO rappels (id_client, texte, quand, cree_le, source, annule) VALUES (?, ?, ?, ?, ?, ?)');
    $ajout->execute([uuid_test(1), 'Appeler Sébastien', '2026-10-14 08:00:00', '2026-10-13 20:00:00', 'app', 0]);
    $ajout->execute([uuid_test(2), 'Annulé', '2026-10-14 08:00:00', '2026-10-13 20:00:00', 'app', 1]);
    abonnement_enregistrer(abonnement_exemple(), null);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    egal(1, planificateur_passage()['envoyees']);
    egal(['Appeler Sébastien'], db()->query('SELECT titre FROM envois')->fetchAll(PDO::FETCH_COLUMN));
});
