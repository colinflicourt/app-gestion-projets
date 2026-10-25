# Passation · Jarvis Jr. · 2026-10-25 (soir)

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Le **lot 3.2** (lire un mail en entier : contenu, marqué lu à l'ouverture, pièces jointes, Répondre) est **terminé**.

## Où on en est
- **Lot 3.2 fini** : M1 à M8 faites et vérifiées, fusion dans `main` (`f79b87e`), version 0.3.2 en ligne (`/api/sante` : `version_base` 6, `base_ok` true).
- En-têtes vérifiés par GET le 25/10 : une seule CSP pour l'app (200), une seule CSP pour le corps d'un mail (401 sans session, `frame-ancestors 'self'`). OVH accepte le bloc `<If>` du `.htaccess`, pas de retour arrière.
- Recette iPhone du lot 3.2 (`RECETTE-LOT3-2.md` § 4) faite par Colin le 25/10 : tout conforme. Décision 11 tranchée (la pièce jointe s'ouvre et on revient dans Junior), la mission M9 n'est pas nécessaire.
- Tests : PHP 469, JS 51, écran 120.

## Reste à faire (hors lot 3.2)
- Recette du lot 3 (`RECETTE-LOT3.md` § 5 et § 6) : à confirmer avec Colin, peut-être couverte par ses essais du 25/10.
- Lot 2 : rappel « Dans 1 h » à recréer, brief du 25/10 (heure d'hiver) à contrôler.
- Adresse du webmail OVH à confirmer.
- Faux serveur e2e : `tests/e2e/serveur.mjs:97` annonce `version_app '0.2.0'`, à aligner sur 0.3.2 si on y touche.
- Lot 4 (fun) d'après `PLAN.md`, quand Colin le décide.

## Pièges
- Vérifier les en-têtes avec `curl -s -D - -o /dev/null`, jamais `curl -sI` (`HEAD` n'est pas prévu par la condition `.htaccess`).
- Filtre de `php tests/run.php <filtre>` sensible à la casse ; suite PHP complète environ 65 s. Fonction définie dans `serveur/routes/*.php` → « Cannot redeclare » : la mettre dans `serveur/lib/`.
- Garde-fou : toute commande citant `.env`, `config.php`, `secrets/`, `deploy`, `sftp`, un `grep -r` passe entièrement par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`. En zsh, ne pas mettre `====` seul dans une commande.
- Les mises en ligne sur OVH, c'est Colin qui les lance (`outils/deploy-ovh.sh`).
