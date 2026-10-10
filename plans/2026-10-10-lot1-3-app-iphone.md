# Jarvis Jr., lot 1, plan 3 sur 3 : l'app iPhone

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** L'app installable sur l'iPhone de Colin (Aujourd'hui avec le tirage, Agenda, Tâches et carte ouverte, capture, Moi), qui lit et écrit la to-do par le serveur des plans 1 et 2, s'ouvre et se lit sans réseau, puis sa mise en ligne par Colin et la recette du lot 1 (SPEC § 12.5).

**Architecture:** Une page unique (`site/index.html`) et des modules ES natifs sans build. Les calculs sont des fonctions pures dans `site/js/lib/` (heure de Paris, cartes, conduite, phrase du jour, tirage) et `site/js/etat.js` (écritures en attente appliquées par-dessus la copie du serveur), testées avec `node --test`. `site/js/app.js` tient l'état, la navigation par `#/…`, le chargement et la file des écritures ; chaque écran de `site/js/ecrans/` construit son DOM avec `h()` (jamais `innerHTML`). La copie locale vit dans IndexedDB, un service worker garde l'app. Les écrans sont testés avec Playwright sur un petit serveur Node qui sert `site/` avec une fausse API.

**Tech Stack:** HTML, CSS et JS natifs (modules ES, IndexedDB, service worker, `Intl`), PHP 8.4 et MySQL 8.4 pour les deux reprises serveur, Node 26 (`node --test`, déjà installé), `playwright-core` 1.63.0 avec le Chromium déjà en cache sur le Mac.

**Spec:** `projects/jarvis-jr/SPEC.md` (validée le 2026-10-08), sections 3, 4.5, 5.6, 8, 10.2, 12.2 à 12.5 et 13. Textes : `CONTENU.md`. Dessin : maquette v28 (https://claude.ai/artifact/ACPbctYmzhyqJrQk4ou4Nj) et contrat `.impeccable/surfaces/site-index-html.md`. L'exécutant lit le spec, le contrat et ce plan.

**Le lot 1 en 3 plans** :
1. Le socle serveur : fait et en ligne le 2026-10-09 (`plans/2026-10-08-lot1-1-socle-serveur.md`).
2. Les données : fait le 2026-10-10, pas encore en ligne (`plans/2026-10-09-lot1-2-donnees.md`).
3. **Ce plan : l'app iPhone, la mise en ligne et la recette du lot 1.**

Tous les chemins sont relatifs à `projects/jarvis-jr/` (dépôt git du projet), sauf mention contraire. Le workspace est `~/Desktop/jarvis-starter-kit/`. Avant de commencer : MySQL local lancé (`{ brew services run mysql@8.4 ; } 2>&1 | ~/.claude/hooks/redact-secrets`) et suite PHP au vert (148 tests). Travailler sur une branche `lot1-3-app` créée depuis `main`.

## Global Constraints

- **Aucune dépendance dans `site/`** : pas de framework, pas de build, pas de CDN. Modules ES natifs chargés par `<script type="module">`. Polices hébergées dans `site/fonts/`.
- **CSP stricte** (`.htaccess`, SPEC § 5.6) : aucun `<script>` ni `<style>` en ligne, **aucun attribut `style=`**, aucun gestionnaire `onclick=` dans le HTML. Une valeur de style variable passe par une propriété CSS posée en JS : `el.style.setProperty('--angle', '-6deg')` (l'option `vars` de `h()`).
- **Jamais `innerHTML`, `outerHTML` ni `insertAdjacentHTML` avec des données** : titres de cartes, descriptions et événements viennent de Trello et de Google. Le DOM se construit avec `h()` (`site/js/lib/dom.js`), le texte passe par `textContent`. Un lien tiré d'une description n'est créé que s'il commence par `http://` ou `https://`, avec `rel="noopener noreferrer"`.
- **Heure de Paris partout** : tout calcul de jour ou d'heure passe par `site/js/lib/temps.js` (`Intl`, fuseau `Europe/Paris`), jamais par `getHours()` ni `toLocaleString()` sans fuseau. L'iPhone peut être réglé sur un autre fuseau : les tests d'écran tournent en `America/New_York` exprès.
- Le serveur envoie des heures ISO avec décalage (`2026-10-21T19:15:00+02:00`) et des jours `AAAA-MM-JJ`. Les journées entières ont `debut` et `fin` en `AAAA-MM-JJ`, `fin` exclue.
- **Direction artistique** : graphite `#121417`, panneaux `#1E2228`, ambre `#FFB020` réservé à l'afficheur et à **l'unique élément allumé** de l'écran, sans halo ; scotch `#EDE6D6` au marqueur pour les étiquettes de tranche ; rouge `#E5484D` (texte `#FF6B70`) pour les alertes seulement ; QYRIS `#3DD68C`, Asso `#A97BFF`, Études `#6FB0FF`, Perso `#FF7AC6`. Polices : Archivo (texte, GO), Martian Mono (afficheur, chiffres, étiquettes), Permanent Marker (scotch seulement). Pas de dégradé, pas d'anneau de progression, pas de carte à bord gauche coloré, pas de vert sur noir.
- **Accessibilité** (SPEC § 8.5) : cibles de 44 px au moins, contraste AA, `prefers-reduced-motion` respecté (tirage et tampons instantanés), libellés pour VoiceOver (le + s'appelle « Noter quelque chose »), tailles de texte en `calc(N * var(--px))` pour suivre la taille de texte de l'iPhone.
- **Textes** : ceux de `CONTENU.md`, puis ceux listés plus bas sous « Textes nouveaux », tous réunis dans `site/js/lib/textes.js`. Junior tutoie, phrases courtes, aucune pique sur les médians, la sécurité ou les erreurs. Pas de point d'exclamation en série, pas d'émoji, **pas de tiret long**.
- Noms, commentaires et messages en français, commentaires rares (style du projet). Indentation de 2 espaces en JS et CSS, de 4 en PHP. PHP : `declare(strict_types=1);`, rien de propre à PHP 8.5 (`|>`, `array_first`, `array_last`, `#[\NoDiscard]`, `clone with`).
- **Écritures Trello simulées en dev** (config de dev) : rien ne part vers la vraie to-do pendant le développement. Les tests d'écran n'utilisent jamais le serveur PHP : leur API est fausse.
- **Service worker (à partir de la tâche 11)** : toute modification d'un fichier de `site/` gardé en cache change l'empreinte attendue. `php tests/run.php` donne alors la nouvelle valeur de `const VERSION` dans `site/sw.js`, à recopier avant de commiter.
- **Secrets** :
  - toute commande Bash qui touche aux secrets (`secrets/`, `.env`, `config.php`, MySQL, aperçu avec la config de dev, déploiement) s'écrit en entier dans `{ commande ; } 2>&1 | ~/.claude/hooks/redact-secrets` ;
  - les tests PHP aussi : `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets` ;
  - ne jamais lire un fichier de `secrets/` avec Read ou Grep ;
  - si un commit est bloqué par le garde-fou, le relancer dans le même groupe filtré.
- **Envois vers OVH** : Claude ne les fait jamais (refusés par le garde-fou d'autorisations). Colin lance `outils/deploy-ovh.sh` à la tâche 14, dans le terminal de VS Code.
- Commits dans le dépôt `projects/jarvis-jr`, un par tâche, message terminé par la ligne `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Commandes de test

- PHP : `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets` (attendu : `… réussi(s), 0 échec(s)`).
- Modules purs : `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node --test 'tests/js/*.test.mjs'` (attendu : `fail 0`).
- Écrans : `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs [morceau du nom d'un test]` (attendu : `… réussi(s), 0 échec(s)`). Installé à la tâche 3.

## Review Focus

1. **Une écriture faite hors ligne** (étape cochée, carte ajoutée) puis app fermée, rouverte, et réponse perdue au premier renvoi : elle reste visible « en attente », ne disparaît jamais et ne part qu'une fois (même `id_client` à chaque renvoi, et le serveur ne crée pas de seconde carte). Tests : tâche 2, « renvoi d'une capture dont la réponse s'est perdue » ; tâche 6, « hors ligne : cocher, recharger, retour du réseau » et « réponse perdue » ; tâche 8, « capture hors ligne ».
2. **Un titre de carte, une description ou un événement piégé** (`<img src=x onerror=…>`, `javascript:alert(1)`, texte de 3 000 caractères) : affiché comme du texte, rien ne s'exécute, aucun lien autre que `http(s)` n'est créé, rien ne déborde. Test : tâche 6, « description piégée ».
3. **L'iPhone dans un autre fuseau, et le passage à l'heure d'hiver du 25/10/2026** : les heures et les jours restent ceux de Paris (un TD du lundi 26/10 à 8 h affiché 08:00, une échéance « 2026-10-27 » saisie hors ligne gardée à 18 h, semaine du 26/10 au 1er novembre). Tests : tâche 4 (`isoParis`, `semaineIso`, `heureParis`) ; tous les tests d'écran tournent en `America/New_York` ; tâche 9, « semaine du 26 octobre ».
4. **Session expirée en plein usage** (401 sur une écriture ou un chargement) : l'écriture reste en file, l'écran de connexion dit « Ta session a expiré », et l'écriture part après la connexion. Test : tâche 6, « session expirée pendant une écriture ».
5. **Données absentes ou partielles** (première ouverture sans réseau, Google jamais connecté, Trello en panne, to-do vide) : un message clair, jamais un écran blanc ni une erreur dans la console. Tests : tâche 5, « sans réseau ni copie », « Trello ne répond pas », « Google déconnecté » ; tâche 7, « agenda pas connecté » et « to-do vide ».

## Écarts au spec et textes nouveaux (à valider par Colin)

Écarts :
- **Message de Google déconnecté** (décision de Colin du 2026-10-10) : « Google m'a déconnecté de ton agenda. Reconnecte-le, ça prend 30 secondes. », avec un bouton « Reconnecter » dans le bandeau. `CONTENU.md` § 11 et le serveur sont alignés à la tâche 1.
- **Modules purs testés avec `node --test`** (Node 26 déjà installé, aucun paquet) plutôt que dans une page Playwright : c'est plus simple et n'ajoute aucun outil. Playwright reste pour les écrans.
- **Tests d'écran sur un petit serveur Node** (`tests/e2e/serveur.mjs` : les fichiers de `site/` avec les en-têtes lus dans le `.htaccess`, et une fausse API) plutôt que sur l'aperçu PHP : Trello et Google sont simulés sans MySQL ni secret. `playwright-core` est épinglé en 1.63.0, la version dont le Chromium est déjà en cache.
- **Carte ouverte** : un écran `#/carte/{id}` (la maquette dépliait la carte dans la liste) ; même dessin.
- **Tirage** : 3 relances avec leurs répliques, puis une 4e pression où Junior choisit (« J'ai choisi. On y va ? ») et où seul « C'est parti » reste, comme dans la maquette jouable.
- **Phrase du jour** : tirée avec la date pour graine, parmi les phrases qui s'appliquent au moment de l'affichage. Elle peut donc changer dans la journée si la situation change (plus rien en retard, par exemple), plutôt que de rester fausse.
- **Service worker** : tout en cache d'abord, nouvelle version installée dès qu'elle est vue et utilisée à l'ouverture suivante. Sa `VERSION` est l'empreinte des fichiers en cache, vérifiée par un test PHP : on ne peut pas mettre en ligne des fichiers changés sous l'ancienne version.
- **Police Permanent Marker** : sous licence Apache 2.0, pas OFL (Archivo et Martian Mono sont OFL). Licences dans `site/fonts/licences/`.
- **Lot 1** : la feuille « Noter » n'a pas le choix Tâche ou Rappel (lot 2), et Moi n'a ni grade, ni album, ni lien Réglages (lot 4) ; la session et Google y sont directement.
- **Reprises de la revue du plan 2** faites ici : jeton Google illisible vu comme absent, une source en panne imprévue ne fait pas tomber l'autre, carte sortie de Fait avec son échéance rouverte, étape du tirage vérifiée, semaines d'agenda purgées, capture sans seconde carte au renvoi. Reportées : agenda UTC introuvable sans signal (avant P27), événement avec heure sur plusieurs jours, `set_error_handler` dans les tests, détection hors transaction.
- Version de l'app : 0.2.0.

Textes nouveaux :
- Bandeaux d'état : « Google ne répond pas. Ton agenda s'affiche tel qu'il y a 5 min. » ; « Pas encore de données. Il me faut du réseau une première fois. » ; « 2 notes attendent le réseau. Elles partiront toutes seules. » (pluriel de CONTENU § 11) ; « Tire pour actualiser », « Relâche pour actualiser », « Actualisation… ».
- Connexion : « Pas de réseau. Réessaie quand ça revient. »
- Aujourd'hui : « Entracte », « 45 min libres », « Rien pour l'instant », « 2 cours », « Plus de cours aujourd'hui », « fin dans », « Agenda pas encore connecté. Ça se fait dans Moi. », « médian J-7 », « Junior · stagiaire ».
- Tirage : « Je t'ai trouvé 35 min avant ton TD. » (réplique de la maquette), « Libre jusqu'à 23 h. », lignes d'afficheur « 35 MIN AVANT TN01 », « LIBRE JUSQU'À 23 H », « IL EST TARD », bouton « Retour ».
- Tâches et carte : « Aucune carte ici. », « Masquer les cartes faites », « en attente du réseau », « Déplacer vers », « Retour ».
- Capture : « Choisis un domaine. », « Heure (facultatif) », « Effacer l'échéance ».
- Agenda : « Jour précédent », « Jour suivant », « Chargement… », « Hors ligne : cette semaine n'est pas encore chargée. », « Toute la journée », « PERSO ».
- Moi : « Google Agenda », « Agenda connecté », « Agenda pas connecté », « Agenda connecté. » (message au retour de Google), « La connexion à Google n'a pas abouti. Réessaie. », « Session », « Déconnecter tous les appareils ? », « 1 note attend encore le réseau. Te déconnecter quand même ? », « L'app », « Version 0.2.0 », « Confidentialité ».
- Page `confidentialite.html` (exigée par Google), rédigée à la tâche 10.

## Fichiers de ce plan

| Fichier | Rôle |
|---|---|
| `serveur/lib/http.php`, `amorce.php`, `google.php`, `donnees.php`, `taches.php`, `rafraichir.php` (modifiés) | Reprises du plan 2, message de Google, version 0.2.0, capture sans seconde carte |
| `serveur/migrations/002_tentatives.sql` | Captures commencées, pour retrouver une carte créée dont la réponse s'est perdue |
| `site/index.html` | La page unique : zones statut, écran, onglets, feuille, messages |
| `site/manifest.webmanifest` | Nom, icônes, couleurs, `display: standalone` |
| `site/sw.js` | Service worker : l'app sans réseau, jamais l'API |
| `site/confidentialite.html` | Règles de confidentialité, page publique exigée par Google |
| `site/css/app.css` | Tout le style : jetons, console, écrans |
| `site/fonts/*.woff2`, `site/fonts/licences/` | Archivo, Martian Mono, Permanent Marker (latin) et leurs licences |
| `site/js/lib/dom.js` | `h()`, `icone()`, `texteAvecLiens()`, `angle()` |
| `site/js/lib/textes.js` | Tous les textes de l'app |
| `site/js/lib/temps.js` | Heure et jours de Paris, textes de dates et de durées (pur) |
| `site/js/lib/cartes.js` | Comptes, groupes et libellés des cartes (pur) |
| `site/js/lib/conduite.js` | Écran de conduite : en scène, standby, médian (pur) |
| `site/js/lib/phrases.js` | Salutation, phrase du jour, aléa à graine (pur) |
| `site/js/lib/tirage.js` | Temps libre, candidats, score, tirage, textes (pur) |
| `site/js/etat.js` | Écritures en attente appliquées à la copie, décisions de la file (pur) |
| `site/js/api.js` | `appeler()`, `ErreurApi`, `ErreurReseau` |
| `site/js/stockage.js` | IndexedDB (`donnees`, `agenda`, `file`), repli en mémoire |
| `site/js/file.js` | La file des écritures : garder, envoyer dans l'ordre, renvoyer |
| `site/js/app.js` | État, navigation, chargement, écritures, messages, service worker |
| `site/js/ecrans/commun.js` | Statut, messages, règle, voyant, tampon, tirer pour actualiser |
| `site/js/ecrans/connexion.js`, `aujourdhui.js`, `taches.js`, `carte.js`, `capture.js`, `agenda.js`, `moi.js` | Les écrans |
| `tests/js/*.test.mjs` | Tests des modules purs (`node --test`) |
| `tests/e2e/package.json`, `cadre.mjs`, `serveur.mjs`, `fixtures.mjs`, `lancer.mjs`, `test_*.mjs` | Tests d'écran (Playwright) |
| `tests/unitaires/test_sw.php` | Fichiers en cache et `VERSION` du service worker |
| `outils/telecharger-polices.py` | Télécharge les 3 polices (sous-ensemble latin) et leurs licences |
| `outils/deploy-ovh.sh` (modifié) | Lance aussi les tests des modules purs avant l'envoi |
| `DESIGN.md` | Le système visuel de l'app, écrit à la revue de finition |

---
### Task 1 : reprises serveur (revue du plan 2), message de Google, version 0.2.0

Cinq constats de la revue du plan 2 que l'app rend visibles, plus le texte décidé par Colin pour Google déconnecté.

**Files:**
- Modify: `serveur/lib/google.php` (`jeton_lire`), `serveur/lib/donnees.php` (`donnees_reponse`), `serveur/lib/taches.php` (`tache_deplacer`, `tache_tirage_parti`), `serveur/lib/rafraichir.php` (`copie_ecrire`), `serveur/lib/http.php` (message), `serveur/lib/amorce.php` (version), `CONTENU.md` (§ 11)
- Test: `tests/unitaires/test_donnees.php`, `tests/unitaires/test_taches_colonnes.php`, `tests/unitaires/test_taches.php`, `tests/unitaires/test_rafraichir.php`

**Interfaces:**
- Consumes : `jeton_lire()`, `donnees_trello()`, `donnees_agenda()`, `resultat_copie()`, `copie_lire()`, `journal_erreur()`, `trello_carte_autorisee()`, `deplacer_dans_la_copie()`.
- Produces : `GET donnees` ne répond plus 500 pour un jeton Google illisible ni pour une panne imprévue d'une source ; `POST taches/deplacer` hors de Fait envoie `dueComplete: false` si la carte a une échéance ; `POST tirage/parti` refuse une étape d'une autre carte (404 `element_introuvable`) ; `JR_VERSION = '0.2.0'`.

- [ ] **Step 1: Write the failing tests**

À la fin de `tests/unitaires/test_donnees.php` :

```php
test("données : un jeton Google illisible (clé changée) est vu comme absent : 200, agenda en google_deconnecte, une ligne de journal", function () {
    base_de_test();
    fixer_heure('2026-10-10 10:00:00');
    google_connecte_pour_test();
    $GLOBALS['JR_CONFIG'] = config_de_test(['cle_chiffrement' => base64_encode(str_repeat("\x02", 32))]);
    egal(null, jeton_lire('google'));
    egal(false, google_connecte());
    faux_http([BATCH => ['json' => batch_trello()]]);
    $r = appeler('GET', 'donnees');
    egal(200, $r['statut']);
    egal('google_deconnecte', corps($r)['agenda']['erreur']);
    vrai(str_contains((string) file_get_contents(dossier_data() . '/erreurs.log'), 'Déchiffrement impossible'), 'ligne de journal attendue');
});

test("données : une panne imprévue de Trello ne fait pas tomber la réponse : dernière copie, trello_indisponible, agenda intact", function () {
    base_de_test();
    fixer_heure('2026-10-10 10:00:00');
    faux_http([BATCH => ['json' => batch_trello(['perso' => [carte_trello('perso', 'À faire', 1)]])]]);
    appeler('GET', 'donnees');
    fixer_heure('2026-10-10 10:05:00');
    faux_http([BATCH => new RuntimeException('panne imprévue')]);
    $r = appeler('GET', 'donnees');
    egal(200, $r['statut']);
    $d = corps($r);
    egal(['trello_indisponible', '2026-10-10T12:00:00+02:00'], [$d['trello']['erreur'], $d['trello']['recupere_le']]);
    egal([id_test('carte', 1)], array_column($d['trello']['cartes'], 'id'));
    egal(['recupere_le' => null, 'erreur' => null, 'google_connecte' => false, 'jours' => []], $d['agenda']);
});
```

À la fin de `tests/unitaires/test_taches_colonnes.php` :

```php
test("déplacer : une carte sortie de Fait avec une échéance la rouvre (dueComplete false), pour qu'elle puisse être en retard", function () {
    base_de_test();
    copie_trello([carte_normalisee(['id' => id_test('carte', 3), 'colonne' => 'fait', 'echeance' => '2026-10-06T08:30:00+02:00', 'echeance_faite' => true])]);
    faux_http([PUT_CARTE => ['json' => []]]);
    egal(200, appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 3), 'colonne' => 'a_faire'])['statut']);
    egal(['idList' => fabrique_trello_ids()['tableaux']['etudes']['colonnes']['À faire'], 'pos' => 'top', 'dueComplete' => false], json_decode((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], true));
    $carte = carte_de_la_copie(id_test('carte', 3));
    egal(['a_faire', false], [$carte['colonne'], $carte['echeance_faite']]);
});
```

À la fin de `tests/unitaires/test_taches.php` :

```php
test("tirage : une étape qui n'appartient pas à la carte donne 404 element_introuvable, sans action", function () {
    base_de_test();
    copie_trello([carte_a_cocher()]);
    faux_http([]);
    $r = appeler('POST', 'tirage/parti', ['id_client' => uuid_test(1), 'carte' => id_test('carte', 1), 'element' => id_test('element', 99)]);
    egal([404, 'element_introuvable'], [$r['statut'], corps($r)['erreur']['code']]);
    egal(0, nombre_actions());
});
```

À la fin de `tests/unitaires/test_rafraichir.php` :

```php
test("copies : écrire une semaine d'agenda efface les semaines lues il y a plus de 30 jours, jamais trello ni agenda", function () {
    $pdo = base_de_test();
    copie_ecrire('trello', ['cartes' => []], utc_test('2026-08-01 10:00:00'));
    copie_ecrire('agenda', ['jours' => []], utc_test('2026-08-01 10:00:00'));
    copie_ecrire('agenda:2026-W31', ['jours' => []], utc_test('2026-08-01 10:00:00'));
    copie_ecrire('agenda:2026-W40', ['jours' => []], utc_test('2026-10-01 10:00:00'));
    copie_ecrire('agenda:2026-W42', ['jours' => []], utc_test('2026-10-10 10:00:00'));
    egal(['agenda', 'agenda:2026-W40', 'agenda:2026-W42', 'trello'], $pdo->query('SELECT source FROM copies ORDER BY source')->fetchAll(PDO::FETCH_COLUMN));
});
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : 5 échecs (jeton illisible : `Déchiffrement impossible` lève ; panne imprévue : 500 ; déplacer : pas de `dueComplete` ; tirage : 200 ; copies : `agenda:2026-W31` encore là).

- [ ] **Step 3: Implement**

`serveur/lib/google.php`, dans `jeton_lire()`, remplacer la dernière ligne `return $chiffre === false ? null : dechiffrer($chiffre);` par :

```php
    if ($chiffre === false) {
        return null;
    }
    try {
        return dechiffrer($chiffre);
    } catch (RuntimeException $e) {
        // Clé changée ou contenu abîmé : le jeton ne sert plus, la connexion à Google est à refaire
        journal_erreur('jetons', $e);
        return null;
    }
```

`serveur/lib/donnees.php`, remplacer `donnees_reponse()` par :

```php
// Une source en panne imprévue (autre qu'une erreur de source, déjà rendue avec sa copie) ne fait pas
// tomber l'autre : sa dernière copie part avec un code d'erreur, et la panne va au journal.
function donnees_reponse(bool $force): array
{
    try {
        $trello = donnees_trello($force);
    } catch (RuntimeException $e) {
        journal_erreur('donnees/trello', $e);
        $trello = resultat_copie(copie_lire('trello'), 'trello_indisponible');
    }
    try {
        $agenda = donnees_agenda('agenda', aujourdhui_paris()->format('Y-m-d'), JR_JOURS_DONNEES, $force);
    } catch (RuntimeException $e) {
        journal_erreur('donnees/agenda', $e);
        $copie = copie_lire('agenda');
        $agenda = [
            'recupere_le' => iso_ou_null($copie['recupere_le'] ?? null),
            'erreur' => 'google_indisponible',
            'google_connecte' => $copie !== null,
            'jours' => $copie['contenu']['jours'] ?? new stdClass(),
        ];
    }
    return [
        'maintenant' => iso_paris(maintenant()),
        'trello' => [
            'recupere_le' => iso_ou_null($trello['recupere_le']),
            'erreur' => $trello['erreur'],
            'cartes' => cartes_a_montrer($trello['contenu']['cartes'] ?? []),
        ],
        'agenda' => $agenda,
    ];
}
```

(`RuntimeException` et non `Throwable` : une erreur de programmation doit rester une 500, et un appel HTTP imprévu dans les tests lève `EchecTest`, qui n'en est pas une.)

`serveur/lib/taches.php`, dans `tache_tirage_parti()`, juste après `$carte = trello_carte_autorisee($carte_id);` :

```php
    $etapes = array_merge(...array_map(fn(array $l): array => array_column($l['elements'], 'id'), $carte['checklists']));
    if ($element_id !== null && !in_array($element_id, $etapes, true)) {
        throw new ErreurApi('element_introuvable', 404, JR_MESSAGES_INTROUVABLE['element_introuvable']);
    }
```

Dans `tache_deplacer()`, remplacer le bloc `sous_verrou_trello(function () use ($carte, $colonne): void { … });` par :

```php
    sous_verrou_trello(function () use ($carte, $colonne): void {
        $corps = ['idList' => trello_id_colonne($carte['domaine'], $colonne), 'pos' => 'top'];
        $echeance_faite = $carte['echeance_faite'];
        // Une carte rouverte redevient à faire : son échéance n'est plus marquée faite (elle peut être en retard)
        if ($carte['colonne'] === 'fait' && $carte['echeance'] !== null) {
            $corps['dueComplete'] = false;
            $echeance_faite = false;
        }
        trello_ecrire_carte($carte['id'], "/cards/{$carte['id']}", $corps);
        deplacer_dans_la_copie($carte['id'], $colonne, $echeance_faite);
        if ($carte['colonne'] === 'fait') {
            action_noter('carte_rouverte', $carte['domaine'], $carte['id'], null, maintenant(), 'app', 'exacte');
        }
    });
```

`serveur/lib/rafraichir.php` : sous `const JR_VERROU_SOURCE_ATTENTE = 10;`, ajouter `const JR_SEMAINES_JOURS = 30;`, puis à la fin de `copie_ecrire()` :

```php
    // Les semaines d'agenda chargées à la demande ne servent plus au-delà de 30 jours
    if (str_starts_with($source, 'agenda:')) {
        db()->prepare("DELETE FROM copies WHERE source LIKE 'agenda:%' AND source <> ? AND recupere_le < ? LIMIT 20")
            ->execute([$source, utc($recupere_le->modify('-' . JR_SEMAINES_JOURS . ' days'))]);
    }
```

`serveur/lib/http.php`, dans `JR_MESSAGES_SOURCES` :

```php
    'google_deconnecte' => "Google m'a déconnecté de ton agenda. Reconnecte-le, ça prend 30 secondes.",
```

`serveur/lib/amorce.php` : `const JR_VERSION = '0.2.0';`

`CONTENU.md` § 11, remplacer la ligne « Google déconnecté » par :

```markdown
| Google déconnecté | Google m'a déconnecté de ton agenda. Reconnecte-le, ça prend 30 secondes. (avec un bouton « Reconnecter » dans le bandeau, décision de Colin du 2026-10-10) |
```

- [ ] **Step 4: Run the tests**

Run: `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `153 réussi(s), 0 échec(s)`.

- [ ] **Step 5: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git checkout -b lot1-3-app && git add serveur/ tests/ CONTENU.md plans/2026-10-10-lot1-3-app-iphone.md && git commit -m "Lot 1 : reprises de la revue du plan 2 (jeton illisible, sources isolées, carte rouverte, étape du tirage, semaines purgées), message de Google, version 0.2.0

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---

### Task 2 : capture sans seconde carte au renvoi

Constat reporté de la revue du plan 2 : si Trello crée la carte mais que sa réponse dépasse 8 s, l'iPhone reçoit une 502 et le renvoi (même `id_client`) refait un `POST /cards`. Le serveur note donc la tentative avant l'envoi ; au renvoi, il cherche d'abord la carte du même titre créée depuis la tentative.

**Files:**
- Create: `serveur/migrations/002_tentatives.sql`
- Modify: `serveur/lib/taches.php`, `serveur/routes/taches.php`
- Test: `tests/unitaires/test_taches_ajouter.php`, `tests/unitaires/test_base.php`, `tests/unitaires/test_routeur.php`

**Interfaces:**
- Consumes : `trello_appel()`, `trello_id_colonne()`, `trello_normaliser_carte()`, `JR_REGEX_ID_TRELLO`, `ecriture_unique()` (qui tient déjà un verrou par `id_client`).
- Produces : table `tentatives` (`id_client`, `debut`) ; `tache_ajouter($domaine, $titre, $urgent, $echeance, ?string $id_client = null)` ; `tentative_debut()`, `tentative_noter()`, `tentative_oublier()`, `trello_carte_deja_creee()`. Version de la base : 2.

- [ ] **Step 1: Write the failing tests**

En tête de `tests/unitaires/test_taches_ajouter.php`, sous `const POST_CARTE = …;` :

```php
const LISTE_A_FAIRE = 'GET https://api.trello.com/1/lists/';
```

À la fin du même fichier :

```php
test("ajouter : réponse de Trello perdue (délai dépassé), le renvoi retrouve la carte créée au lieu d'en créer une seconde", function () {
    base_de_test();
    fixer_heure('2026-10-10 08:00:00');
    copie_trello([]);
    faux_http([POST_CARTE => new ErreurReseau('délai dépassé')]);
    egal(502, ajouter(1, ['domaine' => 'etudes', 'titre' => 'Réviser le chapitre 3'])['statut']);
    fixer_heure('2026-10-10 08:00:30');
    faux_http([LISTE_A_FAIRE => ['json' => [carte_trello('etudes', 'À faire', 5, ['name' => 'Réviser le chapitre 3'])]]]);
    $r = ajouter(1, ['domaine' => 'etudes', 'titre' => 'Réviser le chapitre 3']);
    egal(200, $r['statut']);
    vrai(str_starts_with(appels_http()[0], LISTE_A_FAIRE . fabrique_trello_ids()['tableaux']['etudes']['colonnes']['À faire'] . '/cards'), 'la liste « À faire » des Études est lue');
    egal([], array_values(array_filter(appels_http(), fn(string $a): bool => str_starts_with($a, 'POST '))));
    egal(id_test('carte', 5), corps($r)['carte']['id']);
    egal([id_test('carte', 5)], array_column(copie_lire('trello')['contenu']['cartes'], 'id'));
    egal(['carte_ajoutee'], db()->query('SELECT type FROM actions')->fetchAll(PDO::FETCH_COLUMN));
    egal(0, (int) db()->query('SELECT COUNT(*) FROM tentatives')->fetchColumn());
});

test("ajouter : au renvoi, sans carte de ce titre créée depuis la tentative (même titre mais ancienne, ou autre titre), la carte est créée", function () {
    base_de_test();
    fixer_heure('2026-10-10 08:00:00');
    copie_trello([]);
    faux_http([POST_CARTE => new ErreurReseau('délai dépassé')]);
    ajouter(1, ['domaine' => 'perso', 'titre' => 'Dentiste']);
    // Les 8 premiers caractères de l'identifiant : créée en 2020, ce n'est pas celle de la tentative
    $ancienne = carte_trello('perso', 'À faire', 6, ['id' => '5f0000000000000000000006', 'name' => 'Dentiste']);
    faux_http([
        LISTE_A_FAIRE => ['json' => [$ancienne, carte_trello('perso', 'À faire', 7, ['name' => 'Autre chose'])]],
        POST_CARTE => ['json' => carte_trello('perso', 'À faire', 8, ['name' => 'Dentiste'])],
    ]);
    $r = ajouter(1, ['domaine' => 'perso', 'titre' => 'Dentiste']);
    egal(200, $r['statut']);
    egal(id_test('carte', 8), corps($r)['carte']['id']);
    egal(1, count(array_filter(appels_http(), fn(string $a): bool => str_starts_with($a, 'POST '))));
});

test('ajouter : en écritures simulées (dev), ni tentative ni lecture de la liste', function () {
    base_de_test();
    $GLOBALS['JR_CONFIG'] = config_de_test(['trello' => ['ecritures' => 'simulees']]);
    copie_trello([]);
    faux_http([]);
    egal(200, ajouter(1, ['domaine' => 'asso', 'titre' => 'Essai'])['statut']);
    egal(0, (int) db()->query('SELECT COUNT(*) FROM tentatives')->fetchColumn());
});
```

`tests/unitaires/test_base.php` : la constante devient

```php
const JR_TABLES_LOT1 = ['actions', 'copies', 'ecritures', 'essais_connexion', 'jetons', 'reglages', 'sessions', 'tentatives'];
```

et chaque version attendue passe de 1 à 2 : `egal(2, version_base($pdo));`, `egal(2, version_attendue());` (premier test), `egal(2, migrer($pdo));` et `egal(2, version_base($pdo));` (deuxième test), `egal(2, migrer($pdo));` (dernier test). Le titre du premier test devient `'base : les migrations créent les 8 tables du lot 1 et notent la version'`.

`tests/unitaires/test_routeur.php`, ligne 8 : `'version_base' => 2`.

- [ ] **Step 2: Run them to make sure they fail**

Run: `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : échecs des tests de base (7 tables, version 1), du routeur (version 1) et des 3 nouveaux tests (table `tentatives` absente, `POST` refait).

- [ ] **Step 3: Implement**

`serveur/migrations/002_tentatives.sql` :

```sql
-- Lot 1, plan 3 : captures dont l'envoi à Trello a commencé. Si la réponse de Trello se perd, le renvoi
-- (même id_client) retrouve la carte créée au lieu d'en créer une seconde. Heures en UTC.

CREATE TABLE IF NOT EXISTS tentatives (
  id_client CHAR(36) NOT NULL PRIMARY KEY,
  debut DATETIME NOT NULL,
  KEY debut (debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

`serveur/lib/taches.php`, au-dessus de `function tache_ajouter` :

```php
const JR_TENTATIVES_JOURS = 1;

// Une capture dont l'envoi à Trello a commencé (revue du plan 2) : si sa réponse se perd, le renvoi
// cherche la carte avant d'en créer une seconde. ecriture_unique() tient déjà le verrou de l'id_client.
function tentative_debut(string $id_client): ?DateTimeImmutable
{
    $requete = db()->prepare('SELECT debut FROM tentatives WHERE id_client = ?');
    $requete->execute([$id_client]);
    $debut = $requete->fetchColumn();
    return $debut === false ? null : depuis_utc((string) $debut);
}

function tentative_noter(string $id_client): void
{
    $maintenant = maintenant();
    db()->prepare('INSERT INTO tentatives (id_client, debut) VALUES (?, ?)')->execute([$id_client, utc($maintenant)]);
    db()->prepare('DELETE FROM tentatives WHERE debut < ? LIMIT 50')
        ->execute([utc($maintenant->modify('-' . JR_TENTATIVES_JOURS . ' days'))]);
}

function tentative_oublier(string $id_client): void
{
    db()->prepare('DELETE FROM tentatives WHERE id_client = ?')->execute([$id_client]);
}

// La carte de ce titre créée depuis $debut (une minute de marge) dans « À faire », ou null.
// Les 8 premiers caractères d'un identifiant Trello donnent l'heure de création, en secondes.
function trello_carte_deja_creee(string $domaine, string $titre, DateTimeImmutable $debut): ?array
{
    $cartes = trello_appel('GET', '/lists/' . trello_id_colonne($domaine, 'a_faire') . '/cards', [
        'fields' => 'name,desc,idBoard,idList,idLabels,labels,pos,due,dueComplete,closed,shortUrl,dateLastActivity',
    ]);
    foreach (is_array($cartes) ? $cartes : [] as $carte) {
        if (is_array($carte) && ($carte['name'] ?? null) === $titre && preg_match(JR_REGEX_ID_TRELLO, (string) ($carte['id'] ?? ''))
            && hexdec(substr($carte['id'], 0, 8)) >= $debut->getTimestamp() - 60) {
            return $carte;
        }
    }
    return null;
}
```

Dans `tache_ajouter()` : la signature devient `function tache_ajouter(mixed $domaine, mixed $titre, mixed $urgent, mixed $echeance, ?string $id_client = null): array`, la fermeture passée à `sous_verrou_trello` reçoit `$id_client` (`use ($domaine, $titre, $urgent, $quand, $corps, $id_client)`), et sa première ligne `$cree = trello_ecrire('POST', '/cards', $corps);` est remplacée par :

```php
        $suivie = $id_client !== null && config('trello')['ecritures'] === 'reelles';
        $debut = $suivie ? tentative_debut($id_client) : null;
        $cree = $debut !== null ? trello_carte_deja_creee($domaine, $titre, $debut) : null;
        if ($cree === null) {
            if ($suivie && $debut === null) {
                tentative_noter($id_client);
            }
            $cree = trello_ecrire('POST', '/cards', $corps);
        }
```

et, juste avant le `return $carte;` de la fermeture :

```php
        if ($suivie) {
            tentative_oublier($id_client);
        }
```

`serveur/routes/taches.php`, la ligne de `taches/ajouter` devient :

```php
    'POST taches/ajouter' => route_ecriture('taches/ajouter', fn(array $c): array => tache_ajouter($c['domaine'] ?? null, $c['titre'] ?? null, $c['urgent'] ?? null, $c['echeance'] ?? null, $c['id_client'])),
```

(`route_ecriture()` a déjà vérifié que `id_client` est une chaîne.)

- [ ] **Step 4: Run the tests**

Run: `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : `156 réussi(s), 0 échec(s)`.

- [ ] **Step 5: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add serveur/ tests/ && git commit -m "Lot 1 : capture sans seconde carte quand la réponse de Trello se perd (table tentatives, migration 002)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---
### Task 3 : le socle de l'app et ses tests d'écran

La page unique, ses polices, le style de base de la console, la navigation par `#/…` entre des écrans d'attente, et l'outillage des tests d'écran (Playwright sur un serveur Node avec une fausse API, utilisée à partir de la tâche 5).

**Files:**
- Create: `outils/telecharger-polices.py`, `site/fonts/archivo.woff2`, `site/fonts/martian-mono.woff2`, `site/fonts/permanent-marker.woff2`, `site/fonts/licences/` (3 fichiers)
- Create: `site/index.html`, `site/manifest.webmanifest`, `site/css/app.css`, `site/js/lib/dom.js`, `site/js/lib/textes.js`, `site/js/app.js`, `site/js/ecrans/attente.js`
- Create: `tests/e2e/package.json`, `tests/e2e/cadre.mjs`, `tests/e2e/serveur.mjs`, `tests/e2e/fixtures.mjs`, `tests/e2e/lancer.mjs`, `tests/e2e/test_socle.mjs`

**Interfaces:**
- Consumes : `site/.htaccess` (en-têtes de sécurité relus par le serveur de test), `site/icons/`, `site/apple-touch-icon.png`, `site/favicon-32.png`, `site/img/lancement-1170x2532.png` (étape 7).
- Produces :
  - `h(tag, props, ...enfants)` : props `class`, `vars` (propriétés CSS), `dataset`, `on…` (écouteurs), sinon attributs (`true` donne un attribut vide, `null`, `undefined` et `false` sont ignorés) ; enfants : nœuds, textes, tableaux imbriqués, `null`/`false` ignorés.
  - `icone(nom, taille = 22, epaisseur = 1.75)` : SVG au trait, `aria-hidden`. Noms : `aujourdhui`, `agenda`, `taches`, `moi`, `plus`, `micro`, `gauche`, `droite`, `fermer`, `externe`, `ferie`.
  - `texteAvecLiens(texte)` : tableau de textes et de liens `<a>` (`http`/`https` seulement).
  - `angle(cle, max = 8)` : angle pseudo-aléatoire stable (`'-3.4deg'`) pour un tampon ou un scotch.
  - `T` (`textes.js`) : tous les textes de l'app, groupés par écran.
  - DOM de `index.html` : `#app`, `#principal`, `#statut`, `#tirer`, `#ecran` (le `<main>` qui défile), `#onglets`, `#feuille`, `#messages`. `document.documentElement.dataset.pret === '1'` après le premier rendu.
  - Tests d'écran : `test(nom, f, { serviceWorkers })`, `assert`, `heure(page, iso)`, `connecte(contexte, serveur)`, `ouvrir(page, chemin)`, `texte(page, selecteur)`, `attendre(condition, message)` dans `cadre.mjs` ; `serveur.etat` (`donnees`, `appels`, `vus`, `pannes`, `horsService`, `perdre`), `serveur.appels(route)`, `serveur.reinitialiser(donnees)`, `MOT_DE_PASSE` dans `serveur.mjs` ; `donneesDeBase()`, `CARTES`, `id()`, `iso()`, `joursDepuis()`, `joursSemaine()` dans `fixtures.mjs`.

- [ ] **Step 1: Download the fonts**

`outils/telecharger-polices.py` :

```python
#!/usr/bin/env python3
"""Télécharge les 3 polices de Jarvis Jr. (woff2 variables, sous-ensemble latin) et leurs licences.

Usage : python3 outils/telecharger-polices.py   (écrit dans site/fonts/ et site/fonts/licences/)
Le sous-ensemble latin couvre les accents, œ, ’, … et € : tous les textes de l'app."""
import pathlib
import re
import urllib.request

DOSSIER = pathlib.Path(__file__).resolve().parent.parent / 'site' / 'fonts'
CSS = ('https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900'
       '&family=Martian+Mono:wdth,wght@75..112.5,100..800&family=Permanent+Marker&display=swap')
NAVIGATEUR = ('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
              '(KHTML, like Gecko) Chrome/140.0 Safari/537.36')
FICHIERS = {'Archivo': 'archivo.woff2', 'Martian Mono': 'martian-mono.woff2', 'Permanent Marker': 'permanent-marker.woff2'}
LICENCES = {
    'OFL-Archivo.txt': 'https://raw.githubusercontent.com/google/fonts/main/ofl/archivo/OFL.txt',
    'OFL-MartianMono.txt': 'https://raw.githubusercontent.com/google/fonts/main/ofl/martianmono/OFL.txt',
    'Apache-PermanentMarker.txt': 'https://raw.githubusercontent.com/google/fonts/main/apache/permanentmarker/LICENSE.txt',
}


def lire(url):
    requete = urllib.request.Request(url, headers={'User-Agent': NAVIGATEUR})
    with urllib.request.urlopen(requete, timeout=30) as reponse:
        return reponse.read()


css = lire(CSS).decode()
# Un bloc @font-face par sous-ensemble, précédé de son nom en commentaire : on garde « latin »
trouves = {}
for sous_ensemble, bloc in re.findall(r'/\* (\S+) \*/\s*@font-face \{(.*?)\}', css, re.S):
    famille = re.search(r"font-family: '([^']+)'", bloc).group(1)
    if sous_ensemble == 'latin' and famille in FICHIERS:
        trouves[famille] = re.search(r'url\((https://[^)]+\.woff2)\)', bloc).group(1)
manquants = sorted(set(FICHIERS) - set(trouves))
if manquants:
    raise SystemExit(f'Introuvable dans la CSS de Google Fonts : {", ".join(manquants)}')

(DOSSIER / 'licences').mkdir(parents=True, exist_ok=True)
for famille, url in trouves.items():
    contenu = lire(url)
    (DOSSIER / FICHIERS[famille]).write_bytes(contenu)
    print(f'{FICHIERS[famille]} : {len(contenu) // 1024} Ko')
for nom, url in LICENCES.items():
    (DOSSIER / 'licences' / nom).write_bytes(lire(url))
    print(f'licences/{nom}')
```

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && python3 outils/telecharger-polices.py`
Expected : 3 fichiers `.woff2` (quelques dizaines à quelques centaines de Ko chacun) et 3 licences. Si une adresse de licence répond 404, chercher le bon chemin dans https://github.com/google/fonts (dossiers `ofl/` et `apache/`) et corriger `LICENCES`.

- [ ] **Step 2: Install the screen-test tooling**

`tests/e2e/package.json` :

```json
{
  "private": true,
  "type": "module",
  "description": "Tests d'écran de Jarvis Jr. : node tests/e2e/lancer.mjs (Chromium déjà en cache pour Playwright 1.63)",
  "devDependencies": {
    "playwright-core": "1.63.0"
  }
}
```

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && npm install --prefix tests/e2e --no-audit --no-fund`
Expected : `tests/e2e/node_modules/` et `tests/e2e/package-lock.json` créés (`node_modules/` est déjà dans `.gitignore`). Le Chromium utilisé est `~/Library/Caches/ms-playwright/chromium-1243`, déjà présent : rien d'autre à télécharger.

`tests/e2e/fixtures.mjs` :

```js
// Données inventées pour les tests d'écran (titres fictifs), à la façon de GET donnees, le mercredi
// 14 octobre 2026 à 15 h 45. La semaine type ressemble à l'emploi du temps A26.

const deux = (n) => String(n).padStart(2, '0');

// Identifiant de 24 caractères comme ceux de Trello : la sorte sur 2, le numéro sur 22
export const id = (sorte, n) => `${sorte}${String(n).padStart(22, '0')}`;

export const CARTES = {
  linkedin: id('ca', 1), formation: id('ca', 2), cabaret: id('ca', 3), median: id('ca', 4), apoint: id('ca', 5),
  tp: id('ca', 6), profil: id('ca', 7), jarvis: id('ca', 8), sites: id('ca', 9), logo: id('ca', 10),
};

function versUtc(jour) {
  const [a, m, j] = jour.split('-').map(Number);
  return Date.UTC(a, m - 1, j);
}

export function ajouter(jour, n) {
  const d = new Date(versUtc(jour) + n * 86400000);
  return `${d.getUTCFullYear()}-${deux(d.getUTCMonth() + 1)}-${deux(d.getUTCDate())}`;
}

const jourSemaine = (jour) => (new Date(versUtc(jour)).getUTCDay() + 6) % 7;

// Heure d'hiver à Paris à partir du dimanche 25 octobre 2026
export const iso = (jour, hhmm) => `${jour}T${hhmm}:00${jour >= '2026-10-25' ? '+01:00' : '+02:00'}`;

const SEMAINE_TYPE = [
  [['LA14', 'TD', '16:30', '18:30', 'FC114']],
  [['GE15', 'Cours', '13:00', '14:00', 'FA201'], ['GE15', 'TD', '16:30', '18:30', 'FA616']],
  [['NF93', 'TD', '08:00', '10:00', 'FA400'], ['TN01', 'TD', '16:30', '19:30', 'FA510']],
  [['MT23', 'TD', '16:30', '18:30', 'FA420']],
  [], [], [],
];
const PERSO = [['2026-10-14', 'Déjeuner avec Léon', '12:30', '13:30'], ['2026-10-21', 'Médian MT23', '19:15', '20:45']];
const BANDEAUX = {
  ferie: { type: 'ferie', jour: null, texte: "Jour férié à l'UTC : pas de cours." },
  examen: { type: 'examen', jour: null, texte: "Jour d'examen." },
  jeudi: { type: 'devient', jour: 'jeudi', texte: "Aujourd'hui, l'UTC suit l'emploi du temps du jeudi." },
};

export function jourAgenda(jour) {
  let modele = jourSemaine(jour);
  const bandeaux = [];
  if (jour === '2026-10-15') { bandeaux.push(BANDEAUX.ferie); modele = null; }
  if (jour === '2026-10-19') { bandeaux.push(BANDEAUX.jeudi); modele = 3; }
  if (jour >= '2026-10-20' && jour <= '2026-10-24') { bandeaux.push(BANDEAUX.examen); modele = null; }
  const evenements = (modele === null ? [] : SEMAINE_TYPE[modele]).map(([uv, type, debut, fin, salle]) => ({
    debut: iso(jour, debut), fin: iso(jour, fin), journee: false, source: 'utc', uv, type, salle, titre: `${uv} - ${type} 1`, median: false,
  }));
  for (const [date, titre, debut, fin] of PERSO) {
    if (date === jour) {
      evenements.push({ debut: iso(jour, debut), fin: iso(jour, fin), journee: false, source: 'perso', uv: null, type: null, salle: null, titre, median: titre.startsWith('Médian') });
    }
  }
  evenements.sort((a, b) => a.debut.localeCompare(b.debut));
  return { bandeaux, evenements };
}

export function joursDepuis(premier, nombre) {
  return Object.fromEntries(Array.from({ length: nombre }, (_, i) => {
    const jour = ajouter(premier, i);
    return [jour, jourAgenda(jour)];
  }));
}

// Les 7 jours d'une semaine ISO (2026-W42 : du lundi 12 au dimanche 18 octobre)
export function joursSemaine(semaine) {
  const annee = Number(semaine.slice(0, 4));
  const n = Number(semaine.slice(6));
  const quatre = `${annee}-01-04`;
  return joursDepuis(ajouter(ajouter(quatre, -jourSemaine(quatre)), (n - 1) * 7), 7);
}

const etapes = (n, noms, coches = []) => ({
  id: id('cc', n), nom: 'Étapes',
  elements: noms.map((nom, i) => ({ id: id('ee', n * 10 + i + 1), nom, coche: coches.includes(i) })),
});

const carte = (n, domaine, colonne, titre, autres = {}) => ({
  id: id('ca', n), domaine, colonne, position: n * 1000, titre, description: '', url: `https://trello.com/c/test${n}`,
  echeance: null, echeance_faite: false, urgent: false, etiquettes: [], checklists: [],
  derniere_activite: '2026-10-13T10:00:00+02:00', ...autres,
});

// Dans l'ordre du serveur : domaine, puis position
export function cartes() {
  return [
    carte(1, 'qyris', 'a_faire', 'Campagne LinkedIn : programmer les posts', {
      echeance: '2026-10-06T08:30:00+02:00', etiquettes: ['LinkedIn'],
      checklists: [etapes(1, ['Envoyer un mail de test', 'Compléter la page', 'Programmer les posts'])],
    }),
    carte(2, 'qyris', 'a_faire', 'Formation 2 : relire le deck, tester les exercices', {
      etiquettes: ['Formations'], description: 'Deck dans le dossier Formations.\n\nListe des tests : https://exemple.fr/tests',
      checklists: [etapes(2, ['Relire le deck en mode présentation', 'Tester les exercices sur les comptes gratuits'])],
    }),
    carte(10, 'qyris', 'fait', 'Logo : variantes PNG', { derniere_activite: '2026-10-12T18:00:00+02:00' }),
    carte(3, 'asso', 'a_faire', 'Cabaret : faire valider la lineup', {
      urgent: true, checklists: [etapes(3, ['Valider les horaires avec la team bouffe', 'Confirmer Oasis sur la grande scène'])],
    }),
    carte(4, 'etudes', 'a_faire', 'MT23 : préparer le médian', { echeance: '2026-10-21T19:15:00+02:00', etiquettes: ['MT23'] }),
    carte(5, 'etudes', 'en_cours', "A'point : tester sur téléphone et clôturer", {
      etiquettes: ['GE15'],
      checklists: [etapes(5, ['Créer le sous-domaine', 'Mettre en ligne', 'Vérifier le site en ligne, téléphone compris', 'Décider du commit'], [0, 1])],
    }),
    carte(6, 'etudes', 'a_faire', 'TN01 : rendre le TP', { echeance: '2026-10-14T18:00:00+02:00', etiquettes: ['TN01'] }),
    carte(7, 'perso', 'a_faire', 'LinkedIn : mettre à jour mon profil', { echeance: '2026-10-05T18:00:00+02:00' }),
    carte(8, 'perso', 'en_cours', "Jarvis Jr. : construire mon app d'organisation", {
      checklists: [etapes(8, ['Choisir le nom', 'Créer un compte cron-job.org', 'Rediriger la boîte UTC'], [0])],
    }),
    carte(9, 'perso', 'en_attente', 'Supprimer les anciens sites Netlify'),
  ];
}

export function donneesDeBase() {
  const maintenant = '2026-10-14T15:45:00+02:00';
  return {
    ok: true,
    maintenant,
    trello: { recupere_le: maintenant, erreur: null, cartes: cartes() },
    agenda: { recupere_le: maintenant, erreur: null, google_connecte: true, jours: joursDepuis('2026-10-14', 15) },
  };
}
```

`tests/e2e/serveur.mjs` :

```js
// Serveur des tests d'écran : les fichiers de site/ avec les en-têtes de sécurité du .htaccess (une seule
// source), et une fausse API (Trello et Google simulés) dont l'état est lu et changé par les tests.
import http from 'node:http';
import { readFile } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';
import { donneesDeBase, joursSemaine } from './fixtures.mjs';

const SITE = fileURLToPath(new URL('../../site/', import.meta.url));
const TYPES = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json', '.webmanifest': 'application/manifest+json', '.png': 'image/png', '.webp': 'image/webp',
  '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.txt': 'text/plain; charset=utf-8',
};
const ENTETES = Object.fromEntries(
  [...readFileSync(join(SITE, '.htaccess'), 'utf8').matchAll(/^\s*Header always set (\S+) "([^"]*)"$/gm)]
    .map((m) => [m[1], m[2]])
    .filter(([nom]) => nom !== 'Strict-Transport-Security'),
);

export const MOT_DE_PASSE = 'phrase de test';

export async function demarrer() {
  const etat = {};
  const s = {
    etat,
    origine: '',
    reinitialiser(donnees = donneesDeBase()) {
      for (const cle of Object.keys(etat)) delete etat[cle];
      // horsService : le réseau manque (connexion coupée). perdre : la prochaine écriture de cette route est
      // faite, mais sa réponse se perd. pannes : route → { statut, code, message }.
      Object.assign(etat, { donnees, appels: [], vus: new Map(), pannes: {}, horsService: false, perdre: null });
    },
    appels(route) {
      return etat.appels.filter((a) => a.route === route);
    },
  };
  s.reinitialiser();
  const serveur = http.createServer((req, res) => {
    traiter(req, res, etat).catch((e) => {
      res.writeHead(500);
      res.end(String(e));
    });
  });
  await new Promise((resolve) => serveur.listen(0, '127.0.0.1', resolve));
  s.origine = `http://127.0.0.1:${serveur.address().port}`;
  s.arreter = () => new Promise((resolve) => {
    serveur.closeAllConnections();
    serveur.close(resolve);
  });
  return s;
}

async function traiter(req, res, etat) {
  const url = new URL(req.url, 'http://test');
  if (url.pathname.startsWith('/api/')) return api(req, res, url, etat);
  const chemin = decodeURIComponent(url.pathname === '/' ? '/index.html' : url.pathname);
  const fichier = normalize(join(SITE, chemin));
  if (!fichier.startsWith(SITE) || chemin.split('/').some((p) => p.startsWith('.'))) {
    return envoyer(res, 404, 'text/plain; charset=utf-8', 'Introuvable');
  }
  let contenu;
  try {
    contenu = await readFile(fichier);
  } catch {
    return envoyer(res, 404, 'text/plain; charset=utf-8', 'Introuvable');
  }
  envoyer(res, 200, TYPES[extname(fichier)] ?? 'application/octet-stream', contenu);
}

function envoyer(res, statut, type, corps, autres = {}) {
  res.writeHead(statut, { ...ENTETES, 'Content-Type': type, 'Cache-Control': 'no-cache', ...autres });
  res.end(corps);
}

function json(res, statut, donnees, autres = {}) {
  const corps = statut < 400 ? { ok: true, ...donnees } : { ok: false, erreur: donnees };
  envoyer(res, statut, 'application/json', JSON.stringify(corps), { 'Cache-Control': 'no-store', ...autres });
}

async function api(req, res, url, etat) {
  if (etat.horsService) {
    req.socket.destroy();
    return;
  }
  const route = url.pathname.slice('/api/'.length);
  let corps = {};
  if (req.method === 'POST') {
    let brut = '';
    for await (const morceau of req) brut += morceau;
    corps = brut === '' ? {} : JSON.parse(brut);
  }
  etat.appels.push({ route, methode: req.method, corps, parametres: Object.fromEntries(url.searchParams) });
  const panne = etat.pannes[route];
  if (panne) return json(res, panne.statut, { code: panne.code, message: panne.message });
  if (req.method === 'GET' && route === 'sante') return json(res, 200, { version_app: '0.2.0', version_base: 2, base_ok: true });
  if (req.method === 'POST' && route === 'connexion') {
    if (corps.mot_de_passe !== MOT_DE_PASSE) {
      return json(res, 401, { code: 'mot_de_passe_incorrect', message: 'Mot de passe incorrect. Encore 9 essais avant une pause de 15 min.' });
    }
    return json(res, 200, {}, { 'Set-Cookie': 'jr-test=1; Path=/; HttpOnly; SameSite=Lax' });
  }
  if (!/(?:^|;\s*)jr-test=1(?:;|$)/.test(req.headers.cookie ?? '')) {
    return json(res, 401, { code: 'session_expiree', message: 'Ta session a expiré. Reconnecte-toi.' });
  }
  if (req.method === 'POST' && (route === 'deconnexion' || route === 'deconnexion-partout')) {
    return json(res, 200, {}, { 'Set-Cookie': 'jr-test=; Path=/; Max-Age=0' });
  }
  if (req.method === 'GET' && route === 'donnees') return json(res, 200, structuredClone(etat.donnees));
  if (req.method === 'GET' && route === 'agenda') {
    const semaine = url.searchParams.get('semaine');
    return json(res, 200, {
      maintenant: etat.donnees.maintenant, semaine,
      agenda: { recupere_le: etat.donnees.maintenant, erreur: null, google_connecte: true, jours: joursSemaine(semaine) },
    });
  }
  if (req.method === 'GET' && route === 'google/connexion') {
    etat.donnees.agenda.google_connecte = true;
    etat.donnees.agenda.erreur = null;
    return envoyer(res, 302, 'text/plain; charset=utf-8', '', { Location: '/#/moi?google=connecte' });
  }
  if (req.method === 'POST') return ecrire(req, res, route, corps, etat);
  return json(res, 404, { code: 'route_inconnue', message: 'Adresse inconnue.' });
}

// Les écritures, comme le vrai serveur : un id_client déjà vu rend le même résultat sans rien refaire
function ecrire(req, res, route, corps, etat) {
  const deja = etat.vus.get(corps.id_client);
  if (deja) return json(res, 200, deja.resultat);
  const cartes = etat.donnees.trello.cartes;
  const carte = cartes.find((c) => c.id === corps.carte);
  let resultat;
  if (route === 'taches/ajouter') {
    const heure = corps.echeance && corps.echeance.length > 10 ? corps.echeance.slice(11, 16) : '18:00';
    const nouvelle = {
      id: `cb${String(etat.vus.size + 1).padStart(22, '0')}`, domaine: corps.domaine, colonne: 'a_faire', position: 1,
      titre: corps.titre, description: '', url: 'https://trello.com/c/nouvelle',
      echeance: corps.echeance ? `${corps.echeance.slice(0, 10)}T${heure}:00+02:00` : null, echeance_faite: false,
      urgent: corps.urgent === true, etiquettes: [], checklists: [], derniere_activite: etat.donnees.maintenant,
    };
    cartes.unshift(nouvelle);
    resultat = { carte: nouvelle };
  } else if (route === 'taches/annuler') {
    const origine = etat.vus.get(corps.id_client_origine);
    if (!origine || origine.route !== 'taches/terminer') return json(res, 404, { code: 'annulation_impossible', message: 'Rien à annuler.' });
    const annulee = cartes.find((c) => c.id === origine.resultat.carte);
    annulee.colonne = origine.resultat.colonne_avant;
    resultat = { carte: annulee.id, colonne: annulee.colonne };
  } else if (!carte) {
    return json(res, 404, { code: 'carte_introuvable', message: "Cette carte n'existe plus dans Trello." });
  } else if (route === 'taches/cocher') {
    for (const liste of carte.checklists) for (const e of liste.elements) if (e.id === corps.element) e.coche = corps.coche;
    resultat = { carte: carte.id, element: corps.element, coche: corps.coche };
  } else if (route === 'taches/terminer') {
    resultat = { carte: carte.id, colonne: 'fait', colonne_avant: carte.colonne };
    carte.colonne = 'fait';
  } else if (route === 'taches/deplacer') {
    carte.colonne = corps.colonne;
    resultat = { carte: carte.id, colonne: corps.colonne };
  } else if (route === 'tirage/parti') {
    resultat = { carte: carte.id, element: corps.element };
  } else {
    return json(res, 404, { code: 'route_inconnue', message: 'Adresse inconnue.' });
  }
  etat.vus.set(corps.id_client, { route, resultat });
  if (etat.perdre === route) {
    etat.perdre = null;
    req.socket.destroy();
    return;
  }
  return json(res, 200, resultat);
}
```

`tests/e2e/cadre.mjs` :

```js
// Mini-cadre des tests d'écran : Playwright (playwright-core 1.63.0, Chromium déjà en cache), un iPhone 14
// réglé exprès sur le fuseau de New York, le serveur de test et sa fausse API.
import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import { demarrer } from './serveur.mjs';

export { assert };

const tests = [];

export function test(nom, f, options = {}) {
  tests.push({ nom, f, options });
}

export const IPHONE = {
  viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true,
  locale: 'fr-FR', timezoneId: 'America/New_York',
};

// Messages de la console qui ne sont pas des erreurs de l'app : un échec réseau voulu (hors ligne, 401)
const IGNORES = [/^Failed to load resource/];

export async function lancer(filtre) {
  const serveur = await demarrer();
  const navigateur = await chromium.launch();
  let reussis = 0;
  let echecs = 0;
  for (const { nom, f, options } of tests) {
    if (filtre && !nom.includes(filtre)) continue;
    serveur.reinitialiser();
    const contexte = await navigateur.newContext({ ...IPHONE, baseURL: serveur.origine, serviceWorkers: options.serviceWorkers ?? 'block' });
    const page = await contexte.newPage();
    const erreurs = [];
    page.on('console', (m) => {
      if (m.type() === 'error' && !IGNORES.some((r) => r.test(m.text()))) erreurs.push(m.text());
    });
    page.on('pageerror', (e) => erreurs.push(e.message));
    try {
      await f({ page, contexte, serveur });
      assert.deepEqual(erreurs, [], 'console sans erreur');
      reussis++;
      console.log(`  ok      ${nom}`);
    } catch (e) {
      echecs++;
      console.log(`  ÉCHEC   ${nom}\n          ${String(e.message).split('\n').join('\n          ')}`);
    } finally {
      await contexte.close();
    }
  }
  await navigateur.close();
  await serveur.arreter();
  console.log(`${reussis} réussi(s), ${echecs} échec(s)`);
  return echecs === 0 ? 0 : 1;
}

// L'heure de l'iPhone, fixe (les minuteurs continuent de tourner). Avant ouvrir().
export async function heure(page, iso) {
  await page.clock.setFixedTime(new Date(iso));
}

export async function connecte(contexte, serveur) {
  await contexte.addCookies([{ name: 'jr-test', value: '1', url: serveur.origine }]);
}

export async function ouvrir(page, chemin = '/') {
  await page.goto(chemin);
  await page.waitForFunction(() => document.documentElement.dataset.pret === '1');
}

// Le texte d'un élément, espaces normalisés (textContent : sans les majuscules ajoutées par le CSS)
export async function texte(page, selecteur) {
  return ((await page.locator(selecteur).first().textContent()) ?? '').replace(/\s+/g, ' ').trim();
}

export async function attendre(condition, message, delai = 4000) {
  const fin = Date.now() + delai;
  while (!(await condition())) {
    if (Date.now() > fin) throw new Error(`Délai dépassé : ${message}`);
    await new Promise((r) => setTimeout(r, 25));
  }
}
```

`tests/e2e/lancer.mjs` :

```js
// Tests d'écran de Jarvis Jr. : node tests/e2e/lancer.mjs [morceau du nom d'un test]
import { readdirSync } from 'node:fs';
import { lancer } from './cadre.mjs';

const dossier = new URL('.', import.meta.url);
for (const nom of readdirSync(dossier).filter((f) => /^test_.*\.mjs$/.test(f)).sort()) {
  await import(new URL(nom, dossier));
}
process.exitCode = await lancer(process.argv[2] ?? '');
```

- [ ] **Step 3: Write the failing screen tests**

`tests/e2e/test_socle.mjs` :

```js
import { test, assert, connecte, ouvrir, texte } from './cadre.mjs';

test('socle : la page charge sa police et ses modules, sans erreur ni refus de la CSP', async ({ page }) => {
  await ouvrir(page, '/');
  await page.evaluate(() => document.fonts.ready);
  const chargees = await page.evaluate(() => [...document.fonts].filter((f) => f.status === 'loaded').map((f) => f.family.replaceAll('"', '')));
  assert.ok(chargees.includes('Archivo'), `Archivo chargée (vu : ${chargees.join(', ')})`);
  for (const police of ['archivo', 'martian-mono', 'permanent-marker']) {
    const r = await page.request.get(`/fonts/${police}.woff2`);
    assert.equal(r.status(), 200, police);
  }
});

// Connecté : à partir de la tâche 5, sans session, l'app affiche l'écran de connexion sans onglets
test("socle : 4 onglets et « Noter quelque chose », cibles de 44 px au moins, navigation par #/", async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await ouvrir(page, '/');
  const cibles = page.locator('#onglets a, #onglets button');
  assert.equal(await cibles.count(), 5);
  const tailles = await cibles.evaluateAll((els) => els.map((e) => {
    const r = e.getBoundingClientRect();
    return [r.width, r.height];
  }));
  for (const [largeur, hauteur] of tailles) assert.ok(largeur >= 44 && hauteur >= 44, `cible de ${largeur} × ${hauteur}`);
  await page.getByRole('button', { name: 'Noter quelque chose' }).waitFor();
  await page.getByRole('link', { name: 'Agenda' }).click();
  await page.getByRole('heading', { name: 'Agenda' }).waitFor();
  assert.equal(await texte(page, '.onglet[aria-current="page"]'), 'Agenda');
});

test('socle : manifeste servi avec son type, en plein écran, couleurs de la console', async ({ page }) => {
  const r = await page.request.get('/manifest.webmanifest');
  assert.equal(r.headers()['content-type'], 'application/manifest+json');
  const m = await r.json();
  assert.deepEqual([m.name, m.display, m.background_color, m.theme_color], ['Jarvis Jr.', 'standalone', '#121417', '#121417']);
});
```

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs`
Expected : `0 réussi(s), 3 échec(s)` (page introuvable).

- [ ] **Step 4: Write the page, the manifest and the base style**

`site/index.html` :

```html
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Jarvis Jr.</title>
<meta name="description" content="Le petit frère de Jarvis : ton emploi du temps, tes tâches, tes mails et tes rappels, dans ta poche.">
<meta name="theme-color" content="#121417">
<meta name="color-scheme" content="dark">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Jarvis Jr.">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="apple-touch-startup-image" href="/img/lancement-1170x2532.png" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="preload" href="/fonts/archivo.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/martian-mono.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/app.css">
<script type="module" src="/js/app.js"></script>
</head>
<body>
<div id="app" class="app">
  <div id="principal" class="principal">
    <div id="statut" class="statut" role="status" hidden></div>
    <p id="tirer" class="tirer" role="status" hidden></p>
    <main id="ecran" class="ecran" tabindex="-1"></main>
    <nav id="onglets" class="onglets" aria-label="Onglets"></nav>
  </div>
  <div id="feuille"></div>
  <div id="messages" class="messages" aria-live="polite"></div>
</div>
<noscript><p class="vide">Jarvis Jr. a besoin de JavaScript.</p></noscript>
</body>
</html>
```

`site/manifest.webmanifest` :

```json
{
  "name": "Jarvis Jr.",
  "short_name": "Jarvis Jr.",
  "description": "Le petit frère de Jarvis : ton emploi du temps, tes tâches, tes mails et tes rappels, dans ta poche.",
  "lang": "fr",
  "start_url": "/#/aujourdhui",
  "scope": "/",
  "display": "standalone",
  "orientation": "portrait",
  "background_color": "#121417",
  "theme_color": "#121417",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

`site/css/app.css` (le style de chaque écran s'ajoute à la fin, tâche par tâche) :

```css
/* Jarvis Jr. : la console de régie (contrat .impeccable/surfaces/site-index-html.md, maquette v28).
   Graphite, panneaux console, ambre réservé à l'afficheur et à l'unique élément allumé, sans halo.
   CSP stricte : aucun style en ligne, les valeurs qui varient passent par des propriétés CSS (--angle…).
   Les tailles de texte en calc(N * var(--px)) suivent la taille de texte réglée sur l'iPhone. */

@font-face { font-family: 'Archivo'; src: url('/fonts/archivo.woff2') format('woff2'); font-weight: 100 900; font-stretch: 62% 125%; font-display: swap; }
@font-face { font-family: 'Martian Mono'; src: url('/fonts/martian-mono.woff2') format('woff2'); font-weight: 100 800; font-stretch: 75% 112.5%; font-display: swap; }
@font-face { font-family: 'Permanent Marker'; src: url('/fonts/permanent-marker.woff2') format('woff2'); font-weight: 400; font-display: swap; }

:root {
  color-scheme: dark;
  --graphite: #121417;
  --panneau: #1E2228;
  --encre: #0A0B0D;
  --bord: #262A31;
  --regle: #2A2F37;
  --console: #2A2F37;
  --console-on: #343A44;
  --contour: #3A4048;
  --texte: #ECEAE4;
  --texte-2: #9EA3A8;
  --texte-3: #8A8F96;
  --ambre: #FFB020;
  --ambre-doux: #B98222;
  --ambre-sombre: #4A3814;
  --ambre-trait: #2A2416;
  --ambre-appuye: #D99414;
  --scotch: #EDE6D6;
  --scotch-encre: #16181B;
  --rouge: #E5484D;
  --rouge-texte: #FF6B70;
  --qyris: #3DD68C;
  --asso: #A97BFF;
  --etudes: #6FB0FF;
  --perso: #FF7AC6;
  --mono: 'Martian Mono', ui-monospace, monospace;
  --px: calc(1rem / 17);
  --ombre-panneau: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 8px 20px rgba(0, 0, 0, 0.35);
  --ombre-bouton: inset 0 1px 0 rgba(255, 255, 255, 0.08), inset 0 -3px 0 rgba(0, 0, 0, 0.25), 0 4px 10px rgba(0, 0, 0, 0.35);
  --ombre-allume: inset 0 1px 0 rgba(255, 255, 255, 0.5), inset 0 -4px 0 rgba(0, 0, 0, 0.18), 0 4px 10px rgba(0, 0, 0, 0.4);
}
.d-qyris { --domaine: var(--qyris); }
.d-asso { --domaine: var(--asso); }
.d-etudes { --domaine: var(--etudes); }
.d-perso { --domaine: var(--perso); }

*, *::before, *::after { box-sizing: border-box; }
html { font-size: 17px; background: var(--graphite); -webkit-text-size-adjust: 100%; }
@supports (font: -apple-system-body) { html { font: -apple-system-body; } }
body { margin: 0; background: var(--graphite); color: var(--texte); font-family: 'Archivo', system-ui, sans-serif; font-size: calc(16 * var(--px)); line-height: 1.35; -webkit-font-smoothing: antialiased; -webkit-tap-highlight-color: transparent; }
button, input, textarea, select { font: inherit; color: inherit; }
button { cursor: pointer; }
a { color: inherit; }
h1, h2, h3, p, ul { margin: 0; }
ul { padding: 0; list-style: none; }
::selection { background: var(--ambre); color: var(--graphite); }
:focus-visible { outline: 2px solid var(--ambre); outline-offset: 3px; }
[hidden] { display: none !important; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }

/* La coque : statut, écran qui défile, onglets */
.app { height: 100dvh; max-width: 640px; margin-inline: auto; display: flex; flex-direction: column; padding-top: max(env(safe-area-inset-top), 12px); }
.principal { flex: 1 1 0; min-height: 0; display: flex; flex-direction: column; }
/* container-type : le premier écran d'Aujourd'hui prend toute la hauteur visible (100cqh) */
.ecran { flex: 1 1 0; min-height: 0; overflow-x: hidden; overflow-y: auto; overscroll-behavior-y: contain; padding: 0 16px 16px; outline: none; container-type: size; }
.statut { flex: none; margin: 0 16px 8px; padding: 8px 12px; display: flex; flex-direction: column; gap: 6px; border-radius: 8px; background: var(--panneau); font-family: var(--mono); font-size: 12px; line-height: 1.45; color: var(--texte-2); }
.statut-action { display: flex; align-items: center; gap: 10px; }
.statut-action span { flex: 1 1 auto; }
.tirer { flex: none; padding: 4px 16px; text-align: center; font-family: var(--mono); font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--texte-2); }
.onglets { flex: none; display: flex; align-items: stretch; gap: 4px; padding: 0 16px max(env(safe-area-inset-bottom), 8px); border-top: 1px solid #22262C; }
.onglet { flex: 1 1 0; min-width: 0; min-height: 52px; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding-top: 6px; color: var(--texte-2); font-size: 11px; font-weight: 600; font-stretch: 80%; text-decoration: none; }
.onglet[aria-current='page'] { color: var(--texte); }
.onglet[aria-current='page']::before { content: ''; position: absolute; top: -1px; left: 22%; right: 22%; height: 2px; background: var(--scotch); }
.bouton-plus { flex: none; align-self: center; width: 52px; height: 44px; margin-top: 4px; display: flex; align-items: center; justify-content: center; border: 0; border-radius: 10px; background: var(--scotch); color: var(--graphite); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6), inset 0 -3px 0 rgba(0, 0, 0, 0.15), 0 4px 10px rgba(0, 0, 0, 0.35); }
.app.sans-onglets .onglets { display: none; }

/* Pièces communes */
.entete { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding-top: 6px; }
.titre-ecran { font-size: calc(30 * var(--px)); font-weight: 750; font-stretch: 80%; letter-spacing: -0.01em; }
.entete-cote { font-family: var(--mono); font-size: 12px; letter-spacing: 0.08em; color: var(--texte-2); }
.entete-retour { display: flex; align-items: center; gap: 8px; padding-top: 2px; }
.section-titre { margin-top: 18px; font-family: var(--mono); font-size: 11px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: var(--texte-2); }
.regle { position: relative; height: 1px; margin-top: 8px; background: var(--regle); }
.regle span { position: absolute; top: 0; width: 1px; height: 6px; background: var(--regle); }
.regle span:nth-child(1) { left: calc((100% - 24px) / 4 + 4px); }
.regle span:nth-child(2) { left: 50%; }
.regle span:nth-child(3) { left: calc((100% - 24px) * 3 / 4 + 20px); }
.rang { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); column-gap: 8px; align-items: center; min-height: 44px; border-bottom: 1px solid var(--panneau); }
.rang > :nth-child(2) { grid-column: 2 / span 3; min-width: 0; }
.vide { margin: 16px 0; color: var(--texte-2); font-size: calc(15 * var(--px)); }
.console { background: var(--panneau); border-radius: 14px; padding: 10px; box-shadow: var(--ombre-panneau); }
.afficheur { background: var(--encre); border: 1px solid var(--bord); border-radius: 8px; padding: 13px 14px 12px; color: var(--ambre); box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.75); }
.voyant { display: inline-block; flex: none; font-family: var(--mono); font-size: 10px; font-weight: 600; letter-spacing: 0.12em; line-height: 1; white-space: nowrap; color: var(--ambre-doux); border: 1px solid var(--ambre-sombre); border-radius: 3px; padding: 4px 5px 3px; }
.voyant.allume { color: var(--encre); background: var(--ambre); border-color: var(--ambre); }
.voyant.creme { color: var(--scotch); border-color: var(--scotch); }
.tampon { display: inline-block; flex: none; transform: rotate(var(--angle, -6deg)); border: 2px solid currentColor; border-radius: 4px; padding: 3px 6px 2px; font-family: 'Archivo', sans-serif; font-stretch: 62.5%; font-weight: 800; font-size: 13px; letter-spacing: 0.12em; line-height: 1; color: var(--texte-3); }
.tampon.frappe { animation: frappe 180ms cubic-bezier(0.2, 0.9, 0.3, 1.2); }
@keyframes frappe { from { transform: rotate(var(--angle, -6deg)) scale(1.5); opacity: 0; } to { transform: rotate(var(--angle, -6deg)) scale(1); opacity: 1; } }
.scotch { display: block; height: 26px; line-height: 28px; padding: 0 4px; background: var(--scotch); color: var(--scotch-encre); font-family: 'Permanent Marker', cursive; font-size: 15px; text-align: center; border-radius: 2px; white-space: nowrap; overflow: hidden; transform: rotate(var(--angle, 0deg)); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.5); }
.carre { display: inline-block; flex: none; width: 8px; height: 8px; border-radius: 2px; background: var(--domaine, var(--texte-2)); }
.point { display: inline-block; flex: none; width: 6px; height: 6px; border-radius: 50%; background: var(--rouge); }
.anneau { display: inline-block; flex: none; width: 6px; height: 6px; border-radius: 50%; border: 1.5px solid var(--texte); }

/* Boutons et champs */
.cuvette { padding: 4px; border-radius: 14px; background: var(--graphite); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.8); }
.cuvette-large { margin-top: 14px; }
.bouton { min-height: 48px; padding: 0 18px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 10px; background: var(--console); color: var(--texte); font-size: calc(15 * var(--px)); font-weight: 600; text-decoration: none; box-shadow: var(--ombre-bouton); }
.bouton-allume { min-height: 52px; padding: 0 22px; display: flex; align-items: center; justify-content: center; border: 0; border-radius: 10px; background: var(--ambre); color: var(--graphite); font-stretch: 75%; font-weight: 800; font-size: 23px; letter-spacing: 0.03em; text-decoration: none; box-shadow: var(--ombre-allume); }
.bouton-allume:active { background: var(--ambre-appuye); box-shadow: inset 0 3px 6px rgba(0, 0, 0, 0.35); }
.bouton-contour { min-height: 44px; display: flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid var(--contour); border-radius: 10px; background: transparent; color: var(--texte); font-size: calc(14 * var(--px)); font-weight: 600; text-decoration: none; }
.bouton-large { width: 100%; margin-top: 10px; }
.cuvette .bouton-large { margin-top: 0; }
.bouton-carre { flex: none; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border: 0; border-radius: 10px; background: var(--panneau); color: var(--texte); text-decoration: none; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05); }
.bouton-petit { flex: none; min-height: 36px; padding: 0 12px; font-size: 13px; }
.bouton:disabled, .bouton-allume:disabled { opacity: 0.6; }
.filtre { min-height: 44px; padding: 0 10px; display: inline-flex; align-items: center; gap: 6px; border: 0; border-radius: 9px; background: var(--panneau); color: var(--texte); font-size: calc(14 * var(--px)); font-weight: 600; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05); }
.filtre[aria-pressed='true'] { background: var(--scotch); color: var(--graphite); font-weight: 700; box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.15); }
.pastille { flex: none; width: 7px; height: 7px; border-radius: 50%; background: var(--domaine); }
.champ { width: 100%; min-height: 54px; padding: 0 16px; border: 1px solid var(--contour); border-radius: 10px; background: var(--panneau); color: var(--texte); font-size: 18px; caret-color: var(--ambre); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.45); }
.erreur { margin-top: 10px; color: var(--rouge-texte); font-size: calc(14 * var(--px)); }

/* Messages (« Carte terminée. » avec Annuler…) */
.messages { position: fixed; left: 16px; right: 16px; bottom: calc(max(env(safe-area-inset-bottom), 8px) + 64px); z-index: 30; display: flex; justify-content: center; pointer-events: none; }
.message { pointer-events: auto; display: flex; align-items: center; gap: 12px; max-width: 420px; padding: 10px 10px 10px 14px; border-radius: 12px; background: var(--scotch); color: var(--graphite); font-size: calc(14 * var(--px)); font-weight: 600; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5); }
.message-action { flex: none; min-height: 44px; padding: 0 12px; border: 0; border-radius: 8px; background: var(--graphite); color: var(--scotch); font-weight: 700; }

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation: none !important; transition: none !important; }
}
```

- [ ] **Step 5: Write the DOM helper, the texts and the navigation**

`site/js/lib/dom.js` :

```js
// Construction du DOM sans innerHTML : le texte passe toujours par textContent (titres de cartes,
// descriptions et événements viennent de Trello et de Google). Aucun style en ligne (CSP) : les valeurs
// variables passent par des propriétés CSS (option vars).

export function h(tag, props = {}, ...enfants) {
  const el = document.createElement(tag);
  for (const [cle, valeur] of Object.entries(props ?? {})) {
    if (valeur === null || valeur === undefined || valeur === false) continue;
    if (cle === 'class') el.className = valeur;
    else if (cle === 'vars') for (const [nom, v] of Object.entries(valeur)) el.style.setProperty(nom, v);
    else if (cle === 'dataset') Object.assign(el.dataset, valeur);
    else if (cle.startsWith('on')) el.addEventListener(cle.slice(2), valeur);
    else el.setAttribute(cle, valeur === true ? '' : String(valeur));
  }
  ajouter(el, enfants);
  return el;
}

function ajouter(el, enfants) {
  for (const enfant of enfants) {
    if (enfant === null || enfant === undefined || enfant === false) continue;
    if (Array.isArray(enfant)) ajouter(el, enfant);
    else el.append(enfant instanceof Node ? enfant : String(enfant));
  }
}

const NS = 'http://www.w3.org/2000/svg';
const ICONES = {
  aujourdhui: [['path', { d: 'M6 4v16M12 4v16M18 4v16' }], ['rect', { x: 4, y: 13, width: 4, height: 3, rx: 1, fill: 'currentColor' }], ['rect', { x: 10, y: 7, width: 4, height: 3, rx: 1, fill: 'currentColor' }], ['rect', { x: 16, y: 10, width: 4, height: 3, rx: 1, fill: 'currentColor' }]],
  agenda: [['rect', { x: 3.5, y: 5, width: 17, height: 15, rx: 2 }], ['path', { d: 'M3.5 10h17M8 3v4M16 3v4' }]],
  taches: [['path', { d: 'M4 6.5l1.6 1.6 2.9-2.9M4 12.5l1.6 1.6 2.9-2.9M4 18.5l1.6 1.6 2.9-2.9M11.5 7h8.5M11.5 13h8.5M11.5 19h8.5' }]],
  moi: [['circle', { cx: 12, cy: 8.5, r: 3.5 }], ['path', { d: 'M5 20c1.2-3.6 4-5.5 7-5.5s5.8 1.9 7 5.5' }]],
  plus: [['path', { d: 'M12 5v14M5 12h14' }]],
  micro: [['rect', { x: 9, y: 3, width: 6, height: 11, rx: 3 }], ['path', { d: 'M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21' }]],
  gauche: [['path', { d: 'M15 5l-7 7 7 7' }]],
  droite: [['path', { d: 'M9 5l7 7-7 7' }]],
  fermer: [['path', { d: 'M6 6l12 12M18 6L6 18' }]],
  externe: [['path', { d: 'M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5' }]],
  ferie: [['rect', { x: 3.5, y: 5, width: 17, height: 15, rx: 2 }], ['path', { d: 'M3.5 10h17M9 14l6 4M15 14l-6 4' }]],
};

export function icone(nom, taille = 22, epaisseur = 1.75) {
  const svg = document.createElementNS(NS, 'svg');
  const attributs = { width: taille, height: taille, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': epaisseur, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true', focusable: 'false' };
  for (const [cle, valeur] of Object.entries(attributs)) svg.setAttribute(cle, String(valeur));
  for (const [tag, attrs] of ICONES[nom]) {
    const trait = document.createElementNS(NS, tag);
    for (const [cle, valeur] of Object.entries(attrs)) trait.setAttribute(cle, String(valeur));
    svg.append(trait);
  }
  return svg;
}

// Texte brut avec ses adresses http(s) en liens ; toute autre « adresse » (javascript:…) reste du texte
export function texteAvecLiens(texte) {
  const morceaux = [];
  let depuis = 0;
  for (const m of texte.matchAll(/https?:\/\/[^\s<>"')\]]+/g)) {
    const adresse = m[0].replace(/[.,;:!?]+$/, '');
    morceaux.push(texte.slice(depuis, m.index), h('a', { href: adresse, target: '_blank', rel: 'noopener noreferrer' }, adresse));
    depuis = m.index + adresse.length;
  }
  morceaux.push(texte.slice(depuis));
  return morceaux.filter((x) => x !== '');
}

// Angle stable pour un tampon ou un scotch : la même clé penche toujours pareil
export function angle(cle, max = 8) {
  let n = 0;
  for (const c of String(cle)) n = (n * 31 + c.codePointAt(0)) | 0;
  return `${((Math.abs(n) % (max * 20)) / 10 - max).toFixed(1)}deg`;
}
```

`site/js/lib/textes.js` :

```js
// Les textes de l'app : CONTENU.md (validé par Colin), SPEC § 13 et les textes nouveaux du plan 3.
// Junior tutoie, phrases courtes, aucune pique sur les médians, la sécurité ou les erreurs.

const pluriel = (n, un, plusieurs) => (n === 1 ? un : plusieurs);

export const T = {
  app: 'Jarvis Jr.',
  onglets: { aujourdhui: "Aujourd'hui", agenda: 'Agenda', taches: 'Tâches', moi: 'Moi', noter: 'Noter quelque chose' },
  connexion: {
    titre: 'JARVIS JR.',
    sousTitre: 'Accès réservé à Colin.',
    champ: 'Mot de passe',
    bouton: 'Entrer',
    expiree: 'Ta session a expiré. Reconnecte-toi.',
    reseau: 'Pas de réseau. Réessaie quand ça revient.',
    adresse: 'jr.qyris.fr',
  },
  etats: {
    horsLigne: (age) => `Hors ligne · données d'il y a ${age}`,
    vide: 'Pas encore de données. Il me faut du réseau une première fois.',
    serveur: 'Erreur du serveur. Réessaie dans un instant.',
    trello: (age) => `Trello ne répond pas. Tes tâches s'affichent telles qu'il y a ${age}.`,
    googleIndisponible: (age) => `Google ne répond pas. Ton agenda s'affiche tel qu'il y a ${age}.`,
    googleDeconnecte: "Google m'a déconnecté de ton agenda. Reconnecte-le, ça prend 30 secondes.",
    reconnecter: 'Reconnecter',
    file: (n) => pluriel(n, '1 note attend le réseau. Elle partira toute seule.', `${n} notes attendent le réseau. Elles partiront toutes seules.`),
    tirer: 'Tire pour actualiser',
    relacher: 'Relâche pour actualiser',
    actualisation: 'Actualisation…',
  },
  aujourdhui: {
    ecranConduite: 'Écran de conduite',
    salutations: { matin: 'Bonjour Colin.', apresMidi: 'Bon après-midi.', soir: 'Bonsoir.', nuit: 'Encore debout ?' },
    junior: 'Junior · stagiaire',
    enScene: 'EN SCÈNE',
    standby: 'STANDBY',
    proposition: 'PROPOSITION',
    entracte: 'Entracte',
    libres: (duree) => `${duree} libres`,
    rien: "Rien pour l'instant",
    nbCours: (n) => pluriel(n, '1 cours', `${n} cours`),
    fini: "Plus de cours aujourd'hui",
    finDans: 'fin dans',
    dans: 'dans',
    demain: 'demain',
    rienDePrevu: 'Rien de prévu.',
    agendaNonConnecte: 'Agenda pas encore connecté. Ça se fait dans Moi.',
    aFaire: 'à faire',
    enRetard: (n) => `${n} en retard`,
    median: (n) => (n === 0 ? "médian aujourd'hui" : `médian J-${n}`),
    tranche: (nom, n, retard, median) => `${nom} : ${n} à faire${retard > 0 ? `, ${retard} en retard` : ''}${median === null ? '' : `, médian ${median === 0 ? "aujourd'hui" : `dans ${median} jours`}`}`,
    go: 'GO',
    goLegende: 'Je fais quoi maintenant ?',
    goAria: 'GO, je fais quoi maintenant ?',
    goEteint: 'GO, éteint pendant le cours',
    conduite: 'Conduite du jour',
    aFaireTitre: 'À faire',
    joue: 'JOUÉ',
    jusqua: (heure) => `jusqu'à ${heure}`,
    perso: 'Perso',
    journee: 'Toute la journée',
    groupes: { retard: 'En retard', aujourdhui: "Aujourd'hui", urgent: 'Urgent' },
    rienDUrgent: "Rien d'urgent. Profite, ou jette un œil à l'onglet Tâches.",
  },
  phrases: {
    generales: [
      "Debout. Le café ne se fera pas tout seul, et moi je n'ai pas de mains.",
      "J'ai rangé ta journée. La vivre, c'est ton job.",
      'Jarvis bosse sur le Mac, moi dans ta poche. Toi, tu bosses quand ?',
      'Je suis Stagiaire, et je connais déjà ton emploi du temps mieux que toi.',
    ],
    lundi: "Lundi. Courage, je l'ai dit pour nous deux.",
    chargee: "Journée chargée. Moi je ne dors jamais, toi tu n'auras pas le temps.",
    pasAvant13h: 'Aucun cours avant 13 h. Je sais que tu dors encore, je ne dirai rien à personne.',
    rienEnRetard: "Rien en retard. J'ai vérifié deux fois, ça ne te ressemble pas.",
    enRetard: (n) => pluriel(n, "1 tâche en retard. Elle ne va pas se faire seule, j'ai essayé.", `${n} tâches en retard. Elles ne vont pas se faire seules, j'ai essayé.`),
    median: (n) => `${n === 0 ? "Médian aujourd'hui" : n === 1 ? 'Médian demain' : `Médian dans ${n} jours`}. Je ne panique pas, donc toi non plus.`,
  },
  tirage: {
    cherche: 'Je cherche…',
    trouve: (duree, type) => `Je t'ai trouvé ${duree} avant ton ${type === 'TD' || type === 'TP' ? type : 'cours'}.`,
    libre: "Libre jusqu'à 23 h.",
    tard: 'Il est tard, mais puisque tu insistes :',
    relances: ["D'accord, une autre.", "Encore ? Tu cherches la tâche qui se fait toute seule. Elle n'existe pas.", "Dernière proposition. Après, c'est moi le patron.", "J'ai choisi. On y va ?"],
    coursImminent: (duree) => `Ton cours commence dans ${duree}. Le meilleur choix : y aller.`,
    vide: 'Plus rien dans ta to-do. Génie ou amnésie ? Ajoute une tâche, ou repose-toi.',
    terminer: (titre) => `Terminer : ${titre}`,
    ligneAvant: (duree, uv) => `${duree} avant ${uv}`.toUpperCase(),
    ligneLibre: "LIBRE JUSQU'À 23 H",
    ligneTard: 'IL EST TARD',
    enRetard: 'en retard',
    enCours: 'en cours',
    cestParti: "C'est parti",
    autreChose: 'Autre chose',
    retour: 'Retour',
    parti: "C'est parti. Coche l'étape quand c'est fait, je note.",
  },
  taches: {
    titre: 'Tâches',
    compte: (n) => pluriel(n, '1 CARTE', `${n} CARTES`),
    tout: 'Tout',
    filtrer: 'Filtrer par tableau',
    groupes: { en_cours: 'En cours', a_faire: 'À faire', en_attente: 'En attente', fait: 'Fait' },
    voirFaites: 'Voir les cartes faites',
    masquerFaites: 'Masquer les cartes faites',
    aucune: 'Aucune carte ici.',
    attente: 'en attente du réseau',
  },
  carte: {
    retour: 'Retour',
    urgent: 'URGENT',
    fait: 'FAIT',
    cocher: (nom) => `Cocher : ${nom}`,
    decocher: (nom) => `Décocher : ${nom}`,
    terminer: 'Terminer',
    deplacer: 'Déplacer',
    deplacerVers: 'Déplacer vers',
    ouvrirTrello: 'Ouvrir dans Trello',
    terminee: 'Carte terminée.',
    annuler: 'Annuler',
    introuvable: "Cette carte n'existe plus dans Trello.",
  },
  capture: {
    titre: 'Noter',
    champ: 'Une tâche, un rappel…',
    champAria: 'Une tâche, un rappel',
    dicter: 'Tu peux dicter avec le micro du clavier.',
    domaine: 'Domaine',
    urgent: 'Urgent',
    echeance: 'Échéance',
    heure: 'Heure (facultatif)',
    effacer: "Effacer l'échéance",
    ajouter: 'Ajouter',
    fermer: 'Fermer',
    ajoute: (nom) => `Ajouté dans ${nom} · À faire. Jarvis le verra à ta prochaine session.`,
    horsLigne: "Pas de réseau. Je l'envoie dès que ça revient.",
    choisirDomaine: 'Choisis un domaine.',
    titreInvalide: 'Il me faut un titre (300 caractères au plus).',
  },
  agenda: {
    titre: 'Agenda',
    affichage: 'Affichage',
    jour: 'Jour',
    semaine: 'Semaine',
    precedente: 'Semaine précédente',
    suivante: 'Semaine suivante',
    veille: 'Jour précédent',
    lendemain: 'Jour suivant',
    aujourdhui: "Aujourd'hui",
    rien: 'Rien de prévu.',
    chargement: 'Chargement…',
    pasChargee: "Hors ligne : cette semaine n'est pas encore chargée.",
    plage: (debut, fin) => `${debut} à ${fin}`,
    journee: 'Toute la journée',
    perso: 'PERSO',
    nonConnecte: 'Agenda pas encore connecté. Ça se fait dans Moi.',
  },
  moi: {
    titre: 'Moi',
    google: 'Google Agenda',
    connecte: 'Agenda connecté',
    nonConnecte: 'Agenda pas connecté',
    connecter: 'Connecter Google Agenda',
    reconnecter: 'Reconnecter',
    connecteMessage: 'Agenda connecté.',
    erreurGoogle: "La connexion à Google n'a pas abouti. Réessaie.",
    session: 'Session',
    deconnecter: 'Se déconnecter',
    deconnecterTout: 'Déconnecter tous les appareils',
    confirmerTout: 'Déconnecter tous les appareils ?',
    confirmerFile: (n) => pluriel(n, '1 note attend encore le réseau. Te déconnecter quand même ?', `${n} notes attendent encore le réseau. Te déconnecter quand même ?`),
    app: "L'app",
    version: (v) => `Version ${v}`,
    confidentialite: 'Confidentialité',
    reseau: 'Pas de réseau. Réessaie quand ça revient.',
  },
};
```

`site/js/ecrans/attente.js` :

```js
// Écran pas encore construit : remplacé tâche par tâche, retiré à la tâche 10.
import { h } from '../lib/dom.js';
import { T } from '../lib/textes.js';

export function rendre(app, route) {
  return h('div', {}, h('h1', { class: 'titre-ecran' }, T.onglets[route.nom] ?? T.onglets.taches));
}
```

`site/js/app.js` (version de départ, remplacée à la tâche 5) :

```js
// Jarvis Jr. : démarrage et navigation entre les écrans (les données arrivent à la tâche 5).
import { h, icone } from './lib/dom.js';
import { T } from './lib/textes.js';
import * as attente from './ecrans/attente.js';

const ECRANS = { aujourdhui: attente, agenda: attente, taches: attente, carte: attente, moi: attente };
const ONGLETS = ['aujourdhui', 'agenda', 'taches', 'moi'];

function lireRoute() {
  const [chemin, requete = ''] = location.hash.replace(/^#\/?/, '').split('?');
  const [nom, param = null] = chemin.split('/').filter(Boolean);
  return { nom: ECRANS[nom] ? nom : 'aujourdhui', param, requete: new URLSearchParams(requete) };
}

function rendre() {
  const route = lireRoute();
  document.getElementById('ecran').replaceChildren(ECRANS[route.nom].rendre(null, route));
  for (const a of document.querySelectorAll('.onglet')) {
    if (a.dataset.onglet === (route.nom === 'carte' ? 'taches' : route.nom)) a.setAttribute('aria-current', 'page');
    else a.removeAttribute('aria-current');
  }
}

document.getElementById('onglets').replaceChildren(
  ...ONGLETS.map((nom) => h('a', { href: `#/${nom}`, class: 'onglet', dataset: { onglet: nom } }, icone(nom), h('span', {}, T.onglets[nom]))),
  h('button', { type: 'button', class: 'bouton-plus', 'aria-label': T.onglets.noter }, icone('plus', 22, 2.25)),
);
window.addEventListener('hashchange', rendre);
rendre();
document.documentElement.dataset.pret = '1';
```

- [ ] **Step 6: Run the screen tests**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs`
Expected : `3 réussi(s), 0 échec(s)`. Une ligne « Refused to … because it violates the Content Security Policy » dans un échec veut dire qu'un style ou un script en ligne s'est glissé : le retirer, ne jamais assouplir la CSP.

Puis la suite PHP, inchangée : `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets` → `156 réussi(s), 0 échec(s)`.

- [ ] **Step 7: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add outils/telecharger-polices.py site/index.html site/manifest.webmanifest site/css site/fonts site/js tests/e2e/package.json tests/e2e/package-lock.json tests/e2e/*.mjs && git commit -m "Lot 1 : socle de l'app (page, polices, style de la console, navigation) et tests d'écran Playwright

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---
### Task 4 : heure de Paris et cartes (modules purs)

Deux modules sans DOM ni réseau, testés avec `node --test` : les jours et les heures de Paris (quel que soit le fuseau de l'iPhone), et ce qu'on calcule sur les cartes (retard, groupes, comptes, libellés).

**Files:**
- Create: `site/js/lib/temps.js`, `site/js/lib/cartes.js`
- Test: `tests/js/temps.test.mjs`, `tests/js/cartes.test.mjs`
- Modify: `outils/deploy-ovh.sh` (les tests des modules purs bloquent aussi l'envoi)

**Interfaces:**
- Consumes : rien (modules purs).
- Produces :
  - `temps.js` : `enParis(date)` → `{ annee, mois, jour, heure, minute }` ; `jourParis(date)` → `'AAAA-MM-JJ'` ; `heureParis(date)` → `'HH:MM'` ; `ajouterJours(jour, n)` ; `joursEntre(de, a)` ; `jourSemaine(jour)` (0 = lundi) ; `lundi(jour)` ; `semaineIso(jour)` → `'2026-W42'` ; `lundiDeSemaine(semaine)` ; `dateLongue(jour)` → `'Mercredi 14 octobre'` ; `etiquetteJour(jour)` → `'MER 14'` ; `dateCourte(jour)` → `'mer. 21/10'` ; `jourMois(jour)` → `'06/10'` ; `plageSemaine(lundi)` → `'12 au 18 octobre'` ; `heureTexte('HH:MM')` → `'8 h'`, `'19 h 15'` ; `dureeTexte(minutes)` → `'45 min'`, `'1 h 10'` ; `ageTexte(minutes)` → `"moins d'une minute"`, `'12 min'`, `'2 h'`, `'3 jours'` ; `minutesEntre(a, b)` ; `isoParis(jour, 'HH:MM')` → ISO avec décalage.
  - `cartes.js` : `DOMAINES`, `NOMS` (`Études`…), `SCOTCH` (`ÉTUDES`…), `COLONNES` (`À faire`…) ; `enRetard(carte, maintenant)` ; `echeanceAujourdhui(carte, maintenant)` ; `avancement(carte)` → `'2/4'` ou `null` ; `libelleEcheance(carte, maintenant)` → `{ texte, retard }` ou `null` ; `compteurs(cartes, maintenant)` → `{ qyris: { ouvertes, retard }, … }` ; `groupesAFaire(cartes, maintenant)` → `{ retard, aujourdhui, urgent }` ; `groupesTaches(cartes, domaine|null)` → `{ en_cours, a_faire, en_attente, fait }` ; `etiquetteCarte(carte)` → `'Études · GE15'` ; `debutTitre(titre)` ; `trouverCarte(cartes, id)`.

- [ ] **Step 1: Write the failing tests**

`tests/js/temps.test.mjs` :

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as t from '../../site/js/lib/temps.js';

test("heure et jour de Paris, quel que soit le fuseau de la machine, heure d'hiver comprise", () => {
  assert.equal(t.heureParis(new Date('2026-10-14T13:45:00Z')), '15:45');
  assert.equal(t.jourParis(new Date('2026-10-14T22:30:00Z')), '2026-10-15');
  assert.equal(t.heureParis(new Date('2026-10-26T07:00:00Z')), '08:00');
  assert.equal(t.heureParis(new Date('2026-10-14T22:00:00Z')), '00:00');
  assert.deepEqual(t.enParis(new Date('2026-10-21T17:15:00Z')), { annee: 2026, mois: 10, jour: 21, heure: 19, minute: 15 });
});

test('jours : écart, jour de la semaine, lundi, semaine ISO (2026 a 53 semaines)', () => {
  assert.equal(t.ajouterJours('2026-10-31', 1), '2026-11-01');
  assert.equal(t.ajouterJours('2026-10-25', 1), '2026-10-26');
  assert.equal(t.joursEntre('2026-10-14', '2026-10-21'), 7);
  assert.equal(t.jourSemaine('2026-10-14'), 2);
  assert.equal(t.jourSemaine('2026-10-18'), 6);
  assert.equal(t.lundi('2026-10-14'), '2026-10-12');
  assert.equal(t.semaineIso('2026-10-14'), '2026-W42');
  assert.equal(t.semaineIso('2026-10-19'), '2026-W43');
  assert.equal(t.semaineIso('2027-01-01'), '2026-W53');
  assert.equal(t.lundiDeSemaine('2026-W43'), '2026-10-19');
  assert.equal(t.lundiDeSemaine('2026-W01'), '2025-12-29');
});

test('textes : dates, semaines, heures, durées, âges', () => {
  assert.equal(t.dateLongue('2026-10-14'), 'Mercredi 14 octobre');
  assert.equal(t.dateLongue('2026-11-01'), 'Dimanche 1er novembre');
  assert.equal(t.etiquetteJour('2026-10-14'), 'MER 14');
  assert.equal(t.dateCourte('2026-10-21'), 'mer. 21/10');
  assert.equal(t.jourMois('2026-10-06'), '06/10');
  assert.equal(t.plageSemaine('2026-10-12'), '12 au 18 octobre');
  assert.equal(t.plageSemaine('2026-10-26'), '26 octobre au 1er novembre');
  assert.equal(t.heureTexte('08:00'), '8 h');
  assert.equal(t.heureTexte('19:15'), '19 h 15');
  assert.deepEqual([8, 45, 60, 70, 140, -3].map(t.dureeTexte), ['8 min', '45 min', '1 h', '1 h 10', '2 h 20', '0 min']);
  assert.deepEqual([0.4, 12, 125, 3000].map(t.ageTexte), ["moins d'une minute", '12 min', '2 h', '2 jours']);
  assert.equal(t.minutesEntre(new Date('2026-10-14T13:45:00Z'), new Date('2026-10-14T14:30:00Z')), 45);
});

test("isoParis : une échéance saisie à Paris garde son heure, avant et après le passage à l'heure d'hiver", () => {
  assert.equal(t.isoParis('2026-10-20', '18:00'), '2026-10-20T18:00:00+02:00');
  assert.equal(t.isoParis('2026-10-27', '08:30'), '2026-10-27T08:30:00+01:00');
  assert.equal(new Date(t.isoParis('2026-10-27', '18:00')).toISOString(), '2026-10-27T17:00:00.000Z');
});
```

`tests/js/cartes.test.mjs` :

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as c from '../../site/js/lib/cartes.js';

const MAINTENANT = new Date('2026-10-14T15:45:00+02:00');

function carte(autres = {}) {
  return {
    id: 'ca01', domaine: 'etudes', colonne: 'a_faire', position: 1000, titre: 'MT23 : préparer le médian', description: '', url: '',
    echeance: null, echeance_faite: false, urgent: false, etiquettes: ['MT23'], checklists: [], derniere_activite: '2026-10-13T10:00:00+02:00', ...autres,
  };
}

test("retard : échéance passée, pas faite, hors de Fait ; une date seule (18 h) d'aujourd'hui n'est pas encore en retard", () => {
  assert.equal(c.enRetard(carte({ echeance: '2026-10-06T08:30:00+02:00' }), MAINTENANT), true);
  assert.equal(c.enRetard(carte({ echeance: '2026-10-06T08:30:00+02:00', echeance_faite: true }), MAINTENANT), false);
  assert.equal(c.enRetard(carte({ echeance: '2026-10-06T08:30:00+02:00', colonne: 'fait' }), MAINTENANT), false);
  const ceSoir = carte({ echeance: '2026-10-14T18:00:00+02:00' });
  assert.equal(c.enRetard(ceSoir, MAINTENANT), false);
  assert.equal(c.echeanceAujourdhui(ceSoir, MAINTENANT), true);
  assert.equal(c.enRetard(ceSoir, new Date('2026-10-14T18:01:00+02:00')), true);
});

test("libellés : « Échéance mer. 21/10 à 19 h 15 », « Échéance mer. 21/10 » pour une date seule, « En retard depuis le 06/10 »", () => {
  assert.deepEqual(c.libelleEcheance(carte({ echeance: '2026-10-21T19:15:00+02:00' }), MAINTENANT), { texte: 'Échéance mer. 21/10 à 19 h 15', retard: false });
  assert.deepEqual(c.libelleEcheance(carte({ echeance: '2026-10-21T18:00:00+02:00' }), MAINTENANT), { texte: 'Échéance mer. 21/10', retard: false });
  assert.deepEqual(c.libelleEcheance(carte({ echeance: '2026-10-06T08:30:00+02:00' }), MAINTENANT), { texte: 'En retard depuis le 06/10', retard: true });
  assert.equal(c.libelleEcheance(carte(), MAINTENANT), null);
});

test('avancement, étiquette, début du titre', () => {
  const etapes = { id: 'cc', nom: 'Étapes', elements: [{ id: 'e1', nom: 'a', coche: true }, { id: 'e2', nom: 'b', coche: false }] };
  assert.equal(c.avancement(carte({ checklists: [etapes, { ...etapes, id: 'cd' }] })), '2/4');
  assert.equal(c.avancement(carte()), null);
  assert.equal(c.etiquetteCarte(carte()), 'Études · MT23');
  assert.equal(c.etiquetteCarte(carte({ domaine: 'perso', etiquettes: [] })), 'Perso');
  assert.equal(c.debutTitre('Formation 2 : relire le deck'), 'Formation 2');
  assert.equal(c.debutTitre('Supprimer les anciens sites'), 'Supprimer les anciens sites');
});

test("comptes par domaine (À faire et En cours seulement) et groupes d'Aujourd'hui", () => {
  const cartes = [
    carte({ id: 'a', domaine: 'qyris', echeance: '2026-10-06T08:30:00+02:00' }),
    carte({ id: 'b', domaine: 'qyris', colonne: 'en_cours' }),
    carte({ id: 'c', domaine: 'qyris', colonne: 'en_attente', echeance: '2026-10-01T18:00:00+02:00' }),
    carte({ id: 'd', domaine: 'asso', urgent: true }),
    carte({ id: 'e', domaine: 'etudes', echeance: '2026-10-14T18:00:00+02:00', urgent: true }),
    carte({ id: 'f', domaine: 'perso', echeance: '2026-10-05T18:00:00+02:00' }),
    carte({ id: 'g', domaine: 'perso', colonne: 'fait', echeance: '2026-10-01T18:00:00+02:00' }),
  ];
  assert.deepEqual(c.compteurs(cartes, MAINTENANT), {
    qyris: { ouvertes: 2, retard: 1 }, asso: { ouvertes: 1, retard: 0 }, etudes: { ouvertes: 1, retard: 0 }, perso: { ouvertes: 1, retard: 1 },
  });
  const g = c.groupesAFaire(cartes, MAINTENANT);
  assert.deepEqual([g.retard.map((x) => x.id), g.aujourdhui.map((x) => x.id), g.urgent.map((x) => x.id)], [['f', 'a'], ['e'], ['d']]);
  const t = c.groupesTaches(cartes, 'qyris');
  assert.deepEqual([t.en_cours.length, t.a_faire.length, t.en_attente.length, t.fait.length], [1, 1, 1, 0]);
  assert.equal(c.groupesTaches(cartes, null).fait.length, 1);
  assert.equal(c.trouverCarte(cartes, 'd').domaine, 'asso');
  assert.equal(c.trouverCarte(cartes, 'zz'), null);
});
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node --test 'tests/js/*.test.mjs'`
Expected : FAIL, `Cannot find module '…/site/js/lib/temps.js'`.

- [ ] **Step 3: Implement**

`site/js/lib/temps.js` :

```js
// Heure de Paris, quel que soit le fuseau de l'iPhone. Les dates du serveur portent leur décalage
// (2026-10-21T19:15:00+02:00) ; un jour est un texte AAAA-MM-JJ, compté sans fuseau.

const FUSEAU = 'Europe/Paris';
const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const format = new Intl.DateTimeFormat('en-GB', {
  timeZone: FUSEAU, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
});
const deux = (n) => String(n).padStart(2, '0');

export function enParis(date) {
  const p = {};
  for (const { type, value } of format.formatToParts(date)) p[type] = value;
  return { annee: Number(p.year), mois: Number(p.month), jour: Number(p.day), heure: Number(p.hour), minute: Number(p.minute) };
}

export function jourParis(date) {
  const p = enParis(date);
  return `${p.annee}-${deux(p.mois)}-${deux(p.jour)}`;
}

export function heureParis(date) {
  const p = enParis(date);
  return `${deux(p.heure)}:${deux(p.minute)}`;
}

function versUtc(jour) {
  const [a, m, j] = jour.split('-').map(Number);
  return Date.UTC(a, m - 1, j);
}

function depuisUtc(ms) {
  const d = new Date(ms);
  return `${d.getUTCFullYear()}-${deux(d.getUTCMonth() + 1)}-${deux(d.getUTCDate())}`;
}

export const ajouterJours = (jour, n) => depuisUtc(versUtc(jour) + n * 86400000);
export const joursEntre = (de, a) => Math.round((versUtc(a) - versUtc(de)) / 86400000);
export const jourSemaine = (jour) => (new Date(versUtc(jour)).getUTCDay() + 6) % 7;
export const lundi = (jour) => ajouterJours(jour, -jourSemaine(jour));

// La semaine ISO est celle de son jeudi
export function semaineIso(jour) {
  const jeudi = ajouterJours(jour, 3 - jourSemaine(jour));
  const annee = Number(jeudi.slice(0, 4));
  return `${annee}-W${deux(Math.floor(joursEntre(`${annee}-01-01`, jeudi) / 7) + 1)}`;
}

// Le 4 janvier est toujours dans la semaine 1
export function lundiDeSemaine(semaine) {
  return ajouterJours(lundi(`${semaine.slice(0, 4)}-01-04`), (Number(semaine.slice(6)) - 1) * 7);
}

const numeroDuJour = (jour) => (Number(jour.slice(8)) === 1 ? '1er' : String(Number(jour.slice(8))));
const mois = (jour) => MOIS[Number(jour.slice(5, 7)) - 1];

export function dateLongue(jour) {
  const nom = JOURS[jourSemaine(jour)];
  return `${nom[0].toUpperCase()}${nom.slice(1)} ${numeroDuJour(jour)} ${mois(jour)}`;
}

export const etiquetteJour = (jour) => `${JOURS[jourSemaine(jour)].slice(0, 3).toUpperCase()} ${Number(jour.slice(8))}`;
export const jourMois = (jour) => `${jour.slice(8)}/${jour.slice(5, 7)}`;
export const dateCourte = (jour) => `${JOURS[jourSemaine(jour)].slice(0, 3)}. ${jourMois(jour)}`;

export function plageSemaine(premier) {
  const dernier = ajouterJours(premier, 6);
  const debut = mois(premier) === mois(dernier) ? numeroDuJour(premier) : `${numeroDuJour(premier)} ${mois(premier)}`;
  return `${debut} au ${numeroDuJour(dernier)} ${mois(dernier)}`;
}

export function heureTexte(hhmm) {
  const [h, m] = hhmm.split(':').map(Number);
  return m === 0 ? `${h} h` : `${h} h ${deux(m)}`;
}

export function dureeTexte(minutes) {
  const m = Math.max(0, Math.round(minutes));
  if (m < 60) return `${m} min`;
  return m % 60 === 0 ? `${Math.floor(m / 60)} h` : `${Math.floor(m / 60)} h ${deux(m % 60)}`;
}

export function ageTexte(minutes) {
  if (minutes < 1) return "moins d'une minute";
  if (minutes < 60) return `${Math.floor(minutes)} min`;
  if (minutes < 48 * 60) return `${Math.floor(minutes / 60)} h`;
  return `${Math.floor(minutes / 1440)} jours`;
}

export const minutesEntre = (a, b) => (b.getTime() - a.getTime()) / 60000;

// Un jour et une heure de Paris en ISO avec le décalage de ce jour-là (carte ajoutée hors ligne)
export function isoParis(jour, hhmm) {
  const [a, mo, j] = jour.split('-').map(Number);
  const [h, mi] = hhmm.split(':').map(Number);
  const voulu = Date.UTC(a, mo - 1, j, h, mi);
  let essai = voulu;
  for (let i = 0; i < 2; i++) {
    const p = enParis(new Date(essai));
    essai += voulu - Date.UTC(p.annee, p.mois - 1, p.jour, p.heure, p.minute);
  }
  const decalage = Math.round((voulu - essai) / 60000);
  const absolu = Math.abs(decalage);
  return `${jour}T${deux(h)}:${deux(mi)}:00${decalage < 0 ? '-' : '+'}${deux(Math.floor(absolu / 60))}:${deux(absolu % 60)}`;
}
```

`site/js/lib/cartes.js` :

```js
// Les cartes Trello telles que le serveur les envoie (SPEC § 5.5) : retards, groupes, comptes, libellés.

import { dateCourte, heureParis, heureTexte, jourMois, jourParis } from './temps.js';

export const DOMAINES = ['qyris', 'asso', 'etudes', 'perso'];
export const NOMS = { qyris: 'QYRIS', asso: 'Asso', etudes: 'Études', perso: 'Perso' };
export const SCOTCH = { qyris: 'QYRIS', asso: 'ASSO', etudes: 'ÉTUDES', perso: 'PERSO' };
export const COLONNES = { a_faire: 'À faire', en_cours: 'En cours', en_attente: 'En attente', fait: 'Fait' };

const active = (carte) => carte.colonne === 'a_faire' || carte.colonne === 'en_cours';
const aEcheance = (carte) => carte.colonne !== 'fait' && carte.echeance !== null && !carte.echeance_faite;

export const enRetard = (carte, maintenant) => aEcheance(carte) && new Date(carte.echeance) < maintenant;

export function echeanceAujourdhui(carte, maintenant) {
  return aEcheance(carte) && !enRetard(carte, maintenant) && jourParis(new Date(carte.echeance)) === jourParis(maintenant);
}

export function avancement(carte) {
  let faits = 0;
  let total = 0;
  for (const liste of carte.checklists) {
    for (const element of liste.elements) {
      total++;
      if (element.coche) faits++;
    }
  }
  return total === 0 ? null : `${faits}/${total}`;
}

// Une échéance à 18 h 00 pile est une date seule (convention de trello.py et du serveur)
export function libelleEcheance(carte, maintenant) {
  if (carte.echeance === null) return null;
  const quand = new Date(carte.echeance);
  const jour = jourParis(quand);
  if (enRetard(carte, maintenant)) return { texte: `En retard depuis le ${jourMois(jour)}`, retard: true };
  const heure = heureParis(quand);
  return { texte: `Échéance ${dateCourte(jour)}${heure === '18:00' ? '' : ` à ${heureTexte(heure)}`}`, retard: false };
}

// Cartes À faire et En cours par domaine, et combien sont en retard (En attente ne dépend pas de Colin)
export function compteurs(cartes, maintenant) {
  const comptes = Object.fromEntries(DOMAINES.map((d) => [d, { ouvertes: 0, retard: 0 }]));
  for (const carte of cartes) {
    if (!active(carte) || !comptes[carte.domaine]) continue;
    comptes[carte.domaine].ouvertes++;
    if (enRetard(carte, maintenant)) comptes[carte.domaine].retard++;
  }
  return comptes;
}

// « À faire » d'Aujourd'hui : En retard, Aujourd'hui (échéance plus tard dans la journée), puis Urgent
export function groupesAFaire(cartes, maintenant) {
  const groupes = { retard: [], aujourdhui: [], urgent: [] };
  for (const carte of cartes) {
    if (!active(carte)) continue;
    if (enRetard(carte, maintenant)) groupes.retard.push(carte);
    else if (echeanceAujourdhui(carte, maintenant)) groupes.aujourdhui.push(carte);
    else if (carte.urgent) groupes.urgent.push(carte);
  }
  const parEcheance = (a, b) => new Date(a.echeance) - new Date(b.echeance);
  groupes.retard.sort(parEcheance);
  groupes.aujourdhui.sort(parEcheance);
  return groupes;
}

// Onglet Tâches : un domaine (ou tous si null), dans l'ordre de Trello
export function groupesTaches(cartes, domaine) {
  const groupes = { en_cours: [], a_faire: [], en_attente: [], fait: [] };
  for (const carte of cartes) {
    if ((domaine === null || carte.domaine === domaine) && groupes[carte.colonne]) groupes[carte.colonne].push(carte);
  }
  return groupes;
}

export const etiquetteCarte = (carte) => [NOMS[carte.domaine], ...carte.etiquettes.slice(0, 1)].join(' · ');

export function debutTitre(titre) {
  const i = titre.indexOf(':');
  return (i > 0 ? titre.slice(0, i) : titre).trim();
}

export const trouverCarte = (cartes, id) => cartes.find((c) => c.id === id) ?? null;
```

`outils/deploy-ovh.sh`, à la fin de l'étape « 1/6 Tests » (après le bloc `if ! php … fi`) :

```bash
if ! (cd "$PROJET" && node --test 'tests/js/*.test.mjs') 2>&1 | "$FILTRE"; then
    echo "Tests des modules de l'app en échec : rien n'est envoyé."
    exit 1
fi
```

- [ ] **Step 4: Run the tests**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node --test 'tests/js/*.test.mjs'`
Expected : `pass 8`, `fail 0`.

Puis : `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets` → `156 réussi(s), 0 échec(s)` (`test_deploiement.php` lit le script de mise en ligne).

- [ ] **Step 5: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add site/js/lib/temps.js site/js/lib/cartes.js tests/js outils/deploy-ovh.sh && git commit -m "Lot 1 : heure de Paris et calculs sur les cartes (modules purs, node --test), testés avant chaque mise en ligne

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---
### Task 5 : les données sur l'iPhone (connexion, copie locale, file des écritures, bandeaux d'état)

Tout ce qui relie l'app au serveur : appels à l'API, copie dans IndexedDB, écritures appliquées tout de suite puis envoyées dans l'ordre (même `id_client` à chaque renvoi), écran de connexion, bandeaux « Hors ligne », « Trello ne répond pas », « Google m'a déconnecté », messages, tirer pour actualiser. Les écrans restent ceux d'attente : ils arrivent aux tâches 6 à 10.

**Files:**
- Create: `site/js/api.js`, `site/js/stockage.js`, `site/js/etat.js`, `site/js/file.js`, `site/js/ecrans/commun.js`, `site/js/ecrans/connexion.js`
- Replace: `site/js/app.js`
- Modify: `site/css/app.css` (connexion, à la fin)
- Test: `tests/js/etat.test.mjs`, `tests/e2e/test_donnees.mjs`

**Interfaces:**
- Consumes : `h`, `icone`, `angle` (`dom.js`), `T`, `ageTexte`, `minutesEntre` (`temps.js`) ; routes `GET donnees`, `POST connexion`, `POST deconnexion`, `POST deconnexion-partout` et toutes les écritures (plans 1 et 2).
- Produces :
  - `api.js` : `appeler(methode, route, { corps, parametres, delai })` → la réponse JSON (`ok: true`) ; lève `ErreurApi` (`statut`, `code`, `message` du serveur) ou `ErreurReseau`.
  - `stockage.js` : `lire(magasin, cle)`, `ecrire(magasin, cle, valeur)`, `supprimer(magasin, cle)`, `tout(magasin)`, `vider()` ; magasins `donnees` (clé `derniere`), `agenda` (clé : la semaine), `file` (clé : `id_client`).
  - `etat.js` : `vue(donnees, file)`, `appliquerEcriture(cartes, e)`, `integrer(donnees, e, reponse)`, `remplacerIdLocal(file, idLocal, idReel)`, `retirerEcriture(file, id)`, `decision(statut)` → `'retirer'`, `'garder'` ou `'connexion'`, `trierCartes(cartes)`. Une écriture : `{ id_client, route, corps, local, ordre, essais }` ; `corps` part au serveur, `local` sert à l'écran (`local.id` d'une carte ajoutée, `local.echeance_iso`, `local.cree_le` ; `local.carte`, `colonne_avant`, `position_avant`, `echeance_faite_avant` pour Annuler). Les cartes et étapes modifiées en attente portent `attente: true`.
  - `file.js` : `charger()`, `nouvelleEcriture(route, corps, local)`, `garder(e)`, `oublier(id)`, `attendre(id)`, `envoyer(rappels)`.
  - `commun.js` : `regle()`, `voyant(texte, allume, creme)`, `tampon(texte, cle, classe)`, `rendreStatut(conteneur, app)`, `rendreMessage(conteneur, texte, action, fermer)`, `tirerPourActualiser(ecran, indicateur, actualiser)`.
  - `app.js` : l'objet `app` passé à chaque écran : `etat`, `maintenant()`, `vue()`, `aller(hash)`, `retour(defaut)`, `rendre()`, `actualiser(force)`, `ecrire(route, corps, local)` → l'écriture, `annulerTerminer(e)`, `message(texte, { action: { libelle, faire }, delai })`, `connecter(motDePasse)`, `deconnecter(partout)`, `demanderConnexion(expiree)`, `ouvrirCapture(domaine)`, `fermerCapture()`, `rendreCapture()`. Un écran est un module qui exporte `rendre(app, route)` → nœud, et au besoin `apres(app, route, ecran, changement)`, `quitter()`, `occupe()`. `route` : `{ nom, param, requete }` (`requete` : `URLSearchParams` de ce qui suit `?` dans le hash). Les écrans s'enregistrent dans `ECRANS` (et la feuille « Noter » dans `FEUILLES`) en haut de `app.js`.

- [ ] **Step 1: Write the failing tests**

`tests/js/etat.test.mjs` :

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { decision, integrer, remplacerIdLocal, retirerEcriture, vue } from '../../site/js/etat.js';

const carte = (id, domaine, colonne, position, autres = {}) => ({
  id, domaine, colonne, position, titre: id, description: '', url: '', echeance: null, echeance_faite: false, urgent: false,
  etiquettes: [], checklists: [], derniere_activite: '2026-10-13T10:00:00+02:00', ...autres,
});
const etapes = () => ({ id: 'cc1', nom: 'Étapes', elements: [{ id: 'e1', nom: 'Lire', coche: false }, { id: 'e2', nom: 'Écrire', coche: false }] });
const donnees = () => ({
  maintenant: '2026-10-14T15:45:00+02:00',
  trello: { recupere_le: null, erreur: null, cartes: [
    carte('q1', 'qyris', 'a_faire', 1000, { checklists: [etapes()] }),
    carte('q2', 'qyris', 'fait', 3000, { echeance: '2026-10-06T08:30:00+02:00', echeance_faite: true }),
    carte('e1', 'etudes', 'a_faire', 2000, { echeance: '2026-10-21T19:15:00+02:00' }),
  ] },
  agenda: { recupere_le: null, erreur: null, google_connecte: false, jours: {} },
});
const ecriture = (route, corps, local = {}) => ({ id_client: `id-${route}`, route, corps, local, ordre: 1, essais: 0 });

test("vue : les écritures en attente s'appliquent dans l'ordre, marquées « attente », sans toucher la copie", () => {
  const d = donnees();
  const v = vue(d, [ecriture('taches/cocher', { carte: 'q1', element: 'e1', coche: true }), ecriture('taches/terminer', { carte: 'e1' })]);
  const q1 = v.trello.cartes.find((c) => c.id === 'q1');
  assert.deepEqual([q1.checklists[0].elements[0].coche, q1.checklists[0].elements[0].attente, q1.attente], [true, true, true]);
  const e1 = v.trello.cartes.find((c) => c.id === 'e1');
  assert.deepEqual([e1.colonne, e1.echeance_faite, e1.attente], ['fait', true, true]);
  assert.equal(d.trello.cartes[0].checklists[0].elements[0].coche, false);
  assert.equal(d.trello.cartes[2].colonne, 'a_faire');
  assert.equal(vue(null, []), null);
});

test("vue : déplacer en haut d'une colonne, sortir de Fait rouvre l'échéance, ajouter crée la carte locale en haut d'À faire", () => {
  const v = vue(donnees(), [
    ecriture('taches/deplacer', { carte: 'q2', colonne: 'en_cours' }),
    ecriture('taches/ajouter', { domaine: 'qyris', titre: 'Appeler Sébastien', urgent: true, echeance: '2026-10-20' }, { id: 'local-1', echeance_iso: '2026-10-20T18:00:00+02:00', cree_le: '2026-10-14T13:45:00.000Z' }),
  ]);
  const q2 = v.trello.cartes.find((c) => c.id === 'q2');
  assert.deepEqual([q2.colonne, q2.echeance_faite], ['en_cours', false]);
  const nouvelle = v.trello.cartes.find((c) => c.id === 'local-1');
  assert.deepEqual([nouvelle.colonne, nouvelle.urgent, nouvelle.echeance, nouvelle.attente], ['a_faire', true, '2026-10-20T18:00:00+02:00', true]);
  assert.deepEqual(v.trello.cartes.map((c) => c.id), ['local-1', 'q1', 'q2', 'e1']);
});

test("annuler : la carte revient à sa colonne, sa position et son échéance d'avant", () => {
  const v = vue(donnees(), [
    ecriture('taches/terminer', { carte: 'e1' }),
    ecriture('taches/annuler', { id_client_origine: 'id-taches/terminer' }, { carte: 'e1', colonne_avant: 'a_faire', position_avant: 2000, echeance_faite_avant: false }),
  ]);
  const e1 = v.trello.cartes.find((c) => c.id === 'e1');
  assert.deepEqual([e1.colonne, e1.position, e1.echeance_faite], ['a_faire', 2000, false]);
});

test('intégrer : une capture acceptée remplace la carte locale par celle du serveur, plus rien « en attente »', () => {
  const ajout = ecriture('taches/ajouter', { domaine: 'etudes', titre: 'Réviser', urgent: false, echeance: null }, { id: 'local-1', echeance_iso: null, cree_le: 'x' });
  const duServeur = carte('ca99', 'etudes', 'a_faire', 100, { titre: 'Réviser' });
  assert.deepEqual(integrer(donnees(), ajout, { carte: duServeur }).trello.cartes.map((c) => c.id), ['q1', 'q2', 'ca99', 'e1']);
  const d = integrer(donnees(), ecriture('taches/cocher', { carte: 'q1', element: 'e2', coche: true }), {});
  const q1 = d.trello.cartes.find((c) => c.id === 'q1');
  assert.deepEqual([q1.checklists[0].elements[1].coche, 'attente' in q1, 'attente' in q1.checklists[0].elements[1]], [true, false, false]);
});

test("file : remplacer l'identifiant local, retirer une écriture, décider selon la réponse", () => {
  const f = [
    ecriture('taches/cocher', { carte: 'local-1', element: 'x', coche: true }),
    ecriture('taches/annuler', { id_client_origine: 'a' }, { carte: 'local-1' }),
    ecriture('taches/deplacer', { carte: 'q1', colonne: 'en_cours' }),
  ];
  const g = remplacerIdLocal(f, 'local-1', 'ca99');
  assert.deepEqual(g.map((e) => [e.corps.carte ?? null, e.local.carte ?? null]), [['ca99', null], [null, 'ca99'], ['q1', null]]);
  assert.equal(retirerEcriture(f, 'id-taches/annuler').length, 2);
  assert.deepEqual([200, 401, 404, 409, 400, 500, 502, null].map(decision), ['retirer', 'connexion', 'retirer', 'retirer', 'retirer', 'garder', 'garder', 'garder']);
});
```

`tests/e2e/test_donnees.mjs` :

```js
import { test, assert, heure, connecte, ouvrir, attendre } from './cadre.mjs';
import { MOT_DE_PASSE } from './serveur.mjs';

const MERCREDI = '2026-10-14T15:45:00+02:00';

const copieLocale = (page) => page.evaluate(async () => (await import('/js/stockage.js')).lire('donnees', 'derniere'));

test("connexion : mot de passe faux puis juste ; les données arrivent et sont gardées sur l'iPhone", async ({ page, serveur }) => {
  await heure(page, MERCREDI);
  await ouvrir(page, '/');
  await page.getByRole('heading', { name: 'JARVIS JR.' }).waitFor();
  assert.equal(await page.locator('#onglets').isVisible(), false);
  await page.getByLabel('Mot de passe').fill('mauvais');
  await page.getByRole('button', { name: 'Entrer' }).click();
  await page.getByText('Mot de passe incorrect. Encore 9 essais avant une pause de 15 min.').waitFor();
  await page.getByLabel('Mot de passe').fill(MOT_DE_PASSE);
  await page.getByRole('button', { name: 'Entrer' }).click();
  await page.locator('#onglets').waitFor();
  await attendre(async () => (await copieLocale(page))?.maintenant === MERCREDI, 'copie gardée dans IndexedDB');
  assert.deepEqual(serveur.appels('donnees').map((a) => a.parametres.force), [undefined, '1']);
});

test("sans réseau, avec une copie de 12 min : « Hors ligne · données d'il y a 12 min »", async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  await ouvrir(page, '/');
  await attendre(async () => (await copieLocale(page)) !== undefined, 'première copie');
  await page.evaluate(async (recu) => {
    const stockage = await import('/js/stockage.js');
    await stockage.ecrire('donnees', 'derniere', { ...(await stockage.lire('donnees', 'derniere')), recu_le: recu });
  }, new Date(MERCREDI).getTime() - 12 * 60000);
  serveur.etat.horsService = true;
  await page.reload();
  await page.getByText("Hors ligne · données d'il y a 12 min").waitFor();
});

test("sans réseau ni copie : un message clair, pas d'écran blanc", async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  serveur.etat.horsService = true;
  await ouvrir(page, '/');
  await page.getByText('Pas encore de données. Il me faut du réseau une première fois.').waitFor();
  assert.equal(await page.locator('#onglets').isVisible(), true);
});

test('session expirée avec une copie : écran de connexion avec « Ta session a expiré. Reconnecte-toi. »', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await ouvrir(page, '/');
  await attendre(async () => (await copieLocale(page)) !== undefined, 'première copie');
  await contexte.clearCookies();
  await page.reload();
  await page.getByText('Ta session a expiré. Reconnecte-toi.').waitFor();
});

test("Trello ne répond pas : « Tes tâches s'affichent telles qu'il y a 5 min »", async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  Object.assign(serveur.etat.donnees.trello, { erreur: 'trello_indisponible', recupere_le: '2026-10-14T15:40:00+02:00' });
  await ouvrir(page, '/');
  await page.getByText("Trello ne répond pas. Tes tâches s'affichent telles qu'il y a 5 min.").waitFor();
});

test('Google déconnecté : le bandeau propose « Reconnecter », vers la connexion Google', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  serveur.etat.donnees.agenda.erreur = 'google_deconnecte';
  await ouvrir(page, '/');
  await page.getByText("Google m'a déconnecté de ton agenda. Reconnecte-le, ça prend 30 secondes.").waitFor();
  assert.equal(await page.getByRole('link', { name: 'Reconnecter' }).getAttribute('href'), '/api/google/connexion');
});
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node --test 'tests/js/*.test.mjs' ; node tests/e2e/lancer.mjs donn`
Expected : `etat.test.mjs` en échec (module introuvable) ; les 6 tests d'écran en échec (pas d'écran de connexion).

- [ ] **Step 3: Implement the pure part and the storage**

`site/js/etat.js` :

```js
// Les écritures en attente appliquées par-dessus la dernière copie du serveur (SPEC § 8.4) : l'écran montre
// tout de suite ce que Colin vient de faire, marqué « attente » jusqu'à la réponse. Fonctions pures.

const ORDRE = ['qyris', 'asso', 'etudes', 'perso'];

export function trierCartes(cartes) {
  return cartes.sort((a, b) => ORDRE.indexOf(a.domaine) - ORDRE.indexOf(b.domaine) || a.position - b.position);
}

// Position d'une carte posée en haut d'une colonne, comme le serveur et Trello (pos=top)
function enHaut(cartes, domaine, colonne, sauf = null) {
  const positions = cartes.filter((c) => c.domaine === domaine && c.colonne === colonne && c.id !== sauf).map((c) => c.position);
  if (positions.length === 0) return 65536;
  const min = Math.min(...positions);
  return min > 0 ? min / 2 : min - 1;
}

function deplacer(cartes, carte, colonne) {
  carte.position = enHaut(cartes, carte.domaine, colonne, carte.id);
  carte.colonne = colonne;
  carte.attente = true;
}

// e : { route, corps, local }. Modifie cartes (une copie, jamais celle du serveur).
export function appliquerEcriture(cartes, e) {
  const c = e.corps;
  const carte = cartes.find((x) => x.id === (e.route === 'taches/annuler' ? e.local.carte : c.carte));
  if (e.route === 'taches/cocher' && carte) {
    for (const liste of carte.checklists) {
      for (const element of liste.elements) {
        if (element.id !== c.element) continue;
        element.coche = c.coche;
        element.attente = true;
        carte.attente = true;
      }
    }
  } else if (e.route === 'taches/terminer' && carte && carte.colonne !== 'fait') {
    carte.echeance_faite = carte.echeance !== null || carte.echeance_faite;
    deplacer(cartes, carte, 'fait');
  } else if (e.route === 'taches/annuler' && carte) {
    Object.assign(carte, { colonne: e.local.colonne_avant, position: e.local.position_avant, echeance_faite: e.local.echeance_faite_avant, attente: true });
  } else if (e.route === 'taches/deplacer' && carte && carte.colonne !== c.colonne) {
    if (carte.colonne === 'fait' && carte.echeance !== null) carte.echeance_faite = false;
    deplacer(cartes, carte, c.colonne);
  } else if (e.route === 'taches/ajouter' && !cartes.some((x) => x.id === e.local.id)) {
    cartes.push({
      id: e.local.id, domaine: c.domaine, colonne: 'a_faire', position: enHaut(cartes, c.domaine, 'a_faire'), titre: c.titre,
      description: '', url: '', echeance: e.local.echeance_iso, echeance_faite: false, urgent: c.urgent === true,
      etiquettes: [], checklists: [], derniere_activite: e.local.cree_le, attente: true,
    });
  }
  return cartes;
}

// Ce que montre l'écran : la copie du serveur, puis chaque écriture en attente, dans l'ordre
export function vue(donnees, file) {
  if (!donnees) return null;
  const v = structuredClone(donnees);
  for (const e of file) appliquerEcriture(v.trello.cartes, e);
  trierCartes(v.trello.cartes);
  return v;
}

// Une écriture acceptée par le serveur entre dans la copie, en attendant la prochaine lecture
export function integrer(donnees, e, reponse) {
  const d = structuredClone(donnees);
  if (e.route === 'taches/ajouter') {
    d.trello.cartes = d.trello.cartes.filter((c) => c.id !== e.local.id && c.id !== reponse.carte.id);
    d.trello.cartes.push(reponse.carte);
  } else {
    appliquerEcriture(d.trello.cartes, e);
  }
  for (const carte of d.trello.cartes) {
    delete carte.attente;
    for (const liste of carte.checklists) for (const element of liste.elements) delete element.attente;
  }
  trierCartes(d.trello.cartes);
  return d;
}

// Une capture envoyée : ses écritures suivantes visent la vraie carte
export function remplacerIdLocal(file, idLocal, idReel) {
  const remplacer = (o) => (o.carte === idLocal ? { ...o, carte: idReel } : o);
  return file.map((e) => ({ ...e, corps: remplacer(e.corps), local: remplacer(e.local) }));
}

export const retirerEcriture = (file, id) => file.filter((e) => e.id_client !== id);

// Réponse à une écriture : acceptée ou refusée pour de bon (4xx : carte supprimée, trop tard…), on la
// retire ; réseau absent ou serveur en panne, on la garde ; session expirée, on demande la connexion.
export function decision(statut) {
  if (statut === 401) return 'connexion';
  if (statut !== null && statut >= 200 && statut < 500) return 'retirer';
  return 'garder';
}
```

`site/js/stockage.js` :

```js
// Copie locale dans IndexedDB : dernières données, semaines d'agenda, écritures en attente. Sans IndexedDB
// (navigation privée), tout reste en mémoire le temps de l'ouverture.

const MAGASINS = ['donnees', 'agenda', 'file'];
const memoire = new Map(MAGASINS.map((m) => [m, new Map()]));
let base = null;

function ouvrir() {
  base ??= new Promise((resolve) => {
    try {
      const r = indexedDB.open('jarvis-jr', 1);
      r.onupgradeneeded = () => {
        for (const m of MAGASINS) if (!r.result.objectStoreNames.contains(m)) r.result.createObjectStore(m);
      };
      r.onsuccess = () => resolve(r.result);
      r.onerror = () => resolve(null);
      r.onblocked = () => resolve(null);
    } catch {
      resolve(null);
    }
  });
  return base;
}

function requete(db, magasin, mode, faire) {
  return new Promise((resolve, reject) => {
    const tx = db.transaction(magasin, mode);
    const r = faire(tx.objectStore(magasin));
    tx.oncomplete = () => resolve(r.result);
    tx.onerror = () => reject(tx.error ?? new Error('IndexedDB'));
    tx.onabort = () => reject(tx.error ?? new Error('IndexedDB'));
  });
}

export async function lire(magasin, cle) {
  const db = await ouvrir();
  return db ? requete(db, magasin, 'readonly', (s) => s.get(cle)) : structuredClone(memoire.get(magasin).get(cle));
}

export async function ecrire(magasin, cle, valeur) {
  const db = await ouvrir();
  if (db) await requete(db, magasin, 'readwrite', (s) => s.put(valeur, cle));
  else memoire.get(magasin).set(cle, structuredClone(valeur));
}

export async function supprimer(magasin, cle) {
  const db = await ouvrir();
  if (db) await requete(db, magasin, 'readwrite', (s) => s.delete(cle));
  else memoire.get(magasin).delete(cle);
}

export async function tout(magasin) {
  const db = await ouvrir();
  return db ? requete(db, magasin, 'readonly', (s) => s.getAll()) : [...memoire.get(magasin).values()].map((v) => structuredClone(v));
}

export async function vider() {
  const db = await ouvrir();
  for (const m of MAGASINS) {
    if (db) await requete(db, m, 'readwrite', (s) => s.clear());
    else memoire.get(m).clear();
  }
}
```

`site/js/api.js` :

```js
// Appels à l'API de jr.qyris.fr : /api/…, cookie de session, réponses { ok, … } ou { ok: false, erreur }.

export class ErreurApi extends Error {
  constructor(statut, code, message) {
    super(message);
    this.statut = statut;
    this.code = code;
  }
}

export class ErreurReseau extends Error {}

export async function appeler(methode, route, { corps = null, parametres = null, delai = 15000 } = {}) {
  const adresse = `/api/${route}${parametres ? `?${new URLSearchParams(parametres)}` : ''}`;
  const controle = new AbortController();
  const minuteur = setTimeout(() => controle.abort(), delai);
  let reponse;
  try {
    reponse = await fetch(adresse, {
      method: methode,
      credentials: 'same-origin',
      cache: 'no-store',
      signal: controle.signal,
      headers: corps === null ? {} : { 'Content-Type': 'application/json' },
      body: corps === null ? undefined : JSON.stringify(corps),
    });
  } catch {
    throw new ErreurReseau('Pas de réseau');
  } finally {
    clearTimeout(minuteur);
  }
  let json = null;
  try {
    json = await reponse.json();
  } catch {
    // corps illisible : traité comme une erreur du serveur
  }
  if (!reponse.ok || json?.ok !== true) {
    throw new ErreurApi(reponse.status, json?.erreur?.code ?? 'erreur_serveur', json?.erreur?.message ?? 'Erreur du serveur. Réessaie dans un instant.');
  }
  return json;
}
```

`site/js/file.js` :

```js
// La file des écritures (SPEC § 8.4) : gardée dans IndexedDB, envoyée dans l'ordre, une à la fois. Un
// renvoi porte le même id_client : le serveur rend le résultat déjà obtenu sans rien refaire.
import { appeler, ErreurApi } from './api.js';
import { decision, remplacerIdLocal } from './etat.js';
import * as stockage from './stockage.js';

let suivant = Date.now();
let envoi = null;
let enVol = null;

export async function charger() {
  return (await stockage.tout('file')).sort((a, b) => a.ordre - b.ordre);
}

export function nouvelleEcriture(route, corps, local = {}) {
  return { id_client: crypto.randomUUID(), route, corps, local, ordre: suivant++, essais: 0 };
}

export async function garder(e) {
  try {
    await stockage.ecrire('file', e.id_client, e);
  } catch {
    // elle reste dans la file en mémoire
  }
}

export async function oublier(id) {
  try {
    await stockage.supprimer('file', id);
  } catch {
    // rien de plus à faire
  }
}

// Si l'écriture id part en ce moment, attend sa réponse et son traitement
export async function attendre(id) {
  if (enVol?.id === id) await enVol.fini;
}

// r : { lire() → file, ecrire(file), succes(e, reponse), refus(e, erreur), garde(e), session() }
export function envoyer(r) {
  envoi ??= (async () => {
    try {
      for (;;) {
        const e = r.lire()[0];
        if (!e) return;
        let terminer;
        enVol = { id: e.id_client, fini: new Promise((resolve) => { terminer = resolve; }) };
        try {
          let reponse = null;
          let erreur = null;
          try {
            reponse = await appeler('POST', e.route, { corps: { ...e.corps, id_client: e.id_client } });
          } catch (err) {
            erreur = err;
          }
          const d = decision(erreur === null ? 200 : erreur instanceof ErreurApi ? erreur.statut : null);
          if (d === 'garder') {
            e.essais++;
            await garder(e);
            r.garde(e);
            return;
          }
          if (d === 'connexion') {
            r.session();
            return;
          }
          let reste = r.lire().filter((x) => x.id_client !== e.id_client);
          await oublier(e.id_client);
          if (erreur === null && e.route === 'taches/ajouter') {
            reste = remplacerIdLocal(reste, e.local.id, reponse.carte.id);
            for (const x of reste) await garder(x);
          }
          r.ecrire(reste);
          if (erreur === null) r.succes(e, reponse);
          else r.refus(e, erreur);
        } finally {
          enVol = null;
          terminer();
        }
      }
    } finally {
      envoi = null;
    }
  })();
  return envoi;
}
```

- [ ] **Step 4: Implement the shared pieces, the login screen and the app**

`site/js/ecrans/commun.js` :

```js
// Pièces partagées par les écrans : bandeaux d'état, messages, règle graduée, voyants, tampons, et
// « tirer pour actualiser » (une web app installée n'a pas celui de Safari).
import { angle, h } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { ageTexte, minutesEntre } from '../lib/temps.js';

export const regle = () => h('div', { class: 'regle', 'aria-hidden': 'true' }, h('span'), h('span'), h('span'));

export const voyant = (texte, allume = false, creme = false) => h('span', { class: `voyant${allume ? ' allume' : ''}${creme ? ' creme' : ''}` }, texte);

export const tampon = (texte, cle, classe = '') => h('span', { class: `tampon ${classe}`.trim(), vars: { '--angle': angle(cle) } }, texte);

export function rendreStatut(conteneur, app) {
  const { etat } = app;
  const lignes = [];
  if (!etat.connexion) {
    const d = etat.donnees;
    const depuis = (iso) => ageTexte(minutesEntre(new Date(iso), app.maintenant()));
    if (etat.horsLigne) lignes.push(h('p', {}, d ? T.etats.horsLigne(ageTexte((Date.now() - d.recu_le) / 60000)) : T.etats.vide));
    else if (etat.erreur) lignes.push(h('p', {}, etat.erreur));
    if (d && !etat.horsLigne) {
      if (d.trello.erreur && d.trello.recupere_le) lignes.push(h('p', {}, T.etats.trello(depuis(d.trello.recupere_le))));
      if (d.agenda.erreur === 'google_deconnecte') {
        lignes.push(h('div', { class: 'statut-action' }, h('span', {}, T.etats.googleDeconnecte), h('a', { class: 'bouton bouton-petit', href: '/api/google/connexion' }, T.etats.reconnecter)));
      } else if (d.agenda.erreur && d.agenda.recupere_le) {
        lignes.push(h('p', {}, T.etats.googleIndisponible(depuis(d.agenda.recupere_le))));
      }
    }
    if (etat.file.length > 0) lignes.push(h('p', {}, T.etats.file(etat.file.length)));
  }
  conteneur.replaceChildren(...lignes);
  conteneur.hidden = lignes.length === 0;
}

export function rendreMessage(conteneur, texte, action = null, fermer = null) {
  if (texte === null) {
    conteneur.replaceChildren();
    return;
  }
  conteneur.replaceChildren(h('div', { class: 'message' },
    h('span', {}, texte),
    action && h('button', { type: 'button', class: 'message-action', onclick: () => { fermer?.(); action.faire(); } }, action.libelle)));
}

export function tirerPourActualiser(ecran, indicateur, actualiser) {
  let depart = null;
  let pret = false;
  ecran.addEventListener('touchstart', (e) => {
    depart = ecran.scrollTop <= 0 ? e.touches[0].clientY : null;
    pret = false;
  }, { passive: true });
  ecran.addEventListener('touchmove', (e) => {
    if (depart === null) return;
    const ecart = e.touches[0].clientY - depart;
    pret = ecart > 70;
    indicateur.hidden = ecart <= 12;
    indicateur.textContent = pret ? T.etats.relacher : T.etats.tirer;
  }, { passive: true });
  ecran.addEventListener('touchend', async () => {
    if (depart === null) return;
    depart = null;
    if (!pret) {
      indicateur.hidden = true;
      return;
    }
    indicateur.textContent = T.etats.actualisation;
    await actualiser();
    indicateur.hidden = true;
  });
}
```

`site/js/ecrans/connexion.js` :

```js
// Connexion (CONTENU § 1) : un mot de passe, une fois tous les 90 jours environ.
import { h } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { ErreurReseau } from '../api.js';
import { voyant } from './commun.js';

export function rendre(app) {
  const C = T.connexion;
  const message = h('p', { class: 'connexion-message', role: 'alert' }, app.etat.expiree ? C.expiree : '');
  const champ = h('input', { id: 'mot-de-passe', class: 'champ', type: 'password', autocomplete: 'current-password', required: true });
  const bouton = h('button', { type: 'submit', class: 'bouton-allume' }, C.bouton);
  const formulaire = h('form', {
    class: 'connexion-formulaire',
    async onsubmit(ev) {
      ev.preventDefault();
      if (champ.value === '') return;
      bouton.disabled = true;
      message.textContent = '';
      try {
        await app.connecter(champ.value);
      } catch (e) {
        message.textContent = e instanceof ErreurReseau ? C.reseau : e.message;
        champ.select();
      } finally {
        bouton.disabled = false;
      }
    },
  }, h('label', { for: 'mot-de-passe', class: 'connexion-label' }, C.champ), champ, h('div', { class: 'cuvette cuvette-large' }, bouton), message);
  return h('div', { class: 'connexion' },
    h('section', { class: 'console', 'aria-label': T.app },
      h('div', { class: 'afficheur connexion-afficheur' }, voyant(T.aujourdhui.standby), h('h1', { class: 'connexion-titre' }, C.titre), h('p', { class: 'connexion-sous-titre' }, C.sousTitre))),
    formulaire,
    h('p', { class: 'connexion-adresse' }, C.adresse));
}
```

`site/js/app.js` (remplace la version de la tâche 3) :

```js
// Jarvis Jr. : l'état de l'app, la navigation, le chargement des données et la file des écritures.
// L'iPhone affiche sa copie tout de suite, puis demande les données au serveur (SPEC § 8.4).
import { h, icone } from './lib/dom.js';
import { T } from './lib/textes.js';
import { appeler, ErreurApi, ErreurReseau } from './api.js';
import * as stockage from './stockage.js';
import * as file from './file.js';
import { integrer, retirerEcriture, vue } from './etat.js';
import { rendreMessage, rendreStatut, tirerPourActualiser } from './ecrans/commun.js';
import * as connexion from './ecrans/connexion.js';
import * as attente from './ecrans/attente.js';

const ECRANS = {
  aujourdhui: attente,
  agenda: attente,
  taches: attente,
  carte: attente,
  moi: attente,
};
const FEUILLES = {
  capture: null,
};
const ONGLETS = ['aujourdhui', 'agenda', 'taches', 'moi'];
const DOMAINES = ['qyris', 'asso', 'etudes', 'perso'];
const RENVOI = 30000;

const etat = {
  donnees: null,        // dernière réponse de GET donnees, avec recu_le (heure de réception, en ms)
  file: [],             // écritures en attente (file.js)
  horsLigne: !navigator.onLine,
  erreur: null,         // erreur du serveur au dernier chargement
  connexion: false,     // l'écran de connexion est demandé…
  expiree: false,       // … parce que la session a expiré
  charge: false,
  dernierChargement: 0,
  capture: null,        // feuille « Noter » ouverte : { domaine, titre, urgent, date, heure, erreur }
  precedente: null,     // route affichée avant celle-ci, pour « Retour »
};

export function lireRoute(hash = location.hash) {
  const [chemin, requete = ''] = hash.replace(/^#\/?/, '').split('?');
  const [nom, param = null] = chemin.split('/').filter(Boolean);
  return { nom: ECRANS[nom] ? nom : 'aujourdhui', param, requete: new URLSearchParams(requete) };
}

const app = {
  etat,
  maintenant: () => new Date(),
  vue: () => vue(etat.donnees, etat.file),
  aller(hash) {
    if (location.hash === hash) rendre();
    else location.hash = hash;
  },
  retour(defaut) {
    if (etat.precedente !== null) history.back();
    else app.aller(defaut);
  },
  rendre: () => rendre(),
  actualiser,
  ecrire,
  annulerTerminer,
  message,
  connecter,
  deconnecter,
  demanderConnexion,
  ouvrirCapture(domaine = null) {
    etat.capture = { domaine, titre: '', urgent: false, date: '', heure: '', erreur: null };
    rendreCapture();
  },
  fermerCapture() {
    etat.capture = null;
    rendreCapture();
    document.querySelector('.bouton-plus')?.focus();
  },
  rendreCapture: () => rendreCapture(),
};

let affichee = null;

function rendre() {
  const route = lireRoute();
  const cle = etat.connexion ? 'connexion' : `${route.nom}/${route.param ?? ''}`;
  const changement = cle !== affichee;
  const ecran = document.getElementById('ecran');
  if (changement && affichee !== null) {
    etat.precedente = affichee;
    ECRANS[affichee.split('/')[0]]?.quitter?.();
  }
  const haut = changement ? 0 : ecran.scrollTop;
  const module = etat.connexion ? connexion : ECRANS[route.nom];
  document.getElementById('app').classList.toggle('sans-onglets', etat.connexion);
  ecran.replaceChildren(module.rendre(app, route));
  ecran.scrollTop = haut;
  affichee = cle;
  for (const a of document.querySelectorAll('.onglet')) {
    if (!etat.connexion && a.dataset.onglet === (route.nom === 'carte' ? 'taches' : route.nom)) a.setAttribute('aria-current', 'page');
    else a.removeAttribute('aria-current');
  }
  rendreStatut(document.getElementById('statut'), app);
  if (changement) ecran.focus({ preventScroll: true });
  module.apres?.(app, route, ecran, changement);
}

function rendreCapture() {
  const conteneur = document.getElementById('feuille');
  const ouverte = etat.capture !== null && FEUILLES.capture !== null;
  document.getElementById('principal').inert = ouverte;
  if (!ouverte) {
    conteneur.replaceChildren();
    return;
  }
  conteneur.replaceChildren(FEUILLES.capture.rendre(app, etat.capture));
  FEUILLES.capture.apres?.(conteneur, etat.capture);
}

async function actualiser(force = false) {
  if (etat.charge || etat.connexion) return;
  etat.charge = true;
  try {
    const d = await appeler('GET', 'donnees', { parametres: force ? { force: '1' } : null });
    d.recu_le = Date.now();
    Object.assign(etat, { donnees: d, horsLigne: false, erreur: null, dernierChargement: Date.now() });
    try {
      await stockage.ecrire('donnees', 'derniere', d);
    } catch {
      // la copie reste en mémoire
    }
    envoyerFile();
  } catch (e) {
    if (e instanceof ErreurApi && e.statut === 401) {
      demanderConnexion(etat.donnees !== null || etat.file.length > 0);
      return;
    }
    if (e instanceof ErreurReseau) etat.horsLigne = true;
    else etat.erreur = e.message;
  } finally {
    etat.charge = false;
  }
  rendre();
}

function demanderConnexion(expiree) {
  etat.connexion = true;
  etat.expiree = expiree;
  etat.capture = null;
  rendreCapture();
  rendre();
}

async function connecter(motDePasse) {
  await appeler('POST', 'connexion', { corps: { mot_de_passe: motDePasse } });
  etat.connexion = false;
  etat.expiree = false;
  rendre();
  await actualiser(true);
}

async function deconnecter(partout) {
  await appeler('POST', partout ? 'deconnexion-partout' : 'deconnexion', { corps: {} });
  try {
    await stockage.vider();
  } catch {
    // rien de plus à effacer
  }
  etat.donnees = null;
  etat.file = [];
  demanderConnexion(false);
}

async function ecrire(route, corps, local = {}) {
  const e = file.nouvelleEcriture(route, corps, local);
  etat.file = [...etat.file, e];
  await file.garder(e);
  rendre();
  envoyerFile();
  return e;
}

let minuteurRenvoi = null;

function envoyerFile() {
  if (etat.connexion || etat.file.length === 0) return;
  file.envoyer({
    lire: () => etat.file,
    ecrire(f) {
      etat.file = f;
    },
    succes(e, reponse) {
      if (etat.donnees) {
        etat.donnees = integrer(etat.donnees, e, reponse);
        stockage.ecrire('donnees', 'derniere', etat.donnees).catch(() => {});
      }
      rendre();
    },
    refus(e, erreur) {
      // Annuler un « Terminer » qui n'était jamais arrivé au serveur : il n'y a rien à dire
      if (!(e.route === 'taches/annuler' && erreur.code === 'annulation_impossible')) message(erreur.message);
      if (['annulation_trop_tard', 'annulation_impossible', 'carte_introuvable', 'element_introuvable'].includes(erreur.code)) actualiser(true);
      else rendre();
    },
    garde() {
      rendre();
      clearTimeout(minuteurRenvoi);
      minuteurRenvoi = setTimeout(envoyerFile, RENVOI);
    },
    session: () => demanderConnexion(true),
  });
}

// « Annuler » pendant 5 s après « Terminer ». Jamais parti : retiré de la file. Parti (ou peut-être
// arrivé, réponse perdue) : on envoie taches/annuler, qui répond « rien à annuler » s'il n'était pas arrivé.
async function annulerTerminer(e) {
  await file.attendre(e.id_client);
  const enFile = etat.file.find((x) => x.id_client === e.id_client);
  if (enFile) {
    etat.file = retirerEcriture(etat.file, e.id_client);
    await file.oublier(e.id_client);
    if (enFile.essais === 0) {
      rendre();
      return;
    }
  }
  await ecrire('taches/annuler', { id_client_origine: e.id_client }, {
    carte: e.corps.carte, colonne_avant: e.local.colonne_avant, position_avant: e.local.position_avant, echeance_faite_avant: e.local.echeance_faite_avant,
  });
}

let minuteurMessage = null;

function message(texte, { action = null, delai = 4000 } = {}) {
  clearTimeout(minuteurMessage);
  const conteneur = document.getElementById('messages');
  rendreMessage(conteneur, texte, action, () => message(null));
  if (texte !== null) minuteurMessage = setTimeout(() => rendreMessage(conteneur, null), delai);
}

function domaineCourant() {
  const route = lireRoute();
  if (route.nom === 'taches' && DOMAINES.includes(route.param)) return route.param;
  try {
    const dernier = localStorage.getItem('jr-domaine');
    return DOMAINES.includes(dernier) ? dernier : null;
  } catch {
    return null;
  }
}

// Au changement de minute, l'écran se redessine (comptes à rebours, JOUÉ), sauf pendant un tirage ou la connexion
function tictac() {
  setTimeout(() => {
    if (!etat.connexion && !ECRANS[lireRoute().nom].occupe?.()) rendre();
    tictac();
  }, 60000 - (Date.now() % 60000) + 50);
}

async function demarrer() {
  document.getElementById('onglets').replaceChildren(
    ...ONGLETS.map((nom) => h('a', { href: `#/${nom}`, class: 'onglet', dataset: { onglet: nom } }, icone(nom), h('span', {}, T.onglets[nom]))),
    h('button', { type: 'button', class: 'bouton-plus', 'aria-label': T.onglets.noter, onclick: () => app.ouvrirCapture(domaineCourant()) }, icone('plus', 22, 2.25)),
  );
  tirerPourActualiser(document.getElementById('ecran'), document.getElementById('tirer'), () => actualiser(true));
  try {
    etat.file = await file.charger();
  } catch {
    etat.file = [];
  }
  try {
    etat.donnees = (await stockage.lire('donnees', 'derniere')) ?? null;
  } catch {
    etat.donnees = null;
  }
  rendre();
  document.documentElement.dataset.pret = '1';
  window.addEventListener('hashchange', () => rendre());
  window.addEventListener('online', () => {
    etat.horsLigne = false;
    actualiser();
  });
  window.addEventListener('offline', () => {
    etat.horsLigne = true;
    rendre();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && Date.now() - etat.dernierChargement > 60000) actualiser();
  });
  tictac();
  await actualiser();
}

demarrer();
```

À la fin de `site/css/app.css` :

```css
/* Connexion */
.connexion { min-height: 100%; display: flex; flex-direction: column; padding-top: 40px; }
.connexion-afficheur { padding: 22px 18px 20px; }
.connexion-titre { margin-top: 16px; font-family: var(--mono); font-size: 36px; font-weight: 600; font-stretch: 87.5%; letter-spacing: 0.02em; line-height: 1; color: var(--ambre); }
.connexion-sous-titre { margin-top: 10px; font-size: calc(16 * var(--px)); color: var(--ambre-doux); }
.connexion-formulaire { margin-top: 32px; display: flex; flex-direction: column; }
.connexion-label { font-size: calc(14 * var(--px)); color: var(--texte-2); }
.connexion-formulaire .champ { margin-top: 8px; }
.connexion-formulaire .bouton-allume { width: 100%; }
.connexion-message { min-height: 1.4em; margin-top: 12px; color: var(--rouge-texte); font-size: calc(14 * var(--px)); }
.connexion-adresse { margin-top: auto; padding-top: 24px; text-align: center; font-family: var(--mono); font-size: 11px; letter-spacing: 0.1em; color: var(--texte-2); }
```

- [ ] **Step 5: Run the tests**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node --test 'tests/js/*.test.mjs' && node tests/e2e/lancer.mjs`
Expected : `pass 13`, `fail 0` ; `9 réussi(s), 0 échec(s)`.

- [ ] **Step 6: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add site/js site/css tests/js tests/e2e && git commit -m "Lot 1 : données sur l'iPhone (connexion, copie IndexedDB, file des écritures, bandeaux d'état, actualiser)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---
### Task 6 : Tâches et carte ouverte

L'onglet Tâches (filtres, groupes, compte, cartes faites à la demande) et l'écran d'une carte (étapes à cocher avec tampons FAIT, Terminer avec Annuler 5 s, Déplacer, Ouvrir dans Trello). Les premières écritures passent par la file de la tâche 5 : c'est ici qu'on vérifie le hors ligne, la réponse perdue et la session expirée.

**Files:**
- Create: `site/js/ecrans/taches.js`, `site/js/ecrans/carte.js`
- Modify: `site/js/app.js` (enregistrement des 2 écrans), `site/css/app.css` (à la fin)
- Test: `tests/e2e/test_taches.mjs`

**Interfaces:**
- Consumes : `app` (tâche 5), `cartes.js`, `dom.js`, `commun.js` (`regle`, `tampon`), `T.taches`, `T.carte`.
- Produces : `ligneCarte(app, carte, { minuscule })` exporté par `taches.js` (réutilisé par Aujourd'hui) ; routes `#/taches`, `#/taches/{domaine}`, `#/carte/{id}`, `#/carte/{id}?etape={element}` (étape mise en évidence et amenée à l'écran).

- [ ] **Step 1: Write the failing tests**

`tests/e2e/test_taches.mjs` :

```js
import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';
import { MOT_DE_PASSE } from './serveur.mjs';
import { CARTES, id } from './fixtures.mjs';

const MERCREDI = '2026-10-14T15:45:00+02:00';

async function preparer(page, contexte, serveur) {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
}

const carteDuServeur = (serveur, cle) => serveur.etat.donnees.trello.cartes.find((c) => c.id === CARTES[cle]);
const fileVide = (page) => attendre(async () => !(await page.locator('#statut').isVisible()), 'plus rien en attente');

test('tâches : 9 cartes ouvertes, 3 groupes, retard en rouge, filtre Études, cartes faites à la demande', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/taches');
  await page.getByText('9 CARTES').waitFor();
  assert.deepEqual(await page.locator('.groupe-titre span:first-child').allTextContents(), ['En cours', 'À faire', 'En attente']);
  assert.equal(await texte(page, '.carte-echeance.retard'), 'En retard depuis le 06/10');
  await page.getByRole('button', { name: 'Études', exact: true }).click();
  await page.getByText('3 CARTES').waitFor();
  assert.equal(new URL(page.url()).hash, '#/taches/etudes');
  await page.getByRole('button', { name: 'Tout', exact: true }).click();
  await page.getByRole('button', { name: 'Voir les cartes faites' }).click();
  await page.getByText('Logo : variantes PNG').waitFor();
});

test("carte : cocher une étape envoie une seule écriture, le tampon FAIT apparaît, l'avancement passe à 1/3", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, `/#/carte/${CARTES.linkedin}`);
  await page.getByRole('heading', { name: 'Campagne LinkedIn : programmer les posts' }).waitFor();
  await page.getByRole('button', { name: 'Cocher : Envoyer un mail de test' }).click();
  await page.getByRole('button', { name: 'Décocher : Envoyer un mail de test' }).waitFor();
  await attendre(() => serveur.appels('taches/cocher').length === 1, 'écriture reçue');
  const { corps } = serveur.appels('taches/cocher')[0];
  assert.deepEqual([corps.carte, corps.element, corps.coche], [CARTES.linkedin, id('ee', 11), true]);
  assert.match(corps.id_client, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
  await attendre(async () => (await texte(page, '.carte-ouverte .carte-progres')) === '1/3', 'avancement 1/3');
});

test('carte : Terminer, puis Annuler dans les 5 s, remet la carte dans « À faire »', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/taches/etudes');
  await page.getByText('MT23 : préparer le médian').click();
  await page.getByRole('button', { name: 'Terminer' }).click();
  await page.getByText('Carte terminée.').waitFor();
  assert.equal(new URL(page.url()).hash, '#/taches/etudes');
  await page.getByRole('button', { name: 'Annuler' }).click();
  // « Terminer » parti puis annulé, ou retiré de la file avant de partir : dans les deux cas, autant de l'un que de l'autre
  await attendre(async () => serveur.appels('taches/terminer').length === serveur.appels('taches/annuler').length
    && carteDuServeur(serveur, 'median').colonne === 'a_faire' && !(await page.locator('#statut').isVisible()), 'carte remise, plus rien en attente');
  assert.ok((await page.locator('.groupe[aria-label="À faire"] .carte-titre').allTextContents()).includes('MT23 : préparer le médian'));
});

test("hors ligne : cocher, recharger l'app, puis retour du réseau : l'étape reste cochée et part une seule fois", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, `/#/carte/${CARTES.cabaret}`);
  await page.getByRole('heading', { name: 'Cabaret : faire valider la lineup' }).waitFor();
  serveur.etat.horsService = true;
  await page.getByRole('button', { name: 'Cocher : Valider les horaires avec la team bouffe' }).click();
  await page.getByText('1 note attend le réseau. Elle partira toute seule.').waitFor();
  await page.reload();
  await page.getByRole('button', { name: 'Décocher : Valider les horaires avec la team bouffe' }).waitFor();
  await page.getByText('1 note attend le réseau. Elle partira toute seule.').waitFor();
  serveur.etat.horsService = false;
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await attendre(() => serveur.appels('taches/cocher').length === 1, 'envoyée au retour du réseau');
  await fileVide(page);
  assert.equal(carteDuServeur(serveur, 'cabaret').checklists[0].elements[0].coche, true);
});

test('réponse perdue : le renvoi porte le même id_client et le serveur ne refait rien', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, `/#/carte/${CARTES.formation}`);
  await page.getByRole('heading', { name: 'Formation 2 : relire le deck, tester les exercices' }).waitFor();
  serveur.etat.perdre = 'taches/cocher';
  await page.getByRole('button', { name: 'Cocher : Relire le deck en mode présentation' }).click();
  await attendre(() => serveur.appels('taches/cocher').length >= 1, 'premier envoi');
  // Chromium renvoie parfois seul une requête restée sans réponse ; sinon, la note attend et la file la renvoie
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await attendre(() => serveur.appels('taches/cocher').length >= 2, 'renvoi');
  assert.equal(new Set(serveur.appels('taches/cocher').map((a) => a.corps.id_client)).size, 1, 'même id_client à chaque envoi');
  await fileVide(page);
});

test('description piégée : le HTML reste du texte, seul le lien https devient un lien, rien ne déborde', async ({ page, contexte, serveur }) => {
  const piege = carteDuServeur(serveur, 'sites');
  piege.titre = `<img src=x onerror="window.pirate=1">${' très long'.repeat(300)}`;
  piege.description = '<script>window.pirate=2</script> javascript:window.pirate=3 voir https://exemple.fr/doc.';
  await preparer(page, contexte, serveur);
  await ouvrir(page, `/#/carte/${CARTES.sites}`);
  await page.locator('.carte-description').waitFor();
  assert.equal(await page.evaluate(() => window.pirate), undefined);
  assert.equal(await page.locator('.carte-ouverte img, .carte-ouverte script').count(), 0);
  assert.deepEqual(await page.locator('.carte-description a').evaluateAll((liens) => liens.map((a) => a.getAttribute('href'))), ['https://exemple.fr/doc']);
  assert.ok(await page.locator('.carte-ouverte-titre').evaluate((e) => e.getBoundingClientRect().right <= window.innerWidth), 'le titre reste dans l\'écran');
});

test("session expirée pendant une écriture : elle attend, l'écran de connexion s'ouvre, puis elle part", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, `/#/carte/${CARTES.apoint}`);
  await page.getByRole('heading', { name: "A'point : tester sur téléphone et clôturer" }).waitFor();
  await contexte.clearCookies();
  await page.getByRole('button', { name: 'Cocher : Vérifier le site en ligne, téléphone compris' }).click();
  await page.getByText('Ta session a expiré. Reconnecte-toi.').waitFor();
  await page.getByLabel('Mot de passe').fill(MOT_DE_PASSE);
  await page.getByRole('button', { name: 'Entrer' }).click();
  await attendre(() => carteDuServeur(serveur, 'apoint').checklists[0].elements[2].coche === true, 'écriture partie après la connexion');
});

test("carte inconnue : « Cette carte n'existe plus dans Trello. »", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/carte/ca9999999999999999999999');
  await page.getByText("Cette carte n'existe plus dans Trello.").waitFor();
});
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs tâches ; node tests/e2e/lancer.mjs carte`
Expected : échecs (écrans d'attente).

- [ ] **Step 3: Implement the two screens**

`site/js/ecrans/taches.js` :

```js
// L'onglet Tâches : filtres par domaine, groupes En cours, À faire, En attente, cartes faites à la demande.
import { h } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { DOMAINES, NOMS, avancement, etiquetteCarte, groupesTaches, libelleEcheance } from '../lib/cartes.js';
import { regle, tampon } from './commun.js';

let voirFaites = false;

// Une carte dans une liste ; dans l'À faire d'Aujourd'hui, l'échéance commence par une minuscule
export function ligneCarte(app, carte, { minuscule = false } = {}) {
  const echeance = libelleEcheance(carte, app.maintenant());
  const progres = avancement(carte);
  const texteEcheance = echeance && (minuscule ? echeance.texte[0].toLowerCase() + echeance.texte.slice(1) : echeance.texte);
  return h('button', { type: 'button', class: `carte-ligne d-${carte.domaine}${carte.colonne === 'fait' ? ' est-faite' : ''}`, onclick: () => app.aller(`#/carte/${carte.id}`) },
    h('span', { class: 'carte-puce' }, h('span', { class: 'carre' }), h('span', { class: 'carte-puce-texte' }, etiquetteCarte(carte)), progres && h('span', { class: 'carte-progres' }, progres)),
    h('span', { class: 'carte-titre' }, carte.titre),
    echeance && h('span', { class: `carte-echeance${echeance.retard ? ' retard' : ''}` }, echeance.retard && h('span', { class: 'point' }), texteEcheance),
    carte.attente && h('span', { class: 'carte-attente' }, T.taches.attente),
    carte.colonne === 'fait' && tampon(T.carte.fait, carte.id, 'carte-tampon'));
}

export function rendre(app, route) {
  const domaine = DOMAINES.includes(route.param) ? route.param : null;
  const g = groupesTaches(app.vue()?.trello.cartes ?? [], domaine);
  const ouvertes = g.en_cours.length + g.a_faire.length + g.en_attente.length;
  const filtre = (cle, libelle, cible) => h('button', {
    type: 'button', class: `filtre${cle ? ` d-${cle}` : ''}`, 'aria-pressed': String(domaine === cle), onclick: () => app.aller(cible),
  }, cle && h('span', { class: 'pastille' }), libelle);
  const groupe = (colonne, liste) => liste.length > 0 && h('section', { class: 'groupe', 'aria-label': T.taches.groupes[colonne] },
    h('h2', { class: 'section-titre groupe-titre' }, h('span', {}, T.taches.groupes[colonne]), h('span', {}, String(liste.length))),
    h('div', { class: 'groupe-cartes' }, liste.map((carte) => ligneCarte(app, carte))));
  return h('div', { class: 'taches' },
    h('header', { class: 'entete' }, h('h1', { class: 'titre-ecran' }, T.taches.titre), h('span', { class: 'entete-cote' }, T.taches.compte(ouvertes))),
    h('div', { class: 'filtres', role: 'group', 'aria-label': T.taches.filtrer }, filtre(null, T.taches.tout, '#/taches'), DOMAINES.map((d) => filtre(d, NOMS[d], `#/taches/${d}`))),
    regle(),
    ouvertes === 0 && h('p', { class: 'vide' }, T.taches.aucune),
    groupe('en_cours', g.en_cours),
    groupe('a_faire', g.a_faire),
    groupe('en_attente', g.en_attente),
    voirFaites && groupe('fait', g.fait),
    (g.fait.length > 0 || voirFaites) && h('button', {
      type: 'button', class: 'bouton-contour bouton-large voir-faites', 'aria-expanded': String(voirFaites),
      onclick: () => {
        voirFaites = !voirFaites;
        app.rendre();
      },
    }, voirFaites ? T.taches.masquerFaites : T.taches.voirFaites));
}
```

`site/js/ecrans/carte.js` :

```js
// Une carte ouverte : étapes à cocher, Terminer (avec Annuler 5 s), Déplacer, Ouvrir dans Trello.
import { h, icone, texteAvecLiens } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { COLONNES, NOMS, avancement, libelleEcheance, trouverCarte } from '../lib/cartes.js';
import { tampon } from './commun.js';

let choixColonne = null;  // la carte dont « Déplacer vers » est ouvert

export function quitter() {
  choixColonne = null;
}

// L'étape proposée par le tirage est amenée au milieu de l'écran
export function apres(app, route, ecran, changement) {
  if (changement) ecran.querySelector('.etape-proposee')?.scrollIntoView({ block: 'center' });
}

function ligneEtape(app, carte, element, proposee) {
  return h('li', { class: `etape${proposee ? ' etape-proposee' : ''}${element.coche ? ' est-cochee' : ''}` },
    h('button', {
      type: 'button', class: 'etape-case', 'aria-pressed': String(element.coche),
      'aria-label': element.coche ? T.carte.decocher(element.nom) : T.carte.cocher(element.nom),
      onclick: () => app.ecrire('taches/cocher', { carte: carte.id, element: element.id, coche: !element.coche }),
    }, element.coche ? tampon(T.carte.fait, element.id, `tampon-fait${element.attente ? ' frappe' : ''}`) : h('span', { class: 'case' })),
    h('span', { class: 'etape-nom' }, element.nom),
    element.attente && h('span', { class: 'carte-attente' }, T.taches.attente));
}

async function terminer(app, carte) {
  const e = await app.ecrire('taches/terminer', { carte: carte.id }, { colonne_avant: carte.colonne, position_avant: carte.position, echeance_faite_avant: carte.echeance_faite });
  app.message(T.carte.terminee, { action: { libelle: T.carte.annuler, faire: () => app.annulerTerminer(e) }, delai: 5000 });
  app.retour(`#/taches/${carte.domaine}`);
}

function deplacer(app, carte, colonne) {
  choixColonne = null;
  if (carte.colonne === colonne) app.rendre();
  else app.ecrire('taches/deplacer', { carte: carte.id, colonne });
}

export function rendre(app, route) {
  const v = app.vue();
  const carte = trouverCarte(v?.trello.cartes ?? [], route.param);
  const retour = h('button', { type: 'button', class: 'bouton-carre', 'aria-label': T.carte.retour, onclick: () => app.retour('#/taches') }, icone('gauche', 20, 2));
  if (!carte) return h('div', { class: 'carte-ecran' }, h('header', { class: 'entete-retour' }, retour), h('p', { class: 'vide' }, v ? T.carte.introuvable : T.etats.vide));
  const etape = route.requete.get('etape');
  const echeance = libelleEcheance(carte, app.maintenant());
  const progres = avancement(carte);
  return h('div', { class: `carte-ecran d-${carte.domaine}` },
    h('header', { class: 'entete-retour' }, retour),
    h('article', { class: 'carte-ouverte' },
      h('div', { class: 'carte-puce' }, h('span', { class: 'carre' }), h('span', { class: 'carte-puce-texte' }, [NOMS[carte.domaine], ...carte.etiquettes].join(' · ')), progres && h('span', { class: 'carte-progres' }, progres)),
      h('h1', { class: 'carte-ouverte-titre' }, carte.titre),
      (echeance || carte.urgent || carte.colonne === 'fait') && h('p', { class: 'carte-meta' },
        carte.colonne === 'fait' && tampon(T.carte.fait, carte.id, 'carte-tampon'),
        carte.urgent && h('span', { class: 'etiquette-urgent' }, T.carte.urgent),
        echeance && h('span', { class: `carte-echeance${echeance.retard ? ' retard' : ''}` }, echeance.texte)),
      carte.description && h('div', { class: 'carte-description' }, carte.description.split(/\n{2,}/).map((p) => h('p', {}, texteAvecLiens(p)))),
      carte.checklists.map((liste) => h('section', { class: 'checklist', 'aria-label': liste.nom },
        carte.checklists.length > 1 && h('h2', { class: 'section-titre' }, liste.nom),
        h('ul', { class: 'etapes' }, liste.elements.map((element) => ligneEtape(app, carte, element, element.id === etape))))),
      h('div', { class: 'carte-actions' },
        carte.colonne !== 'fait' && h('div', { class: 'cuvette carte-terminer' }, h('button', { type: 'button', class: 'bouton-allume', onclick: () => terminer(app, carte) }, T.carte.terminer)),
        h('button', {
          type: 'button', class: 'bouton carte-deplacer', 'aria-expanded': String(choixColonne === carte.id),
          onclick: () => {
            choixColonne = choixColonne === carte.id ? null : carte.id;
            app.rendre();
          },
        }, T.carte.deplacer)),
      choixColonne === carte.id && h('div', { class: 'choix-colonnes', role: 'group', 'aria-label': T.carte.deplacerVers },
        ['a_faire', 'en_cours', 'en_attente'].map((colonne) => h('button', {
          type: 'button', class: 'filtre', 'aria-pressed': String(carte.colonne === colonne), onclick: () => deplacer(app, carte, colonne),
        }, COLONNES[colonne]))),
      carte.url.startsWith('https://trello.com/') && h('a', { class: 'bouton-contour bouton-large', href: carte.url, target: '_blank', rel: 'noopener noreferrer' }, T.carte.ouvrirTrello, icone('externe', 16, 2))));
}
```

`site/js/app.js` : sous `import * as attente from './ecrans/attente.js';`, ajouter

```js
import * as taches from './ecrans/taches.js';
import * as carte from './ecrans/carte.js';
```

et dans `ECRANS`, remplacer `taches: attente,` par `taches,` et `carte: attente,` par `carte,`.

À la fin de `site/css/app.css` :

```css
/* Tâches */
.filtres { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 12px; }
.groupe-titre { display: flex; justify-content: space-between; margin-bottom: 8px; }
.groupe-cartes { display: flex; flex-direction: column; gap: 8px; }
.carte-ligne { position: relative; display: block; width: 100%; padding: 12px; border: 0; border-radius: 12px; background: var(--panneau); color: var(--texte); text-align: left; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 6px 14px rgba(0, 0, 0, 0.3); }
.carte-puce { display: flex; align-items: center; gap: 6px; font-family: var(--mono); font-size: 10.5px; letter-spacing: 0.06em; color: var(--domaine); }
.carte-puce-texte { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.carte-progres { margin-left: auto; color: var(--texte-2); }
.carte-titre { display: -webkit-box; margin-top: 6px; overflow: hidden; font-size: calc(16 * var(--px)); font-weight: 650; line-height: 1.3; overflow-wrap: anywhere; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.carte-echeance { display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: calc(13 * var(--px)); color: var(--texte-2); }
.carte-echeance.retard { color: var(--rouge-texte); font-weight: 600; }
.carte-attente { display: block; margin-top: 4px; font-family: var(--mono); font-size: 10.5px; letter-spacing: 0.06em; color: var(--texte-2); }
.carte-ligne.est-faite { opacity: 0.72; }
.carte-tampon { color: var(--domaine); }
.carte-ligne .carte-tampon { position: absolute; right: 12px; bottom: 12px; }
.voir-faites { margin-top: 18px; }

/* Carte ouverte */
.carte-ouverte { margin-top: 12px; padding: 14px 12px 12px; border-radius: 12px; background: var(--panneau); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 6px 14px rgba(0, 0, 0, 0.3); }
.carte-ouverte .carte-puce-texte { white-space: normal; }
.carte-ouverte-titre { margin-top: 8px; font-size: calc(22 * var(--px)); font-weight: 700; font-stretch: 90%; line-height: 1.25; overflow-wrap: anywhere; }
.carte-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 8px; }
.carte-meta .carte-echeance { margin-top: 0; }
.etiquette-urgent { font-family: var(--mono); font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: var(--rouge-texte); border: 1px solid var(--rouge); border-radius: 3px; padding: 4px 5px 3px; }
.carte-description { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; font-size: calc(15 * var(--px)); line-height: 1.45; overflow-wrap: anywhere; }
.carte-description p { white-space: pre-line; }
.carte-description a { color: var(--scotch); }
.checklist { margin-top: 12px; }
.etapes { margin-top: 8px; border-top: 1px solid var(--regle); }
.etape { display: flex; align-items: center; gap: 10px; min-height: 48px; border-bottom: 1px solid var(--regle); }
.etape-case { flex: none; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; padding: 0; border: 0; background: transparent; color: var(--domaine); }
.case { width: 24px; height: 24px; border: 1.5px solid #7A8088; border-radius: 5px; background: var(--graphite); box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.6); }
.tampon-fait { padding: 2px 4px 1px; border-radius: 3px; font-size: 11px; color: var(--domaine); }
.etape-nom { flex: 1 1 auto; min-width: 0; padding: 8px 0; font-size: calc(14 * var(--px)); line-height: 1.3; overflow-wrap: anywhere; }
.etape.est-cochee .etape-nom { color: var(--texte-2); }
.etape .carte-attente { flex: none; margin: 0 4px 0 0; }
.etape-proposee { margin: 0 -6px; padding: 0 6px; border-radius: 8px; box-shadow: inset 0 0 0 2px var(--scotch); }
.carte-actions { display: flex; gap: 8px; margin-top: 12px; }
.carte-terminer { flex: 1.3 1 0; padding: 3px; border-radius: 12px; }
.carte-terminer .bouton-allume { width: 100%; min-height: 46px; font-size: 18px; font-stretch: 80%; }
.carte-deplacer { flex: 1 1 0; min-height: 52px; }
.choix-colonnes { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
```

- [ ] **Step 4: Run the tests**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs`
Expected : `17 réussi(s), 0 échec(s)`.

(Le rendu de chaque écran est comparé à la maquette v28 à la tâche 12, sur des captures de tous les écrans.)

- [ ] **Step 5: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add site/js site/css tests/e2e && git commit -m "Lot 1 : onglet Tâches et carte ouverte (cocher, Terminer avec Annuler, Déplacer, Ouvrir dans Trello)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---
### Task 7 : Aujourd'hui et le tirage

L'écran principal (maquette v28 : « Au réveil », « Entre deux cours », « En TD », « Sous le pli ») : l'écran de conduite (EN SCÈNE, STANDBY, chiffres ambre), Junior (salutation, phrase du jour, bandeaux des jours spéciaux), les 4 tranches, GO ; sous le pli, la conduite du jour et l'À faire en 3 groupes, avec une barre compacte et un petit GO. Puis le tirage : STANDBY, l'afficheur qui roule, la proposition qui tombe, 3 relances, Junior qui choisit, « C'est parti ».

**Files:**
- Create: `site/js/lib/conduite.js`, `site/js/lib/phrases.js`, `site/js/lib/tirage.js`, `site/js/ecrans/aujourdhui.js`
- Modify: `site/js/app.js` (enregistrement), `site/css/app.css` (à la fin)
- Test: `tests/js/conduite.test.mjs`, `tests/js/phrases.test.mjs`, `tests/js/tirage.test.mjs`, `tests/e2e/test_aujourdhui.mjs`

**Interfaces:**
- Consumes : `temps.js`, `cartes.js`, `textes.js`, `dom.js`, `commun.js` (`regle`, `voyant`, `tampon`), `ligneCarte` (`taches.js`), `app`.
- Produces :
  - `conduite.js` : `estCours(e)`, `jourDe(jours, jour)`, `etatConduite(jours, maintenant)` → `{ aujourdhui, scene, standby, enCours, bandeaux, nbCours, premierCours }` avec `scene` = `{ type: 'cours', cours, finDans }` | `{ type: 'entracte', libres }` | `{ type: 'rien', nbCours }` | `{ type: 'fini' }` et `standby` = `{ cours (avec jour), dans, quand: 'aujourdhui' | 'demain' | 'plus_tard' }` ou `null` ; `medianProche(jours, maintenant, horizon = 7)` → `{ evenement, dans }` ou `null` ; `nomCreneau(e)` → `'TN01 · TD'` ou le titre.
  - `phrases.js` : `salutation(maintenant)`, `aleaDepuis(graine)`, `choixPondere(elements, poids, alea)`, `phrasesDeSituation(faits)`, `phraseDuJour(faits)` avec `faits = { jour, nbCours, premierCours (minutes), nbRetard, medianDans }`.
  - `tirage.js` : `TRAJET` (10), `IMMINENT` (15), `LIMITE` (23), `RELANCES` (3), `PAS` (14) ; `tempsLibre(jours, maintenant)` → `{ enCours, coursDans, minutes, avant, tard }` ; `score(carte, maintenant)` ; `candidats(cartes, maintenant)` → `[{ cle, carte, element, score }]` triés ; `tirer(liste, dejaProposes, alea)` ; `texteProposition(c)` ; `ligneAfficheur(libre)` ; `replique(libre, relances)` ; `etiquetteProposition(c, maintenant)` ; `rouler(texte, avancement, alea)`.
  - `aujourdhui.js` : écran `aujourdhui` avec `occupe()` (pendant que l'afficheur roule), `quitter()`, `apres()` (barre compacte).

- [ ] **Step 1: Write the failing tests of the pure modules**

`tests/js/conduite.test.mjs` :

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { etatConduite, medianProche, nomCreneau } from '../../site/js/lib/conduite.js';

const cours = (jour, uv, type, debut, fin, salle) => ({ debut: `${jour}T${debut}:00+02:00`, fin: `${jour}T${fin}:00+02:00`, journee: false, source: 'utc', uv, type, salle, titre: `${uv} - ${type} 1`, median: false });
const perso = (jour, titre, debut, fin) => ({ ...cours(jour, null, null, debut, fin, null), source: 'perso', titre, median: titre.startsWith('Médian') });
const JOURS = {
  '2026-10-14': { bandeaux: [], evenements: [cours('2026-10-14', 'NF93', 'TD', '08:00', '10:00', 'FA400'), perso('2026-10-14', 'Déjeuner', '12:30', '13:30'), cours('2026-10-14', 'TN01', 'TD', '16:30', '19:30', 'FA510')] },
  '2026-10-15': { bandeaux: [{ type: 'ferie', jour: null, texte: "Jour férié à l'UTC : pas de cours." }], evenements: [] },
  '2026-10-19': { bandeaux: [], evenements: [cours('2026-10-19', 'MT23', 'TD', '16:30', '18:30', 'FA420')] },
  '2026-10-21': { bandeaux: [], evenements: [perso('2026-10-21', 'Médian MT23', '19:15', '20:45')] },
};
const a = (iso) => etatConduite(JOURS, new Date(iso));

test('conduite : au réveil, entre deux cours, pendant un cours, le soir', () => {
  const matin = a('2026-10-14T07:32:00+02:00');
  assert.deepEqual([matin.scene, matin.standby.cours.uv, matin.standby.dans, matin.standby.quand], [{ type: 'rien', nbCours: 2 }, 'NF93', 28, 'aujourdhui']);
  const entracte = a('2026-10-14T15:45:00+02:00');
  assert.deepEqual([entracte.scene, entracte.standby.cours.uv, entracte.standby.dans], [{ type: 'entracte', libres: 45 }, 'TN01', 45]);
  const td = a('2026-10-14T17:10:00+02:00');
  assert.deepEqual([td.scene.type, td.scene.cours.uv, td.scene.finDans, td.enCours, td.standby.cours.uv, td.standby.quand], ['cours', 'TN01', 140, true, 'MT23', 'plus_tard']);
  const soir = a('2026-10-14T21:00:00+02:00');
  assert.deepEqual([soir.scene.type, soir.standby.cours.jour, soir.nbCours, soir.premierCours.uv], ['fini', '2026-10-19', 2, 'NF93']);
});

test('conduite : jour férié (bandeau), veille du cours (« demain »), aucune donnée', () => {
  assert.equal(a('2026-10-15T10:00:00+02:00').bandeaux[0].type, 'ferie');
  assert.equal(a('2026-10-18T20:00:00+02:00').standby.quand, 'demain');
  const rien = etatConduite({}, new Date('2026-10-14T15:45:00+02:00'));
  assert.deepEqual([rien.scene.type, rien.standby, rien.nbCours], ['fini', null, 0]);
});

test('médian : dans 7 jours ou moins, avec son nombre de jours ; nom des créneaux', () => {
  assert.equal(medianProche(JOURS, new Date('2026-10-14T15:45:00+02:00')).dans, 7);
  assert.equal(medianProche(JOURS, new Date('2026-10-13T15:45:00+02:00')), null);
  assert.equal(medianProche(JOURS, new Date('2026-10-21T21:00:00+02:00')), null);
  assert.equal(nomCreneau(JOURS['2026-10-14'].evenements[0]), 'NF93 · TD');
  assert.equal(nomCreneau(JOURS['2026-10-14'].evenements[1]), 'Déjeuner');
});
```

`tests/js/phrases.test.mjs` :

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { aleaDepuis, choixPondere, phraseDuJour, phrasesDeSituation, salutation } from '../../site/js/lib/phrases.js';
import { T } from '../../site/js/lib/textes.js';

test("salutation selon l'heure de Paris", () => {
  const heures = ['2026-10-14T07:32:00+02:00', '2026-10-14T12:00:00+02:00', '2026-10-14T18:30:00+02:00', '2026-10-14T23:10:00+02:00', '2026-10-15T04:59:00+02:00'];
  assert.deepEqual(heures.map((iso) => salutation(new Date(iso))), ['Bonjour Colin.', 'Bon après-midi.', 'Bonsoir.', 'Encore debout ?', 'Encore debout ?']);
});

test('phrases de situation : lundi, journée chargée, pas de cours avant 13 h, retard ou pas, médian', () => {
  assert.deepEqual(phrasesDeSituation({ jour: '2026-10-19', nbCours: 4, premierCours: 13 * 60 + 30, nbRetard: 2, medianDans: 2 }), [
    T.phrases.lundi, T.phrases.chargee, T.phrases.pasAvant13h,
    "2 tâches en retard. Elles ne vont pas se faire seules, j'ai essayé.", 'Médian dans 2 jours. Je ne panique pas, donc toi non plus.',
  ]);
  assert.deepEqual(phrasesDeSituation({ jour: '2026-10-14', nbCours: 2, premierCours: 480, nbRetard: 0, medianDans: null }), [T.phrases.rienEnRetard]);
  assert.equal(T.phrases.enRetard(1), "1 tâche en retard. Elle ne va pas se faire seule, j'ai essayé.");
});

test('phrase du jour : même date, même phrase ; toujours une phrase possible ; plusieurs phrases sur un mois', () => {
  const faits = (jour) => ({ jour, nbCours: 2, premierCours: 480, nbRetard: 2, medianDans: 7 });
  assert.equal(phraseDuJour(faits('2026-10-14')), phraseDuJour(faits('2026-10-14')));
  const vues = new Set();
  for (let j = 1; j <= 30; j++) {
    const f = faits(`2026-11-${String(j).padStart(2, '0')}`);
    const phrase = phraseDuJour(f);
    assert.ok([...phrasesDeSituation(f), ...T.phrases.generales].includes(phrase), phrase);
    vues.add(phrase);
  }
  assert.ok(vues.size >= 3, `${vues.size} phrases différentes sur un mois`);
});

test('aléa à graine et choix pondéré', () => {
  const a = aleaDepuis('2026-10-14');
  const b = aleaDepuis('2026-10-14');
  assert.deepEqual([a(), a(), a()], [b(), b(), b()]);
  assert.deepEqual([0.2, 0.5, 0.9999].map((x) => choixPondere(['x', 'y'], [1, 3], () => x)), ['x', 'y', 'y']);
});
```

`tests/js/tirage.test.mjs` :

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { candidats, etiquetteProposition, ligneAfficheur, replique, rouler, score, tempsLibre, texteProposition, tirer } from '../../site/js/lib/tirage.js';
import { T } from '../../site/js/lib/textes.js';

const MAINTENANT = new Date('2026-10-14T15:45:00+02:00');
const cours = (uv, debut, fin) => ({ debut: `2026-10-14T${debut}:00+02:00`, fin: `2026-10-14T${fin}:00+02:00`, journee: false, source: 'utc', uv, type: 'TD', salle: 'FA510', titre: `${uv} - TD 1`, median: false });
const JOURS = { '2026-10-14': { bandeaux: [], evenements: [cours('NF93', '08:00', '10:00'), cours('TN01', '16:30', '19:30')] } };
const carte = (id, autres = {}) => ({
  id, domaine: 'qyris', colonne: 'a_faire', position: 1, titre: id, description: '', url: '', echeance: null, echeance_faite: false,
  urgent: false, etiquettes: [], checklists: [], derniere_activite: '', ...autres,
});
const a = (iso) => tempsLibre(JOURS, new Date(iso));

test('temps libre : avant le prochain cours (moins 10 min de trajet), pendant un cours, sans autre cours, après 23 h', () => {
  const avant = a('2026-10-14T15:45:00+02:00');
  assert.deepEqual([avant.enCours, avant.coursDans, avant.minutes, avant.avant.uv, avant.tard], [false, 45, 35, 'TN01', false]);
  assert.equal(a('2026-10-14T17:00:00+02:00').enCours, true);
  const soir = a('2026-10-14T20:15:00+02:00');
  assert.deepEqual([soir.minutes, soir.avant, soir.tard], [165, null, false]);
  assert.equal(a('2026-10-14T23:30:00+02:00').tard, true);
});

test('score : retard (100 + 5 par jour, 30 jours au plus), échéance proche, Urgent, En cours', () => {
  const avec = (echeance) => score(carte('x', { echeance }), MAINTENANT);
  assert.deepEqual(
    ['2026-10-06T08:30:00+02:00', '2026-08-01T18:00:00+02:00', '2026-10-14T18:00:00+02:00', '2026-10-15T18:00:00+02:00', '2026-10-17T18:00:00+02:00', '2026-10-21T19:15:00+02:00', '2026-10-22T19:15:00+02:00'].map(avec),
    [140, 250, 80, 50, 30, 15, 0],
  );
  assert.equal(score(carte('h', { urgent: true, colonne: 'en_cours' }), MAINTENANT), 65);
});

test('candidats : première étape non cochée, carte entière sans étape restante, En attente et Fait exclus, triés par score', () => {
  const etapes = { id: 'cc', nom: 'Étapes', elements: [{ id: 'e1', nom: 'Fait', coche: true }, { id: 'e2', nom: 'Envoyer le mail', coche: false }] };
  const liste = candidats([carte('a', { checklists: [etapes] }), carte('b', { urgent: true }), carte('c', { colonne: 'en_attente', urgent: true }), carte('d', { colonne: 'fait' })], MAINTENANT);
  assert.deepEqual(liste.map((c) => [c.carte.id, c.element?.id ?? null, c.score]), [['b', null, 40], ['a', 'e2', 0]]);
});

test('tirer : parmi les 5 meilleurs pas encore proposés ; texte de la proposition', () => {
  const liste = candidats(['a', 'b', 'c', 'd', 'e', 'f'].map((id, i) => carte(id, { urgent: i < 2 })), MAINTENANT);
  const proposes = new Set();
  for (let i = 0; i < 5; i++) proposes.add(tirer(liste, proposes, () => 0).cle);
  assert.deepEqual([...proposes].map((cle) => cle.split(':')[0]), ['a', 'b', 'c', 'd', 'e']);
  assert.equal(tirer([], new Set(), Math.random), null);
  const etape = (nom) => ({ carte: carte('x', { titre: 'Formation 2 : relire le deck' }), element: { id: 'e', nom } });
  assert.equal(texteProposition(etape('Tester les exercices sur les comptes gratuits')), 'Formation 2 : tester les exercices sur les comptes gratuits.');
  assert.equal(texteProposition(etape('QYRIS : envoyer le devis.')), 'Formation 2 : QYRIS : envoyer le devis.');
  assert.equal(texteProposition({ carte: carte('y', { titre: 'Dentiste' }), element: null }), 'Terminer : Dentiste');
});

test('afficheur, répliques, étiquette et défilement du tirage', () => {
  const avant = a('2026-10-14T15:45:00+02:00');
  assert.equal(ligneAfficheur(avant), '35 MIN AVANT TN01');
  assert.equal(ligneAfficheur(a('2026-10-14T20:15:00+02:00')), "LIBRE JUSQU'À 23 H");
  assert.equal(ligneAfficheur(a('2026-10-14T23:30:00+02:00')), 'IL EST TARD');
  assert.equal(replique(avant, 0), "Je t'ai trouvé 35 min avant ton TD.");
  assert.deepEqual([1, 2, 3, 4].map((n) => replique(avant, n)), T.tirage.relances);
  assert.equal(replique(a('2026-10-14T20:15:00+02:00'), 0), "Libre jusqu'à 23 h.");
  assert.equal(replique(a('2026-10-14T23:30:00+02:00'), 0), 'Il est tard, mais puisque tu insistes :');
  assert.equal(etiquetteProposition({ carte: carte('z', { domaine: 'etudes', etiquettes: ['GE15'], colonne: 'en_cours' }) }, MAINTENANT), 'Études · GE15 · en cours');
  assert.equal(rouler('35 MIN', 1), '35 MIN');
  assert.equal(rouler('35 MIN', 0, () => 0), '00 000');
});
```

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node --test 'tests/js/*.test.mjs'`
Expected : les 3 nouveaux fichiers en échec (modules introuvables), les 13 tests précédents au vert.

- [ ] **Step 2: Implement the pure modules**

`site/js/lib/conduite.js` :

```js
// L'écran de conduite d'Aujourd'hui (SPEC § 8.1) : ce qui est en scène, ce qui suit. Fonctions pures.
import { ajouterJours, jourParis, joursEntre, minutesEntre } from './temps.js';

const VIDE = { bandeaux: [], evenements: [] };

export const estCours = (e) => e.source === 'utc' && !e.journee;
export const jourDe = (jours, jour) => jours?.[jour] ?? VIDE;
export const nomCreneau = (e) => (e.uv ? `${e.uv} · ${e.type}` : e.titre);

const dans = (maintenant, iso) => Math.round(minutesEntre(maintenant, new Date(iso)));

// Les cours connus d'aujourd'hui à la fin de la copie, dans l'ordre, chacun avec son jour
function coursAVenir(jours, aujourdhui) {
  return Object.keys(jours ?? {}).filter((j) => j >= aujourdhui).sort()
    .flatMap((j) => jours[j].evenements.filter(estCours).map((e) => ({ ...e, jour: j })));
}

export function etatConduite(jours, maintenant) {
  const aujourdhui = jourParis(maintenant);
  const cours = coursAVenir(jours, aujourdhui);
  const duJour = cours.filter((e) => e.jour === aujourdhui);
  const enCours = duJour.find((e) => new Date(e.debut) <= maintenant && maintenant < new Date(e.fin)) ?? null;
  const suivant = cours.find((e) => new Date(e.debut) > maintenant) ?? null;
  let scene;
  if (enCours) {
    scene = { type: 'cours', cours: enCours, finDans: dans(maintenant, enCours.fin) };
  } else if (suivant?.jour === aujourdhui) {
    scene = duJour.some((e) => new Date(e.fin) <= maintenant) ? { type: 'entracte', libres: dans(maintenant, suivant.debut) } : { type: 'rien', nbCours: duJour.length };
  } else {
    scene = { type: 'fini' };
  }
  let quand = null;
  if (suivant) quand = suivant.jour === aujourdhui ? 'aujourdhui' : suivant.jour === ajouterJours(aujourdhui, 1) ? 'demain' : 'plus_tard';
  return {
    aujourdhui,
    scene,
    standby: suivant && { cours: suivant, dans: dans(maintenant, suivant.debut), quand },
    enCours: enCours !== null,
    bandeaux: jourDe(jours, aujourdhui).bandeaux,
    nbCours: duJour.length,
    premierCours: duJour[0] ?? null,
  };
}

// Le prochain médian de l'agenda principal (titre « Médian … »), s'il tombe dans les $horizon jours
export function medianProche(jours, maintenant, horizon = 7) {
  const aujourdhui = jourParis(maintenant);
  for (const jour of Object.keys(jours ?? {}).filter((j) => j >= aujourdhui).sort()) {
    const median = jours[jour].evenements.find((e) => e.median && (e.journee || new Date(e.fin) > maintenant));
    if (median) {
      const n = joursEntre(aujourdhui, jour);
      return n <= horizon ? { evenement: median, dans: n } : null;
    }
  }
  return null;
}
```

`site/js/lib/phrases.js` :

```js
// Ce que dit Junior en haut d'Aujourd'hui : la salutation et la phrase du jour (SPEC § 8.2). Fonctions pures.
import { T } from './textes.js';
import { enParis, jourSemaine } from './temps.js';

export function salutation(maintenant) {
  const { heure } = enParis(maintenant);
  const s = T.aujourdhui.salutations;
  if (heure >= 5 && heure < 12) return s.matin;
  if (heure >= 12 && heure < 18) return s.apresMidi;
  if (heure >= 18 && heure < 23) return s.soir;
  return s.nuit;
}

function hacher(texte) {
  let n = 2166136261;
  for (const c of texte) {
    n ^= c.codePointAt(0);
    n = Math.imul(n, 16777619);
  }
  return n >>> 0;
}

// Aléa à graine (mulberry32) : la même graine donne toujours la même suite
export function aleaDepuis(graine) {
  let a = hacher(graine);
  return () => {
    a = (a + 0x6D2B79F5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

export function choixPondere(elements, poids, alea) {
  let reste = alea() * poids.reduce((s, p) => s + p, 0);
  for (let i = 0; i < elements.length; i++) {
    reste -= poids[i];
    if (reste < 0) return elements[i];
  }
  return elements[elements.length - 1];
}

// Celles qui dépendent des lots suivants (promotion, joker, 0 mail) n'existent pas encore
export function phrasesDeSituation(f) {
  const P = T.phrases;
  const s = [];
  if (jourSemaine(f.jour) === 0) s.push(P.lundi);
  if (f.nbCours >= 4) s.push(P.chargee);
  if (f.premierCours !== null && f.premierCours >= 13 * 60) s.push(P.pasAvant13h);
  s.push(f.nbRetard === 0 ? P.rienEnRetard : P.enRetard(f.nbRetard));
  if (f.medianDans !== null && f.medianDans <= 7) s.push(P.median(f.medianDans));
  return s;
}

// Graine : la date. Une phrase de situation a 3 fois plus de chances qu'une phrase générale.
export function phraseDuJour(f) {
  const situations = phrasesDeSituation(f);
  const generales = T.phrases.generales;
  return choixPondere([...situations, ...generales], [...situations.map(() => 3), ...generales.map(() => 1)], aleaDepuis(f.jour));
}
```

`site/js/lib/tirage.js` :

```js
// « Je fais quoi maintenant ? » (SPEC § 8.3). Fonctions pures : l'aléa est passé en paramètre.
import { T } from './textes.js';
import { debutTitre, enRetard, etiquetteCarte } from './cartes.js';
import { estCours } from './conduite.js';
import { choixPondere } from './phrases.js';
import { dureeTexte, enParis, jourParis, joursEntre, minutesEntre } from './temps.js';

export const TRAJET = 10;
export const IMMINENT = 15;
export const LIMITE = 23;
export const RELANCES = 3;
export const PAS = 14;

// Du moment présent au prochain cours du jour moins le trajet, ou jusqu'à 23 h. Il ne filtre rien :
// aucune carte n'a d'estimation de durée, il est seulement affiché.
export function tempsLibre(jours, maintenant) {
  const cours = (jours?.[jourParis(maintenant)]?.evenements ?? []).filter(estCours);
  const rien = { enCours: false, coursDans: null, minutes: null, avant: null, tard: false };
  if (cours.some((e) => new Date(e.debut) <= maintenant && maintenant < new Date(e.fin))) return { ...rien, enCours: true };
  const prochain = cours.find((e) => new Date(e.debut) > maintenant);
  if (prochain) {
    const dans = Math.round(minutesEntre(maintenant, new Date(prochain.debut)));
    return { ...rien, coursDans: dans, minutes: dans - TRAJET, avant: prochain };
  }
  const { heure, minute } = enParis(maintenant);
  if (heure >= LIMITE || heure < 5) return { ...rien, tard: true };
  return { ...rien, minutes: LIMITE * 60 - (heure * 60 + minute) };
}

// Points d'une carte (réglables, à recaler à l'usage)
export function score(carte, maintenant) {
  let points = 0;
  if (carte.echeance !== null && !carte.echeance_faite) {
    if (enRetard(carte, maintenant)) {
      points += 100 + 5 * Math.min(Math.floor(minutesEntre(new Date(carte.echeance), maintenant) / 1440), 30);
    } else {
      const ecart = joursEntre(jourParis(maintenant), jourParis(new Date(carte.echeance)));
      if (ecart === 0) points += 80;
      else if (ecart === 1) points += 50;
      else if (ecart <= 3) points += 30;
      else if (ecart <= 7) points += 15;
    }
  }
  if (carte.urgent) points += 40;
  if (carte.colonne === 'en_cours') points += 25;
  return points;
}

// Pour chaque carte En cours ou À faire : sa première étape non cochée, ou la carte entière
export function candidats(cartes, maintenant) {
  const liste = [];
  for (const carte of cartes) {
    if (carte.colonne !== 'en_cours' && carte.colonne !== 'a_faire') continue;
    let element = null;
    for (const l of carte.checklists) {
      element = l.elements.find((e) => !e.coche) ?? null;
      if (element) break;
    }
    liste.push({ cle: `${carte.id}:${element?.id ?? ''}`, carte, element, score: score(carte, maintenant) });
  }
  return liste.sort((a, b) => b.score - a.score);
}

// Au hasard, pondéré par (score + 10), parmi les 5 meilleurs pas encore proposés dans ce tirage
export function tirer(liste, dejaProposes, alea) {
  const restants = liste.filter((c) => !dejaProposes.has(c.cle));
  const pool = (restants.length > 0 ? restants : liste).slice(0, 5);
  return pool.length === 0 ? null : choixPondere(pool, pool.map((c) => c.score + 10), alea);
}

// « Formation 2 : tester les exercices sur les comptes gratuits. » (un sigle en tête garde ses capitales)
export function texteProposition(c) {
  if (c.element === null) return T.tirage.terminer(c.carte.titre);
  const nom = c.element.nom.trim();
  const etape = /^\p{Lu}{2}/u.test(nom) ? nom : nom.charAt(0).toLocaleLowerCase('fr') + nom.slice(1);
  return `${debutTitre(c.carte.titre)} : ${etape}${/[.!?…]$/.test(etape) ? '' : '.'}`;
}

export function ligneAfficheur(libre) {
  if (libre.tard) return T.tirage.ligneTard;
  if (libre.avant) return T.tirage.ligneAvant(dureeTexte(libre.minutes), libre.avant.uv ?? libre.avant.titre);
  return T.tirage.ligneLibre;
}

// La réplique de Junior : celle du premier résultat, puis celles des relances (la 4e : Junior choisit)
export function replique(libre, relances) {
  if (relances > 0) return T.tirage.relances[Math.min(relances, T.tirage.relances.length) - 1];
  if (libre.tard) return T.tirage.tard;
  if (libre.avant) return T.tirage.trouve(dureeTexte(libre.minutes), libre.avant.type);
  return T.tirage.libre;
}

export function etiquetteProposition(c, maintenant) {
  const etat = enRetard(c.carte, maintenant) ? T.tirage.enRetard : c.carte.colonne === 'en_cours' ? T.tirage.enCours : null;
  return [etiquetteCarte(c.carte), etat].filter(Boolean).join(' · ');
}

// L'afficheur qui roule colonne par colonne : les caractères déjà fixés restent
export function rouler(texte, avancement, alea = Math.random) {
  const pool = '0123456789ABCDEFGHJKLMNPRSTUVXYZ';
  const fixes = Math.floor(texte.length * avancement);
  return [...texte].map((c, i) => (c === ' ' || i < fixes ? c : pool[Math.floor(alea() * pool.length)])).join('');
}
```

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node --test 'tests/js/*.test.mjs'`
Expected : `pass 25`, `fail 0`.

- [ ] **Step 3: Write the failing screen tests**

`tests/e2e/test_aujourdhui.mjs` :

```js
import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';

async function ouvrirAujourdhui(page, contexte, serveur, quand, attendu) {
  await connecte(contexte, serveur);
  await heure(page, quand);
  await ouvrir(page, '/#/aujourdhui');
  await attendre(async () => (await texte(page, '.aujourdhui-haut .afficheur')).includes(attendu), `afficheur avec « ${attendu} »`);
}

const afficheur = (page) => texte(page, '.aujourdhui-haut .afficheur');

async function contient(page, morceaux) {
  const a = await afficheur(page);
  for (const m of morceaux) assert.ok(a.includes(m), `afficheur : « ${m} » (vu : ${a})`);
}

test("aujourd'hui, 15 h 45 entre deux cours : Entracte, 45 min libres, STANDBY TN01 à 16:30 en FA510", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Entracte');
  await contient(page, ['Mercredi 14 octobre', 'EN SCÈNE', '45 min libres', 'STANDBY', 'TN01 · TD', '16:30', '45 min', 'FA510']);
  assert.ok((await texte(page, '.talkback-texte')).startsWith('Bon après-midi.'));
  assert.deepEqual(await page.locator('.tranche-compte').allTextContents(), ['2', '1', '3', '2']);
  assert.deepEqual(await page.locator('.tranche-alerte').allTextContents(), ['1 en retard', '', 'médian J-7', '1 en retard']);
  assert.equal(await page.getByRole('button', { name: 'QYRIS : 2 à faire, 1 en retard' }).count(), 1);
});

test("pendant le TD de 17 h 10 : EN SCÈNE TN01 jusqu'à 19:30, fin dans 2 h 20, GO éteint", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T17:10:00+02:00', 'fin dans');
  await contient(page, ['TN01 · TD', '19:30', '2 h 20', 'FA510', 'MT23 · TD', 'lun. 19/10 16:30']);
  const go = page.getByRole('button', { name: 'GO, éteint pendant le cours' });
  assert.equal(await go.getAttribute('aria-disabled'), 'true');
  await go.click({ force: true });
  assert.equal(await page.locator('.tirage-ligne').count(), 0);
});

test("au réveil, 7 h 32 : « Rien pour l'instant », 2 cours, STANDBY NF93 dans 28 min, « Bonjour Colin. »", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T07:32:00+02:00', "Rien pour l'instant");
  await contient(page, ['2 cours', 'NF93 · TD', '08:00', '28 min', 'FA400']);
  assert.ok((await texte(page, '.talkback-texte')).startsWith('Bonjour Colin.'));
});

test("le soir, 21 h : « Plus de cours aujourd'hui », prochain cours lundi 19/10, « Bonsoir. »", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T21:00:00+02:00', "Plus de cours aujourd'hui");
  await contient(page, ['MT23 · TD', '16:30', 'lun. 19/10', 'FA420']);
  assert.ok((await texte(page, '.talkback-texte')).startsWith('Bonsoir.'));
});

test("jeudi 15 férié : le bandeau « Jour férié à l'UTC : pas de cours. »", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-15T10:00:00+02:00', 'Jeudi 15 octobre');
  await page.getByText("Jour férié à l'UTC : pas de cours.").waitFor();
});

test('sous le pli : conduite du jour (NF93 JOUÉ, TN01 STANDBY), À faire en 3 groupes, barre compacte', async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Entracte');
  const rangs = await page.locator('.conduite-rang').allTextContents();
  assert.equal(rangs.length, 3);
  assert.ok(rangs[0].includes('NF93 · TD') && rangs[0].includes('JOUÉ'), rangs[0]);
  assert.ok(rangs[1].includes('Déjeuner avec Léon') && rangs[1].includes('Perso'), rangs[1]);
  assert.ok(rangs[2].includes('TN01 · TD') && rangs[2].includes('STANDBY'), rangs[2]);
  assert.deepEqual(await page.locator('.rang-etiquette').allTextContents(), ['En retard', "Aujourd'hui", 'Urgent']);
  assert.deepEqual(await page.locator('.rang-groupe').first().locator('.carte-titre').allTextContents(), ['LinkedIn : mettre à jour mon profil', 'Campagne LinkedIn : programmer les posts']);
  assert.equal(await texte(page, '.rang-groupe .carte-echeance.retard'), 'en retard depuis le 05/10');
  await page.locator('.sous-le-pli .rang-groupe').last().scrollIntoViewIfNeeded();
  await attendre(() => page.locator('.compacte').evaluate((e) => e.classList.contains('visible')), 'barre compacte visible');
});

test("GO : la proposition tombe, 3 relances avec leurs répliques, puis Junior choisit ; « C'est parti » ouvre la carte", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Entracte');
  // Les 5 meilleures (scores 140, 140, 80, 40, 25), puis les autres candidates : le tirage se fait parmi les
  // 5 meilleures pas encore proposées, une moins bonne peut donc sortir après quelques relances
  const meilleures = [
    'Campagne LinkedIn : envoyer un mail de test.', 'Terminer : LinkedIn : mettre à jour mon profil', 'Terminer : TN01 : rendre le TP',
    'Cabaret : valider les horaires avec la team bouffe.', "A'point : vérifier le site en ligne, téléphone compris.",
  ];
  const possibles = [...meilleures, 'Jarvis Jr. : créer un compte cron-job.org.', 'Terminer : MT23 : préparer le médian', 'Formation 2 : relire le deck en mode présentation.'];
  const repliques = ["Je t'ai trouvé 35 min avant ton TD.", "D'accord, une autre.", "Encore ? Tu cherches la tâche qui se fait toute seule. Elle n'existe pas.", "Dernière proposition. Après, c'est moi le patron.", "J'ai choisi. On y va ?"];
  await page.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).click();
  const vues = [];
  for (let i = 0; i < 5; i++) {
    await page.locator('.tirage-texte').waitFor();
    assert.equal(await texte(page, '.tirage-ligne'), '35 MIN AVANT TN01');
    assert.equal(await texte(page, '.talkback-texte'), repliques[i]);
    vues.push(await texte(page, '.tirage-texte'));
    if (i < 4) await page.getByRole('button', { name: 'Autre chose' }).click();
  }
  assert.equal(await page.getByRole('button', { name: 'Autre chose' }).count(), 0);
  assert.equal(new Set(vues).size, 5, 'jamais deux fois la même proposition');
  for (const vue of vues) assert.ok(possibles.includes(vue), vue);
  assert.ok(meilleures.includes(vues[0]), 'la première proposition est parmi les 5 meilleures');
  await page.getByRole('button', { name: "C'est parti" }).click();
  await page.getByText("C'est parti. Coche l'étape quand c'est fait, je note.").waitFor();
  assert.match(new URL(page.url()).hash, /^#\/carte\/ca\d{22}/);
  await attendre(() => serveur.appels('tirage/parti').length === 1, 'tirage/parti envoyé');
});

test("mouvement réduit : la proposition s'affiche tout de suite, sans défilement", async ({ page, contexte, serveur }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Entracte');
  await page.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).click();
  assert.equal(await page.locator('.tirage-attente').count(), 0);
  assert.equal(await page.locator('.tirage-texte').count(), 1);
});

test('cours dans 10 min : « Le meilleur choix : y aller. », puis Retour', async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T16:20:00+02:00', 'Entracte');
  await page.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).click();
  assert.equal(await texte(page, '.talkback-texte'), 'Ton cours commence dans 10 min. Le meilleur choix : y aller.');
  await page.getByRole('button', { name: 'Retour' }).click();
  await page.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).waitFor();
});

test('to-do vide : « Plus rien dans ta to-do… »', async ({ page, contexte, serveur }) => {
  serveur.etat.donnees.trello.cartes = [];
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Entracte');
  await page.getByText("Rien d'urgent. Profite, ou jette un œil à l'onglet Tâches.").waitFor();
  await page.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).click();
  assert.equal(await texte(page, '.talkback-texte'), 'Plus rien dans ta to-do. Génie ou amnésie ? Ajoute une tâche, ou repose-toi.');
});

test("agenda pas connecté : l'afficheur le dit, sans erreur", async ({ page, contexte, serveur }) => {
  serveur.etat.donnees.agenda = { recupere_le: null, erreur: null, google_connecte: false, jours: {} };
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Agenda pas encore connecté. Ça se fait dans Moi.');
});
```

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs aujourd ; node tests/e2e/lancer.mjs GO`
Expected : échecs (écran d'attente).

- [ ] **Step 4: Implement the screen**

`site/js/ecrans/aujourdhui.js` :

```js
// Aujourd'hui (SPEC § 8.1) : l'écran de conduite, Junior, les 4 tranches et GO ; sous le pli, la conduite
// du jour et l'À faire. Le tirage (§ 8.3) vit ici : GO, Autre chose, C'est parti.
import { h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { DOMAINES, NOMS, SCOTCH, compteurs, groupesAFaire } from '../lib/cartes.js';
import { etatConduite, jourDe, medianProche, nomCreneau } from '../lib/conduite.js';
import { phraseDuJour, salutation } from '../lib/phrases.js';
import { IMMINENT, PAS, RELANCES, candidats, etiquetteProposition, ligneAfficheur, replique, rouler, tempsLibre, texteProposition, tirer } from '../lib/tirage.js';
import { dateCourte, dateLongue, dureeTexte, enParis, heureParis } from '../lib/temps.js';
import { regle, tampon, voyant } from './commun.js';
import { ligneCarte } from './taches.js';

const A = T.aujourdhui;
const ANGLES = { qyris: '-1.6deg', asso: '0.9deg', etudes: '-0.7deg', perso: '1.4deg' };

// Le tirage en cours : { phase: 'roule' | 'resultat' | 'special', liste, proposes, choix, libre, relances, replique, ligne }
let tirage = null;
let minuteur = null;
let observateur = null;

export const occupe = () => tirage?.phase === 'roule';

export function quitter() {
  clearInterval(minuteur);
  tirage = null;
  observateur?.disconnect();
}

const ligne = (...enfants) => h('div', { class: 'afficheur-ligne' }, enfants);
const titre = (texte) => h('span', { class: 'afficheur-titre' }, texte);
const cote = (texte) => texte && h('span', { class: 'afficheur-cote' }, texte);
const trait = () => h('div', { class: 'afficheur-trait' });
const heureDe = (iso) => heureParis(new Date(iso));

function chiffres(heure, legende, salle) {
  return [
    h('div', { class: 'chiffres-ligne' }, h('span', { class: 'chiffres' }, heure), h('span', { class: 'chiffres-cote' }, legende.map((l, i) => [i > 0 && h('br'), l]))),
    salle && h('div', { class: 'chiffres' }, salle),
  ];
}

function quand(standby) {
  if (standby.quand === 'aujourdhui') return [A.dans, dureeTexte(standby.dans)];
  return [standby.quand === 'demain' ? A.demain : dateCourte(standby.cours.jour)];
}

function afficheurConduite(c, agenda) {
  if (agenda === 'absent') return ligne(voyant(A.standby), titre(T.agenda.chargement));
  if (agenda === 'non_connecte') return ligne(voyant(A.standby), titre(A.agendaNonConnecte));
  const s = c.scene;
  const suite = c.standby;
  if (s.type === 'cours') {
    const coteSuite = suite && (suite.quand === 'aujourdhui' ? [heureDe(suite.cours.debut), suite.cours.salle].filter(Boolean).join(' · ') : `${quand(suite)[0]} ${heureDe(suite.cours.debut)}`);
    return [
      ligne(voyant(A.enScene, true), titre(nomCreneau(s.cours))),
      chiffres(heureDe(s.cours.fin), [A.finDans, dureeTexte(s.finDans)], s.cours.salle),
      trait(),
      ligne(voyant(A.standby), titre(suite ? nomCreneau(suite.cours) : A.rienDePrevu), cote(coteSuite)),
    ];
  }
  const enScene = { entracte: [A.entracte, s.libres !== undefined && A.libres(dureeTexte(s.libres))], rien: [A.rien, A.nbCours(s.nbCours)], fini: [A.fini, null] }[s.type];
  return [
    ligne(voyant(A.enScene), titre(enScene[0]), cote(enScene[1])),
    trait(),
    suite ? [ligne(voyant(A.standby, true), titre(nomCreneau(suite.cours))), chiffres(heureDe(suite.cours.debut), quand(suite), suite.cours.salle)] : ligne(voyant(A.standby), titre(A.rienDePrevu)),
  ];
}

function afficheurTirage(c, maintenant) {
  const suite = c.standby?.quand === 'aujourdhui' ? c.standby : null;
  return [
    suite && [ligne(voyant(A.standby), titre(nomCreneau(suite.cours)), cote([heureDe(suite.cours.debut), suite.cours.salle].filter(Boolean).join(' · '))), trait()],
    ligne(voyant(A.proposition, true)),
    h('div', { class: 'tirage-ligne' }, tirage.ligne),
    tirage.phase === 'roule'
      ? h('div', { class: 'tirage-attente', 'aria-hidden': 'true' }, '· · · · · · · ·')
      : h('div', { class: 'tirage-resultat' },
        h('p', { class: 'tirage-texte' }, texteProposition(tirage.choix)),
        h('p', { class: `tirage-domaine d-${tirage.choix.carte.domaine}` }, h('span', { class: 'carre' }), etiquetteProposition(tirage.choix, maintenant))),
  ];
}

function talkback(c, cartes, jours, maintenant) {
  let paroles = tirage?.replique;
  if (!paroles) {
    const premier = c.premierCours ? enParis(new Date(c.premierCours.debut)) : null;
    const faits = {
      jour: c.aujourdhui,
      nbCours: c.nbCours,
      premierCours: premier ? premier.heure * 60 + premier.minute : null,
      nbRetard: Object.values(compteurs(cartes, maintenant)).reduce((s, k) => s + k.retard, 0),
      medianDans: medianProche(jours, maintenant)?.dans ?? null,
    };
    paroles = [h('strong', {}, `${salutation(maintenant)} `), phraseDuJour(faits)];
  }
  return h('div', { class: 'talkback' },
    h('div', { class: 'talkback-qui' }, icone('micro', 14, 2), h('span', {}, A.junior)),
    h('p', { class: 'talkback-texte', 'aria-live': 'polite' }, paroles),
    c.bandeaux.map((b) => h('p', { class: 'bandeau' }, icone(b.type === 'ferie' ? 'ferie' : 'agenda', 16), b.texte)));
}

function tranches(app, cartes, jours, maintenant) {
  const comptes = compteurs(cartes, maintenant);
  const median = medianProche(jours, maintenant);
  return h('div', { class: 'tranches' }, DOMAINES.map((d) => {
    const k = comptes[d];
    const medianIci = d === 'etudes' && median ? median.dans : null;
    let alerte = h('span', { class: 'tranche-alerte' });
    if (k.retard > 0) alerte = h('span', { class: 'tranche-alerte retard' }, h('span', { class: 'point' }), A.enRetard(k.retard));
    else if (medianIci !== null) alerte = h('span', { class: 'tranche-alerte' }, h('span', { class: 'anneau' }), A.median(medianIci));
    return h('button', { type: 'button', class: `tranche d-${d}`, 'aria-label': A.tranche(NOMS[d], k.ouvertes, k.retard, medianIci), onclick: () => app.aller(`#/taches/${d}`) },
      h('span', { class: 'scotch', vars: { '--angle': ANGLES[d] } }, SCOTCH[d]),
      h('span', { class: 'tranche-compte' }, String(k.ouvertes)),
      h('span', { class: 'tranche-legende' }, A.aFaire),
      alerte,
      h('span', { class: 'fader', 'aria-hidden': 'true' }, h('span', { class: 'fader-piste' }), h('span', { class: 'fader-graduations' }), h('span', { class: 'fader-graduations droite' }), h('span', { class: 'fader-poignee' })));
  }));
}

function panneauGo(app, libre) {
  let contenu;
  if (tirage?.phase === 'resultat') {
    contenu = h('div', { class: 'go-choix' },
      tirage.relances <= RELANCES && h('button', { type: 'button', class: 'bouton bouton-haut', onclick: () => relancer(app) }, T.tirage.autreChose),
      h('div', { class: 'cuvette' }, h('button', { type: 'button', class: 'bouton-allume bouton-parti', onclick: () => parti(app) }, T.tirage.cestParti)));
  } else if (tirage?.phase === 'special') {
    contenu = h('button', {
      type: 'button', class: 'bouton bouton-haut',
      onclick: () => {
        tirage = null;
        app.rendre();
      },
    }, T.tirage.retour);
  } else {
    const roule = tirage?.phase === 'roule';
    contenu = [
      h('div', { class: 'cuvette' }, h('button', {
        type: 'button', class: `go${roule ? ' appuye' : ''}`, 'aria-label': libre.enCours ? A.goEteint : A.goAria,
        'aria-disabled': libre.enCours || roule ? 'true' : null, onclick: () => go(app),
      }, A.go)),
      h('p', { class: 'go-legende' }, A.goLegende),
    ];
  }
  return h('div', { class: 'go-panneau' }, contenu);
}

function go(app) {
  if (tirage?.phase === 'roule') return;
  const maintenant = app.maintenant();
  const v = app.vue();
  const libre = tempsLibre(v?.agenda.jours ?? {}, maintenant);
  if (libre.enCours) return;
  const base = { relances: 0, proposes: new Set(), choix: null, libre, ligne: '' };
  if (libre.coursDans !== null && libre.coursDans < IMMINENT) {
    tirage = { ...base, phase: 'special', replique: T.tirage.coursImminent(dureeTexte(libre.coursDans)) };
    app.rendre();
    return;
  }
  const liste = candidats(v?.trello.cartes ?? [], maintenant);
  if (liste.length === 0) {
    tirage = { ...base, phase: 'special', replique: T.tirage.vide };
    app.rendre();
    return;
  }
  tirage = { ...base, liste };
  lancer(app);
}

function relancer(app) {
  tirage.relances++;
  lancer(app);
}

// STANDBY, l'afficheur roule colonne par colonne, puis la proposition tombe (instantané en mouvement réduit)
function lancer(app) {
  tirage.choix = tirer(tirage.liste, tirage.proposes, Math.random);
  tirage.proposes.add(tirage.choix.cle);
  const cible = ligneAfficheur(tirage.libre);
  const fin = () => {
    clearInterval(minuteur);
    Object.assign(tirage, { phase: 'resultat', ligne: cible, replique: replique(tirage.libre, tirage.relances) });
    app.rendre();
  };
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
    fin();
    return;
  }
  Object.assign(tirage, { phase: 'roule', ligne: rouler(cible, 0), replique: T.tirage.cherche });
  app.rendre();
  let pas = 0;
  clearInterval(minuteur);
  minuteur = setInterval(() => {
    pas++;
    if (pas >= PAS) {
      fin();
      return;
    }
    tirage.ligne = rouler(cible, pas / PAS);
    const el = document.querySelector('.aujourdhui-haut .tirage-ligne');
    if (el) el.textContent = tirage.ligne;
  }, 55);
}

function parti(app) {
  const { carte, element } = tirage.choix;
  quitter();
  app.ecrire('tirage/parti', { carte: carte.id, element: element?.id ?? null });
  app.message(T.tirage.parti);
  app.aller(`#/carte/${carte.id}${element ? `?etape=${element.id}` : ''}`);
}

// Quand l'écran de conduite sort de la vue, une barre compacte garde le prochain cours et un petit GO
function compacte(app, c, libre, agenda) {
  let s = null;
  if (c.scene.type === 'cours') s = { voyant: A.enScene, nom: nomCreneau(c.scene.cours), cote: `${A.finDans} ${dureeTexte(c.scene.finDans)}`, chiffres: [heureDe(c.scene.cours.fin), c.scene.cours.salle] };
  else if (c.standby) s = { voyant: A.standby, nom: nomCreneau(c.standby.cours), cote: quand(c.standby).join(' '), chiffres: [heureDe(c.standby.cours.debut), c.standby.cours.salle] };
  return h('div', { class: 'compacte', inert: true, 'aria-hidden': 'true' },
    h('div', { class: 'compacte-barre' },
      h('div', { class: 'afficheur afficheur-compact' },
        s ? [ligne(voyant(s.voyant, true), titre(s.nom), cote(s.cote)), h('div', { class: 'chiffres-compacts' }, s.chiffres.filter(Boolean).join('  '))]
          : ligne(voyant(A.standby), titre(agenda === 'non_connecte' ? A.agendaNonConnecte : A.rienDePrevu))),
      h('div', { class: 'cuvette' }, h('button', {
        type: 'button', class: 'go go-petit', 'aria-label': libre.enCours ? A.goEteint : A.goAria, 'aria-disabled': libre.enCours ? 'true' : null,
        onclick: () => {
          document.getElementById('ecran').scrollTo({ top: 0 });
          go(app);
        },
      }, A.go))));
}

function sousLePli(app, c, cartes, jours, maintenant) {
  const prochain = c.standby?.cours;
  const rangs = jourDe(jours, c.aujourdhui).evenements.map((e) => {
    const passe = !e.journee && new Date(e.fin) <= maintenant;
    const estProchain = prochain && e.debut === prochain.debut && e.titre === prochain.titre;
    const details = e.journee ? [A.journee] : [e.source === 'perso' ? A.perso : e.salle, A.jusqua(heureDe(e.fin))];
    return h('div', { class: `rang conduite-rang${passe ? ' passe' : ''}` },
      h('span', { class: 'rang-heure' }, e.journee ? '' : heureDe(e.debut)),
      h('div', { class: 'rang-corps' },
        h('div', { class: 'rang-texte' }, h('p', { class: 'rang-titre' }, nomCreneau(e)), h('p', { class: 'rang-sous' }, details.filter(Boolean).join(' · '))),
        passe ? tampon(A.joue, e.debut + e.titre) : estProchain && voyant(A.standby, false, true)));
  });
  const g = groupesAFaire(cartes, maintenant);
  const groupe = (cle, liste) => liste.length > 0 && h('div', { class: 'rang rang-groupe' },
    h('span', { class: `rang-etiquette${cle === 'retard' ? ' retard' : ''}` }, cle === 'retard' && h('span', { class: 'point' }), A.groupes[cle]),
    h('div', { class: 'rang-cartes' }, liste.map((carte) => ligneCarte(app, carte, { minuscule: true }))));
  const rien = g.retard.length + g.aujourdhui.length + g.urgent.length === 0;
  return h('div', { class: 'sous-le-pli' },
    h('h2', { class: 'section-titre' }, A.conduite), regle(),
    rangs.length > 0 ? rangs : h('p', { class: 'vide' }, A.rienDePrevu),
    h('h2', { class: 'section-titre' }, A.aFaireTitre), regle(),
    rien ? h('p', { class: 'vide' }, A.rienDUrgent) : [groupe('retard', g.retard), groupe('aujourdhui', g.aujourdhui), groupe('urgent', g.urgent)]);
}

export function rendre(app) {
  const maintenant = app.maintenant();
  const v = app.vue();
  const jours = v?.agenda.jours ?? {};
  const cartes = v?.trello.cartes ?? [];
  const c = etatConduite(jours, maintenant);
  const libre = tempsLibre(jours, maintenant);
  if (tirage && libre.enCours) quitter();
  let agenda = 'ok';
  if (v === null) agenda = 'absent';
  else if (!v.agenda.google_connecte && Object.keys(jours).length === 0) agenda = 'non_connecte';
  return h('div', { class: 'aujourdhui' },
    compacte(app, c, libre, agenda),
    h('div', { class: 'aujourdhui-haut' },
      h('section', { class: 'console', 'aria-label': A.ecranConduite },
        h('div', { class: 'afficheur' },
          h('div', { class: 'afficheur-date' }, dateLongue(c.aujourdhui)),
          tirage?.choix ? afficheurTirage(c, maintenant) : afficheurConduite(c, agenda)),
        talkback(c, cartes, jours, maintenant)),
      tranches(app, cartes, jours, maintenant),
      panneauGo(app, libre)),
    sousLePli(app, c, cartes, jours, maintenant));
}

export function apres(app, route, ecran) {
  observateur?.disconnect();
  const grand = ecran.querySelector('.aujourdhui-haut .console');
  const barre = ecran.querySelector('.compacte');
  if (!grand || !barre || !('IntersectionObserver' in window)) return;
  observateur = new IntersectionObserver(([entree]) => {
    const visible = !entree.isIntersecting;
    barre.classList.toggle('visible', visible);
    barre.inert = !visible;
    if (visible) barre.removeAttribute('aria-hidden');
    else barre.setAttribute('aria-hidden', 'true');
  }, { root: ecran });
  observateur.observe(grand);
}
```

`site/js/app.js` : sous les imports des écrans, ajouter `import * as aujourdhui from './ecrans/aujourdhui.js';` et dans `ECRANS`, remplacer `aujourdhui: attente,` par `aujourdhui,`.

À la fin de `site/css/app.css` :

```css
/* Aujourd'hui : écran de conduite */
.aujourdhui { position: relative; }
.aujourdhui-haut { min-height: max(600px, 100cqh); display: flex; flex-direction: column; gap: 8px; padding-bottom: 8px; }
.aujourdhui-haut > .console { flex: none; }
.afficheur-date { font-family: var(--mono); font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; color: var(--ambre-doux); }
.afficheur-ligne { display: flex; align-items: center; gap: 10px; min-height: 22px; margin-top: 12px; }
.afficheur-trait + .afficheur-ligne { margin-top: 0; }
.afficheur-titre { min-width: 0; font-size: 17px; font-weight: 600; color: var(--ambre); overflow-wrap: anywhere; }
.afficheur-cote { margin-left: auto; padding-left: 8px; font-family: var(--mono); font-size: 12px; color: var(--ambre-doux); white-space: nowrap; }
.afficheur-trait { height: 1px; margin: 11px 0 10px; background: var(--ambre-trait); }
.chiffres-ligne { display: flex; align-items: flex-end; justify-content: space-between; gap: 8px; margin-top: 8px; }
.chiffres { overflow: hidden; font-family: var(--mono); font-size: 56px; font-weight: 500; font-stretch: 87.5%; letter-spacing: -0.03em; line-height: 0.95; color: var(--ambre); font-variant-numeric: tabular-nums; white-space: nowrap; text-overflow: ellipsis; }
.chiffres-ligne + .chiffres { margin-top: 4px; }
.chiffres-cote { flex: none; padding-bottom: 4px; font-family: var(--mono); font-size: 15px; line-height: 1.3; text-align: right; color: var(--ambre); }
.talkback { padding: 10px 4px 2px; }
.talkback-qui { display: flex; align-items: center; gap: 6px; color: var(--texte-2); font-family: var(--mono); font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase; }
.talkback-texte { margin-top: 5px; font-size: calc(16 * var(--px)); line-height: 1.36; font-stretch: 94%; }
.talkback-texte strong { font-weight: 700; }
.bandeau { display: flex; align-items: center; gap: 8px; margin-top: 8px; padding: 8px 10px; border-radius: 8px; background: var(--console); font-size: calc(14 * var(--px)); font-weight: 600; }

/* Aujourd'hui : les 4 tranches */
.tranches { flex: 1 1 0; min-height: 176px; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.tranche { min-height: 0; overflow: hidden; display: flex; flex-direction: column; align-items: center; padding: 10px 8px 9px; border: 0; border-radius: 10px; background: var(--panneau); color: var(--texte); text-align: center; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 6px 14px rgba(0, 0, 0, 0.3); }
.tranche .scotch { align-self: stretch; padding: 0; }
.tranche-compte { margin-top: 14px; font-family: var(--mono); font-size: 30px; font-weight: 600; font-stretch: 87.5%; line-height: 1; color: var(--domaine); }
.tranche-legende { margin-top: 4px; font-size: 12px; color: var(--texte-2); }
.tranche-alerte { display: flex; align-items: center; gap: 5px; height: 15px; margin-top: 8px; font-size: 11.5px; font-weight: 600; font-stretch: 88%; white-space: nowrap; }
.tranche-alerte.retard { color: var(--rouge-texte); }
.fader { position: relative; align-self: stretch; flex: 1 1 0; min-height: 36px; margin-top: 12px; }
.fader-piste { position: absolute; left: 50%; top: 2px; bottom: 2px; width: 4px; margin-left: -2px; border-radius: 2px; background: #0B0C0E; box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.9); }
.fader-graduations { position: absolute; top: 2px; bottom: 2px; left: 9px; width: 7px; background: repeating-linear-gradient(to bottom, var(--contour) 0, var(--contour) 1px, transparent 1px, transparent 9px); }
.fader-graduations.droite { left: auto; right: 9px; }
.fader-poignee { position: absolute; left: 50%; bottom: 6%; width: 36px; height: 16px; margin-left: -18px; border-radius: 3px; background: var(--domaine); box-shadow: 0 3px 5px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.4), inset 0 -2px 0 rgba(0, 0, 0, 0.2); }

/* Aujourd'hui : GO et le tirage */
.go-panneau { flex: none; min-height: 96px; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 16px 8px; border-radius: 14px; background: var(--panneau); box-shadow: var(--ombre-panneau); }
.go { display: block; width: 175px; height: 64px; border: 0; border-radius: 10px; background: var(--ambre); color: var(--graphite); font-family: 'Archivo', sans-serif; font-stretch: 75%; font-weight: 800; font-size: 38px; letter-spacing: 0.06em; line-height: 1; box-shadow: var(--ombre-allume); }
.go.appuye, .go:not([aria-disabled='true']):active { background: var(--ambre-appuye); transform: translateY(2px); box-shadow: inset 0 3px 6px rgba(0, 0, 0, 0.35); }
.go[aria-disabled='true']:not(.appuye) { background: var(--console); color: var(--ambre-doux); box-shadow: var(--ombre-bouton); }
.go-petit { width: 56px; height: 56px; border-radius: 9px; font-size: 20px; }
.go-legende { margin-top: 6px; font-size: calc(14 * var(--px)); color: var(--texte-2); }
.go-choix { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 4px 0; }
.bouton-haut { min-height: 60px; }
.bouton-parti { min-height: 60px; padding: 0 26px; }
.tirage-ligne { margin-top: 10px; overflow: hidden; font-family: var(--mono); font-size: 19px; font-weight: 600; letter-spacing: 0.04em; white-space: pre; color: var(--ambre); }
.tirage-attente { height: 78px; margin-top: 8px; font-family: var(--mono); font-size: 19px; letter-spacing: 0.3em; color: var(--ambre-sombre); }
.tirage-resultat { animation: revele 420ms cubic-bezier(0.16, 1, 0.3, 1) both; }
@keyframes revele { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(0 0 0 0); } }
.tirage-texte { min-height: 52px; margin-top: 8px; font-size: 20px; font-weight: 600; line-height: 1.28; color: var(--ambre); overflow-wrap: anywhere; }
.tirage-domaine { display: flex; align-items: center; gap: 7px; margin-top: 8px; font-family: var(--mono); font-size: 11px; letter-spacing: 0.06em; color: var(--ambre-doux); }

/* Aujourd'hui : barre compacte et sous le pli */
.compacte { position: sticky; top: 0; z-index: 5; height: 0; }
.compacte-barre { position: absolute; top: 0; left: -16px; right: -16px; display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: var(--graphite); border-bottom: 1px solid var(--panneau); transform: translateY(-110%); transition: transform 160ms ease-out; }
.compacte.visible .compacte-barre { transform: none; }
.afficheur-compact { flex: 1 1 auto; min-width: 0; padding: 8px 12px 9px; }
.afficheur-compact .afficheur-ligne { margin-top: 0; gap: 8px; }
.afficheur-compact .afficheur-titre { font-size: 15px; }
.chiffres-compacts { margin-top: 6px; font-family: var(--mono); font-size: 28px; font-weight: 500; font-stretch: 87.5%; letter-spacing: -0.02em; line-height: 1; color: var(--ambre); white-space: pre; }
.compacte .cuvette { flex: none; border-radius: 12px; }
.sous-le-pli > .section-titre:first-child { margin-top: 14px; }
.rang-heure { font-family: var(--mono); font-size: 15px; font-stretch: 87.5%; }
.rang-corps { display: flex; align-items: center; gap: 10px; }
.rang-texte { flex: 1 1 auto; min-width: 0; padding: 6px 0; }
.rang-titre { font-size: calc(15 * var(--px)); font-weight: 600; overflow-wrap: anywhere; }
.rang-sous { font-size: calc(13 * var(--px)); color: var(--texte-2); }
.conduite-rang.passe .rang-heure, .conduite-rang.passe .rang-titre { color: var(--texte-2); }
.conduite-rang.passe .rang-sous { color: var(--texte-3); }
.rang-groupe { align-items: start; padding-top: 10px; border-bottom: 0; }
.rang-etiquette { display: flex; align-items: center; gap: 6px; padding-top: 12px; font-size: calc(13 * var(--px)); font-weight: 600; }
.rang-etiquette.retard { color: var(--rouge-texte); }
.rang-cartes { display: flex; flex-direction: column; gap: 8px; }
```

- [ ] **Step 5: Run the tests**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node --test 'tests/js/*.test.mjs' && node tests/e2e/lancer.mjs`
Expected : `pass 25`, `fail 0` ; `28 réussi(s), 0 échec(s)`.

- [ ] **Step 6: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add site/js site/css tests/js tests/e2e && git commit -m "Lot 1 : Aujourd'hui (écran de conduite, Junior, tranches, sous le pli) et le tirage GO

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---
### Task 8 : la feuille « Noter » (capture)

Le bouton + ouvre une feuille du bas (maquette « Noter, bouton + ») : titre (dictable au micro du clavier), domaine sur scotch, Urgent, échéance avec heure facultative, « Ajouter ». La carte s'affiche tout de suite dans « À faire », en attente du réseau s'il manque.

**Files:**
- Create: `site/js/ecrans/capture.js`
- Modify: `site/js/app.js` (enregistrement de la feuille), `site/css/app.css` (à la fin)
- Test: `tests/e2e/test_capture.mjs`

**Interfaces:**
- Consumes : `app.ouvrirCapture(domaine)`, `app.fermerCapture()`, `app.rendreCapture()`, `app.ecrire()`, `app.message()`, `app.etat.capture` (`{ domaine, titre, urgent, date, heure, erreur }`), `isoParis()`, `DOMAINES`, `NOMS`, `SCOTCH`, `T.capture`.
- Produces : module de feuille `rendre(app, f)` et `apres(conteneur, f)` (le champ reçoit le focus à l'ouverture seulement). Domaine proposé à l'ouverture : celui de l'onglet Tâches filtré, sinon le dernier utilisé (`localStorage` `jr-domaine`, préférence de confort seulement).

- [ ] **Step 1: Write the failing tests**

`tests/e2e/test_capture.mjs` :

```js
import { test, assert, heure, connecte, ouvrir, attendre } from './cadre.mjs';

async function preparer(page, contexte, serveur, chemin) {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
  await ouvrir(page, chemin);
  await attendre(() => serveur.appels('donnees').length > 0, 'données demandées');
}

test("capture : titre, Études, Urgent et échéance ; confirmation ; la carte part et s'affiche dans Tâches", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/aujourdhui');
  await page.getByRole('button', { name: 'Noter quelque chose' }).click();
  const feuille = page.getByRole('dialog', { name: 'Noter' });
  await feuille.waitFor();
  await feuille.getByLabel('Une tâche, un rappel').fill('  Réviser le   chapitre 3  ');
  await feuille.getByRole('button', { name: 'Études' }).click();
  await feuille.getByRole('button', { name: 'Urgent' }).click();
  await feuille.getByLabel('Échéance').fill('2026-10-20');
  await feuille.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText('Ajouté dans Études · À faire. Jarvis le verra à ta prochaine session.').waitFor();
  assert.equal(await page.getByRole('dialog').count(), 0);
  await attendre(() => serveur.appels('taches/ajouter').length === 1, 'capture reçue');
  const { corps } = serveur.appels('taches/ajouter')[0];
  assert.deepEqual([corps.domaine, corps.titre, corps.urgent, corps.echeance], ['etudes', 'Réviser le chapitre 3', true, '2026-10-20']);
  await page.getByRole('link', { name: 'Tâches' }).click();
  await page.getByText('Réviser le chapitre 3').waitFor();
});

test("capture : sans titre ou sans domaine, un message et rien n'est envoyé ; Échap ferme la feuille", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/aujourdhui');
  await page.getByRole('button', { name: 'Noter quelque chose' }).click();
  await page.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText('Il me faut un titre (300 caractères au plus).').waitFor();
  await page.getByLabel('Une tâche, un rappel').fill('Appeler Sébastien');
  await page.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText('Choisis un domaine.').waitFor();
  await page.keyboard.press('Escape');
  assert.equal(await page.getByRole('dialog').count(), 0);
  assert.equal(serveur.appels('taches/ajouter').length, 0);
});

test('capture hors ligne : « Pas de réseau… », carte en attente, puis une seule carte au retour du réseau, même si la réponse se perd', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/taches/perso');
  await page.getByText('3 CARTES').waitFor();
  await contexte.setOffline(true);
  await page.getByRole('button', { name: 'Noter quelque chose' }).click();
  const feuille = page.getByRole('dialog', { name: 'Noter' });
  assert.equal(await feuille.getByRole('button', { name: 'Perso' }).getAttribute('aria-pressed'), 'true');
  await feuille.getByLabel('Une tâche, un rappel').fill('Dentiste');
  await feuille.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText("Pas de réseau. Je l'envoie dès que ça revient.").waitFor();
  await page.getByText('Dentiste').waitFor();
  await page.getByText('en attente du réseau').waitFor();
  serveur.etat.perdre = 'taches/ajouter';
  await contexte.setOffline(false);
  await attendre(() => serveur.appels('taches/ajouter').length >= 1, 'premier envoi');
  // Chromium renvoie parfois seul une requête restée sans réponse ; sinon, c'est la file de l'app qui renvoie
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await attendre(() => serveur.appels('taches/ajouter').length >= 2, 'renvoi');
  assert.equal(new Set(serveur.appels('taches/ajouter').map((a) => a.corps.id_client)).size, 1, 'même id_client à chaque envoi');
  await attendre(async () => !(await page.locator('#statut').isVisible()), 'plus rien en attente');
  assert.equal(serveur.etat.donnees.trello.cartes.filter((c) => c.titre === 'Dentiste').length, 1);
  assert.equal(await page.getByText('Dentiste').count(), 1);
});
```

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs capture`
Expected : 3 échecs (le + n'ouvre rien).

- [ ] **Step 2: Implement the sheet**

`site/js/ecrans/capture.js` :

```js
// La feuille « Noter » (bouton +, CONTENU § 4) : une tâche dans l'un des 4 tableaux. Le choix « Rappel »
// arrive au lot 2. La carte s'affiche tout de suite ; sans réseau, elle part dès qu'il revient.
import { h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { DOMAINES, NOMS, SCOTCH } from '../lib/cartes.js';
import { isoParis } from '../lib/temps.js';

const C = T.capture;
const ANGLES = { qyris: '-1.4deg', asso: '0.9deg', etudes: '-0.7deg', perso: '1.4deg' };
const TITRE_MAX = 300;

// Le champ reçoit le focus à l'ouverture seulement (le clavier de l'iPhone ne doit pas revenir à chaque
// choix) ; ensuite, le focus reste dans la feuille, pour VoiceOver et pour la touche Échap
export function apres(conteneur, f) {
  if (!f.focus) {
    f.focus = true;
    conteneur.querySelector('#capture-titre')?.focus();
  } else if (!conteneur.contains(document.activeElement)) {
    conteneur.querySelector('.feuille')?.focus({ preventScroll: true });
  }
}

async function ajouter(app, f) {
  const titre = f.titre.trim().replace(/\s+/gu, ' ');
  if (titre === '' || titre.length > TITRE_MAX) {
    f.erreur = C.titreInvalide;
    app.rendreCapture();
    return;
  }
  if (!DOMAINES.includes(f.domaine)) {
    f.erreur = C.choisirDomaine;
    app.rendreCapture();
    return;
  }
  try {
    localStorage.setItem('jr-domaine', f.domaine);
  } catch {
    // simple préférence, sans importance
  }
  const horsLigne = app.etat.horsLigne || !navigator.onLine;
  app.fermerCapture();
  await app.ecrire('taches/ajouter', { domaine: f.domaine, titre, urgent: f.urgent, echeance: f.date ? (f.heure ? `${f.date}T${f.heure}` : f.date) : null }, {
    id: `local-${crypto.randomUUID()}`,
    echeance_iso: f.date ? isoParis(f.date, f.heure || '18:00') : null,
    cree_le: app.maintenant().toISOString(),
  });
  app.message(horsLigne ? C.horsLigne : C.ajoute(NOMS[f.domaine]));
}

export function rendre(app, f) {
  const redessiner = () => app.rendreCapture();
  const champ = h('textarea', {
    id: 'capture-titre', class: 'champ capture-champ', rows: 2, placeholder: C.champ, maxlength: TITRE_MAX,
    oninput: (e) => { f.titre = e.target.value; },
  });
  champ.value = f.titre;
  const date = h('input', {
    id: 'capture-date', class: 'champ champ-date', type: 'date',
    onchange: (e) => {
      f.date = e.target.value;
      redessiner();
    },
  });
  date.value = f.date;
  const heure = f.date && h('input', { id: 'capture-heure', class: 'champ champ-date', type: 'time', onchange: (e) => { f.heure = e.target.value; } });
  if (heure) heure.value = f.heure;
  return h('div', {
    class: 'voile',
    onclick: (e) => { if (e.target === e.currentTarget) app.fermerCapture(); },
    onkeydown: (e) => { if (e.key === 'Escape') app.fermerCapture(); },
  },
    h('section', { class: 'feuille', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'capture-nom', tabindex: '-1' },
      h('div', { class: 'feuille-poignee', 'aria-hidden': 'true' }),
      h('div', { class: 'feuille-entete' },
        h('h1', { id: 'capture-nom', class: 'feuille-titre' }, C.titre),
        h('button', { type: 'button', class: 'bouton-carre bouton-console', 'aria-label': C.fermer, onclick: () => app.fermerCapture() }, icone('fermer', 18, 2.25))),
      h('label', { for: 'capture-titre', class: 'sr-only' }, C.champAria),
      champ,
      h('p', { class: 'capture-aide' }, icone('micro', 14, 2), C.dicter),
      h('p', { id: 'capture-domaine', class: 'section-titre' }, C.domaine),
      h('div', { class: 'capture-domaines', role: 'group', 'aria-labelledby': 'capture-domaine' }, DOMAINES.map((d) => h('button', {
        type: 'button', class: `capture-domaine d-${d}`, 'aria-pressed': String(f.domaine === d), 'aria-label': NOMS[d],
        onclick: () => {
          f.domaine = d;
          f.erreur = null;
          redessiner();
        },
      }, h('span', { class: 'scotch', vars: { '--angle': ANGLES[d] } }, SCOTCH[d]), h('span', { class: 'capture-trait' })))),
      h('div', { class: 'capture-options' },
        h('button', {
          type: 'button', class: 'bouton capture-urgent', 'aria-pressed': String(f.urgent),
          onclick: () => {
            f.urgent = !f.urgent;
            redessiner();
          },
        }, h('span', { class: 'lampe' }), C.urgent),
        h('label', { class: 'capture-echeance', for: 'capture-date' }, h('span', {}, C.echeance), date)),
      heure && h('label', { class: 'capture-echeance capture-heure', for: 'capture-heure' }, h('span', {}, C.heure), heure),
      f.date && h('button', {
        type: 'button', class: 'lien-discret',
        onclick: () => {
          f.date = '';
          f.heure = '';
          redessiner();
        },
      }, C.effacer),
      f.erreur && h('p', { class: 'erreur', role: 'alert' }, f.erreur),
      h('div', { class: 'cuvette cuvette-large' }, h('button', { type: 'button', class: 'bouton-allume bouton-large', onclick: () => ajouter(app, f) }, C.ajouter))));
}
```

`site/js/app.js` : sous les imports des écrans, ajouter `import * as capture from './ecrans/capture.js';` et dans `FEUILLES`, remplacer `capture: null,` par `capture,`.

À la fin de `site/css/app.css` :

```css
/* Feuille « Noter » */
.voile { position: fixed; inset: 0; z-index: 20; display: flex; align-items: flex-end; justify-content: center; background: rgba(8, 9, 10, 0.72); }
.feuille { width: 100%; max-width: 640px; outline: none; max-height: 92dvh; overflow-y: auto; padding: 10px 16px max(env(safe-area-inset-bottom), 16px); border-radius: 22px 22px 0 0; background: var(--panneau); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07), 0 -12px 30px rgba(0, 0, 0, 0.55); animation: monte 200ms cubic-bezier(0.2, 0.8, 0.2, 1); }
@keyframes monte { from { transform: translateY(40px); opacity: 0; } to { transform: none; opacity: 1; } }
.feuille-poignee { width: 40px; height: 5px; margin: 0 auto; border-radius: 3px; background: var(--contour); }
.feuille-entete { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; }
.feuille-titre { font-size: calc(24 * var(--px)); font-weight: 750; font-stretch: 80%; }
.bouton-console { background: var(--console); }
.capture-champ { min-height: 0; margin-top: 12px; padding: 12px 14px; background: var(--graphite); line-height: 1.35; resize: none; }
.capture-champ::placeholder { color: var(--texte-2); }
.capture-aide { display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: calc(13 * var(--px)); color: var(--texte-2); }
.capture-domaines { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-top: 8px; }
.capture-domaine { position: relative; height: 58px; padding: 0 6px; border: 0; border-radius: 10px; background: var(--console); opacity: 0.6; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06); }
.capture-domaine[aria-pressed='true'] { background: var(--console-on); opacity: 1; box-shadow: inset 0 0 0 2px var(--domaine), 0 4px 10px rgba(0, 0, 0, 0.4); }
.capture-domaine .scotch { height: 22px; padding: 0; line-height: 24px; font-size: 13px; }
.capture-trait { position: absolute; left: 10px; right: 10px; bottom: 6px; height: 4px; border-radius: 2px; background: var(--domaine); }
.capture-options { display: flex; align-items: flex-end; gap: 8px; margin-top: 12px; }
.capture-urgent { flex: 1 1 0; }
.lampe { flex: none; width: 8px; height: 8px; border-radius: 50%; background: #3A2224; box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.6); }
.capture-urgent[aria-pressed='true'] .lampe { background: var(--rouge); }
.capture-echeance { flex: 1.4 1 0; display: flex; flex-direction: column; gap: 4px; font-size: calc(13 * var(--px)); color: var(--texte-2); }
.capture-heure { margin-top: 10px; }
.champ-date { min-height: 48px; padding: 0 12px; background: var(--console); font-family: var(--mono); font-size: 16px; color-scheme: dark; }
.lien-discret { min-height: 44px; padding: 0 8px; border: 0; background: transparent; color: var(--texte-2); font-family: 'Archivo', sans-serif; font-size: calc(13 * var(--px)); letter-spacing: 0; text-decoration: underline; text-transform: none; }
```

- [ ] **Step 3: Run the tests**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs`
Expected : `31 réussi(s), 0 échec(s)`.

- [ ] **Step 4: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add site/js site/css tests/e2e && git commit -m "Lot 1 : feuille « Noter » (capture d'une tâche, hors ligne compris)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---

### Task 9 : l'onglet Agenda

Bascule Jour et Semaine, navigation d'une semaine (ou d'un jour) à l'autre, bandeaux des jours spéciaux, cours sur scotch tamponnés JOUÉ une fois passés, prochain cours en STANDBY, événements de l'agenda principal marqués PERSO. Les 15 jours de la copie d'Aujourd'hui servent directement ; les autres jours se chargent par semaine (`GET agenda?semaine=…`), gardés en mémoire et dans IndexedDB pour être relus sans réseau.

**Files:**
- Create: `site/js/ecrans/agenda.js`
- Modify: `site/js/app.js` (enregistrement), `site/css/app.css` (à la fin)
- Test: `tests/e2e/test_agenda.mjs`

**Interfaces:**
- Consumes : `appeler()`, `ErreurApi` (`api.js`), `stockage.js` (magasin `agenda`), `etatConduite()`, `nomCreneau()`, `temps.js`, `angle()`, `regle()`, `tampon()`, `voyant()`, `app.demanderConnexion()`, `T.agenda`.
- Produces : écran `agenda` (état gardé pendant la session : affichage, jour choisi, semaines chargées) avec `quitter()`.

- [ ] **Step 1: Write the failing tests**

`tests/e2e/test_agenda.mjs` :

```js
import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';

async function ouvrirAgenda(page, contexte, serveur) {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
  await ouvrir(page, '/#/agenda');
  await page.locator('.agenda-rang').first().waitFor();
}

const rangs = (page) => page.locator('.agenda-rang').allTextContents();

test('agenda : semaine du 12 au 18 octobre, cours passés JOUÉ, férié, STANDBY sur le prochain cours', async ({ page, contexte, serveur }) => {
  await ouvrirAgenda(page, contexte, serveur);
  await page.getByText('12 au 18 octobre').waitFor();
  const r = await rangs(page);
  assert.equal(r.length, 7);
  assert.ok(r[0].startsWith('LUN 12') && r[0].includes('LA14') && r[0].includes('TD · FC114') && r[0].includes('16:30 à 18:30') && r[0].includes('JOUÉ'), r[0]);
  assert.ok(r[2].includes('NF93') && r[2].includes('Déjeuner avec Léon') && r[2].includes('PERSO') && r[2].includes('TN01') && r[2].includes('STANDBY'), r[2]);
  assert.ok(r[3].includes("Jour férié à l'UTC : pas de cours."), r[3]);
  assert.ok(r[4].includes('Rien de prévu.'), r[4]);
  assert.equal(await texte(page, '.agenda-jour[aria-current="date"]'), 'MER 14');
  assert.deepEqual(serveur.appels('agenda').map((a) => a.parametres.semaine), ['2026-W42']);
});

test("agenda : semaine suivante (« devient un jeudi », examens, médian), vue Jour, puis retour à aujourd'hui", async ({ page, contexte, serveur }) => {
  await ouvrirAgenda(page, contexte, serveur);
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await page.getByText('19 au 25 octobre').waitFor();
  const r = await rangs(page);
  assert.ok(r[0].includes("Aujourd'hui, l'UTC suit l'emploi du temps du jeudi.") && r[0].includes('MT23'), r[0]);
  assert.ok(r[2].includes("Jour d'examen.") && r[2].includes('Médian MT23'), r[2]);
  assert.deepEqual(serveur.appels('agenda').map((a) => a.parametres.semaine), ['2026-W42'], 'jours déjà dans la copie : pas de second appel');
  await page.getByRole('button', { name: 'Jour', exact: true }).click();
  await page.getByText('Mercredi 21 octobre').waitFor();
  assert.equal((await rangs(page)).length, 1);
  await page.getByRole('button', { name: "Aujourd'hui" }).click();
  await page.getByText('Mercredi 14 octobre').waitFor();
});

test("agenda : semaine du 26 octobre (heure d'hiver), le TD du lundi reste à 16:30", async ({ page, contexte, serveur }) => {
  await ouvrirAgenda(page, contexte, serveur);
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await page.getByText('26 octobre au 1er novembre').waitFor();
  await attendre(async () => (await rangs(page))[0]?.includes('LA14'), 'semaine chargée');
  assert.ok((await rangs(page))[0].includes('16:30 à 18:30'));
  assert.deepEqual(serveur.appels('agenda').map((a) => a.parametres.semaine), ['2026-W42', '2026-W44']);
});

test('agenda hors ligne : une semaine jamais chargée le dit, une semaine déjà vue reste lisible', async ({ page, contexte, serveur }) => {
  await ouvrirAgenda(page, contexte, serveur);
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await attendre(async () => (await rangs(page))[0]?.includes('LA14'), 'semaine du 26 chargée');
  await contexte.setOffline(true);
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await page.getByText("Hors ligne : cette semaine n'est pas encore chargée.").waitFor();
  await page.getByRole('button', { name: 'Semaine précédente' }).click();
  await attendre(async () => (await rangs(page))[0]?.includes('LA14'), 'semaine du 26 relue sans réseau');
});
```

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs agenda`
Expected : 4 échecs.

- [ ] **Step 2: Implement the screen**

`site/js/ecrans/agenda.js` :

```js
// L'onglet Agenda (SPEC § 8.1) : Jour et Semaine, jours spéciaux, cours JOUÉ, prochain cours en STANDBY,
// événements de l'agenda principal à part. Les 15 jours de la copie d'Aujourd'hui servent directement ;
// les autres se chargent par semaine, gardés pour être relus sans réseau.
import { angle, h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { appeler, ErreurApi } from '../api.js';
import * as stockage from '../stockage.js';
import { etatConduite } from '../lib/conduite.js';
import { ajouterJours, dateLongue, etiquetteJour, heureParis, jourParis, lundi, plageSemaine, semaineIso } from '../lib/temps.js';
import { regle, tampon, voyant } from './commun.js';

const G = T.agenda;
const VIDE = { bandeaux: [], evenements: [] };
let affichage = 'semaine';
let choisi = null;
const semaines = new Map();  // semaine ISO → { etat: 'chargement' | 'pret' | 'absente', jours }

// Une semaine absente (pas de réseau) sera redemandée à la prochaine navigation
export function quitter() {
  for (const [semaine, s] of semaines) if (s.etat === 'absente') semaines.delete(semaine);
}

async function charger(app, semaine) {
  semaines.set(semaine, { etat: 'chargement' });
  let jours = null;
  try {
    jours = (await appeler('GET', 'agenda', { parametres: { semaine } })).agenda.jours;
    stockage.ecrire('agenda', semaine, jours).catch(() => {});
  } catch (e) {
    if (e instanceof ErreurApi && e.statut === 401) {
      semaines.delete(semaine);
      app.demanderConnexion(true);
      return;
    }
    jours = (await stockage.lire('agenda', semaine).catch(() => null)) ?? null;
  }
  semaines.set(semaine, jours ? { etat: 'pret', jours } : { etat: 'absente' });
  app.rendre();
}

// Les jours demandés : ceux de la copie d'Aujourd'hui (les plus frais), complétés par la semaine chargée
function joursPour(app, dates, copie) {
  if (dates.every((d) => copie[d])) return { etat: 'pret', jours: copie };
  const semaine = semaineIso(dates[0]);
  const s = semaines.get(semaine);
  if (!s) {
    charger(app, semaine);
    return { etat: 'chargement' };
  }
  if (s.etat !== 'pret') return s;
  return { etat: 'pret', jours: { ...s.jours, ...Object.fromEntries(dates.filter((d) => copie[d]).map((d) => [d, copie[d]])) } };
}

function evenement(e, aujourdhui, maintenant, prochain) {
  const passe = e.journee ? e.fin <= aujourdhui : new Date(e.fin) <= maintenant;
  const estProchain = prochain && e.debut === prochain.debut && e.titre === prochain.titre;
  const uv = e.source === 'utc' && e.uv;
  return h('div', { class: `evenement${passe ? ' passe' : ''}` },
    uv ? h('span', { class: 'scotch scotch-uv', vars: { '--angle': angle(e.debut + e.uv, 1.5) } }, e.uv) : e.source === 'perso' && h('span', { class: 'marque-perso' }, G.perso),
    h('div', { class: 'evenement-texte' },
      h('p', { class: 'evenement-titre' }, uv ? [e.type, e.salle].filter(Boolean).join(' · ') : e.titre),
      h('p', { class: 'evenement-heure' }, e.journee ? G.journee : G.plage(heureParis(new Date(e.debut)), heureParis(new Date(e.fin))))),
    passe ? tampon(T.aujourdhui.joue, e.debut + e.titre) : estProchain && voyant(T.aujourdhui.standby, false, true));
}

function rangJour(jour, contenu, aujourdhui, maintenant, prochain) {
  const elements = [
    ...contenu.bandeaux.map((b) => h('div', { class: 'bandeau-jour' }, icone(b.type === 'ferie' ? 'ferie' : 'agenda', 18), h('span', {}, b.texte))),
    ...contenu.evenements.map((e) => evenement(e, aujourdhui, maintenant, prochain)),
  ];
  return h('div', { class: 'rang agenda-rang' },
    h('span', { class: `agenda-jour${jour === aujourdhui ? ' aujourdhui' : ''}`, 'aria-current': jour === aujourdhui ? 'date' : null }, etiquetteJour(jour)),
    h('div', { class: 'agenda-contenu' }, elements.length > 0 ? elements : h('span', { class: 'vide-court' }, G.rien)));
}

export function rendre(app) {
  const maintenant = app.maintenant();
  const aujourdhui = jourParis(maintenant);
  choisi ??= aujourdhui;
  const v = app.vue();
  const semaine = affichage === 'semaine';
  const dates = semaine ? Array.from({ length: 7 }, (_, i) => ajouterJours(lundi(choisi), i)) : [choisi];
  const aller = (n) => {
    choisi = n === 0 ? aujourdhui : ajouterJours(choisi, n * (semaine ? 7 : 1));
    quitter();
    app.rendre();
  };
  const bascule = (nom, libelle) => h('button', {
    type: 'button', class: 'bascule-choix', 'aria-pressed': String(affichage === nom),
    onclick: () => {
      affichage = nom;
      app.rendre();
    },
  }, libelle);
  const nonConnecte = v !== null && !v.agenda.google_connecte && Object.keys(v.agenda.jours).length === 0;
  const donnees = nonConnecte ? null : joursPour(app, dates, v?.agenda.jours ?? {});
  let corps;
  if (nonConnecte) corps = h('p', { class: 'vide' }, G.nonConnecte);
  else if (donnees.etat === 'chargement') corps = h('p', { class: 'vide' }, G.chargement);
  else if (donnees.etat === 'absente') corps = h('p', { class: 'vide' }, G.pasChargee);
  else {
    const prochain = etatConduite(donnees.jours, maintenant).standby?.cours ?? null;
    corps = dates.map((d) => rangJour(d, donnees.jours[d] ?? VIDE, aujourdhui, maintenant, prochain));
  }
  return h('div', { class: 'agenda' },
    h('header', { class: 'entete entete-centre' },
      h('h1', { class: 'titre-ecran' }, G.titre),
      h('div', { class: 'bascule', role: 'group', 'aria-label': G.affichage }, bascule('jour', G.jour), bascule('semaine', G.semaine))),
    h('div', { class: 'agenda-nav' },
      h('button', { type: 'button', class: 'bouton-carre', 'aria-label': semaine ? G.precedente : G.veille, onclick: () => aller(-1) }, icone('gauche', 20, 2)),
      h('div', { class: 'agenda-plage' },
        h('span', {}, semaine ? plageSemaine(dates[0]) : dateLongue(choisi)),
        !dates.includes(aujourdhui) && h('button', { type: 'button', class: 'lien-discret', onclick: () => aller(0) }, G.aujourdhui)),
      h('button', { type: 'button', class: 'bouton-carre', 'aria-label': semaine ? G.suivante : G.lendemain, onclick: () => aller(1) }, icone('droite', 20, 2))),
    regle(),
    corps);
}
```

`site/js/app.js` : sous les imports des écrans, ajouter `import * as agenda from './ecrans/agenda.js';` et dans `ECRANS`, remplacer `agenda: attente,` par `agenda,`.

À la fin de `site/css/app.css` :

```css
/* Agenda */
.entete-centre { align-items: center; }
.bascule { display: flex; gap: 4px; padding: 3px; border-radius: 10px; background: #0D0F11; box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.7); }
.bascule-choix { min-height: 40px; padding: 0 14px; border: 0; border-radius: 8px; background: transparent; color: var(--texte-2); font-size: calc(14 * var(--px)); font-weight: 600; }
.bascule-choix[aria-pressed='true'] { background: var(--scotch); color: var(--graphite); font-weight: 700; box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.15); }
.agenda-nav { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 10px; }
.agenda-plage { display: flex; flex-direction: column; align-items: center; gap: 2px; font-family: var(--mono); font-size: 12px; letter-spacing: 0.12em; text-align: center; text-transform: uppercase; }
.agenda-rang { align-items: start; padding: 12px 0; }
.agenda-jour { justify-self: start; padding: 4px 6px 3px; font-family: var(--mono); font-size: 13px; letter-spacing: 0.08em; }
.agenda-jour.aujourdhui { border-radius: 3px; background: var(--scotch); color: var(--graphite); font-weight: 600; }
.agenda-contenu { display: flex; flex-direction: column; gap: 12px; }
.bandeau-jour { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; background: var(--panneau); font-size: calc(14 * var(--px)); font-weight: 600; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05); }
.evenement { display: flex; align-items: center; gap: 10px; }
.scotch-uv { flex: none; width: 46px; height: 21px; padding: 0; line-height: 23px; font-size: 12px; }
.marque-perso { flex: none; width: 46px; padding: 3px 0 2px; border: 1px solid var(--contour); border-radius: 3px; font-family: var(--mono); font-size: 9px; letter-spacing: 0.1em; text-align: center; color: var(--texte-2); }
.evenement-texte { flex: 1 1 auto; min-width: 0; }
.evenement-titre { font-size: calc(15 * var(--px)); font-weight: 600; overflow-wrap: anywhere; }
.evenement-heure { font-family: var(--mono); font-size: 12.5px; color: var(--texte-2); }
.evenement.passe .evenement-titre { color: var(--texte-2); }
.evenement.passe .evenement-heure { color: var(--texte-3); }
.evenement.passe .scotch-uv { opacity: 0.75; }
.vide-court { font-size: calc(14 * var(--px)); color: var(--texte-2); }
```

- [ ] **Step 3: Run the tests**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs`
Expected : `35 réussi(s), 0 échec(s)`.

- [ ] **Step 4: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add site/js site/css tests/e2e && git commit -m "Lot 1 : onglet Agenda (jour et semaine, jours spéciaux, semaines chargées à la demande)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---
### Task 10 : Moi et la page de confidentialité

Moi au lot 1 : la connexion à Google Agenda (état, « Connecter Google Agenda » ou « Reconnecter », retour de Google), la session (« Se déconnecter », « Déconnecter tous les appareils »), la version de l'app et le lien vers les règles de confidentialité. La page publique `confidentialite.html` est exigée par Google pour un projet « En production ». Le dernier écran d'attente disparaît.

**Files:**
- Create: `site/js/ecrans/moi.js`, `site/confidentialite.html`
- Modify: `site/js/app.js` (enregistrement, retrait d'`attente`), `site/css/app.css` (à la fin)
- Delete: `site/js/ecrans/attente.js`
- Test: `tests/e2e/test_moi.mjs`

**Interfaces:**
- Consumes : `GET sante` (`version_app`), `GET google/connexion` (redirige vers Google, puis vers `/#/moi?google=connecte` ou `?google=erreur`), `app.deconnecter(partout)`, `app.actualiser(true)`, `app.message()`, `app.etat.file`, `T.moi`.
- Produces : écran `moi` ; page publique `/confidentialite.html` (sans connexion, même feuille de style).

- [ ] **Step 1: Write the failing tests**

`tests/e2e/test_moi.mjs` :

```js
import { test, assert, heure, connecte, ouvrir, attendre } from './cadre.mjs';

async function preparer(page, contexte, serveur) {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
}

const copieLocale = (page) => page.evaluate(async () => (await import('/js/stockage.js')).lire('donnees', 'derniere'));

test("Moi : agenda connecté, « Reconnecter », version de l'app, lien vers les règles de confidentialité", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi');
  await page.getByText('Agenda connecté', { exact: true }).waitFor();
  assert.equal(await page.getByRole('link', { name: 'Reconnecter' }).getAttribute('href'), '/api/google/connexion');
  await page.getByText('Version 0.2.0').waitFor();
  assert.equal(await page.getByRole('link', { name: 'Confidentialité' }).getAttribute('href'), '/confidentialite.html');
});

test('Moi : retour de Google « connecte » : « Agenda connecté. », actualisation forcée, adresse nettoyée', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi?google=connecte');
  await page.getByText('Agenda connecté.', { exact: true }).waitFor();
  await attendre(() => serveur.appels('donnees').some((a) => a.parametres.force === '1'), 'actualisation forcée');
  assert.equal(new URL(page.url()).hash, '#/moi');
});

test("Moi : retour « erreur » : « La connexion à Google n'a pas abouti. Réessaie. »", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi?google=erreur');
  await page.getByText("La connexion à Google n'a pas abouti. Réessaie.").waitFor();
});

test('Google déconnecté : « Reconnecter » mène à Google, puis revient sur Moi avec « Agenda connecté. »', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  serveur.etat.donnees.agenda.erreur = 'google_deconnecte';
  await ouvrir(page, '/#/aujourdhui');
  await page.getByRole('link', { name: 'Reconnecter' }).click();
  await page.getByText('Agenda connecté.', { exact: true }).waitFor();
  assert.equal(new URL(page.url()).hash, '#/moi');
  await attendre(async () => !(await page.locator('#statut').isVisible()), 'bandeau de Google retiré');
});

test("Se déconnecter : la copie de l'iPhone est effacée, écran de connexion sans message d'expiration", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi');
  await attendre(async () => (await copieLocale(page)) !== undefined, 'copie gardée');
  await page.getByRole('button', { name: 'Se déconnecter' }).click();
  await page.getByRole('heading', { name: 'JARVIS JR.' }).waitFor();
  assert.equal(await page.getByText('Ta session a expiré. Reconnecte-toi.').count(), 0);
  assert.equal(await copieLocale(page), undefined);
  assert.equal(serveur.appels('deconnexion').length, 1);
});

test('Déconnecter tous les appareils : rien sans confirmation, puis la déconnexion partout', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi');
  await page.getByRole('button', { name: 'Déconnecter tous les appareils' }).click();
  assert.equal(serveur.appels('deconnexion-partout').length, 0);
  page.once('dialog', (d) => d.accept());
  await page.getByRole('button', { name: 'Déconnecter tous les appareils' }).click();
  await page.getByRole('heading', { name: 'JARVIS JR.' }).waitFor();
  assert.equal(serveur.appels('deconnexion-partout').length, 1);
});

test('règles de confidentialité : page publique, lisible sans connexion, sans erreur', async ({ page }) => {
  await page.goto('/confidentialite.html');
  await page.getByRole('heading', { name: 'Règles de confidentialité' }).waitFor();
  await page.getByText('calendar.readonly', { exact: false }).first().waitFor();
});
```

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs Moi ; node tests/e2e/lancer.mjs confidentialité`
Expected : échecs (écran d'attente, page introuvable).

- [ ] **Step 2: Implement Moi, the public page, and retire the waiting screen**

`site/js/ecrans/moi.js` :

```js
// Moi au lot 1 (SPEC § 8.1) : Google Agenda, la session, la version. Grade, XP, album et badges
// arrivent au lot 4, avec un lien vers Réglages.
import { h } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { appeler, ErreurReseau } from '../api.js';
import { regle } from './commun.js';

const M = T.moi;
let version = null;
let erreurGoogle = false;

// Retour de Google (#/moi?google=…) : message, actualisation, puis l'adresse est nettoyée
export function apres(app, route) {
  const google = route.requete.get('google');
  if (google) {
    history.replaceState(null, '', '#/moi');
    erreurGoogle = google === 'erreur';
    if (google === 'connecte') {
      app.message(M.connecteMessage);
      app.actualiser(true);
    }
    app.rendre();
    return;
  }
  if (version === null) {
    version = '';
    appeler('GET', 'sante')
      .then((r) => {
        version = r.version_app;
        app.rendre();
      })
      .catch(() => {
        version = null;
      });
  }
}

async function sortir(app, partout) {
  const n = app.etat.file.length;
  if (n > 0 && !window.confirm(M.confirmerFile(n))) return;
  if (partout && !window.confirm(M.confirmerTout)) return;
  try {
    await app.deconnecter(partout);
  } catch (e) {
    app.message(e instanceof ErreurReseau ? M.reseau : e.message);
  }
}

export function rendre(app) {
  const v = app.vue();
  const connecte = Boolean(v?.agenda.google_connecte) && v.agenda.erreur !== 'google_deconnecte';
  return h('div', { class: 'moi' },
    h('header', { class: 'entete' }, h('h1', { class: 'titre-ecran' }, M.titre)),
    h('h2', { class: 'section-titre' }, M.google), regle(),
    h('p', { class: 'reglage-ligne' }, h('span', { class: `temoin${connecte ? ' allume' : ''}` }), connecte ? M.connecte : M.nonConnecte),
    erreurGoogle && h('p', { class: 'erreur', role: 'alert' }, M.erreurGoogle),
    connecte
      ? h('a', { class: 'bouton bouton-large', href: '/api/google/connexion' }, M.reconnecter)
      : h('div', { class: 'cuvette cuvette-large' }, h('a', { class: 'bouton-allume bouton-large', href: '/api/google/connexion' }, M.connecter)),
    h('h2', { class: 'section-titre' }, M.session), regle(),
    h('button', { type: 'button', class: 'bouton bouton-large', onclick: () => sortir(app, false) }, M.deconnecter),
    h('button', { type: 'button', class: 'bouton bouton-large', onclick: () => sortir(app, true) }, M.deconnecterTout),
    h('h2', { class: 'section-titre' }, M.app), regle(),
    h('p', { class: 'reglage-ligne' }, version ? M.version(version) : T.app),
    h('a', { class: 'reglage-ligne lien', href: '/confidentialite.html' }, M.confidentialite));
}
```

`site/confidentialite.html` :

```html
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Confidentialité · Jarvis Jr.</title>
<meta name="color-scheme" content="dark">
<link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
<link rel="stylesheet" href="/css/app.css">
</head>
<body>
<main class="page-publique">
<p class="page-publique-retour"><a href="/">Jarvis Jr.</a></p>
<h1>Règles de confidentialité</h1>
<p class="page-publique-date">Mise à jour le 10 octobre 2026.</p>

<h2>L'application</h2>
<p>Jarvis Jr. (jr.qyris.fr) est l'application personnelle d'organisation de Colin Flicourt. Elle n'a qu'un utilisateur, Colin, qui y accède avec un mot de passe. Elle n'est ni vendue, ni ouverte au public.</p>

<h2>Les données utilisées</h2>
<ul>
<li>Google Agenda, en lecture seule (autorisation calendar.readonly) : les événements de l'agenda principal de Colin et de son agenda « UTC · Emploi du temps A26 », pour afficher son emploi du temps et ses jours spéciaux.</li>
<li>Trello : les cartes des 4 tableaux de l'espace « Jarvis · Colin », pour afficher et mettre à jour sa liste de tâches.</li>
</ul>
<p>Jarvis Jr. ne modifie jamais l'agenda et n'accède à aucune autre donnée Google.</p>

<h2>Où elles sont gardées</h2>
<p>Une copie de ces données est gardée sur le serveur de l'application (hébergement OVH, en France) et sur l'iPhone de Colin, pour s'afficher vite et sans réseau. Elle est remplacée à chaque actualisation. Les jetons d'accès à Google sont chiffrés. Aucune donnée n'est vendue, partagée, ni utilisée pour de la publicité ou pour entraîner un modèle d'IA. Aucun outil de mesure d'audience n'est utilisé.</p>

<h2>Cookie</h2>
<p>Un seul cookie, nécessaire à la connexion : il garde la session de Colin pendant 90 jours au plus.</p>

<h2>Retirer l'accès</h2>
<p>L'accès à Google Agenda peut être retiré à tout moment sur <a href="https://myaccount.google.com/permissions">myaccount.google.com/permissions</a>. L'application efface alors ses jetons Google à la lecture suivante ; ses copies sont effacées sur simple demande.</p>

<h2>Règles de Google</h2>
<p>L'utilisation et le transfert à toute autre application des informations reçues des API Google respectent les règles relatives aux données utilisateur des services d'API Google (Google API Services User Data Policy), y compris les exigences d'utilisation limitée.</p>

<h2>Contact</h2>
<p>Colin Flicourt : <a href="mailto:colin@qyris.fr">colin@qyris.fr</a></p>
</main>
</body>
</html>
```

`site/js/app.js` : supprimer la ligne `import * as attente from './ecrans/attente.js';`, ajouter `import * as moi from './ecrans/moi.js';` avec les autres écrans, et dans `ECRANS`, remplacer `moi: attente,` par `moi,`. `ECRANS` devient :

```js
const ECRANS = {
  aujourdhui,
  agenda,
  taches,
  carte,
  moi,
};
```

Supprimer `site/js/ecrans/attente.js` (`git rm site/js/ecrans/attente.js`).

À la fin de `site/css/app.css` :

```css
/* Moi */
.reglage-ligne { display: flex; align-items: center; gap: 10px; min-height: 48px; border-bottom: 1px solid var(--panneau); font-size: calc(15 * var(--px)); font-weight: 600; text-decoration: none; }
.temoin { flex: none; width: 9px; height: 9px; border-radius: 50%; background: var(--contour); box-shadow: 0 0 0 3px var(--console); }
.temoin.allume { background: var(--scotch); }
.moi .bouton-large + .bouton-large { margin-top: 8px; }
.lien { text-decoration: underline; text-underline-offset: 3px; }

/* Page publique (confidentialité) */
.page-publique { max-width: 640px; margin: 0 auto; padding: max(env(safe-area-inset-top), 24px) 20px 40px; font-size: calc(16 * var(--px)); line-height: 1.55; }
.page-publique h1 { margin-top: 18px; font-size: calc(28 * var(--px)); font-weight: 750; font-stretch: 80%; line-height: 1.2; }
.page-publique h2 { margin-top: 24px; font-family: var(--mono); font-size: 12px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: var(--texte-2); }
.page-publique p, .page-publique ul { margin-top: 10px; }
.page-publique ul { padding-left: 20px; list-style: disc; }
.page-publique a { color: var(--scotch); }
.page-publique-retour { font-family: var(--mono); font-size: 12px; letter-spacing: 0.1em; }
.page-publique-date { color: var(--texte-2); font-size: calc(14 * var(--px)); }
```

- [ ] **Step 3: Run the tests**

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs`
Expected : `42 réussi(s), 0 échec(s)` (`test_socle` cherche maintenant le `h1` d'Agenda, toujours présent).

- [ ] **Step 4: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add -A site tests/e2e && git commit -m "Lot 1 : Moi (Google Agenda, session, version) et page de confidentialité exigée par Google

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---

### Task 11 : le service worker, l'app sans réseau, tous les écrans à toutes les largeurs

Le service worker garde l'app (SPEC § 8.4) : tout en cache d'abord, jamais l'API, nouvelle version installée dès qu'elle est vue et utilisée à l'ouverture suivante. Un test PHP vérifie que chaque fichier du lot 1 est dans sa liste et que sa `VERSION` est l'empreinte de ces fichiers : un fichier changé sans changer `VERSION` bloque la mise en ligne (le script lance les tests). Puis un test d'écran ouvre l'app sans réseau, et un autre vérifie que rien ne déborde, écran par écran, à 375, 390 et 768 px.

**Files:**
- Create: `site/sw.js`, `tests/unitaires/test_sw.php`, `tests/e2e/test_hors_ligne.mjs`
- Modify: `site/js/app.js` (enregistrement du service worker), `site/.htaccess` (type des polices), `tests/unitaires/test_htaccess.php`

**Interfaces:**
- Consumes : tous les fichiers de `site/` du lot 1.
- Produces : `/sw.js` (portée `/`), cache nommé par `VERSION` (`jr-` et 10 caractères hexadécimaux).

- [ ] **Step 1: Write the failing tests**

`tests/unitaires/test_sw.php` :

```php
<?php
declare(strict_types=1);

// Ce que le service worker n'a pas à garder au lot 1 : l'API, les cartes à collectionner et les boss
// (lot 4, 8,9 Mo), l'écran de lancement (lu par iOS à l'installation), la page publique, les licences.
const JR_SW_HORS_CACHE = ['/.htaccess', '/robots.txt', '/sw.js', '/confidentialite.html', '/api/', '/data/', '/img/', '/fonts/licences/'];

function sw_source(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2) . '/site/sw.js');
}

function sw_fichiers(): array
{
    preg_match('/const FICHIERS = \[(.*?)\];/s', sw_source(), $m);
    preg_match_all("/'([^']+)'/", $m[1] ?? '', $f);
    return $f[1];
}

test('service worker : chaque fichier du lot 1 est dans sa liste, et seulement ceux-là', function () {
    $site = dirname(__DIR__, 2) . '/site';
    $presents = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS)) as $fichier) {
        $chemin = substr($fichier->getPathname(), strlen($site));
        if ($fichier->getFilename() === '.DS_Store') {
            continue;
        }
        foreach (JR_SW_HORS_CACHE as $exclu) {
            if (str_starts_with($chemin, $exclu)) {
                continue 2;
            }
        }
        $presents[] = $chemin;
    }
    $listes = array_values(array_filter(sw_fichiers(), fn(string $f): bool => $f !== '/'));
    sort($presents);
    sort($listes);
    egal($presents, $listes);
    vrai(in_array('/', sw_fichiers(), true), "'/' doit être en cache (ouverture sans réseau)");
});

test("service worker : sa VERSION est l'empreinte des fichiers en cache (changer un fichier oblige à la changer)", function () {
    $site = dirname(__DIR__, 2) . '/site';
    $empreinte = hash_init('sha256');
    foreach (sw_fichiers() as $f) {
        if ($f !== '/') {
            hash_update($empreinte, $f . "\n" . file_get_contents($site . $f));
        }
    }
    $attendue = 'jr-' . substr(hash_final($empreinte), 0, 10);
    preg_match("/const VERSION = '([^']+)';/", sw_source(), $m);
    egal($attendue, $m[1] ?? null, "dans site/sw.js, mettre const VERSION = '$attendue';");
});
```

Dans `tests/unitaires/test_htaccess.php`, ajouter `'AddType font/woff2 .woff2',` à la liste `$attendus` du deuxième test.

`tests/e2e/test_hors_ligne.mjs` :

```js
import { test, assert, heure, connecte, ouvrir } from './cadre.mjs';
import { CARTES } from './fixtures.mjs';

test("hors ligne : après une première visite, l'app s'ouvre sans réseau, avec sa copie", async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
  await ouvrir(page, '/#/taches');
  await page.getByText('9 CARTES').waitFor();
  await page.waitForFunction(() => Boolean(navigator.serviceWorker?.controller));
  await contexte.setOffline(true);
  await page.reload();
  await page.getByText('9 CARTES').waitFor();
  await page.getByText(/^Hors ligne · données d'il y a/).waitFor();
}, { serviceWorkers: 'allow' });

test('tous les écrans : rien ne déborde à 375, 390 et 768 px, feuille « Noter » comprise', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
  await ouvrir(page, '/#/aujourdhui');
  await page.locator('.tranche-compte').first().waitFor();
  const debords = () => page.evaluate(() => [...document.querySelectorAll('#app *')]
    .filter((e) => e.getClientRects().length > 0 && e.getBoundingClientRect().right > window.innerWidth + 1)
    .map((e) => `${e.tagName.toLowerCase()}.${e.className}`));
  for (const largeur of [375, 390, 768]) {
    await page.setViewportSize({ width: largeur, height: 844 });
    for (const route of ['#/aujourdhui', '#/agenda', '#/taches', `#/carte/${CARTES.apoint}`, '#/moi']) {
      await page.evaluate((r) => { location.hash = r; }, route);
      await page.waitForTimeout(200);
      assert.deepEqual(await debords(), [], `${route} à ${largeur} px`);
    }
    await page.getByRole('button', { name: 'Noter quelque chose' }).click();
    await page.getByRole('dialog', { name: 'Noter' }).waitFor();
    assert.deepEqual(await debords(), [], `feuille « Noter » à ${largeur} px`);
    await page.keyboard.press('Escape');
  }
});
```

Run: `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php sw ; php tests/run.php htaccess ; } 2>&1 | ~/.claude/hooks/redact-secrets ; cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/lancer.mjs "hors ligne : après"`
Expected : les 2 tests du service worker et celui du `.htaccess` en échec ; le test d'écran en échec (pas de service worker). Le test des débordements passe peut-être déjà : s'il échoue, la liste nomme les éléments qui sortent de l'écran, à corriger dans `app.css` (jamais en masquant le débordement de `#app`).

- [ ] **Step 2: Implement the service worker**

`site/sw.js` :

```js
// Service worker de Jarvis Jr. (SPEC § 8.4) : l'app s'ouvre sans réseau. Tout ce qui est en cache est servi
// d'abord ; une nouvelle version (VERSION changée) est installée dès qu'elle est vue et sert à l'ouverture
// suivante. L'API n'est jamais mise en cache. VERSION est l'empreinte des fichiers de FICHIERS :
// php tests/run.php donne la bonne valeur.
const VERSION = 'jr-0000000000';
const FICHIERS = [
  '/',
  '/index.html',
  '/manifest.webmanifest',
  '/css/app.css',
  '/js/app.js',
  '/js/api.js',
  '/js/etat.js',
  '/js/file.js',
  '/js/stockage.js',
  '/js/lib/cartes.js',
  '/js/lib/conduite.js',
  '/js/lib/dom.js',
  '/js/lib/phrases.js',
  '/js/lib/temps.js',
  '/js/lib/textes.js',
  '/js/lib/tirage.js',
  '/js/ecrans/agenda.js',
  '/js/ecrans/aujourdhui.js',
  '/js/ecrans/capture.js',
  '/js/ecrans/carte.js',
  '/js/ecrans/commun.js',
  '/js/ecrans/connexion.js',
  '/js/ecrans/moi.js',
  '/js/ecrans/taches.js',
  '/fonts/archivo.woff2',
  '/fonts/martian-mono.woff2',
  '/fonts/permanent-marker.woff2',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/apple-touch-icon.png',
  '/favicon-32.png',
];

// cache: 'reload' : OVH ajoute max-age=900 aux fichiers statiques, il faut la version du serveur
self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION)
    .then((cache) => cache.addAll(FICHIERS.map((f) => new Request(f, { cache: 'reload' }))))
    .then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys()
    .then((noms) => Promise.all(noms.filter((n) => n !== VERSION).map((n) => caches.delete(n))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) return;
  e.respondWith((async () => {
    const cache = await caches.open(VERSION);
    const trouve = await cache.match(e.request, { ignoreSearch: true });
    if (trouve) return trouve;
    try {
      return await fetch(e.request);
    } catch (erreur) {
      const page = e.request.mode === 'navigate' ? await cache.match('/') : undefined;
      if (page) return page;
      throw erreur;
    }
  })());
});
```

`site/js/app.js`, dans `demarrer()`, juste après `document.documentElement.dataset.pret = '1';` :

```js
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
```

`site/.htaccess`, sous `AddType application/manifest+json .webmanifest` :

```
AddType font/woff2 .woff2
```

- [ ] **Step 3: Set the VERSION and run everything**

Run: `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php sw ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : le test de la version échoue avec `dans site/sw.js, mettre const VERSION = 'jr-…';`. Recopier cette valeur dans `site/sw.js`, puis :

Run: `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets ; cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node --test 'tests/js/*.test.mjs' && node tests/e2e/lancer.mjs`
Expected : `158 réussi(s), 0 échec(s)` ; `pass 25` ; `44 réussi(s), 0 échec(s)`. Si Chromium écrit une erreur de console quand l'enregistrement du service worker est bloqué (tests en `serviceWorkers: 'block'`), ajouter son motif exact à `IGNORES` dans `cadre.mjs` : c'est voulu, pas une erreur de l'app.

À partir d'ici, tout changement d'un fichier de `site/` en cache change l'empreinte : `php tests/run.php` donne la nouvelle `VERSION` à recopier (Global Constraints).

- [ ] **Step 4: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add site tests && git commit -m "Lot 1 : service worker (app sans réseau, version vérifiée par empreinte), débordements vérifiés à 3 largeurs

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---
### Task 12 : revues (finition, code, sécurité) et DESIGN.md

Avant toute mise en ligne (SPEC § 12.4, contrat de direction : « unreviewed and undocumented is unfinished »). Rien n'est envoyé sur OVH.

**Files:**
- Create: `tests/e2e/captures.mjs`, `DESIGN.md`
- Modify: `.gitignore` ; les fichiers que les revues font corriger ; `site/sw.js` (`VERSION`) si un fichier en cache change ; `README.md` (constats mineurs)

**Interfaces:**
- Consumes : tout ce qui précède ; le contrat `.impeccable/surfaces/site-index-html.md` ; la maquette v28.
- Produces : 11 captures dans `tests/e2e/captures/` (hors git), `DESIGN.md`, un verdict de finition, les constats des revues traités.

- [ ] **Step 1: Capture every screen**

`tests/e2e/captures.mjs` :

```js
// Captures de tous les écrans pour la revue de finition : node tests/e2e/captures.mjs
// → tests/e2e/captures/*.png (hors git), en iPhone 14, sur la fausse API des tests.
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import { IPHONE } from './cadre.mjs';
import { demarrer } from './serveur.mjs';
import { CARTES } from './fixtures.mjs';

const DOSSIER = fileURLToPath(new URL('./captures/', import.meta.url));
const MERCREDI = '2026-10-14T15:45:00+02:00';
const go = async (p) => {
  await p.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).click();
  await p.locator('.tirage-texte').waitFor();
  await p.waitForTimeout(500);
};
const pli = async (p) => {
  await p.locator('.sous-le-pli .rang-groupe').last().scrollIntoViewIfNeeded();
  await p.waitForTimeout(300);
};
const noter = async (p) => {
  await p.getByRole('button', { name: 'Noter quelque chose' }).click();
  await p.getByRole('dialog').waitFor();
};

// [nom, heure de l'iPhone, adresse, connecté, geste avant la capture]
const PLANCHES = [
  ['01-connexion', MERCREDI, '/', false, null],
  ['02-matin', '2026-10-14T07:32:00+02:00', '/#/aujourdhui', true, null],
  ['03-entre-deux-cours', MERCREDI, '/#/aujourdhui', true, null],
  ['04-tirage', MERCREDI, '/#/aujourdhui', true, go],
  ['05-en-td', '2026-10-14T17:10:00+02:00', '/#/aujourdhui', true, null],
  ['06-sous-le-pli', MERCREDI, '/#/aujourdhui', true, pli],
  ['07-taches', MERCREDI, '/#/taches', true, null],
  ['08-carte', MERCREDI, `/#/carte/${CARTES.apoint}`, true, null],
  ['09-noter', MERCREDI, '/#/aujourdhui', true, noter],
  ['10-agenda', MERCREDI, '/#/agenda', true, null],
  ['11-moi', MERCREDI, '/#/moi', true, null],
];

mkdirSync(DOSSIER, { recursive: true });
const serveur = await demarrer();
const navigateur = await chromium.launch();
for (const [nom, heure, adresse, connecte, geste] of PLANCHES) {
  const contexte = await navigateur.newContext({ ...IPHONE, baseURL: serveur.origine, serviceWorkers: 'block' });
  if (connecte) await contexte.addCookies([{ name: 'jr-test', value: '1', url: serveur.origine }]);
  const page = await contexte.newPage();
  await page.clock.setFixedTime(new Date(heure));
  await page.goto(adresse);
  await page.waitForTimeout(600);
  if (geste) await geste(page);
  await page.screenshot({ path: `${DOSSIER}${nom}.png` });
  await contexte.close();
}
await navigateur.close();
await serveur.arreter();
console.log(`${PLANCHES.length} captures dans ${DOSSIER}`);
```

Dans `.gitignore`, ajouter la ligne `tests/e2e/captures/`.

Run: `cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node tests/e2e/captures.mjs`
Expected : `11 captures dans …/tests/e2e/captures/`. Lire chaque image (outil Read) et noter tout défaut visible : chevauchement, texte coupé, alignement sur la grille des 4 colonnes perdu, élément allumé en trop, couleur hors charte, cible trop petite, contraste faible.

- [ ] **Step 2: Finish review with the impeccable skill, then DESIGN.md**

Charger la skill `impeccable` et faire sa revue de finition (polish, puis audit) sur `site/index.html` et ses écrans, avec le contrat `.impeccable/surfaces/site-index-html.md` et les 11 captures. Points à juger en priorité : le premier écran d'Aujourd'hui lisible bras tendu (heure et salle), un seul élément allumé par écran, le top GO (roulement mécanique et bref), les tampons JOUÉ et FAIT, la grille des tranches visible partout, le mouvement réduit. Corriger dans `app.css` ou les écrans, relancer `node tests/e2e/lancer.mjs` et `node tests/e2e/captures.mjs`, jusqu'au verdict « ship ».

Écrire ensuite `DESIGN.md` (le système visuel de l'app, pour les lots 2 à 4) avec ces sections, remplies d'après `app.css` et le contrat :
1. Le monde (la console de régie, ce qui est refusé) ;
2. Les jetons (couleurs et leurs rôles, polices et leurs usages, `--px` et la taille de texte de l'iPhone, rayons, ombres) ;
3. Les pièces (console et afficheur, voyant, tampon, scotch, tranche et fader, GO et cuvette, boutons console, allumé et contour, carte, étape, feuille, bandeaux d'état, messages) ;
4. Les règles (un seul élément allumé, ambre réservé, rouge pour les alertes, grille des 4 colonnes et règle graduée, rien ne disparaît, mouvement mécanique et bref, mouvement réduit) ;
5. Les exceptions voulues (graduations des faders en dégradé répété, contour holographique des cartes rares au lot 4) ;
6. L'accessibilité (cibles, contraste, VoiceOver, taille de texte) ;
7. Le verdict de la revue de finition et sa date.

- [ ] **Step 3: Code review**

Revue de code de tout ce qui a changé depuis le début de la branche (`git diff main...lot1-3-app`), avec un relecteur neuf (superpowers:requesting-code-review). Points à regarder en priorité :
- les 5 points de « Review Focus » ;
- CSP : aucun style ni script en ligne, aucun `innerHTML` ;
- la file des écritures : ordre, `id_client` stable, `attendre()` et « Annuler », identifiant local remplacé, rien de perdu à la fermeture de l'app ;
- l'heure de Paris partout (aucun `getHours()`, `toLocaleDateString()` sans fuseau) ;
- les fuites d'écouteurs et de minuteurs (tirage, `IntersectionObserver`, `tictac`) ;
- la reprise des tâches 1 et 2 côté serveur.

Corriger chaque constat important, test d'abord (vu rouge, puis vert). Noter les mineurs dans `README.md`, section « Prochaine session ».

- [ ] **Step 4: Security review**

Lancer la skill `security-review` sur la branche. Points à regarder en priorité : injection dans le DOM (titres, descriptions, événements), liens créés depuis Trello, `href` de « Ouvrir dans Trello », cookie et `credentials`, ce que garde IndexedDB et son effacement à la déconnexion, la page publique, les en-têtes du `.htaccess`, la table `tentatives`. Même traitement des constats.

- [ ] **Step 5: Run everything and commit**

Run: `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets ; cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && node --test 'tests/js/*.test.mjs' && node tests/e2e/lancer.mjs`
Expected : tout au vert (avec les tests ajoutés par les corrections). Si un fichier en cache a changé, recopier la nouvelle `VERSION` donnée par `php tests/run.php`.

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add -A . && git commit -m "Lot 1 : revues de finition, de code et de sécurité de l'app, DESIGN.md

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

(Vérifier avant avec `git status` qu'aucun fichier de `secrets/`, `serveur/config.php`, `serveur/data/` ni capture ne part : ils sont dans `.gitignore`.)

---

### Task 13 : vérification sur les vraies données, en local

Comme à la fin du plan 2 : la base `jarvisjr_dev`, Google déjà connecté en dev, Trello lu pour de vrai, **écritures Trello simulées** (rien ne part vers la vraie to-do). C'est Colin qui se connecte : Claude ne connaît pas le mot de passe.

**Files:**
- Modify: `README.md` ; ce que les constats font corriger

**Interfaces:**
- Consumes : l'aperçu local (`outils/apercu.php`), la config de dev (`outils/generer-config.php --mode=dev`), `.claude/scripts/trello.py` (comptes attendus).
- Produces : le lot 1 vu par Colin sur ses vraies données ; README à jour.

- [ ] **Step 1: Start the local preview with the dev config**

Run (en arrière-plan) : `{ brew services run mysql@8.4 ; cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php outils/generer-config.php --mode=dev --dossier=serveur && php -S localhost:8787 -t site outils/apercu.php ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Puis : `curl -s http://localhost:8787/api/sante` doit répondre `{"ok":true,"version_app":"0.2.0","version_base":2,"base_ok":true}` (la migration 002 s'applique à la première requête).

- [ ] **Step 2: Prepare what Colin should see**

Run: `{ cd ~/Desktop/jarvis-starter-kit && python3 .claude/scripts/trello.py lire ; } 2>&1 | ~/.claude/hooks/redact-secrets`
En tirer, par tableau, le nombre de cartes À faire et En cours (ce que montrent les tranches) et les cartes en retard. Lire avec le connecteur Google Agenda les événements d'aujourd'hui dans « UTC · Emploi du temps A26 » et dans l'agenda principal.

- [ ] **Step 3: Colin checks his app in Chrome (Colin's step)**

Donner à Colin ces consignes, avec les comptes de l'étape 2, puis attendre son retour :

1. Dans **Chrome** (pas Safari), ouvrir `http://localhost:8787` et te connecter avec ton mot de passe de Jarvis Jr.
2. **Aujourd'hui** : la date, le cours en scène ou le prochain (heure, salle), les 4 compteurs (attendus : QYRIS n, Asso n, Études n, Perso n), les cartes en retard sous le pli.
3. **Tâches** : les mêmes cartes que dans Trello ; ouvre une carte qui a des étapes.
4. **Agenda** : cette semaine et la suivante, comparées à Google Agenda.
5. **GO** : la proposition doit être une vraie étape de ta to-do. Essaie « Autre chose ».
6. **+** : note « Essai Jarvis Jr. » dans Perso. Elle apparaît dans Tâches, mais pas dans Trello : en local, les écritures sont simulées exprès.
7. **Sans réseau** : outils de développement (Cmd + Option + I), onglet Network, choisir « Offline », recharger : l'app s'affiche avec « Hors ligne · données d'il y a … ». Remettre « No throttling ».
8. Dis-moi tout ce qui cloche, même un détail.

- [ ] **Step 4: Check the server side, fix, stop**

Run: `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && tail -20 serveur/data/erreurs.log 2>/dev/null ; } 2>&1 | ~/.claude/hooks/redact-secrets`
Expected : aucune ligne nouvelle, ou seulement des lignes techniques (jamais un titre de carte, un jeton ni un mot de passe).

Corriger chaque anomalie remontée par Colin (test d'abord, `VERSION` du service worker à jour), puis arrêter l'aperçu (tuer le processus `php -S`).

- [ ] **Step 5: Update the README**

`README.md`, section « Statut », ajouter :

```markdown
- 2026-10-XX : **lot 1, plan 3 (l'app iPhone) fait** : Aujourd'hui (écran de conduite, Junior, tranches, sous le pli), tirage GO, Tâches et carte ouverte, capture, Agenda, Moi, connexion, copie IndexedDB, file des écritures hors ligne, service worker, page de confidentialité. Reprises serveur de la revue du plan 2, capture sans seconde carte (migration 002). N tests PHP, M tests de modules (`node --test`), P tests d'écran (Playwright). Revues de finition (verdict « ship », `DESIGN.md`), de code et de sécurité. Vérifié par Colin sur ses vraies données en local. Plan : `plans/2026-10-10-lot1-3-app-iphone.md`
```

(remplacer `XX`, `N`, `M` et `P`), remplacer la section « Prochaine session » par :

```markdown
## Prochaine session

1. Mise en ligne par Colin (`outils/deploy-ovh.sh`), connexion de Google en production depuis Moi, installation sur l'iPhone, recette du lot 1 (SPEC § 12.5) : tâche 14 du plan 3.
2. Préparatifs de Colin restants (carte Trello) : compte cron-job.org avant le lot 2, redirection UTC et mots de passe d'application avant le lot 3. Accord pour créer les 3 médians dans l'agenda principal avant le lot 2 (l'indicateur « médian J-7 » d'Aujourd'hui les attend).
```

garder la liste « À reprendre » en retirant les points traités aux tâches 1 et 2 (jeton illisible, carte rouverte, étape du tirage, semaines purgées, seconde carte au renvoi, écarts de `GET donnees`), et y ajouter les constats mineurs des revues. Dans « Fichiers », ajouter :

```markdown
- `site/js/` : l'app (modules ES, sans build) ; `site/css/app.css` ; `site/sw.js` (sa `VERSION` est donnée par `php tests/run.php`) ; `site/confidentialite.html`
- `tests/js/` : `node --test 'tests/js/*.test.mjs'` (modules purs)
- `tests/e2e/` : `node tests/e2e/lancer.mjs` (Playwright, installer une fois : `npm install --prefix tests/e2e`) ; `node tests/e2e/captures.mjs` (captures de tous les écrans)
- `DESIGN.md` : le système visuel de l'app
```

- [ ] **Step 6: Commit**

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && php tests/run.php && git add -A . && git commit -m "Lot 1 : app vérifiée par Colin sur ses vraies données en local, README

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

---

### Task 14 : mise en ligne, Google en production, iPhone, recette du lot 1

Colin lance la mise en ligne (Claude ne peut pas envoyer sur OVH) et fait les gestes sur l'iPhone ; Claude vérifie en ligne et côté Trello. Le lot 1 est fini quand les 6 points du SPEC § 12.5 sont vérifiés.

**Files:**
- Modify: `README.md`

**Interfaces:**
- Consumes : `outils/deploy-ovh.sh` (tests PHP et des modules, config de production, simulation, confirmation, envoi SFTP, `/api/sante`), `.claude/scripts/trello.py`.
- Produces : https://jr.qyris.fr avec l'app ; Jarvis Jr. installé sur l'iPhone de Colin et connecté à Google.

- [ ] **Step 1: Close the branch**

Tous les tests au vert (PHP, modules, écrans). Utiliser superpowers:finishing-a-development-branch : proposer à Colin de fusionner `lot1-3-app` dans `main` (en local, comme au plan 2) et lui demander s'il veut envoyer `main` sur GitHub (`colinflicourt/app-gestion-projets`, en avance d'une trentaine de commits). Faire ce qu'il choisit.

- [ ] **Step 2: Colin deploys (Colin's step)**

Donner à Colin cette commande, à lancer dans le terminal de VS Code :

```bash
cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && outils/deploy-ovh.sh
```

À lui dire : le script lance les tests, affiche la liste des fichiers qui partent (les nouveaux : `index.html`, `sw.js`, `confidentialite.html`, `css/`, `js/`, `fonts/`, et `serveur/migrations/002_tentatives.sql`), demande « Envoyer ? (o/N) » (répondre `o`), puis vérifie `/api/sante`. Attendu à la fin : `"version_app":"0.2.0","version_base":2,"base_ok":true` puis « En ligne. ». S'il voit autre chose, me coller la sortie.

- [ ] **Step 3: Check online**

Run :

```bash
{ for f in / /sw.js /manifest.webmanifest /fonts/archivo.woff2 /confidentialite.html; do echo "== $f"; curl -sI "https://jr.qyris.fr$f" | grep -iE '^(HTTP|content-type|cache-control|content-security-policy|strict-transport-security|x-content-type-options)'; done; echo "== http"; curl -sI http://jr.qyris.fr/ | head -1; echo "== api sans session"; curl -s https://jr.qyris.fr/api/donnees; echo; for f in /serveur/config.php /.ovhconfig /api/../serveur/trello-ids.json; do printf '%s : ' "$f"; curl -s -o /dev/null -w '%{http_code}\n' "https://jr.qyris.fr$f"; done ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

Expected :
- `/` : 200, `text/html`, `Cache-Control: no-cache`, la CSP du SPEC § 5.6, HSTS, `nosniff` ;
- `/sw.js` et `/manifest.webmanifest` : `no-cache`, le manifeste en `application/manifest+json` ;
- `/fonts/archivo.woff2` : 200, `font/woff2` ;
- `/confidentialite.html` : 200 ;
- `http://` : 301 vers `https://` ;
- `/api/donnees` : `{"ok":false,"erreur":{"code":"session_expiree",…}}` ;
- `/serveur/config.php`, `/.ovhconfig`, `/api/../serveur/trello-ids.json` : 404 (ou 403), jamais 200.

Puis, sans secret dans git : `{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git grep -n -I -E 'ATTA[0-9a-f]{20}|GOCSPX-|-----BEGIN' ; echo "fin" ; } 2>&1 | ~/.claude/hooks/redact-secrets` → seulement « fin ».

- [ ] **Step 4: Colin connects Google in production (Colin's step)**

Consignes : sur le Mac, dans Chrome, ouvrir https://jr.qyris.fr, te connecter, aller dans **Moi**, toucher **« Connecter Google Agenda »**, choisir colin.flicourt@gmail.com. Si Google affiche « Google n'a pas validé cette application » : « Paramètres avancés », puis « Accéder à Jarvis Jr ». Accepter la lecture de l'agenda. Tu reviens sur Moi avec « Agenda connecté. ». (Les jetons sont gardés sur le serveur : l'iPhone en profitera sans refaire la connexion.)

- [ ] **Step 5: Colin installs the app on his iPhone (Colin's step)**

Consignes : sur l'iPhone, dans **Safari**, ouvrir https://jr.qyris.fr, toucher **Partager**, puis **« Sur l'écran d'accueil »**, garder le nom « Jarvis Jr. », **Ajouter**. Ouvrir l'app depuis l'icône (elle a sa propre mémoire, séparée de Safari) et te connecter une fois.

- [ ] **Step 6: Acceptance of lot 1 (SPEC § 12.5), with Colin**

Faire chaque point dans l'ordre, noter le résultat dans la conversation :

1. **Installée et connectée** : l'icône « Jr. » sur l'écran d'accueil, l'app s'ouvre en plein écran sur Aujourd'hui (Colin).
2. **Vraies données, Google compris** : Aujourd'hui, Agenda et Tâches correspondent à Trello et à Google Agenda (Colin regarde ; Claude compare avec `trello.py lire` et le connecteur Google Agenda).
3. **Écritures** : Colin coche une étape d'une vraie carte (au choix), puis note « Essai Jarvis Jr. » dans Perso avec le +. Claude lance `{ cd ~/Desktop/jarvis-starter-kit && python3 .claude/scripts/trello.py lire --details ; } 2>&1 | ~/.claude/hooks/redact-secrets` : l'étape est cochée et la carte « Essai Jarvis Jr. » est dans Perso, À faire. C'est ce que Jarvis voit au `/prime`.
4. **GO** propose une vraie étape (Colin).
5. **Sans réseau** : mode Avion, ouvrir l'app : elle s'affiche avec « Hors ligne · données d'il y a … ». Toujours en mode Avion, noter « Essai hors ligne » dans Perso (« Pas de réseau. Je l'envoie dès que ça revient. »). Couper le mode Avion, rouvrir l'app : la note part ; Claude vérifie avec `trello.py lire` qu'elle est dans Trello **une seule fois**.
6. **Sécurité** : vérifiée à l'étape 3 (API refusée sans session, `serveur/` inaccessible, en-têtes actifs, aucun secret dans git). Le blocage au 11e essai de mot de passe est couvert par les tests PHP : on ne l'essaie pas en ligne, il bloquerait aussi l'adresse IP de Colin pendant 15 min.

Puis, avec l'accord de Colin, archiver les 2 cartes d'essai : `{ cd ~/Desktop/jarvis-starter-kit && python3 .claude/scripts/trello.py archiver ID ; } 2>&1 | ~/.claude/hooks/redact-secrets` (identifiants courts lus à l'étape 3 et 5), et décocher l'étape cochée au point 3 si Colin le demande.

- [ ] **Step 7: Close lot 1**

`README.md`, section « Statut », ajouter :

```markdown
- 2026-10-XX : **lot 1 en ligne et recetté** sur https://jr.qyris.fr : installé sur l'iPhone de Colin, Google connecté en production, les 6 points du SPEC § 12.5 vérifiés (écritures arrivées dans Trello, hors ligne compris, une seule fois).
```

et dans « Prochaine session », remplacer le point 1 par : « Lot 2 (notifications, rappels, brief du matin, Siri) : section du SPEC à écrire, puis son plan. ».

Mettre à jour la carte Trello Jarvis Jr. (avec l'accord de Colin) : `{ cd ~/Desktop/jarvis-starter-kit && python3 .claude/scripts/trello.py modifier xAc3LTLE --detail "…" ; } 2>&1 | ~/.claude/hooks/redact-secrets`, description reprise de l'actuelle avec « Lot 1 en ligne et recetté le 2026-10-XX ; prochaine étape : lot 2 », et cocher l'étape « Trancher le message Google… » (`cocher xAc3LTLE "Trancher le message Google"`).

```bash
{ cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git add README.md && git commit -m "Lot 1 en ligne et recetté

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

Proposer à Colin un `/end` pour mettre `CONTEXT.md` et `HISTORY.md` à jour.

---

## Couverture du spec par ce plan

| Section du spec | Tâche |
|---|---|
| 3 (architecture de l'app : page unique, modules, calcul pur à part, routeur, `api.js`, `stockage.js`) | 3, 4, 5, 7 |
| 4.5 (IndexedDB : `donnees`, `agenda`, `file`) | 5, 9 |
| 5.6 (en-têtes, `.htaccess`, cache des fichiers sensibles, type des polices) | 3, 11, 14 |
| 7.1 (connexion à Google depuis Moi, retour sur Moi) | 10, 14 |
| 8.1 (connexion, Aujourd'hui, Agenda, Tâches, carte ouverte, capture, Moi, 4 onglets et le +) | 5, 6, 7, 8, 9, 10 |
| 8.2 (phrase du jour) | 7 |
| 8.3 (tirage : temps libre, candidats, score, relances, textes, animation, « C'est parti ») | 7 |
| 8.4 (hors ligne : copie, écriture locale, file, réponses du serveur, service worker) | 5, 6, 8, 11 |
| 8.5 (accessibilité : 44 px, contraste, mouvement réduit, VoiceOver, taille de texte, polices hébergées) | 3, 6 à 12 |
| 10.2 (déploiement par Colin, tests avant envoi) | 4, 14 |
| 12.2 (tests JS des modules purs) | 4, 5, 7 |
| 12.3 (Playwright : 375, 390, 768 px, hors ligne, tirage, mouvement réduit, console sans erreur) | 3, 5 à 11 |
| 12.4 (revue de code et de sécurité avant la mise en ligne) | 12 |
| 12.5 (le lot 1 est fini quand…) | 13, 14 |
| 13 (textes nouveaux) | 3, 7, 10 |
| Constats reportés de la revue du plan 2 | 1, 2 |
| Page d'accueil et règles de confidentialité exigées par Google | 10, 14 |

## Hors de ce plan

- **Lot 2** : notifications web, planificateur et cron-job.org, rappels automatiques et manuels, brief du matin, raccourci Siri, choix « Rappel » de la capture (section du SPEC à écrire avant son plan). Avant lui : créer les 3 médians dans l'agenda principal, avec l'accord de Colin.
- **Lots 3 et 4** : mails ; XP, grades, badges, cartes et album, journal, Wrapped, combat contre le boss (« C'est parti » le lancera), lien Réglages dans Moi.
- **Constats reportés** : agenda UTC introuvable sans signal (à régler avant P27), événement avec heure sur plusieurs jours, `set_error_handler` dans les tests PHP, détection des actions hors transaction.
