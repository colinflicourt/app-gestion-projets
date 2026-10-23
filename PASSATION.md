# Passation · Jarvis Jr. · 2026-10-23 12:11

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. On mène le **lot 3 (Mails, lecture seule)** en mode chef d'orchestre, d'après `plans/2026-10-23-lot3-1-mails.md`. Le lot 4 (le fun) viendra après.

## Où on en est
- **Fait et vérifié** (23/10, branche `lot3-mails`, partie de `main` à `39ba5e5`) :
  - Plan du lot 3 écrit (7 missions) et commité (`3f19baa`). Colin a dit « on commence ici », sans encore trancher les textes nouveaux ni le format des heures.
  - **M1** client IMAP en lecture seule (`695ad15`) : liste blanche stricte, un seul `fwrite` gardé par elle, faux serveur IMAP lancé par `proc_open`.
  - **M2** migration 006, comptes chiffrés, tri UTC et pubs (`6e75234`).
  - **M3** routes, rafraîchissement par compte, planificateur, brief (`b953cba`).
  - Claude a relancé la suite PHP lui-même après chaque mission : **348 tests verts** (247 au départ). JS : 41, écran : 73, non touchés.
  - Le plan contient maintenant le **format exact de l'API livrée par M3** (§ Interfaces), pour M4 et M5.
- **En cours chez un sous-agent** : rien. Arbre git propre.

## Décisions prises
- Celles du plan (décisions 1 à 12) et du SPEC § 16.1, à ne pas rediscuter.
- Écarts acceptés : un mail illisible est écarté au lieu de faire échouer la boîte ; dates des mails en ISO UTC avec `Z` ; doublon QYRIS : on garde colin@, non lu si l'un des deux l'est ; changer l'identifiant d'un compte efface sa copie ; second crochet de test `JR_ESSAI_IMAP` (réglé par `faux_imap()`).
- Toujours en vigueur : les sous-agents ne commitent pas (Claude relit et commite), et c'est Colin qui lance tout envoi vers OVH.

## Prochaine étape
1. **Demander à Colin** (bloquant pour M4) : heures des mails « 14:32 » (SPEC § 16.8) ou « 14 h 32 » (style du reste de l'app, conseillé par Claude) ; validation des textes nouveaux du plan (§ Textes nouveaux). Reporter ses réponses dans le plan (décision 8, tableau).
2. Lancer **M4** (onglet Mails et bloc d'Aujourd'hui, `developpeur` Sonnet moyen) avec la fiche du plan, puis **M5** (Réglages > Comptes mail), **M6** (diagnostic `entetes_gmail`, migration 006 à la mise en ligne, `RECETTE-LOT3.md`), **M7** (`verificateur` Haiku).
3. Relecture ciblée par Claude, SPEC § 16 et `CONTENU.md` à jour, README, fusion dans `main`.
4. Partie B avec Colin : ses préparatifs (SPEC § 16.12), mise en ligne, réglage de la règle UTC sur de vrais en-têtes, recette.

Effort conseillé pour la conversation suivante : **moyen**.

## Fichiers clés
- `plans/2026-10-23-lot3-1-mails.md` : plan, décisions, interfaces, API de M3, fiches M4 à M7
- `SPEC.md` § 16.8 (écrans), `CONTENU.md` § 3 Mails et Réglages, `DESIGN.md` (lien de la maquette v28)
- `serveur/routes/mails.php`, `serveur/lib/mails.php` : l'API que M4 et M5 consomment

## Pièges et points ouverts
- Garde-fou : toute commande qui cite `.env`, `serveur/lib` en recherche récursive, un `grep -r` ou un script de mise en ligne passe entièrement par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`. Un heredoc contenant « .env » est bloqué.
- La suite PHP dure environ 70 s (un test attend le délai de 8 s du serveur muet). Filtre par nom : `php tests/run.php <morceau du nom>`.
- Adresse du webmail OVH (décision 9) à confirmer à la recette.
- Lot 2, toujours ouvert : rappel « Dans 1 h » à recréer puis contrôler (`envoyer.sh --lot2`) ; le 25/10, vérifier que le brief n'est ni doublé ni sauté au passage à l'heure d'hiver ; clore le lot (README, HISTORY, case Trello, `main` sur GitHub).
- Préparatifs de Colin pour le lot 3 pas faits (redirection Tuttifruti, mots de passe d'application Gmail et iCloud, mots de passe OVH) : ils bloquent la recette, pas le code.
- Encore à trancher par Colin : piège de « Terminer », chiffres des codes d'UV, effacement de la copie de l'iPhone au premier 401, relecture de `CONTENU.md` § 5 et § 5.1.
