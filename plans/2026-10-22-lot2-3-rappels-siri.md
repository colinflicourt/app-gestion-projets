# Jarvis Jr., lot 2, plan 3 sur 3 : rappels manuels, Siri, Réglages, mise en ligne

> **Plan du 2026-10-22**, écrit par Claude en **mode chef d'orchestre** (`guides/CHEF-D-ORCHESTRE.md`) : ce plan fixe les décisions et les contrats, le code est écrit par des sous-agents, mission par mission, d'après les fiches ci-dessous. Partie A (code, en local) puis partie B (mise en ligne et recette, avec Colin).

**Goal :** depuis l'iPhone, Colin se met un rappel en deux gestes, dicte une tâche à Siri, règle ses rappels dans Réglages, et le lot 2 tourne en ligne (cron-job.org chaque minute).

**Spec :** `SPEC.md` § 15.6 (rappels manuels), § 15.7 (Siri), § 15.8 (Réglages), § 15.9 (tests et fin du lot) ; `CONTENU.md` § 3 Réglages, § 4 Capture, § 5 Notifications, § 11 Erreurs.

Tous les chemins sont relatifs à `projects/jarvis-jr/`. Branche `lot2-3-rappels-siri`, partie de `main`. **Les sous-agents ne font pas de commit** : Claude relit le compte rendu et commite après chaque mission. Missions **l'une après l'autre** : elles partagent la base MySQL locale des tests et l'arbre git.

Tests (toujours à travers le filtre) :
- PHP : `{ php tests/run.php ; } 2>&1 | ~/.claude/hooks/redact-secrets | tail -15` (MySQL local : `brew services run mysql@8.4`)
- Modules JS : `node --test 'tests/js/*.test.mjs' 2>&1 | tail -15`
- Écran : `{ node tests/e2e/lancer.mjs ; } 2>&1 | ~/.claude/hooks/redact-secrets | tail -20`

Point de départ (`main`, 2026-10-12) : 212 tests PHP, 32 de modules, 53 d'écran.

## Règles communes (recopiées dans chaque fiche)

- PHP 8.4 (8.5 sur le Mac : rien de propre à 8.5), sans Composer, `declare(strict_types=1);`. JS : modules ES sans build ni dépendance. Noms, commentaires, messages en français, commentaires rares, style du code voisin.
- Aucun réseau dans les tests : `http_requete()` remplacée par `faux_http()`.
- Dates en base en UTC (`utc()`, `depuis_utc()`), règles et textes à l'heure de Paris (`paris()`, `iso_paris()`), heure donnée par `maintenant()`.
- Journal : jamais de jeton, d'endpoint complet ni de texte de rappel ou de dictée.
- Toute commande qui touche aux secrets ou à `config.php` passe en entier par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`.
- Textes : ceux de `CONTENU.md` mot pour mot ; les textes nouveaux sont ceux de ce plan (§ Textes nouveaux), rangés dans `site/js/lib/textes.js` côté app. Heures écrites « 8 h », « 8 h 30 ». Pas de tiret long.
- Une écriture de l'app passe par la file (`site/js/file.js`) et par `route_ecriture()` côté serveur (idempotence par `id_client`).

## Décisions (Claude, 2026-10-22)

1. **`rappels_du_jour`** (dans `GET donnees`) = les rappels manuels non annulés d'aujourd'hui (lus dans `rappels`, donc visibles tout de suite, sans attendre le planificateur) + les lignes automatiques d'aujourd'hui de `envois` (types `cours`, `echeance`, `median`, `brief`, statut `prevu` ou `envoye`). Triés par heure. Un élément : `{id, type, heure (ISO Paris), titre, passe (bool), annulable (bool)}`, `id` = l'id du rappel pour un manuel, la clé d'envoi sinon. Seul un rappel manuel pas encore passé est annulable.
2. **Annuler un rappel** : petit bouton « Annuler » sur un rappel manuel du bloc, sans confirmation (il ne coûte qu'à refaire), écriture `rappels/annuler` par la file. Le planificateur retire déjà la ligne `manuel:<id>` d'un rappel annulé.
3. **Siri** : `POST /api/siri` route machine. Jeton de 32 octets (base64url) créé par `POST reglages/siri` (session exigée), montré une seule fois, seule son empreinte SHA-256 gardée dans `jetons` (service `siri`) ; un nouveau jeton remplace l'ancien. Mauvais jeton ou aucun jeton créé : 404. **Limite** : 30 appels réussis par heure glissante, sinon 429 « Trop de notes d'un coup. Réessaie dans une heure. ». **Doublon** : la même phrase (après nettoyage) dans les 2 min → pas de seconde carte, réponse « Déjà noté. ». Table `siri_appels` (migration `005_siri.sql` : `id`, `recu_le`, `empreinte` CHAR(64) du texte nettoyé), purgée au-delà de 24 h. La carte passe par `tache_ajouter()` (domaine, titre, pas urgent, sans échéance). Réponse en texte brut (`text/plain`) pour que le raccourci la lise telle quelle.
4. **Domaine de la dictée** : premier mot comparé sans accents ni majuscules à `qyris`, `asso`, `etudes`, `perso` (et `études`) ; s'il correspond, il est retiré du titre, la ponctuation qui le suit aussi (« Études, réviser » → « Réviser »). Première lettre du titre en majuscule. Titre vide après retrait : 400 « Je n'ai rien entendu. ». Plus de 500 caractères : 400.
5. **Réglages des rappels** : `GET reglages` rend `{rappels: reglages_rappels(), planificateur: {dernier_passage: ISO ou null}, siri: {jeton_cree: bool}}` ; `POST reglages/rappels` (route d'écriture) reçoit l'objet complet, le **refuse en 400** s'il n'est pas déjà valide (heure `HH:MM`, jours 1 à 7 sans doublon et au moins un, minutes parmi 5, 10, 15, 30, 60, médians parmi 1, 3, 7 et 14), sinon l'écrit tel quel et le renvoie. `reglages_rappels_valides()` reste le filet du planificateur.
6. **État du planificateur** dans Réglages : « Dernier passage il y a 1 min. » si moins de 5 min, sinon « Le planificateur ne tourne plus depuis 2 h. Tes rappels ne partent pas. », et « Le planificateur n'est pas encore lancé. » s'il n'est jamais passé.
7. **Session** déménage de Moi vers Réglages (« Se déconnecter », « Déconnecter tous les appareils », même code). Moi garde un lien « Réglages ».
8. **Petits défauts du plan 1 repris ici** : message en français si l'abonnement échoue ; `session_id` remis à nul dans `abonnements` quand la session est supprimée (SPEC § 15.2) ; partie Notifications de Réglages qui reste vide jusqu'à 3 s (afficher l'état connu tout de suite, puis le préciser).
9. **Hors de ce plan** (restent dans le README) : piège de « Terminer », chiffres des codes d'UV, effacement de la copie au premier 401, agenda UTC introuvable avant P27, autres constats des revues du lot 1.

## Textes nouveaux, à valider par Colin

| Où | Texte |
|---|---|
| Capture, rappel « Ce soir 20 h » après 19 h 30 | « Demain 20 h » (CONTENU § 4, SPEC § 15.6) |
| Capture, confirmations | « Rappel prévu dans 1 h. », « Rappel prévu ce soir à 20 h. », « Rappel prévu demain à 8 h. », « Rappel prévu lundi 26 octobre à 14 h 30. » |
| Capture, « Choisir… » dans le passé | « Choisis une heure à venir. » |
| Rappels du jour, rappel passé | heure barrée, sans bouton |
| Rappels du jour, bouton | « Annuler » ; après : « Rappel annulé. » |
| Réglages, parties | « Brief du matin », « Rappels », « Siri », « Session » (CONTENU § 3) |
| Réglages, brief | « Heure », « Jours » (L M M J V S D) |
| Réglages, rappels | « Avant les cours » (5, 10, 15, 30 ou 60 min), « Échéances, la veille à » (heure), « Médians » (J-14, J-7, J-3, J-1) « à » (heure) |
| Réglages, enregistré | « Réglages enregistrés. » |
| Réglages, planificateur | décision 6 |
| Siri, mode d'emploi | « Dans Raccourcis : nouveau raccourci, action « Obtenir le contenu de l'URL ». Adresse `https://jr.qyris.fr/api/siri`, méthode POST, en-tête Authorization `Bearer` suivi du jeton, corps JSON avec un champ `texte` = « Texte dicté ». Ajoute avant « Dicter le texte » et après « Énoncer » le résultat. Nomme-le « Note pour Jarvis ». » |
| Siri, boutons | « Créer le jeton Siri », « Changer le jeton », avertissement « Copie-le maintenant : il ne sera plus affiché. » ; « Un jeton existe. Le changer coupe l'ancien raccourci. » |
| Siri, réponses lues | « Ajouté dans Perso. » (CONTENU, SPEC), « Déjà noté. », « Je n'ai rien entendu. », « Trop de notes d'un coup. Réessaie dans une heure. », « Trello ne répond pas. Réessaie dans un moment. » |
| Notifications, échec d'abonnement | « Je n'ai pas pu activer les notifications. Réessaie dans un moment. » |

---

## Partie A : le code (missions)

| # | Mission | Sous-agent | Modèle, effort |
|---|---|---|---|
| A1 | Rappels manuels côté serveur | `developpeur` | Sonnet, moyen |
| A2 | Siri côté serveur | `developpeur` | Sonnet, haut (sécurité) |
| A3 | Réglages côté serveur et `session_id` | `developpeur` | Sonnet, moyen |
| A4 | Capture « Rappel » et bloc « Rappels du jour » | `developpeur` | Sonnet, moyen |
| A5 | Écran Réglages complet | `developpeur` | Sonnet, moyen |
| A6 | Préparer la mise en ligne | `developpeur` | Sonnet, moyen |
| A7 | Vérification indépendante | `verificateur` | Haiku, moyen |

Puis Claude : relecture ciblée (Siri, jetons, journal), textes, SPEC § 15 et `CONTENU.md` à jour, README, fusion dans `main`.

### A1. Rappels manuels côté serveur

- **Créer** `serveur/lib/rappels_manuels.php`, `serveur/routes/rappels.php`, `tests/unitaires/test_rappels_manuels.php`. **Modifier** `serveur/lib/donnees.php` (ajout de `rappels_du_jour` à `donnees_reponse()`).
- `POST rappels` {`id_client`, `texte` (1 à 500 caractères après `trim`), `quand` (ISO 8601 avec décalage)} par `route_ecriture()` : 400 si texte vide ou trop long, si `quand` illisible, passé de plus de 1 min ou à plus de 1 an. Écrit `rappels` (source `app`), rend `{rappel: {id, texte, quand (ISO Paris)}}`.
- `POST rappels/annuler` {`id_client`, `id`} : `annule = 1` ; rappel inconnu : 404 `rappel_introuvable` ; déjà annulé : succès.
- `rappels_du_jour` : décision 1, fonction pure de construction testée à part (heure de Paris, journée de Paris, passage à l'heure d'hiver le 25/10/2026).
- Critères : tests verts (anciens compris) ; idempotence (même `id_client` deux fois → une seule ligne) ; annulation retirée du bloc ; texte du rappel jamais journalisé.

### A2. Siri côté serveur

- **Créer** `serveur/migrations/005_siri.sql`, `serveur/lib/siri.php`, `serveur/routes/siri.php`, `tests/unitaires/test_siri.php`.
- Routes : `POST siri` (machine, publique, jeton `Authorization: Bearer`, décisions 3 et 4) ; `POST reglages/siri` (session exigée, crée ou remplace le jeton, rend `{jeton}` une seule fois).
- Fonctions pures testées : `siri_destination(texte)` → `{domaine, titre}` ; nettoyage pour l'empreinte (espaces, casse).
- Lire avant : comment `routes/cron.php` lit le jeton (`hash_equals`, en-tête transmis par `.htaccess`) et comment `tache_ajouter()` crée une carte.
- Critères : bon jeton → carte dans Perso · À faire, réponse texte « Ajouté dans Perso. » ; « Études réviser le TD » → Études, titre « Réviser le TD » ; mauvais jeton, sans jeton, jeton jamais créé → 404 ; 31e appel de l'heure → 429 ; même phrase deux fois en 2 min → une carte ; Trello en panne → 502 avec le texte prévu ; nouveau jeton → l'ancien donne 404 ; aucun jeton ni texte dicté dans le journal.

### A3. Réglages côté serveur et `session_id`

- **Créer** `serveur/routes/reglages.php`, `tests/unitaires/test_reglages_routes.php`. **Modifier** `serveur/lib/rappels.php` (fonction de validation stricte à côté de `reglages_rappels_valides()`), `serveur/lib/session.php`.
- `GET reglages` et `POST reglages/rappels` : décision 5. `dernier_passage` lu dans le réglage `cron_dernier_passage`, `jeton_cree` d'après `jetons` (service `siri`).
- `session_supprimer()` et `session_supprimer_toutes()` : `abonnements.session_id` remis à nul pour les sessions supprimées (la suppression de tous les abonnements à « Déconnecter tous les appareils » ne change pas).
- Critères : tests verts ; chaque cas invalide refusé en 400 ; un objet valide relu tel quel par `reglages_rappels()` ; `session_id` nul après une déconnexion simple.

### A4. Capture « Rappel » et bloc « Rappels du jour »

- **Créer** `site/js/lib/heures-rappel.js` (fonctions pures : les 4 choix, libellé « Ce soir 20 h » ou « Demain 20 h », texte de confirmation) et `tests/js/heures-rappel.test.mjs` (après 19 h 30, minuit, 25/10/2026 heure d'hiver, « Choisir… » dans le passé). **Modifier** `site/js/ecrans/capture.js`, `site/js/ecrans/aujourdhui.js`, `site/js/lib/textes.js`, `site/css/app.css` si besoin, `tests/e2e/test_capture.mjs`, `tests/e2e/test_aujourdhui.mjs`, `tests/e2e/fixtures.mjs`.
- Choix « Tâche » ou « Rappel » dans la feuille (Tâche par défaut). Rappel : 4 choix (CONTENU § 4), « Choisir… » ouvre un champ `datetime-local` natif. Envoi par la file, route `rappels` ; confirmation de la décision ; hors ligne : texte existant de CONTENU § 4.
- Bloc « Rappels du jour » d'Aujourd'hui d'après `rappels_du_jour` (vide : « Aucun rappel aujourd'hui. ») ; un rappel créé apparaît tout de suite (ajout local en attendant la réponse) ; « Annuler » par la file (route `rappels/annuler`).
- Critères : tests de modules et d'écran verts ; capture d'écran d'Aujourd'hui avec 2 rappels et de la feuille en mode Rappel à 390 px (chemins dans le compte rendu).

### A5. Écran Réglages complet

- **Modifier** `site/js/ecrans/reglages.js`, `site/js/ecrans/moi.js`, `site/js/lib/textes.js`, `site/css/app.css`, `tests/e2e/test_reglages.mjs`, `tests/e2e/test_moi.mjs`.
- Parties, dans cet ordre : Notifications (existante, + décision 8), Brief du matin, Rappels, Siri, Session (décision 7). Lecture par `GET reglages` ; une modification envoie l'objet complet à `POST reglages/rappels` (par la file), puis « Réglages enregistrés. ». État du planificateur (décision 6). Siri : jeton affiché une fois avec bouton « Copier », mode d'emploi.
- Respecter `DESIGN.md` (console de régie, tailles de texte qui suivent l'iPhone, § 2) et le style des parties existantes.
- Critères : tests d'écran verts ; captures de Réglages à 390 px (haut et bas de l'écran) et de Moi.

### A6. Préparer la mise en ligne

- **Lire** `outils/deploy-ovh.sh`, `outils/test-serveur/`, le README (section mise en ligne). **Modifier** ce qu'il faut pour que : la mise en ligne applique les migrations 004 et 005 ; le diagnostic serveur vérifie sur OVH (PHP 8.4) le vecteur RFC 8291, la signature ES256, la transmission de l'en-tête `Authorization` (bon jeton → 200, mauvais → 404) et `fastcgi_finish_request`.
- **Écrire** `RECETTE-LOT2.md` : les commandes exactes que Colin lance dans le terminal de VS Code (mise en ligne, diagnostic), le réglage de cron-job.org pas à pas (URL `https://jr.qyris.fr/api/cron`, chaque minute, méthode POST, en-tête `Authorization: Bearer <CRON_JETON>`, où lire le jeton sans l'afficher en clair dans une session Claude), le réglage du cron d'OVH en secours, la création du raccourci Siri, et la recette du SPEC § 15.9 en cases à cocher.
- **Ne pas lancer** de mise en ligne ni d'envoi SFTP (c'est Colin). Aucun secret dans les fichiers écrits.
- Critères : `bash -n` sur les scripts modifiés ; tests PHP verts (dont `test_deploiement.php`) ; `RECETTE-LOT2.md` complet.

### A7. Vérification indépendante

Les 3 suites complètes, nombre de tests avant et après, console du navigateur sans erreur sur Aujourd'hui, Capture, Réglages et Moi, `git status` (aucun secret ni fichier de `serveur/data/` suivi), aucun tiret long dans les textes ajoutés de `textes.js`.

---

## Partie B : mise en ligne et recette (avec Colin)

1. Colin : compte cron-job.org, relecture des textes nouveaux (ce plan, `CONTENU.md` § 3, § 5, § 5.1).
2. Colin lance la mise en ligne et le diagnostic (`RECETTE-LOT2.md`), Claude lit les résultats à travers le filtre.
3. Réglage de cron-job.org et du cron d'OVH, raccourci Siri sur l'iPhone.
4. Recette du SPEC § 15.9 sur 24 h. Le lot 2 est fini quand toutes les cases sont cochées.
