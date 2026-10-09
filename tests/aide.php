<?php
// Mini-cadre de tests de Jarvis Jr. (sans Composer) et aides communes.

declare(strict_types=1);

require_once dirname(__DIR__) . '/outils/lib-env.php';

const MOT_DE_PASSE_TEST = 'phrase de test assez longue';

final class EchecTest extends Exception {}

$GLOBALS['JR_TESTS'] = [];
$GLOBALS['JR_FICHIERS_TEMPORAIRES'] = [];

function test(string $nom, callable $f): void
{
    $GLOBALS['JR_TESTS'][] = [$nom, $f];
}

function egal(mixed $attendu, mixed $obtenu, string $message = ''): void
{
    if ($attendu !== $obtenu) {
        throw new EchecTest(($message !== '' ? "$message : " : '') . 'attendu ' . var_export($attendu, true) . ', obtenu ' . var_export($obtenu, true));
    }
}

function vrai(bool $condition, string $message): void
{
    if (!$condition) {
        throw new EchecTest($message);
    }
}

function leve(string $classe, callable $f): Throwable
{
    try {
        $f();
    } catch (Throwable $e) {
        if (!$e instanceof $classe) {
            throw new EchecTest("attendu $classe, obtenu " . get_class($e) . ' : ' . $e->getMessage());
        }
        return $e;
    }
    throw new EchecTest("attendu $classe, rien n'a été levé");
}

function fichier_temporaire(string $contenu = ''): string
{
    $fichier = tempnam(sys_get_temp_dir(), 'jrtest');
    file_put_contents($fichier, $contenu);
    $GLOBALS['JR_FICHIERS_TEMPORAIRES'][] = $fichier;
    return $fichier;
}

// Accès à la base locale de test (écrit par outils/installer-mysql-local.sh), ou null s'il n'existe pas encore.
function env_local(): ?array
{
    static $local = false;
    if ($local === false) {
        $fichier = dirname(__DIR__, 3) . '/secrets/jarvis-jr-local.env';
        $local = is_file($fichier) ? lire_env($fichier) : null;
    }
    return $local;
}

function config_de_test(array $surcharges = []): array
{
    static $empreinte = null;
    $empreinte ??= password_hash(MOT_DE_PASSE_TEST, PASSWORD_DEFAULT, ['cost' => 4]);
    $local = env_local() ?? [];
    return array_replace_recursive([
        'mode' => 'test',
        'origine' => 'http://localhost:8787',
        'base' => [
            'hote' => $local['DB_HOST'] ?? '',
            'nom' => $local['DB_NAME_TEST'] ?? '',
            'utilisateur' => $local['DB_USER'] ?? '',
            'mot_de_passe' => $local['DB_PASSWORD'] ?? '',
        ],
        'trello' => ['cle' => 'cle-de-test', 'jeton' => 'jeton-de-test', 'ecritures' => 'simulees'],
        'google' => ['client_id' => 'client-de-test', 'client_secret' => 'secret-de-test', 'retour' => 'http://localhost:8787/api/google-callback.php'],
        'mot_de_passe_empreinte' => $empreinte,
        'cle_chiffrement' => base64_encode(str_repeat("\x01", 32)),
    ], $surcharges);
}

function supprimer_dossier(string $dossier): void
{
    if (!is_dir($dossier)) {
        return;
    }
    foreach (scandir($dossier) as $nom) {
        if ($nom === '.' || $nom === '..') {
            continue;
        }
        $chemin = "$dossier/$nom";
        is_dir($chemin) ? supprimer_dossier($chemin) : unlink($chemin);
    }
    rmdir($dossier);
}

// Chaque test part d'un état propre : config de test, heure réelle, pas de route d'essai, dossier data neuf.
function lancer_tests(string $filtre): int
{
    $reussis = 0;
    $echecs = 0;
    $racine_data = sys_get_temp_dir() . '/jr-tests-' . getmypid();
    foreach ($GLOBALS['JR_TESTS'] as $numero => [$nom, $f]) {
        if ($filtre !== '' && !str_contains($nom, $filtre)) {
            continue;
        }
        $GLOBALS['JR_CONFIG'] = config_de_test();
        $GLOBALS['JR_MAINTENANT'] = null;
        $GLOBALS['JR_ROUTES_SUPPLEMENTAIRES'] = [];
        $GLOBALS['JR_DOSSIER_DATA'] = "$racine_data/$numero";
        try {
            $f();
            $reussis++;
            echo "ok     $nom\n";
        } catch (Throwable $e) {
            $echecs++;
            $type = $e instanceof EchecTest ? '' : get_class($e) . ' : ';
            echo "ÉCHEC  $nom\n       $type" . $e->getMessage() . "\n";
        }
    }
    foreach ($GLOBALS['JR_FICHIERS_TEMPORAIRES'] as $fichier) {
        if (is_file($fichier)) {
            unlink($fichier);
        }
    }
    supprimer_dossier($racine_data);
    echo "\n$reussis réussi(s), $echecs échec(s)\n";
    return $echecs > 0 ? 1 : 0;
}

function fixer_heure(string $utc): void
{
    $GLOBALS['JR_MAINTENANT'] = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
}

// Repart d'une base de test vide et migrée. Refuse toute base dont le nom ne finit pas par _test.
function base_de_test(): PDO
{
    if (env_local() === null) {
        throw new EchecTest('Base locale absente : lance outils/installer-mysql-local.sh');
    }
    if (!str_ends_with((string) config('base')['nom'], '_test')) {
        throw new EchecTest('Refus : la base de test doit finir par _test');
    }
    db_oublier();
    try {
        $pdo = db();
    } catch (PDOException $e) {
        throw new EchecTest('MySQL local injoignable : brew services run mysql@8.4');
    }
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec("DROP TABLE `$table`");
    }
    migrer($pdo);
    return $pdo;
}

function nouvelle_connexion_test(): PDO
{
    $b = config('base');
    return new PDO("mysql:host={$b['hote']};dbname={$b['nom']};charset=utf8mb4", $b['utilisateur'], $b['mot_de_passe'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function requete(array $surcharges = []): array
{
    return array_replace([
        'methode' => 'GET',
        'chemin' => 'sante',
        'requete' => [],
        'corps_brut' => '',
        'entetes' => ['origin' => 'http://localhost:8787', 'content-type' => 'application/json'],
        'cookies' => [],
        'ip' => '203.0.113.7',
        'appareil' => 'Tests',
    ], $surcharges);
}

function corps(array $reponse): array
{
    return json_decode($reponse['corps'], true, 512, JSON_THROW_ON_ERROR);
}

function routes_d_essai(): void
{
    $GLOBALS['JR_ROUTES_SUPPLEMENTAIRES'] = [
        'GET essai-protegee' => ['publique' => false, 'action' => fn(array $r, ?array $s): array => reponse_json(['session' => $s['id']])],
        'POST essai-echo' => ['publique' => true, 'action' => fn(array $r, ?array $s): array => reponse_json(['corps' => $r['corps']])],
        'GET essai-panne' => ['publique' => true, 'action' => function (array $r, ?array $s): array {
            throw new RuntimeException('panne simulée');
        }],
    ];
}

function requete_http(string $methode, string $url, array $entetes = [], ?string $corps = null): array
{
    $recus = [];
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_CUSTOMREQUEST => $methode,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $entetes,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HEADERFUNCTION => function ($c, string $ligne) use (&$recus): int {
            if (str_contains($ligne, ':')) {
                [$nom, $valeur] = explode(':', $ligne, 2);
                $recus[strtolower(trim($nom))][] = trim($valeur);
            }
            return strlen($ligne);
        },
    ]);
    if ($corps !== null) {
        curl_setopt($c, CURLOPT_POSTFIELDS, $corps);
    }
    $reponse = curl_exec($c);
    return ['statut' => (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE), 'entetes' => $recus, 'corps' => is_string($reponse) ? $reponse : ''];
}

function attendre_port(int $port): void
{
    for ($i = 0; $i < 100; $i++) {
        $prise = @fsockopen('127.0.0.1', $port, $code, $message, 0.1);
        if ($prise !== false) {
            fclose($prise);
            return;
        }
        usleep(50000);
    }
    throw new EchecTest("Le serveur d'aperçu ne répond pas sur le port $port");
}
