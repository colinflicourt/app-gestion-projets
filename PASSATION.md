# Passation · Jarvis Jr. · 2026-10-22 16:40

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Lot 2 (rappels) : finir la recette du SPEC § 15.9, puis clore le lot.

## Où on en est
- **Fait et vérifié** (22/10, avec Colin) :
  - Mise en ligne de `main` (`8479456`) : `/api/sante` répond `version_app 0.2.0`, `version_base 5`, `base_ok true` (migrations 003 à 005 appliquées).
  - Diagnostic `--lot2` : `TOUT_EST_BON` (PHP 8.4.27 fpm, vecteur RFC 8291, ES256, `fastcgi_finish_request`), puis nettoyé.
  - `verifier-cron-en-ligne.sh` : 200 / 404 / 404, Apache transmet `Authorization`.
  - cron-job.org : tâche « Jarvis Jr. planificateur », POST chaque minute, passages en 200. Alertes : échec après 3, retour à la normale, désactivation, TLS 7 jours.
  - Cron OVH de secours créé : `jr.qyris.fr/serveur/cron-ovh.php`, PHP 8.4, toutes les heures, activé.
  - Siri : raccourci renommé **« Retiens pour Jarvis »** (« Note pour… » est intercepté par l'app Notes). Il crée bien une carte dans Perso. Les 3 cartes de test ont été archivées.
- **Fait, pas encore vérifié** :
  - Notifications activées sur l'iPhone et notification de test : Colin ne l'a pas confirmé explicitement.
  - Rappel manuel « Dans 1 h » : proposé, pas confirmé.
- **En cours chez un sous-agent** : rien.

## Décisions prises
- Celles de la passation précédente restent valables : les sous-agents ne commitent pas ; Colin lance tout envoi vers OVH.
- Nom du raccourci Siri : « Retiens pour Jarvis ».
- Le cron OVH n'envoie pas de mail de log (24 par jour). C'est cron-job.org qui prévient en cas de panne.

## Prochaine étape
1. Demander à Colin : notification de test reçue (aussi écran verrouillé) ? Rappel « Dans 1 h » arrivé à l'heure ? Brief du vendredi 23/10 arrivé à 7h30 ?
2. Contrôler la table `envois` sur OVH (pas de doublon, une seule carte par dictée Siri), à travers le filtre, sans afficher de secret.
3. Rappel de cours : pas de cours avant novembre (examens jusqu'au 24/10, vacances du 26 au 30/10). Claude a proposé un faux cours « Test Jarvis » dans l'agenda « UTC · Emploi du temps A26 », que Colin supprimerait ensuite. **Colin n'a pas encore répondu.** `rappels_cours` prend tout événement non « journée » de cet agenda.
4. Remplacer « Note pour Jarvis » par « Retiens pour Jarvis » dans `site/js/lib/textes.js` (`siriEtapes`), `RECETTE-LOT2.md` et `SPEC.md`, puis mettre à jour `VERSION` dans `site/sw.js`. Mise en ligne par Colin, à grouper avec la suivante.
5. Quand tout est coché : clore le lot 2 (README, HISTORY, Trello) et envoyer `main` sur GitHub.

Effort conseillé : **moyen**.

## Fichiers clés
- `RECETTE-LOT2.md` § 8 : les cases de la recette
- `serveur/lib/rappels.php` : règles des rappels (cours l. 108)
- `site/js/lib/textes.js` l. 236 : mode d'emploi Siri à renommer

## Pièges et points ouverts
- Garde-fou : une commande qui cite `serveur/lib`, un `grep -r` ou un script de mise en ligne doit être entièrement dans `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`. Un `cd` placé devant peut quand même être bloqué, utiliser des chemins absolus.
- Dans cron-job.org, l'URL doit être en `https://` (en `http://`, on obtient un 301, déjà corrigé).
- Passage à l'heure d'hiver dans la nuit du 24 au 25/10 : vérifier qu'aucun rappel n'est envoyé deux fois ni sauté.
- Toujours à faire par Colin (README) : relire les textes (CONTENU.md § 5 et § 5.1) ; trancher le piège de « Terminer », les chiffres des codes d'UV et l'effacement de la copie de l'iPhone au premier 401.
