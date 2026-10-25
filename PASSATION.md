# Passation · Jarvis Jr. · 2026-10-25 (fin de soirée)

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Chantier : **lot 4.1, moteur d'XP et son affichage**. Mode chef d'orchestre.

## Où on en est
- Plan validé, textes compris : `plans/2026-10-25-lot4-1-xp.md`. Branche `lot4-1-xp`, arbre propre.
- Partie A (moteur pur, régulation, barème C) : faite et commitée (`1707a31`, `b8b5f4e`, `3c31894`, `34440dc`).
- **M3 serveur, `a924f58`, relu** : `xp` dans `GET donnees` (vaut `null` si le calcul échoue, erreur au journal) et dans les réponses de cocher, terminer, déplacer, annuler (`xp_gagnee`, `domaine`, `xp`), `fait_le` (7 jours en arrière, 2 min en avance au plus), `GET xp/export`. Annuler rend moins le gain lu dans le rejeu avant l'annulation.
- **M4 iPhone, `c61117b`, relu** : `site/js/lib/xp.js` (miroir de `JR_XP_REGLES` testé par `php -r`), gain estimé au geste avec plafonds (`aujourdhui_paye` plus les gestes en file), « Carte terminée. +30 XP Études. », « +5 XP » d'étape, niveau sur la poignée des tranches, bloc XP de Moi. Ajouts de Claude : `xp.js` dans `FICHIERS` de `sw.js` (VERSION recalculée), libellé du brief « Junior · <grade> » qui suit le grade, test du décocher rendu robuste.
- Tests vérifiés par Claude : PHP 534, JS 64, écran 133, tous verts. Rien n'est en ligne.
- En cours chez un sous-agent : rien.

## Décisions prises
- Barème C (25/10) et les 13 décisions du plan font foi. Seuils revus seulement à la baisse.
- `xp` null dans une réponse : l'iPhone garde l'XP connue ; sans XP du tout, le bloc de Moi disparaît et les poignées restent sans chiffre.
- Grade de départ affiché « Stagiaire » (« Junior · Stagiaire · x / 1 500 XP »), l'exemple « / 4 000 » du plan est périmé (barème C : 6 000).

## Prochaine étape
1. Lancer **M5** (célébrations : `celebration.js`, `aCelebrer` et `marquerVus` dans `xp.js`, branchement dans `app.js`), sous-agent `developpeur`, Sonnet, effort moyen, d'après la fiche M5 du plan. Ajouter à la fiche : (a) dans `app.js`, un Annuler d'une carte éclair déjà répondue doit retirer 1 aux éclairs payés estimés (`xp_paye`), aujourd'hui l'estimation reste trop basse jusqu'à la réponse ; (b) toute nouvelle page JS va dans `FICHIERS` de `sw.js` puis VERSION recalculée (`php tests/run.php "service worker"` donne la valeur).
2. M6 (`JR_VERSION = '0.4.1'`, VERSION de `sw.js`, `RECETTE-LOT4-1.md`), M7 (vérification), relecture ciblée, fusion dans `main`.
3. Mise en ligne et recette iPhone par Colin **à partir du 31/10**.
Effort conseillé pour la conversation suivante : **moyen**.

## Fichiers clés
- `plans/2026-10-25-lot4-1-xp.md` : fiches M5 à M7, décisions 5, 9, 10, § Textes
- `site/js/lib/xp.js` : moteur iPhone livré par M4
- `SPEC.md` § 18.6 : célébrations
- `guides/CHEF-D-ORCHESTRE.md` (racine du workspace) : méthode

## Pièges et points ouverts
- **Colin en Espagne du 26 au 30/10** : ni mise en ligne ni essai iPhone avant. Médian NF93 le 2/11.
- Les sous-agents ont glissé (code avant tests, `git stash`) : rappeler dans chaque fiche tests d'abord, aucune commande git qui modifie l'arbre. Ils ne touchent pas `sw.js` sans consigne : le dire.
- Indicateur « en attente » (`#statut`) pas encore visible juste après un clic : dans les tests d'écran, attendre l'état du faux serveur plutôt que `fileVide`.
- Fixtures e2e : identifiants de carte `6a92b700…` (anciens). Un identifiant `ca…` est dans le futur, donc carte éclair à 5 XP.
- Fonctions d'aide des tests PHP : `xpt_` (moteur) et `xpd_` (M3), ne pas les redéfinir.
- Coût : chaque écriture rejoue 2 fois toutes les actions (gain puis `xp_etat`). Acceptable pour quelques milliers d'actions, à réduire si ça rame.
- Pour 4.3 : seul Études atteint le niveau 13 dans l'année, à trancher avec les cartes de niveau.
- Commandes qui citent `.env`, `config.php`, `secrets/`, `deploy`, `sftp`, Trello ou `grep -r` entièrement dans `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`. `curl -s -D - -o /dev/null`, jamais `curl -sI`.
- Hors lot 4 : rappel « Dans 1 h » et brief à l'heure d'hiver (lot 2) à contrôler.
