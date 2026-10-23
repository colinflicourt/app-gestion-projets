# Recette du lot 3 de Jarvis Jr.

Mise en ligne de l'onglet Mails (Gmail, iCloud, colin@ et contact@ en lecture seule), puis vérification pas à pas. Compte une heure et demie environ, plus le temps de créer les mots de passe. Les commandes se lancent dans le terminal de VS Code, depuis le dossier du projet :

```
cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr
```

Règles d'or :

- Ne colle jamais un mot de passe dans une conversation avec Claude. Tu les saisis seulement dans Réglages de l'app, sur l'iPhone.
- Junior ne fait que lire : il ne marque jamais un mail comme lu, ne supprime rien, n'envoie rien. La recette le vérifie à la main (partie 7).

## 1. Préparatifs (toi, avant la mise en ligne)

Ils bloquent les tests réels, pas la mise en ligne elle-même. Compte 30 à 45 minutes.

### 1.1 Rediriger la boîte UTC vers Gmail

- [ ] Connecte-toi au webmail UTC, ouvre ses préférences (rubrique transfert ou redirection) et active le transfert de ta boîte `@etu.utc.fr` vers `colin.flicourt@gmail.com`.
- [ ] Regarde s'il existe une option pour garder une copie sur le serveur de l'UTC (ou, à l'inverse, une case « ne pas conserver de copie » à laisser décochée). Choisis de garder la copie : tes mails UTC restent alors aussi chez l'UTC. Si l'option n'existe pas, note-le : ce n'est pas bloquant.
- [ ] Envoie-toi un mail de test sur ton adresse `@etu.utc.fr` (depuis un autre compte, par exemple iCloud) avec l'objet « Test UTC ». Vérifie qu'il arrive dans Gmail (dossier Réception, pas Spam). Garde-le : il sert à régler la règle UTC (partie 5).

### 1.2 Mot de passe d'application Gmail

- [ ] Compte Google, Sécurité, active la **validation en deux étapes** si ce n'est pas fait.
- [ ] Puis Sécurité, **Mots de passe des applications** (si tu ne le trouves pas, cherche « mots de passe des applications » dans la barre de recherche du compte Google). Nom : `Jarvis Jr.`
- [ ] Google affiche 16 lettres. Garde la page ouverte, ou colle-les dans les Notes de l'iPhone le temps de la saisie (partie 4). Elles ne seront plus affichées ensuite.

### 1.3 Mot de passe d'application iCloud

- [ ] Compte Apple (account.apple.com), Connexion et sécurité, **Mots de passe pour app**. Nom : `Jarvis Jr.`
- [ ] Garde le mot de passe créé (format `xxxx-xxxx-xxxx-xxxx`) sous la main pour la partie 4.

### 1.4 Mots de passe OVH de colin@ et contact@

- [ ] Aie sous la main le mot de passe de `colin@qyris.fr` (celui de son webmail OVH).
- [ ] Idem pour `contact@qyris.fr`. Cette boîte n'a jamais été testée : ouvre son webmail OVH et connecte-toi une fois, pour être sûr que le mot de passe est bon. Sinon, change-le dans l'espace client OVH (Emails, la boîte, Changer le mot de passe).
- [ ] Note l'**adresse exacte du webmail OVH** que tu utilises (celle que tu tapes dans Safari). Elle sert à la partie 6.

## 2. Mise en ligne

Avant : les tests PHP et JS sont verts, la branche `lot3-mails` est fusionnée dans la branche principale et Claude te dit « prêt pour la mise en ligne » (rien à lancer pour toi). MySQL tourne sur ton Mac (sinon : `brew services run mysql@8.4`).

Une seule commande :

```
outils/deploy-ovh.sh
```

Elle fait six étapes et s'arrête à la moindre erreur :

1. **Tests** : tu dois voir « 351 réussi(s), 0 échec(s) » (ou plus), puis les tests de l'app.
2. **Configuration de production** : « config.php et trello-ids.json écrits ».
3. **Simulation** : la liste de ce qui va partir. Tu dois y voir `serveur/lib/imap.php`, `imap_decodage.php`, `mails.php`, `tri_mails.php`, les routes des mails, la migration `006_mails.sql`, et des fichiers dans `www/` (dont `js/ecrans/mails.js`).
4. **Question** : « Envoyer ? (o/N) ». Tape `o` puis Entrée.
5. **Envoi** : le serveur et sa configuration, puis le site, et `sw.js` en tout dernier.
6. **Vérification** : le premier appel à l'API applique tout seul la migration 006 (rien d'autre à faire). Tu dois lire :

```
{"version_app":"...","version_base":6,"base_ok":true}
Les migrations en retard (003 à 006 pour les lots 2 et 3) se sont appliquées à cet appel : version_base = version attendue.
En ligne.
```

- [ ] `version_base` vaut 6 et `base_ok` vaut `true`.
- [ ] Le script se termine par « En ligne. »

Si ça échoue : copie le message (il ne contient aucun secret) et donne-le à Claude. Ne relance pas dix fois : OVH a déjà bloqué ton Mac une fois pour ça.

## 3. Sur l'iPhone : charger la nouvelle version

- [ ] Ferme l'app Jarvis Jr. (balaye-la dans le sélecteur d'apps) et rouvre-la. Si l'onglet **Mails** n'apparaît pas dans la barre du bas, ferme et rouvre une seconde fois.
- [ ] La barre du bas affiche Aujourd'hui, Agenda, Tâches, Mails, Moi.

## 4. Brancher les 4 comptes (Réglages, Comptes mail)

Pour chaque compte : l'identifiant est pré-rempli, tu saisis le mot de passe, tu touches **Enregistrer**. L'app teste la connexion avant de garder quoi que ce soit : si le mot de passe est faux, rien n'est enregistré et le message de la boîte s'affiche. Il faut du réseau.

- [ ] **Gmail** : mot de passe d'application de la partie 1.2 (16 lettres). L'état passe à « Connecté, vérifié à 14 h 32 » (avec l'heure réelle).
- [ ] **iCloud** : mot de passe d'application de la partie 1.3.
- [ ] **colin@qyris.fr** : mot de passe OVH.
- [ ] **contact@qyris.fr** : mot de passe OVH.
- [ ] Les 4 lignes affichent « Connecté, vérifié à … ». Le champ mot de passe reste vide à la réouverture (c'est voulu, l'app ne le revoit jamais).

Si une ligne dit « Mot de passe refusé » : refais le mot de passe d'application (Gmail et iCloud n'acceptent pas le mot de passe habituel) et recommence.

## 5. Régler la règle UTC avec les vrais en-têtes

La règle qui range un mail dans la section UTC est volontairement large (tout mail où `utc.fr` apparaît dans un destinataire ou un passage `Received`). Il faut la confirmer sur de vrais mails : le diagnostic affiche les en-têtes des 5 derniers mails de Gmail, sans objet ni extrait ni mot de passe.

1. **Envoie-toi un nouveau mail** sur ton adresse `@etu.utc.fr` (objet « Test UTC 2 »), et attends qu'il soit dans Gmail. Il doit être l'un des 5 derniers mails de la Réception.
2. **Envoyer le diagnostic** (n'envoie que lui, rien de l'app n'est touché) :

```
outils/test-serveur/envoyer.sh --lot2
```

Il affiche « Adresse du diagnostic : https://jr.qyris.fr/diag-xxxxxxxx.php ».

3. **Lire le résultat** : ouvre cette adresse dans Safari ou Chrome sur le Mac et cherche le bloc `"entetes_gmail"`. Il contient, pour chacun des 5 derniers mails : `uid`, `from`, `to`, `cc`, `delivered-to`, `x-forwarded-to`, `received`, `list-id`, et `utc` (`true` si la règle actuelle le range dans UTC).
   - Si le bloc dit `"etat": "non_connecte"` : Gmail n'est pas enregistré (partie 4).
   - Si `"etat": "erreur"` : le code (`refus`, `injoignable`, `delai`) dit pourquoi. Reteste le compte dans Réglages.
4. **Donne ce bloc à Claude** (copie-colle seulement `entetes_gmail`). Il y a ton adresse d'expéditeur et des lignes `Received`, mais aucun mot de passe. Claude relit les en-têtes et ajuste `JR_MARQUEURS_UTC` dans `serveur/lib/tri_mails.php` si besoin : dans ce cas, une petite mission, puis une nouvelle mise en ligne (`outils/deploy-ovh.sh`).
5. **Nettoyer** (à faire tout de suite après la lecture) :

```
outils/test-serveur/nettoyer.sh
```

- [ ] Le bloc `entetes_gmail` a été lu avec le mail de test UTC dedans, et `utc` vaut `true` pour lui, `false` pour un mail Gmail ordinaire.
- [ ] Si Claude a ajusté la règle : nouvelle mise en ligne faite et diagnostic relancé, même résultat.
- [ ] `nettoyer.sh` finit par « Fait » et l'adresse du diagnostic donne maintenant une erreur ou une page introuvable.

Le bloc `"base"` du diagnostic peut annoncer une erreur « config-test.php » : c'est normal avec `--lot2`.

## 6. Adresse du webmail OVH à confirmer

Le bouton « Ouvrir le webmail OVH » de la section QYRIS pointe pour l'instant vers `https://www.ovhcloud.com/fr/mail/` (page de présentation, pas forcément la boîte). 

- [ ] Dans l'onglet Mails, touche « Ouvrir le webmail OVH » : tu arrives sur la connexion au webmail, pas sur une page de pub. Sinon, donne à Claude l'adresse notée à la partie 1.4 : il corrige `LIENS.qyris` dans `site/js/ecrans/mails.js`, puis nouvelle mise en ligne.
- [ ] « Ouvrir dans Gmail » ouvre l'app Gmail (ou, à défaut, la page Gmail après un court instant).
- [ ] « Ouvrir dans iCloud Mail » ouvre iCloud Mail.

## 7. Recette du lot 3 (SPEC § 16.10)

Le lot 3 est fini quand toutes ces cases sont cochées.

- [ ] **Les 4 comptes sont connectés depuis l'iPhone.** Réglages, Comptes mail : 4 lignes « Connecté, vérifié à … ».
- [ ] **Un mail envoyé à l'adresse `@etu.utc.fr` apparaît dans la section UTC.** Onglet Mails : « Test UTC 2 » est dans la section UTC (en tête de la liste, mis en évidence comme non lu), et non dans la section Gmail.
- [ ] **Une newsletter est repliée dans les pubs et ne compte pas dans les non lus.** Repère une vraie newsletter non lue dans une de tes boîtes : elle est sous « + N pubs » (repliée), et le compteur de non lus de la section ne la compte pas. Dans Aujourd'hui, le bloc Mails ne la compte pas non plus. Essaie « Ce n'est pas une pub » sur une pub : elle remonte dans la liste. Puis « C'est une pub » sur un mail ordinaire d'un expéditeur : il descend dans les pubs. (Un mail de la section UTC n'est jamais une pub.)
- [ ] **Un mail non lu reste non lu après que Junior l'a affiché.** Vérifié à la main, un webmail à la fois, avec un mail réellement non lu dans chaque boîte :
  - [ ] **Gmail** : repère dans Gmail un mail non lu (en gras). Ouvre l'onglet Mails de Jarvis Jr. ; le mail s'affiche. Retourne dans Gmail (page ou app) : il est toujours en gras.
  - [ ] **iCloud** : même épreuve avec un mail non lu d'iCloud Mail (pastille bleue). Après l'affichage dans Jarvis Jr., la pastille est toujours là.
  - [ ] **Webmail OVH** : même épreuve avec un mail non lu de colin@ ou contact@ (envoie-t'en un depuis un autre compte). Après l'affichage dans Jarvis Jr., il est toujours non lu dans le webmail OVH.
- [ ] **Le brief du lendemain annonce le bon nombre de mails non lus.** Le lendemain matin, la notification du brief (7 h 30 par défaut) se termine par « N mails non lus. » (ou « 1 mail non lu. »), et N correspond au total hors pubs du bloc Mails d'Aujourd'hui avant le brief. Si tout est lu : aucune phrase sur les mails.
- [ ] **Un mot de passe faux affiche le message d'erreur de sa boîte sans gêner les autres.** Dans Réglages, change volontairement le mot de passe d'iCloud, enregistre (refusé, rien n'est gardé), puis supprime le compte iCloud et reconnecte-le avec le bon. Plus parlant : si une boîte tombe en erreur un jour, sa section affiche son message d'erreur et les autres sections s'affichent normalement.
- [ ] **Hors ligne** : en mode avion, l'onglet Mails affiche la dernière copie avec « à jour il y a … », et Réglages dit « Il faut du réseau pour tester une boîte. » quand tu essaies de tester un compte.
- [ ] **Les heures** s'affichent à l'heure de Paris : « 14 h 32 » pour aujourd'hui, « hier », « lun. 19/10 » pour plus vieux.

Quand tout est coché, dis-le à Claude : il clôt le lot 3 (README, journal, Trello).
