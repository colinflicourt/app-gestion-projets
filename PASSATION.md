# Passation · Jarvis Jr. · 2026-10-25 21:55

## Objectif
Jarvis Jr. (jr.qyris.fr), l'app perso d'organisation de Colin sur iPhone. Chantier : **lot 4.1, moteur d'XP et son affichage**. Code fini, reste la mise en ligne et la recette.

## Où on en est
- Fait et vérifié : M1 à M7 du plan `plans/2026-10-25-lot4-1-xp.md`. M5 célébrations (`05acb43`) : niveau en plein écran couleur du domaine (fermeture seule à 4,5 s), promotion et récapitulatif plein écran jusqu'au toucher, joker en bandeau (5 s), mémo `celebrations` dans IndexedDB écrit avant l'affichage ; Annuler d'une carte éclair répondue rend l'éclair payé. M6 (`7bdc73e`) : `JR_VERSION` 0.4.1, `RECETTE-LOT4-1.md`. M7 : 6 critères sur 6 conformes (aucune XP stockée, console propre, pas de secret ni de tiret long).
- Fusion `lot4-1-xp` dans `main` (`eb03b67`), poussée sur GitHub. Tests PHP 534, JS 74 (relancés sur `main`), écran 145 (sur la branche, identique).
- **Rien n'est en ligne** : la version en ligne est 0.3.2.
- En cours chez un sous-agent : rien.

## Décisions prises
- Barème C et les 13 décisions du plan font foi. Textes des célébrations conformes mot pour mot au § Textes du plan (seul écart : espace insécable avant « : » du récapitulatif, et bouton « Fermer » en aria-label).
- Décision 9 appliquée telle quelle : célébré si `emis_le > emis_le_max` et `cle` non vue.

## Prochaine étape
**Mise à jour du 29/10 :** lot 4.1 en ligne et recetté par Colin sur l'iPhone (cases 2 à 6 conformes ; case 1 : récapitulatif affiché une fois mais fermé aussitôt par un toucher involontaire). Ajouté et en ligne en 0.4.2 : « Revoir le récapitulatif » dans Moi (dernier récapitulatif gardé dans le mémo `celebrations.dernier_recap`, sinon bilan de tout l'historique). Élément Trello coché.
1. Spec du lot 4.2 (boss), à valider par Colin, en y incluant la protection d'1 s contre les touchers (voir Pièges).
2. Revue de régulation après 2 à 3 semaines d'usage (`GET xp/export`), vers mi-novembre.
Effort conseillé pour la conversation suivante : **moyen**.

## Fichiers clés
- `RECETTE-LOT4-1.md` : commandes et cases de la recette
- `plans/2026-10-25-lot4-1-xp.md` : Partie C (mise en ligne et recette)
- `outils/deploy-ovh.sh` : script de mise en ligne (lancé par Colin seulement)

## Pièges et points ouverts
- **Colin en Espagne du 26 au 30/10**, médian NF93 le lun 2/11 à 7h45 : créneau court pour la mise en ligne.
- Les envois SFTP vers OVH par Claude sont refusés : Colin lance, Claude lit la sortie. `curl -s -D - -o /dev/null`, jamais `curl -sI`. Toute commande qui cite `.env`, `config.php`, `secrets/`, `deploy`, `sftp`, Trello ou `grep -r` entièrement dans `{ … ; } 2>&1 | ~/.claude/hooks/redact-secrets`.
- Tests des célébrations : le sous-agent ne les a pas vus échouer avant d'écrire le code (sauf le test d'Annuler éclair). Ils passent, preuve de rouge manquante.
- Si les seuils de niveau sont un jour baissés, un niveau atteint pour la première fois par la baisse peut avoir un `emis_le` antérieur à `emis_le_max` et ne pas être célébré. À regarder à la revue de régulation.
- Cadre noir du focus sur les captures plein écran (focus programmatique du bouton Fermer) : à vérifier sur l'iPhone, normalement invisible au toucher.
- Les célébrations ne partent qu'après une lecture de données ou une réponse d'écriture, pas sur la copie locale du démarrage.
- Coût : chaque écriture rejoue 2 fois toutes les actions. À réduire si ça rame.
- Pour 4.3 : seul Études atteint le niveau 13 dans l'année, à trancher avec les cartes de niveau.
- **À faire avec le lot 4.2** (demande de Colin, 29/10) : ignorer les touchers pendant environ 1 s après l'apparition d'une célébration plein écran. À la recette, le récapitulatif s'est fermé aussitôt, probablement sur un toucher involontaire (Colin pense avoir touché). Le bouton Fermer couvre tout l'écran.
- Hors lot 4 : rappel « Dans 1 h » et brief à l'heure d'hiver (lot 2) à contrôler.
