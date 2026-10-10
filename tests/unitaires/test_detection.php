<?php
declare(strict_types=1);

function copie_detection(array ...$cartes): array
{
    return ['cartes' => $cartes];
}

test('détection : la toute première copie ne produit aucune action', function () {
    egal([], detecter_actions(null, copie_detection(carte_normalisee(['colonne' => 'fait']))));
});

test("détection : une carte entrée dans Fait donne carte_terminee, à l'heure de sa dernière activité, avec urgent et échéance", function () {
    $avant = carte_normalisee(['colonne' => 'en_cours', 'urgent' => true, 'echeance' => '2026-10-21T19:15:00+02:00']);
    $apres = ['colonne' => 'fait', 'derniere_activite' => '2026-10-09T14:05:00+02:00'] + $avant;
    $actions = detecter_actions(copie_detection($avant), copie_detection($apres));
    egal(1, count($actions));
    egal(['carte_terminee', 'etudes', id_test('carte', 1), null], [$actions[0]['type'], $actions[0]['domaine'], $actions[0]['carte_id'], $actions[0]['element_id']]);
    egal('2026-10-09 12:05:00', utc($actions[0]['quand']));
    egal(['urgent' => true, 'echeance' => '2026-10-21T19:15:00+02:00'], $actions[0]['donnees']);
});

test('détection : une carte apparue directement dans Fait (créée puis terminée entre deux copies) compte comme terminée', function () {
    $actions = detecter_actions(copie_detection(), copie_detection(carte_normalisee(['colonne' => 'fait'])));
    egal(['carte_terminee'], array_column($actions, 'type'));
});

test('détection : une carte sortie de Fait donne carte_rouverte', function () {
    $avant = carte_normalisee(['colonne' => 'fait']);
    egal(['carte_rouverte'], array_column(detecter_actions(copie_detection($avant), copie_detection(['colonne' => 'a_faire'] + $avant)), 'type'));
});

test('détection : une étape passée à cochée ou à décochée donne etape_cochee ou etape_decochee, avec son élément', function () {
    $avant = carte_normalisee(['checklists' => [checklist_normalisee(1, [false, true])]]);
    $apres = ['checklists' => [checklist_normalisee(1, [true, false])]] + $avant;
    $actions = detecter_actions(copie_detection($avant), copie_detection($apres));
    egal([['etape_cochee', id_test('element', 11)], ['etape_decochee', id_test('element', 12)]], array_map(fn(array $a): array => [$a['type'], $a['element_id']], $actions));
    egal([[], []], array_column($actions, 'donnees'));
});

test('détection : rien ne change, carte nouvelle hors de Fait, carte disparue, déplacement hors de Fait, étape nouvelle déjà cochée : aucune action', function () {
    $a = carte_normalisee(['id' => id_test('carte', 1), 'checklists' => [checklist_normalisee(1, [false])]]);
    $b = carte_normalisee(['id' => id_test('carte', 2)]);
    $c = carte_normalisee(['id' => id_test('carte', 3), 'colonne' => 'a_faire']);
    $a_apres = ['colonne' => 'en_attente', 'checklists' => [checklist_normalisee(1, [false, true])]] + $a;
    egal([], detecter_actions(copie_detection($a, $b), copie_detection($a, $b)));
    egal([], detecter_actions(copie_detection($a, $b), copie_detection($a_apres, $c)));
});
