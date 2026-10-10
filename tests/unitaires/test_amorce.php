<?php
declare(strict_types=1);

test("heure : utc() convertit l'heure de Paris, avant et après le passage à l'heure d'hiver", function () {
    $paris = new DateTimeZone('Europe/Paris');
    egal('2026-10-24 10:00:00', utc(new DateTimeImmutable('2026-10-24 12:00:00', $paris)));
    egal('2026-10-26 11:00:00', utc(new DateTimeImmutable('2026-10-26 12:00:00', $paris)));
});

test('heure : depuis_utc relit le format de la base et refuse le reste', function () {
    egal('2026-10-08T13:00:00+00:00', depuis_utc('2026-10-08 13:00:00')->format(DATE_ATOM));
    leve(InvalidArgumentException::class, fn() => depuis_utc('08/10/2026'));
});

test('heure : maintenant() peut être fixé par les tests', function () {
    fixer_heure('2026-10-08 06:00:00');
    egal('2026-10-08 06:00:00', utc(maintenant()));
});

test('config : config() lit la configuration en cours', function () {
    egal('test', config('mode'));
    egal(null, config('inexistante'));
});

test('journal : une ligne par erreur, sans retour à la ligne, route nettoyée', function () {
    fixer_heure('2026-10-08 06:00:00');
    journal_erreur("taches/cocher\n<script>", new RuntimeException("ligne 1\nligne 2"));
    $contenu = file_get_contents(dossier_data() . '/erreurs.log');
    egal(1, substr_count($contenu, "\n"));
    vrai(str_starts_with($contenu, '2026-10-08 06:00:00 | taches/cocher??script? | RuntimeException | ligne 1 ligne 2 | '), $contenu);
});

test('journal : un message PDO (qui peut citer des valeurs) est réduit à son code SQLSTATE', function () {
    journal_erreur('connexion', new PDOException("Duplicate entry 'contenu perso' for key", 23000));
    $contenu = file_get_contents(dossier_data() . '/erreurs.log');
    vrai(!str_contains($contenu, 'contenu perso'), 'le message PDO ne doit pas être journalisé');
    vrai(str_contains($contenu, 'SQLSTATE 23000'), $contenu);
});

test('journal : au-delà de 1 Mo, le journal passe en .1 et repart de zéro', function () {
    $fichier = dossier_data() . '/erreurs.log';
    file_put_contents($fichier, str_repeat('x', JR_JOURNAL_MAX + 1));
    journal_erreur('sante', new RuntimeException('après rotation'));
    vrai(is_file($fichier . '.1'), 'erreurs.log.1 attendu');
    vrai(filesize($fichier) < 1000, 'nouveau journal attendu');
});

test('erreurs PHP : un avertissement devient une exception, mais pas une dépréciation ni une erreur masquée par @', function () {
    leve(ErrorException::class, fn() => erreur_php(E_WARNING, 'avertissement', __FILE__, __LINE__));
    egal(false, erreur_php(E_DEPRECATED, 'dépréciation', __FILE__, __LINE__));
    egal(false, erreur_php(E_USER_DEPRECATED, 'dépréciation', __FILE__, __LINE__));
    set_error_handler('erreur_php');
    try {
        egal(false, @file_get_contents(dossier_data() . '/absent.txt'));
    } finally {
        restore_error_handler();
    }
});

test("amorce : un journal impossible à écrire ne lève rien, même quand les avertissements deviennent des exceptions", function () {
    $GLOBALS['JR_DOSSIER_DATA'] = fichier_temporaire('un fichier, pas un dossier');
    set_error_handler('erreur_php');
    try {
        journal_erreur('essai', new RuntimeException('panne'));
    } finally {
        restore_error_handler();
    }
    vrai(true, 'aucune exception attendue');
});

test("amorce : iso_paris donne l'heure de Paris avec son décalage, de part et d'autre du 25/10/2026", function () {
    egal('2026-10-24T10:00:00+02:00', iso_paris(utc_test('2026-10-24 08:00:00')));
    egal('2026-10-25T10:00:00+01:00', iso_paris(utc_test('2026-10-25 09:00:00')));
    egal('2026-10-21T19:15:00+02:00', iso_paris(new DateTimeImmutable('2026-10-21T17:15:00.000Z')));
    fixer_heure('2026-10-24 22:30:00');  // 0 h 30 le 25 à Paris
    egal('2026-10-25T00:00:00+02:00', iso_paris(aujourdhui_paris()));
});

test('amorce : normaliser_texte compare sans casse, accents ni espaces en trop', function () {
    egal('ferie', normaliser_texte('  FÉRIÉ '));
    egal("jour d'examen", normaliser_texte('Jour d’examen'));
    egal('utc · emploi du temps a26', normaliser_texte("UTC  ·  Emploi du temps\tA26"));
    egal('median mt23', normaliser_texte('Médian MT23'));
});
