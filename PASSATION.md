# Passation · Jarvis Jr. · 2026-10-25 12:35

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Lot 3 (Mails) en ligne et en recette. Chantier en cours : le **lot 3.2**, lire un mail en entier depuis l'app. Sa spec est validée, il reste à écrire le plan de missions puis à l'exécuter.

## Où on en est
- **Fait et vérifié** :
  - Les commits du 25/10 jusqu'à `b231629` sont en ligne : le `mails.js` servi est identique au fichier local, et `/api/sante` donne `version_base` 6. colin@qyris.fr est branché d'après Colin. Les 4 boîtes sont connectées.
  - Spec du lot 3.2 écrite (`SPEC.md` § 17), relue par un sous-agent « builder » (8 points, tous intégrés), **validée par Colin le 25/10**, textes nouveaux du § 17.11 compris. Commit `210b51a`. Arbre git propre.
- **Fait, pas encore vérifié** : la recette `RECETTE-LOT3.md` § 5 et § 6 n'est pas faite.
- **En cours chez un sous-agent** : rien.

## Décisions prises (détail dans `SPEC.md` § 17.2 à 17.8, à ne pas rediscuter)
- Toucher un mail ouvre son contenu complet. `POST /api/mails/ouvrir` le marque lu : un seul `UID STORE <uid> +FLAGS.SILENT (\Seen)` après `SELECT`, jamais `CLOSE` ni `EXPUNGE`. La liste blanche IMAP a deux usages : la lecture et l'ouverture.
- Le corps est servi par `GET /api/mails/corps`, une page HTML dans un `<iframe sandbox>` sans `allow-scripts`. Il a trois protections : le nettoyage `DOMDocument`, sa propre CSP et le sandbox. Les images `cid:` sont mises en `data:` dans la même session IMAP. Les liens sont interceptés par l'écran.
- Les pièces jointes passent par `GET /api/mails/piece`. Elles sont lues par morceaux de 1 Mo, 20 Mo au plus, et leur type vient d'une liste blanche (HTML et SVG partent en `octet-stream`).
- « Répondre » ouvre `googlegmail://` pour Gmail, `mailto:` pour iCloud et QYRIS, et Roundcube en rédaction pour l'UTC, qui n'est pas dans l'app Mail de l'iPhone. Pas de SMTP.
- Rien du contenu des mails n'est gardé, ni sur le serveur ni sur l'iPhone. Les sous-agents ne commitent pas : Claude relit et commite. C'est Colin qui lance tout envoi vers OVH.

## Prochaine étape
1. Écrire le plan de missions `plans/2026-10-25-lot3-2-contenu.md` avec superpowers:writing-plans, en mode chef d'orchestre (`guides/CHEF-D-ORCHESTRE.md`), sur le modèle de `plans/2026-10-23-lot3-1-mails.md`. Montrer le découpage à Colin en quelques lignes.
2. Exécuter les missions par sous-agents. Le cœur sécurité passe en premier : usages IMAP, nettoyage HTML, en-têtes des réponses brutes et exception `.htaccess`. Puis vérification indépendante et commit.
3. Mise en ligne par Colin, puis `curl -sI` sur une route normale et sur `/api/mails/corps` : une seule CSP chacune, la bonne.
Effort conseillé pour la conversation suivante : **haut** (écriture IMAP et HTML de mails).

## Fichiers clés
- `SPEC.md` § 17 : la spec du lot 3.2, base unique du plan
- `plans/2026-10-23-lot3-1-mails.md` : modèle de plan du lot 3
- `serveur/lib/imap.php` (`imap_segments_commande`, `imap_session`), `serveur/lib/imap_decodage.php` (`imap_parties_texte`), `serveur/lib/reponse.php` (`entetes_securite`), `site/.htaccess` (l. 27 à 35), `site/js/ecrans/mails.js`

## Pièges et points ouverts
- **Essais sur l'iPhone impossibles du 26 au 30/10** (Colin en Espagne). Deux mécanismes restent à trancher sur l'appareil : l'ouverture d'une pièce jointe depuis l'app installée, et l'ouverture d'un lien depuis le cadre (§ 17.6 et § 17.7). Le développement peut avancer avant.
- `.htaccess` pose la CSP de l'app avec `Header always set` sur tout, `/api` compris. Il faut une condition sur `%{THE_REQUEST}`, car l'URI est réécrite vers `api/index.php`.
- Garde-fou : toute commande citant `.env`, `config.php`, `secrets/`, un `grep -r` ou un script de mise en ligne passe entièrement par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`. Écrire les scripts Python longs dans le scratchpad.
- `site/sw.js` : `VERSION` est à recalculer après tout changement dans `site/` (`php tests/run.php "service worker"`).
- `outils/deploy-ovh.sh` affiche « En ligne. » sans comparer `version_base` aux migrations : à corriger. `JR_VERSION` passe à 0.3.2 avec ce lot.
- Lot 2 encore ouvert : rappel « Dans 1 h » à recréer et contrôler, brief du 25/10 (heure d'hiver) à vérifier. Adresse du webmail OVH (`LIENS.qyris`) à confirmer par Colin.
