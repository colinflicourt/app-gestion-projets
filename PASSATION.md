# Passation · Jarvis Jr. · 2026-10-25 (fin d'après-midi)

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Chantier : **lot 3.2**, lire un mail en entier depuis l'app (contenu, marqué lu à l'ouverture, pièces jointes, Répondre). Exécution du plan `plans/2026-10-25-lot3-2-contenu.md` en mode chef d'orchestre.

## Où on en est
- **Fait et vérifié** (branche `lot3-2-contenu`, partie de `main` `78cc357`, arbre propre) :
  - M1 à M5 (serveur, `ac38a13` à `67465d5`), M6 écran d'un mail (`aa21717`), M7 préparation de la mise en ligne (`f7c5930`), chacune relue par Claude.
  - M7 : `JR_VERSION = '0.3.2'` ; `site/sw.js` `VERSION` déjà juste (`jr-6c975fff1e`, test « service worker » vert) ; `outils/deploy-ovh.sh` compare `version_base` de `/api/sante` au plus grand numéro de `serveur/migrations/` (6), « En ligne. » seulement si égaux, sinon « Attention : … » et `exit 1` ; test ajouté dans `tests/unitaires/test_deploiement.php` ; `RECETTE-LOT3-2.md` écrit (CSP attendues relues contre `site/.htaccess` et `entetes_corps_mail()`, 3 phrases corrigées par Claude).
  - Tests : **PHP 469, JS 51, écran 120**, tous verts (PHP 349 avant le lot). `bash -n` du script OK.
- **Fait, pas encore vérifié** : le contrôle de `version_base` n'a tourné qu'en simulation (pas d'accès réseau). Vérification indépendante (M8) pas faite. Recette du lot 3 (`RECETTE-LOT3.md` § 5 et § 6) toujours pas faite.
- **En cours chez un sous-agent** : rien.

## Décisions prises
- Plan § Décisions 1 à 13 et SPEC § 17 : à ne pas rediscuter. Ajouts acceptés pendant l'exécution : voir l'historique git et la passation précédente (nettoyage plus strict, codes d'erreur des flux, `Content-ID` dans `multipart/mixed` listée comme pièce).
- Retour arrière si OVH refuse `<If>` : remettre `git show 78cc357:site/.htaccess` par `outils/ovh-sftp.sh "put … -o jr.qyris.fr/www/.htaccess"`, et ne pas relancer `deploy-ovh.sh` avant correction (le `mirror` renverrait le fichier fautif).

## Prochaine étape
1. Lancer **M8, vérification indépendante** (sous-agent `verificateur`, Haiku, effort moyen) d'après la fiche M8 du plan : 3 suites (avant : PHP 349, JS et écran relevés par M1 ; maintenant 469 / 51 / 120), recherches dans `serveur/` hors tests (`CLOSE`, `EXPUNGE`, `\Deleted`, `-FLAGS`, `SELECT "` et `STORE` hors liste blanche et `imap_ouvrir_mail`, `BODY[` sans `.PEEK`), aucun contenu de mail écrit dans `serveur/data/`, `stockage.js`, `sw.js`, console sans erreur sur Mails et l'écran d'un mail, captures à 390 px, `git status`, aucun tiret long.
2. Relecture ciblée par Claude, `SPEC.md` § 17 et `README.md` à jour, fusion de `lot3-2-contenu` dans `main`.
3. Mise en ligne par Colin (`RECETTE-LOT3-2.md` § 1), puis vérification des en-têtes par GET (§ 2) : Claude lit la sortie. Recette sur l'iPhone **à partir du 31/10**.
Effort conseillé pour la conversation suivante : **moyen**.

## Fichiers clés
- `plans/2026-10-25-lot3-2-contenu.md` : fiche M8, partie B
- `RECETTE-LOT3-2.md` : mise en ligne, en-têtes, retour arrière, cases
- `SPEC.md` § 17 (à mettre à jour après M8), `README.md`

## Pièges et points ouverts
- **Essais sur l'iPhone impossibles du 26 au 30/10** (Colin en Espagne). Pièce jointe et lien du cadre tranchent la décision 11 ; mission M9 si ça échoue.
- `HEAD` n'est pas prévu par la condition `.htaccess` : vérifier les en-têtes avec `curl -s -D - -o /dev/null`, jamais `curl -sI`.
- Faux serveur e2e : `tests/e2e/serveur.mjs:97` annonce `version_app '0.2.0'` et `tests/e2e/test_moi.mjs:15` l'attend. Sans conséquence, à aligner sur 0.3.2 si on y touche.
- Filtre de `php tests/run.php <filtre>` sensible à la casse ; suite PHP complète environ 65 s. Fonction définie dans `serveur/routes/*.php` → « Cannot redeclare » : la mettre dans `serveur/lib/`.
- Garde-fou : toute commande citant `.env`, `config.php`, `secrets/`, `deploy`, `sftp`, un `grep -r` passe entièrement par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`. En zsh, ne pas mettre `====` seul dans une commande (« === not found »).
- Hors lot 3.2 : rappel « Dans 1 h » à recréer, brief du 25/10 (heure d'hiver), adresse du webmail OVH à confirmer.
