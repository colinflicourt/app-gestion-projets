# Passation · Jarvis Jr. · 2026-10-23 11:23

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Mener le **lot 3 (Mails)** en mode chef d'orchestre : la spec est validée, il reste à écrire le plan de missions puis à le faire exécuter. Le lot 4 (le fun) viendra après.

## Où on en est
- **Fait et vérifié** (23/10) :
  - **Spec du lot 3 validée par Colin** : `SPEC.md` § 16 (l. 552 et suivantes), relue par un sous-agent « builder » dont les 6 points ont tous été tranchés dans le document.
  - Mise en ligne de `cd57b3c` faite par Colin (il le confirme).
  - Carte Trello Perso `h4xDF2bR` « iCloud : trier la boîte » créée (chantier à part, hors de l'app).
- **Pas encore fait** : contrôle de la table `rappels`. Colin doit d'abord recréer un rappel « Dans 1 h », puis on lance le diagnostic (`envoyer.sh --lot2`, curl, `nettoyer.sh`). Reporté à plus tard, à sa demande.
- **En cours chez un sous-agent** : rien. `SPEC.md` n'est pas commité.

## Décisions prises (détail au § 16.1)
- UTC : la boîte est redirigée vers Gmail par Tuttifruti (Mon compte > Gestion de compte). Colin accepte que ses mails UTC ne soient plus gardés sur le serveur de l'UTC. L'app ne touche jamais au CAS.
- Aucune notification de mail : les mails sont seulement affichés (onglet, bloc d'Aujourd'hui, brief).
- Les pubs sont repérées par leurs en-têtes, repliées (« + 48 pubs ») et jamais comptées. La section UTC n'est jamais filtrée. Correction par expéditeur dans la base de Junior.
- Ordre des sections : UTC, QYRIS (colin@ et contact@), Gmail, iCloud.
- Lecture seule stricte : `EXAMINE` et `BODY.PEEK` seulement, avec une liste blanche de commandes testée.
- Un rafraîchissement par compte, lancés en parallèle depuis l'iPhone ; copies dans `copies` (`mail:<compte>`).
- Toujours en vigueur : les sous-agents ne commitent pas, et c'est Colin qui lance tout envoi vers OVH.

## Prochaine étape
1. Commiter `SPEC.md` (§ 16) et cette passation sur `main`.
2. Écrire le plan de missions du lot 3 avec superpowers:writing-plans, en partant de `SPEC.md` § 16 et du modèle `plans/2026-10-22-lot2-3-rappels-siri.md` : `plans/2026-10-2X-lot3-1-....md`. Découpage probable : (a) client IMAP, décodage et faux serveur ; (b) tri UTC et pubs, comptes, routes, brief ; (c) écrans Mails, Aujourd'hui, Réglages, Playwright. Le montrer à Colin, puis lancer les sous-agents (`guides/CHEF-D-ORCHESTRE.md`).
3. Ajouter au diagnostic une section qui montre les en-têtes bruts des derniers mails Gmail (§ 16.6), pour régler la règle UTC à la recette.
4. Clore le lot 2 : README, HISTORY, case Trello « Lot 2 : mise en ligne et recette », `main` sur GitHub. Il restera le rappel des vrais cours à vérifier en novembre.

Effort conseillé : **haut** (plan du lot 3).

## Fichiers clés
- `SPEC.md` § 16 : spec du lot 3 (validée), § 15 et `rafraichir.php` comme modèles
- `plans/2026-10-22-lot2-3-rappels-siri.md` : modèle de plan
- `serveur/lib/chiffrement.php` (`chiffrer`, `dechiffrer`), `serveur/lib/rafraichir.php` (`rafraichir`), `serveur/lib/brief.php`
- `CONTENU.md` § Mails et § Réglages : textes

## Pièges et points ouverts
- Garde-fou : toute commande qui cite `.env`, `serveur/lib`, un `grep -r` ou un script de mise en ligne passe entièrement par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`. Un heredoc qui contient le mot « .env » est bloqué aussi : écrire avec l'outil Edit.
- Claude n'a aucun accès direct à la base OVH : il passe par le diagnostic, que Colin envoie puis retire.
- Le 25/10, après le passage à l'heure d'hiver, vérifier que le brief de 10h30 n'est ni doublé ni sauté (relire `envois`).
- Préparatifs du lot 3 côté Colin, pas faits : redirection Tuttifruti (regarder s'il existe une case « garder une copie »), validation en 2 étapes puis mot de passe d'application Gmail, mot de passe d'application iCloud, mots de passe OVH de colin@ et contact@. Ils bloquent les tests réels, pas le développement.
- La page de la DSI sur Tuttifruti répondait en 502 le 23/10 : le chemin du menu vient seulement d'un résumé de moteur de recherche.
- Encore à trancher par Colin : le piège de « Terminer », les chiffres des codes d'UV, l'effacement de la copie de l'iPhone au premier 401, la relecture de `CONTENU.md` § 5 et § 5.1.
- Colin veut des passations fréquentes : en proposer une après chaque étape livrée.
