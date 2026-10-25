<?php
declare(strict_types=1);

// Moteur d'XP (fonctions pures). Les tests des règles passent la table du § 7 de CONTENU.md ;
// seul le dernier test épingle JR_XP_REGLES : c'est lui qu'on modifie après la régulation.

function xpt_regles(): array
{
    return ['carte' => 30, 'bonus_urgent' => 20, 'bonus_echeance' => 10, 'carte_eclair' => 5, 'eclair_secondes' => 600, 'etape' => 5, 'pas_niveau' => 100,
        'grades' => ['stagiaire' => 0, 'assistant' => 1000, 'bras_droit' => 4000, 'majordome' => 10000]];
}

function xpt_paris(string $texte): DateTimeImmutable
{
    return new DateTimeImmutable($texte, new DateTimeZone('Europe/Paris'));
}

// Identifiant Trello dont les 8 premiers caractères sont l'horodatage de création (par défaut : vieille carte)
function xpt_carte(string $lettre = 'a', ?int $creation = null): string
{
    return sprintf('%08x', $creation ?? 1788000000) . str_repeat($lettre, 16);
}

function xpt_action(int $id, string $type, string $domaine, string $carte, ?string $element, DateTimeImmutable $quand, array $donnees = []): array
{
    return ['id' => $id, 'type' => $type, 'domaine' => $domaine, 'carte_id' => $carte, 'element_id' => $element, 'quand' => $quand, 'donnees' => $donnees];
}

function xpt_gain(array $donnees, string $quand, ?string $carte = null): int
{
    return xp_gain_carte($carte ?? xpt_carte(), $donnees, xpt_paris($quand), xpt_regles());
}

function xpt_evenements(array $rejeu, string $type): array
{
    return array_values(array_filter($rejeu['evenements'], fn(array $e): bool => $e['type'] === $type));
}

// Une carte terminée par jour (heure donnée), pour fabriquer des séries
function xpt_jours(array $jours, string $heure = '10:00:00', string $domaine = 'perso', int $premier_id = 1): array
{
    $actions = [];
    foreach ($jours as $i => $jour) {
        $actions[] = xpt_action($premier_id + $i, 'carte_terminee', $domaine, xpt_carte(chr(97 + $i % 26) . '', 1788000000 + $i), null, xpt_paris("$jour $heure"));
    }
    return $actions;
}

test('xp : barème de la carte (ordinaire, urgente, échéance tenue, les deux, échéance dépassée, sans échéance)', function () {
    egal(30, xpt_gain([], '2026-10-20 10:00:00'));
    egal(30, xpt_gain(['urgent' => false, 'echeance' => null], '2026-10-20 10:00:00'));
    egal(50, xpt_gain(['urgent' => true], '2026-10-20 10:00:00'));
    egal(40, xpt_gain(['echeance' => '2026-10-22T09:00:00+02:00'], '2026-10-20 10:00:00'));
    egal(60, xpt_gain(['urgent' => true, 'echeance' => '2026-10-22T09:00:00+02:00'], '2026-10-20 10:00:00'));
    egal(30, xpt_gain(['echeance' => '2026-10-19T09:00:00+02:00'], '2026-10-20 10:00:00'));
    egal(30, xpt_gain(['echeance' => 'pas une date'], '2026-10-20 10:00:00'));
});

test("xp : échéance posée sans heure (18 h 00) comparée au jour, autre heure comparée à la minute", function () {
    $echeance = '2026-10-20T18:00:00+02:00';
    egal(40, xpt_gain(['echeance' => $echeance], '2026-10-20 23:30:00'));
    egal(30, xpt_gain(['echeance' => $echeance], '2026-10-21 00:05:00'));
    egal(40, xpt_gain(['echeance' => $echeance], '2026-10-19 08:00:00'));
    egal(40, xpt_gain(['echeance' => '2026-10-20T14:00:00+02:00'], '2026-10-20 14:00:30'));
    egal(30, xpt_gain(['echeance' => '2026-10-20T14:00:00+02:00'], '2026-10-20 14:01:00'));
    egal(40, xpt_gain(['echeance' => '2026-10-20T18:30:00+02:00'], '2026-10-20 18:29:00'));
    egal(30, xpt_gain(['echeance' => '2026-10-20T18:30:00+02:00'], '2026-10-20 18:31:00'));
    // en heure d'hiver, 18 h 00 Paris s'écrit +01:00
    egal(40, xpt_gain(['echeance' => '2026-11-03T18:00:00+01:00'], '2026-11-03 23:30:00'));
    egal(30, xpt_gain(['echeance' => '2026-11-03T18:00:00+01:00'], '2026-11-04 00:05:00'));
});

test('xp : carte ajoutée et tirage au parti rapportent 0 et ne comptent pas le jour', function () {
    $a = xpt_paris('2026-10-20 10:00:00');
    $rejeu = xp_rejouer([
        xpt_action(1, 'carte_ajoutee', 'perso', xpt_carte(), null, $a),
        xpt_action(2, 'tirage_parti', 'perso', xpt_carte(), null, $a),
    ], xpt_paris('2026-10-20 12:00:00'), xpt_regles());
    egal(0, $rejeu['total']);
    egal([1 => 0, 2 => 0], $rejeu['gains']);
    egal([], $rejeu['jours']);
    egal(0, $rejeu['serie']['jours']);
});

test('xp : carte éclair, 9 min 59 s rapporte 5, 10 min 00 s rapporte 30, bonus perdus', function () {
    $creation = 1790000000;
    $carte = xpt_carte('b', $creation);
    $quand = fn(int $secondes): DateTimeImmutable => new DateTimeImmutable('@' . ($creation + $secondes));
    egal(5, xp_gain_carte($carte, [], $quand(599), xpt_regles()));
    egal(30, xp_gain_carte($carte, [], $quand(600), xpt_regles()));
    egal(5, xp_gain_carte($carte, ['urgent' => true, 'echeance' => '2099-01-01T18:00:00+01:00'], $quand(30), xpt_regles()));
    egal(50, xp_gain_carte($carte, ['urgent' => true], $quand(600), xpt_regles()));
});

test("xp : Focus 5, l'heure du geste antérieure à la création est une carte éclair, jamais un gain négatif", function () {
    $creation = 1790000000;
    $carte = xpt_carte('c', $creation);
    egal(5, xp_gain_carte($carte, [], new DateTimeImmutable('@' . ($creation - 3600)), xpt_regles()));
    egal(5, xp_gain_carte($carte, ['urgent' => true], new DateTimeImmutable('@' . ($creation - 1)), xpt_regles()));
});

test("xp : création lue dans l'identifiant, null si l'identifiant n'est pas Trello", function () {
    $creation = xp_creation_carte('671f2a80' . str_repeat('0', 16));
    vrai($creation instanceof DateTimeImmutable, 'date attendue');
    egal(0x671f2a80, $creation->getTimestamp());
    egal(null, xp_creation_carte('671f2a80' . str_repeat('0', 15)));
    egal(null, xp_creation_carte('local-12345'));
    egal(null, xp_creation_carte('671f2a8g' . str_repeat('0', 16)));
    egal(null, xp_creation_carte(''));
    // identifiant non Trello : pas de carte éclair
    egal(30, xp_gain_carte('local-12345', [], xpt_paris('2026-10-20 10:00:00'), xpt_regles()));
});

test('xp : cocher, décocher, recocher ne rapporte qu\'une fois (5, -5, 5, total 5)', function () {
    $carte = xpt_carte();
    $rejeu = xp_rejouer([
        xpt_action(1, 'etape_cochee', 'etudes', $carte, 'e1', xpt_paris('2026-10-20 10:00:00')),
        xpt_action(2, 'etape_decochee', 'etudes', $carte, 'e1', xpt_paris('2026-10-20 10:05:00')),
        xpt_action(3, 'etape_cochee', 'etudes', $carte, 'e1', xpt_paris('2026-10-20 10:10:00')),
    ], xpt_paris('2026-10-20 12:00:00'), xpt_regles());
    egal([1 => 5, 2 => -5, 3 => 5], $rejeu['gains']);
    egal(5, $rejeu['total']);
    egal(5, $rejeu['domaines']['etudes']);
});

test('xp : deux étapes différentes comptent chacune, la même étape cochée deux fois une seule', function () {
    $carte = xpt_carte();
    $rejeu = xp_rejouer([
        xpt_action(1, 'etape_cochee', 'asso', $carte, 'e1', xpt_paris('2026-10-20 10:00:00')),
        xpt_action(2, 'etape_cochee', 'asso', $carte, 'e2', xpt_paris('2026-10-20 10:01:00')),
        xpt_action(3, 'etape_cochee', 'asso', $carte, 'e1', xpt_paris('2026-10-20 10:02:00')),
        xpt_action(4, 'etape_cochee', 'asso', xpt_carte('d'), 'e1', xpt_paris('2026-10-20 10:03:00')),
    ], xpt_paris('2026-10-20 12:00:00'), xpt_regles());
    egal([1 => 5, 2 => 5, 3 => 0, 4 => 5], $rejeu['gains']);
    egal(15, $rejeu['total']);
});

test('xp : terminer, rouvrir, terminer rapporte 30, -30, 30 ; terminer deux fois de suite 30 puis 0', function () {
    $carte = xpt_carte();
    $rejeu = xp_rejouer([
        xpt_action(1, 'carte_terminee', 'qyris', $carte, null, xpt_paris('2026-10-20 10:00:00')),
        xpt_action(2, 'carte_rouverte', 'qyris', $carte, null, xpt_paris('2026-10-20 11:00:00')),
        xpt_action(3, 'carte_terminee', 'qyris', $carte, null, xpt_paris('2026-10-20 12:00:00')),
        xpt_action(4, 'carte_terminee', 'qyris', $carte, null, xpt_paris('2026-10-20 12:30:00')),
    ], xpt_paris('2026-10-20 13:00:00'), xpt_regles());
    egal([1 => 30, 2 => -30, 3 => 30, 4 => 0], $rejeu['gains']);
    egal(30, $rejeu['total']);
});

test("xp : la réouverture retire exactement ce que la fin avait rapporté, et la nouvelle fin recalcule les bonus", function () {
    $creation = 1790000000;
    $carte = xpt_carte('e', $creation);
    $rejeu = xp_rejouer([
        xpt_action(1, 'carte_terminee', 'etudes', $carte, null, new DateTimeImmutable('@' . ($creation + 60)), ['urgent' => true]),
        xpt_action(2, 'carte_rouverte', 'etudes', $carte, null, new DateTimeImmutable('@' . ($creation + 7200))),
        xpt_action(3, 'carte_terminee', 'etudes', $carte, null, new DateTimeImmutable('@' . ($creation + 7300)), ['urgent' => true]),
    ], new DateTimeImmutable('@' . ($creation + 8000)), xpt_regles());
    egal([1 => 5, 2 => -5, 3 => 50], $rejeu['gains'], 'éclair retiré pour 5, puis gain complet');
    egal(50, $rejeu['total']);
});

test('xp : plancher à 0 (retrait sans gain préalable) et action annulée absente de gains', function () {
    $carte = xpt_carte();
    $rejeu = xp_rejouer([
        xpt_action(1, 'etape_decochee', 'perso', $carte, 'e1', xpt_paris('2026-10-20 10:00:00')),
        xpt_action(2, 'carte_rouverte', 'perso', $carte, null, xpt_paris('2026-10-20 10:01:00')),
        xpt_action(4, 'etape_cochee', 'perso', $carte, 'e2', xpt_paris('2026-10-20 10:02:00')),
    ], xpt_paris('2026-10-20 12:00:00'), xpt_regles());
    egal([1 => 0, 2 => 0, 4 => 5], $rejeu['gains']);
    egal(5, $rejeu['total']);
    vrai(!array_key_exists(3, $rejeu['gains']), 'action 3 (annulée) absente');
    foreach ($rejeu['domaines'] as $domaine => $xp) {
        vrai($xp >= 0, "$domaine ne descend jamais sous 0");
    }
});

test('xp : les quatre domaines sont toujours présents, chaque gain va au domaine de son action', function () {
    $vide = xp_rejouer([], xpt_paris('2026-10-20 12:00:00'), xpt_regles());
    egal(['qyris' => 0, 'asso' => 0, 'etudes' => 0, 'perso' => 0], $vide['domaines']);
    egal(0, $vide['total']);
    $rejeu = xp_rejouer([
        xpt_action(1, 'carte_terminee', 'asso', xpt_carte(), null, xpt_paris('2026-10-20 10:00:00')),
        xpt_action(2, 'etape_cochee', 'qyris', xpt_carte('b'), 'e', xpt_paris('2026-10-20 10:01:00')),
    ], xpt_paris('2026-10-20 12:00:00'), xpt_regles());
    egal(['qyris' => 5, 'asso' => 30, 'etudes' => 0, 'perso' => 0], $rejeu['domaines']);
    egal(35, $rejeu['total']);
});

test('xp : seuils de niveau 1 à 13 (100 de plus à chaque niveau)', function () {
    $attendus = [0, 100, 300, 600, 1000, 1500, 2100, 2800, 3600, 4500, 5500, 6600, 7800];
    $obtenus = [];
    for ($n = 1; $n <= 13; $n++) {
        $obtenus[] = xp_seuil_niveau($n, xpt_regles());
    }
    egal($attendus, $obtenus);
});

test('xp : progression dans le niveau', function () {
    egal(['niveau' => 3, 'dans_niveau' => 120, 'taille_niveau' => 300], xp_progression(420, xpt_regles()));
    egal(['niveau' => 1, 'dans_niveau' => 0, 'taille_niveau' => 100], xp_progression(0, xpt_regles()));
    egal(['niveau' => 1, 'dans_niveau' => 99, 'taille_niveau' => 100], xp_progression(99, xpt_regles()));
    egal(['niveau' => 2, 'dans_niveau' => 0, 'taille_niveau' => 200], xp_progression(100, xpt_regles()));
    egal(['niveau' => 13, 'dans_niveau' => 0, 'taille_niveau' => 1300], xp_progression(7800, xpt_regles()));
    egal(['niveau' => 12, 'dans_niveau' => 1199, 'taille_niveau' => 1200], xp_progression(7799, xpt_regles()));
});

test('xp : grades aux seuils exacts', function () {
    egal(['grade' => 'stagiaire', 'seuil' => 0, 'suivant' => 'assistant', 'seuil_suivant' => 1000], xp_grade(0, xpt_regles()));
    egal('stagiaire', xp_grade(999, xpt_regles())['grade']);
    egal(['grade' => 'assistant', 'seuil' => 1000, 'suivant' => 'bras_droit', 'seuil_suivant' => 4000], xp_grade(1000, xpt_regles()));
    egal('assistant', xp_grade(3999, xpt_regles())['grade']);
    egal('bras_droit', xp_grade(4000, xpt_regles())['grade']);
    egal('bras_droit', xp_grade(9999, xpt_regles())['grade']);
    egal(['grade' => 'majordome', 'seuil' => 10000, 'suivant' => null, 'seuil_suivant' => null], xp_grade(10000, xpt_regles()));
    egal('majordome', xp_grade(250000, xpt_regles())['grade']);
});

test("xp : un niveau et une promotion émis par la même action ont le même emis_le, au format ISO Paris", function () {
    // 970 XP en Études (194 étapes) puis une carte : 1 000 XP, niveau 5 et grade Assistant
    $actions = [];
    for ($i = 1; $i <= 194; $i++) {
        $actions[] = xpt_action($i, 'etape_cochee', 'etudes', xpt_carte(), "e$i", xpt_paris('2026-10-19 10:00:00'));
    }
    $actions[] = xpt_action(500, 'carte_terminee', 'etudes', xpt_carte('b'), null, xpt_paris('2026-10-20 15:30:00'));
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-20 18:00:00'), xpt_regles());
    egal(1000, $rejeu['total']);
    $niveaux = array_values(array_filter(xpt_evenements($rejeu, 'niveau'), fn(array $e): bool => $e['niveau'] >= 5));
    egal(1, count($niveaux));
    egal('niveau:etudes:5', $niveaux[0]['cle']);
    egal('etudes', $niveaux[0]['domaine']);
    egal('2026-10-20T15:30:00+02:00', $niveaux[0]['emis_le']);
    egal('2026-10-20T15:30:00+02:00', $niveaux[0]['date']);
    $grades = xpt_evenements($rejeu, 'grade');
    egal(1, count($grades));
    egal('grade:assistant', $grades[0]['cle']);
    egal('assistant', $grades[0]['grade']);
    egal($niveaux[0]['emis_le'], $grades[0]['emis_le']);
});

test("xp : premier franchissement, un niveau perdu puis regagné n'est émis qu'une fois", function () {
    $actions = [];
    for ($i = 1; $i <= 19; $i++) {
        $actions[] = xpt_action($i, 'etape_cochee', 'etudes', xpt_carte(), "e$i", xpt_paris('2026-10-19 10:00:00'));
    }
    $carte = xpt_carte('b');
    $actions[] = xpt_action(100, 'carte_terminee', 'etudes', $carte, null, xpt_paris('2026-10-20 10:00:00'));
    $actions[] = xpt_action(101, 'carte_rouverte', 'etudes', $carte, null, xpt_paris('2026-10-20 11:00:00'));
    $actions[] = xpt_action(102, 'carte_terminee', 'etudes', $carte, null, xpt_paris('2026-10-20 12:00:00'));
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-20 18:00:00'), xpt_regles());
    egal(125, $rejeu['domaines']['etudes']);
    $niveaux = xpt_evenements($rejeu, 'niveau');
    egal(['niveau:etudes:2'], array_column($niveaux, 'cle'));
    egal('2026-10-20T10:00:00+02:00', $niveaux[0]['emis_le']);
});

test('xp : chaque niveau franchi donne son propre événement, dans l\'ordre', function () {
    $actions = [];
    for ($i = 1; $i <= 12; $i++) {
        $actions[] = xpt_action($i, 'carte_terminee', 'perso', xpt_carte(chr(96 + $i)), null, xpt_paris('2026-10-20 10:00:00'), ['urgent' => true]);
    }
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-20 18:00:00'), xpt_regles());
    egal(600, $rejeu['total']);
    egal(['niveau:perso:2', 'niveau:perso:3', 'niveau:perso:4'], array_column(xpt_evenements($rejeu, 'niveau'), 'cle'));
});

test('xp : série de 3 jours jusqu\'à aujourd\'hui, journée en cours vide sans casser, étape cochée puis décochée le même jour', function () {
    $trois = xpt_jours(['2026-10-18', '2026-10-19', '2026-10-20']);
    $rejeu = xp_rejouer($trois, xpt_paris('2026-10-20 18:00:00'), xpt_regles());
    egal(['2026-10-18', '2026-10-19', '2026-10-20'], $rejeu['jours']);
    egal(['jours' => 3, 'joker_libre' => true, 'dernier_joker' => null, 'en_jeu' => false], $rejeu['serie']);

    $rejeu = xp_rejouer($trois, xpt_paris('2026-10-21 09:00:00'), xpt_regles());
    egal(['jours' => 3, 'joker_libre' => true, 'dernier_joker' => null, 'en_jeu' => false], $rejeu['serie']);

    $carte = xpt_carte('z');
    $rejeu = xp_rejouer([
        xpt_action(1, 'etape_cochee', 'perso', $carte, 'e', xpt_paris('2026-10-20 09:00:00')),
        xpt_action(2, 'etape_decochee', 'perso', $carte, 'e', xpt_paris('2026-10-20 17:00:00')),
    ], xpt_paris('2026-10-20 18:00:00'), xpt_regles());
    egal([], $rejeu['jours']);
    egal(0, $rejeu['serie']['jours']);
});

test("xp : défaire un geste un autre jour retire l'XP mais pas le jour de la série", function () {
    $carte = xpt_carte('z');
    $rejeu = xp_rejouer([
        xpt_action(1, 'etape_cochee', 'perso', $carte, 'e', xpt_paris('2026-10-19 09:00:00')),
        xpt_action(2, 'etape_decochee', 'perso', $carte, 'e', xpt_paris('2026-10-20 09:00:00')),
    ], xpt_paris('2026-10-20 18:00:00'), xpt_regles());
    egal(['2026-10-19'], $rejeu['jours']);
    egal(0, $rejeu['total']);
    egal(1, $rejeu['serie']['jours']);
});

test("xp : une cochée-décochée-recochée le même jour compte le jour, et l'événement joker vise la première action comptée restante", function () {
    $carte = xpt_carte('z');
    $rejeu = xp_rejouer([
        xpt_action(1, 'etape_cochee', 'perso', $carte, 'e', xpt_paris('2026-10-19 09:00:00')),
        xpt_action(2, 'etape_decochee', 'perso', $carte, 'e', xpt_paris('2026-10-19 09:30:00')),
        xpt_action(3, 'etape_cochee', 'perso', $carte, 'e', xpt_paris('2026-10-19 10:00:00')),
    ], xpt_paris('2026-10-19 18:00:00'), xpt_regles());
    egal(['2026-10-19'], $rejeu['jours']);
});

test("xp : Focus 1, un jour manqué avec le joker libre ne casse pas la série, le joker est émis à la première action du jour suivant", function () {
    // lundi 19, mardi 20 manqué, mercredi 21 (premières actions à 08:15 puis 14:00)
    $actions = xpt_jours(['2026-10-19']);
    $actions[] = xpt_action(10, 'carte_terminee', 'perso', xpt_carte('m', 1788000100), null, xpt_paris('2026-10-21 08:15:00'));
    $actions[] = xpt_action(11, 'carte_terminee', 'perso', xpt_carte('n', 1788000200), null, xpt_paris('2026-10-21 14:00:00'));
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-21 20:00:00'), xpt_regles());
    egal(['2026-10-19', '2026-10-21'], $rejeu['jours']);
    egal(2, $rejeu['serie']['jours']);
    egal(false, $rejeu['serie']['joker_libre']);
    egal('2026-10-20', $rejeu['serie']['dernier_joker']);
    egal(false, $rejeu['serie']['en_jeu']);
    $jokers = xpt_evenements($rejeu, 'joker');
    egal(1, count($jokers));
    egal('joker:2026-10-20', $jokers[0]['cle']);
    egal('2026-10-20', $jokers[0]['date']);
    egal('2026-10-21T08:15:00+02:00', $jokers[0]['emis_le']);
});

test("xp : hier manqué et aujourd'hui vide, la série est en jeu et aucun joker n'est émis", function () {
    $actions = xpt_jours(['2026-10-18', '2026-10-19']);
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-21 09:00:00'), xpt_regles());
    egal(['jours' => 2, 'joker_libre' => true, 'dernier_joker' => null, 'en_jeu' => true], $rejeu['serie']);
    egal([], xpt_evenements($rejeu, 'joker'));
});

test("xp : hier manqué dans une semaine dont le joker est libre, mais jamais de série en cours, rien n'est en jeu", function () {
    $rejeu = xp_rejouer([], xpt_paris('2026-10-21 09:00:00'), xpt_regles());
    egal(['jours' => 0, 'joker_libre' => true, 'dernier_joker' => null, 'en_jeu' => false], $rejeu['serie']);
});

test('xp : deux jours manqués d\'affilée cassent la série', function () {
    $actions = xpt_jours(['2026-10-19']);
    $actions[] = xpt_action(10, 'carte_terminee', 'perso', xpt_carte('m', 1788000100), null, xpt_paris('2026-10-22 10:00:00'));
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-22 20:00:00'), xpt_regles());
    egal(1, $rejeu['serie']['jours']);
    egal([], xpt_evenements($rejeu, 'joker'));
    egal(false, $rejeu['serie']['en_jeu']);
});

test('xp : un jour manqué dans une semaine dont le joker est déjà pris casse la série', function () {
    // lun 19 compté, mar 20 manqué (joker), mer 21 compté, jeu 22 manqué : joker déjà pris, série cassée
    $actions = xpt_jours(['2026-10-19', '2026-10-21', '2026-10-23']);
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-23 20:00:00'), xpt_regles());
    egal(1, $rejeu['serie']['jours']);
    egal('2026-10-20', $rejeu['serie']['dernier_joker']);
    egal(['joker:2026-10-20'], array_column(xpt_evenements($rejeu, 'joker'), 'cle'));
});

test("xp : le jour manqué cassé n'est pas rattrapé quand la série en jeu a son joker déjà pris", function () {
    // mer 21 compté après joker du mardi, jeudi 22 manqué, aujourd'hui vendredi 23 vide : cassée sans attendre
    $actions = xpt_jours(['2026-10-19', '2026-10-21']);
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-23 09:00:00'), xpt_regles());
    egal(0, $rejeu['serie']['jours']);
    egal(false, $rejeu['serie']['en_jeu']);
});

test('xp : le joker se libère chaque lundi, la semaine du jour manqué compte', function () {
    // sam 17 compté, dim 18 manqué (semaine du 12), lun 19 compté : joker de la semaine du 12 pris, libre dans la semaine du 19
    $actions = xpt_jours(['2026-10-17', '2026-10-19']);
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-19 20:00:00'), xpt_regles());
    egal(2, $rejeu['serie']['jours']);
    egal('2026-10-18', $rejeu['serie']['dernier_joker']);
    egal(true, $rejeu['serie']['joker_libre'], 'nouvelle semaine, joker libre');
    egal('2026-10-19T10:00:00+02:00', xpt_evenements($rejeu, 'joker')[0]['emis_le']);
});

test('xp : la série commence au premier jour compté, les jours d\'avant ne sont pas manqués', function () {
    $actions = xpt_jours(['2026-10-20']);
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-20 20:00:00'), xpt_regles());
    egal(1, $rejeu['serie']['jours']);
    egal([], xpt_evenements($rejeu, 'joker'));
});

test("xp : passage à l'heure d'hiver du 25/10/2026, samedi, dimanche et lundi comptés sans trou ni doublon", function () {
    $actions = [
        xpt_action(1, 'carte_terminee', 'perso', xpt_carte('a', 1788000001), null, xpt_paris('2026-10-24 12:00:00')),
        // 00 h 30 le dimanche, encore en heure d'été (22 h 30 UTC le samedi)
        xpt_action(2, 'carte_terminee', 'perso', xpt_carte('b', 1788000002), null, xpt_paris('2026-10-25 00:30:00')),
        // 23 h 30 le dimanche, en heure d'hiver (22 h 30 UTC le dimanche)
        xpt_action(3, 'carte_terminee', 'perso', xpt_carte('c', 1788000003), null, xpt_paris('2026-10-25 23:30:00')),
        xpt_action(4, 'carte_terminee', 'perso', xpt_carte('d', 1788000004), null, xpt_paris('2026-10-26 00:10:00')),
    ];
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-26 08:00:00'), xpt_regles());
    egal(['2026-10-24', '2026-10-25', '2026-10-26'], $rejeu['jours']);
    egal(3, $rejeu['serie']['jours']);
    egal([], xpt_evenements($rejeu, 'joker'));

    // dimanche 25 sans action : un seul jour manqué, couvert par le joker, pas deux
    $sans_dimanche = [$actions[0], $actions[3]];
    $rejeu = xp_rejouer($sans_dimanche, xpt_paris('2026-10-26 08:00:00'), xpt_regles());
    egal(['2026-10-24', '2026-10-26'], $rejeu['jours']);
    egal(2, $rejeu['serie']['jours']);
    egal(['joker:2026-10-25'], array_column(xpt_evenements($rejeu, 'joker'), 'cle'));
});

test('xp : aujourdhui (Paris) est rendu dans le rejeu', function () {
    $rejeu = xp_rejouer([], xpt_paris('2026-10-26 00:10:00'), xpt_regles());
    egal('2026-10-26', $rejeu['aujourdhui']);
    $rejeu = xp_rejouer([], new DateTimeImmutable('2026-10-25 23:10:00', new DateTimeZone('UTC')), xpt_regles());
    egal('2026-10-26', $rejeu['aujourdhui']);
});

test("xp : l'objet public (grade, domaines, série, événements) et le filtre des jokers à 30 jours", function () {
    $actions = [];
    for ($i = 1; $i <= 19; $i++) {
        $actions[] = xpt_action($i, 'etape_cochee', 'etudes', xpt_carte(), "e$i", xpt_paris('2026-08-03 10:00:00'));
    }
    // vieux joker : lundi 3 août compté, mardi 4 manqué, mercredi 5 compté
    $actions[] = xpt_action(50, 'carte_terminee', 'etudes', xpt_carte('b', 1780000000), null, xpt_paris('2026-08-05 10:00:00'));
    // joker récent : mardi 20 octobre
    $actions[] = xpt_action(60, 'carte_terminee', 'asso', xpt_carte('c'), null, xpt_paris('2026-10-19 10:00:00'));
    $actions[] = xpt_action(61, 'carte_terminee', 'asso', xpt_carte('d'), null, xpt_paris('2026-10-21 10:00:00'));
    $rejeu = xp_rejouer($actions, xpt_paris('2026-10-21 20:00:00'), xpt_regles());
    egal(['joker:2026-08-04', 'joker:2026-10-20'], array_column(xpt_evenements($rejeu, 'joker'), 'cle'));

    $objet = xp_objet($rejeu, xpt_regles());
    egal(array_keys($objet), ['total', 'grade', 'seuil_grade', 'grade_suivant', 'seuil_suivant', 'domaines', 'serie', 'evenements']);
    egal(95 + 30 + 60, $objet['total']);
    egal('stagiaire', $objet['grade']);
    egal(0, $objet['seuil_grade']);
    egal('assistant', $objet['grade_suivant']);
    egal(1000, $objet['seuil_suivant']);
    egal(['xp' => 125, 'niveau' => 2, 'dans_niveau' => 25, 'taille_niveau' => 200], $objet['domaines']['etudes']);
    egal(['xp' => 60, 'niveau' => 1, 'dans_niveau' => 60, 'taille_niveau' => 100], $objet['domaines']['asso']);
    egal(['xp' => 0, 'niveau' => 1, 'dans_niveau' => 0, 'taille_niveau' => 100], $objet['domaines']['qyris']);
    egal($rejeu['serie'], $objet['serie']);
    $cles = array_column($objet['evenements'], 'cle');
    vrai(in_array('niveau:etudes:2', $cles, true), 'niveau conservé');
    vrai(in_array('joker:2026-10-20', $cles, true), 'joker récent conservé');
    vrai(!in_array('joker:2026-08-04', $cles, true), 'joker de plus de 30 jours retiré');
});

test('xp : 3 000 actions rejouées en moins de 50 ms', function () {
    $actions = [];
    $debut = xpt_paris('2025-09-01 08:00:00');
    $domaines = JR_DOMAINES;
    for ($i = 1; $i <= 3000; $i++) {
        $quand = $debut->modify('+' . ($i * 175) . ' minutes');
        $carte = xpt_carte(chr(97 + $i % 6), 1788000000 + $i);
        $type = ['etape_cochee', 'etape_cochee', 'carte_terminee', 'etape_decochee', 'carte_rouverte'][$i % 5];
        $actions[] = xpt_action($i, $type, $domaines[$i % 4], $carte, $type[0] === 'e' ? 'e' . ($i % 40) : null, $quand,
            $type === 'carte_terminee' ? ['urgent' => $i % 3 === 0, 'echeance' => '2026-10-20T18:00:00+02:00'] : []);
    }
    $maintenant = $debut->modify('+13 months');
    $depart = hrtime(true);
    $rejeu = xp_rejouer($actions, $maintenant, xpt_regles());
    $objet = xp_objet($rejeu, xpt_regles());
    $ms = (hrtime(true) - $depart) / 1e6;
    echo sprintf("         (3 000 actions : %.1f ms)\n", $ms);
    vrai($rejeu['total'] > 0 && count($rejeu['gains']) === 3000 && $objet['total'] === $rejeu['total'], 'rejeu cohérent');
    vrai($ms < 50, sprintf('trop lent : %.1f ms', $ms));
});

test('xp : JR_XP_REGLES vaut exactement la table du § 7 (à modifier avec le barème validé)', function () {
    egal(['carte' => 30, 'bonus_urgent' => 20, 'bonus_echeance' => 10, 'carte_eclair' => 5, 'eclair_secondes' => 600, 'etape' => 5, 'pas_niveau' => 100,
        'grades' => ['stagiaire' => 0, 'assistant' => 1000, 'bras_droit' => 4000, 'majordome' => 10000]], JR_XP_REGLES);
});

test('xp : sans table passée en paramètre, les fonctions utilisent JR_XP_REGLES', function () {
    egal(30, xp_gain_carte(xpt_carte(), [], xpt_paris('2026-10-20 10:00:00')));
    egal(300, xp_seuil_niveau(3));
    egal('assistant', xp_grade(1000)['grade']);
    egal(3, xp_progression(420)['niveau']);
    egal(0, xp_rejouer([], xpt_paris('2026-10-20 10:00:00'))['total']);
});
