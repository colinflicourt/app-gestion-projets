# Passation · Jarvis Jr. · 2026-10-25 23:00

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Chantier : **lot 4.1, moteur d'XP et son affichage**. Mode chef d'orchestre.

## Où on en est
- Plan du 4.1 écrit et validé par Colin, textes compris : `plans/2026-10-25-lot4-1-xp.md` (`8dfe21a`, mis à jour `34440dc`). Branche `lot4-1-xp`, arbre propre.
- **Partie A finie, relue, commitée** :
  - M1 `1707a31` : moteur pur `serveur/lib/xp.php` (rejeu, plancher, niveaux, grades, série et joker, événements avec `cle`, `date`, `emis_le`) ;
  - M2 `b8b5f4e` : `REGULATION-XP.md`, `outils/historique-trello.py` (GET seulement), `outils/simuler-xp.php` ; données extraites dans `outils/donnees/` (hors git) ;
  - M1b `3c31894` : barème C et plafonds par jour dans le moteur, `aujourdhui_paye` dans `xp_objet()` ;
  - `34440dc` : barème C reporté dans `CONTENU.md` § 7, `SPEC.md` § 18.4 et § 18.10, et le plan.
- Tests : PHP 515 (469 au départ), JS 51, écran 120. Rien n'est encore branché au serveur ni à l'iPhone.
- En cours chez un sous-agent : rien.

## Décisions prises
- **Barème C validé par Colin** (25/10) : carte 30 (+20 Urgent, +10 échéance), éclair 5 (au plus 2 par jour), étape 5 (au plus 10 par jour), pas de niveau 160 (niveau 13 = 12 480), grades 1 500 / 6 000 / 22 000. Combat 1 XP/min et boss +10, journal 3 (au plus 2 par jour) : pour 4.2 et 4.4, pas dans `JR_XP_REGLES` maintenant.
- Seuils revus après 2 à 3 semaines d'usage, **seulement à la baisse** (les monter ferait redescendre des niveaux déjà gagnés).
- Gain plafonné (décision 12 du plan) : l'iPhone estime 0, et affiche alors « Carte terminée. » sans XP, et pas de « +5 XP » pour une étape.
- Les 12 décisions et les contrats du plan font foi (renvoi d'écriture, `fait_le`, célébrations par `emis_le` et `cle`, magasin IndexedDB `donnees` clé `celebrations`, version 0.4.1).

## Prochaine étape
1. Lancer **M3** (serveur : `xp` dans `GET donnees` et les écritures, heure du geste, `GET xp/export`), sous-agent `developpeur`, Sonnet, effort haut, d'après la fiche M3 du plan. M3 se fonde sur la sortie réelle de `xp_rejouer()` (clés en plus : `aujourdhui`, `paye_aujourdhui`).
2. Puis M4 (iPhone : `xp.js` et miroir testé, gains, tranches, Moi), M5 (célébrations), M6 (version 0.4.1, `RECETTE-LOT4-1.md`), M7 (vérification). Relecture ciblée par Claude, fusion dans `main`.
3. Mise en ligne et recette iPhone par Colin **à partir du 31/10**.
Effort conseillé pour la conversation suivante : **moyen**.

## Fichiers clés
- `plans/2026-10-25-lot4-1-xp.md` : décisions, interfaces, fiches M3 à M7
- `serveur/lib/xp.php` : moteur livré (à lire par signatures seulement)
- `SPEC.md` § 18.5 et § 18.6 : API et iPhone
- `guides/CHEF-D-ORCHESTRE.md` (racine du workspace) : méthode

## Pièges et points ouverts
- **Colin en Espagne du 26 au 30/10** : ni mise en ligne ni essai iPhone avant son retour. Médian NF93 le 2/11.
- Les sous-agents ont glissé deux fois : M1b a écrit le code avant les tests, et a lancé un `git stash` aussitôt restauré. Rappeler dans chaque fiche : tests d'abord, aucune commande git qui modifie l'arbre.
- Fonctions d'aide des tests XP préfixées `xpt_` : ne pas les redéfinir dans les tests de M3.
- Faux serveur e2e : `tests/e2e/serveur.mjs:97` annonce `0.2.0`, à aligner en M4.
- Pour 4.3 : seul Études atteint le niveau 13 dans l'année (les autres domaines plafonnent entre 6 et 8), à trancher avec les cartes de niveau.
- `curl -s -D - -o /dev/null`, jamais `curl -sI`. Commandes qui citent `.env`, `config.php`, `secrets/`, `deploy`, `sftp`, Trello ou `grep -r` entièrement dans `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`.
- Hors lot 4 : rappel « Dans 1 h » et brief à l'heure d'hiver (lot 2) à contrôler.
