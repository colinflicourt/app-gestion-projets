# Jarvis Jr., lot 2, plan 2 sur 3 : le planificateur

> **Plan du 2026-10-12**, écrit d'après le SPEC § 15 (validé par Colin le 2026-10-11) et le code du plan 1 (fusionné dans `main` en local). À relire par Colin avant exécution, en particulier les **textes nouveaux** (section plus bas).

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chaque minute, le serveur prépare les notifications des 24 h à venir (cours, échéances, médians, brief du matin, rappels manuels), les garde sans doublon dans la table `envois`, et envoie celles qui sont dues à tous les appareils abonnés.

**Architecture:** Trois modules purs (`formats.php` pour écrire les heures, `brief.php` pour le brief du matin, `rappels.php` pour les règles de génération), un module `envois.php` (table `envois` : enregistrer, retirer, envoyer, périmer, réessayer, purger), un module `planificateur.php` (un passage sous le verrou `jr_cron`, copies rafraîchies si trop vieilles), et la route `POST /api/cron` protégée par un jeton, qui répond tout de suite et fait le passage après la réponse.

**Tech Stack:** PHP 8.4 sans Composer, MySQL 8.4 (`GET_LOCK`, `INSERT … AS n ON DUPLICATE KEY UPDATE`), le chiffrement et l'envoi du plan 1 (`notifier_tous()`), Apache (`.htaccess`) et PHP-FPM d'OVH (`fastcgi_finish_request`).

**Spec:** `projects/jarvis-jr/SPEC.md` § 15.2 (tables, clés d'envoi, réglages), § 15.4 (planificateur), § 15.5 (brief), et `CONTENU.md` § 5 (textes des notifications) et § 5.1 (répliques).

**Le lot 2 en 3 plans** :
1. ~~Le socle des notifications~~ (fait le 2026-10-11).
2. **Ce plan : le planificateur.**
3. Rappels manuels (capture, `POST /api/rappels`), Siri, reste de Réglages (heures et interrupteurs des rappels, qui écriront la clé `rappels` lue ici), bloc « Rappels du jour », mise en ligne, réglage de cron-job.org et recette du lot 2 (SPEC § 15.9).

Tous les chemins sont relatifs à `projects/jarvis-jr/` (dépôt git du projet). Travail sur une branche `lot2-2-planificateur` partie de `main`, fusionnée à la fin. Tests : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets` (chaque `tests/unitaires/test_*.php` et `tests/integration/test_*.php` est chargé, chaque fichier de `serveur/lib/` aussi). Avant de commencer : MySQL local lancé (`{ brew services run mysql@8.4 ; } 2>&1 | ~/.claude/hooks/redact-secrets`) et suite actuelle au vert (179 tests PHP, 32 de modules, 53 d'écran).

## Global Constraints

- PHP 8.4 sur le serveur, 8.5 sur le Mac : rien de propre à 8.5. Pas de Composer. `declare(strict_types=1);` partout. Noms, commentaires et messages en français, commentaires rares.
- **Aucun réseau dans les tests** : tout appel sortant passe par `http_requete()`, remplacée par `faux_http()`.
- Dates en base en UTC, heure venue de `maintenant()` : écrire avec `utc()`, relire avec `depuis_utc()`, afficher avec l'heure de Paris (`paris()`, `iso_paris()`). Les règles se calculent **à l'heure de Paris** (passage à l'heure d'hiver le dimanche 25/10/2026 à 3 h).
- **Journal** : jamais d'endpoint complet, jamais de jeton, jamais le texte d'une notification.
- Toute commande qui touche aux fichiers de secrets ou à `config.php` passe en entier par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`.
- Textes : ceux de `CONTENU.md` § 5 mot pour mot (« TN01 · TD dans 15 min », « Salle FA510. », « Demain : … », « Échéance à 8 h 30. Il reste 5 étapes sur 5. », « Médian MT23 dans 7 jours », « Mercredi 21/10 à 19 h 15. », « Mercredi à 19 h 15. », « 19 h 15. Dors bien ce soir. », « Mercredi 14 octobre · 2 cours », « Premier à 8 h en FA400 (NF93 TD). », « Rappel que tu t'es mis hier à 22 h. »). Heures écrites « 8 h », « 8 h 30 ». Pas de tiret long.
- Péremption (SPEC § 15.4) : cours 10 min, médian et échéance 3 h, brief 2 h, manuel 12 h. TTL : 15 min pour un cours (urgence `high`), 12 h pour le reste (urgence `normal`). 3 essais au plus.
- Valeurs par défaut des réglages (SPEC § 15.2) : brief 07:30 tous les jours, cours 15 min avant, échéances la veille à 20:00, médians J-7, J-3, J-1 à 09:00.

## Review Focus

- **Deux passages en même temps** (cron-job.org et le cron d'OVH, ou un passage lent qui déborde sur la minute suivante) : une notification ne doit partir qu'une fois. Verrou `jr_cron` et clé unique : tests de la tâche 5 (verrou pris) et de la tâche 4 (même liste enregistrée deux fois).
- **Passage à l'heure d'hiver (25/10/2026)** : le brief part à 7 h 30 à Paris la veille comme le jour même, et la notification d'une échéance du lundi 26/10 part le dimanche à 20 h. Tests de la tâche 3.
- **Une source en panne** (Trello 503, Google injoignable) : les rappels de l'autre source et l'envoi des notifications dues continuent, avec la dernière copie. Test de la tâche 5.
- **Un réglage abîmé en base** (heure « 25:99 », jours « x ») : le planificateur reprend la valeur par défaut au lieu de planter chaque minute. Test de la tâche 3.
- **Agenda pas connecté ou copie trop vieille** : le brief ne doit pas annoncer « Pas de cours » un jour qu'il ne connaît pas. Test de la tâche 2.

## Textes nouveaux, à valider par Colin

Ils ne sont pas dans `CONTENU.md`. Proposés ici, ajoutés à `CONTENU.md` § 5 à la tâche 7.

| Où | Texte |
|---|---|
| Cours sans salle (texte de la notification) | « De 8 h à 10 h. » |
| Échéance dont toutes les étapes sont cochées | « Échéance à 12 h. Toutes les étapes sont cochées. » |
| Titre du brief un jour particulier de l'UTC | « Lundi 26 octobre · Vacances », « · Férié », « · Jour d'examen » |
| Brief, jour où l'UTC suit l'emploi du temps d'un autre jour | le texte du bandeau d'Aujourd'hui en tête : « Aujourd'hui, l'UTC suit l'emploi du temps du lundi. » |
| Brief, tâches | « 1 tâche du jour. », « 1 tâche du jour, 2 en retard. », « 2 tâches en retard. » |
| Brief, médian proche | « Médian MT23 dans 7 jours. » (CONTENU), « Médian MT23 demain à 19 h 15. », « Médian MT23 aujourd'hui à 19 h 15. » |
| Brief d'un jour absent de la copie de l'agenda | le titre seul : « Mardi 1er décembre » |

Les 20 répliques de `CONTENU.md` § 5.1 (toujours « à relire par Colin ») sont recopiées telles quelles dans `serveur/lib/brief.php` : les changer plus tard ne demande que d'éditer cette liste.

## Écarts au SPEC (à reporter dans le SPEC § 15 à la tâche 7)

- **Clés des cours et des médians** : `cours:<début>:<empreinte du titre>` et `median:<début>:J7`, au lieu de l'identifiant de l'événement, que la copie de l'agenda ne garde pas. Un cours déplacé change de clé, comme une échéance déplacée.
- **Fenêtre de génération** : de 12 h avant (la plus longue péremption) à 24 h après le passage. Une notification due mais pas encore partie reste ainsi dans la liste, et n'est pas retirée avant d'être envoyée.
- **Une ligne retirée qui réapparaît redevient prévue** (carte remise dans À faire, réglage rallumé).
- **Une ligne encore prévue suit la source** (texte, heure) : le brief de demain, préparé dès 7 h 30 aujourd'hui, est recalculé à chaque passage jusqu'à son envoi.
- **La route répond avant le passage** (`fastcgi_finish_request`, disponible chez OVH) : cron-job.org n'attend jamais Trello ou Google, et ne compte pas d'échecs.
- **Notification de test** : elle reste envoyée directement (`notifier_tous()`, la même fonction que le planificateur), hors de `envois`.
- **Réglage `cron_dernier_passage`** (heure de Paris) écrit à chaque passage : pour vérifier en ligne que cron-job.org tourne (et pour Réglages au plan 3).

---

### Task 1: Tables `rappels` et `envois`, jeton du planificateur

**Files:**
- Create: `serveur/migrations/004_rappels.sql`
- Create: `outils/generer-jeton-cron.php`
- Modify: `outils/lib-env.php` (`construire_config()` : `CRON_JETON` obligatoire, clé `cron`)
- Modify: `tests/fabriques.php` (constante `JETON_CRON_TEST`)
- Modify: `tests/aide.php` (`config_de_test()` : clé `cron`)
- Test: `tests/unitaires/test_base.php` (11 tables, version 4), `tests/unitaires/test_env.php`

**Interfaces:**
- Produces: tables `rappels` (`id`, `id_client`, `texte`, `quand`, `cree_le`, `source`, `annule`) et `envois` (`cle` ASCII binaire, `type`, `prevu_pour`, `titre`, `texte`, `url`, `statut`, `essais`, `envoye_le`) ; `config('cron')` → `['jeton' => string]` ; constante de test `JETON_CRON_TEST`.

- [ ] **Step 1 : les tests d'abord**

Dans `tests/unitaires/test_base.php`, la liste des tables et la version :

```php
const JR_TABLES = ['abonnements', 'actions', 'copies', 'ecritures', 'envois', 'essais_connexion', 'jetons', 'rappels', 'reglages', 'sessions', 'tentatives'];
```

Le titre du premier test devient « base : les migrations créent les 11 tables (lot 1, abonnements, rappels et envois du lot 2) et notent la version », et chaque `egal(3, …)` du fichier devient `egal(4, …)` (5 endroits).

Dans `tests/unitaires/test_env.php`, ajouter `'CRON_JETON' => 'jeton-cron',` à la fin du tableau de `env_exemple_app()`, puis :

```php
test('config : le jeton du planificateur, obligatoire', function () {
    $c = construire_config('production', env_exemple_app(), ['TRELLO_KEY' => 'k']);
    egal(['jeton' => 'jeton-cron'], $c['cron']);
    $app = env_exemple_app();
    unset($app['CRON_JETON']);
    $e = leve(RuntimeException::class, fn() => construire_config('production', $app, ['TRELLO_KEY' => 'k']));
    egal('Clé manquante ou vide : CRON_JETON', $e->getMessage());
});
```

- [ ] **Step 2 : les voir échouer**

Run: `{ php tests/run.php base ; php tests/run.php config ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: ÉCHEC sur les tables (`envois` et `rappels` absentes), la version (3 au lieu de 4) et `$c['cron']` (clé absente).

- [ ] **Step 3 : la migration** `serveur/migrations/004_rappels.sql`

```sql
-- Lot 2, plan 2 : rappels manuels, et une ligne par notification à envoyer (la clé interdit les doublons).
-- La clé est en ASCII binaire : jamais deux clés confondues par la casse ou les accents. Heures en UTC.
CREATE TABLE IF NOT EXISTS rappels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_client CHAR(36) NOT NULL,
  texte VARCHAR(500) NOT NULL,
  quand DATETIME NOT NULL,
  cree_le DATETIME NOT NULL,
  source VARCHAR(10) NOT NULL DEFAULT 'app',
  annule TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY id_client (id_client),
  KEY quand (quand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS envois (
  cle VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  type VARCHAR(20) NOT NULL,
  prevu_pour DATETIME NOT NULL,
  titre VARCHAR(200) NOT NULL,
  texte VARCHAR(500) NOT NULL,
  url VARCHAR(200) NOT NULL,
  statut VARCHAR(10) NOT NULL DEFAULT 'prevu',
  essais TINYINT UNSIGNED NOT NULL DEFAULT 0,
  envoye_le DATETIME NULL,
  KEY statut_prevu (statut, prevu_pour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

- [ ] **Step 4 : le jeton dans la config**

Dans `outils/lib-env.php`, `construire_config()` : ajouter `'CRON_JETON'` à la fin de la liste des clés obligatoires de `$app` (après `'VAPID_SUJET'`), et à la fin du tableau rendu :

```php
        'cron' => ['jeton' => $app['CRON_JETON']],
```

Dans `tests/fabriques.php`, après les constantes RFC :

```php
// Jeton du planificateur dans la config de test (POST /api/cron)
const JETON_CRON_TEST = 'jeton-du-planificateur-pour-les-tests-0123456789';
```

Dans `tests/aide.php`, `config_de_test()`, après la ligne `'vapid' => …` :

```php
        'cron' => ['jeton' => JETON_CRON_TEST],
```

- [ ] **Step 5 : `outils/generer-jeton-cron.php`**

```php
<?php
// Crée le jeton du planificateur (CRON_JETON, 32 octets aléatoires) dans le fichier de secrets de l'app.
//   php outils/generer-jeton-cron.php
// N'affiche jamais le jeton. Ne remplace jamais un jeton déjà présent.

declare(strict_types=1);

require __DIR__ . '/lib-env.php';

$fichier = dirname(__DIR__, 3) . '/secrets/jarvis-jr.env';
try {
    if ((lire_env($fichier)['CRON_JETON'] ?? '') !== '') {
        fwrite(STDERR, "Jeton du planificateur déjà présent, rien n'est changé.\n");
        exit(1);
    }
    env_definir($fichier, 'CRON_JETON', rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
echo "Jeton du planificateur ajouté (CRON_JETON). Il sera donné à cron-job.org au plan 3.\n";
```

- [ ] **Step 6 : tests au vert**

Run: `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: tout passe (180 tests).

- [ ] **Step 7 : créer le jeton et regénérer la config de dev**

```bash
{ php outils/generer-jeton-cron.php && php outils/generer-config.php --mode=dev --dossier=serveur && php -r '$c = require "serveur/config.php"; echo strlen($c["cron"]["jeton"] ?? ""), "\n";' ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

Expected : « Jeton du planificateur ajouté », « config.php et trello-ids.json écrits », puis `43`. Le jeton lui-même n'apparaît jamais.

- [ ] **Step 8 : commit**

```bash
git add serveur/migrations/004_rappels.sql outils/generer-jeton-cron.php outils/lib-env.php tests/fabriques.php tests/aide.php tests/unitaires/test_base.php tests/unitaires/test_env.php
git commit -m "Lot 2 : tables rappels et envois, jeton du planificateur" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Heures lisibles et brief du matin

**Files:**
- Create: `serveur/lib/formats.php`
- Create: `serveur/lib/brief.php`
- Test: `tests/unitaires/test_formats.php`, `tests/unitaires/test_brief.php`

**Interfaces:**
- Consumes: `paris()` (`amorce.php`), `JR_JOURS_SEMAINE` et `jours_agenda()` (`creneaux.php`, pour les tests).
- Produces:
  - `heure_lisible(DateTimeImmutable): string` → « 8 h », « 8 h 30 » (heure de Paris) ;
  - `jour_nom(DateTimeImmutable): string` → « Mercredi » ;
  - `date_longue(DateTimeImmutable): string` → « Mercredi 14 octobre », « Dimanche 1er novembre » ;
  - `pluriel(int, string): string` → « 1 tâche », « 3 tâches » ;
  - `couper(string, int): string` (points de suspension compris dans la longueur) ;
  - `jours_entre(string, string): int` (dates AAAA-MM-JJ) ;
  - `JR_REPLIQUES` (20 textes), `replique_du_jour(string $date): string` ;
  - `brief_du_matin(string $date, array $cartes, array $jours): array{titre: string, texte: string}` (`$cartes` : cartes de la copie Trello, `$jours` : jours de la copie de l'agenda, AAAA-MM-JJ → `['bandeaux' => […], 'evenements' => […]]`), avec ses deux morceaux `brief_taches(array $cartes, string $date): string` et `brief_median(array $jours, string $date): string` (`''` quand il n'y a rien à dire).

- [ ] **Step 1 : les tests des formats** `tests/unitaires/test_formats.php`

```php
<?php
declare(strict_types=1);

test("formats : heures et dates comme Junior les dit, à l'heure de Paris", function () {
    egal('8 h', heure_lisible(utc_test('2026-10-14 06:00:00')));
    egal('8 h 30', heure_lisible(utc_test('2026-10-26 07:30:00')));  // heure d'hiver : UTC+1
    egal('19 h 15', heure_lisible(new DateTimeImmutable('2026-10-21T19:15:00+02:00')));
    egal('0 h 05', heure_lisible(utc_test('2026-10-13 22:05:00')));
    egal('Mercredi 14 octobre', date_longue(utc_test('2026-10-14 06:00:00')));
    egal('Dimanche 1er novembre', date_longue(utc_test('2026-10-31 23:30:00')));  // déjà le 1er à Paris
    egal('Mercredi', jour_nom(new DateTimeImmutable('2026-10-21T19:15:00+02:00')));
});

test("formats : pluriel, coupe, et jours entre deux dates (changement d'heure compris)", function () {
    egal(['1 tâche', '3 tâches'], [pluriel(1, 'tâche'), pluriel(3, 'tâche')]);
    egal('abc', couper('abc', 3));
    egal('ab…', couper('abcd', 3));
    egal(120, mb_strlen(couper(str_repeat('é', 300), 120)));
    egal(7, jours_entre('2026-10-14', '2026-10-21'));
    egal(2, jours_entre('2026-10-24', '2026-10-26'));
    egal(0, jours_entre('2026-10-25', '2026-10-25'));
});
```

- [ ] **Step 2 : les tests du brief** `tests/unitaires/test_brief.php`

```php
<?php
declare(strict_types=1);

// Jours de la copie de l'agenda, construits comme en vrai par jours_agenda()
function jours_brief(array $utc, array $perso, string $premier = '2026-10-14', int $nombre = 15): array
{
    return jours_agenda($utc, $perso, $premier, $nombre);
}

test('brief : un mercredi chargé, avec le premier cours, la tâche du jour, le médian et une réplique', function () {
    $jours = jours_brief(
        [evenement_google('NF93 - TD 1', '2026-10-14T08:00', '2026-10-14T10:00', 'FA400'), evenement_google('MT23 - TD', '2026-10-14T14:15', '2026-10-14T16:15')],
        [evenement_google('Médian MT23', '2026-10-21T19:15', '2026-10-21T20:45')]
    );
    $cartes = [
        carte_normalisee(['id' => id_test('carte', 1), 'titre' => 'Rendre le TP', 'echeance' => '2026-10-14T18:00:00+02:00']),
        carte_normalisee(['id' => id_test('carte', 2), 'echeance' => '2026-10-15T08:00:00+02:00']),
        carte_normalisee(['id' => id_test('carte', 3), 'colonne' => 'fait', 'echeance' => '2026-10-10T08:00:00+02:00']),
        carte_normalisee(['id' => id_test('carte', 4), 'echeance' => '2026-10-11T08:00:00+02:00', 'echeance_faite' => true]),
    ];
    $b = brief_du_matin('2026-10-14', $cartes, $jours);
    egal('Mercredi 14 octobre · 2 cours', $b['titre']);
    egal('Premier à 8 h en FA400 (NF93 TD). 1 tâche du jour. Médian MT23 dans 7 jours. ' . replique_du_jour('2026-10-14'), $b['texte']);
});

test("brief : sans cours avec des tâches en retard ; « 1 cours » ; le médian du lendemain ou du jour avec l'heure ; à 15 jours, rien", function () {
    $cartes = [carte_normalisee(['echeance' => '2026-10-12T20:00:00+02:00']), carte_normalisee(['id' => id_test('carte', 2), 'echeance' => '2026-10-13T09:00:00+02:00'])];
    $b = brief_du_matin('2026-10-17', $cartes, jours_brief([], []));
    egal('Samedi 17 octobre · Pas de cours', $b['titre']);
    egal('2 tâches en retard. ' . replique_du_jour('2026-10-17'), $b['texte']);
    egal('1 tâche du jour.', brief_taches($cartes, '2026-10-12'));
    egal('1 tâche du jour, 1 en retard.', brief_taches($cartes, '2026-10-13'));
    egal('2 tâches du jour, 1 en retard.', brief_taches([...$cartes, carte_normalisee(['id' => id_test('carte', 3), 'echeance' => '2026-10-13T18:00:00+02:00'])], '2026-10-13'));
    $median = [evenement_google('Médian MT23', '2026-10-21T19:15', '2026-10-21T20:45')];
    $jours = jours_brief([evenement_google('MT23 - Cours', '2026-10-20T10:15', '2026-10-20T12:15')], $median);
    $mardi = brief_du_matin('2026-10-20', [], $jours);
    egal('Mardi 20 octobre · 1 cours', $mardi['titre']);
    egal('Premier à 10 h 15 (MT23 Cours). Médian MT23 demain à 19 h 15. ' . replique_du_jour('2026-10-20'), $mardi['texte']);
    vrai(str_contains(brief_du_matin('2026-10-21', [], $jours)['texte'], "Médian MT23 aujourd'hui à 19 h 15."), "médian du jour");
    vrai(!str_contains(brief_du_matin('2026-10-06', [], jours_brief([], $median, '2026-10-06', 16))['texte'], 'Médian'), 'médian à 15 jours : pas cité');
});

test("brief : vacances dans le titre ; « devient un lundi » en tête du texte ; jour absent de la copie : ni cours ni « Pas de cours »", function () {
    $jours = jours_brief([
        evenement_google('Vacances universitaires', '2026-10-26', '2026-10-31'),
        evenement_google('Devient un lundi', '2026-11-03', '2026-11-04'),
        evenement_google('NF93 - TD', '2026-11-03T08:00', '2026-11-03T10:00', 'FA400'),
    ], [], '2026-10-26', 15);
    egal('Lundi 26 octobre · Vacances', brief_du_matin('2026-10-26', [], $jours)['titre']);
    $b = brief_du_matin('2026-11-03', [], $jours);
    egal('Mardi 3 novembre · 1 cours', $b['titre']);
    egal("Aujourd'hui, l'UTC suit l'emploi du temps du lundi. Premier à 8 h en FA400 (NF93 TD). " . replique_du_jour('2026-11-03'), $b['texte']);
    $absent = brief_du_matin('2026-12-01', [], $jours);
    egal(['Mardi 1er décembre', replique_du_jour('2026-12-01')], [$absent['titre'], $absent['texte']]);
});

test('brief : répliques tirées par la date, jamais deux jours de suite la même, chacune une fois par bloc de 20 jours', function () {
    egal(20, count(JR_REPLIQUES));
    egal(replique_du_jour('2026-10-14'), replique_du_jour('2026-10-14'));
    $debut = new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC'));
    $precedente = null;
    $blocs = [];
    for ($i = 0; $i < 400; $i++) {
        $jour = $debut->modify("+$i days");
        $r = replique_du_jour($jour->format('Y-m-d'));
        vrai($r !== $precedente, 'même réplique deux jours de suite : ' . $jour->format('Y-m-d'));
        $precedente = $r;
        $blocs[intdiv(intdiv($jour->getTimestamp(), 86400), 20)][] = $r;
    }
    foreach ($blocs as $bloc => $repliques) {
        if (count($repliques) === 20) {
            egal(20, count(array_unique($repliques)), "bloc $bloc");
        }
    }
});
```

- [ ] **Step 3 : les voir échouer**

Run: `{ php tests/run.php formats ; php tests/run.php brief ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: ÉCHEC, `Call to undefined function heure_lisible()` puis `brief_du_matin()`.

- [ ] **Step 4 : `serveur/lib/formats.php`**

```php
<?php
// Dates et heures écrites comme Junior les dit, à l'heure de Paris : « 8 h 30 », « Mercredi 14 octobre ».

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/creneaux.php';

const JR_MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

// 8 h, 8 h 30, 19 h 15
function heure_lisible(DateTimeImmutable $moment): string
{
    $p = $moment->setTimezone(paris());
    return $p->format('G') . ' h' . ($p->format('i') === '00' ? '' : ' ' . $p->format('i'));
}

function jour_nom(DateTimeImmutable $moment): string
{
    return ucfirst(JR_JOURS_SEMAINE[(int) $moment->setTimezone(paris())->format('N') - 1]);
}

// Mercredi 14 octobre, Dimanche 1er novembre
function date_longue(DateTimeImmutable $moment): string
{
    $p = $moment->setTimezone(paris());
    $jour = (int) $p->format('j');
    return jour_nom($p) . ' ' . ($jour === 1 ? '1er' : (string) $jour) . ' ' . JR_MOIS[(int) $p->format('n') - 1];
}

function pluriel(int $nombre, string $mot): string
{
    return $nombre . ' ' . $mot . ($nombre > 1 ? 's' : '');
}

// $max caractères au plus, points de suspension compris
function couper(string $texte, int $max): string
{
    return mb_strlen($texte) <= $max ? $texte : rtrim(mb_substr($texte, 0, $max - 1)) . '…';
}

// Jours de calendrier entre deux dates AAAA-MM-JJ, sans souci de changement d'heure
function jours_entre(string $de, string $a): int
{
    $utc = new DateTimeZone('UTC');
    return intdiv((new DateTimeImmutable($a, $utc))->getTimestamp() - (new DateTimeImmutable($de, $utc))->getTimestamp(), 86400);
}
```

- [ ] **Step 5 : `serveur/lib/brief.php`**

```php
<?php
// Le brief du matin (SPEC § 15.5) : titre et texte de la notification d'un jour. Fonctions pures.

declare(strict_types=1);

require_once __DIR__ . '/formats.php';

// CONTENU.md § 5.1 (proposition de Claude, à relire par Colin)
const JR_REPLIQUES = [
    "J'ai déjà tout rangé. Toi, tu n'as qu'à exister.",
    "Café d'abord, génie ensuite.",
    "Rien d'impossible aujourd'hui. J'ai vérifié.",
    "Je t'ai préparé la journée. Les applaudissements, c'est plus tard.",
    "Tu peux traîner au lit. Le Bug, lui, est déjà debout.",
    "Une journée de plus à me rendre indispensable.",
    "Si tu suis le plan, je prends le mérite. Sinon, c'est toi.",
    "Petit rappel : Jarvis, c'est moi en plus grand. Et en moins drôle.",
    "J'aurais fait la journée à ta place, mais il me manque des jambes.",
    "Les cartes Trello ne se terminent pas en les regardant. J'ai testé.",
    "Aujourd'hui, on vise la colonne Fait. Elle se sent seule.",
    "Le Poly t'attend. Il n'a rien d'autre à faire, lui.",
    "Debout, Majordome en devenir. Ah non, c'est moi le Majordome.",
    "Journée chargée. Heureusement, tu m'as.",
    "Météo de la journée : 100 % de chances que je te le rappelle.",
    "J'ai relu ton agenda. Courage. Sincèrement.",
    "La Flemme a demandé de tes nouvelles. Je lui ai dit que tu étais occupé.",
    "On fait simple : un GO, une tâche, une victoire.",
    "Tu as dormi. Moi non. Je ne me plains pas, je le signale.",
    "Si quelqu'un demande, c'était mon idée.",
];

// Le titre quand l'UTC a prévu autre chose qu'une journée de cours
const JR_BRIEF_BANDEAUX = ['vacances' => 'Vacances', 'ferie' => 'Férié', 'examen' => "Jour d'examen"];

// L'ordre des répliques d'un bloc de 20 jours : mélangé, mais toujours le même
function repliques_ordre(int $bloc): array
{
    $ordre = array_keys(JR_REPLIQUES);
    usort($ordre, fn(int $a, int $b): int => strcmp(hash('sha256', "$bloc:$a"), hash('sha256', "$bloc:$b")));
    return $ordre;
}

// Chaque réplique une fois par bloc de 20 jours, jamais deux jours de suite la même. Graine : la date.
function replique_du_jour(string $date): string
{
    $total = count(JR_REPLIQUES);
    $numero = intdiv((new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp(), 86400);
    $bloc = intdiv($numero, $total);
    $ordre = repliques_ordre($bloc);
    // Le premier jour d'un bloc ne répète pas le dernier du bloc précédent
    if ($ordre[0] === repliques_ordre($bloc - 1)[$total - 1]) {
        [$ordre[0], $ordre[1]] = [$ordre[1], $ordre[0]];
    }
    return JR_REPLIQUES[$ordre[$numero % $total]];
}

// Un jour absent de la copie de l'agenda (Google pas connecté, copie trop vieille) : ni cours ni
// « Pas de cours », on ne sait pas.
function brief_du_matin(string $date, array $cartes, array $jours): array
{
    $titre = date_longue(new DateTimeImmutable("$date 12:00", paris()));
    $morceaux = [];
    $jour = $jours[$date] ?? null;
    if (is_array($jour)) {
        $cours = array_values(array_filter($jour['evenements'] ?? [], fn(array $e): bool => $e['source'] === 'utc' && !$e['journee']));
        $etiquette = $cours === [] ? 'Pas de cours' : count($cours) . ' cours';
        foreach ($jour['bandeaux'] ?? [] as $bandeau) {
            if (isset(JR_BRIEF_BANDEAUX[$bandeau['type']])) {
                $etiquette = JR_BRIEF_BANDEAUX[$bandeau['type']];
            } elseif ($bandeau['type'] === 'devient') {
                $morceaux[] = $bandeau['texte'];
            }
        }
        $titre .= " · $etiquette";
        if ($cours !== []) {
            $premier = $cours[0];
            $nom = $premier['uv'] !== null ? "{$premier['uv']} {$premier['type']}" : $premier['titre'];
            $salle = $premier['salle'] !== null ? " en {$premier['salle']}" : '';
            $morceaux[] = 'Premier à ' . heure_lisible(new DateTimeImmutable($premier['debut'])) . "$salle ($nom).";
        }
    }
    $morceaux[] = brief_taches($cartes, $date);
    $morceaux[] = brief_median($jours, $date);
    $morceaux[] = replique_du_jour($date);
    return ['titre' => $titre, 'texte' => implode(' ', array_filter($morceaux, fn(string $m): bool => $m !== ''))];
}

// « 1 tâche du jour, 2 en retard. » : cartes ouvertes dont l'échéance tombe ce jour-là ou avant
function brief_taches(array $cartes, string $date): string
{
    $du_jour = 0;
    $en_retard = 0;
    foreach ($cartes as $c) {
        if ($c['echeance'] === null || $c['colonne'] === 'fait' || $c['echeance_faite']) {
            continue;
        }
        $jour = (new DateTimeImmutable($c['echeance']))->setTimezone(paris())->format('Y-m-d');
        if ($jour === $date) {
            $du_jour++;
        } elseif ($jour < $date) {
            $en_retard++;
        }
    }
    return match (true) {
        $du_jour > 0 && $en_retard > 0 => pluriel($du_jour, 'tâche') . " du jour, $en_retard en retard.",
        $du_jour > 0 => pluriel($du_jour, 'tâche') . ' du jour.',
        $en_retard > 0 => pluriel($en_retard, 'tâche') . ' en retard.',
        default => '',
    };
}

// Le médian le plus proche (événements « Médian … » de l'agenda principal), s'il tombe dans les 14 jours
function brief_median(array $jours, string $date): string
{
    ksort($jours);
    foreach ($jours as $jour => $contenu) {
        if ($jour < $date) {
            continue;
        }
        foreach ($contenu['evenements'] ?? [] as $e) {
            if (empty($e['median']) || $e['journee']) {
                continue;
            }
            $ecart = jours_entre($date, (string) $jour);
            $heure = heure_lisible(new DateTimeImmutable($e['debut']));
            return match (true) {
                $ecart > 14 => '',
                $ecart === 0 => "{$e['titre']} aujourd'hui à $heure.",
                $ecart === 1 => "{$e['titre']} demain à $heure.",
                default => "{$e['titre']} dans $ecart jours.",
            };
        }
    }
    return '';
}
```

- [ ] **Step 6 : tests au vert**

Run: `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: tout passe (186 tests).

- [ ] **Step 7 : commit**

```bash
git add serveur/lib/formats.php serveur/lib/brief.php tests/unitaires/test_formats.php tests/unitaires/test_brief.php
git commit -m "Lot 2 : brief du matin (premier cours, tâches, médian, réplique du jour) et heures lisibles" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Règles de génération des rappels

**Files:**
- Create: `serveur/lib/rappels.php`
- Test: `tests/unitaires/test_rappels.php`

**Interfaces:**
- Consumes: `brief_du_matin()`, `heure_lisible()`, `jour_nom()`, `pluriel()`, `couper()`, `jours_entre()` (tâche 2) ; `reglage_lire()` (`reglages.php`).
- Produces:
  - `JR_REGLAGES_RAPPELS` (valeurs par défaut), `JR_RAPPELS_AVANT` (86400), `JR_RAPPELS_APRES` (43200) ;
  - `reglages_rappels_valides(mixed $stockes): array` (pure) et `reglages_rappels(): array` (lit la clé `rappels`) : le plan 3 réutilise `reglages_rappels_valides()` pour écrire les réglages ;
  - `generer_rappels(array $cartes, array $jours, array $reglages, array $rappels, DateTimeImmutable $maintenant): array`, liste de `['cle' => string, 'type' => 'cours'|'echeance'|'median'|'brief'|'manuel', 'prevu_pour' => DateTimeImmutable (UTC), 'titre' => string (120 car. au plus), 'texte' => string (400 au plus), 'url' => string]`, une par clé. `$rappels` : `[['id' => int, 'texte' => string, 'quand' => DateTimeImmutable, 'cree_le' => DateTimeImmutable], …]`.

- [ ] **Step 1 : les tests** `tests/unitaires/test_rappels.php`

```php
<?php
declare(strict_types=1);

function cles_rappels(array $rappels): array
{
    $cles = array_column($rappels, 'cle');
    sort($cles);
    return $cles;
}

function rappel_cle(array $rappels, string $cle): array
{
    foreach ($rappels as $r) {
        if ($r['cle'] === $cle) {
            return $r;
        }
    }
    throw new EchecTest("rappel absent : $cle");
}

function reglages_sans_brief(): array
{
    $r = JR_REGLAGES_RAPPELS;
    $r['brief']['jours'] = [];
    return $r;
}

function cle_cours(string $debut, string $titre): string
{
    return "cours:$debut:" . substr(hash('sha256', $titre), 0, 12);
}

test('rappels : un cours prévient 15 min avant, avec sa salle, ou ses heures sans salle ; rien pour un cours commencé ni pour l\'agenda principal', function () {
    $jours = jours_agenda([
        evenement_google('TN01 - TD', '2026-10-14T16:45', '2026-10-14T18:45', 'FA510'),
        evenement_google('Conférence', '2026-10-14T10:00', '2026-10-14T11:30'),
        evenement_google('NF93 - TD', '2026-10-14T08:00', '2026-10-14T10:00', 'FA400'),
    ], [evenement_google('Dentiste', '2026-10-14T12:00', '2026-10-14T12:30')], '2026-10-14', 2);
    $liste = generer_rappels([], $jours, reglages_sans_brief(), [], utc_test('2026-10-14 06:30:00'));  // 8 h 30 à Paris
    egal([cle_cours('2026-10-14T10:00:00+02:00', 'Conférence'), cle_cours('2026-10-14T16:45:00+02:00', 'TN01 - TD')], cles_rappels($liste));
    $tn01 = rappel_cle($liste, cle_cours('2026-10-14T16:45:00+02:00', 'TN01 - TD'));
    egal(['cours', '2026-10-14 14:30:00', 'TN01 · TD dans 15 min', 'Salle FA510.', '/#/aujourdhui'], [$tn01['type'], utc($tn01['prevu_pour']), $tn01['titre'], $tn01['texte'], $tn01['url']]);
    $conference = rappel_cle($liste, cle_cours('2026-10-14T10:00:00+02:00', 'Conférence'));
    egal(['Conférence dans 15 min', 'De 10 h à 11 h 30.'], [$conference['titre'], $conference['texte']]);
});

test('rappels : les minutes des cours se règlent, et les rappels des cours se coupent', function () {
    $jours = jours_agenda([evenement_google('TN01 - TD', '2026-10-14T16:45', '2026-10-14T18:45', 'FA510')], [], '2026-10-14', 1);
    $r = reglages_sans_brief();
    $r['cours']['minutes'] = 30;
    $liste = generer_rappels([], $jours, $r, [], utc_test('2026-10-14 06:30:00'));
    egal(['2026-10-14 14:15:00', 'TN01 · TD dans 30 min'], [utc($liste[0]['prevu_pour']), $liste[0]['titre']]);
    $r['cours']['actif'] = false;
    egal([], generer_rappels([], $jours, $r, [], utc_test('2026-10-14 06:30:00')));
});

test("rappels : une échéance prévient la veille à 20 h (passage à l'heure d'hiver compris), avec les étapes qui restent", function () {
    $c = fn(int $n, array $s): array => carte_normalisee(['id' => id_test('carte', $n)] + $s);
    $cartes = [
        $c(1, ['titre' => 'Campagne LinkedIn', 'echeance' => '2026-10-26T08:30:00+01:00', 'checklists' => [checklist_normalisee(1, [false, false, false, false, false])]]),
        $c(2, ['titre' => 'Rendre le TP', 'echeance' => '2026-10-26T12:00:00+01:00', 'checklists' => [checklist_normalisee(2, [true, false]), checklist_normalisee(3, [true])]]),
        $c(3, ['titre' => 'Tout coché', 'echeance' => '2026-10-26T12:00:00+01:00', 'checklists' => [checklist_normalisee(4, [true])]]),
        $c(4, ['titre' => 'Sans étapes', 'echeance' => '2026-10-26T09:00:00+01:00']),
        $c(5, ['colonne' => 'fait', 'echeance' => '2026-10-26T09:00:00+01:00']),
        $c(6, ['echeance' => '2026-10-26T09:00:00+01:00', 'echeance_faite' => true]),
        $c(7, []),
    ];
    $liste = generer_rappels($cartes, [], reglages_sans_brief(), [], utc_test('2026-10-25 08:00:00'));
    egal(4, count($liste));
    $cle = fn(int $n, string $echeance): string => 'echeance:' . id_test('carte', $n) . ":$echeance";
    $linkedin = rappel_cle($liste, $cle(1, '2026-10-26T08:30:00+01:00'));
    egal(['echeance', '2026-10-25 19:00:00', 'Demain : Campagne LinkedIn', 'Échéance à 8 h 30. Il reste 5 étapes sur 5.', '/#/carte/' . id_test('carte', 1)],
        [$linkedin['type'], utc($linkedin['prevu_pour']), $linkedin['titre'], $linkedin['texte'], $linkedin['url']]);
    egal('Échéance à 12 h. Il reste 1 étape sur 3.', rappel_cle($liste, $cle(2, '2026-10-26T12:00:00+01:00'))['texte']);
    egal('Échéance à 12 h. Toutes les étapes sont cochées.', rappel_cle($liste, $cle(3, '2026-10-26T12:00:00+01:00'))['texte']);
    egal('Échéance à 9 h.', rappel_cle($liste, $cle(4, '2026-10-26T09:00:00+01:00'))['texte']);
});

test('rappels : un médian prévient à J-7, J-3 et J-1 à 9 h, avec les textes de CONTENU.md ; un médian « journée entière » est ignoré', function () {
    $jours = jours_agenda([], [evenement_google('Médian MT23', '2026-10-21T19:15', '2026-10-21T20:45'), evenement_google('Médian NF93', '2026-10-22', '2026-10-23')], '2026-10-13', 15);
    $vus = [];
    foreach (['2026-10-13 12:00:00', '2026-10-17 12:00:00', '2026-10-19 12:00:00'] as $moment) {
        foreach (generer_rappels([], $jours, reglages_sans_brief(), [], utc_test($moment)) as $r) {
            $vus[$r['cle']] = [utc($r['prevu_pour']), $r['titre'], $r['texte'], $r['url']];
        }
    }
    ksort($vus);
    egal([
        'median:2026-10-21T19:15:00+02:00:J1' => ['2026-10-20 07:00:00', 'Médian MT23 demain', '19 h 15. Dors bien ce soir.', '/#/agenda'],
        'median:2026-10-21T19:15:00+02:00:J3' => ['2026-10-18 07:00:00', 'Médian MT23 dans 3 jours', 'Mercredi à 19 h 15.', '/#/agenda'],
        'median:2026-10-21T19:15:00+02:00:J7' => ['2026-10-14 07:00:00', 'Médian MT23 dans 7 jours', 'Mercredi 21/10 à 19 h 15.', '/#/agenda'],
    ], $vus);
});

test("rappels : le brief part à 7 h 30 à Paris la veille et le jour du passage à l'heure d'hiver, seulement les jours choisis", function () {
    $r = JR_REGLAGES_RAPPELS;
    $samedi = generer_rappels([], [], $r, [], utc_test('2026-10-24 07:00:00'));
    egal(['brief:2026-10-24', 'brief:2026-10-25'], cles_rappels($samedi));
    egal('2026-10-24 05:30:00', utc(rappel_cle($samedi, 'brief:2026-10-24')['prevu_pour']));
    egal('2026-10-25 06:30:00', utc(rappel_cle($samedi, 'brief:2026-10-25')['prevu_pour']));
    egal(['brief', 'Samedi 24 octobre', '/#/aujourdhui'], [rappel_cle($samedi, 'brief:2026-10-24')['type'], rappel_cle($samedi, 'brief:2026-10-24')['titre'], rappel_cle($samedi, 'brief:2026-10-24')['url']]);
    $r['brief']['jours'] = [1, 2, 3, 4, 5];
    egal([], generer_rappels([], [], $r, [], utc_test('2026-10-24 07:00:00')));
});

test('rappels : un rappel manuel arrive à son heure, avec le jour où il a été mis', function () {
    $rappels = [
        ['id' => 1, 'texte' => 'Appeler Sébastien', 'quand' => utc_test('2026-10-14 08:00:00'), 'cree_le' => utc_test('2026-10-13 20:00:00')],
        ['id' => 2, 'texte' => 'Sortir le linge', 'quand' => utc_test('2026-10-14 09:00:00'), 'cree_le' => utc_test('2026-10-14 07:00:00')],
        ['id' => 3, 'texte' => "Payer l'URSSAF", 'quand' => utc_test('2026-10-14 10:00:00'), 'cree_le' => utc_test('2026-10-10 07:15:00')],
    ];
    $vus = [];
    foreach (generer_rappels([], [], reglages_sans_brief(), $rappels, utc_test('2026-10-14 06:00:00')) as $r) {
        $vus[$r['cle']] = [$r['type'], utc($r['prevu_pour']), $r['titre'], $r['texte'], $r['url']];
    }
    egal([
        'manuel:1' => ['manuel', '2026-10-14 08:00:00', 'Appeler Sébastien', "Rappel que tu t'es mis hier à 22 h.", '/#/aujourdhui'],
        'manuel:2' => ['manuel', '2026-10-14 09:00:00', 'Sortir le linge', "Rappel que tu t'es mis aujourd'hui à 9 h.", '/#/aujourdhui'],
        'manuel:3' => ['manuel', '2026-10-14 10:00:00', "Payer l'URSSAF", "Rappel que tu t'es mis le 10/10 à 9 h 15.", '/#/aujourdhui'],
    ], $vus);
});

test('rappels : seulement de 12 h avant à 24 h après le passage ; une clé une seule fois ; titre coupé à 120 caractères', function () {
    $rappel = fn(int $id, string $texte, string $quand): array => ['id' => $id, 'texte' => $texte, 'quand' => utc_test($quand), 'cree_le' => utc_test('2026-10-13 08:00:00')];
    $liste = generer_rappels([], [], reglages_sans_brief(), [
        $rappel(1, 'trop vieux', '2026-10-13 17:59:00'),
        $rappel(2, 'encore là', '2026-10-13 18:01:00'),
        $rappel(3, 'dans 24 h pile', '2026-10-15 06:00:00'),
        $rappel(4, 'trop tôt', '2026-10-15 06:01:00'),
        $rappel(2, 'doublon', '2026-10-14 08:00:00'),
        $rappel(5, str_repeat('long ', 100), '2026-10-14 08:00:00'),
    ], utc_test('2026-10-14 06:00:00'));
    egal(['manuel:2', 'manuel:3', 'manuel:5'], cles_rappels($liste));
    egal('encore là', rappel_cle($liste, 'manuel:2')['titre']);
    egal(120, mb_strlen(rappel_cle($liste, 'manuel:5')['titre']));
});

test('rappels : un réglage abîmé reprend sa valeur par défaut sans toucher aux autres ; une liste remplace la liste entière', function () {
    egal(JR_REGLAGES_RAPPELS, reglages_rappels_valides(null));
    egal(JR_REGLAGES_RAPPELS, reglages_rappels_valides('texte'));
    $r = reglages_rappels_valides([
        'brief' => ['heure' => '25:99', 'jours' => [1, 2]],
        'cours' => ['actif' => 'oui', 'minutes' => 30],
        'echeances' => 'x',
        'medians' => ['jours' => [3, 'x'], 'heure' => '08:00', 'actif' => false],
    ]);
    egal(['heure' => '07:30', 'jours' => [1, 2]], $r['brief']);
    egal(['actif' => true, 'minutes' => 30], $r['cours']);
    egal(JR_REGLAGES_RAPPELS['echeances'], $r['echeances']);
    egal(['actif' => false, 'jours' => [7, 3, 1], 'heure' => '08:00'], $r['medians']);
    egal([], reglages_rappels_valides(['brief' => ['jours' => []]])['brief']['jours']);
});

test('rappels : reglages_rappels() lit la clé « rappels » de la table reglages', function () {
    base_de_test();
    egal(JR_REGLAGES_RAPPELS, reglages_rappels());
    reglage_ecrire('rappels', ['cours' => ['minutes' => 20]]);
    egal(20, reglages_rappels()['cours']['minutes']);
});
```

- [ ] **Step 2 : les voir échouer**

Run: `{ php tests/run.php rappels ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: ÉCHEC, `Undefined constant "JR_REGLAGES_RAPPELS"`.

- [ ] **Step 3 : `serveur/lib/rappels.php`**

```php
<?php
// Les notifications à préparer (SPEC § 15.4) : cours, échéances, médians, brief du matin, rappels manuels.
// Tout est pur, sauf reglages_rappels() qui lit la base.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/brief.php';
require_once __DIR__ . '/formats.php';
require_once __DIR__ . '/reglages.php';

const JR_RAPPELS_AVANT = 86400;  // les notifications des 24 h à venir
const JR_RAPPELS_APRES = 43200;  // et celles des 12 dernières heures, pas encore périmées (la plus longue péremption)

// Valeurs par défaut (CONTENU.md § 3, Réglages). Brief : jours ISO, 1 = lundi ; aucun jour = pas de brief.
const JR_REGLAGES_RAPPELS = [
    'brief' => ['heure' => '07:30', 'jours' => [1, 2, 3, 4, 5, 6, 7]],
    'cours' => ['actif' => true, 'minutes' => 15],
    'echeances' => ['actif' => true, 'heure' => '20:00'],
    'medians' => ['actif' => true, 'jours' => [7, 3, 1], 'heure' => '09:00'],
];

// Chaque valeur abîmée reprend sa valeur par défaut : un réglage illisible ne doit jamais arrêter le planificateur
function reglages_rappels_valides(mixed $stockes): array
{
    $heure = fn(mixed $v): bool => is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) === 1;
    $liste = fn(int $min, int $max): Closure => fn(mixed $v): bool => is_array($v) && array_is_list($v)
        && array_filter($v, fn(mixed $n): bool => !is_int($n) || $n < $min || $n > $max) === [];
    $regles = [
        'brief' => ['heure' => $heure, 'jours' => $liste(1, 7)],
        'cours' => ['actif' => 'is_bool', 'minutes' => fn(mixed $v): bool => is_int($v) && $v >= 1 && $v <= 120],
        'echeances' => ['actif' => 'is_bool', 'heure' => $heure],
        'medians' => ['actif' => 'is_bool', 'jours' => $liste(1, 30), 'heure' => $heure],
    ];
    $reglages = JR_REGLAGES_RAPPELS;
    foreach ($regles as $partie => $champs) {
        $stockee = is_array($stockes) && is_array($stockes[$partie] ?? null) ? $stockes[$partie] : [];
        foreach ($champs as $champ => $valide) {
            $valeur = $stockee[$champ] ?? null;
            if ($valeur !== null && $valide($valeur)) {
                $reglages[$partie][$champ] = is_array($valeur) ? array_values(array_unique($valeur)) : $valeur;
            }
        }
    }
    return $reglages;
}

function reglages_rappels(): array
{
    return reglages_rappels_valides(reglage_lire('rappels'));
}

function rappel(string $cle, string $type, DateTimeImmutable $prevu_pour, string $titre, string $texte, string $url): array
{
    return [
        'cle' => $cle,
        'type' => $type,
        'prevu_pour' => $prevu_pour->setTimezone(new DateTimeZone('UTC')),
        'titre' => couper($titre, 120),
        'texte' => couper($texte, 400),
        'url' => $url,
    ];
}

// À Paris : le jour de $moment moins $jours_avant jours, à l'heure HH:MM (changement d'heure compris)
function a_heure(DateTimeImmutable $moment, string $heure, int $jours_avant = 0): DateTimeImmutable
{
    [$h, $m] = array_map('intval', explode(':', $heure));
    return $moment->setTimezone(paris())->setTime(0, 0)->modify("-$jours_avant days")->setTime($h, $m);
}

// Les créneaux de l'agenda UTC (pas l'agenda principal), $minutes avant le début. Rien pour un cours commencé.
function rappels_cours(array $jours, array $reglage, DateTimeImmutable $maintenant): array
{
    $liste = [];
    $minutes = $reglage['minutes'];
    foreach ($jours as $jour) {
        foreach ($jour['evenements'] ?? [] as $e) {
            if ($e['source'] !== 'utc' || $e['journee']) {
                continue;
            }
            $debut = new DateTimeImmutable($e['debut']);
            if ($debut <= $maintenant) {
                continue;
            }
            $nom = $e['uv'] !== null ? "{$e['uv']} · {$e['type']}" : $e['titre'];
            $texte = $e['salle'] !== null ? "Salle {$e['salle']}." : 'De ' . heure_lisible($debut) . ' à ' . heure_lisible(new DateTimeImmutable($e['fin'])) . '.';
            $cle = 'cours:' . $e['debut'] . ':' . substr(hash('sha256', $e['titre']), 0, 12);
            $liste[] = rappel($cle, 'cours', $debut->modify("-$minutes minutes"), "$nom dans $minutes min", $texte, '/#/aujourdhui');
        }
    }
    return $liste;
}

// Cartes ouvertes avec une échéance pas encore faite, la veille à l'heure réglée
function rappels_echeances(array $cartes, array $reglage): array
{
    $liste = [];
    foreach ($cartes as $c) {
        if ($c['echeance'] === null || $c['colonne'] === 'fait' || $c['echeance_faite']) {
            continue;
        }
        $echeance = new DateTimeImmutable($c['echeance']);
        $elements = array_merge([], ...array_map(fn(array $l): array => $l['elements'], $c['checklists']));
        $reste = count(array_filter($elements, fn(array $e): bool => !$e['coche']));
        $etapes = match (true) {
            $elements === [] => '',
            $reste === 0 => ' Toutes les étapes sont cochées.',
            default => ' Il reste ' . pluriel($reste, 'étape') . ' sur ' . count($elements) . '.',
        };
        $liste[] = rappel("echeance:{$c['id']}:{$c['echeance']}", 'echeance', a_heure($echeance, $reglage['heure'], 1),
            "Demain : {$c['titre']}", 'Échéance à ' . heure_lisible($echeance) . '.' . $etapes, "/#/carte/{$c['id']}");
    }
    return $liste;
}

// Événements « Médian … » de l'agenda principal (repérés par creneaux.php), avec une heure, à J-n
function rappels_medians(array $jours, array $reglage, DateTimeImmutable $maintenant): array
{
    $liste = [];
    foreach ($jours as $jour) {
        foreach ($jour['evenements'] ?? [] as $e) {
            if (empty($e['median']) || $e['journee']) {
                continue;
            }
            $debut = new DateTimeImmutable($e['debut']);
            if ($debut <= $maintenant) {
                continue;
            }
            $heure = heure_lisible($debut);
            foreach ($reglage['jours'] as $n) {
                [$titre, $texte] = match (true) {
                    $n === 1 => ["{$e['titre']} demain", "$heure. Dors bien ce soir."],
                    $n >= 7 => ["{$e['titre']} dans $n jours", jour_nom($debut) . ' ' . $debut->setTimezone(paris())->format('d/m') . " à $heure."],
                    default => ["{$e['titre']} dans $n jours", jour_nom($debut) . " à $heure."],
                };
                $liste[] = rappel("median:{$e['debut']}:J$n", 'median', a_heure($debut, $reglage['heure'], $n), $titre, $texte, '/#/agenda');
            }
        }
    }
    return $liste;
}

// Le brief d'aujourd'hui et celui de demain, les jours choisis
function rappels_brief(array $cartes, array $jours, array $reglage, DateTimeImmutable $maintenant): array
{
    $liste = [];
    $aujourdhui = $maintenant->setTimezone(paris())->setTime(0, 0);
    foreach ([$aujourdhui, $aujourdhui->modify('+1 day')] as $jour) {
        if (!in_array((int) $jour->format('N'), $reglage['jours'], true)) {
            continue;
        }
        $date = $jour->format('Y-m-d');
        $brief = brief_du_matin($date, $cartes, $jours);
        $liste[] = rappel("brief:$date", 'brief', a_heure($jour, $reglage['heure']), $brief['titre'], $brief['texte'], '/#/aujourdhui');
    }
    return $liste;
}

function rappels_manuels(array $rappels): array
{
    $liste = [];
    foreach ($rappels as $r) {
        $cree_le = $r['cree_le']->setTimezone(paris());
        $quand = match (jours_entre($cree_le->format('Y-m-d'), $r['quand']->setTimezone(paris())->format('Y-m-d'))) {
            0 => "aujourd'hui",
            1 => 'hier',
            default => 'le ' . $cree_le->format('d/m'),
        };
        $liste[] = rappel("manuel:{$r['id']}", 'manuel', $r['quand'], $r['texte'], "Rappel que tu t'es mis $quand à " . heure_lisible($cree_le) . '.', '/#/aujourdhui');
    }
    return $liste;
}

// Les notifications prévues entre 12 h avant et 24 h après $maintenant, une par clé. Une source vide
// ($cartes ou $jours à []) ne donne rien pour elle.
function generer_rappels(array $cartes, array $jours, array $reglages, array $rappels, DateTimeImmutable $maintenant): array
{
    $liste = array_merge(
        $reglages['cours']['actif'] ? rappels_cours($jours, $reglages['cours'], $maintenant) : [],
        $reglages['echeances']['actif'] ? rappels_echeances($cartes, $reglages['echeances']) : [],
        $reglages['medians']['actif'] ? rappels_medians($jours, $reglages['medians'], $maintenant) : [],
        rappels_brief($cartes, $jours, $reglages['brief'], $maintenant),
        rappels_manuels($rappels),
    );
    $debut = $maintenant->modify('-' . JR_RAPPELS_APRES . ' seconds');
    $fin = $maintenant->modify('+' . JR_RAPPELS_AVANT . ' seconds');
    $gardes = [];
    foreach ($liste as $r) {
        if ($r['prevu_pour'] > $debut && $r['prevu_pour'] <= $fin) {
            $gardes[$r['cle']] ??= $r;
        }
    }
    return array_values($gardes);
}
```

- [ ] **Step 4 : tests au vert**

Run: `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: tout passe (195 tests).

- [ ] **Step 5 : commit**

```bash
git add serveur/lib/rappels.php tests/unitaires/test_rappels.php
git commit -m "Lot 2 : génération des rappels (cours, échéances, médians, brief, manuels) et réglages par défaut" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: La table `envois` : enregistrer, envoyer, périmer, réessayer, purger

**Files:**
- Create: `serveur/lib/envois.php`
- Modify: `serveur/lib/abonnements.php` (`notifier_tous()` : compte `a_reessayer`)
- Test: `tests/unitaires/test_envois.php`, `tests/unitaires/test_abonnements.php`, `tests/unitaires/test_routes_notifications.php`

**Interfaces:**
- Consumes: la liste de `generer_rappels()` (tâche 3) ; `notifier_tous(array $message, string $urgence, int $ttl)` (plan 1).
- Produces:
  - `notifier_tous()` rend `['envoyes' => int, 'supprimes' => int, 'echecs' => int, 'a_reessayer' => int]` (`a_reessayer` : les échecs dus à un 429, une 5xx ou au réseau, déjà comptés dans `echecs`) ;
  - `envois_enregistrer(array $rappels): void` ;
  - `envoyer_dus(DateTimeImmutable $maintenant, float $limite): array` → `['envoyees' => int, 'perimees' => int, 'echouees' => int, 'reportees' => int]` (`$limite` : heure `microtime(true)` au-delà de laquelle plus rien ne part) ;
  - `envois_purger(DateTimeImmutable $maintenant): void`.

- [ ] **Step 1 : `notifier_tous()`, le test d'abord**

Dans `tests/unitaires/test_abonnements.php`, l'attendu du test « un 410 supprime… » devient `['envoyes' => 1, 'supprimes' => 1, 'echecs' => 0, 'a_reessayer' => 0]`. Dans `tests/unitaires/test_routes_notifications.php`, les deux attendus du test « test sans abonnement… » gagnent `'a_reessayer' => 0` à la fin. Puis, dans `test_abonnements.php` :

```php
test("abonnements : 429, 5xx et panne réseau sont à réessayer, un 403 non", function () {
    base_de_test();
    abonnement_enregistrer(abonnement_exemple(), null);
    $message = ['titre' => 'T', 'texte' => 'x', 'url' => '/#/aujourdhui', 'tag' => 't'];
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 429, 'corps' => '']]);
    egal(['envoyes' => 0, 'supprimes' => 0, 'echecs' => 1, 'a_reessayer' => 1], notifier_tous($message));
    faux_http(['POST https://web.push.apple.com/' => new ErreurReseau('Réseau : délai dépassé')]);
    egal(1, notifier_tous($message)['a_reessayer']);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 403, 'corps' => '']]);
    egal(['envoyes' => 0, 'supprimes' => 0, 'echecs' => 1, 'a_reessayer' => 0], notifier_tous($message));
});
```

Run: `{ php tests/run.php abonnements ; php tests/run.php 'routes notifications' ; } 2>&1 | ~/.claude/hooks/redact-secrets` → ÉCHEC (clé `a_reessayer` absente).

Dans `serveur/lib/abonnements.php`, `notifier_tous()` : le bilan de départ devient `['envoyes' => 0, 'supprimes' => 0, 'echecs' => 0, 'a_reessayer' => 0]`, et la branche `else` finit par :

```php
            $bilan['echecs']++;
            if ($statut === 0 || $statut === 429 || $statut >= 500) {
                $bilan['a_reessayer']++;
            }
```

Run: même commande → tout passe.

- [ ] **Step 2 : les tests de `envois`** `tests/unitaires/test_envois.php`

```php
<?php
declare(strict_types=1);

function rappel_envoi(string $cle, string $prevu_pour, array $surcharges = []): array
{
    return array_replace(['cle' => $cle, 'type' => 'manuel', 'prevu_pour' => utc_test($prevu_pour), 'titre' => 'Titre', 'texte' => 'Texte', 'url' => '/#/aujourdhui'], $surcharges);
}

function statuts_envois(): array
{
    return db()->query('SELECT cle, statut FROM envois ORDER BY cle')->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Bilan de envoyer_dus() : comparé strictement, l'ordre des clés compte, d'où array_replace()
const BILAN_VIDE = ['envoyees' => 0, 'perimees' => 0, 'echouees' => 0, 'reportees' => 0];

function bilan_envois(array $valeurs): array
{
    return array_replace(BILAN_VIDE, $valeurs);
}

test("envois : enregistrés une fois par clé ; une prévue suit le nouveau texte et la nouvelle heure, une envoyée ne bouge plus", function () {
    base_de_test();
    $liste = [rappel_envoi('manuel:1', '2026-10-14 08:00:00'), rappel_envoi('manuel:2', '2026-10-14 09:00:00')];
    envois_enregistrer($liste);
    envois_enregistrer($liste);
    egal(['manuel:1' => 'prevu', 'manuel:2' => 'prevu'], statuts_envois());
    db()->exec("UPDATE envois SET statut = 'envoye' WHERE cle = 'manuel:1'");
    envois_enregistrer([rappel_envoi('manuel:1', '2026-10-14 08:30:00', ['titre' => 'Nouveau']), rappel_envoi('manuel:2', '2026-10-14 09:30:00', ['titre' => 'Nouveau'])]);
    egal([['manuel:1', '2026-10-14 08:00:00', 'Titre'], ['manuel:2', '2026-10-14 09:30:00', 'Nouveau']],
        db()->query('SELECT cle, prevu_pour, titre FROM envois ORDER BY cle')->fetchAll(PDO::FETCH_NUM));
});

test("envois : une prévue absente de la liste est retirée, et redevient prévue si elle réapparaît ; une envoyée reste envoyée", function () {
    base_de_test();
    envois_enregistrer([rappel_envoi('a', '2026-10-14 08:00:00'), rappel_envoi('b', '2026-10-14 09:00:00'), rappel_envoi('c', '2026-10-14 10:00:00')]);
    db()->exec("UPDATE envois SET statut = 'envoye' WHERE cle = 'c'");
    envois_enregistrer([rappel_envoi('a', '2026-10-14 08:00:00')]);
    egal(['a' => 'prevu', 'b' => 'retire', 'c' => 'envoye'], statuts_envois());
    envois_enregistrer([rappel_envoi('a', '2026-10-14 08:00:00'), rappel_envoi('b', '2026-10-14 09:15:00')]);
    egal(['a' => 'prevu', 'b' => 'prevu', 'c' => 'envoye'], statuts_envois());
    egal('2026-10-14 09:15:00', db()->query("SELECT prevu_pour FROM envois WHERE cle = 'b'")->fetchColumn());
    envois_enregistrer([]);
    egal(['a' => 'retire', 'b' => 'retire', 'c' => 'envoye'], statuts_envois());
});

test('envois : seules les dues partent, une fois, la plus ancienne d\'abord, avec TTL et urgence selon le type', function () {
    base_de_test();
    fixer_heure('2026-10-14 06:50:00');
    abonnement_enregistrer(abonnement_exemple(), null);
    envois_enregistrer([
        rappel_envoi('manuel:1', '2026-10-14 06:50:00'),
        rappel_envoi('cours:x', '2026-10-14 06:45:00', ['type' => 'cours']),
        rappel_envoi('manuel:2', '2026-10-14 06:51:00'),
    ]);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    egal(bilan_envois(['envoyees' => 2]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    $entetes = array_map(fn(array $a): string => implode("\n", $a['entetes']), $GLOBALS['JR_APPELS_HTTP']);
    vrai(str_contains($entetes[0], 'TTL: 900') && str_contains($entetes[0], 'Urgency: high'), 'cours en premier : TTL 900, urgence haute');
    vrai(str_contains($entetes[1], 'TTL: 43200') && str_contains($entetes[1], 'Urgency: normal'), 'rappel : TTL 12 h, urgence normale');
    egal(['cours:x' => 'envoye', 'manuel:1' => 'envoye', 'manuel:2' => 'prevu'], statuts_envois());
    egal('2026-10-14 06:50:00', db()->query("SELECT envoye_le FROM envois WHERE cle = 'cours:x'")->fetchColumn());
    egal(bilan_envois([]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    egal(2, count($GLOBALS['JR_APPELS_HTTP']));
});

test('envois : trop en retard, une notification est périmée sans partir (cours 10 min, échéance 3 h, brief 2 h, rappel 12 h)', function () {
    base_de_test();
    fixer_heure('2026-10-14 12:00:00');
    abonnement_enregistrer(abonnement_exemple(), null);
    envois_enregistrer([
        rappel_envoi('cours:vieux', '2026-10-14 11:49:59', ['type' => 'cours']),
        rappel_envoi('cours:juste', '2026-10-14 11:50:00', ['type' => 'cours']),
        rappel_envoi('echeance:vieille', '2026-10-14 08:59:59', ['type' => 'echeance']),
        rappel_envoi('brief:vieux', '2026-10-14 09:59:59', ['type' => 'brief']),
        rappel_envoi('brief:juste', '2026-10-14 10:00:00', ['type' => 'brief']),
        rappel_envoi('manuel:vieux', '2026-10-13 23:59:59'),
    ]);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    egal(bilan_envois(['envoyees' => 2, 'perimees' => 4]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    egal(['brief:juste' => 'envoye', 'brief:vieux' => 'perime', 'cours:juste' => 'envoye', 'cours:vieux' => 'perime', 'echeance:vieille' => 'perime', 'manuel:vieux' => 'perime'], statuts_envois());
});

test("envois : un 503 est réessayé aux passages suivants, 3 essais au plus ; un 403 échoue tout de suite ; sans abonnement, rien à envoyer", function () {
    base_de_test();
    fixer_heure('2026-10-14 12:00:00');
    envois_enregistrer([rappel_envoi('manuel:personne', '2026-10-14 12:00:00')]);
    faux_http([]);
    egal(bilan_envois(['envoyees' => 1]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    abonnement_enregistrer(abonnement_exemple(), null);
    envois_enregistrer([rappel_envoi('manuel:1', '2026-10-14 12:00:00')]);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 503, 'corps' => '']]);
    egal(bilan_envois(['reportees' => 1]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    egal(bilan_envois(['reportees' => 1]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    egal(bilan_envois(['echouees' => 1]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    $l = db()->query("SELECT statut, essais FROM envois WHERE cle = 'manuel:1'")->fetch();
    egal(['echoue', 3], [$l['statut'], (int) $l['essais']]);
    envois_enregistrer([rappel_envoi('manuel:2', '2026-10-14 12:00:00')]);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 403, 'corps' => '']]);
    egal(bilan_envois(['echouees' => 1]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
});

test("envois : rien ne part une fois la limite de temps passée ; la purge efface au-delà de 30 jours", function () {
    base_de_test();
    fixer_heure('2026-10-14 12:00:00');
    abonnement_enregistrer(abonnement_exemple(), null);
    envois_enregistrer([rappel_envoi('manuel:1', '2026-10-14 12:00:00'), rappel_envoi('manuel:ancien', '2026-09-14 11:59:00')]);
    faux_http([]);
    egal(0, envoyer_dus(maintenant(), 0.0)['envoyees']);
    egal('prevu', statuts_envois()['manuel:1']);
    envois_purger(maintenant());
    egal(['manuel:1'], array_keys(statuts_envois()));
});
```

- [ ] **Step 3 : les voir échouer**

Run: `{ php tests/run.php envois ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: ÉCHEC, `Call to undefined function envois_enregistrer()`.

- [ ] **Step 4 : `serveur/lib/envois.php`**

```php
<?php
// La table envois (SPEC § 15.2) : une ligne par notification, la clé interdit les doublons.

declare(strict_types=1);

require_once __DIR__ . '/abonnements.php';
require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';

// Au-delà de ce retard (secondes), une notification ne part plus
const JR_PEREMPTION = ['cours' => 600, 'median' => 10800, 'echeance' => 10800, 'brief' => 7200, 'manuel' => 43200];
const JR_ENVOI_ESSAIS = 3;
const JR_ENVOIS_JOURS = 30;

// Ajoute les nouvelles. Une ligne encore prévue suit la source (heure, texte) ; une retirée qui réapparaît
// redevient prévue ; envoyée, périmée ou échouée, elle ne bouge plus. Les prévues absentes de la liste
// sont retirées (cours supprimé, carte terminée, rappel annulé, réglage coupé).
function envois_enregistrer(array $rappels): void
{
    $ajout = db()->prepare("INSERT INTO envois (cle, type, prevu_pour, titre, texte, url) VALUES (?, ?, ?, ?, ?, ?) AS n
        ON DUPLICATE KEY UPDATE
            statut = IF(statut = 'retire', 'prevu', statut),
            prevu_pour = IF(statut = 'prevu', n.prevu_pour, prevu_pour),
            titre = IF(statut = 'prevu', n.titre, titre),
            texte = IF(statut = 'prevu', n.texte, texte),
            url = IF(statut = 'prevu', n.url, url)");
    foreach ($rappels as $r) {
        $ajout->execute([$r['cle'], $r['type'], utc($r['prevu_pour']), $r['titre'], $r['texte'], $r['url']]);
    }
    $cles = array_column($rappels, 'cle');
    $sauf = $cles === [] ? '' : ' AND cle NOT IN (' . implode(',', array_fill(0, count($cles), '?')) . ')';
    db()->prepare("UPDATE envois SET statut = 'retire' WHERE statut = 'prevu'$sauf")->execute($cles);
}

// Les dues, la plus ancienne d'abord, tant que $limite (microtime) n'est pas passée : le reste part au
// passage suivant. 429, 5xx ou réseau : nouvel essai au passage suivant. Personne d'abonné : rien à faire.
function envoyer_dus(DateTimeImmutable $maintenant, float $limite): array
{
    $bilan = ['envoyees' => 0, 'perimees' => 0, 'echouees' => 0, 'reportees' => 0];
    $dues = db()->prepare("SELECT cle, type, prevu_pour, titre, texte, url, essais FROM envois
        WHERE statut = 'prevu' AND prevu_pour <= ? ORDER BY prevu_pour, cle LIMIT 50");
    $dues->execute([utc($maintenant)]);
    $noter = db()->prepare('UPDATE envois SET statut = ?, essais = ?, envoye_le = ? WHERE cle = ?');
    foreach ($dues->fetchAll() as $e) {
        $essais = (int) $e['essais'];
        if ($maintenant->getTimestamp() - depuis_utc($e['prevu_pour'])->getTimestamp() > (JR_PEREMPTION[$e['type']] ?? 3600)) {
            $noter->execute(['perime', $essais, null, $e['cle']]);
            $bilan['perimees']++;
            continue;
        }
        if (microtime(true) > $limite) {
            break;
        }
        $cours = $e['type'] === 'cours';
        $r = notifier_tous(['titre' => $e['titre'], 'texte' => $e['texte'], 'url' => $e['url'], 'tag' => $e['cle']], $cours ? 'high' : 'normal', $cours ? 900 : 43200);
        $essais++;
        if ($r['envoyes'] === 0 && $r['a_reessayer'] > 0 && $essais < JR_ENVOI_ESSAIS) {
            $noter->execute(['prevu', $essais, null, $e['cle']]);
            $bilan['reportees']++;
        } elseif ($r['envoyes'] === 0 && $r['echecs'] > 0) {
            $noter->execute(['echoue', $essais, null, $e['cle']]);
            $bilan['echouees']++;
        } else {
            $noter->execute(['envoye', $essais, utc($maintenant), $e['cle']]);
            $bilan['envoyees']++;
        }
    }
    return $bilan;
}

function envois_purger(DateTimeImmutable $maintenant): void
{
    db()->prepare('DELETE FROM envois WHERE prevu_pour < ? LIMIT 500')->execute([utc($maintenant->modify('-' . JR_ENVOIS_JOURS . ' days'))]);
}
```

- [ ] **Step 5 : tests au vert**

Run: `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: tout passe (202 tests).

- [ ] **Step 6 : commit**

```bash
git add serveur/lib/envois.php serveur/lib/abonnements.php tests/unitaires/test_envois.php tests/unitaires/test_abonnements.php tests/unitaires/test_routes_notifications.php
git commit -m "Lot 2 : table envois (sans doublon, retrait, péremption, 3 essais, purge)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Un passage du planificateur

**Files:**
- Create: `serveur/lib/planificateur.php`
- Modify: `serveur/lib/donnees.php` (`donnees_trello()` et `donnees_agenda()` : âge maximal en paramètre)
- Test: `tests/unitaires/test_planificateur.php`

**Interfaces:**
- Consumes: `generer_rappels()`, `reglages_rappels()`, `JR_RAPPELS_AVANT`, `JR_RAPPELS_APRES` (tâche 3) ; `envois_enregistrer()`, `envoyer_dus()`, `envois_purger()` (tâche 4) ; `donnees_trello()`, `donnees_agenda()`, `JR_JOURS_DONNEES`, `copie_lire()` (lot 1).
- Produces:
  - `donnees_trello(bool $force, int $age = JR_AGE_TRELLO)`, `donnees_agenda(string $source, string $premier, int $nombre, bool $force = false, int $age = JR_AGE_AGENDA)` (appels existants inchangés) ;
  - `planificateur_passage(?float $limite = null): array` → `['verrou' => false]` si un autre passage tourne, sinon `['verrou' => true, 'envoyees' => …, 'perimees' => …, 'echouees' => …, 'reportees' => …]` ; écrit le réglage `cron_dernier_passage` (heure de Paris, ISO).

- [ ] **Step 1 : les tests** `tests/unitaires/test_planificateur.php`

```php
<?php
declare(strict_types=1);

// Copies Trello et agenda toutes neuves (rien à relire) et Google connecté, comme après une ouverture de l'app
function copies_fraiches(array $cartes, array $evenements_utc = [], array $evenements_perso = []): void
{
    $premier = aujourdhui_paris()->format('Y-m-d');
    copie_trello($cartes);
    copie_ecrire('agenda', ['premier' => $premier, 'jours' => jours_agenda($evenements_utc, $evenements_perso, $premier, JR_JOURS_DONNEES)], maintenant());
    google_connecte_pour_test();
}

function sans_brief_en_base(): void
{
    reglage_ecrire('rappels', ['brief' => ['jours' => []]]);
}

function appel_google(): bool
{
    return array_filter(appels_http(), fn(string $a): bool => str_contains($a, 'googleapis.com/calendar')) !== [];
}

test('planificateur : un passage envoie le rappel du cours une fois ; le passage suivant ne renvoie rien (aucun doublon)', function () {
    base_de_test();
    sans_brief_en_base();
    fixer_heure('2026-10-14 14:31:00');  // 16 h 31 à Paris
    copies_fraiches([], [evenement_google('TN01 - TD', '2026-10-14T16:45', '2026-10-14T18:45', 'FA510')]);
    abonnement_enregistrer(abonnement_exemple(), null);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    egal(['verrou' => true, 'envoyees' => 1, 'perimees' => 0, 'echouees' => 0, 'reportees' => 0], planificateur_passage());
    fixer_heure('2026-10-14 14:32:00');
    egal(['verrou' => true, 'envoyees' => 0, 'perimees' => 0, 'echouees' => 0, 'reportees' => 0], planificateur_passage());
    egal(1, count($GLOBALS['JR_APPELS_HTTP']));
    egal([['TN01 · TD dans 15 min', 'envoye']], db()->query('SELECT titre, statut FROM envois')->fetchAll(PDO::FETCH_NUM));
    egal('2026-10-14T16:32:00+02:00', reglage_lire('cron_dernier_passage'));
});

test('planificateur : si un autre passage tient le verrou, celui-ci repart aussitôt sans rien faire', function () {
    base_de_test();
    $autre = nouvelle_connexion_test();
    egal(1, (int) $autre->query("SELECT GET_LOCK('jr_cron', 0)")->fetchColumn());
    faux_http([]);
    egal(['verrou' => false], planificateur_passage());
    egal(null, reglage_lire('cron_dernier_passage'));
    $autre->query("SELECT RELEASE_LOCK('jr_cron')");
});

test("planificateur : Trello relu au-delà de 15 min, l'agenda au-delà d'une heure, pas avant", function () {
    base_de_test();
    sans_brief_en_base();
    fixer_heure('2026-10-14 10:00:00');
    copies_fraiches([]);
    fixer_heure('2026-10-14 10:14:59');
    faux_http([]);
    planificateur_passage();
    egal([], appels_http());
    fixer_heure('2026-10-14 10:59:59');
    faux_http([BATCH => ['json' => batch_trello()]]);
    planificateur_passage();
    vrai(str_starts_with(appels_http()[0] ?? '', BATCH), 'Trello relu après 15 min');
    vrai(!appel_google(), "agenda pas relu avant 1 h");
    fixer_heure('2026-10-14 11:00:00');
    google_connecte_pour_test();  // jeton d'accès Google encore valable
    faux_http(['GET https://www.googleapis.com/calendar/v3/calendars/' => ['json' => ['items' => []]]]);
    planificateur_passage();
    vrai(appel_google(), 'agenda relu après 1 h');
});

test("planificateur : Trello en panne, l'agenda et l'envoi continuent avec la dernière copie, et la panne va au journal", function () {
    base_de_test();
    sans_brief_en_base();
    fixer_heure('2026-10-14 14:00:00');
    copies_fraiches([carte_normalisee(['echeance' => '2026-10-15T08:30:00+02:00'])], [evenement_google('TN01 - TD', '2026-10-14T16:45', '2026-10-14T18:45', 'FA510')]);
    fixer_heure('2026-10-14 14:31:00');  // copie Trello de 31 min : relue, mais Trello répond 503
    abonnement_enregistrer(abonnement_exemple(), null);
    faux_http([BATCH => ['statut' => 503, 'corps' => ''], 'POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    egal(1, planificateur_passage()['envoyees']);
    egal(['cours' => 'envoye', 'echeance' => 'prevu'], db()->query('SELECT type, statut FROM envois ORDER BY type')->fetchAll(PDO::FETCH_KEY_PAIR));
    vrai(str_contains((string) file_get_contents(dossier_data() . '/erreurs.log'), 'rafraichir/trello'), 'panne de Trello au journal');
});

test('planificateur : un rappel manuel en base part à son heure ; annulé, il ne part pas', function () {
    base_de_test();
    sans_brief_en_base();
    fixer_heure('2026-10-14 08:00:00');
    copies_fraiches([]);
    $ajout = db()->prepare('INSERT INTO rappels (id_client, texte, quand, cree_le, source, annule) VALUES (?, ?, ?, ?, ?, ?)');
    $ajout->execute([uuid_test(1), 'Appeler Sébastien', '2026-10-14 08:00:00', '2026-10-13 20:00:00', 'app', 0]);
    $ajout->execute([uuid_test(2), 'Annulé', '2026-10-14 08:00:00', '2026-10-13 20:00:00', 'app', 1]);
    abonnement_enregistrer(abonnement_exemple(), null);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    egal(1, planificateur_passage()['envoyees']);
    egal(['Appeler Sébastien'], db()->query('SELECT titre FROM envois')->fetchAll(PDO::FETCH_COLUMN));
});
```

- [ ] **Step 2 : les voir échouer**

Run: `{ php tests/run.php planificateur ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: ÉCHEC, `Call to undefined function planificateur_passage()`.

- [ ] **Step 3 : l'âge maximal en paramètre** dans `serveur/lib/donnees.php`

```php
function donnees_trello(bool $force, int $age = JR_AGE_TRELLO): array
{
    $detecter = config('trello')['ecritures'] === 'reelles' ? 'noter_actions_detectees' : null;
    return rafraichir('trello', $force ? JR_AGE_FORCE : $age, 'trello_lire', $detecter);
}
```

et `donnees_agenda(string $source, string $premier, int $nombre, bool $force = false, int $age = JR_AGE_AGENDA)`, avec `$force ? JR_AGE_FORCE : $age` à la place de `$force ? JR_AGE_FORCE : JR_AGE_AGENDA`.

- [ ] **Step 4 : `serveur/lib/planificateur.php`**

```php
<?php
// Le planificateur (SPEC § 15.4) : un passage par minute, lancé par cron-job.org (route POST cron)
// ou, en secours, par le cron d'OVH (serveur/cron-ovh.php).

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/donnees.php';
require_once __DIR__ . '/envois.php';
require_once __DIR__ . '/rappels.php';
require_once __DIR__ . '/reglages.php';

const JR_CRON_AGE_TRELLO = 900;
const JR_CRON_AGE_AGENDA = 3600;
const JR_CRON_DUREE = 20;

// Les copies Trello et agenda, relues si trop vieilles. Une source en panne laisse sa dernière copie
// (ou rien) : les rappels de l'autre partent quand même.
function copies_pour_rappels(): array
{
    try {
        $cartes = donnees_trello(false, JR_CRON_AGE_TRELLO)['contenu']['cartes'] ?? [];
    } catch (Throwable $e) {
        journal_erreur('cron/trello', $e);
        $cartes = copie_lire('trello')['contenu']['cartes'] ?? [];
    }
    try {
        $jours = donnees_agenda('agenda', aujourdhui_paris()->format('Y-m-d'), JR_JOURS_DONNEES, false, JR_CRON_AGE_AGENDA)['jours'];
    } catch (Throwable $e) {
        journal_erreur('cron/agenda', $e);
        $jours = copie_lire('agenda')['contenu']['jours'] ?? [];
    }
    // Agenda jamais connecté : donnees_agenda() rend un objet vide
    return [$cartes, is_array($jours) ? $jours : []];
}

// Rappels manuels non annulés de la fenêtre de génération
function rappels_a_venir(DateTimeImmutable $maintenant): array
{
    $requete = db()->prepare('SELECT id, texte, quand, cree_le FROM rappels WHERE annule = 0 AND quand > ? AND quand <= ?');
    $requete->execute([utc($maintenant->modify('-' . JR_RAPPELS_APRES . ' seconds')), utc($maintenant->modify('+' . JR_RAPPELS_AVANT . ' seconds'))]);
    return array_map(fn(array $r): array => [
        'id' => (int) $r['id'],
        'texte' => $r['texte'],
        'quand' => depuis_utc($r['quand']),
        'cree_le' => depuis_utc($r['cree_le']),
    ], $requete->fetchAll());
}

// Un passage, sous verrou : un second appel pendant un passage repart aussitôt. Au-delà de $limite
// (microtime, 20 s par défaut), plus rien ne part : le reste partira au passage suivant.
function planificateur_passage(?float $limite = null): array
{
    $limite ??= microtime(true) + JR_CRON_DUREE;
    if ((int) db()->query("SELECT GET_LOCK('jr_cron', 0)")->fetchColumn() !== 1) {
        return ['verrou' => false];
    }
    try {
        [$cartes, $jours] = copies_pour_rappels();
        $maintenant = maintenant();
        envois_enregistrer(generer_rappels($cartes, $jours, reglages_rappels(), rappels_a_venir($maintenant), $maintenant));
        $bilan = envoyer_dus(maintenant(), $limite);
        envois_purger(maintenant());
        reglage_ecrire('cron_dernier_passage', iso_paris(maintenant()));
        return ['verrou' => true] + $bilan;
    } finally {
        db()->query("SELECT RELEASE_LOCK('jr_cron')");
    }
}
```

- [ ] **Step 5 : tests au vert**

Run: `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: tout passe (207 tests), y compris les tests de `donnees` (appels inchangés).

- [ ] **Step 6 : commit**

```bash
git add serveur/lib/planificateur.php serveur/lib/donnees.php tests/unitaires/test_planificateur.php
git commit -m "Lot 2 : passage du planificateur (verrou, copies relues si trop vieilles, rappels, envoi, purge)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: La route `POST /api/cron`, le secours d'OVH et l'appel local

**Files:**
- Create: `serveur/routes/cron.php`
- Create: `serveur/cron-ovh.php`
- Create: `outils/cron-local.sh`
- Modify: `serveur/lib/routeur.php` (routes « machine », `jeton_bearer()`)
- Modify: `site/api/index.php` (en-tête Authorization, travail après la réponse)
- Modify: `site/.htaccess` (Authorization recopié pour PHP)
- Test: `tests/unitaires/test_route_cron.php`, `tests/unitaires/test_htaccess.php`, `tests/integration/test_http_cron.php`

**Interfaces:**
- Consumes: `planificateur_passage()` (tâche 5), `config('cron')['jeton']` (tâche 1).
- Produces:
  - option de route `'machine' => true` : ni origine de l'app ni corps exigés (un corps présent doit être du JSON), la route vérifie elle-même son jeton. **Siri l'utilisera au plan 3** ;
  - `jeton_bearer(array $requete): string` (`''` si absent) ;
  - une réponse peut porter `'apres' => callable` : `site/api/index.php` l'appelle après avoir envoyé la réponse ;
  - `POST /api/cron` : 200 `{"ok":true}` avec le bon jeton, 404 `route_inconnue` sinon.

- [ ] **Step 1 : les tests de la route** `tests/unitaires/test_route_cron.php`

```php
<?php
declare(strict_types=1);

// Comme cron-job.org : POST sans origine, sans corps, sans type de contenu
function appel_cron(?string $jeton): array
{
    return traiter(requete([
        'methode' => 'POST',
        'chemin' => 'cron',
        'entetes' => $jeton === null ? [] : ['authorization' => "Bearer $jeton"],
    ]));
}

test('route cron : sans jeton, mauvais jeton ou jeton non configuré → 404 comme une adresse inconnue, et aucun passage', function () {
    base_de_test();
    foreach ([null, '', 'mauvais', JETON_CRON_TEST . 'x', substr(JETON_CRON_TEST, 0, -1)] as $jeton) {
        $r = appel_cron($jeton);
        egal(404, $r['statut'], var_export($jeton, true));
        egal('route_inconnue', corps($r)['erreur']['code']);
        vrai(!isset($r['apres']), 'aucun passage');
    }
    $GLOBALS['JR_CONFIG']['cron']['jeton'] = '';
    egal(404, appel_cron('')['statut']);
    unset($GLOBALS['JR_CONFIG']['cron']);
    egal(404, appel_cron(JETON_CRON_TEST)['statut']);
});

test('route cron : le bon jeton → 200 tout de suite, le passage se fait après la réponse', function () {
    base_de_test();
    sans_brief_en_base();
    fixer_heure('2026-10-14 08:00:00');
    copie_trello([]);
    $r = appel_cron(JETON_CRON_TEST);
    egal(200, $r['statut']);
    egal(['ok' => true], corps($r));
    egal(null, reglage_lire('cron_dernier_passage'));
    faux_http([]);
    egal(true, ($r['apres'])()['verrou']);
    egal('2026-10-14T10:00:00+02:00', reglage_lire('cron_dernier_passage'));
});

test("routeur : une route machine se passe de l'origine de l'app et lit un corps JSON s'il y en a un ; les autres routes exigent toujours l'origine", function () {
    base_de_test();
    $GLOBALS['JR_ROUTES_SUPPLEMENTAIRES'] = [
        'POST essai-machine' => ['publique' => true, 'machine' => true, 'action' => fn(array $r, ?array $s): array => reponse_json(['corps' => $r['corps'], 'jeton' => jeton_bearer($r)])],
        'POST essai-echo' => ['publique' => true, 'action' => fn(array $r, ?array $s): array => reponse_json(['corps' => $r['corps']])],
    ];
    $machine = fn(string $corps, array $entetes): array => traiter(requete(['methode' => 'POST', 'chemin' => 'essai-machine', 'corps_brut' => $corps, 'entetes' => $entetes]));
    egal(['ok' => true, 'corps' => ['texte' => 'QYRIS appeler'], 'jeton' => 'abc'], corps($machine('{"texte":"QYRIS appeler"}', ['content-type' => 'application/json', 'authorization' => 'Bearer abc'])));
    egal(['ok' => true, 'corps' => [], 'jeton' => ''], corps($machine('', [])));
    egal(415, $machine('texte', [])['statut']);
    egal(403, traiter(requete(['methode' => 'POST', 'chemin' => 'essai-echo', 'corps_brut' => '{}', 'entetes' => ['content-type' => 'application/json']]))['statut']);
});
```

`sans_brief_en_base()` vient de `test_planificateur.php` (tous les fichiers de test sont chargés avant de lancer).

Dans `tests/unitaires/test_htaccess.php` :

```php
test("htaccess : l'en-tête Authorization (jeton du planificateur) est recopié pour PHP, avant la réécriture de l'API", function () {
    $htaccess = lire_htaccess();
    $regle = 'RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]';
    vrai(str_contains($htaccess, 'RewriteCond %{HTTP:Authorization} .'), 'condition attendue');
    vrai(str_contains($htaccess, $regle), 'règle attendue');
    vrai(strpos($htaccess, $regle) < strpos($htaccess, 'RewriteRule ^api/ api/index.php'), "avant la réécriture de l'API");
});
```

Et `tests/integration/test_http_cron.php` (vrai serveur d'aperçu, donc vrai `site/api/index.php`) :

```php
<?php
declare(strict_types=1);

test("HTTP : POST /api/cron avec le jeton en Authorization répond 200 et fait le passage ; sans jeton, 404 et rien", function () {
    base_de_test();
    reglage_ecrire('rappels', ['brief' => ['jours' => []]]);
    copie_trello([]);  // copie neuve : le serveur d'aperçu n'appellera pas Trello
    [$origine, $serveur] = lancer_apercu();
    try {
        egal(404, requete_http('POST', "$origine/api/cron", [], '')['statut']);
        egal(null, reglage_lire('cron_dernier_passage'));
        egal(200, requete_http('POST', "$origine/api/cron", ['Authorization: Bearer ' . JETON_CRON_TEST], '')['statut']);
        vrai(reglage_lire('cron_dernier_passage') !== null, 'passage fait après la réponse');
    } finally {
        arreter_apercu($serveur);
    }
});
```

- [ ] **Step 2 : les voir échouer**

Run: `{ php tests/run.php cron ; php tests/run.php routeur ; php tests/run.php htaccess ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: ÉCHEC (404 avec le bon jeton : route absente ; `jeton_bearer()` inconnue ; règle absente du `.htaccess`).

- [ ] **Step 3 : le routeur** (`serveur/lib/routeur.php`)

Le commentaire de `routes()` gagne une ligne :

```php
// Chaque fichier de serveur/routes/ renvoie ['MÉTHODE chemin' => ['publique' => bool, 'action' => callable]].
// Une route 'machine' (cron-job.org, Siri : pas de navigateur) n'exige ni l'origine de l'app ni de corps,
// et vérifie elle-même son jeton.
```

Dans `traiter()` :

```php
        $requete['corps'] = $requete['methode'] === 'GET' ? [] : lire_corps_json($requete, $route['machine'] ?? false);
```

`lire_corps_json()` :

```php
function lire_corps_json(array $requete, bool $machine = false): array
{
    if ($machine && $requete['corps_brut'] === '') {
        return [];
    }
    if (!$machine && ($requete['entetes']['origin'] ?? null) !== config('origine')) {
        throw new ErreurApi('origine_refusee', 403, 'Requête refusée.');
    }
    // … la suite (type de contenu, taille, JSON) ne change pas
}

// Jeton d'un appel machine : « Authorization: Bearer … », ou '' s'il manque
function jeton_bearer(array $requete): string
{
    return preg_match('/^Bearer\s+(\S+)$/i', (string) ($requete['entetes']['authorization'] ?? ''), $m) ? $m[1] : '';
}
```

Le commentaire au-dessus de `lire_corps_json()` devient : « Écritures : POST en JSON, depuis l'origine de l'app (sauf route machine), corps objet de 64 Ko au plus (vide accepté). »

- [ ] **Step 4 : la route** `serveur/routes/cron.php`

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/planificateur.php';

// Planificateur (SPEC § 15.4) : cron-job.org appelle chaque minute avec son jeton. Mauvais jeton : 404, comme
// une adresse inconnue. La réponse part tout de suite, le passage se fait après (site/api/index.php).
return [
    'POST cron' => ['publique' => true, 'machine' => true, 'action' => function (array $requete, ?array $session): array {
        $attendu = (string) (config('cron')['jeton'] ?? '');
        if ($attendu === '' || !hash_equals($attendu, jeton_bearer($requete))) {
            throw new ErreurApi('route_inconnue', 404, 'Adresse inconnue.');
        }
        return reponse_json([]) + ['apres' => fn(): array => planificateur_passage()];
    }],
];
```

- [ ] **Step 5 : `site/api/index.php`**

```php
$reponse = traiter([
    'methode' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'chemin' => (string) preg_replace('#^/api/#', '', $chemin),
    'requete' => $_GET,
    'corps_brut' => (string) file_get_contents('php://input', false, null, 0, JR_CORPS_MAX + 1),
    'entetes' => [
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
        'content-type' => $_SERVER['CONTENT_TYPE'] ?? '',
        // Apache ne transmet pas toujours Authorization : le .htaccess le recopie (REDIRECT_ après la réécriture)
        'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
    ],
    'cookies' => $_COOKIE,
    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'appareil' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
]);
emettre($reponse);

// Travail après la réponse (le planificateur) : l'appelant n'attend ni Trello, ni Google, ni les envois
if (isset($reponse['apres'])) {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    ignore_user_abort(true);
    try {
        ($reponse['apres'])();
    } catch (Throwable $e) {
        journal_erreur('apres', $e);
    }
}
```

- [ ] **Step 6 : `site/.htaccess`**, juste avant « # API : point d'entrée unique » :

```apache
# Jeton du planificateur et de Siri : Apache ne transmet pas toujours Authorization à PHP
RewriteCond %{HTTP:Authorization} .
RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

```

- [ ] **Step 7 : le secours d'OVH** `serveur/cron-ovh.php` (hors du web, comme tout `serveur/`)

```php
<?php
// Secours du planificateur : le cron d'OVH (une fois par heure au plus) lance ce script en ligne de commande.
// À régler par Colin dans l'espace client au plan 3, si l'offre le permet : jr.qyris.fr/serveur/cron-ovh.php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/lib/planificateur.php';

set_error_handler('erreur_php');
try {
    assurer_migrations();
    planificateur_passage();
} catch (Throwable $e) {
    journal_erreur('cron-ovh', $e);
    exit(1);
}
```

- [ ] **Step 8 : l'appel local** `outils/cron-local.sh` (puis `chmod +x outils/cron-local.sh`)

```bash
#!/bin/bash
# Appelle le planificateur de l'aperçu local (http://localhost:8787), comme cron-job.org le fera en ligne.
#   outils/cron-local.sh            un passage
#   outils/cron-local.sh --boucle   un passage par minute, jusqu'à Ctrl+C
# Le jeton est lu dans serveur/config.php et passe par un fichier d'en-têtes : jamais affiché ni en argument.
# Claude le lance à travers le filtre : { outils/cron-local.sh ; } 2>&1 | ~/.claude/hooks/redact-secrets

set -euo pipefail
ICI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENTETES="$(mktemp)"
trap 'rm -f "$ENTETES"' EXIT
php -r '$c = require $argv[1]; echo "Authorization: Bearer ", $c["cron"]["jeton"] ?? "", "\n";' "$ICI/../serveur/config.php" > "$ENTETES"

passage() {
    curl -sS --max-time 60 -o /dev/null -w "%{http_code}  $(date +%H:%M:%S)\n" -X POST -H @"$ENTETES" http://localhost:8787/api/cron
}

passage
while [ "${1:-}" = "--boucle" ]; do
    sleep 60
    passage
done
```

- [ ] **Step 9 : tests au vert, et les deux scripts relus par PHP et bash**

Run: `{ php tests/run.php && php -l serveur/cron-ovh.php && bash -n outils/cron-local.sh ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected: tout passe (212 tests), « No syntax errors detected », rien pour `bash -n`.

- [ ] **Step 10 : commit**

```bash
git add serveur/routes/cron.php serveur/cron-ovh.php outils/cron-local.sh serveur/lib/routeur.php site/api/index.php site/.htaccess tests/unitaires/test_route_cron.php tests/unitaires/test_htaccess.php tests/integration/test_http_cron.php
git commit -m "Lot 2 : route POST /api/cron (jeton, réponse avant le passage), secours du cron d'OVH, appel local" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Essai réel dans Chrome, documents à jour, fusion

Pas de nouveau code. Colin participe au step 3.

- [ ] **Step 1 : toute la suite**

```bash
{ php tests/run.php && node --test 'tests/js/*.test.mjs' && node tests/e2e/lancer.mjs ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

Expected : 212 tests PHP, 32 de modules et 53 d'écran au vert (aucun écran n'a changé : les tests d'écran vérifient qu'on n'a rien cassé).

- [ ] **Step 2 : préparer l'aperçu local**

- Vérifier qu'aucun aperçu ne tourne déjà (`lsof -nP -iTCP:8787 -sTCP:LISTEN`), sinon le lancer en tâche de fond : `php -S localhost:8787 -t site outils/apercu.php` (base de dev, `serveur/config.php` regénéré à la tâche 1).
- Vérifier, sans rien afficher d'autre que des nombres, que la base de dev a l'abonnement de Chrome de l'essai du plan 1 :
  `{ php -r 'require "serveur/lib/base.php"; assurer_migrations(); echo db()->query("SELECT COUNT(*) FROM abonnements")->fetchColumn(), "\n";' ; } 2>&1 | ~/.claude/hooks/redact-secrets`
  S'il vaut 0 : Colin ouvre http://localhost:8787 dans Chrome, Moi, Réglages, « Activer les notifications ».

- [ ] **Step 3 : le vrai brief, dans 2 minutes** (avec Colin, Chrome ouvert sur le Mac)

```bash
{ php -r 'require "serveur/lib/rappels.php"; $h = (new DateTimeImmutable("+2 minutes", paris()))->format("H:i"); reglage_ecrire("rappels", ["brief" => ["heure" => $h, "jours" => [1,2,3,4,5,6,7]]]); echo "Brief réglé à $h\n";' && outils/cron-local.sh ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

Puis, en tâche de fond, trois passages à une minute d'intervalle : `{ for i in 1 2 3; do sleep 60; outils/cron-local.sh; done ; } 2>&1 | ~/.claude/hooks/redact-secrets`. Attendu : `200` à chaque passage, et **Colin reçoit dans Chrome son vrai brief** (titre « Lundi 12 octobre · … », premier cours, tâches, médian MT23, réplique), une seule fois. Vérifier ensuite la table : `SELECT cle, type, statut, essais FROM envois` (aucun texte affiché), puis remettre le brief à 7 h 30 : `DELETE FROM reglages WHERE cle = 'rappels'`.

- [ ] **Step 4 : le secours d'OVH tourne aussi**

`{ php serveur/cron-ovh.php && php -r 'require "serveur/lib/reglages.php"; echo reglage_lire("cron_dernier_passage"), "\n";' ; } 2>&1 | ~/.claude/hooks/redact-secrets` → l'heure du passage, à la minute près. Rien de nouveau dans `serveur/data/erreurs.log` (le lire à travers le filtre).

- [ ] **Step 5 : documents**

- `SPEC.md` § 15 : statut « plan 2 fait le 2026-10-1x » ; § 15.4, ajouter les écarts de la section « Écarts au SPEC » de ce plan ; § 15.2, migration `004_rappels.sql` (au lieu de `003_rappels.sql`, prise par les abonnements).
- `CONTENU.md` § 5 : les textes nouveaux validés par Colin (tableau en haut de ce plan).
- `README.md` du projet, « Prochaine session » : plan 3 du lot 2 (rappels manuels, Siri, Réglages, mise en ligne, cron-job.org) ; à vérifier en ligne au plan 3 : qu'Apache d'OVH transmet bien `Authorization` (bon jeton → 200, mauvais → 404), que `fastcgi_finish_request` rend la main tout de suite, et que le cron d'OVH accepte un script PHP. Reprendre les petits défauts du plan 1 toujours ouverts.
- Ce plan : cases cochées, statut en tête.

- [ ] **Step 6 : revue de toute la branche, puis fusion**

Revue de la branche `lot2-2-planificateur` contre ce plan et le SPEC § 15 (selon le mode d'exécution choisi), corrections, suite complète au vert, puis :

```bash
git checkout main && git merge --no-ff lot2-2-planificateur -m "Lot 2, plan 2 : le planificateur" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Pas d'envoi sur GitHub ni de mise en ligne : la mise en ligne du lot 2 est au plan 3.

---

## Hors de ce plan

- **Plan 3** : `POST /api/rappels` et `rappels/annuler` (capture « Rappel »), `rappels_du_jour` dans `GET donnees` et le bloc d'Aujourd'hui, Siri (route machine, jeton en base), écran Réglages (heures et interrupteurs des rappels, écrits avec `reglages_rappels_valides()`, état du planificateur avec `cron_dernier_passage`), mise en ligne, tâche cron-job.org, cron d'OVH si l'offre le permet, recette du lot 2.
- Mails non lus dans le brief : lot 3.
- Un médian en « journée entière » n'a pas de rappel (seuls les médians avec une heure en ont).
