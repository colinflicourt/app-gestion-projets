<?php
declare(strict_types=1);

const UUID_TEST = '1b4e28ba-2fa1-41d2-883f-0016d3cca427';
const UUID_TEST_2 = '2c4e28ba-2fa1-41d2-883f-0016d3cca427';

test("écritures : la première fois l'action s'exécute, un renvoi rend le même résultat sans la refaire", function () {
    base_de_test();
    $appels = 0;
    $action = function () use (&$appels): array {
        $appels++;
        return ['carte' => 'abc', 'n' => $appels];
    };
    egal(['carte' => 'abc', 'n' => 1], ecriture_unique(UUID_TEST, 'taches/cocher', $action));
    // Une colonne JSON de MySQL range les clés d'un objet à sa façon : seules les paires comptent
    $renvoi = ecriture_unique(UUID_TEST, 'taches/cocher', $action);
    ksort($renvoi);
    egal(['carte' => 'abc', 'n' => 1], $renvoi);
    egal(1, $appels);
});

test("écritures : un identifiant qui n'est pas un UUID v4 en minuscules est refusé (400)", function () {
    base_de_test();
    foreach (['', 'abc', strtoupper(UUID_TEST), '1b4e28ba-2fa1-11d2-883f-0016d3cca427'] as $mauvais) {
        $e = leve(ErreurApi::class, fn() => ecriture_unique($mauvais, 'taches/cocher', fn(): array => []));
        egal(400, $e->statut);
    }
});

test("écritures : si l'action échoue, rien n'est gardé et un renvoi peut réessayer", function () {
    base_de_test();
    leve(RuntimeException::class, fn() => ecriture_unique(UUID_TEST, 'taches/cocher', function (): array {
        throw new RuntimeException('Trello ne répond pas');
    }));
    egal(['ok' => 1], ecriture_unique(UUID_TEST, 'taches/cocher', fn(): array => ['ok' => 1]));
});

test('écritures : ecriture_trouver rend la route et le résultat gardés', function () {
    base_de_test();
    fixer_heure('2026-10-08 10:00:00');
    ecriture_unique(UUID_TEST, 'taches/terminer', fn(): array => ['colonne_avant' => 'en_cours']);
    egal(['route' => 'taches/terminer', 'recu_le' => '2026-10-08 10:00:00', 'resultat' => ['colonne_avant' => 'en_cours']], ecriture_trouver(UUID_TEST));
    egal(null, ecriture_trouver(UUID_TEST_2));
});

test('écritures : au-delà de 30 jours, les anciennes écritures sont effacées', function () {
    base_de_test();
    fixer_heure('2026-10-08 10:00:00');
    ecriture_unique(UUID_TEST, 'taches/cocher', fn(): array => []);
    fixer_heure('2026-11-08 10:00:01');
    ecriture_unique(UUID_TEST_2, 'taches/cocher', fn(): array => []);
    egal(null, ecriture_trouver(UUID_TEST));
});

test("écritures : un identifiant déjà utilisé par une autre route est refusé (409), sans exécuter l'action", function () {
    base_de_test();
    ecriture_unique(UUID_TEST, 'taches/cocher', fn(): array => ['ok' => 1]);
    $appels = 0;
    $e = leve(ErreurApi::class, fn() => ecriture_unique(UUID_TEST, 'taches/terminer', function () use (&$appels): array {
        $appels++;
        return [];
    }));
    egal(409, $e->statut);
    egal('id_client_reutilise', $e->codeErreur);
    egal(0, $appels);
});
