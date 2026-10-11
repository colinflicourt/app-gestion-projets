# Jarvis Jr., lot 2, plan 1 sur 3 : le socle des notifications

> **BROUILLON du 2026-10-11.** Le SPEC § 15 a été validé par Colin le même jour. Les tâches 1 à 5 sont détaillées (le code de la tâche 2 et la liste blanche de la tâche 3 ont déjà tourné dans un essai). La tâche 6 est seulement esquissée et doit être détaillée avant d'exécuter le plan.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Le serveur sait envoyer une notification web chiffrée (VAPID et aes128gcm) à chaque appareil abonné, et l'iPhone sait s'abonner, recevoir et afficher une notification de test.

**Architecture:** Un module pur `serveur/lib/push.php` (base64url, clés P-256, chiffrement RFC 8291, JWT VAPID), un module `serveur/lib/abonnements.php` (table `abonnements`, liste blanche des endpoints, envoi par `http_requete()` et suites selon le code HTTP), des routes dans `serveur/routes/notifications.php`, puis côté iPhone les événements `push` et `notificationclick` de `sw.js` et la partie Notifications de Réglages.

**Tech Stack:** PHP 8.4 sans Composer (openssl : ECDH, ES256, aes-128-gcm ; `hash_hkdf`), MySQL 8.4, Push API et Notifications API du navigateur (iOS 16.4 et plus, app installée).

**Spec:** `projects/jarvis-jr/SPEC.md` § 15 (validé par Colin le 2026-10-11), et § 4, 5, 9, 12 pour le socle.

**Le lot 2 en 3 plans** :
1. **Ce plan : le socle des notifications** (envoi, abonnement, notification de test).
2. Le planificateur (`POST /api/cron`, `envois`, génération des rappels automatiques et du brief, règles de péremption, heure d'hiver).
3. Rappels manuels (capture), Siri, reste de Réglages, bloc « Rappels du jour », mise en ligne et recette du lot 2 (SPEC § 15.9).

Tous les chemins sont relatifs à `projects/jarvis-jr/` (dépôt git du projet). Tests : `php tests/run.php` (chaque `tests/unitaires/test_*.php` est chargé, chaque fichier de `serveur/lib/` aussi). Avant de commencer : MySQL local lancé (`{ brew services run mysql@8.4 ; } 2>&1 | ~/.claude/hooks/redact-secrets`) et suite actuelle au vert (159 tests PHP).

## Global Constraints

- PHP 8.4 sur le serveur, 8.5 sur le Mac : rien de propre à 8.5. Pas de Composer, aucune bibliothèque externe. `declare(strict_types=1);` partout. Noms, commentaires et messages en français, commentaires rares.
- Ne pas passer `$key_length` à `openssl_pkey_derive` (obsolète en 8.5). `openssl_verify` prend la clé publique (`openssl_pkey_get_details($cle)['key']`).
- **Aucun réseau dans les tests** : envoi par `http_requete()`, remplacée par `faux_http()`.
- Dates en base en UTC, heure venue de `maintenant()` : écrire avec `utc(maintenant())` et relire avec `depuis_utc()` (`amorce.php`), comme le reste du serveur, jamais `maintenant()->format(…)`.
- **Journal** : jamais d'endpoint complet (il identifie l'appareil), jamais de clé, jamais le texte d'une notification.
- Toute commande qui touche aux fichiers de secrets ou à `config.php` passe par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`.
- Endpoints acceptés : HTTPS, hôte `web.push.apple.com`, `*.push.apple.com`, `fcm.googleapis.com` ou `updates.push.services.mozilla.com`.

## Review Focus

- Un abonnement mort (410) ne doit pas faire échouer l'envoi aux autres appareils : test dans la tâche 3.
- Un endpoint `https://web.push.apple.com.exemple.fr/…` (faux suffixe) doit être refusé : test dans la tâche 3.
- Un iPhone qui renvoie le même abonnement à chaque ouverture ne crée pas de doublon : test dans la tâche 4.
- Une notification au contenu illisible s'affiche quand même (sinon iOS coupe l'abonnement) : tâche 5.
- Une signature DER dont `r` ou `s` commence par un octet nul (33 octets) donne bien 64 octets : test dans la tâche 2.

---

### Task 1: Table `abonnements`, secrets VAPID et outil de génération

**Files:**
- Create: `serveur/migrations/003_notifications.sql`
- Create: `outils/generer-vapid.php`
- Modify: `outils/generer-config.php` (ajouter `vapid` au tableau écrit)
- Modify: `tests/aide.php` (`config_de_test()` : clés VAPID de test)
- Test: `tests/unitaires/test_base.php` (version attendue 3)

**Interfaces:**
- Produces: `config('vapid')` → `['publique' => base64url (65 octets), 'privee' => base64url (32 octets), 'sujet' => 'mailto:contact@qyris.fr']`.

- [ ] **Step 1 : la migration**

```sql
-- Lot 2, plan 1 : un abonnement aux notifications par appareil. Heures en UTC.
CREATE TABLE IF NOT EXISTS abonnements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  endpoint TEXT NOT NULL,
  empreinte CHAR(64) NOT NULL,
  p256dh VARCHAR(100) NOT NULL,
  auth VARCHAR(30) NOT NULL,
  session_id INT UNSIGNED NULL,
  cree_le DATETIME NOT NULL,
  vu_le DATETIME NOT NULL,
  echecs TINYINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY empreinte (empreinte)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

Les tables `rappels` et `envois` viennent au plan 2 (migration 004), pour qu'une migration ne crée que ce que son plan utilise.

- [ ] **Step 2 : mettre à jour le test de version** (`test_base.php` : la version attendue passe de 2 à 3), le voir échouer avant la migration puis passer avec : `php tests/run.php`

- [ ] **Step 3 : `outils/generer-vapid.php`** écrit une paire P-256 en base64url, à coller par Colin dans le fichier de secrets de l'app (`VAPID_PUBLIQUE`, `VAPID_PRIVEE`, `VAPID_SUJET`). Il n'affiche **que la clé publique** et écrit les trois lignes à la fin du fichier de secrets lui-même si les clés n'y sont pas encore (jamais d'écrasement).

```php
<?php
declare(strict_types=1);
// Génère la paire VAPID une fois pour toutes. Usage : php outils/generer-vapid.php
$fichier = dirname(__DIR__, 3) . '/secrets/jarvis-jr.env';
$texte = (string) file_get_contents($fichier);
if (str_contains($texte, 'VAPID_PRIVEE=')) {
    fwrite(STDERR, "Clés VAPID déjà présentes, rien n'est changé.\n");
    exit(1);
}
$cle = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
$d = openssl_pkey_get_details($cle)['ec'];
$b64 = fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
$publique = $b64("\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT));
$privee = $b64(str_pad($d['d'], 32, "\0", STR_PAD_LEFT));
file_put_contents($fichier, rtrim($texte, "\n") . "\nVAPID_PUBLIQUE=$publique\nVAPID_PRIVEE=$privee\nVAPID_SUJET=mailto:contact@qyris.fr\n");
echo "Clés VAPID ajoutées. Clé publique : $publique\n";
```

- [ ] **Step 4 : `generer-config.php`** ajoute `'vapid' => ['publique' => $env['VAPID_PUBLIQUE'], 'privee' => $env['VAPID_PRIVEE'], 'sujet' => $env['VAPID_SUJET']]` (lire d'abord le fichier : suivre la façon dont il construit les autres entrées et son contrôle des clés manquantes). `config_de_test()` reçoit les clés du vecteur RFC 8291 (`as_public`, `as_private` de la tâche 2).

- [ ] **Step 5 : suite au vert, commit** `Lot 2 : table abonnements et clés VAPID`

### Task 2: `push.php`, chiffrement RFC 8291 et JWT VAPID (fonctions pures)

**Files:**
- Create: `serveur/lib/push.php`
- Test: `tests/unitaires/test_push.php`
- Modify: `tests/fabriques.php` (les 7 constantes `RFC_*` ci-dessous y vont, car `test_abonnements.php` de la tâche 3 les utilise et se charge avant `test_push.php`)

**Interfaces:**
- Produces:
  - `b64url_encoder(string $octets): string`, `b64url_decoder(string $texte): string` (lève `InvalidArgumentException` si invalide) ;
  - `cle_p256(string $publique65, ?string $privee32 = null): OpenSSLAsymmetricKey` ;
  - `chiffrer_message(string $texte, string $p256dh, string $auth, ?OpenSSLAsymmetricKey $ephemere = null, ?string $sel = null): string` (corps aes128gcm complet ; `$p256dh` et `$auth` en octets ; éphémère et sel aléatoires si nuls) ;
  - `vapid_entete(string $endpoint, array $vapid, int $maintenant): string` (valeur complète de l'en-tête `Authorization`).

- [ ] **Step 1 : les tests, qui échouent**

```php
// Dans tests/fabriques.php : RFC 8291, annexe A
const RFC_TEXTE = 'When I grow up, I want to be a watermelon';
const RFC_AS_PUB = 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8';
const RFC_AS_PRIV = 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw';
const RFC_UA_PUB = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
const RFC_SEL = 'DGv6ra1nlYgDCS1FRnbzlw';
const RFC_AUTH = 'BTBZMqHH6r4Tts7J_aSIgg';
const RFC_CORPS = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

// tests/unitaires/test_push.php
<?php
declare(strict_types=1);

test('push : le vecteur de la RFC 8291 est rejoué octet pour octet', function () {
    $ephemere = cle_p256(b64url_decoder(RFC_AS_PUB), b64url_decoder(RFC_AS_PRIV));
    $corps = chiffrer_message(RFC_TEXTE, b64url_decoder(RFC_UA_PUB), b64url_decoder(RFC_AUTH), $ephemere, b64url_decoder(RFC_SEL));
    egal(RFC_CORPS, b64url_encoder($corps));
});

test('push : sans éphémère ni sel imposés, deux chiffrements diffèrent', function () {
    $a = chiffrer_message('x', b64url_decoder(RFC_UA_PUB), b64url_decoder(RFC_AUTH));
    $b = chiffrer_message('x', b64url_decoder(RFC_UA_PUB), b64url_decoder(RFC_AUTH));
    vrai($a !== $b, 'clé éphémère et sel aléatoires attendus');
});

test('push : en-tête VAPID valide, audience = origine de l\'endpoint, signature vérifiable', function () {
    $vapid = ['publique' => RFC_AS_PUB, 'privee' => RFC_AS_PRIV, 'sujet' => 'mailto:contact@qyris.fr'];
    $entete = vapid_entete('https://web.push.apple.com/QGuQ', $vapid, 1_760_000_000);
    vrai((bool) preg_match('/^vapid t=([^.]+)\.([^.]+)\.([^,]+), k=(.+)$/', $entete, $m), $entete);
    egal(RFC_AS_PUB, $m[4]);
    $charge = json_decode(b64url_decoder($m[2]), true);
    egal('https://web.push.apple.com', $charge['aud']);
    egal(1_760_000_000 + 12 * 3600, $charge['exp']);
    $sig = b64url_decoder($m[3]);
    egal(64, strlen($sig));
    $der = der_depuis_brut($sig);
    $publique = openssl_pkey_get_details(cle_p256(b64url_decoder(RFC_AS_PUB), b64url_decoder(RFC_AS_PRIV)))['key'];
    egal(1, openssl_verify("$m[1].$m[2]", $der, $publique, OPENSSL_ALGO_SHA256));
});

test('push : r ou s de 33 octets (octet nul en tête) donne 64 octets', function () {
    $r = "\x00" . str_repeat("\x80", 32);
    $s = str_repeat("\x01", 32);
    $der = "\x30" . chr(4 + 33 + 32) . "\x02\x21" . $r . "\x02\x20" . $s;
    egal(str_repeat("\x80", 32) . $s, brut_depuis_der($der));
});

test('push : base64url invalide refusé', function () {
    leve(InvalidArgumentException::class, fn() => b64url_decoder('pas*valide'));
});
```

Lancer : `php tests/run.php` → échec, fonctions inconnues.

- [ ] **Step 2 : l'implémentation** (code éprouvé le 2026-10-11 contre le vecteur, dans le scratchpad de la session)

```php
<?php
declare(strict_types=1);

// Notifications web : chiffrement aes128gcm (RFC 8291 et 8188) et JWT VAPID (RFC 8292). Calcul pur.

function b64url_encoder(string $octets): string
{
    return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
}

function b64url_decoder(string $texte): string
{
    $octets = preg_match('/^[A-Za-z0-9_-]*$/', $texte) ? base64_decode(strtr($texte, '-_', '+/'), true) : false;
    if ($octets === false) {
        throw new InvalidArgumentException('base64url invalide');
    }
    return $octets;
}

function cle_p256(string $publique65, ?string $privee32 = null): OpenSSLAsymmetricKey
{
    if (strlen($publique65) !== 65 || $publique65[0] !== "\x04") {
        throw new InvalidArgumentException('clé publique P-256 invalide');
    }
    $ec = ['curve_name' => 'prime256v1', 'x' => substr($publique65, 1, 32), 'y' => substr($publique65, 33, 32)];
    if ($privee32 !== null) {
        $ec['d'] = $privee32;
    }
    $cle = openssl_pkey_new(['ec' => $ec]);
    if ($cle === false) {
        throw new InvalidArgumentException('clé P-256 refusée');
    }
    return $cle;
}

function publique_brute(OpenSSLAsymmetricKey $cle): string
{
    $ec = openssl_pkey_get_details($cle)['ec'];
    return "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
}

function chiffrer_message(string $texte, string $p256dh, string $auth, ?OpenSSLAsymmetricKey $ephemere = null, ?string $sel = null): string
{
    $ephemere ??= openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $sel ??= random_bytes(16);
    $as_pub = publique_brute($ephemere);
    $secret = openssl_pkey_derive(cle_p256($p256dh), $ephemere);
    $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $p256dh . $as_pub, $auth);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sel);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sel);
    $chiffre = openssl_encrypt($texte . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $sel . pack('N', 4096) . chr(65) . $as_pub . $chiffre . $tag;
}

// Signature ES256 : openssl rend du DER, JWT attend r‖s sur 64 octets
function brut_depuis_der(string $der): string
{
    $o = 3;
    $r = substr($der, $o + 1, ord($der[$o]));
    $o += 1 + ord($der[$o]) + 1;
    $s = substr($der, $o + 1, ord($der[$o]));
    $bloc = fn(string $n): string => str_pad(ltrim($n, "\0"), 32, "\0", STR_PAD_LEFT);
    return $bloc($r) . $bloc($s);
}

function der_depuis_brut(string $brut): string
{
    $entier = function (string $n): string {
        $n = ltrim($n, "\0");
        if ($n === '' || ord($n[0]) & 0x80) {
            $n = "\0" . $n;
        }
        return "\x02" . chr(strlen($n)) . $n;
    };
    $corps = $entier(substr($brut, 0, 32)) . $entier(substr($brut, 32, 32));
    return "\x30" . chr(strlen($corps)) . $corps;
}

function vapid_entete(string $endpoint, array $vapid, int $maintenant): string
{
    $p = parse_url($endpoint);
    $charge = ['aud' => $p['scheme'] . '://' . $p['host'], 'exp' => $maintenant + 12 * 3600, 'sub' => $vapid['sujet']];
    $signe = b64url_encoder('{"typ":"JWT","alg":"ES256"}') . '.' . b64url_encoder(json_encode($charge, JSON_UNESCAPED_SLASHES));
    $cle = cle_p256(b64url_decoder($vapid['publique']), b64url_decoder($vapid['privee']));
    openssl_sign($signe, $der, $cle, OPENSSL_ALGO_SHA256);
    return 'vapid t=' . $signe . '.' . b64url_encoder(brut_depuis_der($der)) . ', k=' . $vapid['publique'];
}
```

- [ ] **Step 3 : suite au vert** : `php tests/run.php`
- [ ] **Step 4 : commit** `Lot 2 : chiffrement des notifications (vecteur RFC 8291) et JWT VAPID`

### Task 3: `abonnements.php`, liste blanche et envoi

**Files:**
- Create: `serveur/lib/abonnements.php`
- Test: `tests/unitaires/test_abonnements.php`

**Interfaces:**
- Consumes: `chiffrer_message()`, `vapid_entete()`, `b64url_decoder()` (tâche 2) ; `http_requete()`, `db()`, `maintenant()`, `config()`.
- Produces:
  - `endpoint_accepte(string $url): bool` ;
  - `abonnement_enregistrer(array $abonnement, ?int $session_id): int` (`$abonnement` = `['endpoint', 'keys' => ['p256dh', 'auth']]` tel que rendu par `PushSubscription.toJSON()` ; lève `ErreurApi('abonnement_invalide', 400, …)`) ;
  - `abonnement_supprimer(string $endpoint): void` ;
  - `notifier_tous(array $message, string $urgence = 'normal', int $ttl = 43200): array` → `['envoyes' => int, 'supprimes' => int, 'echecs' => int]`.

- [ ] **Step 1 : les tests, qui échouent**

```php
<?php
declare(strict_types=1);

function abonnement_exemple(string $endpoint = 'https://web.push.apple.com/QGuQ-abc'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => RFC_UA_PUB, 'auth' => RFC_AUTH]];
}

test('abonnements : liste blanche des endpoints', function () {
    vrai(endpoint_accepte('https://web.push.apple.com/QGuQ'), 'https://web.push.apple.com/QGuQ');
    vrai(endpoint_accepte('https://api.push.apple.com/x'), 'https://api.push.apple.com/x');
    vrai(endpoint_accepte('https://fcm.googleapis.com/fcm/send/x'), 'https://fcm.googleapis.com/fcm/send/x');
    vrai(!endpoint_accepte('http://web.push.apple.com/x'), 'http://web.push.apple.com/x');
    vrai(!endpoint_accepte('https://web.push.apple.com.exemple.fr/x'), 'https://web.push.apple.com.exemple.fr/x');
    vrai(!endpoint_accepte('https://exemple.fr/web.push.apple.com'), 'https://exemple.fr/web.push.apple.com');
    vrai(!endpoint_accepte('https://user@web.push.apple.com:8443/x'), 'https://user@web.push.apple.com:8443/x');
    vrai(!endpoint_accepte('pas une adresse'), 'pas une adresse');
});

test('abonnements : le même abonnement renvoyé ne crée pas de doublon', function () {
    base_de_test();
    abonnement_enregistrer(abonnement_exemple(), null);
    abonnement_enregistrer(abonnement_exemple(), null);
    egal(1, (int) db()->query('SELECT COUNT(*) FROM abonnements')->fetchColumn());
});

test('abonnements : un endpoint hors liste est refusé', function () {
    base_de_test();
    leve(ErreurApi::class, fn() => abonnement_enregistrer(abonnement_exemple('https://exemple.fr/x'), null));
});

test('abonnements : un 410 supprime cet abonnement sans empêcher l\'envoi aux autres', function () {
    base_de_test();
    abonnement_enregistrer(abonnement_exemple('https://web.push.apple.com/mort'), null);
    abonnement_enregistrer(abonnement_exemple('https://web.push.apple.com/vivant'), null);
    faux_http([
        'POST https://web.push.apple.com/mort' => ['statut' => 410, 'corps' => ''],
        'POST https://web.push.apple.com/vivant' => ['statut' => 201, 'corps' => ''],
    ]);
    egal(['envoyes' => 1, 'supprimes' => 1, 'echecs' => 0], notifier_tous(['titre' => 'T', 'texte' => 'x', 'url' => '/#/aujourdhui', 'tag' => 'test']));
    egal(1, (int) db()->query('SELECT COUNT(*) FROM abonnements')->fetchColumn());
});

test('abonnements : en-têtes d\'envoi', function () {
    base_de_test();
    abonnement_enregistrer(abonnement_exemple(), null);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    notifier_tous(['titre' => 'T', 'texte' => 'x', 'url' => '/', 'tag' => 't'], 'high', 900);
    $entetes = implode("\n", $GLOBALS['JR_APPELS_HTTP'][0]['entetes']);
    vrai(str_contains($entetes, 'Content-Encoding: aes128gcm'), 'Content-Encoding: aes128gcm');
    vrai(str_contains($entetes, 'TTL: 900'), 'TTL: 900');
    vrai(str_contains($entetes, 'Urgency: high'), 'Urgency: high');
    vrai(str_contains($entetes, 'Authorization: vapid t='), 'Authorization: vapid t=');
});
```

Aides de `tests/aide.php` : `base_de_test()` repart d'une base vide et migrée, `vrai()` exige un message, il n'existe pas de `faux()`. `RFC_UA_PUB` et `RFC_AUTH` sont dans `tests/fabriques.php` (tâche 2).

- [ ] **Step 2 : l'implémentation**

```php
<?php
declare(strict_types=1);

// Abonnements aux notifications (un par appareil) et envoi chiffré à chacun.

const JR_PUSH_HOTES = ['web.push.apple.com', 'fcm.googleapis.com', 'updates.push.services.mozilla.com'];

function endpoint_accepte(string $url): bool
{
    $p = parse_url($url);
    if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['port']) || !isset($p['host'])) {
        return false;
    }
    $hote = strtolower($p['host']);
    return in_array($hote, JR_PUSH_HOTES, true) || str_ends_with($hote, '.push.apple.com');
}

function abonnement_enregistrer(array $abonnement, ?int $session_id): int
{
    $endpoint = (string) ($abonnement['endpoint'] ?? '');
    $p256dh = (string) ($abonnement['keys']['p256dh'] ?? '');
    $auth = (string) ($abonnement['keys']['auth'] ?? '');
    try {
        $valide = endpoint_accepte($endpoint) && strlen($endpoint) <= 1000
            && strlen(b64url_decoder($p256dh)) === 65 && strlen(b64url_decoder($auth)) === 16;
    } catch (InvalidArgumentException) {
        $valide = false;
    }
    if (!$valide) {
        throw new ErreurApi('abonnement_invalide', 400, 'Abonnement aux notifications invalide.');
    }
    $maintenant = utc(maintenant());
    db()->prepare('INSERT INTO abonnements (endpoint, empreinte, p256dh, auth, session_id, cree_le, vu_le)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), session_id = VALUES(session_id), vu_le = VALUES(vu_le), echecs = 0')
        ->execute([$endpoint, hash('sha256', $endpoint), $p256dh, $auth, $session_id, $maintenant, $maintenant]);
    return (int) db()->query('SELECT id FROM abonnements WHERE empreinte = ' . db()->quote(hash('sha256', $endpoint)))->fetchColumn();
}

function abonnement_supprimer(string $endpoint): void
{
    db()->prepare('DELETE FROM abonnements WHERE empreinte = ?')->execute([hash('sha256', $endpoint)]);
}

function notifier_tous(array $message, string $urgence = 'normal', int $ttl = 43200): array
{
    $bilan = ['envoyes' => 0, 'supprimes' => 0, 'echecs' => 0];
    $texte = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    foreach (db()->query('SELECT id, endpoint, p256dh, auth FROM abonnements')->fetchAll() as $a) {
        try {
            $corps = chiffrer_message($texte, b64url_decoder($a['p256dh']), b64url_decoder($a['auth']));
            $r = http_requete('POST', $a['endpoint'], [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                "TTL: $ttl",
                "Urgency: $urgence",
                'Authorization: ' . vapid_entete($a['endpoint'], config('vapid'), maintenant()->getTimestamp()),
            ], $corps, 10);
            $statut = $r['statut'];
        } catch (ErreurReseau) {
            $statut = 0;
        }
        if ($statut === 200 || $statut === 201) {
            $bilan['envoyes']++;
        } elseif ($statut === 404 || $statut === 410) {
            db()->prepare('DELETE FROM abonnements WHERE id = ?')->execute([$a['id']]);
            $bilan['supprimes']++;
        } else {
            db()->prepare('UPDATE abonnements SET echecs = LEAST(echecs + 1, 255) WHERE id = ?')->execute([$a['id']]);
            journal_erreur('notifications', new RuntimeException("notification refusée : HTTP $statut (abonnement {$a['id']})"));
            $bilan['echecs']++;
        }
    }
    return $bilan;
}
```

`journal_erreur()` (`amorce.php`) ne garde que la route et le message : aucun endpoint ni texte n'y passe. `maintenant()` rend un `DateTimeImmutable` (réglable dans les tests par `fixer_heure()`).

- [ ] **Step 3 : suite au vert** : `php tests/run.php`
- [ ] **Step 4 : commit** `Lot 2 : abonnements, liste blanche des endpoints, envoi chiffré`

### Task 4: Routes `abonnements` et `notifications/test`

**Files:**
- Create: `serveur/routes/notifications.php`
- Modify: `serveur/routes/donnees.php` ou `systeme.php` : exposer `vapid_publique` à l'iPhone (dans `GET donnees`, champ `notifications.cle`)
- Test: `tests/unitaires/test_routes_notifications.php`

**Interfaces:**
- Consumes: `abonnement_enregistrer()`, `abonnement_supprimer()`, `notifier_tous()` (tâche 3).
- Produces:
  - `POST abonnements` {`abonnement`} → `{ok: true}` (session exigée, met à jour `vu_le` à chaque appel) ;
  - `POST abonnements/supprimer` {`endpoint`} → `{ok: true}` ;
  - `POST notifications/test` → `{envoyes, supprimes, echecs}`, message `{titre: 'Notification de test', texte: 'Si tu lis ça, tes rappels arriveront bien.', url: '/#/moi', tag: 'test'}` (textes de `CONTENU.md` § 5).

- [ ] **Step 1 : tests** (suivre `tests/unitaires/test_routeur.php` pour appeler une route avec ou sans session et vérifier le jeton CSRF) : sans session → 401 ; abonnement valide → 200 et une ligne ; renvoyé deux fois → toujours une ligne ; endpoint hors liste → 400 `abonnement_invalide` ; test sans abonnement → `{envoyes: 0, …}` ; test avec un abonnement → un appel HTTP vers l'endpoint.
- [ ] **Step 2 : implémentation** dans le format de `serveur/routes/systeme.php` (`'POST abonnements' => ['publique' => false, 'action' => function (array $requete, ?array $session): array { … }]`).
- [ ] **Step 3 : suite au vert, commit** `Lot 2 : routes d'abonnement et notification de test`

### Task 5: `sw.js`, événements `push` et `notificationclick`

**Files:**
- Modify: `site/sw.js` (ajouter deux écouteurs à la fin, rien d'autre ne change)
- Test: `tests/unitaires/test_sw.php` (aides `sw_source()` déjà là)

**Interfaces:**
- Consumes: le message chiffré de la tâche 3, JSON `{titre, texte, url, tag}`.
- Produces: une notification affichée à chaque `push`, même illisible ; un toucher ramène l'app sur `url`.

`VERSION` est l'empreinte des fichiers de `FICHIERS`, dont `sw.js` ne fait pas partie : modifier `sw.js` ne change pas `VERSION`, et le navigateur installe quand même le nouveau service worker (il compare `sw.js` octet par octet).

- [ ] **Step 1 : les tests, qui échouent**

```php
test('sw : un push affiche toujours une notification, même sans contenu lisible', function () {
    $src = sw_source();
    vrai(str_contains($src, "self.addEventListener('push'"), 'écouteur push');
    vrai(str_contains($src, 'showNotification('), 'showNotification');
    vrai(str_contains($src, 'Jarvis Jr. a quelque chose pour toi.'), 'texte de repli');
    vrai(str_contains($src, 'e.waitUntil(afficher'), 'waitUntil sur l\'affichage');
});

test('sw : un toucher ramène l\'app sur l\'adresse de la notification, dans l\'app seulement', function () {
    $src = sw_source();
    vrai(str_contains($src, "self.addEventListener('notificationclick'"), 'écouteur notificationclick');
    vrai(str_contains($src, 'clients.openWindow('), 'ouverture si aucune fenêtre');
    vrai(str_contains($src, 'adresse_interne('), 'adresse filtrée');
});
```

- [ ] **Step 2 : l'implémentation**, à la fin de `site/sw.js`

```js
// Notifications (SPEC § 15.8). iOS coupe l'abonnement si un push n'affiche rien : on affiche toujours.
const REPLI = { titre: 'Jarvis Jr.', texte: 'Jarvis Jr. a quelque chose pour toi.', url: '/#/aujourdhui', tag: 'jr' };

function lire_message(e) {
  try {
    const m = e.data ? e.data.json() : null;
    return m && typeof m.titre === 'string' ? { ...REPLI, ...m } : REPLI;
  } catch {
    return REPLI;
  }
}

// Seulement une adresse de l'app : une notification ne doit jamais ouvrir un autre site
function adresse_interne(url) {
  try {
    const u = new URL(url, self.location.origin);
    return u.origin === self.location.origin ? u.href : new URL(REPLI.url, self.location.origin).href;
  } catch {
    return new URL(REPLI.url, self.location.origin).href;
  }
}

function afficher(m) {
  return self.registration.showNotification(m.titre, {
    body: m.texte,
    tag: m.tag,
    data: { url: adresse_interne(m.url) },
    icon: '/icons/icon-192.png',
  });
}

self.addEventListener('push', (e) => {
  e.waitUntil(afficher(lire_message(e)));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || adresse_interne(REPLI.url);
  e.waitUntil((async () => {
    const fenetres = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const f of fenetres) {
      if (new URL(f.url).origin === self.location.origin) {
        await f.focus();
        return f.navigate(url);
      }
    }
    return self.clients.openWindow(url);
  })());
});
```

- [ ] **Step 3 : suite au vert** : `php tests/run.php`
- [ ] **Step 4 : vérification dans Chrome** (aperçu local, `outils/apercu.php`) : DevTools, Application, Service workers, bouton « Push » avec `{"titre":"Essai","texte":"Bonjour","url":"/#/taches","tag":"t"}` → notification « Essai » ; un clic ramène l'app sur Tâches. Puis « Push » avec le texte `pas du json` → notification de repli.
- [ ] **Step 5 : commit** `Lot 2 : le service worker affiche les notifications et ouvre l'app au toucher`

### Task 6: Réglages, partie Notifications

Détaillée le 2026-10-11 (2e session). Pour ce plan, Réglages n'a que la partie Notifications ; brief, rappels, Siri et Session arrivent au plan 3.

**Files:**
- Create: `site/js/notifications.js` (tout ce qui touche à `Notification`, `PushManager` et au service worker)
- Create: `site/js/ecrans/reglages.js` (écran `#/reglages`)
- Modify: `site/js/app.js` (écran `reglages`, onglet Moi allumé sur Réglages, renvoi à l'ouverture, oubli à la déconnexion)
- Modify: `site/js/ecrans/moi.js` (lien « Réglages »)
- Modify: `site/js/lib/textes.js` (`T.reglages`, `T.moi.reglages`)
- Modify: `site/css/app.css` (une règle `.reglage-aide`)
- Modify: `site/sw.js` (deux fichiers dans `FICHIERS`, `VERSION` recalculée)
- Modify: `CONTENU.md` § 3 Réglages (les trois textes nouveaux)
- Modify: `tests/e2e/serveur.mjs` (routes `abonnements`, `abonnements/supprimer`, `notifications/test`), `tests/e2e/fixtures.mjs` (`notifications.cle`)
- Test: `tests/js/notifications.test.mjs`, `tests/e2e/test_reglages.mjs`

**Interfaces:**
- Consumes: `POST abonnements` {`abonnement`}, `POST abonnements/supprimer` {`endpoint`}, `POST notifications/test` → {`envoyes`, `supprimes`, `echecs`} (tâche 4) ; `GET donnees` → `notifications.cle` (clé publique VAPID en base64url, tâche 4).
- Produces (`site/js/notifications.js`) :
  - `cleOctets(texte: string): Uint8Array` (base64url → octets) ;
  - `etatNotifications(): Promise<'indisponibles'|'coupees'|'actives'|'inactives'>` ;
  - `activer(cle: string): Promise<même type>` (à appeler directement dans le geste) ;
  - `renvoyer(): Promise<void>` (sans bruit) ; `oublier(): Promise<void>` (sans bruit) ; `envoyerTest(): Promise<{envoyes, supprimes, echecs}>`.

**Pièges :**
- iOS n'accepte `Notification.requestPermission()` que dans le geste : `activer()` l'appelle en premier, avant tout `await`.
- `navigator.serviceWorker.ready` ne se résout jamais sans service worker (tests d'écran : service workers bloqués) : attente bornée à 3 s, puis `indisponibles`.
- Dans Safari hors de l'app installée, iOS n'expose pas `PushManager` : état `indisponibles`, avec une explication.
- `oublier()` passe **avant** la fin de session (les routes d'abonnement exigent la session), et désabonne l'iPhone même si le serveur ne répond pas : l'abonnement resté au serveur répondra 410 au prochain envoi et sera supprimé (tâche 3).

- [ ] **Step 1 : les tests de module, qui échouent** (`tests/js/notifications.test.mjs`)

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cleOctets } from '../../site/js/notifications.js';

test('clé VAPID base64url en octets : 65 octets, point non compressé (0x04)', () => {
  const o = cleOctets('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
  assert.equal(o.length, 65);
  assert.equal(o[0], 4);
  assert.equal(o[64], 0x0f);
});

test('base64url avec - et _ (sans remplissage) relu comme en base64', () => {
  assert.deepEqual([...cleOctets('-_8')], [0xfb, 0xff]);
});
```

Lancer : `node --test 'tests/js/*.test.mjs'` → échec, module introuvable.

- [ ] **Step 2 : `site/js/notifications.js`**

```js
// Notifications web sur l'iPhone (SPEC § 15.8) : abonnement sur un geste, renvoyé à chaque ouverture
// (iOS n'a pas pushsubscriptionchange), oublié à la déconnexion.
import { appeler } from './api.js';

// Clé publique VAPID (base64url) en octets, pour applicationServerKey
export function cleOctets(texte) {
  const b64 = texte.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(texte.length / 4) * 4, '=');
  return Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
}

const disponibles = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

// L'inscription du service worker, ou null s'il n'est pas prêt en 3 s
const inscription = () => Promise.race([navigator.serviceWorker.ready, new Promise((r) => setTimeout(() => r(null), 3000))]);

async function abonnementActuel() {
  if (!disponibles() || Notification.permission !== 'granted') return null;
  const i = await inscription();
  return i ? i.pushManager.getSubscription() : null;
}

const envoyer = (abonnement) => appeler('POST', 'abonnements', { corps: { abonnement: abonnement.toJSON() } });

export async function etatNotifications() {
  if (!disponibles()) return 'indisponibles';
  if (Notification.permission === 'denied') return 'coupees';
  return (await abonnementActuel()) ? 'actives' : 'inactives';
}

// Sur un geste : la permission est demandée avant toute autre attente (iOS l'exige)
export async function activer(cle) {
  const permission = await Notification.requestPermission();
  if (permission !== 'granted') return permission === 'denied' ? 'coupees' : 'inactives';
  const i = await inscription();
  if (!i) return 'indisponibles';
  const abonnement = (await i.pushManager.getSubscription())
    ?? (await i.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: cleOctets(cle) }));
  await envoyer(abonnement);
  return 'actives';
}

export async function renvoyer() {
  try {
    const abonnement = await abonnementActuel();
    if (abonnement) await envoyer(abonnement);
  } catch {
    // réessayé à la prochaine ouverture
  }
}

export async function oublier() {
  try {
    const abonnement = await abonnementActuel();
    if (!abonnement) return;
    try {
      await appeler('POST', 'abonnements/supprimer', { corps: { endpoint: abonnement.endpoint } });
    } finally {
      await abonnement.unsubscribe();
    }
  } catch {
    // un abonnement resté au serveur répondra 410 au prochain envoi et sera supprimé
  }
}

export const envoyerTest = () => appeler('POST', 'notifications/test', { corps: {} });
```

Lancer : `node --test 'tests/js/*.test.mjs'` → vert.

- [ ] **Step 3 : faux serveur et données des tests d'écran**

Dans `tests/e2e/fixtures.mjs`, `donneesDeBase()` rend aussi `notifications: { cle: 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8' }`. Dans `tests/e2e/serveur.mjs`, `reinitialiser()` ajoute `testEnvoyes: 1` à l'état, et `api()` gagne, juste avant `if (req.method === 'POST') return ecrire(…)` :

```js
  if (req.method === 'POST' && (route === 'abonnements' || route === 'abonnements/supprimer')) return json(res, 200, {});
  if (req.method === 'POST' && route === 'notifications/test') return json(res, 200, { envoyes: etat.testEnvoyes, supprimes: 0, echecs: 0 });
```

- [ ] **Step 4 : les tests d'écran, qui échouent** (`tests/e2e/test_reglages.mjs`)

```js
import { test, assert, heure, connecte, ouvrir, attendre } from './cadre.mjs';

// Faux Push de l'iPhone : Notification, PushManager et service worker prêt, avec un journal lu par le test
async function preparer(page, contexte, serveur, { permission = 'default', reponse = 'granted', abonne = false, push = true } = {}) {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
  await page.addInitScript((o) => {
    const journal = { demandes: 0, abonnements: [], desabonnements: 0 };
    window.__push = journal;
    const creer = () => ({
      endpoint: 'https://web.push.apple.com/QGuQ-essai',
      toJSON() { return { endpoint: this.endpoint, keys: { p256dh: 'p256dh-essai', auth: 'auth-essai' } }; },
      async unsubscribe() { journal.desabonnements++; actuel = null; return true; },
    });
    let actuel = o.abonne ? creer() : null;
    window.Notification = class {
      static permission = o.permission;
      static async requestPermission() { journal.demandes++; this.permission = o.reponse; return o.reponse; }
    };
    if (!o.push) {
      delete window.PushManager;
      return;
    }
    const pushManager = {
      async getSubscription() { return actuel; },
      async subscribe(options) {
        journal.abonnements.push({ userVisibleOnly: options.userVisibleOnly, octets: options.applicationServerKey.length });
        actuel = creer();
        return actuel;
      },
    };
    Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: { ready: Promise.resolve({ pushManager }), register: async () => ({}) } });
  }, { permission, reponse, abonne, push });
}

const journal = (page) => page.evaluate(() => window.__push);

test('Moi mène à Réglages : notifications « Pas encore activées », bouton « Activer les notifications », retour sur Moi', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi');
  await page.getByRole('link', { name: 'Réglages' }).click();
  await page.getByRole('heading', { name: 'Réglages' }).waitFor();
  await page.getByText('Pas encore activées').waitFor();
  await page.getByRole('button', { name: 'Activer les notifications' }).waitFor();
  assert.equal(await page.locator('.onglet[aria-current="page"]').getAttribute('data-onglet'), 'moi');
  await page.getByRole('button', { name: 'Retour' }).click();
  await page.getByRole('heading', { name: 'Moi' }).waitFor();
});

test("Activer : permission demandée, abonnement avec la clé du serveur (65 octets), envoyé au serveur, « Actives sur cet iPhone »", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByRole('button', { name: 'Activer les notifications' }).click();
  await page.getByText('Actives sur cet iPhone').waitFor();
  const j = await journal(page);
  assert.equal(j.demandes, 1);
  assert.deepEqual(j.abonnements, [{ userVisibleOnly: true, octets: 65 }]);
  assert.equal(serveur.appels('abonnements').at(-1).corps.abonnement.endpoint, 'https://web.push.apple.com/QGuQ-essai');
  await page.getByRole('button', { name: 'Envoyer une notification de test' }).waitFor();
});

test("Activer refusé : « Coupées » et le chemin dans les Réglages de l'iPhone, rien n'est envoyé", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { reponse: 'denied' });
  await ouvrir(page, '/#/reglages');
  await page.getByRole('button', { name: 'Activer les notifications' }).click();
  await page.getByText('Les notifications sont coupées sur cet iPhone. Va dans Réglages de l\'iPhone, Notifications, Jarvis Jr.').waitFor();
  await page.getByText('Coupées', { exact: true }).waitFor();
  assert.equal(serveur.appels('abonnements').length, 0);
  assert.equal(await page.getByRole('button', { name: 'Activer les notifications' }).count(), 0);
});

test('Notification de test : envoyée au serveur, puis « Notification envoyée… » ; sans appareil abonné, le dire', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { permission: 'granted', abonne: true });
  await ouvrir(page, '/#/reglages');
  await page.getByRole('button', { name: 'Envoyer une notification de test' }).click();
  await page.getByText('Notification envoyée. Elle arrive dans quelques secondes.').waitFor();
  assert.equal(serveur.appels('notifications/test').length, 1);
  serveur.etat.testEnvoyes = 0;
  await page.getByRole('button', { name: 'Envoyer une notification de test' }).click();
  await page.getByText("Aucun appareil n'est abonné. Réactive les notifications sur cet iPhone.").waitFor();
});

test("À l'ouverture de l'app, un abonnement existant est renvoyé au serveur (iOS n'a pas pushsubscriptionchange)", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { permission: 'granted', abonne: true });
  await ouvrir(page, '/#/aujourdhui');
  await attendre(() => serveur.appels('abonnements').length === 1, 'abonnement renvoyé');
});

test("Se déconnecter : le serveur oublie l'abonnement avant la fin de session, puis l'iPhone se désabonne", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { permission: 'granted', abonne: true });
  await ouvrir(page, '/#/moi');
  await page.getByRole('button', { name: 'Se déconnecter' }).click();
  await page.getByRole('heading', { name: 'JARVIS JR.' }).waitFor();
  const routes = serveur.etat.appels.map((a) => a.route);
  assert.ok(routes.indexOf('abonnements/supprimer') !== -1 && routes.indexOf('abonnements/supprimer') < routes.indexOf('deconnexion'), routes.join(', '));
  assert.equal(serveur.appels('abonnements/supprimer')[0].corps.endpoint, 'https://web.push.apple.com/QGuQ-essai');
  assert.equal((await journal(page)).desabonnements, 1);
});

test("Navigateur sans notifications web (Safari hors de l'app installée) : « Indisponibles ici », l'explication, aucun bouton", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { push: false });
  await ouvrir(page, '/#/reglages');
  await page.getByText('Indisponibles ici').waitFor();
  await page.getByText("Les notifications marchent seulement dans l'app installée sur l'écran d'accueil (iOS 16.4 ou plus).").waitFor();
  assert.equal(await page.getByRole('button', { name: 'Activer les notifications' }).count(), 0);
});
```

Lancer : `node tests/e2e/lancer.mjs Réglages` (et les autres noms) → échecs : pas d'écran Réglages.

- [ ] **Step 5 : textes** (`site/js/lib/textes.js`) : `T.moi.reglages = 'Réglages'`, et après `moi` :

```js
  reglages: {
    titre: 'Réglages',
    retour: 'Retour',
    notifications: 'Notifications',
    etats: { actives: 'Actives sur cet iPhone', coupees: 'Coupées', inactives: 'Pas encore activées', indisponibles: 'Indisponibles ici' },
    activer: 'Activer les notifications',
    tester: 'Envoyer une notification de test',
    aideCoupees: "Les notifications sont coupées sur cet iPhone. Va dans Réglages de l'iPhone, Notifications, Jarvis Jr.",
    aideIndisponibles: "Les notifications marchent seulement dans l'app installée sur l'écran d'accueil (iOS 16.4 ou plus).",
    testEnvoye: 'Notification envoyée. Elle arrive dans quelques secondes.',
    testPersonne: "Aucun appareil n'est abonné. Réactive les notifications sur cet iPhone.",
    reseau: 'Pas de réseau. Réessaie quand ça revient.',
  },
```

Les trois textes nouveaux (`indisponibles` et son aide, `testEnvoye`, `testPersonne`) sont ajoutés à `CONTENU.md` § 3 Réglages, à relire par Colin.

- [ ] **Step 6 : `site/js/ecrans/reglages.js`**

```js
// Réglages (SPEC § 15.8). Au plan 1 du lot 2 : la partie Notifications. Brief, rappels, Siri et Session
// arrivent au plan 3.
import { h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { ErreurReseau } from '../api.js';
import * as notifications from '../notifications.js';
import { regle } from './commun.js';

const R = T.reglages;
let etatNotif = null;  // null tant que l'état n'est pas lu
let occupe = false;    // une demande en cours (activer, tester)

export function quitter() {
  etatNotif = null;
}

export function apres(app, route, ecran, changement) {
  if (!changement) return;
  notifications.etatNotifications().then((e) => {
    etatNotif = e;
    app.rendre();
  });
}

async function faire(app, action) {
  if (occupe) return;
  occupe = true;
  try {
    await action();
  } catch (e) {
    app.message(e instanceof ErreurReseau ? R.reseau : e.message);
  } finally {
    occupe = false;
    app.rendre();
  }
}

// Pas d'await avant notifications.activer() : la permission doit être demandée dans le geste
function activer(app) {
  const cle = app.etat.donnees?.notifications?.cle;
  if (!cle) {
    app.message(R.reseau);
    return;
  }
  faire(app, async () => {
    etatNotif = await notifications.activer(cle);
  });
}

function tester(app) {
  faire(app, async () => {
    const r = await notifications.envoyerTest();
    app.message(r.envoyes > 0 ? R.testEnvoye : R.testPersonne);
  });
}

function blocNotifications(app) {
  if (etatNotif === null) return [];
  const actives = etatNotif === 'actives';
  return [
    h('p', { class: 'reglage-ligne' }, h('span', { class: `temoin${actives ? ' allume' : ''}` }), R.etats[etatNotif]),
    etatNotif === 'coupees' && h('p', { class: 'erreur', role: 'alert' }, R.aideCoupees),
    etatNotif === 'indisponibles' && h('p', { class: 'reglage-aide' }, R.aideIndisponibles),
    etatNotif === 'inactives' && h('div', { class: 'cuvette cuvette-large' },
      h('button', { type: 'button', class: 'bouton-allume bouton-large', disabled: occupe, onclick: () => activer(app) }, R.activer)),
    actives && h('button', { type: 'button', class: 'bouton bouton-large', disabled: occupe, onclick: () => tester(app) }, R.tester),
  ];
}

export function rendre(app) {
  return h('div', { class: 'moi reglages' },
    h('header', { class: 'entete-retour' },
      h('button', { type: 'button', class: 'bouton-carre', 'aria-label': R.retour, onclick: () => app.retour('#/moi') }, icone('gauche', 20, 2)),
      h('h1', { class: 'titre-ecran' }, R.titre)),
    h('h2', { class: 'section-titre' }, R.notifications), regle(),
    blocNotifications(app));
}
```

CSS (`site/css/app.css`, à côté de `.reglage-ligne`) : `.reglage-aide { margin-top: 10px; color: var(--texte-2); font-size: calc(14 * var(--px)); }`

- [ ] **Step 7 : brancher** :
  - `app.js` : `import * as reglages from './ecrans/reglages.js';` et `import * as notifications from './notifications.js';` ; `reglages` dans `ECRANS` ; l'onglet allumé passe par `const PARENTS = { carte: 'taches', reglages: 'moi' };` (`a.dataset.onglet === (PARENTS[route.nom] ?? route.nom)`) ; dans `demarrer()`, après `await actualiser();` : `notifications.renvoyer();` ; au début de `deconnecter(partout)` : `await notifications.oublier();`.
  - `moi.js` : en tête de la section « L'app », `h('a', { class: 'reglage-ligne lien', href: '#/reglages' }, M.reglages)`.
  - `sw.js` : `'/js/notifications.js'` et `'/js/ecrans/reglages.js'` dans `FICHIERS` (ordre alphabétique de la liste), puis `VERSION` donnée par `php tests/run.php`.

- [ ] **Step 8 : tout au vert** : `php tests/run.php`, `node --test 'tests/js/*.test.mjs'`, `node tests/e2e/lancer.mjs` (les 45 tests d'écran du lot 1 compris).

- [ ] **Step 9 : vraie notification dans Chrome sur le Mac**, avant toute mise en ligne : clés VAPID dans le fichier de secrets (tâche 1), `outils/generer-config.php`, aperçu local `php -S localhost:8787 -t site outils/apercu.php`, puis dans Chrome (Colin) : Moi, Réglages, « Activer les notifications », « Envoyer une notification de test » → la notification « Notification de test » s'affiche ; un clic ramène l'app sur Moi.

- [ ] **Step 10 : commit** `Lot 2 : Réglages, activer les notifications et notification de test`
