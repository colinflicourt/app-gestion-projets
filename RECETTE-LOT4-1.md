# Recette du lot 4.1 de Jarvis Jr.

XP, tranches et grades, série et jokers, passages de niveau, récapitulatif. Cette mise en ligne ajoute le lot 4.1 (version 0.4.1). Le lot 3.2 est déjà en ligne. Les commandes se lancent dans le terminal de VS Code.

Règles d'or :

- Ne colle jamais un mot de passe ni un jeton dans une conversation avec Claude.
- Tu lances la mise en ligne toi-même. Claude ne lance rien vers OVH.
- Mise en ligne à partir du 31/10 (créneau court, voir le plan).

## 1. Mise en ligne

Colin lance cette commande dans le terminal de VS Code, depuis le dossier du projet :

```
cd ~/Desktop/jarvis-starter-kit/projects/jarvis-jr
outils/deploy-ovh.sh
```

Elle fait six étapes et s'arrête à la moindre erreur. Attends la dernière ligne :

- Si elle affiche « En ligne. », la mise en ligne est finie, passe à la partie 2.
- Si elle affiche « Attention : version_base vaut X, attendu Y. Ne pas considérer la mise en ligne comme finie. », ne continue pas : copie le message à Claude.

## 2. Vérification de la version (sur ton Mac, par GET)

La commande utilise GET et non HEAD (`curl -sI`).

```
curl -s -D - -o /dev/null https://jr.qyris.fr/api/sante
```

Attendu : statut `200` dans les en-têtes.

Le contenu du JSON n'apparaît pas avec `-o /dev/null`. Lance donc aussi :

```
curl -s https://jr.qyris.fr/api/sante
```

Attendu, dans le JSON :

- `"version_app":"0.4.1"`
- `"version_base":6`
- `"base_ok":true`

Coller la sortie des deux commandes à Claude.

## 3. Si le site rend 500 juste après la mise en ligne

Ne relance pas la mise en ligne tant que Claude n'a pas regardé. Copie le message d'erreur exact à Claude.

Retour arrière : Claude te donnera les commandes exactes au cas par cas, à lancer toi-même dans le terminal de VS Code, depuis le dossier du projet.

## 4. Cases à cocher : critères du SPEC § 18.7 (Colin, sur l'iPhone)

- [ ] la première ouverture montre le récapitulatif depuis le 10/10, une seule fois
- [ ] une carte terminée affiche son XP et la tranche bouge
- [ ] « Annuler » retire cette carte (XP et tranche reviennent en arrière)
- [ ] Moi montre grade, niveaux et série cohérents
- [ ] une carte terminée dans l'app Trello est comptée au rafraîchissement suivant

## 5. Cases à cocher : essais complémentaires (Colin, sur l'iPhone)

- [ ] Terminer une carte puis toucher « Annuler » : la carte revient ouverte, l'XP est retiré
- [ ] cocher une étape puis la décocher : l'XP de l'étape est gagné puis retiré, sans reste
- [ ] carte terminée dans l'app Trello : elle est comptée après rafraîchissement (XP et tranche)
- [ ] fermer Junior puis le relancer : aucune célébration ne se rejoue (ni passage de niveau, ni promotion)
- [ ] le récapitulatif n'apparaît qu'à la première ouverture ; à la suivante, il ne revient pas

## 6. Si quelque chose ne va pas

Note ce qui se passe, précisément : l'écran, l'action faite juste avant, le texte exact affiché, et si l'XP affiché dans Moi est différent de celui de la tranche. Dis-le à Claude avant toute nouvelle mise en ligne.

## 7. Fin du lot

Le lot 4.1 est fini quand toutes les cases des parties 4 et 5 sont cochées. Ensuite, première revue de régulation après deux à trois semaines d'usage (SPEC § 18.10, export `GET xp/export`).
