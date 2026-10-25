# Passation · Jarvis Jr. · 2026-10-25 12:16

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Lot 3 (Mails) en ligne et en recette ; prochain chantier : le **lot 3.2**, lire un mail en entier depuis l'app.

## Où on en est
- **Fait et vérifié** :
  - Lot 3 en ligne le 25/10 (`/api/sante` : `version_base` 6). Comptes UTC (`imaps.utc.fr`, la redirection vers Gmail est abandonnée), Gmail et iCloud branchés par Colin : « Connecté, vérifié ».
  - Commits du 25/10, sur `main` en local : `226c88f` (onglet Mails = liste des 4 boîtes, une boîte s'ouvre sur `#/mails/<boîte>` avec « Retour aux boîtes »), `19574f9` (non lu : pastille et trait ambre, gras ; lu : gris), `e73e7c9` (compte `contact` retiré : contact@ n'est qu'une redirection vers colin@ ; une ligne `contact` restée en base est ignorée, test ajouté), `b231629` (étiquette « contact@ » sur un mail adressé à contact@, via To/Cc/Delivered-To/X-Forwarded-To déjà lus, champ `pour_contact`). 349 tests PHP, 48 JS, 100 e2e, 0 échec ; captures vérifiées.
- **Fait, pas encore vérifié** : ces 4 commits ne sont **pas en ligne** (Colin lance `outils/deploy-ovh.sh`). colin@qyris.fr pas encore branché. Recette `RECETTE-LOT3.md` § 5 et § 6 pas faite.
- **En cours chez un sous-agent** : rien. Arbre git propre.

## Décisions prises
- Lot 3.2, tranché par Colin le 25/10 :
  1. Toucher un mail ouvre son **contenu complet**.
  2. L'ouvrir le **marque comme lu dans la vraie boîte** : fin de la lecture seule stricte, ouverte au minimum (seul le drapeau `\Seen`, jamais `\Deleted` ni `EXPUNGE`). **Pas** de bouton « Marquer non lu ».
  3. « Répondre » = version **simple** : ouvre Mail ou Gmail de l'iPhone (destinataire et « Re: objet » pré-remplis). Pas d'envoi SMTP depuis l'app (risque d'usurpation si la session est volée).
  4. Mails HTML : affichés **nettoyés**, images distantes **bloquées** avec un bouton « Afficher les images ».
- Sous-agents sans commit (Claude relit et commite). C'est Colin qui lance tout envoi vers OVH.

## Prochaine étape
1. Demander à Colin si la mise en ligne de `b231629` est faite et si colin@ est branché ; sinon la lui faire lancer d'abord.
2. Spec du lot 3.2 avec superpowers:brainstorming → `SPEC.md` § 17 : route de contenu (`BODY.PEEK[partie]` puis `UID STORE +FLAGS.SILENT (\Seen)`, donc `SELECT` au lieu d'`EXAMINE` pour cette route seulement), liste blanche IMAP élargie au strict nécessaire, nettoyage HTML (affichage isolé, CSP, pas de script), écran du mail, bouton Répondre (`mailto:`, `googlegmail://co?…` pour Gmail, cas UTC). Faire valider par Colin.
3. Plan de missions (`plans/2026-10-25-lot3-2-contenu.md`), en mode chef d'orchestre, puis exécution par sous-agents.
Effort conseillé pour la conversation suivante : **haut** (sécurité de l'écriture IMAP et du HTML).

## Fichiers clés
- `SPEC.md` § 16 (lot 3, décisions 16.1, révisions du 25/10) : base de la spec 3.2
- `serveur/lib/imap.php` (`imap_segments_commande`, `JR_IMAP_ELEMENT_FETCH`, `imap_lire_boite`) et `serveur/lib/imap_decodage.php` (extraits par partie, en-têtes)
- `site/js/ecrans/mails.js` : liste des boîtes, `rendreSection`, `carteMail`
- `RECETTE-LOT3.md` : recette restante du lot 3

## Pièges et points ouverts
- Garde-fou : toute commande citant `.env`, `config.php`, `secrets/`, un `grep -r` ou un script de mise en ligne passe entièrement par `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`. Un heredoc Python long avec ces mots peut être bloqué : écrire le script dans le scratchpad. MySQL : `pgrep -fl mysqld`.
- `site/sw.js` : `VERSION` = empreinte des fichiers, à refaire après tout changement dans `site/` (`php tests/run.php "service worker"` donne la valeur).
- `outils/deploy-ovh.sh` dit « En ligne. » dès que `base_ok` est vrai, sans comparer `version_base` au nombre de migrations locales (au 25/10 il a affiché 5 alors que 006 était attendue ; un appel suivant a donné 6). À corriger.
- `JR_VERSION` (`serveur/lib/amorce.php`) est resté à 0.2.0 depuis le lot 2.
- `PLAN.md` cite encore contact@ et « 5 boîtes » (document d'origine, laissé tel quel).
- Lot 2 encore ouvert : rappel « Dans 1 h » à recréer et contrôler ; vérifier que le brief du 25/10 (passage à l'heure d'hiver) n'a été ni doublé ni sauté.
- Adresse du webmail OVH (`LIENS.qyris`) à confirmer par Colin.
- Colin en Espagne du 26 au 30/10.
