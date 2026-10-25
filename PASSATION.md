# Passation · Jarvis Jr. · 2026-10-25 (après-midi)

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Chantier : **lot 3.2**, lire un mail en entier depuis l'app (contenu, marqué lu à l'ouverture, pièces jointes, Répondre). Exécution du plan `plans/2026-10-25-lot3-2-contenu.md` en mode chef d'orchestre.

## Où on en est
- **Fait et vérifié** (branche `lot3-2-contenu`, partie de `main` `78cc357`, arbre propre) :
  - Plan écrit et commité (`6d9393d`), découpage montré à Colin, qui a dit « on lance ».
  - M1 (`ac38a13`) usages IMAP `lecture`/`ouverture`, `imap_ouvrir_mail` ; M2 (`39eba19`) `nettoyage_html.php` ; M3 (`cda5bb5`) `reponse_brute`, `reponse_flux`/`flux_executer`, clé de route `'erreur'`, `mail_entetes.php`, bloc `<If>` dans `.htaccess` (vérifié par un Apache 2.4 local) ; M4 (`d34aea9`) `mail_contenu.php`, `imap_lire_partie`/`imap_lire_corps`/`imap_lire_piece` ; M5 (`67465d5`) `mail_lecture.php`, routes `POST mails/ouvrir`, `GET mails/corps`, `GET mails/piece`.
  - Chaque mission relue par Claude (liste blanche, nettoyage des URL et CSS, condition `.htaccess`, lecture par morceaux, mise à jour de la copie). Tests : **PHP 468, JS 48, écran 100**, tous verts (PHP 349 avant le lot).
- **Fait, pas encore vérifié** : rien côté serveur hors de la vérification indépendante (M8). Recette du lot 3 (`RECETTE-LOT3.md` § 5 et § 6) toujours pas faite.
- **En cours chez un sous-agent** : rien.

## Décisions prises (plan, § Décisions 1 à 13 : à ne pas rediscuter)
- Toutes celles de `SPEC.md` § 17 et du plan. Ajouts pendant l'exécution, acceptés par Claude : nettoyage plus strict que le plan (`image-set`, échappements CSS, `<` neutralisé, `bmp` en `data:`) ; `<meta>` du mail retirés avant `DOMDocument` ; codes du flux `absent`/`hors_copie`/`partie_inconnue` → 404, `trop_lourde` → 413, le reste → 502 ; pièce qui dépasse 20 Mo en cours de lecture : réponse coupée ; pas de `Content-Length` sur les pièces ; `mail_ouvrir` convertit les `ErreurSource` en `ErreurApi` (404 `absent`).
- Image `Content-ID` placée dans un `multipart/mixed` (Apple Mail) : listée comme pièce, pas affichée dans le texte. Accepté pour l'instant.

## Prochaine étape
1. Lancer **M6, écran d'un mail** (sous-agent `developpeur`, Sonnet, effort moyen), d'après la fiche M6 du plan. Lui donner la forme des réponses ci-dessous (constatée par M5) :
   - `POST mails/ouvrir` {compte, uid entier} → `{ok, objet, de_nom, de_adresse, a:[{nom,adresse}], cc, date (ISO UTC Z), pour_contact, pieces:[{partie,nom,type,taille,trop_lourde}], repondre_url, repondre_repli (null sauf Gmail), marque_lu}` ; erreurs JSON 404 `hors_copie` | `absent`, 400 `non_connecte` | `compte_invalide` | `requete_invalide` | `refus` | `injoignable` | `delai`.
   - `GET mails/corps?compte=&uid=[&images=1]` → page HTML, `<body data-images-bloquees="1">` si des images sont bloquées ; erreurs : page « Ce mail ne s'ouvre pas pour l'instant. » (404, 400, 401, 502).
   - `GET mails/piece?compte=&uid=&partie=` → fichier en flux ; erreurs texte 404, 413, 400, 401, 502.
2. Puis M7 (préparer la mise en ligne : `executant`, Haiku), M8 (vérification : `verificateur`, Haiku), relecture ciblée, SPEC § 17 et README à jour, fusion dans `main`. Commit par Claude après chaque mission.
3. Mise en ligne par Colin, vérification des en-têtes par `GET` (pas `curl -sI`), recette sur l'iPhone **à partir du 31/10**.
Effort conseillé pour la conversation suivante : **moyen** (écran ; le cœur sécurité est fait).

## Fichiers clés
- `plans/2026-10-25-lot3-2-contenu.md` : plan, fiches M6 à M8
- `SPEC.md` § 17.4 (écran), § 17.11 (textes)
- `site/js/ecrans/mails.js`, `site/js/lib/mails.js`, `site/js/lib/textes.js`, `tests/e2e/serveur.mjs`, `tests/e2e/fixtures.mjs`, `tests/e2e/test_mails.mjs`

## Pièges et points ouverts
- **Essais sur l'iPhone impossibles du 26 au 30/10** (Colin en Espagne) : ouverture d'une pièce jointe depuis l'app installée (décision 11 : lien `target="_blank"` pour l'instant) et lien du cadre. Une mission M9 est prévue si ça échoue.
- Si OVH refuse `<If>` dans `.htaccess`, **tout le site** rend 500 après la mise en ligne : M7 met la commande de retour arrière dans `RECETTE-LOT3-2.md`.
- `HEAD` n'est pas exclu par la condition `.htaccess` : vérifier les en-têtes avec `curl -s -D - -o /dev/null`.
- Le filtre de `php tests/run.php <filtre>` est sensible à la casse ; la suite PHP complète prend environ 65 s.
- Une fonction définie dans `serveur/routes/*.php` provoque « Cannot redeclare » (les routes sont rechargées) : les mettre dans `serveur/lib/`.
- Garde-fou : toute commande citant `.env`, `config.php`, `secrets/`, un `grep -r` ou un script de mise en ligne passe entièrement par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`.
- Restent hors du lot 3.2 : rappel « Dans 1 h » à recréer, brief du 25/10 (heure d'hiver), adresse du webmail OVH à confirmer, `JR_VERSION` à passer à 0.3.2 (M7).
