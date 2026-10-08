# Jarvis Jr., lot 1, plan 1 sur 3 : le socle serveur

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un serveur PHP déployé sur jr.qyris.fr, avec une base migrée, des sessions de 90 jours, la connexion par mot de passe, l'idempotence des écritures et le journal d'actions, testé de bout en bout et mis en ligne par Colin.

**Architecture:** Une API JSON à point d'entrée unique (`site/api/index.php`), dont la logique est une fonction pure `traiter(requête) → réponse` testable sans HTTP. Le code serveur vit dans `serveur/`, hors du dossier public. MySQL 8.4 en local (Homebrew) et chez OVH, heures en UTC. Les envois vers OVH sont faits par Colin avec `outils/deploy-ovh.sh`.

**Tech Stack:** PHP 8.4 sans Composer (PDO MySQL, libsodium, bcrypt), MySQL 8.4, Apache `.htaccess` (OVH mutualisé), bash, lftp en SFTP.

**Spec:** `projects/jarvis-jr/SPEC.md` (validée par Colin le 2026-10-08). L'exécutant lit le spec et ce plan.

**Le lot 1 en 3 plans** :
1. **Ce plan : le socle serveur** (config, base, sessions, connexion, routeur, écritures uniques, journal d'actions, aperçu local, déploiement).
2. Les données (client HTTP, Trello, Google, lecture des créneaux, rafraîchissement, détection, routes `donnees`, `agenda`, `taches/*`, `tirage/parti`). Écrit après l'exécution de ce plan.
3. L'app iPhone (écrans, tirage, hors ligne, service worker, Playwright). Écrit après le plan 2.

Tous les chemins de ce plan sont relatifs à `projects/jarvis-jr/` (dépôt git du projet), sauf mention contraire. Le workspace est `~/Desktop/jarvis-starter-kit/`.

## Global Constraints

- PHP 8.4 sur le serveur, 8.5 sur le Mac : rien de propre à 8.5 (opérateur `|>`, `array_first`, `array_last`, `#[\NoDiscard]`, `clone with`). Pas de Composer, aucune bibliothèque externe.
- `declare(strict_types=1);` en tête de chaque fichier PHP. Noms, commentaires et messages en français, commentaires rares (style du projet).
- MySQL 8.4, InnoDB, `utf8mb4`, `utf8mb4_0900_ai_ci`. Dates en base : UTC, `DATETIME` au format `Y-m-d H:i:s`. Session MySQL : `time_zone = '+00:00'` et `sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'`. Jamais l'horloge de MySQL (`NOW()`…) : l'heure vient de `maintenant()` en PHP.
- Origine : `https://jr.qyris.fr` en production, `http://localhost:8787` en dev.
- Cookie de session : `__Host-jr`, `Secure`, `HttpOnly`, `SameSite=Lax`, `Path=/`, 90 jours glissants (7 776 000 s), prolongé au plus une fois par 24 h. La base ne garde que l'empreinte SHA-256 du jeton.
- Connexion : 10 échecs par adresse IP en 15 min, puis 429. Textes exacts : « Mot de passe incorrect. Encore N essais avant une pause de 15 min. » (« essai » au singulier pour 1), « Trop d'essais. Réessaie dans 15 min. », « Ta session a expiré. Reconnecte-toi. ».
- Réponses JSON : `{"ok": true, …}` ou `{"ok": false, "erreur": {"code": "…", "message": "…"}}`.
- **Secrets** :
  - toute commande Bash qui touche aux secrets (`secrets/`, `.env`, `config.php`, MySQL, SFTP, déploiement) s'écrit en entier dans `{ commande ; } 2>&1 | ~/.claude/hooks/redact-secrets` ;
  - les tests aussi : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets` ;
  - ne jamais lire un fichier de `secrets/` avec Read ou Grep ;
  - si un commit est bloqué par le garde-fou, le relancer dans le même groupe filtré.
- **Envois vers OVH** : Claude ne les fait pas (refusés par le garde-fou d'autorisations le 2026-10-08). Colin lance `outils/deploy-ovh.sh` et `outils/definir-mot-de-passe.sh` dans son terminal.
- Pas de tiret long dans les textes destinés à Colin.
- Commits dans le dépôt `projects/jarvis-jr`, message terminé par la ligne `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Un corps de requête vide, qui n'est pas un objet JSON, mal formé ou de plus de 64 Ko** doit donner 400 ou 413, jamais 500. Test : tâche 6, « un corps vide, non objet ou trop gros ».
2. **Un cookie forgé** (injection SQL, mauvaise longueur, 10 000 caractères, accents) doit donner « pas de session » (401), jamais 500. Tests : tâche 5, « cookie absent, inconnu ou forgé » ; tâche 6, « cookie forgé ».
3. **Un mot de passe avec espaces au début ou à la fin, accents, emoji, antislash ou guillemets** doit marcher exactement tel que tapé. Le piège est le `read` de bash, qui rogne les espaces. Test : tâche 7, « definir-mot-de-passe.sh garde les espaces ».
4. **Deux requêtes juste après un déploiement** ne doivent pas migrer la base en même temps : l'une attend, ou abandonne proprement. Test : tâche 3, « si une autre connexion migre déjà ».
5. **L'horloge de MySQL chez OVH est réglée sur Paris.** Un `NOW()` dans le SQL décalerait sessions et pauses d'une ou deux heures. Test : tâche 3, « aucun code serveur ne lit l'horloge de MySQL ».

## Fichiers de ce plan

| Fichier | Rôle |
|---|---|
| `.gitignore` (modifié) | Ignore `serveur/config.php`, `serveur/trello-ids.json`, `serveur/data/` |
| `outils/lib-env.php` | Lecture et écriture des `.env`, construction et écriture de `config.php` |
| `outils/generer-config.php` | CLI : écrit `config.php` et `trello-ids.json` dans un dossier |
| `outils/env-definir.php` | CLI : écrit une clé dans un `.env` (valeur par variable d'environnement) |
| `outils/empreinte.php` | CLI : empreinte bcrypt d'un mot de passe lu sur l'entrée standard |
| `outils/definir-mot-de-passe.sh` | Colin choisit son mot de passe, et la clé de chiffrement est créée |
| `outils/installer-mysql-local.sh` | MySQL 8.4 local, bases `jarvisjr_dev` et `jarvisjr_test`, `secrets/jarvis-jr-local.env` |
| `outils/apercu.php` | Routeur de `php -S` qui imite le `.htaccess` |
| `outils/deploy-ovh.sh` | Mise en ligne, lancée par Colin |
| `serveur/lib/amorce.php` | `config()`, `maintenant()`, `utc()`, `depuis_utc()`, `dossier_data()`, `journal_erreur()` |
| `serveur/lib/base.php` | `db()`, `db_oublier()`, migrations |
| `serveur/lib/chiffrement.php` | `chiffrer()`, `dechiffrer()` |
| `serveur/lib/session.php` | Sessions maison |
| `serveur/lib/reponse.php` | `ErreurApi`, réponses JSON, en-têtes de sécurité, `emettre()` |
| `serveur/lib/routeur.php` | `routes()`, `traiter()`, `lire_corps_json()` |
| `serveur/lib/connexion.php` | Essais, vérification du mot de passe |
| `serveur/lib/ecritures.php` | `ecriture_unique()`, `ecriture_trouver()` |
| `serveur/lib/actions.php` | Journal d'actions |
| `serveur/routes/systeme.php` | `GET sante` |
| `serveur/routes/session.php` | `POST connexion`, `deconnexion`, `deconnexion-partout` |
| `serveur/migrations/001_socle.sql` | Les 7 tables du lot 1 |
| `site/api/index.php` | Point d'entrée HTTP de l'API |
| `site/.htaccess`, `site/robots.txt` | Règles Apache, robots |
| `tests/run.php`, `tests/aide.php` | Lanceur et aides de test |
| `tests/unitaires/test_*.php`, `tests/integration/test_http.php` | Tests |

---

### Task 1 : lanceur de tests et configuration

**Files:**
- Create: `tests/run.php`, `tests/aide.php`, `outils/lib-env.php`, `outils/generer-config.php`, `outils/env-definir.php`
- Modify: `.gitignore`
- Test: `tests/unitaires/test_env.php`

**Interfaces:**
- Consumes : rien.
- Produces :
  - `test(string $nom, callable $f): void`, `egal(mixed $attendu, mixed $obtenu, string $message = ''): void`, `vrai(bool $condition, string $message): void`, `leve(string $classe, callable $f): Throwable`, `fichier_temporaire(string $contenu = ''): string`, `env_local(): ?array`, `config_de_test(array $surcharges = []): array`, `lancer_tests(string $filtre): int`, `supprimer_dossier(string $dossier): void`, classe `EchecTest`, constante `MOT_DE_PASSE_TEST` ;
  - `lire_env(string $fichier): array`, `env_definir(string $fichier, string $cle, string $valeur): void`, `construire_config(string $mode, array $app, array $trello, array $local = []): array`, `ecrire_config(array $config, string $fichier): void`, constante `JR_ORIGINES` ;
  - forme de la config : `['mode', 'origine', 'base' => ['hote','nom','utilisateur','mot_de_passe'], 'trello' => ['cle','jeton','ecritures'], 'google' => ['client_id','client_secret','retour'], 'mot_de_passe_empreinte', 'cle_chiffrement']`.

- [ ] **Step 1 : écrire le lanceur et les aides de test**

`tests/aide.php` :

```php
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
```

`tests/run.php` :

```php
<?php
// Tests PHP de Jarvis Jr. : php tests/run.php [morceau du nom d'un test]
// Toujours à travers le filtre : { php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets

declare(strict_types=1);

require __DIR__ . '/aide.php';
foreach (glob(dirname(__DIR__) . '/serveur/lib/*.php') as $fichier) {
    require_once $fichier;
}
foreach (array_merge(glob(__DIR__ . '/unitaires/test_*.php'), glob(__DIR__ . '/integration/test_*.php')) as $fichier) {
    require $fichier;
}
exit(lancer_tests($argv[1] ?? ''));
```

- [ ] **Step 2 : écrire les tests de la configuration**

`tests/unitaires/test_env.php` :

```php
<?php
declare(strict_types=1);

function env_exemple_app(): array
{
    return [
        'DB_HOST' => 'base.ovh', 'DB_NAME' => 'jr', 'DB_USER' => 'u', 'DB_PASSWORD' => 'p',
        'TRELLO_TOKEN' => 't', 'GOOGLE_CLIENT_ID' => 'g', 'GOOGLE_CLIENT_SECRET' => 's',
        'APP_MOT_DE_PASSE_EMPREINTE' => '$2y$04$abc', 'APP_CLE_CHIFFREMENT' => 'Y2xl',
    ];
}

test('env : lit CLÉ=valeur, ignore commentaires et lignes vides, garde les $ et les =', function () {
    $f = fichier_temporaire("# commentaire\n\nA=simple\nB='\$2y\$12\$abc=def'\nC=\"entre guillemets\"\n  D = espaces  \nE=\n");
    egal(['A' => 'simple', 'B' => '$2y$12$abc=def', 'C' => 'entre guillemets', 'D' => 'espaces', 'E' => ''], lire_env($f));
});

test('env : un fichier absent lève une exception qui ne donne que le nom du fichier', function () {
    $e = leve(RuntimeException::class, fn() => lire_env('/chemin/secret/absent.env'));
    egal('Fichier introuvable : absent.env', $e->getMessage());
});

test('env : env_definir remplace une clé, en ajoute une autre et laisse le fichier en 600', function () {
    $f = fichier_temporaire("A=1\nB=2\n");
    env_definir($f, 'B', '$2y$12$x');
    env_definir($f, 'C', 'trois');
    egal(['A' => '1', 'B' => '$2y$12$x', 'C' => 'trois'], lire_env($f));
    egal('0600', substr(sprintf('%o', fileperms($f)), -4));
});

test('env : env_definir refuse un guillemet simple, un retour à la ligne ou un nom de clé bizarre', function () {
    $f = fichier_temporaire("A=1\n");
    leve(InvalidArgumentException::class, fn() => env_definir($f, 'A', "it's"));
    leve(InvalidArgumentException::class, fn() => env_definir($f, 'A', "a\nb"));
    leve(InvalidArgumentException::class, fn() => env_definir($f, 'a b', 'x'));
});

test('config : en production, base de jarvis-jr.env, origine https, écritures Trello réelles', function () {
    $c = construire_config('production', env_exemple_app(), ['TRELLO_KEY' => 'k']);
    egal('https://jr.qyris.fr', $c['origine']);
    egal(['hote' => 'base.ovh', 'nom' => 'jr', 'utilisateur' => 'u', 'mot_de_passe' => 'p'], $c['base']);
    egal('reelles', $c['trello']['ecritures']);
    egal('k', $c['trello']['cle']);
    egal('https://jr.qyris.fr/api/google-callback.php', $c['google']['retour']);
});

test('config : en dev, base locale, origine localhost, écritures simulées sauf JR_DEV_TRELLO_REEL=1', function () {
    $local = ['DB_HOST' => 'localhost', 'DB_NAME' => 'jarvisjr_dev', 'DB_USER' => 'jarvisjr', 'DB_PASSWORD' => 'l'];
    $c = construire_config('dev', env_exemple_app(), ['TRELLO_KEY' => 'k'], $local);
    egal('http://localhost:8787', $c['origine']);
    egal('jarvisjr_dev', $c['base']['nom']);
    egal('simulees', $c['trello']['ecritures']);
    $c = construire_config('dev', env_exemple_app(), ['TRELLO_KEY' => 'k'], $local + ['JR_DEV_TRELLO_REEL' => '1']);
    egal('reelles', $c['trello']['ecritures']);
});

test('config : une clé manquante est nommée, sans aucune valeur dans le message ; un mode inconnu est refusé', function () {
    $app = env_exemple_app();
    unset($app['GOOGLE_CLIENT_SECRET']);
    $e = leve(RuntimeException::class, fn() => construire_config('production', $app, ['TRELLO_KEY' => 'k']));
    egal('Clé manquante ou vide : GOOGLE_CLIENT_SECRET', $e->getMessage());
    leve(InvalidArgumentException::class, fn() => construire_config('autre', env_exemple_app(), ['TRELLO_KEY' => 'k']));
});

test('config : ecrire_config produit un fichier PHP relisible, en droits 600', function () {
    $f = fichier_temporaire();
    $config = construire_config('production', env_exemple_app(), ['TRELLO_KEY' => 'k']);
    ecrire_config($config, $f);
    egal($config, require $f);
    egal('0600', substr(sprintf('%o', fileperms($f)), -4));
});
```

- [ ] **Step 3 : lancer les tests, vérifier qu'ils échouent**

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : erreur fatale `Failed opening required '.../outils/lib-env.php'`.

- [ ] **Step 4 : écrire `outils/lib-env.php`**

```php
<?php
// Lecture et écriture des fichiers .env de Jarvis Jr., construction de serveur/config.php.
// Utilisé par generer-config.php, env-definir.php et les tests. Jamais chargé par l'app en ligne.

declare(strict_types=1);

const JR_ORIGINES = ['production' => 'https://jr.qyris.fr', 'dev' => 'http://localhost:8787'];

// Fichier CLÉ=valeur, sans expansion de variables (une empreinte bcrypt contient des $).
// Les guillemets simples ou doubles autour de la valeur sont retirés.
function lire_env(string $fichier): array
{
    if (!is_file($fichier)) {
        throw new RuntimeException('Fichier introuvable : ' . basename($fichier));
    }
    $valeurs = [];
    foreach (file($fichier, FILE_IGNORE_NEW_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#' || !str_contains($ligne, '=')) {
            continue;
        }
        [$cle, $valeur] = explode('=', $ligne, 2);
        $valeur = trim($valeur);
        if (strlen($valeur) >= 2 && ($valeur[0] === "'" || $valeur[0] === '"') && $valeur[-1] === $valeur[0]) {
            $valeur = substr($valeur, 1, -1);
        }
        $valeurs[trim($cle)] = $valeur;
    }
    return $valeurs;
}

// Remplace ou ajoute CLÉ='valeur' (entre guillemets simples, pour que bash ne touche pas aux $),
// en gardant les autres lignes. Le fichier reste en 600.
function env_definir(string $fichier, string $cle, string $valeur): void
{
    if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $cle)) {
        throw new InvalidArgumentException('Nom de clé invalide');
    }
    if (str_contains($valeur, "'") || str_contains($valeur, "\n")) {
        throw new InvalidArgumentException('Valeur impossible à écrire entre guillemets simples');
    }
    $lignes = is_file($fichier) ? file($fichier, FILE_IGNORE_NEW_LINES) : [];
    $nouvelle = $cle . "='" . $valeur . "'";
    $remplacee = false;
    foreach ($lignes as $i => $ligne) {
        if (preg_match('/^\s*' . $cle . '\s*=/', $ligne)) {
            $lignes[$i] = $nouvelle;
            $remplacee = true;
        }
    }
    if (!$remplacee) {
        $lignes[] = $nouvelle;
    }
    $ancien_umask = umask(077);
    $ok = file_put_contents($fichier, implode("\n", $lignes) . "\n", LOCK_EX);
    umask($ancien_umask);
    if ($ok === false) {
        throw new RuntimeException('Écriture impossible : ' . basename($fichier));
    }
    chmod($fichier, 0600);
}

// $app : secrets/jarvis-jr.env ; $trello : secrets/trello.env ; $local : secrets/jarvis-jr-local.env (dev).
function construire_config(string $mode, array $app, array $trello, array $local = []): array
{
    if (!isset(JR_ORIGINES[$mode])) {
        throw new InvalidArgumentException("Mode inconnu : $mode");
    }
    $base = $mode === 'production' ? $app : $local;
    $requis = [
        [$base, ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD']],
        [$app, ['TRELLO_TOKEN', 'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'APP_MOT_DE_PASSE_EMPREINTE', 'APP_CLE_CHIFFREMENT']],
        [$trello, ['TRELLO_KEY']],
    ];
    foreach ($requis as [$source, $cles]) {
        foreach ($cles as $cle) {
            if (($source[$cle] ?? '') === '') {
                throw new RuntimeException("Clé manquante ou vide : $cle");
            }
        }
    }
    $origine = JR_ORIGINES[$mode];
    $trello_reel = $mode === 'production' || ($local['JR_DEV_TRELLO_REEL'] ?? '') === '1';
    return [
        'mode' => $mode,
        'origine' => $origine,
        'base' => [
            'hote' => $base['DB_HOST'],
            'nom' => $base['DB_NAME'],
            'utilisateur' => $base['DB_USER'],
            'mot_de_passe' => $base['DB_PASSWORD'],
        ],
        'trello' => ['cle' => $trello['TRELLO_KEY'], 'jeton' => $app['TRELLO_TOKEN'], 'ecritures' => $trello_reel ? 'reelles' : 'simulees'],
        'google' => ['client_id' => $app['GOOGLE_CLIENT_ID'], 'client_secret' => $app['GOOGLE_CLIENT_SECRET'], 'retour' => $origine . '/api/google-callback.php'],
        'mot_de_passe_empreinte' => $app['APP_MOT_DE_PASSE_EMPREINTE'],
        'cle_chiffrement' => $app['APP_CLE_CHIFFREMENT'],
    ];
}

function ecrire_config(array $config, string $fichier): void
{
    $ancien_umask = umask(077);
    $ok = file_put_contents($fichier, "<?php\n// Généré par outils/generer-config.php. Ne pas modifier, ne pas versionner.\nreturn " . var_export($config, true) . ";\n", LOCK_EX);
    umask($ancien_umask);
    if ($ok === false) {
        throw new RuntimeException('Écriture impossible : ' . basename($fichier));
    }
    chmod($fichier, 0600);
}
```

- [ ] **Step 5 : lancer les tests, vérifier qu'ils passent**

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `8 réussi(s), 0 échec(s)`.

- [ ] **Step 6 : écrire les deux petits outils en ligne de commande**

`outils/generer-config.php` :

```php
<?php
// Écrit config.php et la copie de trello-ids.json dans un dossier, pour Jarvis Jr.
//   php outils/generer-config.php --mode=dev|production --dossier=serveur
// Toujours à travers le filtre des secrets.

declare(strict_types=1);

require __DIR__ . '/lib-env.php';

$options = getopt('', ['mode:', 'dossier:']);
$mode = (string) ($options['mode'] ?? '');
$dossier = (string) ($options['dossier'] ?? '');
if ($dossier === '' || !is_dir($dossier)) {
    fwrite(STDERR, "Usage : php outils/generer-config.php --mode=dev|production --dossier=DOSSIER\n");
    exit(1);
}

$workspace = dirname(__DIR__, 3);
try {
    $app = lire_env("$workspace/secrets/jarvis-jr.env");
    $trello = lire_env("$workspace/secrets/trello.env");
    $local = $mode === 'dev' ? lire_env("$workspace/secrets/jarvis-jr-local.env") : [];
    ecrire_config(construire_config($mode, $app, $trello, $local), "$dossier/config.php");
    if (!copy("$workspace/.claude/scripts/trello-ids.json", "$dossier/trello-ids.json")) {
        throw new RuntimeException('Copie de trello-ids.json impossible');
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
echo "config.php et trello-ids.json écrits dans $dossier (mode $mode)\n";
```

`outils/env-definir.php` :

```php
<?php
// Écrit une clé dans un fichier .env. La valeur passe par la variable JR_VALEUR, jamais en argument.
//   JR_VALEUR=... php outils/env-definir.php FICHIER CLÉ

declare(strict_types=1);

require __DIR__ . '/lib-env.php';

$fichier = (string) ($argv[1] ?? '');
$cle = (string) ($argv[2] ?? '');
$valeur = getenv('JR_VALEUR');
if ($fichier === '' || $cle === '' || $valeur === false || $valeur === '') {
    fwrite(STDERR, "Usage : JR_VALEUR=... php outils/env-definir.php FICHIER CLÉ\n");
    exit(1);
}
try {
    env_definir($fichier, $cle, $valeur);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
```

Ajouter à la fin de `.gitignore` :

```
serveur/config.php
serveur/trello-ids.json
serveur/data/
```

Run : `php -l outils/generer-config.php && php -l outils/env-definir.php`
Expected : `No syntax errors detected` deux fois.

- [ ] **Step 7 : commit**

```bash
git add .gitignore tests/run.php tests/aide.php tests/unitaires/test_env.php outils/lib-env.php outils/generer-config.php outils/env-definir.php
git commit -m "$(printf 'Lot 1 : lanceur de tests et génération de la config\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
```

---

### Task 2 : amorce (config, heure, journal d'erreurs)

**Files:**
- Create: `serveur/lib/amorce.php`
- Modify: `tests/aide.php` (ajout de `fixer_heure`)
- Test: `tests/unitaires/test_amorce.php`

**Interfaces:**
- Consumes : `config_de_test()` (tâche 1), qui remplit `$GLOBALS['JR_CONFIG']`.
- Produces :
  - constantes `JR_VERSION` (`'0.1.0'`), `JR_DOMAINES` (`['qyris','asso','etudes','perso']`), `JR_JOURNAL_MAX` (1 048 576) ;
  - `config(?string $cle = null): mixed` ; `maintenant(): DateTimeImmutable` (UTC, remplaçable par `$GLOBALS['JR_MAINTENANT']`) ;
  - `utc(DateTimeImmutable $moment): string` (format `Y-m-d H:i:s` en UTC) ; `depuis_utc(string $texte): DateTimeImmutable` ;
  - `dossier_data(): string` (crée le dossier, remplaçable par `$GLOBALS['JR_DOSSIER_DATA']`) ; `journal_erreur(string $route, Throwable $e): void` ;
  - aide de test `fixer_heure(string $utc): void`.

- [ ] **Step 1 : ajouter l'aide `fixer_heure` à `tests/aide.php`** (à la fin du fichier)

```php
function fixer_heure(string $utc): void
{
    $GLOBALS['JR_MAINTENANT'] = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
}
```

- [ ] **Step 2 : écrire les tests**

`tests/unitaires/test_amorce.php` :

```php
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
```

- [ ] **Step 3 : lancer les tests, vérifier qu'ils échouent**

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : les 8 tests de la tâche 1 passent ; les 7 nouveaux sont en `ÉCHEC` avec `Call to undefined function utc()` (ou `depuis_utc`, `config`, `journal_erreur`).

- [ ] **Step 4 : écrire `serveur/lib/amorce.php`**

```php
<?php
// Amorce commune : configuration, heure, journal d'erreurs. Chargée par toute la partie serveur.

declare(strict_types=1);

const JR_VERSION = '0.1.0';
const JR_DOMAINES = ['qyris', 'asso', 'etudes', 'perso'];
const JR_JOURNAL_MAX = 1048576;

date_default_timezone_set('UTC');

// La config vient de serveur/config.php (généré, hors git). Les tests la posent dans $GLOBALS['JR_CONFIG'].
// Le serveur d'aperçu des tests d'intégration peut désigner un autre fichier par JR_CONFIG_FICHIER.
function config(?string $cle = null): mixed
{
    if (!isset($GLOBALS['JR_CONFIG'])) {
        $fichier = __DIR__ . '/../config.php';
        if (PHP_SAPI === 'cli-server' && getenv('JR_CONFIG_FICHIER')) {
            $fichier = getenv('JR_CONFIG_FICHIER');
        }
        $GLOBALS['JR_CONFIG'] = require $fichier;
    }
    return $cle === null ? $GLOBALS['JR_CONFIG'] : ($GLOBALS['JR_CONFIG'][$cle] ?? null);
}

function maintenant(): DateTimeImmutable
{
    return $GLOBALS['JR_MAINTENANT'] ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

// Format des dates en base : UTC, 'AAAA-MM-JJ HH:MM:SS'.
function utc(DateTimeImmutable $moment): string
{
    return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function depuis_utc(string $texte): DateTimeImmutable
{
    $moment = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $texte, new DateTimeZone('UTC'));
    if ($moment === false) {
        throw new InvalidArgumentException('Date UTC illisible');
    }
    return $moment;
}

function dossier_data(): string
{
    $dossier = $GLOBALS['JR_DOSSIER_DATA'] ?? __DIR__ . '/../data';
    if (!is_dir($dossier)) {
        mkdir($dossier, 0700, true);
    }
    return $dossier;
}

// Une ligne par erreur : heure, route, type, message technique. Jamais de contenu personnel :
// un message PDO peut citer des valeurs, on n'en garde que le code SQLSTATE.
function journal_erreur(string $route, Throwable $e): void
{
    $fichier = dossier_data() . '/erreurs.log';
    if (is_file($fichier) && filesize($fichier) > JR_JOURNAL_MAX) {
        rename($fichier, $fichier . '.1');
    }
    $route = preg_replace('/[^a-z0-9\/._-]/i', '?', substr($route, 0, 60));
    $message = $e instanceof PDOException ? 'SQLSTATE ' . $e->getCode() : $e->getMessage();
    $message = str_replace(["\r", "\n"], ' ', mb_substr($message, 0, 300));
    $ligne = sprintf("%s | %s | %s | %s | %s:%d\n", utc(maintenant()), $route, get_class($e), $message, basename($e->getFile()), $e->getLine());
    file_put_contents($fichier, $ligne, FILE_APPEND | LOCK_EX);
}
```

- [ ] **Step 5 : lancer tous les tests, vérifier qu'ils passent**

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `15 réussi(s), 0 échec(s)`.

- [ ] **Step 6 : commit**

```bash
git add serveur/lib/amorce.php tests/aide.php tests/unitaires/test_amorce.php
git commit -m "$(printf 'Lot 1 : amorce (config, heure UTC, journal d erreurs)\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
```

---

### Task 3 : MySQL local, connexion et migrations

**Files:**
- Create: `outils/installer-mysql-local.sh`, `serveur/lib/base.php`, `serveur/migrations/001_socle.sql`
- Modify: `tests/aide.php` (ajout de `base_de_test`, `nouvelle_connexion_test`)
- Test: `tests/unitaires/test_base.php`

**Interfaces:**
- Consumes : `config()`, `maintenant()`, `utc()` (tâche 2) ; `env_local()`, `EchecTest` (tâche 1).
- Produces :
  - constantes `JR_SQL_MODE`, `JR_DOSSIER_MIGRATIONS` ;
  - `db(): PDO` (singleton dans `$GLOBALS['JR_PDO']`, fetch associatif, exceptions, UTC, strict) ; `db_oublier(): void` ;
  - `version_attendue(string $dossier = JR_DOSSIER_MIGRATIONS): int` ; `version_base(PDO $pdo): int` ; `migrer(PDO $pdo, string $dossier = JR_DOSSIER_MIGRATIONS, int $attente = 10): int` ; `assurer_migrations(): void` ;
  - tables : `reglages`, `sessions`, `essais_connexion`, `jetons`, `copies`, `actions`, `ecritures` (colonnes ci-dessous) ;
  - aides de test : `base_de_test(): PDO` (vide `jarvisjr_test` et migre ; refuse toute base qui ne finit pas par `_test`), `nouvelle_connexion_test(): PDO`.

- [ ] **Step 1 : écrire le script d'installation de MySQL local**

`outils/installer-mysql-local.sh` :

```bash
#!/bin/bash
# Installe MySQL 8.4 en local (Homebrew) pour le développement de Jarvis Jr. : bases jarvisjr_dev et
# jarvisjr_test, utilisateur jarvisjr, accès dans secrets/jarvis-jr-local.env (droits 600).
# MySQL n'est pas lancé au démarrage du Mac : « brew services run » le lance pour la session,
# « brew services stop mysql@8.4 » l'arrête.
# À lancer à travers le filtre des secrets.

set -euo pipefail

ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_LOCAL="$ICI/../../../secrets/jarvis-jr-local.env"

brew list mysql@8.4 >/dev/null 2>&1 || brew install mysql@8.4
MYSQL_BIN="$(brew --prefix mysql@8.4)/bin"
brew services run mysql@8.4
"$MYSQL_BIN/mysqladmin" --wait=30 --connect-timeout=2 -uroot ping

if [ -f "$ENV_LOCAL" ]; then
    echo "Déjà configuré : $ENV_LOCAL existe."
    exit 0
fi

MOT_DE_PASSE="$(openssl rand -hex 16)"
# Le mot de passe passe par l'entrée standard de mysql, jamais en argument
"$MYSQL_BIN/mysql" -uroot <<SQL
CREATE DATABASE IF NOT EXISTS jarvisjr_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS jarvisjr_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER IF NOT EXISTS 'jarvisjr'@'localhost' IDENTIFIED BY '$MOT_DE_PASSE';
GRANT ALL PRIVILEGES ON jarvisjr_dev.* TO 'jarvisjr'@'localhost';
GRANT ALL PRIVILEGES ON jarvisjr_test.* TO 'jarvisjr'@'localhost';
SQL

umask 077
cat > "$ENV_LOCAL" <<EOF
# Base MySQL locale de Jarvis Jr. (outils/installer-mysql-local.sh). Données d'essai seulement.
DB_HOST=localhost
DB_NAME=jarvisjr_dev
DB_NAME_TEST=jarvisjr_test
DB_USER=jarvisjr
DB_PASSWORD=$MOT_DE_PASSE
EOF
echo "MySQL local prêt. Accès écrits dans secrets/jarvis-jr-local.env."
```

`DB_HOST=localhost` fait passer PHP par le socket Unix de MySQL, que l'authentification par défaut de MySQL 8.4 (`caching_sha2_password`) accepte sans TLS.

- [ ] **Step 2 : faire l'installation, avec l'accord de Colin**

Colin a donné son accord de principe le 2026-10-08 (question 4 du brainstorming). Le lui rappeler en une ligne avant de lancer :

Run : `{ chmod +x outils/installer-mysql-local.sh && outils/installer-mysql-local.sh ; } 2>&1 | ~/.claude/hooks/redact-secrets` (timeout 600 000 ms)
Expected : `mysqld is alive`, puis `MySQL local prêt.`

- [ ] **Step 3 : ajouter les aides de base à `tests/aide.php`** (à la fin du fichier)

```php
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
```

- [ ] **Step 4 : écrire les tests**

`tests/unitaires/test_base.php` :

```php
<?php
declare(strict_types=1);

const JR_TABLES_LOT1 = ['actions', 'copies', 'ecritures', 'essais_connexion', 'jetons', 'reglages', 'sessions'];

test('base : les migrations créent les 7 tables du lot 1 et notent la version', function () {
    $pdo = base_de_test();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    egal(JR_TABLES_LOT1, $tables);
    egal(1, version_base($pdo));
    egal(1, version_attendue());
});

test('base : relancer les migrations ne change rien', function () {
    $pdo = base_de_test();
    egal(1, migrer($pdo));
    egal(1, version_base($pdo));
});

test('base : une base vide est en version 0', function () {
    $pdo = base_de_test();
    foreach (JR_TABLES_LOT1 as $table) {
        $pdo->exec("DROP TABLE `$table`");
    }
    egal(0, version_base($pdo));
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
```

- [ ] **Step 5 : lancer les tests, vérifier qu'ils échouent**

Run : `{ php tests/run.php base ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `ÉCHEC` avec `Call to undefined function db_oublier()`.

- [ ] **Step 6 : écrire la migration du socle**

`serveur/migrations/001_socle.sql` (pas de point-virgule dans les commentaires : le lecteur découpe sur `;` en fin de ligne) :

```sql
-- Lot 1 : les 7 tables du socle. Heures en UTC.

CREATE TABLE reglages (
  cle VARCHAR(60) NOT NULL PRIMARY KEY,
  valeur JSON NOT NULL,
  maj_le DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  empreinte CHAR(64) NOT NULL,
  cree_le DATETIME NOT NULL,
  vu_le DATETIME NOT NULL,
  expire_le DATETIME NOT NULL,
  appareil VARCHAR(120) NOT NULL DEFAULT '',
  UNIQUE KEY empreinte (empreinte),
  KEY expire_le (expire_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE essais_connexion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  quand DATETIME NOT NULL,
  KEY ip_quand (ip, quand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE jetons (
  service VARCHAR(40) NOT NULL PRIMARY KEY,
  contenu BLOB NOT NULL,
  expire_le DATETIME NULL,
  maj_le DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE copies (
  source VARCHAR(40) NOT NULL PRIMARY KEY,
  contenu JSON NOT NULL,
  recupere_le DATETIME NOT NULL,
  empreinte CHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(30) NOT NULL,
  domaine VARCHAR(10) NOT NULL,
  carte_id CHAR(24) NOT NULL,
  element_id CHAR(24) NULL,
  quand DATETIME NOT NULL,
  source VARCHAR(10) NOT NULL,
  precision_heure VARCHAR(10) NOT NULL,
  annulee TINYINT(1) NOT NULL DEFAULT 0,
  donnees JSON NULL,
  KEY recherche (type, carte_id, element_id, quand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE ecritures (
  id_client CHAR(36) NOT NULL PRIMARY KEY,
  route VARCHAR(40) NOT NULL,
  recu_le DATETIME NOT NULL,
  resultat JSON NOT NULL,
  KEY recu_le (recu_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

- [ ] **Step 7 : écrire `serveur/lib/base.php`**

```php
<?php
// Connexion MySQL (mode strict, heure UTC) et migrations.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';

const JR_SQL_MODE = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION';
const JR_DOSSIER_MIGRATIONS = __DIR__ . '/../migrations';

function db(): PDO
{
    if (!isset($GLOBALS['JR_PDO'])) {
        $b = config('base');
        $pdo = new PDO("mysql:host={$b['hote']};dbname={$b['nom']};charset=utf8mb4", $b['utilisateur'], $b['mot_de_passe'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        // Le sql_mode d'OVH n'est pas strict, et son horloge MySQL est à l'heure de Paris
        $pdo->exec("SET SESSION sql_mode = '" . JR_SQL_MODE . "', time_zone = '+00:00'");
        $GLOBALS['JR_PDO'] = $pdo;
    }
    return $GLOBALS['JR_PDO'];
}

function db_oublier(): void
{
    unset($GLOBALS['JR_PDO']);
}

function version_attendue(string $dossier = JR_DOSSIER_MIGRATIONS): int
{
    $fichiers = glob($dossier . '/[0-9][0-9][0-9]_*.sql');
    return $fichiers ? (int) basename(end($fichiers)) : 0;
}

function version_base(PDO $pdo): int
{
    try {
        $valeur = $pdo->query("SELECT valeur FROM reglages WHERE cle = 'version_base'")->fetchColumn();
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            return 0;  // table absente : base vide
        }
        throw $e;
    }
    return $valeur === false ? 0 : (int) json_decode((string) $valeur);
}

// Applique les migrations en retard, sous un verrou : deux requêtes juste après un déploiement
// ne migrent pas en même temps. Renvoie la version atteinte.
function migrer(PDO $pdo, string $dossier = JR_DOSSIER_MIGRATIONS, int $attente = 10): int
{
    if ((int) $pdo->query("SELECT GET_LOCK('jr_migrations', $attente)")->fetchColumn() !== 1) {
        throw new RuntimeException('Verrou des migrations indisponible');
    }
    try {
        $version = version_base($pdo);
        foreach (glob($dossier . '/[0-9][0-9][0-9]_*.sql') as $fichier) {
            $numero = (int) basename($fichier);
            if ($numero <= $version) {
                continue;
            }
            foreach (preg_split('/;\s*$/m', (string) file_get_contents($fichier)) as $instruction) {
                if (trim(preg_replace('/^--.*$/m', '', $instruction)) !== '') {
                    $pdo->exec($instruction);
                }
            }
            $pdo->prepare("INSERT INTO reglages (cle, valeur, maj_le) VALUES ('version_base', ?, ?) AS n
                ON DUPLICATE KEY UPDATE valeur = n.valeur, maj_le = n.maj_le")
                ->execute([json_encode($numero), utc(maintenant())]);
            $version = $numero;
        }
        return $version;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('jr_migrations')");
    }
}

function assurer_migrations(): void
{
    $pdo = db();
    if (version_base($pdo) < version_attendue()) {
        migrer($pdo);
    }
}
```

- [ ] **Step 8 : lancer tous les tests, vérifier qu'ils passent**

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `21 réussi(s), 0 échec(s)`.

- [ ] **Step 9 : commit**

```bash
git add outils/installer-mysql-local.sh serveur/lib/base.php serveur/migrations/001_socle.sql tests/aide.php tests/unitaires/test_base.php
git commit -m "$(printf 'Lot 1 : MySQL local, connexion stricte en UTC et migrations\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
```

---

### Task 4 : chiffrement

**Files:**
- Create: `serveur/lib/chiffrement.php`
- Test: `tests/unitaires/test_chiffrement.php`

**Interfaces:**
- Consumes : `config('cle_chiffrement')` (base64 de 32 octets).
- Produces : `chiffrer(string $clair): string` (nonce de 24 octets suivi du texte chiffré, binaire) ; `dechiffrer(string $chiffre): string` (lève `RuntimeException` si le texte est modifié, trop court, ou chiffré avec une autre clé) ; `cle_chiffrement(): string`. Utilisé par le plan 2 pour les jetons Google.

- [ ] **Step 1 : écrire les tests**

`tests/unitaires/test_chiffrement.php` :

```php
<?php
declare(strict_types=1);

test('chiffrement : aller-retour, et deux chiffrements du même texte diffèrent', function () {
    $a = chiffrer('jeton Google');
    $b = chiffrer('jeton Google');
    vrai($a !== $b, 'nonce aléatoire attendu');
    egal('jeton Google', dechiffrer($a));
});

test('chiffrement : un texte modifié est refusé', function () {
    $c = chiffrer('jeton Google');
    $c[30] = $c[30] === 'a' ? 'b' : 'a';
    leve(RuntimeException::class, fn() => dechiffrer($c));
});

test('chiffrement : un texte trop court est refusé', function () {
    leve(RuntimeException::class, fn() => dechiffrer('court'));
});

test('chiffrement : avec une autre clé, le déchiffrement échoue', function () {
    $c = chiffrer('jeton Google');
    $GLOBALS['JR_CONFIG']['cle_chiffrement'] = base64_encode(str_repeat("\x02", 32));
    leve(RuntimeException::class, fn() => dechiffrer($c));
});

test('chiffrement : une clé absente ou de mauvaise taille est refusée', function () {
    $GLOBALS['JR_CONFIG']['cle_chiffrement'] = base64_encode('trop courte');
    leve(RuntimeException::class, fn() => chiffrer('x'));
    $GLOBALS['JR_CONFIG']['cle_chiffrement'] = '';
    leve(RuntimeException::class, fn() => chiffrer('x'));
});
```

- [ ] **Step 2 : lancer les tests, vérifier qu'ils échouent**

Run : `{ php tests/run.php chiffrement ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `ÉCHEC` avec `Call to undefined function chiffrer()`.

- [ ] **Step 3 : écrire `serveur/lib/chiffrement.php`**

```php
<?php
// Chiffrement des secrets gardés en base (jetons Google, plus tard mots de passe d'application) : libsodium.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';

function cle_chiffrement(): string
{
    $cle = base64_decode((string) config('cle_chiffrement'), true);
    if ($cle === false || strlen($cle) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('Clé de chiffrement absente ou invalide');
    }
    return $cle;
}

function chiffrer(string $clair): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return $nonce . sodium_crypto_secretbox($clair, $nonce, cle_chiffrement());
}

function dechiffrer(string $chiffre): string
{
    if (strlen($chiffre) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
        throw new RuntimeException('Texte chiffré invalide');
    }
    $clair = sodium_crypto_secretbox_open(
        substr($chiffre, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        substr($chiffre, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        cle_chiffrement()
    );
    if ($clair === false) {
        throw new RuntimeException('Déchiffrement impossible');
    }
    return $clair;
}
```

- [ ] **Step 4 : lancer tous les tests, vérifier qu'ils passent**

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `26 réussi(s), 0 échec(s)`.

- [ ] **Step 5 : commit**

```bash
git add serveur/lib/chiffrement.php tests/unitaires/test_chiffrement.php
git commit -m "$(printf 'Lot 1 : chiffrement libsodium\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
```

---

### Task 5 : sessions maison

**Files:**
- Create: `serveur/lib/session.php`
- Test: `tests/unitaires/test_session.php`

**Interfaces:**
- Consumes : `db()`, `maintenant()`, `utc()`, `depuis_utc()`, `base_de_test()`, `fixer_heure()`.
- Produces :
  - constantes `JR_COOKIE_SESSION` (`'__Host-jr'`), `JR_SESSION_DUREE` (7 776 000), `JR_SESSION_RAFRAICHIR` (86 400) ;
  - un « cookie » est un tableau `['nom' => string, 'valeur' => string, 'options' => ['expires' => int, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']]`, prêt pour `setcookie()` ;
  - `session_creer(string $appareil): array` (renvoie le cookie) ;
  - `session_trouver(?string $jeton): ?array` (ligne `['id', 'cree_le', 'vu_le', 'expire_le', 'jeton']`, ou `null`) ;
  - `session_prolonger_si_besoin(array $session): ?array` (cookie à renvoyer, ou `null`) ;
  - `session_supprimer(int $id): void` ; `session_supprimer_toutes(): void` ; `cookie_session(string $jeton, int $duree): array` ; `cookie_session_efface(): array`.

- [ ] **Step 1 : écrire les tests**

`tests/unitaires/test_session.php` :

```php
<?php
declare(strict_types=1);

test("session : créer pose un cookie __Host-jr sûr, la base ne garde que l'empreinte", function () {
    $pdo = base_de_test();
    $cookie = session_creer('iPhone');
    egal('__Host-jr', $cookie['nom']);
    vrai((bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $cookie['valeur']), 'jeton de 43 caractères attendu');
    $options = $cookie['options'];
    unset($options['expires']);
    egal(['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'], $options);
    egal(0, (int) $pdo->query('SELECT COUNT(*) FROM sessions WHERE empreinte = ' . $pdo->quote($cookie['valeur']))->fetchColumn());
    vrai(session_trouver($cookie['valeur']) !== null, 'session retrouvée attendue');
});

test('session : un cookie absent, inconnu ou forgé ne donne aucune session, sans erreur', function () {
    base_de_test();
    $faux = [null, '', 'abc', str_repeat('a', 43), "' OR 1=1 -- " . str_repeat('x', 31), str_repeat('é', 43), str_repeat('a', 5000)];
    foreach ($faux as $jeton) {
        egal(null, session_trouver($jeton));
    }
});

test('session : expirée après 90 jours sans visite, et effacée', function () {
    $pdo = base_de_test();
    fixer_heure('2026-10-08 10:00:00');
    $cookie = session_creer('iPhone');
    fixer_heure('2027-01-06 10:00:01');
    egal(null, session_trouver($cookie['valeur']));
    egal(0, (int) $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
});

test('session : prolongée au plus une fois par jour, pour 90 jours à partir de la visite', function () {
    $pdo = base_de_test();
    fixer_heure('2026-10-08 10:00:00');
    $cookie = session_creer('iPhone');
    fixer_heure('2026-10-09 09:00:00');
    egal(null, session_prolonger_si_besoin(session_trouver($cookie['valeur'])));
    fixer_heure('2026-10-09 11:00:00');
    $renvoye = session_prolonger_si_besoin(session_trouver($cookie['valeur']));
    egal($cookie['valeur'], $renvoye['valeur']);
    egal('2027-01-07 11:00:00', $pdo->query('SELECT expire_le FROM sessions')->fetchColumn());
});

test('session : supprimer_toutes déconnecte tous les appareils', function () {
    base_de_test();
    $a = session_creer('iPhone');
    $b = session_creer('Mac');
    session_supprimer_toutes();
    egal(null, session_trouver($a['valeur']));
    egal(null, session_trouver($b['valeur']));
});

test('session : le cookie effacé a une valeur vide et une date passée', function () {
    $cookie = cookie_session_efface();
    egal('', $cookie['valeur']);
    egal(1, $cookie['options']['expires']);
});
```

- [ ] **Step 2 : lancer les tests, vérifier qu'ils échouent**

Run : `{ php tests/run.php session ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `ÉCHEC` avec `Call to undefined function session_creer()`.

- [ ] **Step 3 : écrire `serveur/lib/session.php`**

```php
<?php
// Sessions maison (les sessions PHP d'OVH expirent après 24 min d'inactivité) : un jeton aléatoire dans
// le cookie, son empreinte SHA-256 en base, 90 jours glissants.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';

const JR_COOKIE_SESSION = '__Host-jr';
const JR_SESSION_DUREE = 7776000;
const JR_SESSION_RAFRAICHIR = 86400;

function session_creer(string $appareil): array
{
    $jeton = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $maintenant = maintenant();
    db()->prepare('INSERT INTO sessions (empreinte, cree_le, vu_le, expire_le, appareil) VALUES (?, ?, ?, ?, ?)')
        ->execute([
            hash('sha256', $jeton),
            utc($maintenant),
            utc($maintenant),
            utc($maintenant->modify('+' . JR_SESSION_DUREE . ' seconds')),
            mb_substr($appareil, 0, 120),
        ]);
    return cookie_session($jeton, JR_SESSION_DUREE);
}

// Ce qui ne ressemble pas à un jeton (43 caractères base64url) est ignoré sans requête.
function session_trouver(?string $jeton): ?array
{
    if ($jeton === null || !preg_match('/^[A-Za-z0-9_-]{43}$/', $jeton)) {
        return null;
    }
    $requete = db()->prepare('SELECT id, cree_le, vu_le, expire_le FROM sessions WHERE empreinte = ?');
    $requete->execute([hash('sha256', $jeton)]);
    $session = $requete->fetch();
    if ($session === false) {
        return null;
    }
    if (depuis_utc($session['expire_le']) <= maintenant()) {
        db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$session['id']]);
        return null;
    }
    return $session + ['jeton' => $jeton];
}

function session_prolonger_si_besoin(array $session): ?array
{
    $maintenant = maintenant();
    if ($maintenant->getTimestamp() - depuis_utc($session['vu_le'])->getTimestamp() < JR_SESSION_RAFRAICHIR) {
        return null;
    }
    db()->prepare('UPDATE sessions SET vu_le = ?, expire_le = ? WHERE id = ?')
        ->execute([utc($maintenant), utc($maintenant->modify('+' . JR_SESSION_DUREE . ' seconds')), $session['id']]);
    return cookie_session($session['jeton'], JR_SESSION_DUREE);
}

function session_supprimer(int $id): void
{
    db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
}

function session_supprimer_toutes(): void
{
    db()->exec('DELETE FROM sessions');
}

// SameSite=Lax et non Strict : au retour de la connexion Google (navigation venue d'un autre site), le
// cookie doit être envoyé. Les écritures restent protégées : POST en JSON depuis la bonne origine.
function cookie_session(string $jeton, int $duree): array
{
    return ['nom' => JR_COOKIE_SESSION, 'valeur' => $jeton, 'options' => [
        'expires' => $duree > 0 ? maintenant()->getTimestamp() + $duree : 1,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]];
}

function cookie_session_efface(): array
{
    return cookie_session('', 0);
}
```

- [ ] **Step 4 : lancer tous les tests, vérifier qu'ils passent**

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `32 réussi(s), 0 échec(s)`.

- [ ] **Step 5 : commit**

```bash
git add serveur/lib/session.php tests/unitaires/test_session.php
git commit -m "$(printf 'Lot 1 : sessions maison de 90 jours glissants\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
```

---

### Task 6 : réponses, routeur et point d'entrée

**Files:**
- Create: `serveur/lib/reponse.php`, `serveur/lib/routeur.php`, `serveur/routes/systeme.php`, `site/api/index.php`
- Modify: `tests/aide.php` (ajout de `requete`, `corps`, `routes_d_essai`)
- Test: `tests/unitaires/test_routeur.php`

**Interfaces:**
- Consumes : `session_trouver()`, `session_prolonger_si_besoin()`, `JR_COOKIE_SESSION` (tâche 5) ; `assurer_migrations()`, `version_base()`, `version_attendue()`, `db()` (tâche 3) ; `journal_erreur()`, `config()` (tâche 2).
- Produces :
  - classe `ErreurApi extends RuntimeException` avec `public readonly string $codeErreur` et `public readonly int $statut` ;
  - une **requête** est `['methode', 'chemin' (sans /api/), 'requete' (paramètres GET), 'corps_brut', 'corps' (tableau, rempli par le routeur), 'entetes' => ['origin' => ?string, 'content-type' => string], 'cookies', 'ip', 'appareil']` ;
  - une **réponse** est `['statut' => int, 'entetes' => array, 'cookies' => list<cookie>, 'corps' => string]` ;
  - une **route** est `'MÉTHODE chemin' => ['publique' => bool, 'action' => callable(array $requete, ?array $session): array]`, déclarée dans un fichier de `serveur/routes/` qui renvoie un tableau ;
  - `entetes_securite(): array` ; `reponse_json(array $donnees, int $statut = 200, array $cookies = []): array` ; `reponse_erreur(string $code, string $message, int $statut, array $cookies = []): array` ; `reponse_redirection(string $url, array $cookies = []): array` ; `emettre(array $reponse): void` ;
  - `routes(): array` (fusionne aussi `$GLOBALS['JR_ROUTES_SUPPLEMENTAIRES']`, réservé aux tests) ; `traiter(array $requete): array` ; `lire_corps_json(array $requete): array` ; constante `JR_CORPS_MAX` (65 536) ;
  - route `GET sante` (publique) : `{ok, version_app, version_base, base_ok}`.

- [ ] **Step 1 : ajouter les aides de requête à `tests/aide.php`** (à la fin du fichier)

```php
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
```

- [ ] **Step 2 : écrire les tests**

`tests/unitaires/test_routeur.php` :

```php
<?php
declare(strict_types=1);

test('routeur : GET sante répond sans session, avec la version et les en-têtes de sécurité', function () {
    base_de_test();
    $r = traiter(requete());
    egal(200, $r['statut']);
    egal(['ok' => true, 'version_app' => JR_VERSION, 'version_base' => 1, 'base_ok' => true], corps($r));
    egal('no-store', $r['entetes']['Cache-Control']);
    vrai(str_contains($r['entetes']['Content-Security-Policy'], "frame-ancestors 'none'"), 'CSP attendue');
});

test('routeur : une adresse inconnue répond 404', function () {
    base_de_test();
    $r = traiter(requete(['chemin' => 'nimporte']));
    egal(404, $r['statut']);
    egal('route_inconnue', corps($r)['erreur']['code']);
});

test('routeur : toute route non publique refuse sans session (401)', function () {
    base_de_test();
    routes_d_essai();
    $protegees = array_filter(routes(), fn(array $route) => !$route['publique']);
    vrai(count($protegees) >= 1, 'au moins une route protégée attendue');
    foreach (array_keys($protegees) as $cle) {
        [$methode, $chemin] = explode(' ', $cle, 2);
        $r = traiter(requete(['methode' => $methode, 'chemin' => $chemin, 'corps_brut' => '{}']));
        egal(401, $r['statut'], $cle);
        egal('session_expiree', corps($r)['erreur']['code'], $cle);
        egal('Ta session a expiré. Reconnecte-toi.', corps($r)['erreur']['message'], $cle);
    }
});

test('routeur : un cookie forgé sur une route protégée donne 401, jamais 500', function () {
    base_de_test();
    routes_d_essai();
    foreach (["' OR 1=1 --", str_repeat('a', 43), str_repeat('x', 10000)] as $faux) {
        egal(401, traiter(requete(['chemin' => 'essai-protegee', 'cookies' => [JR_COOKIE_SESSION => $faux]]))['statut']);
    }
});

test('routeur : une écriture exige la bonne origine et du JSON', function () {
    base_de_test();
    routes_d_essai();
    $post = ['methode' => 'POST', 'chemin' => 'essai-echo', 'corps_brut' => '{}'];
    egal(403, traiter(requete($post + ['entetes' => ['origin' => 'https://autre.site', 'content-type' => 'application/json']]))['statut']);
    egal(403, traiter(requete($post + ['entetes' => ['origin' => null, 'content-type' => 'application/json']]))['statut']);
    egal(415, traiter(requete($post + ['entetes' => ['origin' => 'http://localhost:8787', 'content-type' => 'text/plain']]))['statut']);
});

test('routeur : un corps vide, non objet ou trop gros donne 400 ou 413, jamais 500', function () {
    base_de_test();
    routes_d_essai();
    $post = fn(string $corps): array => traiter(requete(['methode' => 'POST', 'chemin' => 'essai-echo', 'corps_brut' => $corps]));
    egal(200, $post('')['statut']);
    egal([], corps($post(''))['corps']);
    egal(['a' => 1], corps($post('{"a":1}'))['corps']);
    foreach (['[1,2]', 'null', '123', '"texte"', '{"a":', "\xff\xfe"] as $mauvais) {
        egal(400, $post($mauvais)['statut'], 'corps ' . bin2hex($mauvais));
    }
    egal(413, $post('{"a":"' . str_repeat('x', JR_CORPS_MAX) . '"}')['statut']);
});

test('routeur : une panne dans une route donne 500 sobre et une ligne de journal', function () {
    base_de_test();
    routes_d_essai();
    $r = traiter(requete(['chemin' => 'essai-panne']));
    egal(500, $r['statut']);
    egal('erreur_serveur', corps($r)['erreur']['code']);
    vrai(str_contains(file_get_contents(dossier_data() . '/erreurs.log'), '| essai-panne | RuntimeException | panne simulée'), 'ligne de journal attendue');
});

test('routeur : avec une session valide, la route protégée répond, et la session est prolongée après 24 h', function () {
    base_de_test();
    routes_d_essai();
    fixer_heure('2026-10-08 10:00:00');
    $cookie = session_creer('Tests');
    $avec_cookie = ['chemin' => 'essai-protegee', 'cookies' => [JR_COOKIE_SESSION => $cookie['valeur']]];
    $r = traiter(requete($avec_cookie));
    egal(200, $r['statut']);
    egal([], $r['cookies']);
    fixer_heure('2026-10-09 11:00:00');
    $r = traiter(requete($avec_cookie));
    egal(JR_COOKIE_SESSION, $r['cookies'][0]['nom']);
    egal($cookie['valeur'], $r['cookies'][0]['valeur']);
});
```

- [ ] **Step 3 : lancer les tests, vérifier qu'ils échouent**

Run : `{ php tests/run.php routeur ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `ÉCHEC` avec `Call to undefined function traiter()`.

- [ ] **Step 4 : écrire `serveur/lib/reponse.php`**

```php
<?php
// Réponses JSON de l'API, erreurs, en-têtes de sécurité.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';

final class ErreurApi extends RuntimeException
{
    public function __construct(public readonly string $codeErreur, public readonly int $statut, string $message)
    {
        parent::__construct($message);
    }
}

// Posés sur toutes les réponses de l'API. Le .htaccess pose les mêmes sur les fichiers statiques
// (tests/unitaires/test_htaccess.php vérifie qu'ils restent identiques), plus HSTS.
function entetes_securite(): array
{
    return [
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; font-src 'self'; manifest-src 'self'; worker-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex, nofollow',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];
}

function reponse_json(array $donnees, int $statut = 200, array $cookies = []): array
{
    return [
        'statut' => $statut,
        'entetes' => entetes_securite() + ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
        'cookies' => $cookies,
        'corps' => json_encode(['ok' => $statut < 400] + $donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ];
}

function reponse_erreur(string $code, string $message, int $statut, array $cookies = []): array
{
    return reponse_json(['erreur' => ['code' => $code, 'message' => $message]], $statut, $cookies);
}

function reponse_redirection(string $url, array $cookies = []): array
{
    return [
        'statut' => 302,
        'entetes' => entetes_securite() + ['Location' => $url, 'Cache-Control' => 'no-store'],
        'cookies' => $cookies,
        'corps' => '',
    ];
}

function emettre(array $reponse): void
{
    http_response_code($reponse['statut']);
    foreach ($reponse['entetes'] as $nom => $valeur) {
        header("$nom: $valeur");
    }
    foreach ($reponse['cookies'] as $cookie) {
        setcookie($cookie['nom'], $cookie['valeur'], $cookie['options']);
    }
    echo $reponse['corps'];
}
```

- [ ] **Step 5 : écrire `serveur/lib/routeur.php`**

```php
<?php
// Logique de l'API : une requête (tableau) entre, une réponse (tableau) sort.
// Toute route exige une session, sauf si elle est déclarée publique.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/reponse.php';
require_once __DIR__ . '/session.php';

const JR_CORPS_MAX = 65536;

// Chaque fichier de serveur/routes/ renvoie ['MÉTHODE chemin' => ['publique' => bool, 'action' => callable]].
function routes(): array
{
    $routes = [];
    foreach (glob(__DIR__ . '/../routes/*.php') as $fichier) {
        $routes += require $fichier;
    }
    return $routes + ($GLOBALS['JR_ROUTES_SUPPLEMENTAIRES'] ?? []);
}

function traiter(array $requete): array
{
    try {
        $route = routes()[$requete['methode'] . ' ' . $requete['chemin']] ?? null;
        if ($route === null) {
            throw new ErreurApi('route_inconnue', 404, 'Adresse inconnue.');
        }
        $requete['corps'] = $requete['methode'] === 'GET' ? [] : lire_corps_json($requete);
        assurer_migrations();
        $session = session_trouver($requete['cookies'][JR_COOKIE_SESSION] ?? null);
        if (!$route['publique'] && $session === null) {
            throw new ErreurApi('session_expiree', 401, 'Ta session a expiré. Reconnecte-toi.');
        }
        // Prolongée avant l'action : une déconnexion pose son cookie effacé en dernier
        $prolongation = $session !== null ? session_prolonger_si_besoin($session) : null;
        $reponse = ($route['action'])($requete, $session);
        if ($prolongation !== null) {
            array_unshift($reponse['cookies'], $prolongation);
        }
        return $reponse;
    } catch (ErreurApi $e) {
        return reponse_erreur($e->codeErreur, $e->getMessage(), $e->statut);
    } catch (Throwable $e) {
        journal_erreur($requete['chemin'], $e);
        return reponse_erreur('erreur_serveur', 'Erreur du serveur. Réessaie dans un instant.', 500);
    }
}

// Écritures : POST en JSON, depuis l'origine de l'app, corps objet de 64 Ko au plus (vide accepté).
function lire_corps_json(array $requete): array
{
    if (($requete['entetes']['origin'] ?? null) !== config('origine')) {
        throw new ErreurApi('origine_refusee', 403, 'Requête refusée.');
    }
    if (!str_starts_with(strtolower((string) ($requete['entetes']['content-type'] ?? '')), 'application/json')) {
        throw new ErreurApi('type_refuse', 415, 'Requête refusée.');
    }
    if (strlen($requete['corps_brut']) > JR_CORPS_MAX) {
        throw new ErreurApi('corps_trop_gros', 413, 'Requête trop grosse.');
    }
    if ($requete['corps_brut'] === '') {
        return [];
    }
    $corps = json_decode($requete['corps_brut'], true);
    if (!is_array($corps) || ($corps !== [] && array_is_list($corps))) {
        throw new ErreurApi('json_invalide', 400, 'Requête illisible.');
    }
    return $corps;
}
```

- [ ] **Step 6 : écrire la route `sante` et le point d'entrée HTTP**

`serveur/routes/systeme.php` :

```php
<?php
declare(strict_types=1);

return [
    'GET sante' => ['publique' => true, 'action' => function (array $requete, ?array $session): array {
        $version = version_base(db());
        return reponse_json(['version_app' => JR_VERSION, 'version_base' => $version, 'base_ok' => $version === version_attendue()]);
    }],
];
```

`site/api/index.php` :

```php
<?php
// Point d'entrée unique de l'API : le .htaccess (ou outils/apercu.php en local) y renvoie toute adresse /api/…

declare(strict_types=1);

ini_set('display_errors', '0');
require __DIR__ . '/../../serveur/lib/routeur.php';

set_error_handler(function (int $niveau, string $message, string $fichier, int $ligne): bool {
    throw new ErrorException($message, 0, $niveau, $fichier, $ligne);
});

$chemin = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
emettre(traiter([
    'methode' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'chemin' => (string) preg_replace('#^/api/#', '', $chemin),
    'requete' => $_GET,
    'corps_brut' => (string) file_get_contents('php://input', false, null, 0, JR_CORPS_MAX + 1),
    'entetes' => ['origin' => $_SERVER['HTTP_ORIGIN'] ?? null, 'content-type' => $_SERVER['CONTENT_TYPE'] ?? ''],
    'cookies' => $_COOKIE,
    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'appareil' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
]));
```

- [ ] **Step 7 : lancer tous les tests, vérifier qu'ils passent**

Run : `{ php tests/run.php ; php -l site/api/index.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `40 réussi(s), 0 échec(s)` et `No syntax errors detected`.

- [ ] **Step 8 : commit**

```bash
git add serveur/lib/reponse.php serveur/lib/routeur.php serveur/routes/systeme.php site/api/index.php tests/aide.php tests/unitaires/test_routeur.php
git commit -m "$(printf 'Lot 1 : routeur de l API, réponses JSON et route sante\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
```

---

### Task 7 : connexion par mot de passe

**Files:**
- Create: `serveur/lib/connexion.php`, `serveur/routes/session.php`, `outils/empreinte.php`, `outils/definir-mot-de-passe.sh`
- Test: `tests/unitaires/test_connexion.php`

**Interfaces:**
- Consumes : `traiter()`, `requete()`, `corps()`, `ErreurApi`, `reponse_json()` (tâche 6) ; `session_creer()`, `session_supprimer()`, `session_supprimer_toutes()`, `cookie_session_efface()`, `session_trouver()` (tâche 5) ; `env_definir()`, `lire_env()` (tâche 1).
- Produces :
  - constantes `JR_CONNEXION_MAX_ECHECS` (10), `JR_CONNEXION_FENETRE` (900) ;
  - `connexion_echecs_recents(string $ip): int` ; `connexion_noter_echec(string $ip): void` ; `connexion_mot_de_passe_valide(string $mot_de_passe): bool` ; `message_essais_restants(int $restants): string` ;
  - routes `POST connexion` (publique, corps `{mot_de_passe}`), `POST deconnexion`, `POST deconnexion-partout` ;
  - outils : `outils/empreinte.php` (entrée standard vers empreinte bcrypt) ; `outils/definir-mot-de-passe.sh` (fichier visé remplaçable par `JR_ENV_FICHIER`, pour les tests).

- [ ] **Step 1 : écrire les tests**

`tests/unitaires/test_connexion.php` :

```php
<?php
declare(strict_types=1);

function connexion(string $mot_de_passe, string $ip = '203.0.113.7'): array
{
    return traiter(requete(['methode' => 'POST', 'chemin' => 'connexion', 'ip' => $ip, 'corps_brut' => json_encode(['mot_de_passe' => $mot_de_passe])]));
}

test('connexion : le bon mot de passe ouvre une session', function () {
    $pdo = base_de_test();
    $r = connexion(MOT_DE_PASSE_TEST);
    egal(200, $r['statut']);
    egal(JR_COOKIE_SESSION, $r['cookies'][0]['nom']);
    egal(1, (int) $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
});

test("connexion : un mauvais mot de passe dit combien d'essais il reste", function () {
    base_de_test();
    $r = connexion('mauvais');
    egal(401, $r['statut']);
    egal('Mot de passe incorrect. Encore 9 essais avant une pause de 15 min.', corps($r)['erreur']['message']);
    for ($i = 0; $i < 7; $i++) {
        connexion('mauvais');
    }
    egal('Mot de passe incorrect. Encore 1 essai avant une pause de 15 min.', corps(connexion('mauvais'))['erreur']['message']);
});

test("connexion : au 10e échec, puis au 11e essai même juste, c'est la pause", function () {
    base_de_test();
    for ($i = 0; $i < 9; $i++) {
        connexion('mauvais');
    }
    $r = connexion('mauvais');
    egal(429, $r['statut']);
    egal("Trop d'essais. Réessaie dans 15 min.", corps($r)['erreur']['message']);
    egal(429, connexion(MOT_DE_PASSE_TEST)['statut']);
});

test("connexion : la pause dure 15 min et ne touche que l'adresse IP fautive", function () {
    base_de_test();
    fixer_heure('2026-10-08 10:00:00');
    for ($i = 0; $i < 10; $i++) {
        connexion('mauvais');
    }
    egal(200, connexion(MOT_DE_PASSE_TEST, '198.51.100.1')['statut']);
    fixer_heure('2026-10-08 10:15:01');
    egal(200, connexion(MOT_DE_PASSE_TEST)['statut']);
});

test("connexion : un champ absent ou qui n'est pas du texte compte comme un échec", function () {
    base_de_test();
    egal(401, traiter(requete(['methode' => 'POST', 'chemin' => 'connexion', 'corps_brut' => '{}']))['statut']);
    egal(401, traiter(requete(['methode' => 'POST', 'chemin' => 'connexion', 'corps_brut' => '{"mot_de_passe":["x"]}']))['statut']);
    egal(2, connexion_echecs_recents('203.0.113.7'));
});

test("déconnexion : efface la session de l'appareil et son cookie, l'autre appareil reste connecté", function () {
    base_de_test();
    $cookie = session_creer('iPhone');
    $autre = session_creer('Mac');
    $r = traiter(requete(['methode' => 'POST', 'chemin' => 'deconnexion', 'cookies' => [JR_COOKIE_SESSION => $cookie['valeur']]]));
    egal(200, $r['statut']);
    egal('', end($r['cookies'])['valeur']);
    egal(null, session_trouver($cookie['valeur']));
    vrai(session_trouver($autre['valeur']) !== null, "l'autre appareil reste connecté");
});

test('déconnexion partout : plus aucune session', function () {
    base_de_test();
    $cookie = session_creer('iPhone');
    $autre = session_creer('Mac');
    $r = traiter(requete(['methode' => 'POST', 'chemin' => 'deconnexion-partout', 'cookies' => [JR_COOKIE_SESSION => $cookie['valeur']]]));
    egal(200, $r['statut']);
    egal(null, session_trouver($autre['valeur']));
});

test('connexion : definir-mot-de-passe.sh garde les espaces, accents, emoji, antislash et guillemets, et crée la clé de chiffrement', function () {
    base_de_test();
    $env = fichier_temporaire("DB_HOST=x\n");
    $mot_de_passe = "  é ü 🎿 \\ \$x 'q' \"d\"  ";
    $processus = proc_open(
        ['bash', dirname(__DIR__, 2) . '/outils/definir-mot-de-passe.sh'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $tubes,
        null,
        ['JR_ENV_FICHIER' => $env, 'PATH' => (string) getenv('PATH')]
    );
    fwrite($tubes[0], "$mot_de_passe\n$mot_de_passe\n");
    fclose($tubes[0]);
    stream_get_contents($tubes[1]);
    stream_get_contents($tubes[2]);
    egal(0, proc_close($processus));
    $valeurs = lire_env($env);
    vrai(password_verify($mot_de_passe, $valeurs['APP_MOT_DE_PASSE_EMPREINTE']), 'empreinte du mot de passe exact attendue');
    egal(32, strlen((string) base64_decode($valeurs['APP_CLE_CHIFFREMENT'], true)));
    egal('x', $valeurs['DB_HOST']);
    $GLOBALS['JR_CONFIG']['mot_de_passe_empreinte'] = $valeurs['APP_MOT_DE_PASSE_EMPREINTE'];
    egal(200, connexion($mot_de_passe)['statut']);
    egal(401, connexion(trim($mot_de_passe))['statut']);
});
```

- [ ] **Step 2 : lancer les tests, vérifier qu'ils échouent**

Run : `{ php tests/run.php connexion ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `ÉCHEC`, avec 404 au lieu de 200 (route `connexion` inconnue) et le script introuvable.

- [ ] **Step 3 : écrire `serveur/lib/connexion.php`**

```php
<?php
// Connexion par mot de passe : 10 échecs par adresse IP en 15 min, puis pause.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';

const JR_CONNEXION_MAX_ECHECS = 10;
const JR_CONNEXION_FENETRE = 900;

function connexion_echecs_recents(string $ip): int
{
    $requete = db()->prepare('SELECT COUNT(*) FROM essais_connexion WHERE ip = ? AND quand > ?');
    $requete->execute([$ip, utc(maintenant()->modify('-' . JR_CONNEXION_FENETRE . ' seconds'))]);
    return (int) $requete->fetchColumn();
}

function connexion_noter_echec(string $ip): void
{
    $maintenant = maintenant();
    db()->prepare('INSERT INTO essais_connexion (ip, quand) VALUES (?, ?)')->execute([mb_substr($ip, 0, 45), utc($maintenant)]);
    db()->prepare('DELETE FROM essais_connexion WHERE quand < ?')->execute([utc($maintenant->modify('-1 day'))]);
}

function connexion_mot_de_passe_valide(string $mot_de_passe): bool
{
    $empreinte = (string) config('mot_de_passe_empreinte');
    return $empreinte !== '' && password_verify($mot_de_passe, $empreinte);
}

function message_essais_restants(int $restants): string
{
    return 'Mot de passe incorrect. Encore ' . $restants . ($restants > 1 ? ' essais' : ' essai') . ' avant une pause de 15 min.';
}
```

- [ ] **Step 4 : écrire les routes de session**

`serveur/routes/session.php` :

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/connexion.php';

return [
    'POST connexion' => ['publique' => true, 'action' => function (array $requete, ?array $session): array {
        $ip = $requete['ip'];
        $echecs = connexion_echecs_recents($ip);
        if ($echecs >= JR_CONNEXION_MAX_ECHECS) {
            throw new ErreurApi('trop_d_essais', 429, "Trop d'essais. Réessaie dans 15 min.");
        }
        $mot_de_passe = $requete['corps']['mot_de_passe'] ?? null;
        if (!is_string($mot_de_passe) || !connexion_mot_de_passe_valide($mot_de_passe)) {
            connexion_noter_echec($ip);
            $restants = JR_CONNEXION_MAX_ECHECS - $echecs - 1;
            if ($restants <= 0) {
                throw new ErreurApi('trop_d_essais', 429, "Trop d'essais. Réessaie dans 15 min.");
            }
            throw new ErreurApi('mot_de_passe_incorrect', 401, message_essais_restants($restants));
        }
        return reponse_json([], 200, [session_creer($requete['appareil'])]);
    }],

    'POST deconnexion' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        session_supprimer((int) $session['id']);
        return reponse_json([], 200, [cookie_session_efface()]);
    }],

    'POST deconnexion-partout' => ['publique' => false, 'action' => function (array $requete, ?array $session): array {
        session_supprimer_toutes();
        return reponse_json([], 200, [cookie_session_efface()]);
    }],
];
```

- [ ] **Step 5 : écrire les outils du mot de passe**

`outils/empreinte.php` :

```php
<?php
// Lit un mot de passe sur l'entrée standard et écrit son empreinte bcrypt, rien d'autre.

declare(strict_types=1);

$mot_de_passe = stream_get_contents(STDIN);
if ($mot_de_passe === false || $mot_de_passe === '') {
    fwrite(STDERR, "Mot de passe vide\n");
    exit(1);
}
echo password_hash($mot_de_passe, PASSWORD_DEFAULT);
```

`outils/definir-mot-de-passe.sh` :

```bash
#!/bin/bash
# Choisir le mot de passe de Jarvis Jr. Seule son empreinte bcrypt est gardée, dans secrets/jarvis-jr.env
# (APP_MOT_DE_PASSE_EMPREINTE). Crée aussi la clé de chiffrement (APP_CLE_CHIFFREMENT) si elle manque.
# À lancer par Colin dans son terminal :
#   cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/definir-mot-de-passe.sh
# Après un changement, relancer outils/deploy-ovh.sh pour l'appliquer en ligne.

set -euo pipefail

ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FICHIER="${JR_ENV_FICHIER:-$ICI/../../../secrets/jarvis-jr.env}"
[ -f "$ENV_FICHIER" ] || { echo "Fichier de secrets introuvable : $ENV_FICHIER"; exit 1; }

echo "Choisis une phrase longue (12 caractères au moins) : tu ne la taperas qu'une fois tous les 90 jours environ."
# IFS= et -r : les espaces au début et à la fin, et les antislashs, sont gardés tels quels
IFS= read -r -s -p "Mot de passe : " MDP1; echo
IFS= read -r -s -p "Encore une fois : " MDP2; echo
[ "$MDP1" = "$MDP2" ] || { echo "Les deux saisies diffèrent. Rien n'a changé."; exit 1; }
[ "${#MDP1}" -ge 12 ] || { echo "Trop court : 12 caractères au moins. Rien n'a changé."; exit 1; }

EMPREINTE="$(printf '%s' "$MDP1" | php "$ICI/empreinte.php")"
unset MDP1 MDP2
JR_VALEUR="$EMPREINTE" php "$ICI/env-definir.php" "$ENV_FICHIER" APP_MOT_DE_PASSE_EMPREINTE

if ! grep -q '^APP_CLE_CHIFFREMENT=' "$ENV_FICHIER"; then
    JR_VALEUR="$(php -r 'echo base64_encode(sodium_crypto_secretbox_keygen());')" php "$ICI/env-definir.php" "$ENV_FICHIER" APP_CLE_CHIFFREMENT
    echo "Clé de chiffrement créée."
fi
echo "Fait. Empreinte enregistrée (le mot de passe lui-même n'est gardé nulle part)."
```

Run : `chmod +x outils/definir-mot-de-passe.sh && bash -n outils/definir-mot-de-passe.sh && php -l outils/empreinte.php`
Expected : aucune sortie de `bash -n`, puis `No syntax errors detected`.

- [ ] **Step 6 : lancer tous les tests, vérifier qu'ils passent**

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `48 réussi(s), 0 échec(s)`.

- [ ] **Step 7 : commit**

```bash
git add serveur/lib/connexion.php serveur/routes/session.php outils/empreinte.php outils/definir-mot-de-passe.sh tests/unitaires/test_connexion.php
git commit -m "$(printf 'Lot 1 : connexion par mot de passe, pause après 10 échecs, déconnexions\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
```

---

### Task 8 : écritures uniques et journal d'actions

**Files:**
- Create: `serveur/lib/ecritures.php`, `serveur/lib/actions.php`
- Test: `tests/unitaires/test_ecritures.php`, `tests/unitaires/test_actions.php`

**Interfaces:**
- Consumes : `db()`, `maintenant()`, `utc()`, `JR_DOMAINES`, `ErreurApi`, `base_de_test()`, `fixer_heure()`.
- Produces (utilisés par le plan 2 pour les routes `taches/*`, `tirage/parti` et la détection) :
  - `ecriture_unique(string $id_client, string $route, callable $action): array` : exécute `$action(): array` une seule fois par `id_client` (UUID v4 en minuscules, sinon `ErreurApi('id_client_invalide', 400)`), rend le résultat gardé aux renvois ;
  - `ecriture_trouver(string $id_client): ?array` : `['route' => string, 'recu_le' => string, 'resultat' => array]` ;
  - constante `JR_TYPES_ACTIONS` ;
  - `action_noter(string $type, string $domaine, string $carte_id, ?string $element_id, DateTimeImmutable $quand, string $source, string $precision, array $donnees = []): int` (`$source` vaut `app` ou `trello`, `$precision` vaut `exacte` ou `approx`, colonne `precision_heure`) ;
  - `action_annuler(int $id): void` ;
  - `action_app_existe_depuis(string $type, string $carte_id, ?string $element_id, DateTimeImmutable $depuis): bool` (actions de l'app non annulées).

- [ ] **Step 1 : écrire les tests**

`tests/unitaires/test_ecritures.php` :

```php
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
    egal(['carte' => 'abc', 'n' => 1], ecriture_unique(UUID_TEST, 'taches/cocher', $action));
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
```

`tests/unitaires/test_actions.php` :

```php
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
```

- [ ] **Step 2 : lancer les tests, vérifier qu'ils échouent**

Run : `{ php tests/run.php écritures ; php tests/run.php actions ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `ÉCHEC` avec `Call to undefined function ecriture_unique()` et `action_noter()`.

- [ ] **Step 3 : écrire `serveur/lib/ecritures.php`**

```php
<?php
// Écritures uniques : chaque écriture venue de l'iPhone porte un identifiant (UUID v4). Un renvoi depuis
// la file hors ligne rend le résultat déjà obtenu, sans refaire l'action.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/reponse.php';

const JR_ECRITURES_JOURS = 30;

function ecriture_unique(string $id_client, string $route, callable $action): array
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id_client)) {
        throw new ErreurApi('id_client_invalide', 400, 'Requête illisible.');
    }
    $pdo = db();
    $verrou = 'jr_ecriture_' . $id_client;
    $prise = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $prise->execute([$verrou]);
    if ((int) $prise->fetchColumn() !== 1) {
        throw new RuntimeException('Verrou de l\'écriture indisponible');
    }
    try {
        $deja = ecriture_trouver($id_client);
        if ($deja !== null) {
            return $deja['resultat'];
        }
        $resultat = $action();
        $maintenant = maintenant();
        $pdo->prepare('INSERT INTO ecritures (id_client, route, recu_le, resultat) VALUES (?, ?, ?, ?)')
            ->execute([$id_client, $route, utc($maintenant), json_encode($resultat, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $pdo->prepare('DELETE FROM ecritures WHERE recu_le < ? LIMIT 50')
            ->execute([utc($maintenant->modify('-' . JR_ECRITURES_JOURS . ' days'))]);
        return $resultat;
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$verrou]);
    }
}

function ecriture_trouver(string $id_client): ?array
{
    $requete = db()->prepare('SELECT route, recu_le, resultat FROM ecritures WHERE id_client = ?');
    $requete->execute([$id_client]);
    $ligne = $requete->fetch();
    if ($ligne === false) {
        return null;
    }
    return ['route' => $ligne['route'], 'recu_le' => $ligne['recu_le'], 'resultat' => json_decode($ligne['resultat'], true, 512, JSON_THROW_ON_ERROR)];
}
```

- [ ] **Step 4 : écrire `serveur/lib/actions.php`**

```php
<?php
// Journal des actions réelles de Colin (étape cochée, carte terminée…), tenu dès le lot 1.
// L'XP du lot 4 en sera recalculée : elle n'est jamais stockée.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';

const JR_TYPES_ACTIONS = ['etape_cochee', 'etape_decochee', 'carte_terminee', 'carte_rouverte', 'carte_ajoutee', 'tirage_parti'];

function action_noter(string $type, string $domaine, string $carte_id, ?string $element_id, DateTimeImmutable $quand, string $source, string $precision, array $donnees = []): int
{
    if (!in_array($type, JR_TYPES_ACTIONS, true) || !in_array($domaine, JR_DOMAINES, true)
        || !in_array($source, ['app', 'trello'], true) || !in_array($precision, ['exacte', 'approx'], true)) {
        throw new InvalidArgumentException("Action invalide : $type");
    }
    db()->prepare('INSERT INTO actions (type, domaine, carte_id, element_id, quand, source, precision_heure, donnees) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $type, $domaine, $carte_id, $element_id, utc($quand), $source, $precision,
            $donnees === [] ? null : json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    return (int) db()->lastInsertId();
}

function action_annuler(int $id): void
{
    db()->prepare('UPDATE actions SET annulee = 1 WHERE id = ?')->execute([$id]);
}

// Pour la détection des actions faites ailleurs : la même action a-t-elle déjà été notée par l'app ?
function action_app_existe_depuis(string $type, string $carte_id, ?string $element_id, DateTimeImmutable $depuis): bool
{
    $requete = db()->prepare("SELECT 1 FROM actions WHERE type = ? AND carte_id = ? AND element_id <=> ?
        AND source = 'app' AND annulee = 0 AND quand >= ? LIMIT 1");
    $requete->execute([$type, $carte_id, $element_id, utc($depuis)]);
    return $requete->fetchColumn() !== false;
}
```

- [ ] **Step 5 : lancer tous les tests, vérifier qu'ils passent**

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `56 réussi(s), 0 échec(s)`.

- [ ] **Step 6 : commit**

```bash
git add serveur/lib/ecritures.php serveur/lib/actions.php tests/unitaires/test_ecritures.php tests/unitaires/test_actions.php
git commit -m "$(printf 'Lot 1 : écritures uniques et journal d actions\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
```

---

### Task 9 : fichiers statiques, .htaccess, aperçu local et test HTTP de bout en bout

**Files:**
- Create: `site/.htaccess`, `site/robots.txt`, `outils/apercu.php`
- Modify: `tests/aide.php` (ajout de `requete_http`, `attendre_port`)
- Test: `tests/unitaires/test_htaccess.php`, `tests/integration/test_http.php`

**Interfaces:**
- Consumes : `entetes_securite()` (tâche 6) ; tout le serveur (tâches 2 à 8) ; `config_de_test()`, `base_de_test()`, `fichier_temporaire()`.
- Produces :
  - `outils/apercu.php`, routeur de `php -S localhost:8787 -t site outils/apercu.php`, utilisé par le plan 3 et par Playwright ;
  - `requete_http(string $methode, string $url, array $entetes = [], ?string $corps = null): array` → `['statut' => int, 'entetes' => array<string nom en minuscules, list<string>>, 'corps' => string]` ;
  - `attendre_port(int $port): void`.

- [ ] **Step 1 : ajouter les aides HTTP à `tests/aide.php`** (à la fin du fichier)

```php
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
```

- [ ] **Step 2 : écrire les tests**

`tests/unitaires/test_htaccess.php` :

```php
<?php
declare(strict_types=1);

function lire_htaccess(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2) . '/site/.htaccess');
}

test("htaccess : les en-têtes de sécurité sont ceux de l'API, plus HSTS", function () {
    preg_match_all('/^\s*Header always set (\S+) "([^"]*)"$/m', lire_htaccess(), $trouves, PREG_SET_ORDER);
    $entetes = [];
    foreach ($trouves as [, $nom, $valeur]) {
        $entetes[$nom] = $valeur;
    }
    $attendus = entetes_securite() + ['Strict-Transport-Security' => 'max-age=31536000'];
    ksort($entetes);
    ksort($attendus);
    egal($attendus, $entetes);
});

test('htaccess : manifeste typé, HTTPS forcé, API réécrite, pas de liste de dossier, pas de cache sur index.html, sw.js et le manifeste', function () {
    $htaccess = lire_htaccess();
    $attendus = [
        'AddType application/manifest+json .webmanifest',
        'RewriteCond %{SERVER_PORT} ^80$',
        'RewriteRule ^api/index\.php$ - [L]',
        'RewriteRule ^api/ api/index.php [L,QSA]',
        'Options -Indexes',
        '<FilesMatch "^(index\.html|sw\.js|manifest\.webmanifest)$">',
        'Header set Cache-Control "no-cache"',
    ];
    foreach ($attendus as $attendu) {
        vrai(str_contains($htaccess, $attendu), "manque dans .htaccess : $attendu");
    }
});
```

`tests/integration/test_http.php` :

```php
<?php
declare(strict_types=1);

test("HTTP : l'aperçu local sert l'API et les fichiers avec les bons en-têtes, connexion et déconnexion comprises", function () {
    base_de_test();
    $port = 18000 + random_int(0, 999);
    $origine = "http://127.0.0.1:$port";
    $config = fichier_temporaire('<?php return ' . var_export(config_de_test(['origine' => $origine]), true) . ';');
    chmod($config, 0600);
    $racine = dirname(__DIR__, 2);
    $env = getenv();
    $env['JR_CONFIG_FICHIER'] = $config;
    $serveur = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', "$racine/site", "$racine/outils/apercu.php"],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $tubes,
        $racine,
        $env
    );
    try {
        attendre_port($port);
        $json = ['Origin: ' . $origine, 'Content-Type: application/json'];

        $sante = requete_http('GET', "$origine/api/sante");
        egal(200, $sante['statut']);
        egal(true, json_decode($sante['corps'], true)['base_ok']);

        $robots = requete_http('GET', "$origine/robots.txt");
        egal(200, $robots['statut']);
        egal('no-cache', $robots['entetes']['cache-control'][0] ?? null);
        vrai(isset($robots['entetes']['content-security-policy']), 'CSP attendue sur les fichiers statiques');

        egal(404, requete_http('GET', "$origine/.htaccess")['statut']);
        egal(401, requete_http('POST', "$origine/api/deconnexion", $json, '{}')['statut']);
        egal(403, requete_http('POST', "$origine/api/connexion", ['Origin: https://autre.site', 'Content-Type: application/json'], '{"mot_de_passe":"x"}')['statut']);
        egal(401, requete_http('POST', "$origine/api/connexion", $json, '{"mot_de_passe":"mauvais"}')['statut']);

        $ok = requete_http('POST', "$origine/api/connexion", $json, json_encode(['mot_de_passe' => MOT_DE_PASSE_TEST]));
        egal(200, $ok['statut']);
        $cookie = $ok['entetes']['set-cookie'][0] ?? '';
        foreach (['__Host-jr=', 'path=/', 'secure', 'httponly', 'samesite=lax'] as $attendu) {
            vrai(str_contains(strtolower($cookie), $attendu), "cookie sans $attendu");
        }
        vrai((bool) preg_match('/Max-Age=777[56]\d{3}/', $cookie), 'Max-Age de 90 jours attendu');

        $jeton = explode(';', substr($cookie, strlen('__Host-jr=')))[0];
        egal(200, requete_http('POST', "$origine/api/deconnexion", array_merge($json, ['Cookie: __Host-jr=' . $jeton]), '')['statut']);
    } finally {
        proc_terminate($serveur);
        proc_close($serveur);
    }
});
```

- [ ] **Step 3 : lancer les tests, vérifier qu'ils échouent**

Run : `{ php tests/run.php htaccess ; php tests/run.php HTTP ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `ÉCHEC` (`.htaccess` absent, le serveur d'aperçu ne répond pas ou renvoie 404).

- [ ] **Step 4 : écrire `site/.htaccess` et `site/robots.txt`**

`site/.htaccess` :

```apache
# Jarvis Jr. : règles Apache de jr.qyris.fr (OVH mutualisé).
# Les en-têtes de sécurité doivent rester identiques à entetes_securite() dans serveur/lib/reponse.php
# (vérifié par tests/unitaires/test_htaccess.php).

Options -Indexes
AddDefaultCharset utf-8
AddType application/manifest+json .webmanifest

RewriteEngine On

# HTTP vers HTTPS (règle recommandée par OVH : il sert les deux sur le même dossier)
RewriteCond %{SERVER_PORT} ^80$
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

# API : point d'entrée unique
RewriteRule ^api/index\.php$ - [L]
RewriteRule ^api/ api/index.php [L,QSA]

<IfModule mod_headers.c>
    Header always set Content-Security-Policy "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; font-src 'self'; manifest-src 'self'; worker-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "no-referrer"
    Header always set X-Robots-Tag "noindex, nofollow"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=()"
    Header always set Strict-Transport-Security "max-age=31536000"

    # OVH ajoute max-age=900 aux fichiers statiques : ceux-ci doivent toujours être revalidés
    <FilesMatch "^(index\.html|sw\.js|manifest\.webmanifest)$">
        Header set Cache-Control "no-cache"
    </FilesMatch>
</IfModule>
```

`site/robots.txt` :

```
User-agent: *
Disallow: /
```

- [ ] **Step 5 : écrire `outils/apercu.php`**

```php
<?php
// Routeur de l'aperçu local : php -S localhost:8787 -t site outils/apercu.php
// Imite le .htaccess : /api/… vers le point d'entrée, en-têtes de sécurité, pas de cache, fichiers cachés refusés.

declare(strict_types=1);

$chemin = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (str_starts_with($chemin, '/api/')) {
    require __DIR__ . '/../site/api/index.php';
    return true;
}

require_once __DIR__ . '/../serveur/lib/reponse.php';
foreach (entetes_securite() as $nom => $valeur) {
    header("$nom: $valeur");
}
header('Cache-Control: no-cache');

$site = (string) realpath(__DIR__ . '/../site');
$fichier = realpath($site . ($chemin === '/' ? '/index.html' : $chemin));
if ($fichier === false || !str_starts_with($fichier, $site . '/') || is_dir($fichier) || str_contains($chemin, '/.')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Introuvable';
    return true;
}

$types = [
    'html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
    'json' => 'application/json', 'webmanifest' => 'application/manifest+json', 'png' => 'image/png',
    'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'txt' => 'text/plain; charset=utf-8',
    'ico' => 'image/x-icon',
];
header('Content-Type: ' . ($types[strtolower(pathinfo($fichier, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
readfile($fichier);
return true;
```

- [ ] **Step 6 : lancer tous les tests, vérifier qu'ils passent**

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `59 réussi(s), 0 échec(s)`.

- [ ] **Step 7 : commit**

```bash
git add site/.htaccess site/robots.txt outils/apercu.php tests/aide.php tests/unitaires/test_htaccess.php tests/integration/test_http.php
git commit -m "$(printf 'Lot 1 : .htaccess, aperçu local et test HTTP de bout en bout\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
```

---

### Task 10 : déploiement et première mise en ligne

**Files:**
- Create: `outils/deploy-ovh.sh`
- Modify: `README.md` (statut, prochaine session, fichiers)

**Interfaces:**
- Consumes : `outils/ovh-sftp.sh` (existant, SFTP, une seule tentative) ; `outils/generer-config.php` (tâche 1) ; `tests/run.php` ; la route `GET sante` (tâche 6).
- Produces : `outils/deploy-ovh.sh`, réutilisé par les plans 2 et 3 et par les lots suivants.

- [ ] **Step 1 : écrire `outils/deploy-ovh.sh`**

```bash
#!/bin/bash
# Mise en ligne de Jarvis Jr. sur jr.qyris.fr. À lancer par Colin dans son terminal :
#   cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/deploy-ovh.sh
# 1. tests  2. config de production  3. simulation  4. confirmation  5. envoi  6. vérification
# Garde en ligne : www/.ovhconfig, serveur/data/ (journal). config.php et trello-ids.json sont posés à part.

set -euo pipefail

ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJET="$(cd "$ICI/.." && pwd)"
SFTP="$ICI/ovh-sftp.sh"
FILTRE="$HOME/.claude/hooks/redact-secrets"
[ -x "$FILTRE" ] || { echo "Filtre des secrets introuvable : $FILTRE"; exit 1; }

echo "1/6 Tests"
if ! php "$PROJET/tests/run.php" 2>&1 | "$FILTRE"; then
    echo "Tests en échec : rien n'est envoyé. (MySQL local lancé ? brew services run mysql@8.4)"
    exit 1
fi

echo "2/6 Configuration de production"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
php "$ICI/generer-config.php" --mode=production --dossier="$TMP" 2>&1 | "$FILTRE"

EXCLUS_SITE="--exclude-glob .ovhconfig --exclude-glob .DS_Store"
EXCLUS_SERVEUR="--exclude-glob config.php --exclude-glob trello-ids.json --exclude-glob data/ --exclude-glob .DS_Store"
miroirs() {  # $1 : --dry-run, ou rien
    echo "mirror --reverse --delete --verbose $1 $EXCLUS_SITE '$PROJET/site/' 'jr.qyris.fr/www/';
mirror --reverse --delete --verbose $1 $EXCLUS_SERVEUR '$PROJET/serveur/' 'jr.qyris.fr/serveur/';"
}

echo "3/6 Simulation (rien n'est modifié)"
"$SFTP" "$(miroirs --dry-run)" 2>&1 | "$FILTRE"
echo
read -r -p "4/6 Envoyer ? (o/N) " REPONSE
[ "$REPONSE" = "o" ] || { echo "Rien n'a été envoyé."; exit 0; }

echo "5/6 Envoi"
"$SFTP" "$(miroirs '')
put '$TMP/config.php' -o 'jr.qyris.fr/serveur/config.php';
chmod 600 jr.qyris.fr/serveur/config.php;
put '$TMP/trello-ids.json' -o 'jr.qyris.fr/serveur/trello-ids.json';" 2>&1 | "$FILTRE"

echo "6/6 Vérification"
curl -sS --max-time 20 https://jr.qyris.fr/api/sante | "$FILTRE"
echo
```

Run : `chmod +x outils/deploy-ovh.sh && bash -n outils/deploy-ovh.sh && echo syntaxe-ok`
Expected : `syntaxe-ok`.

- [ ] **Step 2 : revue de code et revue de sécurité avant la mise en ligne**

Lancer le skill `code-review` sur les changements depuis le commit `c2c6ed6`, puis le skill `security-review`. Corriger chaque constat retenu, en ajoutant le test qui le prouve, et relancer :

Run : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `59 réussi(s), 0 échec(s)` (plus les tests ajoutés par les corrections).

- [ ] **Step 3 : Colin choisit son mot de passe**

Lui donner la commande, à lancer dans le terminal de VS Code :

```bash
cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/definir-mot-de-passe.sh
```

Vérifier ensuite la présence des deux clés, sans leur valeur :

Run : `{ grep -o '^[A-Z_]*=' ~/Desktop/jarvis-starter-kit/secrets/jarvis-jr.env ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : la liste contient `APP_MOT_DE_PASSE_EMPREINTE=` et `APP_CLE_CHIFFREMENT=`.

- [ ] **Step 4 : Colin lance la mise en ligne**

Lui donner la commande :

```bash
cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/deploy-ovh.sh
```

Lui dire quoi regarder dans la simulation : des lignes d'envoi pour `site/` et `serveur/`, et aucune suppression de `.ovhconfig`, `config.php` ni `data/`. Il répond `o`, puis colle la dernière ligne.
Expected : `{"ok":true,"version_app":"0.1.0","version_base":1,"base_ok":true}`.

- [ ] **Step 5 : vérifier en ligne (lecture seule)**

Run :

```bash
{ for u in https://jr.qyris.fr/robots.txt http://jr.qyris.fr/robots.txt https://jr.qyris.fr/serveur/config.php https://jr.qyris.fr/api/inconnue; do echo "=== $u"; curl -sS -I --max-time 15 "$u" | grep -i -E '^HTTP|^location|^content-security|^strict-transport|^x-robots|^x-powered-by|^cache-control'; done
curl -sS --max-time 15 -X POST -H 'Content-Type: application/json' -H 'Origin: https://jr.qyris.fr' -d '{}' https://jr.qyris.fr/api/deconnexion; echo
curl -sS --max-time 15 -X POST -H 'Content-Type: application/json' -H 'Origin: https://autre.site' -d '{"mot_de_passe":"x"}' https://jr.qyris.fr/api/connexion; echo ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

Expected :
- `robots.txt` en HTTPS : 200, CSP, HSTS, `X-Robots-Tag`, pas de `X-Powered-By` ;
- en HTTP : `301` avec `location: https://jr.qyris.fr/robots.txt` ;
- `serveur/config.php` : 404 (le dossier n'est pas dans le web) ;
- `api/inconnue` : 404 ;
- déconnexion sans session : `{"ok":false,"erreur":{"code":"session_expiree",…}}` ;
- connexion depuis une autre origine : `origine_refusee`.

Si la redirection HTTP boucle (OVH termine le HTTPS en amont), remplacer `RewriteCond %{SERVER_PORT} ^80$` par `RewriteCond %{HTTP:X-Forwarded-Proto} !https`, en faisant de même dans le test de la tâche 9, puis faire relancer le déploiement par Colin.

- [ ] **Step 6 : mettre à jour le README et faire un commit**

Dans `README.md` :
- dans « Statut », ajouter une ligne datée du jour de la mise en ligne : « lot 1, plan 1 (socle serveur) fait et en ligne : base migrée, connexion, sessions, `/api/sante`. Plan : `plans/2026-10-08-lot1-1-socle-serveur.md` » ;
- remplacer « Prochaine session » par : « 1. Écrire et exécuter le plan 2 du lot 1 (données : Trello, Google, créneaux, rafraîchissement, routes `donnees`, `agenda`, `taches/*`). 2. Puis le plan 3 (l'app iPhone). » ;
- dans « Fichiers », ajouter : `SPEC.md` (spec technique), `plans/` (plans d'implémentation), `serveur/` (API PHP), `tests/` (`php tests/run.php`, à travers le filtre), `outils/deploy-ovh.sh` et `outils/definir-mot-de-passe.sh` (lancés par Colin), `outils/apercu.php` (aperçu local).

```bash
git add outils/deploy-ovh.sh README.md
git commit -m "$(printf 'Lot 1 : script de déploiement, socle serveur en ligne\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
```

---

## Couverture du spec par ce plan

| Section du spec | Tâche |
|---|---|
| 3 (architecture, point d'entrée unique, routes déclarées) | 6 |
| 4.1, 4.2, 4.4 (règles, tables, migrations) | 3 |
| 5.1 (mot de passe, 10 essais) | 7 |
| 5.2 (session, cookie, 90 jours glissants) | 5 |
| 5.3 (origine, JSON) | 6 |
| 5.4 (routes `sante`, `connexion`, `deconnexion`, `deconnexion-partout`) | 6, 7 |
| 5.6 (en-têtes, `.htaccess`) | 9 |
| 9 (secrets, `config.php`, écritures simulées en dev) | 1, 7 |
| 10 (dev local, déploiement) | 3, 9, 10 |
| 11 (erreurs, journal) | 2, 6 |
| 12.1 (tests PHP du socle) | 1 à 9 |
| 4.2 `ecritures`, `actions` (utilisées par le plan 2) | 8 |
| 4.5, 5.4 (autres routes), 5.5, 6, 7, 8, 12.2, 12.3 | Plans 2 et 3 |
