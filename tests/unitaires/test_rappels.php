<?php
declare(strict_types=1);

function cles_rappels(array $rappels): array
{
    $cles = array_column($rappels, 'cle');
    sort($cles);
    return $cles;
}

function rappel_cle(array $rappels, string $cle): array
{
    foreach ($rappels as $r) {
        if ($r['cle'] === $cle) {
            return $r;
        }
    }
    throw new EchecTest("rappel absent : $cle");
}

function reglages_sans_brief(): array
{
    $r = JR_REGLAGES_RAPPELS;
    $r['brief']['jours'] = [];
    return $r;
}

function cle_cours(string $debut, string $titre): string
{
    return "cours:$debut:" . substr(hash('sha256', $titre), 0, 12);
}

test("rappels : un cours prévient 15 min avant, avec sa salle, ou ses heures sans salle ; rien pour un cours commencé ni pour l'agenda principal", function () {
    $jours = jours_agenda([
        evenement_google('TN01 - TD', '2026-10-14T16:45', '2026-10-14T18:45', 'FA510'),
        evenement_google('Conférence', '2026-10-14T10:00', '2026-10-14T11:30'),
        evenement_google('NF93 - TD', '2026-10-14T08:00', '2026-10-14T10:00', 'FA400'),
    ], [evenement_google('Dentiste', '2026-10-14T12:00', '2026-10-14T12:30')], '2026-10-14', 2);
    $liste = generer_rappels([], $jours, reglages_sans_brief(), [], utc_test('2026-10-14 06:30:00'));  // 8 h 30 à Paris
    egal([cle_cours('2026-10-14T10:00:00+02:00', 'Conférence'), cle_cours('2026-10-14T16:45:00+02:00', 'TN01 - TD')], cles_rappels($liste));
    $tn01 = rappel_cle($liste, cle_cours('2026-10-14T16:45:00+02:00', 'TN01 - TD'));
    egal(['cours', '2026-10-14 14:30:00', 'TN01 · TD dans 15 min', 'Salle FA510.', '/#/aujourdhui'], [$tn01['type'], utc($tn01['prevu_pour']), $tn01['titre'], $tn01['texte'], $tn01['url']]);
    $conference = rappel_cle($liste, cle_cours('2026-10-14T10:00:00+02:00', 'Conférence'));
    egal(['Conférence dans 15 min', 'De 10 h à 11 h 30.'], [$conference['titre'], $conference['texte']]);
});

test('rappels : les minutes des cours se règlent, et les rappels des cours se coupent', function () {
    $jours = jours_agenda([evenement_google('TN01 - TD', '2026-10-14T16:45', '2026-10-14T18:45', 'FA510')], [], '2026-10-14', 1);
    $r = reglages_sans_brief();
    $r['cours']['minutes'] = 30;
    $liste = generer_rappels([], $jours, $r, [], utc_test('2026-10-14 06:30:00'));
    egal(['2026-10-14 14:15:00', 'TN01 · TD dans 30 min'], [utc($liste[0]['prevu_pour']), $liste[0]['titre']]);
    $r['cours']['actif'] = false;
    egal([], generer_rappels([], $jours, $r, [], utc_test('2026-10-14 06:30:00')));
});

test("rappels : une échéance prévient la veille à 20 h (passage à l'heure d'hiver compris), avec les étapes qui restent", function () {
    $c = fn(int $n, array $s): array => carte_normalisee(['id' => id_test('carte', $n)] + $s);
    $cartes = [
        $c(1, ['titre' => 'Campagne LinkedIn', 'echeance' => '2026-10-26T08:30:00+01:00', 'checklists' => [checklist_normalisee(1, [false, false, false, false, false])]]),
        $c(2, ['titre' => 'Rendre le TP', 'echeance' => '2026-10-26T12:00:00+01:00', 'checklists' => [checklist_normalisee(2, [true, false]), checklist_normalisee(3, [true])]]),
        $c(3, ['titre' => 'Tout coché', 'echeance' => '2026-10-26T12:00:00+01:00', 'checklists' => [checklist_normalisee(4, [true])]]),
        $c(4, ['titre' => 'Sans étapes', 'echeance' => '2026-10-26T09:00:00+01:00']),
        $c(5, ['colonne' => 'fait', 'echeance' => '2026-10-26T09:00:00+01:00']),
        $c(6, ['echeance' => '2026-10-26T09:00:00+01:00', 'echeance_faite' => true]),
        $c(7, []),
    ];
    $liste = generer_rappels($cartes, [], reglages_sans_brief(), [], utc_test('2026-10-25 08:00:00'));
    egal(4, count($liste));
    $cle = fn(int $n, string $echeance): string => 'echeance:' . id_test('carte', $n) . ":$echeance";
    $linkedin = rappel_cle($liste, $cle(1, '2026-10-26T08:30:00+01:00'));
    egal(['echeance', '2026-10-25 19:00:00', 'Demain : Campagne LinkedIn', 'Échéance à 8 h 30. Il reste 5 étapes sur 5.', '/#/carte/' . id_test('carte', 1)],
        [$linkedin['type'], utc($linkedin['prevu_pour']), $linkedin['titre'], $linkedin['texte'], $linkedin['url']]);
    egal('Échéance à 12 h. Il reste 1 étape sur 3.', rappel_cle($liste, $cle(2, '2026-10-26T12:00:00+01:00'))['texte']);
    egal('Échéance à 12 h. Toutes les étapes sont cochées.', rappel_cle($liste, $cle(3, '2026-10-26T12:00:00+01:00'))['texte']);
    egal('Échéance à 9 h.', rappel_cle($liste, $cle(4, '2026-10-26T09:00:00+01:00'))['texte']);
});

test('rappels : un médian prévient à J-7, J-3 et J-1 à 9 h, avec les textes de CONTENU.md ; un médian « journée entière » est ignoré', function () {
    $jours = jours_agenda([], [evenement_google('Médian MT23', '2026-10-21T19:15', '2026-10-21T20:45'), evenement_google('Médian NF93', '2026-10-22', '2026-10-23')], '2026-10-13', 15);
    $vus = [];
    foreach (['2026-10-13 12:00:00', '2026-10-17 12:00:00', '2026-10-19 12:00:00'] as $moment) {
        foreach (generer_rappels([], $jours, reglages_sans_brief(), [], utc_test($moment)) as $r) {
            $vus[$r['cle']] = [utc($r['prevu_pour']), $r['titre'], $r['texte'], $r['url']];
        }
    }
    ksort($vus);
    egal([
        'median:2026-10-21T19:15:00+02:00:J1' => ['2026-10-20 07:00:00', 'Médian MT23 demain', '19 h 15. Dors bien ce soir.', '/#/agenda'],
        'median:2026-10-21T19:15:00+02:00:J3' => ['2026-10-18 07:00:00', 'Médian MT23 dans 3 jours', 'Mercredi à 19 h 15.', '/#/agenda'],
        'median:2026-10-21T19:15:00+02:00:J7' => ['2026-10-14 07:00:00', 'Médian MT23 dans 7 jours', 'Mercredi 21/10 à 19 h 15.', '/#/agenda'],
    ], $vus);
});

test("rappels : le brief part à 7 h 30 à Paris la veille et le jour du passage à l'heure d'hiver, seulement les jours choisis", function () {
    $r = JR_REGLAGES_RAPPELS;
    $samedi = generer_rappels([], [], $r, [], utc_test('2026-10-24 07:00:00'));
    egal(['brief:2026-10-24', 'brief:2026-10-25'], cles_rappels($samedi));
    egal('2026-10-24 05:30:00', utc(rappel_cle($samedi, 'brief:2026-10-24')['prevu_pour']));
    egal('2026-10-25 06:30:00', utc(rappel_cle($samedi, 'brief:2026-10-25')['prevu_pour']));
    $brief = rappel_cle($samedi, 'brief:2026-10-24');
    egal(['brief', 'Samedi 24 octobre', '/#/aujourdhui'], [$brief['type'], $brief['titre'], $brief['url']]);
    $r['brief']['jours'] = [1, 2, 3, 4, 5];
    egal([], generer_rappels([], [], $r, [], utc_test('2026-10-24 07:00:00')));
});

test('rappels : un rappel manuel arrive à son heure, avec le jour où il a été mis', function () {
    $rappels = [
        ['id' => 1, 'texte' => 'Appeler Sébastien', 'quand' => utc_test('2026-10-14 08:00:00'), 'cree_le' => utc_test('2026-10-13 20:00:00')],
        ['id' => 2, 'texte' => 'Sortir le linge', 'quand' => utc_test('2026-10-14 09:00:00'), 'cree_le' => utc_test('2026-10-14 07:00:00')],
        ['id' => 3, 'texte' => "Payer l'URSSAF", 'quand' => utc_test('2026-10-14 10:00:00'), 'cree_le' => utc_test('2026-10-10 07:15:00')],
    ];
    $vus = [];
    foreach (generer_rappels([], [], reglages_sans_brief(), $rappels, utc_test('2026-10-14 06:00:00')) as $r) {
        $vus[$r['cle']] = [$r['type'], utc($r['prevu_pour']), $r['titre'], $r['texte'], $r['url']];
    }
    egal([
        'manuel:1' => ['manuel', '2026-10-14 08:00:00', 'Appeler Sébastien', "Rappel que tu t'es mis hier à 22 h.", '/#/aujourdhui'],
        'manuel:2' => ['manuel', '2026-10-14 09:00:00', 'Sortir le linge', "Rappel que tu t'es mis aujourd'hui à 9 h.", '/#/aujourdhui'],
        'manuel:3' => ['manuel', '2026-10-14 10:00:00', "Payer l'URSSAF", "Rappel que tu t'es mis le 10/10 à 9 h 15.", '/#/aujourdhui'],
    ], $vus);
});

test('rappels : seulement de 12 h avant à 24 h après le passage ; une clé une seule fois ; titre coupé à 120 caractères', function () {
    $rappel = fn(int $id, string $texte, string $quand): array => ['id' => $id, 'texte' => $texte, 'quand' => utc_test($quand), 'cree_le' => utc_test('2026-10-13 08:00:00')];
    $liste = generer_rappels([], [], reglages_sans_brief(), [
        $rappel(1, 'trop vieux', '2026-10-13 17:59:00'),
        $rappel(2, 'encore là', '2026-10-13 18:01:00'),
        $rappel(3, 'dans 24 h pile', '2026-10-15 06:00:00'),
        $rappel(4, 'trop tôt', '2026-10-15 06:01:00'),
        $rappel(2, 'doublon', '2026-10-14 08:00:00'),
        $rappel(5, str_repeat('long ', 100), '2026-10-14 08:00:00'),
    ], utc_test('2026-10-14 06:00:00'));
    egal(['manuel:2', 'manuel:3', 'manuel:5'], cles_rappels($liste));
    egal('encore là', rappel_cle($liste, 'manuel:2')['titre']);
    egal(120, mb_strlen(rappel_cle($liste, 'manuel:5')['titre']));
});

test('rappels : un réglage abîmé reprend sa valeur par défaut sans toucher aux autres ; une liste remplace la liste entière', function () {
    egal(JR_REGLAGES_RAPPELS, reglages_rappels_valides(null));
    egal(JR_REGLAGES_RAPPELS, reglages_rappels_valides('texte'));
    $r = reglages_rappels_valides([
        'brief' => ['heure' => '25:99', 'jours' => [1, 2]],
        'cours' => ['actif' => 'oui', 'minutes' => 30],
        'echeances' => 'x',
        'medians' => ['jours' => [3, 'x'], 'heure' => '08:00', 'actif' => false],
    ]);
    egal(['heure' => '07:30', 'jours' => [1, 2]], $r['brief']);
    egal(['actif' => true, 'minutes' => 30], $r['cours']);
    egal(JR_REGLAGES_RAPPELS['echeances'], $r['echeances']);
    egal(['actif' => false, 'jours' => [7, 3, 1], 'heure' => '08:00'], $r['medians']);
    egal([], reglages_rappels_valides(['brief' => ['jours' => []]])['brief']['jours']);
});

test('rappels : reglages_rappels() lit la clé « rappels » de la table reglages', function () {
    base_de_test();
    egal(JR_REGLAGES_RAPPELS, reglages_rappels());
    reglage_ecrire('rappels', ['cours' => ['minutes' => 20]]);
    egal(20, reglages_rappels()['cours']['minutes']);
});
