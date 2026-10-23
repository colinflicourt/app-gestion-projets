> Plan validé par Colin le 2026-10-07 (copie de travail ; original : ~/.claude/plans/humble-puzzling-flask.md).

# Ton app perso d'organisation (nom à choisir) : plan

## Contexte

Ton organisation est éclatée : l'agenda est sur Google depuis aujourd'hui, la to-do sur Trello, et le mail dans 5 boîtes (UTC, Gmail, iCloud, colin@qyris.fr, contact@qyris.fr). Le but est d'avoir **une seule app sur ton iPhone** qui rassemble l'emploi du temps, la to-do, les mails et des rappels qui te préviennent au bon moment. Elle ajoute des fonctions qui donnent envie de l'ouvrir (« Je fais quoi maintenant ? », XP, Wrapped, session focus au ski). Tu es le seul utilisateur. Elle **remplace l'idée de l'« app de gestion de projets »**, dont le dossier est vide depuis juin.

On suit `guides/CREER-UN-SITE-WEB.md`, adapté à une app. On avance une étape à la fois, et tu valides chaque étape.

---

## Tes décisions (prises aujourd'hui)

| Sujet | Décision |
|---|---|
| Forme | **Web app installable** sur l'écran d'accueil, hébergée sur ton OVH. Pas d'app iOS native : elle coûterait 99 $/an, sinon il faudrait la réinstaller tous les 7 jours |
| To-do | **Trello Jarvis**, une seule source : les tableaux QYRIS, Asso, Études et Perso. Ce que tu ajoutes depuis le téléphone, Jarvis le voit au `/prime` |
| Mails | **UTC** (redirigé vers Gmail, voir ci-dessous), **Gmail**, **iCloud**, **QYRIS** (colin@ et contact@) |
| Mot de passe UTC | **Jamais dans l'app.** Tu rediriges ta boîte UTC vers Gmail depuis le webmail UTC, et l'app range ces mails dans une section « UTC » |
| Fonctions | Brief du matin et rappels automatiques, capture rapide et Siri, « Je fais quoi maintenant ? », XP, niveaux, séries et badges, journal et Wrapped du dimanche, session focus (au ski dans ce plan, remplacée le 2026-10-07 par un combat contre un boss de fin) |
| Univers du design | Le dev et l'IA, la scène, le jeu vidéo : trois pistes de direction artistique |
| Écarté pour l'instant | Espace auto-entrepreneur, rendus Moodle, comptes à rebours, widget d'écran d'accueil. Tout reste faisable plus tard sur le même socle |

---

## Ce que fait l'app

**Les écrans** (barre d'onglets en bas, pensée pour le pouce, plus un bouton **+** toujours visible) :

1. **Aujourd'hui** : ton prochain cours, avec la salle et le temps restant. Les tâches urgentes, en retard ou dues aujourd'hui. Les mails non lus par boîte. Les rappels du jour. Le bouton « Je fais quoi maintenant ? »
2. **Agenda** : le jour et la semaine, tes deux agendas Google (le principal et « UTC · Emploi du temps A26 »)
3. **Tâches** : les 4 tableaux Trello Jarvis. Tu peux cocher un élément de checklist, passer une carte dans Fait, la déplacer ou en ajouter une
4. **Mails** : les non lus et les 20 derniers par boîte (UTC, Gmail, iCloud, colin@, contact@), avec l'expéditeur, l'objet, un extrait et un bouton qui ouvre le mail dans la bonne app ou le bon webmail. Lecture seule : l'app ne marque jamais un mail comme lu
5. **Moi** : l'XP par domaine, le niveau, la série, les badges, le journal et les Wrapped passés
6. **Réglages** : les notifications (avec un envoi de test), les heures du brief et des rappels, les comptes mail et la déconnexion

**Les fonctions** :

- **Capture rapide (+)** :
  - une phrase, dictée ou tapée ;
  - c'est une tâche (domaine, urgent, échéance) envoyée dans « À faire » du bon tableau Trello, ou un rappel (« Dans 1 h », « Ce soir 20 h », « Demain 8 h », ou une date) ;
  - hors ligne, la capture attend et part au retour du réseau.
- **Siri** : un raccourci iOS « Retiens pour Jarvis » envoie ta dictée dans la capture, avec un jeton qui ne sert qu'à cela.
- **Rappels automatiques** (tous réglables) :
  - 15 min avant chaque cours ou TD, avec la salle ;
  - la veille d'une échéance Trello à 20 h ;
  - J-7, J-3 et J-1 avant chaque médian ;
  - et tes rappels manuels.
- **Brief du matin** (7 h 30 par défaut, réglable) : une notification avec ta journée (premier cours et salle, tâches du jour, mails non lus, médian le plus proche). Le ton de Jarvis a de la répartie, à la manière de Candid Colin, avec des phrases écrites à l'avance et tirées au hasard. Pas d'API payante.
- **« Je fais quoi maintenant ? »** :
  - l'app calcule ton temps libre jusqu'au prochain cours, moins 10 min pour te déplacer ;
  - elle classe tes cartes selon l'urgence, l'échéance et les cartes « En cours » ;
  - un tirage animé, façon machine à sous, propose **la prochaine étape non cochée** d'une carte. Exemple : « 45 min avant TN01 : Formation 2, tester les exercices » ;
  - « C'est parti » lance une session focus. Tu as 3 relances, ensuite Jarvis s'impatiente.
- **XP, niveaux, séries, badges** :
  - une carte finie, un élément coché, une session focus ou une ligne de journal rapporte de l'XP dans son domaine ;
  - les cartes finies ailleurs (par Jarvis ou dans l'app Trello) comptent aussi ;
  - la série compte les jours avec au moins une tâche finie ou une session focus ;
  - une douzaine de badges, gagnés seulement sur des faits réels.
- **Journal et Wrapped du dimanche** :
  - une ligne et une humeur par jour, avec un rappel le soir que tu peux couper ;
  - le dimanche à 19 h, ta semaine en stories plein écran : tâches finies par domaine, heures de cours, XP, série, meilleur jour, phrase de la semaine, ce qui arrive la semaine suivante ;
  - une notification « Ton Wrapped est prêt ».
- **Session focus contre un boss de fin** (remplace le skieur le 2026-10-07, détail dans `CONTENU.md` et `VISUELS.md`) :
  - la tâche lancée devient un boss, un par domaine (Le Bug, Le 99+, Le Poly, La Flemme), dont la vie fond pendant 25, 45 ou 60 min, ou jusqu'au prochain cours ;
  - l'écran reste allumé (iOS 18.4 ou plus) ;
  - si tu quittes l'app plus de quelques secondes, le boss récupère toute sa vie et le combat est perdu ;
  - une notification t'annonce le K.O., et la session rapporte de l'XP.
- **Hors ligne** : en amphi sans réseau, l'app affiche les dernières données, avec « à jour il y a 12 min ».

---

## Architecture recommandée

```
iPhone : web app installée (HTML/CSS/JS natifs, service worker, stockage local)
   │ HTTPS
   ▼
API PHP sur ton OVH (nouveau sous-domaine, non indexé)
   ├─ Google Agenda (API officielle, connexion Google, lecture seule)
   ├─ Trello (API, jeton dédié à l'app, 4 tableaux Jarvis seulement)
   ├─ IMAP en lecture seule : Gmail (+ UTC redirigé), iCloud, OVH colin@ et contact@
   ├─ Notifications web (serveur de notifications d'Apple, gratuit)
   └─ Base : MySQL OVH si une base est libre, sinon SQLite hors du dossier public
cron-job.org (gratuit) ── appelle le planificateur chaque minute
   (le cron d'OVH ne tourne qu'une fois par heure : il sert de secours)
```

- **Stack** : la tienne. HTML, CSS et JS natifs sans framework ni build, PHP sans Composer. Si GSAP sert, il est copié dans `vendor/` avec une version figée. Aucun CDN, aucun suivi.
- **Code à réutiliser** :
  - Connexion :
    - modèle : `projects/cavanicos/site/api/auth.php` (bcrypt, hash factice, 10 essais par 15 min, `session_regenerate_id`) ;
    - adaptation : un seul utilisateur et une session longue de 90 jours. Sur iPhone, l'app installée a ses propres cookies : il faut un cookie avec une durée, sinon tu serais déconnecté à chaque fermeture.
  - Trello :
    - fonctions `lire`, `ajouter`, `terminer` et `cocher` portées en PHP depuis `.claude/scripts/trello.py` ;
    - identifiants des tableaux, colonnes et étiquettes : `.claude/scripts/trello-ids.json`.
  - Aperçu local : `etudes/GE15/a-point/outils/apercu.py`, transposé en `php -S` avec un routeur sans cache.
  - Script de détourage : `asso/skiut/candid-colin/tools/remove-bg.py`, pour les 4 boss générés.
- **Agenda** :
  - API Google Agenda avec la portée `calendar.readonly` et `singleEvents=true`, pour que Google déplie lui-même les événements répétés ;
  - cache de 10 min ;
  - la connexion à Google se fait une fois. Son jeton est chiffré sur le serveur.
- **Mails** :
  - l'extension IMAP n'existe plus en PHP 8.4 et plus, d'où un petit client maison sur une connexion chiffrée. Il ouvre la boîte en lecture seule, compte les non lus, puis lit les en-têtes et un extrait décodé ;
  - la section UTC regroupe les mails Gmail adressés à ton adresse etu.utc.fr ;
  - les mots de passe d'application sont **saisis par toi dans Réglages**, chiffrés avec libsodium. Claude ne les voit jamais.
- **Notifications** :
  - envoi maison, sans dépendance, selon les normes du web (VAPID et chiffrement aes128gcm), vérifié avec le vecteur de test officiel de la RFC 8291 ;
  - un abonnement mort est supprimé ;
  - le service worker affiche toujours une notification, sinon iOS coupe l'abonnement.
- **Planificateur** (`cron.php`, appelé chaque minute) :
  - un verrou empêche deux passages en même temps ;
  - une clé unique par rappel évite d'en envoyer deux fois ;
  - il génère les rappels des 24 h à venir et envoie ceux qui sont dus, ainsi que le brief, le Wrapped et la fin des sessions focus ;
  - tout est en heure de Paris.
- **Sécurité** :
  - `serveur/config.php` est hors du dossier public et hors de git. Il est généré depuis `secrets/<app>.env` et protégé par le garde-fou ;
  - protection CSRF, en-têtes stricts (CSP, HSTS, `noindex`) ;
  - liste blanche des 4 tableaux Trello dans le code ;
  - aucun contenu personnel dans les journaux d'erreurs.

**Dossier** : `projects/app-gestion-projets/` sera renommé avec le nom de l'app, en gardant son dépôt git.

```
projects/<nom>/
├── site/        # public : index.html, manifest, sw.js, css/, js/ (un module par écran), fonts/, img/, api/*.php (points d'entrée minces)
├── serveur/     # hors du dossier public : lib/ (agenda, trello, imap, push, rappels, xp, focus…), config.php (hors git), data/
├── tests/       # tests PHP (run.php, sans Composer) et scénarios Playwright
├── outils/      # aperçu local sans cache
├── assets-originaux/  archives/  .impeccable/
├── CONTENU.md  PRODUCT.md  DESIGN.md  VISUELS.md  SPEC.md
└── deploy-ovh.sh
```

---

## Les outils

### Pour construire (côté Claude Code)

| Outil | Rôle ici |
|---|---|
| **superpowers** : brainstorming, writing-plans, test-driven-development, systematic-debugging, verification-before-completion, requesting-code-review | Spec, plan d'implémentation par lot, tests d'abord sur la logique sensible (chiffrement des notifications, rappels, XP, tirage), revue avant chaque mise en ligne |
| **impeccable** | `init` (PRODUCT.md), page de décision entre les 3 pistes, `critique`, `polish`, `audit` (verdict « ship ») |
| **ui-ux-pro-max**, **baseline-ui** | Ergonomie mobile (cibles de 44 px, pouce, contrastes), passe de finition |
| **game-feel**, **game-ui-ux** | Tirage façon machine à sous, passage de niveau, combat du boss (coups, K.O., fuite), barres d'XP façon interface de jeu |
| **dataviz** | Graphiques du Wrapped et barres d'XP |
| **fixing-accessibility**, **fixing-motion-performance**, **fixing-metadata** | Accessibilité, animations fluides et économes, manifeste et icônes |
| **copywriting** | Textes de l'app, voix et répliques de Jarvis, noms des badges |
| **security-review**, **code-review** | Avant chaque mise en ligne (secrets, CSRF, connexion, notifications) |
| **Claude Design** | Maquettes mobiles des écrans, que tu peux modifier |
| **Artifact** avec base de données | Canvas de suivi du projet (étapes, décisions, questions) |
| **Playwright** (sinon Chrome sans fenêtre, comme aujourd'hui quand il était occupé) | Tests à 375, 390 et 768 px, hors ligne, mouvement réduit, parcours complets |
| **Connecteur Google Agenda** | Créer les 3 médians dans ton agenda (sur ton accord) et vérifier les données |
| **Connecteur Gmail** | Vérifier que la redirection UTC arrive bien |
| **`trello.py`** | Carte de suivi du projet dans Trello |
| **DALL-E** (toi), **Canva** | Icône de l'app, illustrations éventuelles |
| **PHP 8.5**, **sqlite3**, **Node**, **gh** (installés) | Aperçu local, tests, base locale, dépôt GitHub **privé** sur ton accord |
| **lftp** + garde-fou des secrets | Déploiement OVH filtré |

### Utilisés par l'app au quotidien

| Service | Coût | À créer ou régler par toi |
|---|---|---|
| OVH mutualisé (hébergement, HTTPS, base) | Déjà payé | Sous-domaine en Multisite, dossier racine `<nom>/www`, SSL. Vérifier s'il reste une base MySQL |
| Google Cloud (API Agenda, connexion Google) | Gratuit, sans carte | Projet, API Agenda activée, écran de consentement **« En production »** (en « Test », Google te déconnecte tous les 7 jours), identifiant OAuth « Application Web ». Je te guide pas à pas |
| Trello API | Gratuit | Un jeton dédié à l'app, même procédure que le 30/09, révocable sans casser Jarvis |
| Gmail IMAP | Gratuit | Validation en 2 étapes, puis un mot de passe d'application |
| Webmail UTC | Gratuit | Redirection de la boîte UTC vers Gmail, puis un mail test |
| iCloud IMAP | Gratuit | Mot de passe d'application Apple |
| OVH MX (colin@ et contact@) | Inclus | Leurs mots de passe, que tu saisis toi-même |
| Notifications web Apple | Gratuit | Installer l'app sur l'écran d'accueil, puis autoriser les notifications |
| cron-job.org | Gratuit | Un compte et une tâche chaque minute (je te donne les réglages) |
| Raccourcis iOS | Gratuit | Créer le raccourci « Retiens pour Jarvis » (instructions fournies) |

### Écartés, et pourquoi

- **Xcode et Swift** : installés, mais l'app native coûterait 99 $/an. Si un jour les widgets ou la fiabilité des notifications manquent vraiment, une app iOS pourra réutiliser la même API.
- **Netlify** : tu l'as abandonné, tout est sur OVH.
- **Connecteurs Claude Google et Gmail dans l'app** : ils servent à Claude, pas à l'app, qui a besoin de ses propres accès.
- **API Claude** : payante. La répartie de Jarvis repose sur des phrases écrites à l'avance.
- **Phaser** : trop lourd pour un combat d'ambiance, un canvas 2D suffit.

---

## Les 3 pistes de direction artistique (à départager à l'étape 5)

1. **« HEAD », le graphe de poche** (le dev et l'IA) :
   - tes 4 domaines deviennent 4 branches aux couleurs de cv.qyris.fr ;
   - la journée est un graphe vertical où HEAD avance avec l'heure ;
   - une tâche finie est un commit, la capture un `git commit` ;
   - Jarvis parle en police monospace, façon Claude Code.
   - C'est le prolongement de ton CV.
2. **« La lineup »** (la scène) :
   - ta journée est une lineup de Cabaret : des blocs dont la hauteur suit la durée et la couleur le type, comme ton script de lineup ;
   - une ambiance music-hall de nuit, avec des ampoules qui s'allument au fil de la journée ;
   - le tirage se fait dans une machine à sous de music-hall, le Wrapped devient la revue de la semaine.
3. **« Player 1 »** (le jeu vidéo) :
   - une interface de jeu : barres d'XP par domaine, niveau, série ;
   - les tâches deviennent des quêtes, avec un écran « Level up » ;
   - des effets qui claquent, sans tomber dans le kitsch.

La session focus et les mécaniques de jeu existent quelle que soit la piste. Tu peux aussi mélanger deux pistes.

---

## Les étapes (guide adapté)

| # | Étape | Livrable | Ce que tu décides ou fais |
|---|---|---|---|
| 0 | Démarrage | Dossier renommé, `CONTEXT.md` mis à jour (l'app remplace l'app de gestion de projets), carte Trello Perso | Rien |
| 1 | Plan | Ce document | Tu le valides |
| 2 | Inventaire | `INVENTAIRE.md` : ce que contiennent tes 4 tableaux et tes 2 agendas, sprites et charte du CV réutilisables | Tu relis |
| 3 | Canvas de suivi | Page Artifact avec base de données, lien dans la carte Trello | Rien |
| 4 | Contenu | `CONTENU.md` : nom de l'app (pistes : HEAD, Lineup, Jarvis…), libellés, voix et répliques de Jarvis, textes des notifications, badges | Le nom, le ton, chaque texte |
| 5 | Direction artistique | `PRODUCT.md`, page de décision impeccable entre les 3 pistes, contrat de direction | La piste |
| 6 | Maquette | Planches mobiles dans Claude Design : connexion, Aujourd'hui, Agenda, Tâches, Mails, capture, tirage, focus, Wrapped, Moi, Réglages | La structure |
| 7 | Visuels | `VISUELS.md` : icône (DALL-E ou dessinée), boss de la session focus, badges en SVG, fonds des combats ; `CARTES.md` : banque des 70 cartes à collectionner (ajouté le 2026-10-08) | Les images à générer |
| 8a | Spec technique | `SPEC.md` (brainstorming), puis un plan d'implémentation par lot (writing-plans) | Tu valides la spec, puis chaque plan et la façon de l'exécuter |
| 8b | Développement, en 4 lots | Voir ci-dessous. Après chaque lot : aperçu local, Playwright, revue de code, puis mise en ligne avec ton feu vert et test sur ton iPhone | Tes retours, le feu vert |
| 9 | Contrôle qualité final | Audit impeccable « ship », accessibilité, sécurité, `DESIGN.md` | Les défauts à arbitrer |
| 10 | Mise en ligne finale | Script de déploiement **né sécurisé** : 2 tentatives au plus, mot de passe hors de la ligne de commande, SFTP si ton offre le permet. Simulation relue, fichiers vérifiés | Le feu vert |
| 11 | Clôture | Test complet sur l'iPhone, commit (et GitHub privé sur ton accord), `/end` | Commit et envoi |

**Les 4 lots de développement** :

1. **Socle et quotidien** :
   - installation sur l'iPhone et connexion ;
   - écrans Aujourd'hui, Agenda et Tâches ;
   - capture rapide et mode hors ligne.
   - Dès ce lot, l'app sert tous les jours.
2. **Rappels** :
   - notifications web et planificateur avec cron-job.org ;
   - rappels manuels et automatiques, brief du matin, raccourci Siri.
3. **Mails** : les 5 boîtes en lecture seule, avec la section UTC.
4. **Le fun** :
   - « Je fais quoi maintenant ? » ;
   - XP, niveaux, séries et badges ;
   - journal et Wrapped du dimanche ;
   - session focus contre un boss de fin, avec « J'ai fini la tâche » ;
   - cartes à collectionner et album dans Moi (ajouté le 2026-10-08, `CONTENU.md` § 8 bis).

**À faire par toi, et quand** :
- **Avant le lot 1** :
  - sous-domaine OVH et vérification de la base MySQL ;
  - projet Google Cloud (je te guide) ;
  - jeton Trello dédié.
- **Avant le lot 2** :
  - compte cron-job.org ;
  - installer l'app et autoriser les notifications ;
  - me laisser créer les 3 médians dans Google Agenda : MT23 le 21/10 à 19 h 15, NF93 le 2/11 à 7 h 45, TN01 le 6/11 à 16 h 45. Ils ne sont pas dans ton agenda principal aujourd'hui.
- **Avant le lot 3** :
  - redirection UTC dans le webmail UTC ;
  - mots de passe d'application Gmail (avec la validation en 2 étapes) et iCloud ;
  - saisir toi-même les mots de passe dans Réglages.

---

## Points de vigilance

- **Ton médian MT23 est dans 14 jours** et demande environ 24 h de révision. Place les sessions de l'app autour des révisions, pas à leur place. Le lot 2 t'aidera justement à les tenir.
- **Notifications web sur iPhone** : elles fonctionnent, en Europe aussi, mais elles sont un peu moins fiables qu'une app native, car l'abonnement peut sauter. Pour ça :
  - un indicateur d'état et une notification de test dans Réglages ;
  - les médians gardent aussi leur alerte dans Google Agenda.
- **Redirection UTC** :
  - d'après l'UTC, les mails redirigés ne sont plus gardés sur leur serveur ;
  - surveille le dossier spam de Gmail ;
  - répondre avec ton adresse UTC depuis Gmail obligerait à donner ton mot de passe UTC à Google : à toi de voir, hors de l'app.
- **Google** : un écran « application non vérifiée » s'affiche une seule fois, c'est normal pour une app perso. Il faut la laisser « En production ».
- **Trello** : l'app ne touche qu'aux 4 tableaux Jarvis (Courses et Repas étudiant jamais), comme le veut la règle de `CLAUDE.md`.
- **Heure** : passage à l'heure d'hiver le 25/10/2026, cas couvert par les tests des rappels.
- **Batterie** : la session focus garde l'écran allumé, d'où un fond sombre pour cet écran.
- **Coût total : 0 €.**
- **Durée** : environ 12 à 15 sessions de travail au total, à étaler. C'est une estimation grossière, à revoir après le lot 1.

---

## Vérification

- **Tests PHP** (`tests/run.php`, sans Composer) :
  - chiffrement des notifications contre le vecteur de la RFC 8291 et signature VAPID ;
  - génération des rappels : pas de doublon, heure de Paris, passage à l'heure d'hiver ;
  - lecture IMAP sur des échanges enregistrés et un faux serveur local ;
  - correspondance avec Trello et liste blanche des tableaux ;
  - calcul de l'XP et des séries ;
  - score et tirage de « Je fais quoi ».
- **Playwright** :
  - chaque écran à 375, 390 et 768 px, sans débordement ;
  - capture en ligne et hors ligne ;
  - tirage, focus (sortie de l'app simulée), stories du Wrapped ;
  - mouvement réduit, console sans erreur.
- **Notifications** : d'abord une vraie notification sur Chrome du Mac en local, puis sur ton iPhone après la mise en ligne (installation, autorisation, test, brief du lendemain matin).
- **Sécurité** :
  - toute adresse de l'API refuse sans connexion ;
  - `serveur/` est inaccessible depuis le web ;
  - le 11e essai de mot de passe est bloqué ;
  - jeton Siri limité à la capture ;
  - aucun secret dans git ni dans la sortie des commandes.
- **En ligne** : fichiers identiques au local, en-têtes actifs, `noindex`, test complet sur ton iPhone.
