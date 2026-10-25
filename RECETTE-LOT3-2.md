# Recette du lot 3.2 de Jarvis Jr.

Contenu des mails : ouverture d'un mail (lu dans les boîtes), images masquées, pièces jointes, liens, réponse. Le lot 3 est déjà en ligne : cette mise en ligne ajoute le lot 3.2 (version 0.3.2) et l'exception du `.htaccess` pour le corps des mails. Les commandes se lancent dans le terminal de VS Code.

Règles d'or :

- Ne colle jamais un mot de passe ni un jeton dans une conversation avec Claude.
- Tu lances la mise en ligne toi-même. Claude ne lance rien vers OVH.

## 1. Mise en ligne

Colin lance cette commande dans le terminal de VS Code, depuis le dossier du projet :

```
cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr
outils/deploy-ovh.sh
```

Elle fait six étapes et s'arrête à la moindre erreur. Attends la dernière ligne :

- Si elle affiche « En ligne. », la mise en ligne est finie, passe à la partie 2.
- Si elle affiche « Attention : version_base vaut X, attendu Y. Ne pas considérer la mise en ligne comme finie. », ne continue pas : copie le message à Claude.

## 2. Vérification des en-têtes (sur ton Mac, par GET)

Les commandes utilisent GET et non HEAD (`curl -sI`) : la condition `<If>` du `.htaccess` ne prévoit pas HEAD, la réponse pourrait montrer deux CSP et fausser la vérification.

Vérification de l'app :

```
curl -s -D - -o /dev/null https://jr.qyris.fr/
```

Attendu : une seule ligne `Content-Security-Policy`, celle de l'app :

```
default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; font-src 'self'; manifest-src 'self'; worker-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'
```

Vérification du corps d'un mail (sans images) :

```
curl -s -D - -o /dev/null 'https://jr.qyris.fr/api/mails/corps?compte=gmail&uid=1'
```

Attendu : statut 401 (pas de session), et une seule ligne `Content-Security-Policy`, celle du corps, qui contient `frame-ancestors 'self'` :

```
default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox allow-same-origin allow-popups allow-popups-to-escape-sandbox
```

Coller la sortie complète des deux commandes à Claude.

## 3. Si tout le site rend 500 juste après la mise en ligne

Cause possible : OVH refuse le bloc `<If>` du `.htaccess` (commit `cda5bb5`). Ne relance pas la mise en ligne tant que Claude n'a pas corrigé.

Retour arrière (Colin lance les deux commandes dans le terminal de VS Code, depuis le dossier du projet). La première récupère le `.htaccess` d'avant le commit `cda5bb5` :

```
cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr && git show 78cc357:site/.htaccess > /tmp/htaccess-precedent
```

La seconde l'envoie à sa place sur OVH, avec le même outil SFTP et les mêmes identifiants que `deploy-ovh.sh` (rien en clair dans la commande) :

```
{ outils/ovh-sftp.sh "put /tmp/htaccess-precedent -o jr.qyris.fr/www/.htaccess" ; } 2>&1 | ~/.claude/hooks/redact-secrets
```

Ensuite, vérifie que le site répond :

```
curl -s -o /dev/null -w '%{http_code}\n' https://jr.qyris.fr/
```

Attendu : `200`.

Conséquences à connaître :

- Le corps d'un mail n'a plus sa propre Content-Security-Policy (celle de l'app l'empêche de s'afficher dans le cadre) : n'ouvre aucun mail dans Junior avant la correction, et préviens Claude.
- Ne relance pas `outils/deploy-ovh.sh` avant la correction : il renverrait le `.htaccess` avec le bloc `<If>`.

## 4. Cases à cocher (Colin, sur l'iPhone et dans les boîtes)

Critères du lot 3.2 (SPEC § 17.10) :

- [ ] un mail non lu ouvert dans Junior apparaît lu dans Gmail, iCloud, le webmail UTC et le webmail OVH, **et les autres non lus restent non lus**
- [ ] le PDF d'un mail de l'UTC s'ouvre, et Colin revient dans Junior
- [ ] une newsletter est lisible, sans images distantes jusqu'au bouton
- [ ] « Répondre » ouvre la bonne app, pré-remplie, pour chacune des 4 boîtes

Essais sur l'iPhone (app installée sur l'écran d'accueil) :

- [ ] Ouvrir une pièce jointe (PDF) puis revenir dans Junior
- [ ] Toucher un lien dans un mail, il s'ouvre dans Safari, puis revenir dans Junior

Recette sur l'iPhone à partir du 31/10.

## 5. Si la pièce jointe ne s'ouvre pas ou si on ne revient pas dans Junior

Décision 11 : les pièces jointes ne sont peut-être pas ouvrables comme cela sur iPhone. Note ce qui se passe, précisément :

- écran blanc, ou écran qui ne change pas
- téléchargement sans ouverture
- rien ne s'affiche, ou une erreur (texte exact)
- ouverture bien faite, mais pas de retour dans Junior à la fermeture (l'app reste sur le document)

Dis-le à Claude. Il lancera une petite mission (`fetch` puis `navigator.share({files})`, ou un visualiseur `Blob` pour les PDF et les images), puis une nouvelle mise en ligne par la commande de la partie 1.
