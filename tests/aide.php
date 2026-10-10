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
        'trello' => ['cle' => 'cle-de-test', 'jeton' => 'jeton-de-test', 'ecritures' => 'reelles'],
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
        // Jamais de réseau dans les tests : tout appel sortant doit être prévu par faux_http()
        $GLOBALS['JR_CLIENT_HTTP'] = function (string $methode, string $url): array {
            throw new EchecTest("Appel HTTP sans faux_http() : $methode $url");
        };
        $GLOBALS['JR_APPELS_HTTP'] = [];
        $GLOBALS['JR_TRELLO_IDS'] = fabrique_trello_ids();
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

// Lance l'aperçu local (php -S) avec une config de test. Plusieurs processus PHP traitent alors
// des requêtes en même temps, comme chez OVH. Renvoie [origine, processus].
function lancer_apercu(array $surcharges = [], int $processus_php = 1): array
{
    $port = 18000 + random_int(0, 999);
    $origine = "http://127.0.0.1:$port";
    $config = fichier_temporaire('<?php return ' . var_export(config_de_test(['origine' => $origine] + $surcharges), true) . ';');
    chmod($config, 0600);
    $racine = dirname(__DIR__);
    $env = getenv();
    $env['JR_CONFIG_FICHIER'] = $config;
    $env['PHP_CLI_SERVER_WORKERS'] = (string) $processus_php;
    $serveur = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', "$racine/site", "$racine/outils/apercu.php"],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $tubes,
        $racine,
        $env
    );
    attendre_port($port);
    return [$origine, $serveur];
}

// Arrête aussi les processus fils de php -S, qui sinon resteraient en écoute sur le port.
function arreter_apercu($serveur): void
{
    exec('pkill -TERM -P ' . (int) proc_get_status($serveur)['pid']);
    proc_terminate($serveur);
    proc_close($serveur);
}

// Remplace le client HTTP. Chaque appel prend la première réponse dont la clé « MÉTHODE début d'adresse »
// correspond, et il est noté dans $GLOBALS['JR_APPELS_HTTP']. Une réponse : ['statut' => 200, 'json' => …],
// ['statut' => …, 'corps' => '…'] ou une exception à lever. Une liste de réponses est consommée dans l'ordre,
// la dernière resservant ensuite.
function faux_http(array $reponses): void
{
    $GLOBALS['JR_APPELS_HTTP'] = [];
    $GLOBALS['JR_CLIENT_HTTP'] = function (string $methode, string $url, array $entetes, ?string $corps, int $delai) use (&$reponses): array {
        $GLOBALS['JR_APPELS_HTTP'][] = ['methode' => $methode, 'url' => $url, 'entetes' => $entetes, 'corps' => $corps];
        foreach ($reponses as $cle => &$reponse) {
            [$m, $debut] = explode(' ', $cle, 2);
            if ($m !== $methode || !str_starts_with($url, $debut)) {
                continue;
            }
            $r = is_array($reponse) && array_is_list($reponse) ? (count($reponse) > 1 ? array_shift($reponse) : $reponse[0]) : $reponse;
            if ($r instanceof Throwable) {
                throw $r;
            }
            return ['statut' => $r['statut'] ?? 200, 'corps' => $r['corps'] ?? json_encode($r['json'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }
        throw new EchecTest("Appel HTTP inattendu : $methode $url");
    };
}

function appels_http(): array
{
    return array_map(fn(array $a): string => $a['methode'] . ' ' . $a['url'], $GLOBALS['JR_APPELS_HTTP']);
}

// Cookies d'une session valide, pour appeler une route protégée
function cookies_session(): array
{
    return [JR_COOKIE_SESSION => session_creer('Tests')['valeur']];
}

// Appelle une route avec une session valide : corps JSON pour un POST, paramètres d'adresse pour un GET
function appeler(string $methode, string $chemin, array $corps = [], array $parametres = []): array
{
    return traiter(requete([
        'methode' => $methode,
        'chemin' => $chemin,
        'requete' => $parametres,
        'corps_brut' => $methode === 'GET' ? '' : json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'cookies' => cookies_session(),
    ]));
}
