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

## Prochaine session

1. **Étape 8a, spec technique** : `SPEC.md` avec superpowers:brainstorming, à partir de l'architecture de `PLAN.md` et des constats du test serveur, puis un plan d'implémentation par lot (superpowers:writing-plans).
2. Préparatifs de Colin restants (carte Trello) : compte cron-job.org avant le lot 2, redirection UTC et mots de passe d'application avant le lot 3.

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
- `INVENTAIRE.md` : tes données (Trello, agendas, mails) et ce qu'on réutilise (code, visuels, ton)
- `CONTENU.md` : contenu validé (voix de Junior, libellés, notifications, XP et grades, badges, Wrapped)
- `PRODUCT.md` : fiche produit pour le design (moments d'usage, contraintes, principes)
- `VISUELS.md` : inventaire et origine de chaque image (icône, écran de lancement, badges, boss, fonds des combats, cartes)
- `CARTES.md` : banque des 70 cartes à collectionner (titres, répliques de Junior, prompts, lots)
- `outils/ovh-sftp.sh` : commandes lftp en SFTP sur l'OVH perso (à lancer à travers le filtre des secrets)
- `outils/test-serveur/` : diagnostic de l'hébergement, `envoyer.sh` et `nettoyer.sh` (lancés par Colin)
- `outils/visuels/` : source de l'icône, `rendre.py` (icône et écran de lancement), `detourer_boss.py` (les 8 boss en WebP transparent) et `encoder_cartes.py` (cartes, fonds et `site/data/cartes.json`), avec Pillow (numpy en plus pour le détourage)
- `assets-originaux/` : images d'origine, jamais publiées telles quelles (`generees/` pour celles que tu génères)
- `.impeccable/surfaces/site-index-html.md` : contrat de la direction artistique (à relire avant chaque écran)
- `.impeccable/decision/directions.json` : les cartes de la page de décision
