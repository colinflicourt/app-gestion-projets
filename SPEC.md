# Jarvis Jr. : spec technique (étape 8a)

> Rédigée le 2026-10-08 avec superpowers:brainstorming, à partir de `PLAN.md`, `CONTENU.md`, `PRODUCT.md`, de la maquette v28 et des constats du test serveur (`README.md`). Les 6 sections de design ont été validées une à une par Colin le même jour. **Statut : validée par Colin le 2026-10-08**, textes nouveaux du § 13 compris.
>
> Portée (choix A de Colin) : **le socle commun et le lot 1 en détail.** Les lots 2, 3 et 4 auront chacun leur section détaillée, écrite juste avant leur plan d'implémentation. Ce document ne redécide ni les écrans, ni les textes, ni le barème (`CONTENU.md` et la maquette font foi) : il décide le *comment*.

---

## 1. But et critères de réussite

- **But** : une seule app sur l'iPhone de Colin pour savoir en 3 secondes ce qui l'attend et quoi faire maintenant.
- **Réussite** : Colin l'ouvre plusieurs fois par jour, ne rate plus un cours, une échéance ou un médian, et ses cartes Trello avancent.
- **Contraintes** : un seul utilisateur, 0 €, HTML, CSS et JS natifs sans build, PHP 8.4 sans Composer, MySQL 8.4 sur l'OVH perso. Trello et Google restent les seules sources. Mails en lecture seule, jamais le mot de passe UTC.
- **Mises en ligne** : c'est Colin qui lance le script de déploiement, car le garde-fou d'autorisations de Claude Code refuse à Claude les envois sur l'hébergement (constaté le 2026-10-08).

## 2. Décisions prises pendant cette session

| # | Sujet | Décision de Colin |
|---|---|---|
| 1 | Portée du spec | Socle commun et lot 1 en détail, une section par lot ensuite |
| 2 | Bouton GO au lot 1 | **Le tirage arrive dès le lot 1**, sans le combat. « C'est parti » ouvre la carte, l'étape en évidence. Au lot 4, il lancera le boss |
| 3 | XP du lot 4 | **Elle compte depuis le lot 1** : un journal des actions réelles est tenu dès le lot 1, l'XP en sera calculée |
| 4 | Base de dev | **MySQL 8.4 installé avec Homebrew** sur le Mac, même version qu'OVH, lancé seulement pendant les sessions de dev |
| 5 | Récupération des données | **À la demande, avec copie locale** : l'iPhone affiche sa copie tout de suite, le serveur rafraîchit si sa copie a vieilli. Le planificateur du lot 2 réutilisera la même fonction |

## 3. Architecture

```
iPhone : web app installée
  site/                     public, envoyé dans jr.qyris.fr/www
    index.html  manifest.webmanifest  sw.js  robots.txt  .htaccess
    css/  fonts/  img/  data/cartes.json
    js/app.js               routeur entre écrans (#/aujourdhui, #/agenda…), état général
    js/api.js               appels au serveur, file d'attente hors ligne
    js/stockage.js          copie locale (IndexedDB)
    js/ecrans/              connexion, aujourdhui, agenda, taches, carte, capture, tirage, moi
    js/lib/                 temps.js (heure de Paris), tirage.js (score), phrases.js, textes.js
    api/index.php           point d'entrée unique de l'API
  │ HTTPS
serveur/                    hors du web, jr.qyris.fr/serveur
  lib/  amorce.php (config, erreurs, journal)  base.php (PDO strict, migrations)
        session.php  http.php (client curl)  trello.php  google.php  creneaux.php
        rafraichir.php (copies + détection)  actions.php  ecritures.php  chiffrement.php
        routes.php (table des routes)  reponse.php (JSON, en-têtes)
  migrations/  001_socle.sql …
  trello-ids.json           copié depuis .claude/scripts/ au déploiement
  data/                     erreurs.log (créé par l'app)
  config.php                généré, droits 600, hors git
tests/      run.php, unitaires/, fixtures/ (réponses Trello et Google inventées), e2e/ (Playwright)
outils/     apercu.php (routeur de php -S), generer-config.php, definir-mot-de-passe.sh,
            deploy-ovh.sh, ovh-sftp.sh, test-serveur/, visuels/
```

**Principes**
- **Une seule porte d'entrée pour l'API** (`site/api/index.php`). Chaque route de `routes.php` déclare si elle exige la connexion, et c'est oui par défaut. Le `.htaccess` y renvoie toute adresse `/api/…`, y compris `/api/google-callback.php`, déjà déclarée chez Google.
- **Des modules courts, chacun avec un seul rôle**, testables seuls. `trello.php` parle à Trello et ne connaît rien des écrans. `creneaux.php` transforme un événement Google en créneau UTC.
- **Le calcul pur à part**, sans réseau ni base : lecture des créneaux, heure de Paris, détection des actions (comparaison de deux copies), tirage, phrase du jour. Le tirage tourne sur l'iPhone, donc hors ligne aussi.
- **Les appels sortants passent par `http.php`**, remplaçable dans les tests par un faux client qui rejoue des réponses enregistrées.
- **Compatibilité PHP** : le serveur est en 8.4, le Mac en 8.5. Rien de propre à 8.5 (`|>`, `array_first`, `#[\NoDiscard]`…).

## 4. Données

### 4.1 Règles

- Trello et Google sont les seules sources. La base garde des copies datées, ce que l'app produit elle-même, et des secrets chiffrés.
- **Toutes les heures sont en UTC dans la base** (`DATETIME`, session MySQL en `+00:00`), converties à l'heure de Paris à l'affichage. Le passage à l'heure d'hiver du 25/10/2026 est couvert par les tests.
- Mode strict fixé à chaque connexion (`sql_mode` d'OVH non strict) : `STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION`.
- InnoDB, `utf8mb4`, collation `utf8mb4_0900_ai_ci`.
- **L'XP n'est jamais stockée** : elle sera recalculée à partir du journal `actions` (lot 4).

### 4.2 Tables du lot 1 (migration `001_socle.sql`)

| Table | Colonnes | Rôle |
|---|---|---|
| `reglages` | `cle` (clé), `valeur` JSON, `maj_le` | Préférences, version de la base (`version_base`), identifiants des 2 agendas (`agenda_calendriers`) |
| `sessions` | `id`, `empreinte` CHAR(64) unique (SHA-256 du jeton), `cree_le`, `vu_le`, `expire_le`, `appareil` (court libellé du navigateur) | Une ligne par appareil connecté |
| `essais_connexion` | `id`, `ip`, `quand` ; index (`ip`, `quand`) | Échecs de mot de passe. Purge au-delà d'un jour |
| `jetons` | `service` (clé : `google`, `google_acces`, `google_oauth_attente` ; plus tard `siri`), `contenu` chiffré (nonce + texte chiffré), `expire_le`, `maj_le` | Secrets chiffrés avec libsodium |
| `copies` | `source` (clé : `trello`, `agenda`, `agenda:2026-W43`…), `contenu` JSON, `recupere_le`, `empreinte` | Dernière copie de chaque source et son heure |
| `actions` | `id`, `type`, `domaine`, `carte_id`, `element_id` (nul si sans objet), `quand`, `source` (`app` ou `trello`), `precision_heure` (`exacte` ou `approx` ; `PRECISION` est un mot réservé de MySQL), `annulee` (0 ou 1), `donnees` JSON (`urgent`, `echeance`) ; index (`type`, `carte_id`, `element_id`, `quand`) | **Le journal d'actions** (décision 3) |
| `ecritures` | `id_client` CHAR(36) (clé, UUID créé sur l'iPhone), `route`, `recu_le`, `resultat` JSON | Écritures déjà traitées : un renvoi rend le même résultat sans rien refaire. Purge au-delà de 30 jours |

**Types d'actions du lot 1** : `etape_cochee`, `etape_decochee`, `carte_terminee`, `carte_rouverte`, `carte_ajoutee`, `tirage_parti`.

### 4.3 Tables prévues pour les lots suivants

Créées par de nouvelles migrations, sans toucher aux tables existantes :
- **lot 2** : abonnements aux notifications, rappels manuels, envois faits (clé unique par rappel) ;
- **lot 3** : comptes mail (mots de passe d'application chiffrés) ; copies des boîtes dans `copies` ;
- **lot 4** : sessions focus, lignes du journal et humeurs, cartes et badges gagnés avec leur date.

### 4.4 Migrations

- Fichiers numérotés dans `serveur/migrations/`. La version appliquée est dans `reglages.version_base` (0 si la table n'existe pas).
- Appliquées automatiquement à la première requête qui trouve une version en retard, sous le verrou MySQL `GET_LOCK('jr_migrations', 10)`. `/api/sante` les déclenche juste après un déploiement.

### 4.5 Sur l'iPhone (IndexedDB, `js/stockage.js`)

| Magasin | Contenu |
|---|---|
| `donnees` | La dernière réponse de `GET donnees`, avec son heure de réception |
| `agenda` | Les semaines chargées à la demande, par semaine |
| `file` | Les écritures en attente : `id_client`, route, corps, date, nombre d'essais |

## 5. Connexion, session et API

### 5.1 Mot de passe

- Choisi par Colin avec `outils/definir-mot-de-passe.sh` : saisie masquée, deux fois, empreinte bcrypt calculée par PHP (mot de passe passé par l'entrée standard, jamais en argument), écrite dans `secrets/jarvis-jr.env` (`APP_MOT_DE_PASSE_EMPREINTE`). Claude ne le voit jamais.
- Le même script génère la clé de chiffrement si elle manque (`APP_CLE_CHIFFREMENT`, 32 octets en base64).
- Conseil affiché par le script : une phrase longue, puisqu'elle ne se tape qu'une fois tous les 90 jours environ.
- **10 échecs par adresse IP en 15 min**, puis blocage. Textes de `CONTENU.md` § 1 (« Encore 4 essais avant une pause de 15 min. »).

### 5.2 Session

- Jeton aléatoire de 32 octets dans le cookie `__Host-jr` : `Secure`, `HttpOnly`, `SameSite=Lax`, `Path=/`, `Max-Age` de 90 jours. La base n'en garde que l'empreinte SHA-256.
- **`Lax` et non `Strict`** (corrigé le 2026-10-08 en écrivant le plan) : au retour de la connexion Google, le navigateur arrive depuis accounts.google.com. Avec `Strict`, le cookie ne serait pas envoyé et la route `google-callback.php`, qui exige la session, refuserait. `Lax` n'envoie le cookie depuis un autre site que pour une navigation GET, et toutes les routes GET sont en lecture seule.
- Expiration glissante : si `vu_le` a plus de 24 h, `vu_le` et `expire_le` (+90 jours) sont mis à jour et le cookie est renvoyé.
- « Se déconnecter » efface la ligne de l'appareil ; « Déconnecter tous les appareils » efface toutes les lignes.
- En dev sur `http://localhost:8787`, Chromium accepte ce cookie (localhost compte comme sûr). Les essais locaux se font donc avec Chromium et Playwright, pas Safari.

### 5.3 Requêtes forgées depuis un autre site

Toute écriture est un `POST` en `application/json` dont l'en-tête `Origin` doit être exactement l'origine configurée (`https://jr.qyris.fr`, ou `http://localhost:8787` en dev). Sans `Origin`, la requête est refusée. Avec `SameSite=Lax`, un POST venu d'un autre site part sans le cookie : aucun jeton CSRF n'est nécessaire.

### 5.4 Routes du lot 1

Toutes sous `/api/`. Connexion exigée sauf mention contraire. Chaque écriture porte un `id_client` (UUID).

| Méthode et route | Entrée | Sortie, effet |
|---|---|---|
| `GET sante` (publique) | | `version_app`, `version_base`, `base_ok`. Déclenche les migrations |
| `POST connexion` (publique) | `mot_de_passe` | Crée la session. Erreurs : `mot_de_passe_incorrect` (avec essais restants), `trop_d_essais` |
| `POST deconnexion` | | Efface la session de l'appareil |
| `POST deconnexion-partout` | | Efface toutes les sessions |
| `GET donnees` | `force` (0 ou 1) | Cartes, cours et jours spéciaux d'aujourd'hui et des 14 jours suivants, heure de chaque copie (voir 5.5) |
| `GET agenda` | `semaine` (`2026-W43`) | Les jours de cette semaine, même forme que dans `donnees` |
| `POST taches/cocher` | `carte`, `element`, `coche` | Coche ou décoche une étape ; action `etape_cochee` ou `etape_decochee` |
| `POST taches/terminer` | `carte` | Carte en haut de Fait ; action `carte_terminee` ; garde la colonne d'avant |
| `POST taches/annuler` | `id_client_origine` | Annule un « Terminer » : la carte revient dans sa colonne d'avant, l'action passe `annulee = 1`. L'app propose « Annuler » 5 s ; le serveur accepte jusqu'à 10 s, pour la latence |
| `POST taches/deplacer` | `carte`, `colonne` (`a_faire`, `en_cours`, `en_attente`) | Déplace la carte (vers Fait, c'est `terminer`) |
| `POST taches/ajouter` | `domaine`, `titre`, `urgent`, `echeance` (facultative, date ou date et heure) | Nouvelle carte en haut de « À faire » ; action `carte_ajoutee`. Une date sans heure devient 18 h 00, heure de Paris, comme dans `trello.py` |
| `POST tirage/parti` | `carte`, `element` | Action `tirage_parti` (pour le badge « Machine à sous » du lot 4) |
| `GET google/connexion` | | Redirige vers Google (voir 7.1) |
| `GET google-callback.php` | `code`, `state` | Termine la connexion Google, puis renvoie vers `#/moi` |

**Forme des réponses** : `{"ok": true, …}` ou `{"ok": false, "erreur": {"code": "…", "message": "…"}}` avec le code HTTP adapté (400, 401, 403, 404, 409, 429, 500, 502). Les messages viennent de `CONTENU.md` § 11.

### 5.5 Contenu de `GET donnees`

```json
{
  "ok": true,
  "maintenant": "2026-10-08T15:40:00+02:00",
  "trello": {
    "recupere_le": "…", "erreur": null,
    "cartes": [{
      "id": "…", "domaine": "etudes", "colonne": "en_cours",
      "titre": "…", "description": "…", "url": "https://trello.com/c/…",
      "echeance": "2026-10-21T19:15:00+02:00", "echeance_faite": false,
      "urgent": false, "etiquettes": ["MT23"],
      "checklists": [{"id": "…", "nom": "…", "elements": [{"id": "…", "nom": "…", "coche": false}]}],
      "derniere_activite": "…"
    }]
  },
  "agenda": {
    "recupere_le": "…", "erreur": null, "google_connecte": true,
    "jours": {
      "2026-10-14": {
        "bandeaux": [{"type": "devient", "jour": "jeudi", "texte": "Aujourd'hui, l'UTC suit l'emploi du temps du jeudi."}],
        "evenements": [{"debut": "…", "fin": "…", "journee": false, "source": "utc", "uv": "NF93", "type": "TD", "salle": "FA400", "titre": "NF93 - TD 1"}]
      }
    }
  }
}
```

- Les cartes de Fait ne sont renvoyées que si leur dernière activité date de moins de 14 jours (lien « Voir les cartes faites »).
- Comme dans `trello.py`, une échéance à 18 h 00 pile s'affiche sans heure (« Échéance mer. 21/10 ») : c'est la convention d'une date seule.
- Le champ `erreur` d'une source vaut par exemple `trello_indisponible` ou `google_deconnecte`. La copie précédente est alors renvoyée avec son heure.

### 5.6 En-têtes et `.htaccess`

- Redirection HTTP vers HTTPS, puis `Strict-Transport-Security: max-age=31536000`.
- `Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; font-src 'self'; manifest-src 'self'; worker-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'`.
- `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow`, `Permissions-Policy` restrictive. `robots.txt` : `Disallow: /`.
- `AddType application/manifest+json .webmanifest` (le test serveur l'a trouvé servi sans type).
- `Cache-Control: no-cache` sur `index.html`, `sw.js` et le manifeste (OVH ajoute `max-age=900` aux fichiers statiques). `no-store` sur toute l'API.
- `Options -Indexes`. Réécriture de `^api/` vers `api/index.php`.

## 6. Trello

### 6.1 Accès et liste blanche

- Clé `TRELLO_KEY` (reprise de `secrets/trello.env`) et jeton dédié `TRELLO_TOKEN` (`secrets/jarvis-jr.env`), envoyés dans l'en-tête `Authorization`, jamais dans l'adresse.
- `serveur/trello-ids.json` (copié depuis `.claude/scripts/trello-ids.json` au déploiement, une seule source) donne les 4 tableaux, leurs colonnes et leurs étiquettes. Correspondance tableau vers domaine et colonne vers `a_faire`, `en_cours`, `en_attente`, `fait`.
- **Avant toute écriture**, le serveur vérifie que la carte appartient à l'un des 4 tableaux, d'après sa copie ou, si la carte y manque, d'après Trello. Sinon : refus `403`. Courses, Repas étudiant et tout autre tableau sont inaccessibles par construction.

### 6.2 Lecture

- Un seul appel `GET /1/batch` avec les 4 URL de tableau : colonnes ouvertes, cartes ouvertes (y compris celles de Fait), étiquettes, checklists et leurs éléments. Délai de 8 s.
- Copie rafraîchie si elle a plus de **60 s** (10 s si `force=1`), sous un verrou MySQL par source pour éviter deux rafraîchissements simultanés.

### 6.3 Écritures

| Action | Appel Trello |
|---|---|
| Cocher, décocher | `PUT /cards/{carte}/checkItem/{element}` avec `state` |
| Terminer | `PUT /cards/{carte}` avec `idList` = Fait, `pos=top` |
| Annuler | `PUT /cards/{carte}` avec la colonne et la position d'avant, gardées dans `ecritures.resultat` |
| Déplacer | `PUT /cards/{carte}` avec `idList` |
| Ajouter | `POST /cards` dans « À faire », `pos=top`, étiquette Urgent si demandée, `due` si échéance |

Après chaque écriture réussie, la copie du serveur est corrigée aussitôt, et l'action est notée (`source = app`, `precision_heure = exacte`).

### 6.4 Détection des actions faites ailleurs

Fonction pure : deux copies en entrée, une liste d'actions en sortie.

- Carte entrée dans Fait : `carte_terminee`. Carte sortie de Fait : `carte_rouverte`. Élément passé à coché : `etape_cochee` ; à décoché : `etape_decochee`.
- Heure : la `dateLastActivity` de la carte, `precision_heure = approx`, `source = trello`.
- **Pas de doublon** : une action détectée est ignorée si la même action (`type`, `carte_id`, `element_id`) a été notée par l'app depuis la copie précédente.
- **Point de départ** : la toute première copie ne produit aucune action. Ce qui est dans Fait avant le lot 1 ne compte pas.

## 7. Google Agenda

### 7.1 Connexion

- Depuis Moi, une seule fois. Portée `calendar.readonly`, `access_type=offline`, `prompt=consent`.
- Protection : un `state` aléatoire et un vérificateur PKCE (S256), gardés chiffrés dans `jetons.google_oauth_attente`, valables 10 min.
- Au retour : vérification du `state`, échange du code (avec le secret client et le vérificateur). Le jeton longue durée est chiffré dans `jetons.google`, le jeton d'accès (1 h) dans `jetons.google_acces`.
- Les agendas sont retrouvés par `calendarList` : le principal et « UTC · Emploi du temps A26 » (par son nom). Leurs identifiants vont dans `reglages.agenda_calendriers`.
- URI de retour déclarées : `https://jr.qyris.fr/api/google-callback.php` et `http://localhost:8787/api/google-callback.php`.
- Si Google répond `invalid_grant` (accès révoqué), `erreur = google_deconnecte` et le message de `CONTENU.md` § 11.

### 7.2 Lecture

- `events.list` sur les 2 agendas, `singleEvents=true`, `orderBy=startTime`, `timeZone=Europe/Paris`, champs limités au nécessaire.
- Copie `agenda` : aujourd'hui et les 14 jours suivants, rafraîchie si elle a plus de **10 min**. Une autre semaine : copie `agenda:AAAA-Www`, chargée à la demande, même règle.

### 7.3 Lecture des créneaux UTC (`creneaux.php`, fonction pure)

- Titre `^([A-Z]{2}\d{2})\s*-\s*(Cours|TD|TP)(?:\s+\d+)?$` : UV, type, salle (le lieu de l'événement). Exemple : `TN01 - TD 2` en FA510 donne « TN01 · TD · FA510 ».
- Titre au format inattendu : affiché tel quel, sans erreur.
- Journées entières (comparaison sans casse ni accents) :

| Titre dans l'agenda | Bandeau |
|---|---|
| Vacances universitaires | « Vacances universitaires. » |
| Jour d'examen | « Jour d'examen. » |
| Ferié | « Jour férié à l'UTC : pas de cours. » |
| Devient un samedi, mercredi, jeudi… | « Aujourd'hui, l'UTC suit l'emploi du temps du jeudi. » |

- Événements de l'agenda principal : `source = perso`, affichés à part des cours. Un événement dont le titre commence par « Médian » est reconnu comme médian (phrase de Junior, rappels du lot 2). Les 3 médians seront créés dans l'agenda principal avant le lot 2, avec l'accord de Colin.

## 8. L'app sur l'iPhone (lot 1)

### 8.1 Écrans

Le dessin vient de la maquette v28 (https://claude.ai/artifact/ACPbctYmzhyqJrQk4ou4Nj) et du contrat `.impeccable/surfaces/site-index-html.md`, recodés en natif. Textes : `CONTENU.md`.

- **Connexion** : § 1 de `CONTENU.md`.
- **Aujourd'hui** :
  - salutation selon l'heure, phrase du jour de Junior, bandeau des jours spéciaux ;
  - écran de conduite : EN SCÈNE (cours en cours), STANDBY (suivant), heure et salle en ambre, compte à rebours ; le soir, « Plus de cours aujourd'hui. Demain : … » ;
  - les 4 tranches : compteur de cartes ouvertes (À faire et En cours) par domaine, point rouge si l'une est en retard. Le curseur de niveau reste au repos, sans chiffre, jusqu'au lot 4 ;
  - sous le pli : conduite du jour (cours, JOUÉ une fois passés), puis À faire en 3 groupes (En retard : échéance passée ; Aujourd'hui : échéance plus tard dans la journée ; Urgent : étiquette Urgent, hors des deux premiers groupes) ;
  - GO, seul élément allumé, éteint pendant un cours.
- **Agenda** : bascule Jour et Semaine (du lundi au dimanche), bandeaux, JOUÉ, UV sur scotch, événements perso distincts, « Rien de prévu. ».
- **Tâches** : filtres par domaine, groupes En cours, À faire, En attente, lien « Voir les cartes faites ». Une carte : titre sur 2 lignes, échéance ou retard, avancement « 2/4 ».
- **Carte ouverte** : titre, échéance, étiquettes, description (texte brut, liens cliquables), checklists avec tampons FAIT. Actions : Cocher, Terminer (« Carte terminée. » avec « Annuler » pendant 5 s), Déplacer, Ouvrir dans Trello.
- **Capture (+)** : feuille du bas, tâche seulement (le choix « Rappel » arrive au lot 2). Titre, domaine sur scotch, Urgent, échéance. Confirmation de `CONTENU.md` § 4.
- **Moi** : connexion Google (état, bouton « Connecter Google Agenda »), « Se déconnecter », « Déconnecter tous les appareils », version de l'app. Grade, XP, album et badges au lot 4.
- **Mails** : l'onglet et le bloc d'Aujourd'hui n'apparaissent qu'au lot 3. Au lot 1, la barre compte 4 onglets et le +.

### 8.2 Phrase du jour (`phrases.js`, fonction pure)

- Tirée une fois par jour (graine = la date), parmi les phrases générales et les phrases de situation vérifiables au lot 1 : lundi, journée chargée (4 créneaux ou plus), pas de cours avant 13 h, rien en retard, tâches en retard, médian dans les 7 jours.
- Une phrase de situation applicable a 3 fois plus de chances qu'une phrase générale.
- Celles qui dépendent des lots suivants (promotion, joker, 0 mail) sont exclues jusque-là.

### 8.3 Le tirage (`tirage.js`, fonction pure)

- **Temps libre** : du moment présent jusqu'au début du prochain cours du jour, moins 10 min de trajet. Sans autre cours : jusqu'à 23 h. Après 23 h : aucun temps libre affiché, le résultat commence par « Il est tard, mais puisque tu insistes : ».
- **Le temps libre ne filtre pas les candidats** : aucune carte n'a d'estimation de durée. Il est affiché, et la plus petite unité proposée est l'étape.
- **Cas particuliers** :
  - pendant un cours, GO est éteint ;
  - cours dans moins de 15 min : « Ton cours commence dans 8 min. Le meilleur choix : y aller. » ;
  - to-do vide : la réplique « Plus rien dans ta to-do… ».
- **Candidats** : pour chaque carte En cours ou À faire des 4 tableaux, la première étape non cochée (checklists et éléments dans leur ordre Trello). Une carte sans étape restante est proposée en entier (« Terminer : titre »). En attente est exclu.
- **Score** (réglable, à recaler à l'usage) :

| Critère | Points |
|---|---|
| En retard | 100, +5 par jour de retard (30 jours au plus) |
| Échéance aujourd'hui | 80 |
| Échéance demain | 50 |
| Échéance dans 3 jours au plus | 30 |
| Échéance dans 7 jours au plus | 15 |
| Étiquette Urgent | 40 |
| Colonne En cours | 25 |

- **Choix** : au hasard, pondéré par (score + 10), parmi les 5 meilleurs candidats pas encore proposés dans ce tirage.
- **Relances** : 3, avec les répliques de `CONTENU.md` § 6. Après la 3e, seul « C'est parti » reste.
- **Texte du résultat** : « {temps libre} avant {UV}. {début du titre de la carte, avant « : »} : {étape} ». Exemple : « 45 min avant TN01. Formation 2 : tester les exercices sur les comptes gratuits. »
- **Animation** : STANDBY, puis les chiffres de l'afficheur roulent colonne par colonne, puis la suggestion tombe. En mouvement réduit, le résultat s'affiche directement.
- **« C'est parti »** (lot 1) : envoie `tirage/parti`, ouvre la carte avec l'étape en évidence.

### 8.4 Hors ligne

- **Affichage** : à l'ouverture, l'app affiche la copie locale, puis demande `GET donnees`. Sans réseau : « Hors ligne · données d'il y a 12 min ».
- **Écriture locale** : chaque écriture s'applique tout de suite à l'écran (marquée « en attente »), puis part dans la file `file`.
- **Renvoi de la file** : envoi dans l'ordre, au retour du réseau (`online`) et à chaque ouverture. La file reste visible avec « 1 note attend le réseau ».
- **Réponses du serveur** :
  - succès, ou réponse déjà connue grâce à `id_client` : l'écriture est retirée de la file ;
  - erreur 4xx (carte supprimée entre-temps, par exemple) : l'écriture est retirée de la file avec un message ;
  - réseau ou 5xx : l'écriture reste, nouvel essai plus tard ;
  - 401 : l'écriture reste et l'écran de connexion s'ouvre.
- **Service worker** :
  - il garde l'app sous un cache nommé par version (`VERSION` dans `sw.js`), avec la liste explicite des fichiers du lot 1 ;
  - un test vérifie que chaque fichier de `site/` utilisé par le lot 1 y figure ;
  - les 70 cartes, qui pèsent 8,9 Mo, ne sont pas en cache avant le lot 4 ;
  - une nouvelle version s'installe à l'ouverture suivante ;
  - l'API n'est jamais mise en cache par le service worker.

### 8.5 Accessibilité

Cibles de 44 px au moins, contraste AA en mode sombre, mouvement réduit respecté (tirage, tampons), libellés VoiceOver (le + s'appelle « Noter quelque chose »), taille de texte de l'iPhone respectée. Polices Archivo, Martian Mono et Permanent Marker hébergées dans `site/fonts/` (licence OFL).

## 9. Secrets et configuration

| Fichier | Contenu | Qui l'écrit |
|---|---|---|
| `secrets/jarvis-jr.env` | `DB_*`, `TRELLO_TOKEN`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (déjà là) ; `APP_MOT_DE_PASSE_EMPREINTE`, `APP_CLE_CHIFFREMENT` (nouveaux) | Colin, et `definir-mot-de-passe.sh` |
| `secrets/trello.env` | `TRELLO_KEY` (repris, pas copié) | Existant |
| `secrets/jarvis-jr-local.env` | Accès à la base locale (`DB_*`), aléatoires | Script d'installation de MySQL local |
| `serveur/config.php` | Tableau PHP : base, Trello, Google, clé de chiffrement, empreinte, origine, mode (`production` ou `dev`), écritures Trello (`reelles` ou `simulees`) | `outils/generer-config.php`, droits 600, hors git |

- `generer-config.php` lit les fichiers `.env` lui-même (sans `source`, l'empreinte bcrypt contient des `$`) et écrit `config.php`. Il sert au déploiement et au dev local.
- **En dev, les écritures Trello sont simulées par défaut** : elles sont journalisées et appliquées à la copie, sans être envoyées. Les lectures restent réelles. On ne touche donc pas à la vraie to-do en cliquant pendant le développement. Le mode réel en local n'est activé qu'avec l'accord de Colin.
- Toute commande qui touche à ces fichiers passe par le filtre `redact-secrets`.

## 10. Développement local et déploiement

### 10.1 Dev local

- **MySQL 8.4** : `brew install mysql@8.4`, avec l'accord de Colin, au premier jour du lot 1. Lancé par `brew services run mysql@8.4` (pas au démarrage du Mac), arrêté en fin de session.
- **Deux bases** : `jarvisjr_dev` et `jarvisjr_test` (vidée à chaque lancement des tests), avec un utilisateur local dédié.
- **Aperçu** : `php -S localhost:8787 outils/apercu.php`. Le routeur imite le `.htaccess` (réécriture `/api/`, en-têtes) et désactive le cache.

### 10.2 Déploiement (`outils/deploy-ovh.sh`, lancé par Colin)

1. Lance `php tests/run.php` et refuse d'envoyer si un test échoue.
2. Génère `config.php` (mode `production`, écritures Trello `reelles`) dans un dossier temporaire, effacé à la sortie quoi qu'il arrive.
3. Simulation : affiche la liste des fichiers ajoutés, modifiés et retirés, puis demande « Envoyer ? (o/N) ».
4. Envoi par `outils/ovh-sftp.sh` (SFTP, une seule tentative, mot de passe hors de la ligne de commande) :
   - `site/` vers `jr.qyris.fr/www/` avec suppression des fichiers retirés, sauf `.ovhconfig` ;
   - `serveur/` vers `jr.qyris.fr/serveur/`, même règle, sauf `config.php` et `data/` ;
   - `config.php` posé à part, puis `chmod 600`.
5. Appelle `https://jr.qyris.fr/api/sante` et affiche la version et l'état de la base.

Toute la sortie passe par le filtre des secrets. Si Colin préfère un jour laisser Claude déployer, une règle d'autorisation Bash suffira. Ce n'est pas prévu.

## 11. Erreurs et journal

- **Trello ou Google injoignable** (délai de 8 s) : la dernière copie est renvoyée avec son heure et le code d'erreur. L'app affiche le message de Junior (`CONTENU.md` § 11).
- **Exception PHP** : `500`, message sobre, sans pique.
- **Journal** : `serveur/data/erreurs.log` (OVH ne journalise pas les erreurs PHP). Chaque ligne donne l'heure, la route, le type d'erreur et un message technique. Jamais de titre de carte, de jeton, de mot de passe ni de contenu personnel. Vidé au-delà de 1 Mo.

## 12. Tests et fin du lot 1

### 12.1 Tests PHP (`tests/run.php`, sans Composer)

Écrits avant le code (superpowers:test-driven-development) pour la logique sensible :
- lecture des créneaux et des journées entières ;
- conversion UTC et heure de Paris, y compris le 25/10/2026 ;
- détection des actions entre deux copies : chaque type, pas de doublon avec les actions de l'app, première copie sans action ;
- liste blanche Trello : refus d'une carte hors des 4 tableaux ;
- session : création, expiration glissante, déconnexion partout ;
- blocage au 11e essai de mot de passe ;
- `ecritures` : un renvoi rend le même résultat sans rien refaire ;
- chiffrement : aller-retour, refus d'un texte modifié ;
- migrations sur une base vide ;
- table des routes : toute route sans mention « publique » refuse sans session.

Trello et Google sont remplacés par le faux client HTTP et des réponses aux contenus inventés (`tests/fixtures/`).

### 12.2 Tests JS

Le tirage, la phrase du jour et `temps.js` sont des modules purs, testés dans Playwright (exécution de modules dans la page) pour éviter d'ajouter un outil.

### 12.3 Playwright (sur l'aperçu local, Trello et Google simulés)

- Chaque écran à 375, 390 et 768 px, sans débordement.
- Hors ligne : affichage de la copie, écriture mise en file, renvoi au retour du réseau sans doublon.
- Tirage : 3 relances, puis « C'est parti ».
- Mouvement réduit.
- Console sans erreur.

### 12.4 Avant chaque mise en ligne

Revue de code et revue de sécurité (`code-review`, `security-review`).

### 12.5 Le lot 1 est fini quand

1. L'app est installée sur l'iPhone de Colin et connectée.
2. Aujourd'hui, Agenda et Tâches montrent ses vraies données, Google compris.
3. Une étape cochée ou une carte ajoutée dans l'app arrive dans Trello, et Jarvis la voit au `/prime`.
4. GO propose une étape réelle.
5. L'app s'ouvre et se lit sans réseau, et une écriture faite hors ligne part au retour du réseau.
6. Côté sécurité :
   - l'API refuse sans session ;
   - `serveur/` est inaccessible depuis le web ;
   - le 11e essai est bloqué ;
   - les en-têtes du 5.6 sont actifs en ligne ;
   - aucun secret n'apparaît dans git ni dans les sorties.

## 13. Textes nouveaux, à valider par Colin avec le plan du lot 1

Ils ne sont pas dans `CONTENU.md` et viennent des décisions de cette session :
- tirage sans autre cours dans la journée : « Libre jusqu'à 23 h. Formation 2 : tester les exercices sur les comptes gratuits. » ;
- tirage après 23 h : « Il est tard, mais puisque tu insistes : … » ;
- tirage, carte sans étape restante : « Terminer : {titre de la carte} » ;
- « C'est parti » au lot 1 : « C'est parti. Coche l'étape quand c'est fait, je note. » ;
- « Terminer » au lot 1 : « Carte terminée. » (le « +30 XP Études » de `CONTENU.md` viendra au lot 4) ;
- Moi, Google : « Connecter Google Agenda », « Agenda connecté », « Reconnecter ».

## 14. Hors du lot 1

| Lot | Contenu | Section de spec à écrire avant son plan |
|---|---|---|
| 2 | Notifications web (VAPID, aes128gcm vérifié contre la RFC 8291), planificateur appelé par cron-job.org, rappels automatiques et manuels, brief du matin, raccourci Siri, choix « Rappel » de la capture | Oui |
| 3 | 5 boîtes mail en lecture seule (client IMAP maison, section UTC), onglet Mails, bloc Mails d'Aujourd'hui | Oui |
| 4 | XP, niveaux, séries, badges calculés depuis `actions`, cartes et album, journal et Wrapped, combat contre le boss avec « J'ai fini la tâche » (« C'est parti » lance alors le boss) | Oui |

**Préparatifs de Colin restants** : compte cron-job.org (avant le lot 2), redirection UTC dans Tuttifruti et mots de passe d'application Gmail et iCloud (avant le lot 3), accord pour créer les 3 médians dans l'agenda principal (avant le lot 2).
