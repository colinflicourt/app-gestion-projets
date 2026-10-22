<?php
declare(strict_types=1);

const JR_TABLES = ['abonnements', 'actions', 'copies', 'ecritures', 'envois', 'essais_connexion', 'jetons', 'rappels', 'reglages', 'sessions', 'siri_appels', 'tentatives'];

test('base : les migrations créent les 12 tables (lot 1, abonnements, rappels, envois et siri_appels du lot 2) et notent la version', function () {
    $pdo = base_de_test();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    egal(JR_TABLES, $tables);
    egal(5, version_base($pdo));
    egal(5, version_attendue());
});

test('base : relancer les migrations ne change rien', function () {
    $pdo = base_de_test();
    egal(5, migrer($pdo));
    egal(5, version_base($pdo));
});

test('base : une base vide est en version 0', function () {
    $pdo = base_de_test();
    foreach (JR_TABLES as $table) {
        $pdo->exec("DROP TABLE `$table`");
    }
    egal(0, version_base($pdo));
});

test('base : une migration interrompue (tables en partie créées, version non notée) se relance', function () {
    $pdo = base_de_test();
    foreach (['actions', 'copies', 'ecritures', 'jetons'] as $table) {
        $pdo->exec("DROP TABLE `$table`");
    }
    $pdo->exec("DELETE FROM reglages WHERE cle = 'version_base'");
    egal(0, version_base($pdo));
    egal(5, migrer($pdo));
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    egal(JR_TABLES, $tables);
});

test('base : la session MySQL est en UTC et en mode strict', function () {
    $pdo = base_de_test();
    egal('+00:00', $pdo->query('SELECT @@session.time_zone')->fetchColumn());
    leve(PDOException::class, fn() => $pdo->prepare('INSERT INTO reglages (cle, valeur, maj_le) VALUES (?, ?, ?)')
        ->execute([str_repeat('x', 61), '1', '2026-10-08 00:00:00']));
});

test('base : si une autre connexion migre déjà, migrer() attend puis abandonne proprement', function () {
    $pdo = base_de_test();
    $autre = nouvelle_connexion_test();
    $autre->query("SELECT GET_LOCK('jr_migrations', 0)");
    try {
        $e = leve(RuntimeException::class, fn() => migrer($pdo, JR_DOSSIER_MIGRATIONS, 1));
        egal('Verrou des migrations indisponible', $e->getMessage());
    } finally {
        $autre->query("SELECT RELEASE_LOCK('jr_migrations')");
    }
});

test("base : aucun code serveur ne lit l'horloge de MySQL (réglée sur Paris chez OVH)", function () {
    $racine = dirname(__DIR__, 2) . '/serveur';
    $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS));
    foreach ($fichiers as $fichier) {
        if (!preg_match('/\.(php|sql)$/', $fichier->getFilename()) || $fichier->getFilename() === 'config.php') {
            continue;
        }
        $source = file_get_contents($fichier->getPathname());
        vrai(!preg_match('/\b(NOW|UTC_TIMESTAMP|SYSDATE|CURDATE|CURTIME)\s*\(|\bCURRENT_TIMESTAMP\b/i', $source), 'horloge MySQL utilisée dans ' . $fichier->getFilename());
    }
});
