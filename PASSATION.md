# Passation · Jarvis Jr. · 2026-10-23 11:01

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Clore la recette du lot 2 (rappels), puis mener le **lot 3 (Mails)** en mode chef d'orchestre, en commençant par sa spec. Le lot 4 (le fun) viendra après.

## Où on en est
- **Fait et vérifié** (23/10) :
  - Recette du lot 2 : notification de test (écran verrouillé aussi), brief du 23/10 et Siri OK. La table `envois` ne contient aucun doublon (contrôle fait avec la section `envois` de `diagnostic.php`).
  - Brief à 10h30 : **pas un bug**, c'est Colin qui l'a réglé sur cette heure. Anomalie close.
  - Diagnostic retiré du serveur : Colin a lancé `nettoyer.sh`, et `.mode-lot2` a été supprimé.
  - Commit `cd57b3c` sur `main` : renommage « Retiens pour Jarvis » et section `envois` du diagnostic. Tests : 247/247 en PHP, 41/41 en JS.
- **Pas encore fait, pas vérifié** :
  - Mise en ligne de `cd57b3c` par Colin (`outils/deploy-ovh.sh`). Je ne sais pas s'il l'a lancée.
  - Rappel « Dans 1 h » à recréer par Colin, puis relecture de la table `rappels` par le diagnostic (`envoyer.sh --lot2`, curl, `nettoyer.sh`). Cette table était vide.
- **En cours chez un sous-agent** : rien.

## Décisions prises
- Rappel de cours : pas de faux cours, on attend les vrais cours de novembre.
- Les sous-agents ne commitent pas. Colin lance tout envoi vers OVH. Le raccourci Siri s'appelle « Retiens pour Jarvis ».
- Lot 3 à mener en mode chef d'orchestre (`guides/CHEF-D-ORCHESTRE.md`).

## Prochaine étape
1. Demander à Colin s'il a mis en ligne et recréé le rappel « Dans 1 h ». Si oui, contrôler la table `rappels` par le diagnostic.
2. **Spec du lot 3 (Mails)**, avec le skill brainstorming : `SPEC.md` § 16, à faire valider par Colin, sur le modèle du § 15. Elle couvre :
   - les 5 boîtes en lecture seule (UTC via la redirection Tuttifruti, Gmail, iCloud, colin@ et contact@ OVH) ;
   - la section UTC et le bloc d'Aujourd'hui ;
   - les mails non lus dans le brief ;
   - le chiffrement des mots de passe d'application (`SPEC.md` l. 89).
3. Écrire le plan de missions (`plans/AAAA-MM-JJ-lot3-1-...md`), puis lancer l'exécution par les sous-agents.
4. Clore le lot 2 : README, HISTORY, Trello (case « Lot 2 : mise en ligne et recette »), `main` sur GitHub. Seul le rappel des vrais cours resterait à vérifier en novembre.

Effort conseillé : **haut** (spec du lot 3).

## Fichiers clés
- `PLAN.md` l. 27-73 (fonctions de l'app, Mails) et l. 208-260 (lots, préparatifs du lot 3)
- `SPEC.md` § 4 (l. 89, données du lot 3), § 8 (l. 274, onglet Mails), § 15 (lot 2, modèle du § 16)
- `RECETTE-LOT2.md` § 8 : cases de la recette du lot 2
- `outils/test-serveur/diagnostic.php` (sections `envois` et `rappels`), `envoyer.sh --lot2`, `nettoyer.sh`, jeton dans `outils/test-serveur/.jeton`

## Pièges et points ouverts
- **Claude n'a aucun accès direct à la base OVH** : il faut passer par le diagnostic, que Colin envoie puis retire.
- Garde-fou : toute commande qui cite `serveur/lib`, un `grep -r` ou un script de mise en ligne passe entièrement par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`.
- Passage à l'heure d'hiver dans la nuit du 24 au 25/10 : vérifier le 25/10 que le brief de 10h30 n'est ni doublé ni sauté (relire `envois`).
- **Préparatifs du lot 3 côté Colin, pas faits** : la redirection UTC dans Tuttifruti, les mots de passe d'application Gmail (validation en 2 étapes) et iCloud. Ils ne bloquent pas la spec, mais ils bloquent les tests réels.
- Encore à trancher par Colin : le piège de « Terminer », les chiffres des codes d'UV, l'effacement de la copie de l'iPhone au premier 401, la relecture de `CONTENU.md` § 5 et § 5.1.
- Colin veut des passations fréquentes, pour économiser des crédits : en proposer une après chaque étape livrée.
