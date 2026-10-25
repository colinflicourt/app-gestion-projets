# Passation · Jarvis Jr. · 2026-10-25 (soir)

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Chantier : **lot 4 (le fun)**, en commençant par le **4.1 : moteur d'XP et son affichage**, précédé d'une **régulation de l'XP**. Mode chef d'orchestre.

## Où on en est
- **Lots 3 et 3.2 finis** : version 0.3.2 en ligne (`version_base` 6), en-têtes vérifiés par GET le 25/10, recettes iPhone du lot 3 (§ 5 et § 6) et du lot 3.2 validées par Colin le 25/10 (cases cochées dans `RECETTE-LOT3.md` et `RECETTE-LOT3-2.md`).
- **Spec du lot 4 écrite et commitée** (`SPEC.md` § 18, `99fcb34`) : découpage 4.1 à 4.4, détail du 4.1 (§ 18.3 à 18.9) **validé par Colin**, régulation de l'XP (§ 18.10) ajoutée à sa demande. Relue par un sous-agent builder (8 points tranchés, marqués « choix de Claude »).
- Rien n'est codé pour le lot 4. Branche `main`, arbre propre. Tests actuels : PHP 469, JS 51, écran 120.
- En cours chez un sous-agent : rien.

## Décisions prises
- **L'XP est le moteur** (Colin) : chaque gain visible au moment de l'action, l'XP doit refléter du vrai travail.
- Ordre : 4.1 moteur et affichage, 4.2 boss, 4.3 badges, cartes et album, 4.4 journal et Wrapped. Chaque sous-lot : spec, plan, sous-agents, vérification, mise en ligne par Colin, recette iPhone.
- **Carte éclair** (Colin) : carte terminée moins de 10 min après sa création = 5 XP au lieu de 30 et bonus ; création lue dans l'identifiant Trello.
- **Régulation avant le moteur** (Colin) : analyser toutes les sources d'XP et tous les paliers, simuler l'année (`outils/simuler-xp.php`), rapport `REGULATION-XP.md` validé par Colin, qui remplace le barème de `CONTENU.md` § 7. Barème et seuils dans une seule table de constantes (PHP + miroir JS testé).
- Le combat paie trop peu (45 min = 14 XP) : piste « 1 XP par minute », à trancher par la simulation. Durée du boss déjà réglable (25, 45, 60 min, jusqu'au prochain cours) ; durée libre à discuter dans la spec du 4.2.
- XP jamais stockée, aucune migration au 4.1, pas de notification push avant le 4.3.

## Prochaine étape
1. Écrire le plan de missions du 4.1 avec superpowers:writing-plans (`plans/2026-10-2x-lot4-1-xp.md`), à faire valider par Colin. **M1 = régulation de l'XP** (§ 18.10), son rapport validé par Colin avant les missions du moteur.
2. Lancer M1 (sous-agent `developpeur`, Sonnet), présenter `REGULATION-XP.md` à Colin.
3. Puis les missions du moteur (`serveur/lib/xp.php`, API, iPhone), vérification, mise en ligne au retour de Colin.
Effort conseillé pour la conversation suivante : **moyen**.

## Fichiers clés
- `SPEC.md` § 18 : spec du lot 4 (4.1 détaillé, régulation § 18.10)
- `CONTENU.md` § 3 (Moi), § 5 à § 8 bis : barème actuel, grades, textes, cartes
- `plans/2026-10-25-lot3-2-contenu.md` : modèle de plan de missions
- `guides/CHEF-D-ORCHESTRE.md` (racine du workspace) : méthode

## Pièges et points ouverts
- **Colin en Espagne du 26 au 30/10** : pas de mise en ligne ni d'essais iPhone avant son retour. Médian NF93 le 2/11 : sessions courtes.
- Les vraies actions sont dans MySQL sur OVH, inaccessibles en local : la simulation part de l'historique Trello (`trello.py lire --fait`, lecture seule) ; une route ou un export de lecture est à prévoir pour la régulation suivante.
- `serveur/lib/taches.php` note l'heure de réception : il faut ajouter l'heure du geste envoyée par l'iPhone (§ 18.5).
- Faux serveur e2e : `tests/e2e/serveur.mjs:97` annonce `version_app '0.2.0'`, à aligner sur 0.3.2.
- Vérifier les en-têtes avec `curl -s -D - -o /dev/null`, jamais `curl -sI`. Suite PHP complète environ 65 s ; fonction définie dans `serveur/routes/*.php` → « Cannot redeclare », la mettre dans `serveur/lib/`.
- Garde-fou : commandes citant `.env`, `config.php`, `secrets/`, `deploy`, `sftp`, `grep -r` entièrement dans `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`. En zsh, pas de `====` seul.
- Hors lot 4 : rappel « Dans 1 h » et brief à l'heure d'hiver (lot 2) à contrôler ; adresse du webmail OVH à confirmer.
