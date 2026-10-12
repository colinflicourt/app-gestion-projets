<?php
declare(strict_types=1);

// Jours de la copie de l'agenda, construits comme en vrai par jours_agenda()
function jours_brief(array $utc, array $perso, string $premier = '2026-10-14', int $nombre = 15): array
{
    return jours_agenda($utc, $perso, $premier, $nombre);
}

test('brief : un mercredi chargé, avec le premier cours, la tâche du jour, le médian et une réplique', function () {
    $jours = jours_brief(
        [evenement_google('NF93 - TD 1', '2026-10-14T08:00', '2026-10-14T10:00', 'FA400'), evenement_google('MT23 - TD', '2026-10-14T14:15', '2026-10-14T16:15')],
        [evenement_google('Médian MT23', '2026-10-21T19:15', '2026-10-21T20:45')]
    );
    $cartes = [
        carte_normalisee(['id' => id_test('carte', 1), 'titre' => 'Rendre le TP', 'echeance' => '2026-10-14T18:00:00+02:00']),
        carte_normalisee(['id' => id_test('carte', 2), 'echeance' => '2026-10-15T08:00:00+02:00']),
        carte_normalisee(['id' => id_test('carte', 3), 'colonne' => 'fait', 'echeance' => '2026-10-10T08:00:00+02:00']),
        carte_normalisee(['id' => id_test('carte', 4), 'echeance' => '2026-10-11T08:00:00+02:00', 'echeance_faite' => true]),
    ];
    $b = brief_du_matin('2026-10-14', $cartes, $jours);
    egal('Mercredi 14 octobre · 2 cours', $b['titre']);
    egal('Premier à 8 h en FA400 (NF93 TD). 1 tâche du jour. Médian MT23 dans 7 jours. ' . replique_du_jour('2026-10-14'), $b['texte']);
});

test("brief : sans cours avec des tâches en retard ; « 1 cours » ; le médian du lendemain ou du jour avec l'heure ; à 15 jours, rien", function () {
    $cartes = [carte_normalisee(['echeance' => '2026-10-12T20:00:00+02:00']), carte_normalisee(['id' => id_test('carte', 2), 'echeance' => '2026-10-13T09:00:00+02:00'])];
    $b = brief_du_matin('2026-10-17', $cartes, jours_brief([], []));
    egal('Samedi 17 octobre · Pas de cours', $b['titre']);
    egal('2 tâches en retard. ' . replique_du_jour('2026-10-17'), $b['texte']);
    egal('1 tâche du jour.', brief_taches($cartes, '2026-10-12'));
    egal('1 tâche du jour, 1 en retard.', brief_taches($cartes, '2026-10-13'));
    egal('2 tâches du jour, 1 en retard.', brief_taches([...$cartes, carte_normalisee(['id' => id_test('carte', 3), 'echeance' => '2026-10-13T18:00:00+02:00'])], '2026-10-13'));
    $median = [evenement_google('Médian MT23', '2026-10-21T19:15', '2026-10-21T20:45')];
    $jours = jours_brief([evenement_google('MT23 - Cours', '2026-10-20T10:15', '2026-10-20T12:15')], $median);
    $mardi = brief_du_matin('2026-10-20', [], $jours);
    egal('Mardi 20 octobre · 1 cours', $mardi['titre']);
    egal('Premier à 10 h 15 (MT23 Cours). Médian MT23 demain à 19 h 15. ' . replique_du_jour('2026-10-20'), $mardi['texte']);
    vrai(str_contains(brief_du_matin('2026-10-21', [], $jours)['texte'], "Médian MT23 aujourd'hui à 19 h 15."), 'médian du jour');
    vrai(!str_contains(brief_du_matin('2026-10-06', [], jours_brief([], $median, '2026-10-06', 16))['texte'], 'Médian'), 'médian à 15 jours : pas cité');
});

test("brief : vacances dans le titre ; « devient un lundi » en tête du texte ; jour absent de la copie : ni cours ni « Pas de cours »", function () {
    $jours = jours_brief([
        evenement_google('Vacances universitaires', '2026-10-26', '2026-10-31'),
        evenement_google('Devient un lundi', '2026-11-03', '2026-11-04'),
        evenement_google('NF93 - TD', '2026-11-03T08:00', '2026-11-03T10:00', 'FA400'),
    ], [], '2026-10-26', 15);
    egal('Lundi 26 octobre · Vacances', brief_du_matin('2026-10-26', [], $jours)['titre']);
    $b = brief_du_matin('2026-11-03', [], $jours);
    egal('Mardi 3 novembre · 1 cours', $b['titre']);
    egal("Aujourd'hui, l'UTC suit l'emploi du temps du lundi. Premier à 8 h en FA400 (NF93 TD). " . replique_du_jour('2026-11-03'), $b['texte']);
    $absent = brief_du_matin('2026-12-01', [], $jours);
    egal(['Mardi 1er décembre', replique_du_jour('2026-12-01')], [$absent['titre'], $absent['texte']]);
});

test('brief : répliques tirées par la date, jamais deux jours de suite la même, chacune une fois par bloc de 20 jours', function () {
    egal(20, count(JR_REPLIQUES));
    egal(replique_du_jour('2026-10-14'), replique_du_jour('2026-10-14'));
    $debut = new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC'));
    $precedente = null;
    $blocs = [];
    for ($i = 0; $i < 400; $i++) {
        $jour = $debut->modify("+$i days");
        $r = replique_du_jour($jour->format('Y-m-d'));
        vrai($r !== $precedente, 'même réplique deux jours de suite : ' . $jour->format('Y-m-d'));
        $precedente = $r;
        $blocs[intdiv(intdiv($jour->getTimestamp(), 86400), 20)][] = $r;
    }
    foreach ($blocs as $bloc => $repliques) {
        if (count($repliques) === 20) {
            egal(20, count(array_unique($repliques)), "bloc $bloc");
        }
    }
});
