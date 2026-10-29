# Passation · Jarvis Jr. · 2026-10-29

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Lot 4 (fun) : le 4.1 (XP) est fini. Chantier suivant : **lot 4.2, session focus contre un boss**.

## Où on en est
- Fait et vérifié : lot 4.1 mis en ligne par Colin le 29/10 et recetté sur l'iPhone (cases 2 à 6 conformes ; case 1 : le récapitulatif s'est affiché une seule fois, mais il s'est fermé aussitôt sur un toucher involontaire). L'élément Trello est coché.
- Ajouté le 29/10 à la demande de Colin, en ligne en **0.4.2** et vérifié (`/api/sante`) : « Revoir le récapitulatif » dans Moi. Le dernier récapitulatif est gardé dans le mémo IndexedDB `celebrations.dernier_recap` ; à défaut, le bouton affiche le bilan de tout l'historique (`recapARevoir`, `site/js/lib/xp.js`). Tests PHP 534, JS 75, écran 147. Commit `7e922f4` sur `main`, poussé.
- En cours chez un sous-agent : rien.

## Décisions prises
- Lot 4.1 considéré comme fini par Colin (29/10).
- La protection contre les touchers involontaires sur les célébrations se fera **avec le lot 4.2**, pas avant (décision de Colin).
- Le découpage du lot 4 (SPEC.md § 18.2) et le barème C (REGULATION-XP.md) font foi.

## Prochaine étape
1. Écrire la spec du lot 4.2 (boss) dans SPEC.md (nouvelle section après § 18.10), d'après PLAN.md (l. 65 à 70 : un boss par domaine, Le Bug, Le 99+, Le Poly, La Flemme, 25/45/60 min, fuite si on quitte l'app), CONTENU.md, VISUELS.md et REGULATION-XP.md (1 XP par minute + 10 si le boss est battu). Y inclure la protection : ignorer les touchers pendant environ 1 s après l'apparition d'une célébration plein écran (`site/js/ecrans/celebration.js`, le bouton Fermer couvre tout l'écran).
2. La faire valider par Colin, puis écrire le plan de missions et lancer en mode chef d'orchestre.
3. Revue de régulation de l'XP vers la mi-novembre (`GET xp/export`, SPEC.md § 18.10).
Effort conseillé pour la conversation suivante : **haut** (spec d'un lot).

## Fichiers clés
- `SPEC.md` § 18 : lot 4, découpage et règles du 4.1
- `PLAN.md` (l. 60 à 100), `CONTENU.md`, `VISUELS.md` : boss, textes, visuels
- `REGULATION-XP.md` : barème des boss
- `site/js/ecrans/celebration.js` : célébrations (protection d'1 s)

## Pièges et points ouverts
- Médians : NF93 lun 2/11 7h45, TN01 ven 6/11, MT23 sam 7/11. Ne pas pousser le lot 4.2 avant si Colin révise.
- Mise en ligne : `outils/deploy-ovh.sh` lancé par Colin seulement (SFTP refusé à Claude). Le script lance d'abord les tests, il faut donc que MySQL local tourne (`brew services run mysql@8.4`) : le 29/10, le premier essai s'est arrêté sur 231 échecs pour cette seule raison.
- Garde-fou : toute commande qui cite `.env`, `config.php`, `secrets/`, `deploy`, `sftp`, `mysql`, Trello ou `grep -r` doit être entièrement dans `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`, `cd` compris. `curl -s -D - -o /dev/null`, jamais `curl -sI`.
- Version : changer un fichier en cache oblige à recalculer `VERSION` dans `site/sw.js` (`php tests/run.php "service worker"` donne la valeur), et à monter `JR_VERSION` dans `serveur/lib/amorce.php` ainsi que la version du faux serveur e2e (`tests/e2e/serveur.mjs`, `test_moi.mjs`).
- Si les seuils de niveau sont baissés, un niveau atteint par la baisse peut ne pas être célébré (`emis_le` antérieur à `emis_le_max`). À regarder à la revue de régulation.
- Coût : chaque écriture rejoue 2 fois toutes les actions. À réduire si ça rame.
- Pour 4.3 : seul Études atteint le niveau 13 dans l'année, à trancher avec les cartes de niveau.
- Hors lot 4 : rappel « Dans 1 h » et brief à l'heure d'hiver (lot 2) à contrôler.
