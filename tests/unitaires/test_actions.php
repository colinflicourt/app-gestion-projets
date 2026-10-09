<?php
declare(strict_types=1);

test("actions : noter garde le type, le domaine, l'heure en UTC, la source, la précision et les données", function () {
    $pdo = base_de_test();
    $quand = new DateTimeImmutable('2026-10-08 18:30:00', new DateTimeZone('Europe/Paris'));
    $id = action_noter('carte_terminee', 'etudes', str_repeat('a', 24), null, $quand, 'app', 'exacte', ['urgent' => true]);
    $ligne = $pdo->query("SELECT * FROM actions WHERE id = $id")->fetch();
    egal('2026-10-08 16:30:00', $ligne['quand']);
    egal(['carte_terminee', 'etudes', 'app', 'exacte', 0], [$ligne['type'], $ligne['domaine'], $ligne['source'], $ligne['precision_heure'], (int) $ligne['annulee']]);
    egal(null, $ligne['element_id']);
    egal(['urgent' => true], json_decode($ligne['donnees'], true));
});

test('actions : un type, un domaine, une source ou une précision inconnus sont refusés', function () {
    base_de_test();
    $quand = new DateTimeImmutable('2026-10-08 10:00:00', new DateTimeZone('UTC'));
    $carte = str_repeat('a', 24);
    leve(InvalidArgumentException::class, fn() => action_noter('carte_volee', 'etudes', $carte, null, $quand, 'app', 'exacte'));
    leve(InvalidArgumentException::class, fn() => action_noter('carte_terminee', 'courses', $carte, null, $quand, 'app', 'exacte'));
    leve(InvalidArgumentException::class, fn() => action_noter('carte_terminee', 'etudes', $carte, null, $quand, 'jarvis', 'exacte'));
    leve(InvalidArgumentException::class, fn() => action_noter('carte_terminee', 'etudes', $carte, null, $quand, 'app', 'environ'));
});

test("actions : action_app_existe_depuis ne voit que les actions de l'app, non annulées, assez récentes, du même élément", function () {
    base_de_test();
    $carte = str_repeat('b', 24);
    $element = str_repeat('c', 24);
    $t = fn(string $heure): DateTimeImmutable => new DateTimeImmutable($heure, new DateTimeZone('UTC'));
    $id = action_noter('etape_cochee', 'qyris', $carte, $element, $t('2026-10-08 10:00:00'), 'app', 'exacte');
    action_noter('carte_terminee', 'qyris', $carte, null, $t('2026-10-08 10:00:00'), 'trello', 'approx');
    vrai(action_app_existe_depuis('etape_cochee', $carte, $element, $t('2026-10-08 09:59:00')), 'action de l\'app attendue');
    vrai(!action_app_existe_depuis('etape_cochee', $carte, $element, $t('2026-10-08 10:01:00')), 'trop ancienne');
    vrai(!action_app_existe_depuis('etape_cochee', $carte, null, $t('2026-10-08 09:59:00')), 'autre élément');
    vrai(!action_app_existe_depuis('carte_terminee', $carte, null, $t('2026-10-08 09:59:00')), 'source trello ignorée');
    action_annuler($id);
    vrai(!action_app_existe_depuis('etape_cochee', $carte, $element, $t('2026-10-08 09:59:00')), 'annulée ignorée');
});
