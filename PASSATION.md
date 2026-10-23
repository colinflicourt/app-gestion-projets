# Passation · Jarvis Jr. · 2026-10-23 11:10

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Finir la recette du lot 2 (rappels), puis démarrer le **lot 3 (Mails)** en mode chef d'orchestre. Le lot 4 (le fun) viendra après.

## Où on en est
- **Fait et vérifié** (23/10) :
  - Colin confirme : notification de test reçue (écran verrouillé aussi), brief du 23/10 reçu, Siri OK.
  - Table `envois` contrôlée en ligne par une nouvelle section en lecture seule de `outils/test-serveur/diagnostic.php` (`$r['envois']`, testée en local puis en ligne) : `AUCUN_DOUBLON true`, aucun envoi rejoué, retard d'envoi de 35 s au plus. Seul le type `brief` figure dans la table (pas de cours avant novembre).
- **Anomalies relevées, non résolues** :
  1. **Brief à 10h30 de Paris au lieu de 7h30** : `brief:2026-10-23` prévu `08:30 UTC`, envoyé `08:30:35 UTC`, et `brief:2026-10-24` aussi à `08:30 UTC`. Le brief du 22/10 était bien prévu à `05:30 UTC`. **Question posée à Colin, sans réponse** : a-t-il réglé le brief sur 10h30 dans Réglages ? Si non, c'est un bug (voir `serveur/lib/rappels.php`, `brief.php`, `reglages.php`).
  2. **Table `rappels` vide** : aucun rappel manuel n'a été créé en ligne, alors que Colin dit avoir reçu le « Dans 1 h ». Il a probablement confondu avec la notification de test. Il doit refaire un rappel « Dans 1 h », puis on relit la table.
- **Fait en local, pas commité, pas en ligne** : renommage « Retiens pour Jarvis » (`textes.js`, `SPEC.md`, `RECETTE-LOT2.md`, `PLAN.md`, `CONTENU.md`, `VERSION` de `sw.js`) et nouvelle section de `diagnostic.php`.
- **Diagnostic encore en ligne** si Colin n'a pas lancé `outils/test-serveur/nettoyer.sh`.
- **En cours chez un sous-agent** : rien.

## Décisions prises
- Rappel de cours : pas de faux cours, on attend les vrais cours de novembre (choix de Colin).
- Celles des passations précédentes restent valables : les sous-agents ne commitent pas, Colin lance tout envoi vers OVH, le raccourci Siri s'appelle « Retiens pour Jarvis ».
- Lot 3 à mener en mode chef d'orchestre (`guides/CHEF-D-ORCHESTRE.md`).

## Prochaine étape
1. Demander à Colin la réponse sur le brief de 10h30 et s'il a lancé `nettoyer.sh`. Si c'est un bug, le corriger en premier (test qui reproduit, puis correctif).
2. Commiter les changements locaux (renommage, diagnostic, éventuel correctif). Colin met en ligne, puis refait un rappel « Dans 1 h ».
3. **Lot 3 (Mails)** : rédiger la spec, en suivant le skill brainstorming, sous forme d'un `SPEC.md` § 16 à faire valider par Colin. Elle couvre :
   - les 5 boîtes en lecture seule (UTC via la redirection Tuttifruti, Gmail, iCloud, colin@ et contact@ OVH) ;
   - la section UTC et le bloc d'Aujourd'hui ;
   - les mails non lus dans le brief ;
   - le chiffrement des mots de passe d'application (`SPEC.md` l. 89).

   Ensuite, le plan de missions (`plans/AAAA-MM-JJ-lot3-1-...md`), puis l'exécution par les sous-agents.
4. Clore le lot 2 (README, HISTORY, Trello, `main` sur GitHub). Seul le rappel des vrais cours resterait à vérifier en novembre.

Effort conseillé : **moyen** (haut pour la spec du lot 3).

## Fichiers clés
- `RECETTE-LOT2.md` § 8 : cases de la recette du lot 2
- `PLAN.md` l. 27-73 (fonctions de l'app, Mails) et l. 208-260 (lots, préparatifs avant le lot 3)
- `SPEC.md` § 4 (l. 89, données du lot 3), § 8 (l. 274, onglet Mails), § 15 (lot 2, modèle à suivre pour le § 16)
- `outils/test-serveur/diagnostic.php` : section `envois` réutilisable pour le prochain contrôle (`envoyer.sh --lot2`, puis curl de `https://jr.qyris.fr/diag-<jeton>.php`, jeton dans `outils/test-serveur/.jeton`)

## Pièges et points ouverts
- **Aucun accès direct de Claude à la base OVH** : passer par le diagnostic, que Colin envoie et retire.
- Garde-fou : toute commande qui cite `serveur/lib`, un `grep -r` ou un script de mise en ligne passe entièrement par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`.
- Passage à l'heure d'hiver dans la nuit du 24 au 25/10 : vérifier le 25/10 que le brief n'est ni doublé ni sauté (relire `envois`).
- **Préparatifs du lot 3 côté Colin, pas faits** : redirection UTC dans Tuttifruti, mots de passe d'application Gmail (validation en 2 étapes) et iCloud. Ils ne bloquent pas la spec, mais ils bloquent les tests réels.
- Toujours à trancher par Colin : le piège de « Terminer », les chiffres des codes d'UV, l'effacement de la copie de l'iPhone au premier 401, la relecture de `CONTENU.md` § 5 et § 5.1.
- Colin veut des passations plus fréquentes (économie de crédits) : en proposer une après chaque étape livrée.
