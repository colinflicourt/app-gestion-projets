# Jarvis Jr.

Le petit frère de Jarvis, dans la poche de Colin : appelé « Junior » dans l'app, adresse prévue jr.qyris.fr. Web app installable sur l'iPhone, Colin est le seul utilisateur. Elle réunit emploi du temps, to-do Trello Jarvis, rappels par notification, mails (UTC via Gmail, Gmail, iCloud, colin@ et contact@qyris.fr), plus « Je fais quoi maintenant ? », XP, Wrapped du dimanche et session focus contre un boss de fin.

Remplace l'ancienne idée d'« application personnelle de gestion de projets » (décision du 2026-10-07). Dossier renommé de `app-gestion-projets/` en `jarvis-jr/` le même jour.

## Statut

- 2026-10-07 : plan validé (`PLAN.md`), méthode `guides/CREER-UN-SITE-WEB.md` adaptée à une app. Inventaire fait (`INVENTAIRE.md`, à relire). Canvas de suivi créé
- Suivi : https://claude.ai/artifact/RRARGbQX4ZvSVSrFD3D1yo
- Contenu validé le 2026-10-07 (`CONTENU.md`)
- Direction artistique choisie le 2026-10-07 : la console de régie
- Maquette validée le 2026-10-07 (14 écrans) : https://claude.ai/artifact/ACPbctYmzhyqJrQk4ou4Nj
- Étape 7 (visuels) terminée le 2026-10-08, sous réserve de la validation de la maquette par Colin : icône, écran de lancement, badges, boss détourés (`site/img/boss/`), 4 fonds et 70 cartes générés par Colin et encodés (`site/img/fonds/`, `site/img/cartes/`, `site/data/cartes.json`), maquette mise à jour (version 28 : vrais boss, « J'ai fini la tâche », album, carte rare au contour brillant)
- 2026-10-08 : récompenses conçues avec Colin (superpowers:brainstorming) : cartes à collectionner et album, bouton « J'ai fini la tâche », fonds des combats. Règles dans `CONTENU.md` § 6 et § 8 bis, visuels dans `VISUELS.md` § 4 bis et § 7, banque des 70 cartes dans `CARTES.md`
- 2026-10-08 : maquette v28 validée par Colin, hébergement prêt (voir plus bas), **test serveur réussi** (voir « Constats du test serveur »)
- 2026-10-08 : **étape 8a faite** : spec technique `SPEC.md` validée par Colin (socle commun et lot 1 en détail), puis plan 1 du lot 1 (`plans/2026-10-08-lot1-1-socle-serveur.md`). Le lot 1 est découpé en 3 plans successifs : socle serveur, données, app iPhone
- 2026-10-09 : **lot 1, plan 1 (socle serveur) fait et en ligne** : base migrée, connexion, sessions, `/api/sante` (`{"ok":true,"version_app":"0.1.0","version_base":1,"base_ok":true}`). 62 tests au vert. Revue de code avant la mise en ligne : 3 constats corrigés (essais de connexion comptés avant la vérification, migration rejouable, dépréciations PHP tolérées). Vérifié en ligne : HTTPS forcé, en-têtes de sécurité, `serveur/` hors du web, API qui refuse sans session ou depuis une autre origine. Mot de passe choisi par Colin. Plan : `plans/2026-10-08-lot1-1-socle-serveur.md`
- 2026-10-10 : **lot 1, plan 2 (données) fait** : lecture des 4 tableaux Trello en un appel, Google Agenda (connexion PKCE, jetons chiffrés, 2 agendas), créneaux et bandeaux, copies rafraîchies sous verrou, actions faites ailleurs notées, écritures de la to-do (cocher, terminer, annuler, déplacer, ajouter, tirage) avec liste blanche. 148 tests au vert. Vérifié en local sur les vraies données (Google connecté en dev, 15 jours identiques à l'agenda « UTC · Emploi du temps A26 »). Revue de code : 3 constats corrigés (verrou de la copie tenu pendant toute une écriture, pour ne pas noter deux fois une action ; carte supprimée dans Trello renvoyée en 404 et retirée de la copie ; verrou occupé qui rend la dernière copie au lieu d'une 500). Rien de mis en ligne : ce sera fait avec l'app, à la fin du plan 3. Plan : `plans/2026-10-09-lot1-2-donnees.md`
- 2026-10-10 : **lot 1, plan 3 (l'app iPhone) fait** : Aujourd'hui (écran de conduite, Junior, tranches, sous le pli), tirage GO, Tâches et carte ouverte, capture, Agenda, Moi, connexion, copie IndexedDB, file des écritures hors ligne, service worker, page de confidentialité. Reprises serveur de la revue du plan 2, capture sans seconde carte (migration 002). 159 tests PHP, 30 tests de modules (`node --test`), 45 tests d'écran (Playwright). Revues de finition (verdict « ship », `DESIGN.md`), de code (4 constats corrigés : file des écritures sans écriture perdue ni ressuscitée, réponse illisible gardée en file, déconnexion sur une session déjà effacée, `sw.js` envoyé en dernier) et de sécurité (aucune faille sérieuse). Vérifié par Colin sur ses vraies données en local, dans Chrome. Plan : `plans/2026-10-10-lot1-3-app-iphone.md`
- 2026-10-10 : **lot 1 en ligne et recetté** sur https://jr.qyris.fr : installé sur l'iPhone de Colin, Google connecté en production, les 6 points du SPEC § 12.5 vérifiés (écritures arrivées dans Trello, hors ligne compris, une seule fois). `main` envoyé sur GitHub

## Prochaine session

1. Lot 2 (notifications, rappels, brief du matin, Siri) : section du SPEC à écrire, puis son plan.
2. Préparatifs de Colin restants (carte Trello) : compte cron-job.org avant le lot 2, redirection UTC et mots de passe d'application avant le lot 3. Accord pour créer les 3 médians dans l'agenda principal avant le lot 2 (l'indicateur « médian J-7 » d'Aujourd'hui les attend).
3. À trancher par Colin : les chiffres des codes d'UV au marqueur (`DESIGN.md`, fin), l'effacement de la copie de l'iPhone au premier 401 (constats ci-dessous), et le piège de « Terminer » vu à la recette : sur la carte ouverte, c'est le seul bouton allumé, Colin l'a touché pour quitter la carte après avoir coché une étape (la carte est partie dans Fait). Le retour est la petite flèche en haut à gauche.

**À reprendre** (revue du plan 2, 2026-10-10) :
- **Agenda UTC introuvable sans signal** : si `calendarList` échoue à la connexion, ou si l'agenda ne s'appelle plus « UTC · Emploi du temps A26 » (semestre P27), 15 jours sans cours et `erreur: null`. Il faut un code d'erreur (texte à valider par Colin) et une nouvelle recherche des agendas. **À faire avant P27**
- Un événement avec heure sur plusieurs jours n'apparaît que le premier jour (aucun test)
- Les tests n'installent pas `set_error_handler('erreur_php')` comme la production
- Détection des actions et écriture de la copie hors transaction (actions notées deux fois si `copie_ecrire` échoue)
- Écarts au SPEC § 5.5 à ajouter à la liste : champ `median` des événements, champ `semaine` de `GET agenda`

**Constats mineurs des revues du plan 3** (code et sécurité, 2026-10-10), non corrigés :
- **Panne sans copie, aucun message** : à la toute première ouverture, Trello en panne donne « Aucune carte ici. » et une to-do vide, Google en panne « Rien de prévu. » (le bandeau ne s'affiche que s'il existe une copie, `ecrans/commun.js`). Côté serveur, Google en panne sans copie répond « Agenda pas encore connecté » (`donnees.php`)
- **Blocage en tête de file** : une écriture qui reçoit toujours une 5xx (un 400 de Trello devient une 502) repart toutes les 30 s, bloque les suivantes et affiche « attend le réseau » alors que le réseau marche. Prévoir un message après quelques essais (`essais` existe déjà)
- **Carte ouverte d'une capture en attente** : `#/carte/local-…` devient « Cette carte n'existe plus dans Trello. » dès que la capture part. Remplacer l'adresse par le vrai identifiant dans `succes`
- **Agenda** : une semaine chargée n'est jamais relue (même en tirant pour actualiser) ; le jour choisi reste sur la veille quand l'app passe minuit ouverte ; une semaine absente n'est redemandée qu'à la navigation suivante, pas au retour du réseau
- **`tictac` redessine tous les écrans chaque minute** : focus perdu (VoiceOver), défilement par inertie arrêté sur Tâches, toucher perdu s'il tombe à la minute pile. Le limiter à Aujourd'hui et à Agenda
- **Feuille « Noter » fermée par un 401 venu en arrière-plan** (renvoi de la file) : le texte en cours est perdu
- **Tests qui prouvent moins que leur nom** : « réponse perdue » et « capture hors ligne » peuvent passer par un renvoi de Chromium plutôt que par la file (la logique de la file est maintenant couverte par `tests/js/file.test.mjs`) ; « Terminer puis Annuler » passe aussi sans aucun envoi ; rien ne combine rechargement et réponse perdue ; aucun titre piégé testé dans l'agenda, la liste ou le tirage (sûrs par construction)
- **Tailles fixes** : bandeau d'état (12 px), onglets et titres de section ne suivent pas la taille de texte de l'iPhone, contrairement à la règle de `DESIGN.md` § 2
- `ordre` des écritures part de `Date.now()` au lancement : si l'horloge de l'iPhone recule entre deux ouvertures, l'ordre de la file s'inverse. Partir de `max(ordre) + 1` dans `charger()`
- `erreurGoogle` (Moi) reste vrai à chaque visite jusqu'à une connexion réussie
- `file.envoyer()` appelé sur une file vide laisse l'envoi bloqué pour toujours (le `finally` passe avant l'affectation). Inatteignable aujourd'hui (`envoyerFile` vérifie la file avant), à blinder
- **Sécurité, à trancher par Colin** : un 401 n'efface pas la copie de l'iPhone. Après « Déconnecter tous les appareils », un iPhone perdu ne voit plus le serveur mais garde sa to-do et son agenda lisibles hors ligne. Piste : effacer `donnees` et `agenda` (pas `file`) au premier 401
- Sécurité, connu : `serveur/config.php` est sous le même utilisateur que les autres sites PHP de l'OVH perso ; une faille dans l'un d'eux permettrait de le lire

**À savoir pour les tests** : une colonne JSON de MySQL range les clés d'un objet à sa façon. Tout ce qui est relu de `copies.contenu` ou `ecritures.resultat` se compare sans l'ordre des clés.

## Décisions

- Web app installable (pas d'app iOS native), hébergée sur l'OVH perso, stack HTML/CSS/JS natifs et PHP sans Composer
- To-do = les 4 tableaux Trello Jarvis seulement (QYRIS, Asso, Études, Perso)
- Mot de passe UTC jamais dans l'app : boîte UTC redirigée vers Gmail par Tuttifruti
- Direction artistique : la console de régie du Cabaret, choisie contre « La lineup », « HEAD » et « Player 1 ». Fond graphite, afficheur ambre sans halo, une couleur par domaine, tampons FAIT et JOUÉ, un seul bouton GO
- Nom : Jarvis Jr., « Junior » dans l'app, adresse jr.qyris.fr. Icône : « Jr. » sur la touche GO ambre (choisie le 2026-10-07). Junior monte en grade avec l'XP de Colin (Stagiaire, Assistant, Bras droit, Majordome)
- Base MySQL sur l'OVH (3 bases libres). SFTP probablement inclus, à vérifier au premier déploiement. iPhone 14 en iOS 26.6 : notifications et Wake Lock disponibles

## Hébergement et accès (préparés le 2026-10-08)

- Sous-domaine jr.qyris.fr en Multisite, dossier racine `jr.qyris.fr/www` : le code serveur ira dans `jr.qyris.fr/serveur/`, hors du web. Certificat SSL demandé, en attente le 2026-10-08
- Base MySQL créée par Colin, accès dans `secrets/jarvis-jr.env` (`DB_*`, droits 600)
- Envoi des fichiers en **SFTP** (port 22, activé sur l'offre), avec le compte principal : l'offre n'autorise pas d'utilisateur FTP dédié
- PHP global de l'hébergement : 8.2. **jr.qyris.fr passé en PHP 8.4** le 2026-10-08 par un `.ovhconfig` déposé à la fois dans `jr.qyris.fr/` et `jr.qyris.fr/www/` (appliqué immédiatement ; on ne sait pas lequel des deux compte, donc le déploiement ne doit pas effacer `www/.ovhconfig`)
- Trello : jeton dédié « Jarvis Jr » (`TRELLO_TOKEN` dans `jarvis-jr.env`), clé reprise de `secrets/trello.env`
- Google Cloud : projet « Jarvis Jr », API Agenda, portée `calendar.readonly`, client « Application Web » (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`). URI de redirection : `https://jr.qyris.fr/api/google-callback.php` et `http://localhost:8787/api/google-callback.php` (aperçu local sur le port 8787). Pour passer « En production », Google exige une page d'accueil et des règles de confidentialité : `https://jr.qyris.fr/` et `https://jr.qyris.fr/confidentialite.html`, une page publique à écrire au lot 1. Domaine autorisé : qyris.fr

## Constats du test serveur (2026-10-08)

Diagnostic `outils/test-serveur/` (`diagnostic.php`, envoyé et retiré par Colin avec `envoyer.sh` et `nettoyer.sh`). Envois OVH par `outils/ovh-sftp.sh` : SFTP, une seule tentative, mot de passe hors de la ligne de commande. Le garde-fou d'autorisations de Claude Code refuse à Claude les envois sur l'hébergement : Colin les lance lui-même.

- **PHP 8.4.22** (FPM), mémoire 512 Mo, 165 s d'exécution, fuseau Europe/Paris, `fastcgi_finish_request` disponible. Erreurs non affichées et **non journalisées** (`log_errors` vide) : l'app tient son propre journal dans `serveur/data/`
- Sur le Mac, PHP 8.5 : ne pas utiliser ce qui est propre à 8.5 (opérateur `|>`, `array_first`, `#[\NoDiscard]`…)
- Extensions utiles présentes : pdo_mysql, openssl, sodium, curl, mbstring, intl, gd, imagick, zip, sockets. **Pas d'APCu**. L'extension `imap` existe chez OVH mais pas sur le Mac, et elle n'est plus maintenue : on garde le client IMAP maison prévu
- Crypto OK sur le serveur (OpenSSL 1.1.1n, ancien mais suffisant) : clés EC P-256, ECDH, ECDSA SHA-256, AES-128-GCM, HKDF, libsodium, bcrypt
- `serveur/` (hors du web) lisible et inscriptible, verrou `flock` OK
- **MySQL 8.4**, utf8mb4 (`utf8mb4_0900_ai_ci`), heure du serveur = Paris, fuseaux nommés chargés, JSON, transactions et `GET_LOCK` OK. `sql_mode` **non strict** (`NO_ENGINE_SUBSTITUTION`) : l'app doit fixer le mode strict à chaque connexion. Base vide au départ
- Réseau sortant OK : HTTPS vers Trello, Google (Agenda, OAuth), Apple et Google (notifications) ; **IMAP 993 vers Gmail, iCloud et OVH (ssl0.ovh.net)**
- Apache : `.htaccess` actif (`Header`, `Options -Indexes`, `RewriteRule`), fichiers commençant par un point refusés (403), pas d'en-tête `X-Powered-By`
- À régler dans le `.htaccess` de l'app :
  - `.webmanifest` servi **sans type MIME** : ajouter `AddType application/manifest+json .webmanifest` ;
  - OVH ajoute `Cache-Control: max-age=900` aux fichiers statiques (mode production) : forcer `no-cache` sur `index.html`, `sw.js` et le manifeste ;
  - **HTTP n'est pas redirigé vers HTTPS** : ajouter la redirection et HSTS.

## Fichiers

- `PLAN.md` : plan complet (écrans, architecture, outils, étapes, vigilance, vérification)
- `SPEC.md` : spec technique (étape 8a), validée le 2026-10-08
- `plans/` : plans d'implémentation, un fichier par plan
- `INVENTAIRE.md` : tes données (Trello, agendas, mails) et ce qu'on réutilise (code, visuels, ton)
- `CONTENU.md` : contenu validé (voix de Junior, libellés, notifications, XP et grades, badges, Wrapped)
- `PRODUCT.md` : fiche produit pour le design (moments d'usage, contraintes, principes)
- `VISUELS.md` : inventaire et origine de chaque image (icône, écran de lancement, badges, boss, fonds des combats, cartes)
- `CARTES.md` : banque des 70 cartes à collectionner (titres, répliques de Junior, prompts, lots)
- `serveur/` : l'API PHP (`lib/`, `routes/`, `migrations/`), hors du web, envoyée dans `jr.qyris.fr/serveur/`
- `site/` : la partie publique (`api/index.php`, `.htaccess`, `robots.txt`, images), envoyée dans `jr.qyris.fr/www/`
- `tests/` : `php tests/run.php`, toujours à travers le filtre des secrets (MySQL local lancé : `brew services run mysql@8.4`)
- `outils/deploy-ovh.sh` et `outils/definir-mot-de-passe.sh` : mise en ligne et choix du mot de passe, lancés par Colin dans son terminal
- `site/js/` : l'app (modules ES, sans build) ; `site/css/app.css` ; `site/sw.js` (sa `VERSION` est donnée par `php tests/run.php`) ; `site/confidentialite.html`
- `tests/js/` : `node --test 'tests/js/*.test.mjs'` (modules purs)
- `tests/e2e/` : `node tests/e2e/lancer.mjs` (Playwright, installer une fois : `npm install --prefix tests/e2e`) ; `node tests/e2e/captures.mjs` (captures de tous les écrans)
- `DESIGN.md` : le système visuel de l'app
- `outils/apercu.php` : aperçu local, `php -S localhost:8787 -t site outils/apercu.php`. À ouvrir dans **Chrome** : Safari refuse le cookie de session (Secure) sur `http://localhost`, la connexion n'y tient pas
- `outils/installer-mysql-local.sh` : MySQL 8.4 local et ses deux bases (déjà fait le 2026-10-08)
- `outils/ovh-sftp.sh` : commandes lftp en SFTP sur l'OVH perso (à lancer à travers le filtre des secrets)
- `outils/test-serveur/` : diagnostic de l'hébergement, `envoyer.sh` et `nettoyer.sh` (lancés par Colin)
- `outils/visuels/` : source de l'icône, `rendre.py` (icône et écran de lancement), `detourer_boss.py` (les 8 boss en WebP transparent) et `encoder_cartes.py` (cartes, fonds et `site/data/cartes.json`), avec Pillow (numpy en plus pour le détourage)
- `assets-originaux/` : images d'origine, jamais publiées telles quelles (`generees/` pour celles que tu génères)
- `.impeccable/surfaces/site-index-html.md` : contrat de la direction artistique (à relire avant chaque écran)
- `.impeccable/decision/directions.json` : les cartes de la page de décision
