# Passation · Jarvis Jr. · 2026-10-22 16:20

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Lot 2 (rappels) : finir la partie B du plan 3, c'est-à-dire mettre en ligne et faire la recette.

## Où on en est
- **Fait et vérifié** :
  - Plans 1 à 3 du lot 2 écrits et fusionnés dans `main` en local (dernier commit `8479456`), pas encore en ligne ni sur GitHub.
  - Plan 3, partie A : rappels manuels, capture « Rappel », bloc « Rappels du jour », Siri, `GET`/`POST reglages`, écran Réglages complet, mise en ligne préparée.
  - Tests : 247 PHP, 41 de modules, 73 d'écran. Vérification indépendante conforme.
- **Fait, pas encore vérifié** : rien n'a tourné sur OVH. Points à vérifier en ligne :
  - la transmission d'`Authorization` ;
  - `fastcgi_finish_request` ;
  - les migrations 003 à 005 au premier appel ;
  - le vecteur RFC 8291 sur PHP 8.4 ;
  - la réception sur l'iPhone.
- **En cours chez un sous-agent** : rien.
- Compte cron-job.org créé par Colin (fuseau Europe/Paris), aucune tâche réglée.

## Décisions prises
- Les sous-agents ne commitent pas. Claude commite après avoir lu chaque compte rendu.
- Siri :
  - jeton dont seule l'empreinte SHA-256 est gardée, mauvais jeton → 404 ;
  - 30 notes réussies par heure, même phrase ignorée pendant 2 min ;
  - réponses en texte brut.
- Heures du bloc « Rappels du jour » écrites « 17:30 », comme l'agenda.
- `POST reglages/rappels` refuse un objet invalide (400) ; `reglages_rappels_valides()` reste le filet du planificateur.
- Session déplacée de Moi vers Réglages.
- C'est Colin qui lance tout envoi vers OVH (Claude ne fait pas de SFTP).

## Prochaine étape
1. Ouvrir `RECETTE-LOT2.md` avec Colin. Il lance, dans le terminal de VS Code, la mise en ligne (partie 2), puis le diagnostic `outils/test-serveur/envoyer.sh --lot2` et `nettoyer.sh` (partie 3). Claude lit les résultats à travers le filtre.
2. Colin lance `outils/verifier-cron-en-ligne.sh` (attendu : 200, puis 404, puis 404).
3. Régler la tâche cron-job.org, et le cron d'OVH si l'offre le permet. Sur l'iPhone : notifications, notification de test, jeton Siri et raccourci.
4. Recette du SPEC § 15.9 sur 24 h. Le lendemain, Claude lit la table `envois` (pas de doublon, une seule carte Siri). Ensuite, envoyer `main` sur GitHub.

Effort conseillé : **moyen** (on guide et on lit des résultats, pas de conception).

## Fichiers clés
- `RECETTE-LOT2.md` : les commandes de Colin et les cases de la recette
- `plans/2026-10-22-lot2-3-rappels-siri.md` : décisions et partie B
- `SPEC.md` § 15.9 : définition de « lot 2 fini »
- `README.md` : section « Prochaine session »

## Pièges et points ouverts
- Le garde-fou bloque toute commande qui cite un script `deploy*`, `config.php`, `secrets/` ou MySQL. Il faut l'écrire `cd … && { … ; } 2>&1 | ~/.claude/hooks/redact-secrets`, avec le `cd` hors du groupe.
- Avec `--lot2`, le diagnostic signale que `config-test.php` est absent : c'est normal.
- Les libellés de cron-job.org et de l'espace client OVH dans la recette ont été écrits de mémoire et peuvent avoir changé.
- Après toute modification de `site/`, la `VERSION` de `site/sw.js` doit prendre la valeur donnée par `php tests/run.php`.
- À relire par Colin :
  - textes nouveaux du plan 3 (`CONTENU.md` § 5) ;
  - 20 répliques du brief (§ 5.1) ;
  - textes des plans 1 et 2.
- À trancher par Colin (README) :
  - le piège de « Terminer » ;
  - les chiffres des codes d'UV ;
  - l'effacement de la copie de l'iPhone au premier 401.
- Le contexte moyen de cette conversation était de 135k jetons, pour une cible de 80k : faire `/passation` dès que le plan est écrit.
