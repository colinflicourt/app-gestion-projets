<?php
declare(strict_types=1);

function verrou_libre(string $source): bool
{
    // Une autre connexion arrive-t-elle à prendre le verrou tout de suite ?
    return (int) nouvelle_connexion_test()->query("SELECT GET_LOCK('jr_source_$source', 0)")->fetchColumn() === 1;
}

test('copies : écrire puis lire rend le contenu, l\'heure en UTC et l\'empreinte du JSON', function () {
    $pdo = base_de_test();
    copie_ecrire('essai', ['n' => 1, 'mot' => 'été'], utc_test('2026-10-09 10:00:00'));
    $copie = copie_lire('essai');
    egal(['n' => 1, 'mot' => 'été'], $copie['contenu']);
    egal('2026-10-09 10:00:00', utc($copie['recupere_le']));
    egal(hash('sha256', '{"n":1,"mot":"été"}'), $copie['empreinte']);
    egal(null, copie_lire('autre'));
    copie_ecrire('essai', ['n' => 2], utc_test('2026-10-09 10:05:00'));
    egal(1, (int) $pdo->query('SELECT COUNT(*) FROM copies')->fetchColumn());
});

test("rafraîchissement : sans copie, la source est lue et gardée avec l'heure du début de la lecture", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    $r = rafraichir('essai', 60, function (): array {
        fixer_heure('2026-10-09 10:00:05');  // la lecture prend 5 s
        return ['n' => 1];
    });
    egal(['n' => 1], $r['contenu']);
    egal(null, $r['erreur']);
    egal('2026-10-09 10:00:00', utc($r['recupere_le']));
    egal('2026-10-09 10:00:00', utc(copie_lire('essai')['recupere_le']));
    vrai(verrou_libre('essai'), 'verrou rendu');
});

test('rafraîchissement : une copie de moins de $age_max secondes est rendue sans lire la source', function () {
    base_de_test();
    copie_ecrire('essai', ['n' => 1], utc_test('2026-10-09 10:00:00'));
    fixer_heure('2026-10-09 10:00:59');
    $r = rafraichir('essai', 60, fn(): array => throw new EchecTest('la source ne doit pas être lue'));
    egal(['n' => 1], $r['contenu']);
    egal(null, $r['erreur']);
});

test('rafraîchissement : une copie trop vieille est relue ; $apres reçoit l\'ancienne copie, la nouvelle et l\'heure de l\'ancienne', function () {
    base_de_test();
    copie_ecrire('essai', ['n' => 1], utc_test('2026-10-09 10:00:00'));
    fixer_heure('2026-10-09 10:01:00');
    $vu = null;
    $r = rafraichir('essai', 60, fn(): array => ['n' => 2], function (?array $avant, array $nouveau, ?DateTimeImmutable $depuis) use (&$vu): void {
        $vu = [$avant, $nouveau, utc($depuis)];
    });
    egal(['n' => 2], $r['contenu']);
    egal([['n' => 1], ['n' => 2], '2026-10-09 10:00:00'], $vu);
});

test('rafraîchissement : $encore_valide peut refuser une copie fraîche', function () {
    base_de_test();
    copie_ecrire('essai', ['premier' => '2026-10-08'], utc_test('2026-10-09 10:00:00'));
    fixer_heure('2026-10-09 10:00:10');
    $r = rafraichir('essai', 600, fn(): array => ['premier' => '2026-10-09'], null, fn(array $c): bool => $c['premier'] === '2026-10-09');
    egal(['premier' => '2026-10-09'], $r['contenu']);
});

test("rafraîchissement : si la source échoue, la dernière copie revient avec son heure et le code, une ligne de journal, le verrou rendu", function () {
    base_de_test();
    copie_ecrire('essai', ['n' => 1], utc_test('2026-10-09 10:00:00'));
    fixer_heure('2026-10-09 10:05:00');
    $r = rafraichir('essai', 60, fn(): array => throw new ErreurSource('trello_indisponible', 'Trello : délai dépassé'));
    egal([['n' => 1], '2026-10-09 10:00:00', 'trello_indisponible'], [$r['contenu'], utc($r['recupere_le']), $r['erreur']]);
    egal('2026-10-09 10:00:00', utc(copie_lire('essai')['recupere_le']));
    vrai(str_contains((string) file_get_contents(dossier_data() . '/erreurs.log'), '| rafraichir/essai | ErreurSource | Trello : délai dépassé'), 'ligne de journal attendue');
    vrai(verrou_libre('essai'), 'verrou rendu');
});

test("rafraîchissement : sans copie et source en panne, contenu nul et code d'erreur", function () {
    base_de_test();
    egal(['contenu' => null, 'recupere_le' => null, 'erreur' => 'google_deconnecte'], rafraichir('essai', 60, fn(): array => throw new ErreurSource('google_deconnecte', 'Google : pas connecté')));
});

test('rafraîchissement : une autre exception remonte, et le verrou est rendu', function () {
    base_de_test();
    leve(RuntimeException::class, fn() => rafraichir('essai', 60, fn(): array => throw new RuntimeException('bogue')));
    vrai(verrou_libre('essai'), 'verrou rendu');
});

test("verrous : celui d'une source tenu par une autre connexion fait attendre, puis échoue proprement", function () {
    base_de_test();
    $autre = nouvelle_connexion_test();
    $autre->query("SELECT GET_LOCK('jr_source_trello', 0)");
    leve(RuntimeException::class, fn() => verrou_source_prendre('trello', 0));
    $autre->query("SELECT RELEASE_LOCK('jr_source_trello')");
    verrou_source_prendre('trello', 0);
    verrou_source_rendre('trello');
});

test("copies : copie_modifier corrige le contenu sans changer l'heure ; sans copie, rien ne se passe", function () {
    base_de_test();
    copie_modifier('essai', fn(array $c): array => throw new EchecTest('pas de copie, pas d\'appel'));
    egal(null, copie_lire('essai'));
    copie_ecrire('essai', ['n' => 1], utc_test('2026-10-09 10:00:00'));
    fixer_heure('2026-10-09 10:30:00');
    copie_modifier('essai', fn(array $c): array => ['n' => $c['n'] + 1]);
    egal(['n' => 2], copie_lire('essai')['contenu']);
    egal('2026-10-09 10:00:00', utc(copie_lire('essai')['recupere_le']));
    vrai(verrou_libre('essai'), 'verrou rendu');
});

test("copies : écrire une semaine d'agenda efface les semaines lues il y a plus de 30 jours, jamais trello ni agenda", function () {
    $pdo = base_de_test();
    copie_ecrire('trello', ['cartes' => []], utc_test('2026-08-01 10:00:00'));
    copie_ecrire('agenda', ['jours' => []], utc_test('2026-08-01 10:00:00'));
    copie_ecrire('agenda:2026-W31', ['jours' => []], utc_test('2026-08-01 10:00:00'));
    copie_ecrire('agenda:2026-W40', ['jours' => []], utc_test('2026-10-01 10:00:00'));
    copie_ecrire('agenda:2026-W42', ['jours' => []], utc_test('2026-10-10 10:00:00'));
    egal(['agenda', 'agenda:2026-W40', 'agenda:2026-W42', 'trello'], $pdo->query('SELECT source FROM copies ORDER BY source')->fetchAll(PDO::FETCH_COLUMN));
});
