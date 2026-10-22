# Recette du lot 2 de Jarvis Jr.

Mise en ligne des notifications, du planificateur, des rappels manuels et de Siri, puis vérification pas à pas. Compte une heure environ. Les commandes se lancent dans le terminal de VS Code, depuis le dossier du projet :

```
cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr
```

Règle d'or : ne colle jamais un mot de passe ou un jeton dans une conversation avec Claude. Tout ce qui suit est fait pour que tu n'aies jamais à le faire.

## 1. Avant

Ce qui doit être déjà fait (par Claude, rien à lancer pour toi) :

- [ ] Les tests PHP et JS sont verts.
- [ ] La branche `lot2-3-rappels-siri` est fusionnée dans la branche principale.
- [ ] Claude te dit « prêt pour la mise en ligne ».

Ce que toi tu vérifies :

- [ ] MySQL tourne sur ton Mac (les tests en ont besoin). Si tu n'es pas sûr : `brew services run mysql@8.4`
- [ ] Tu as du réseau et tu n'es pas sur le Wi-Fi d'un endroit qui bloque le SFTP (port 22).

## 2. Mise en ligne

Une seule commande :

```
outils/deploy-ovh.sh
```

Elle fait six étapes et s'arrête à la moindre erreur :

1. **Tests** : tu dois voir « 247 réussi(s), 0 échec(s) » (ou plus), puis les tests de l'app. Au moindre échec, rien n'est envoyé.
2. **Configuration de production** : « config.php et trello-ids.json écrits ». Elle contient les clés du lot 2 (notifications, jeton du planificateur). Si une clé manque, le message dit laquelle (jamais sa valeur).
3. **Simulation** : la liste de ce qui va partir. Tu dois y voir de nouveaux fichiers dans `serveur/lib/`, `serveur/routes/`, `serveur/migrations/` (003, 004, 005), `serveur/cron-ovh.php`, et des fichiers dans `www/`.
4. **Question** : « Envoyer ? (o/N) ». Tape `o` puis Entrée.
5. **Envoi** : le serveur, la configuration, puis le site, et `sw.js` en tout dernier. Tu vois la liste de `cron-ovh.php` et des migrations sur l'hébergement.
6. **Vérification** : le script appelle `/api/sante`. Ce premier appel applique tout seul les migrations 003 à 005 (c'est le serveur qui s'en charge au premier appel, tu n'as rien d'autre à faire). Tu dois lire :

```
{"version_app":"...","version_base":5,"base_ok":true}
Les migrations en retard (003 à 005 pour le lot 2) se sont appliquées à cet appel : version_base = version attendue.
En ligne.
```

- [ ] `version_base` vaut 5 et `base_ok` vaut `true`.
- [ ] Le script se termine par « En ligne. »

Si ça échoue : copie le message (il ne contient aucun secret, tout passe par le filtre) et donne-le à Claude. Ne relance pas dix fois : OVH a déjà bloqué ton Mac une fois pour ça.

## 3. Diagnostic du serveur

Il prouve que le serveur OVH sait faire la cryptographie des notifications : PHP 8.4, le vecteur de test de la RFC 8291 refait octet pour octet, une signature ES256 vérifiée par `openssl_verify`, et `fastcgi_finish_request` (le planificateur répond tout de suite, puis travaille). Il n'affiche aucun secret.

**Envoyer** (n'envoie que le diagnostic, rien de l'app n'est touché) :

```
outils/test-serveur/envoyer.sh --lot2
```

Il affiche une ligne « Adresse du diagnostic : https://jr.qyris.fr/diag-xxxxxxxx.php ».

**Lire le résultat** : ouvre cette adresse dans Safari ou Chrome sur le Mac. Cherche le bloc `"lot2"`. Tu dois voir :

```
"php_8_4_ou_plus": true,
"vecteur_rfc8291_octet_pour_octet": true,
"signature_es256_openssl_verify": true,
"fastcgi_finish_request": true,
"TOUT_EST_BON": true
```

- [ ] `TOUT_EST_BON` vaut `true`.

Le bloc `"base"` peut annoncer une erreur « config-test.php » : c'est normal avec `--lot2`, la base est déjà vérifiée à l'étape 2. Si un des contrôles est à `false`, ne passe pas à la suite : dis-le à Claude.

**Nettoyer** (à faire tout de suite après la lecture) :

```
outils/test-serveur/nettoyer.sh
```

- [ ] Le script finit par « Fait » et le diagnostic n'apparaît plus dans la liste `www`.
- [ ] L'adresse du diagnostic donne maintenant une erreur ou une page introuvable.

## 4. Vérifier que le planificateur reçoit son jeton

Apache d'OVH ne transmet pas toujours l'en-tête `Authorization` à PHP. Le `.htaccess` contient la règle qui le recopie. Ce script le prouve en ligne :

```
outils/verifier-cron-en-ligne.sh
```

Il lit le jeton dans ton fichier de secrets, le met dans un fichier temporaire (effacé à la fin) et ne l'affiche jamais : il n'est ni dans la commande, ni dans l'historique du terminal. Tu dois voir :

```
Bon jeton      : 200 (attendu 200)
Mauvais jeton  : 404 (attendu 404)
Sans jeton     : 404 (attendu 404)
OK : Apache transmet bien Authorization.
```

- [ ] Les trois codes sont ceux attendus et le script finit par « OK ».

Si le bon jeton donne 404 : l'en-tête n'arrive pas jusqu'à PHP. Préviens Claude, ne continue pas vers cron-job.org.

## 5. cron-job.org (le planificateur principal)

C'est lui qui réveille Jarvis Jr. chaque minute. Ton compte existe déjà (fuseau Europe/Paris).

**Copier l'en-tête sans l'afficher.** Dans le terminal :

```
php -r 'require $argv[1]; echo "Bearer ", lire_env($argv[2])["CRON_JETON"];' outils/lib-env.php ../../secrets/jarvis-jr.env | pbcopy
```

Il ne s'affiche rien : c'est normal, « Bearer » suivi du jeton est dans le presse-papiers de ton Mac. Tu le colles à l'étape 5, puis tu vides le presse-papiers (dernier point de cette partie).

**Créer la tâche** sur https://console.cron-job.org :

1. Clique sur **Create cronjob**.
2. **Title** : `Jarvis Jr. planificateur`
3. **URL** : `https://jr.qyris.fr/api/cron`
4. **Execution schedule** : chaque minute (« Every 1 minute » ; si la liste propose un choix par heures et minutes, coche toutes les minutes).
5. Onglet **Advanced** :
   - **Request method** : `POST`
   - **Headers** : ajoute une ligne. Nom : `Authorization`. Valeur : colle (Cmd+V) le contenu du presse-papiers, qui commence par `Bearer ` suivi du jeton.
   - **Timeout** (délai) : 30 secondes. Le serveur répond tout de suite, le travail se fait après.
   - Laisse le corps de la requête vide.
6. Onglet **Notifications** : coche l'avertissement **par mail en cas d'échec** (« Notify me when the execution fails »), et si l'option existe, pas de mail à chaque succès.
7. Clique sur **Create**.
8. Retourne dans le terminal et vide le presse-papiers : `pbcopy < /dev/null`

Les noms des onglets peuvent un peu varier : l'important, c'est POST, l'en-tête `Authorization` et le « chaque minute ».

**Vérifier :**

- [ ] Dans cron-job.org, ouvre la tâche, puis l'historique (« History ») : après 2 minutes, les passages sont en succès (code 200).
- [ ] Dans Jarvis Jr., Réglages, la ligne du planificateur affiche « Dernier passage il y a 1 min. » (ou moins). Si tu lis « Le planificateur n'est pas encore lancé » après 3 minutes, regarde l'historique de cron-job.org : un 404 veut dire que l'en-tête est faux (refais l'étape de copie et recolle-le).
- [ ] Le presse-papiers est vidé.

## 6. Le cron d'OVH (secours, facultatif)

Si cron-job.org tombe, le cron d'OVH relance le même travail une fois par heure.

1. Espace client OVH, **Hébergements**, ton hébergement perso.
2. Onglet **Tâches planifiées - Cron** (selon l'offre, il peut s'appeler « Cron »). Clique sur **Créer une tâche planifiée**.
3. **Commande / fichier** : `jr.qyris.fr/serveur/cron-ovh.php` (chemin depuis la racine de l'hébergement, tel que le script de mise en ligne l'a envoyé).
4. **Version de PHP** : `8.4`.
5. **Fréquence** : 1 fois par heure.
6. Valide.

- [ ] La tâche est créée et active.

Si ton offre ne propose pas les tâches planifiées, ou refuse cette fréquence, on s'en passe : cron-job.org suffit. Ce n'est pas un blocage.

## 7. Sur l'iPhone

1. Ferme l'app Jarvis Jr. (balaye-la dans le sélecteur d'apps) et rouvre-la. La nouvelle version se charge. Si tu ne vois pas « Rappels » dans Réglages, ferme et rouvre une seconde fois.
2. **Réglages**, **Notifications** : touche **Activer les notifications** et accepte la demande d'iOS. L'état passe à « Actives sur cet iPhone ».
3. Touche **Envoyer une notification de test**. Elle arrive en quelques secondes.
4. **Réglages**, **Siri** : touche **Créer le jeton Siri**. Touche **Copier** tout de suite (il ne sera plus affiché).
5. Ouvre le **mode d'emploi** juste en dessous dans Réglages et suis les sept étapes pour créer le raccourci « Note pour Jarvis » dans l'app Raccourcis. Pour la valeur de l'en-tête, tape `Bearer`, une espace, puis colle le jeton.

- [ ] L'état des notifications indique « Actives sur cet iPhone ».
- [ ] La notification de test est arrivée (écran verrouillé aussi).
- [ ] Le raccourci « Note pour Jarvis » est créé.

## 8. Recette du lot 2 (SPEC § 15.9)

Le lot 2 est fini quand toutes ces cases sont cochées.

- [ ] **La notification de test arrive sur l'iPhone.** Réglages, Envoyer une notification de test : elle apparaît en moins de 10 secondes, app fermée.
- [ ] **Un rappel manuel « Dans 1 h » arrive à l'heure.** Dans la capture, choisis « Rappel », écris « Test rappel », touche « Dans 1 h ». Le bloc « Rappels du jour » de l'écran Aujourd'hui l'affiche avec son heure. À l'heure dite, la notification arrive (tolérance : 1 à 2 minutes). Tu peux aussi essayer d'annuler un rappel dans le bloc : il disparaît.
- [ ] **Le rappel d'un vrai cours arrive 15 minutes avant.** Le prochain jour de cours, vérifie que la notification arrive 15 minutes avant le début (le délai se règle dans Réglages, Rappels, Avant les cours). Compare avec l'heure du cours dans l'onglet Agenda.
- [ ] **Le brief du lendemain arrive à 7 h 30.** Le lendemain matin, la notification du brief arrive à 7 h 30 (heure réglable dans Réglages, Brief du matin, et jours choisis).
- [ ] **Une dictée Siri crée une carte une seule fois.** Dis « Dis Siri, note pour Jarvis », dicte une phrase courte. Siri répond. Vérifie dans l'onglet Tâches (ou dans Trello) qu'il y a une seule nouvelle carte avec cette phrase, pas deux. Refais l'essai une fois pour être sûr.
- [ ] **Aucun doublon dans `envois` après 24 h de planificateur.** Pendant la journée, tu ne dois jamais recevoir deux fois la même notification. Le lendemain, demande à Claude de contrôler la table `envois` (il le fait sans toucher à tes secrets) : aucune ligne en double.
- [ ] **Le planificateur reste vivant.** Réglages affiche « Dernier passage il y a 1 min. » à n'importe quel moment de la journée, sans alerte rouge.

Quand tout est coché, dis-le à Claude : il clôt le lot 2 (README, journal, Trello).
