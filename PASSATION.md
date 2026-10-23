# Passation · Jarvis Jr. · 2026-10-23 16:06

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Le **lot 3 (Mails, lecture seule)** est codé ; il reste sa mise en ligne et sa recette avec Colin (partie B). Le lot 4 (le fun) viendra après.

## Où on en est
- **Fait et vérifié** (23/10) :
  - Lot 3, missions M1 à M7 faites par sous-agents, relues et commitées par Claude. Heures « 14 h 32 » et textes nouveaux validés par Colin (`b88e23a`).
  - **M7 (vérification indépendante) conforme** : 351 tests PHP, 48 JS, 98 d'écran, 0 échec (avant le lot : 247, 41, 73). Aucun `STORE`, `EXPUNGE`, `SELECT "` dans `serveur/` ; liste blanche : CAPABILITY, LOGIN, `EXAMINE "INBOX"`, `UID SEARCH UNSEEN|ALL`, `UID FETCH` en `BODY.PEEK`, LOGOUT. Aucun secret suivi, aucun tiret long. Captures 12 à 15 (`tests/e2e/captures/`) propres.
  - Relecture de Claude : mot de passe `SensitiveParameter`, chiffré en base, absent des réponses ; erreurs IMAP sans texte du serveur ; `journal_erreur` n'écrit que le message.
  - SPEC § 16, `CONTENU.md` § 3 (Mails, Réglages), README à jour (`57bfe38`). **Fusionné dans `main` en local** (`d8f1a77`). Pas sur GitHub, pas en ligne.
- **Fait, pas encore vérifié** : tout le lot 3 sur de vraies boîtes (rien n'a parlé à un vrai serveur IMAP).
- **En cours chez un sous-agent** : rien. Arbre git propre, sur `main`.

## Décisions prises
- Plan du lot 3 (décisions 1 à 12) et SPEC § 16.1 : à ne pas rediscuter.
- Sous-agents sans commit (Claude relit et commite). C'est Colin qui lance tout envoi vers OVH.

## Prochaine étape
1. **Colin fait ses préparatifs** (`RECETTE-LOT3.md` partie 1, 30 à 45 min) : redirection depuis le webmail UTC vers Gmail + mail « Test UTC », mot de passe d'application Gmail (validation en 2 étapes), mot de passe d'application iCloud, mots de passe OVH de colin@ et contact@. Jamais de mot de passe collé dans la conversation.
2. Colin lance la mise en ligne (commandes exactes dans `RECETTE-LOT3.md`, migration 006 comprise), saisit les 4 comptes dans Réglages sur l'iPhone, lance le diagnostic.
3. Claude lit `entetes_gmail` à travers le filtre ; si le mail « Test UTC » n'est pas reconnu, petite mission pour ajuster `JR_MARQUEURS_UTC`, nouvelle mise en ligne.
4. Recette du SPEC § 16.10 (cases de `RECETTE-LOT3.md`), puis `main` sur GitHub, README, Trello.

Effort conseillé pour la conversation suivante : **moyen**.

## Fichiers clés
- `RECETTE-LOT3.md` : préparatifs, mise en ligne, diagnostic, recette pas à pas
- `plans/2026-10-23-lot3-1-mails.md` : décisions et interfaces, si un ajustement est nécessaire
- `serveur/lib/tri_mails.php` (`JR_MARQUEURS_UTC`, `mail_est_utc`) : seul endroit à toucher pour la règle UTC
- `SPEC.md` § 16.10 et § 16.12

## Pièges et points ouverts
- Garde-fou : toute commande qui cite `.env`, `config.php`, `serveur/lib` en recherche récursive, un `grep -r`, `brew services` ou un script de mise en ligne passe entièrement par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`. Un heredoc contenant « .env » est bloqué. Le sous-agent de M7 a vu `brew services run mysql@8.4` bloqué même filtré : vérifier MySQL par `pgrep -fl mysqld`.
- La suite PHP dure environ 70 s (serveur muet de 8 s). Filtre par nom : `php tests/run.php <morceau du nom>`.
- Adresse du webmail OVH (décision 9) et lien direct d'un mail Gmail (`lien`) à confirmer à la recette.
- Lot 2, toujours ouvert : rappel « Dans 1 h » à recréer puis contrôler (`envoyer.sh --lot2`) ; le 25/10, vérifier que le brief n'est ni doublé ni sauté au passage à l'heure d'hiver ; clore le lot (Trello, GitHub).
- Encore à trancher par Colin : piège de « Terminer », chiffres des codes d'UV, effacement de la copie de l'iPhone au premier 401, relecture de `CONTENU.md` § 5 et § 5.1.
