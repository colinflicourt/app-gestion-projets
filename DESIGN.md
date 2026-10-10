# Jarvis Jr. : le système visuel

Le monde visuel de l'app, tel que le lot 1 l'a construit dans `site/css/app.css`. Il sert de référence aux lots 2 à 4 (rappels, mails, fun). Contrat d'origine : `.impeccable/surfaces/site-index-html.md`. Maquette validée par Colin : v28 (https://claude.ai/artifact/ACPbctYmzhyqJrQk4ou4Nj).

## 1. Le monde

La journée se pilote comme la régie du Cabaret UTC : un écran de conduite, quatre tranches, un seul GO. Colin voit ce qui est en scène et ce qui suit, appuie sur GO et part sur l'étape proposée.

Refusé :
- la liste de cartes blanches arrondies des apps d'organisation ;
- les anneaux de progression, les dégradés de surface ;
- le cliché IA ou hacker : vert sur noir, néon, halo, hologramme ;
- une carte à bord gauche coloré.

## 2. Les jetons

### Couleurs

| Jeton | Valeur | Rôle |
|---|---|---|
| `--graphite` | #121417 | fond de l'app |
| `--panneau` | #1E2228 | panneaux de console, cartes, feuille |
| `--encre` | #0A0B0D | fond de l'afficheur |
| `--console` | #2A2F37 | boutons de console non allumés, bandeaux |
| `--contour` | #3A4048 | contours de champs et de boutons, graduations |
| `--regle` | #2A2F37 | règle graduée sous les titres de section |
| `--texte` | #ECEAE4 | texte principal |
| `--texte-2` | #9EA3A8 | texte secondaire, légendes |
| `--texte-3` | #8A8F96 | ce qui est passé (JOUÉ, heures passées), sur graphite seulement |
| `--ambre` | #FFB020 | l'afficheur et l'unique élément allumé |
| `--ambre-doux` | #B98222 | texte second de l'afficheur, GO éteint |
| `--ambre-sombre` / `--ambre-trait` | #4A3814 / #2A2416 | contours de voyant, trait de l'afficheur, attente du tirage |
| `--scotch` | #EDE6D6 | scotch des tranches, onglet actif, filtre choisi, +, messages |
| `--rouge` | #E5484D | pastilles d'alerte (pas de texte) |
| `--rouge-texte` | #FF6B70 | texte d'alerte sur graphite et panneaux |
| `--qyris` `--asso` `--etudes` `--perso` | #3DD68C #A97BFF #6FB0FF #FF7AC6 | une couleur franche par domaine, posée par la classe `d-…` dans `--domaine` |

Pas d'orange : trop proche du rouge d'alerte.

### Polices

Toutes hébergées dans `site/fonts/` (sous-ensemble latin), licences dans `site/fonts/licences/`.
- **Archivo** (OFL, axes de graisse et de largeur) : le texte, les titres en largeur 75 à 90 %, GO et les boutons allumés en 75 %, graisse 800.
- **Martian Mono** (OFL) : l'afficheur, les chiffres (toujours `tabular-nums`), les étiquettes en capitales espacées, les heures.
- **Permanent Marker** (Apache 2.0) : le scotch seulement.

### Taille de texte

`html` prend `font: -apple-system-body` (17 px à la taille standard de l'iPhone). `--px` vaut `1rem / 17` : un texte écrit `calc(16 * var(--px))` fait 16 px à la taille standard et suit le réglage de l'iPhone. Le texte courant, les titres d'écran, les boutons et les champs suivent ce réglage. L'afficheur, le scotch, les voyants et les tampons gardent des tailles fixes en px : ce sont des pièces de console dessinées à leur taille.

### Rayons et ombres

- Rayons : 14 px pour les panneaux et les cuvettes, 10 px pour les boutons et les champs, 8 px pour l'afficheur et les bandeaux, 3 à 4 px pour les voyants et les tampons, 22 px en haut de la feuille.
- `--ombre-panneau` : liseré clair en haut, ombre portée douce (0 8 px 20 px).
- `--ombre-bouton` : liseré clair, fond enfoncé de 3 px, ombre portée de 4 px.
- `--ombre-allume` : la même chose en plus marqué, pour l'élément allumé.
- L'afficheur et les cuvettes sont creusés (ombre intérieure). Aucune ombre sans décalage, aucun halo coloré.

## 3. Les pièces

- **Console** (`.console`) : un panneau qui porte l'afficheur et la ligne de talkback de Junior.
- **Afficheur** (`.afficheur`) : fond encre, texte ambre. Une date en capitales, puis des lignes `voyant + titre + cote` séparées par un trait ambre sombre, et les chiffres géants (`.chiffres`, 56 px, Martian Mono étroit) : heure et salle lisibles bras tendu. Un nom de cours (`.afficheur-titre-court`) garde sa ligne, c'est la cote qui passe à la ligne.
- **Voyant** (`.voyant`) : EN SCÈNE, STANDBY, PROPOSITION. Éteint : contour ambre sombre. Allumé : fond ambre, texte encre. Crème : sous le pli, pour le cours suivant.
- **Tampon** (`.tampon`) : FAIT, JOUÉ. Archivo étroit 800 en capitales, cadre de 2 px, penché d'un angle stable tiré de la clé (`angle()`), qui frappe en 180 ms. FAIT prend la couleur du domaine, JOUÉ le gris de ce qui est passé. Rien ne disparaît : ce qui est fait reste visible, tamponné.
- **Scotch** (`.scotch`) : bande crème écrite au marqueur, penchée, pour le nom des tranches, des domaines de la feuille « Noter » et les codes d'UV dans l'Agenda.
- **Tranche** (`.tranche`) : une par domaine. Scotch, compteur en chiffres de la couleur du domaine, légende, alerte (point rouge et « 1 en retard », ou anneau et « médian J-7 »), fader à graduations avec sa poignée dans la couleur du domaine. Au lot 4, la poignée porte le niveau d'XP du domaine.
- **GO et sa cuvette** (`.go`, `.cuvette`) : le bouton allumé d'Aujourd'hui, dans un creux graphite. Pendant un cours, GO s'éteint (fond console, texte ambre doux). Sous le pli, un petit GO carré dans la barre compacte.
- **Boutons de console** (`.bouton`) : fond console, liseré, fond enfoncé. **Allumé** (`.bouton-allume`) : ambre, l'unique de l'écran (Terminer, C'est parti, Ajouter, Entrer). **Contour** (`.bouton-contour`) : action secondaire sortante (Ouvrir dans Trello). **Carré** (`.bouton-carre`) : retour, fermer, jours précédent et suivant.
- **Carte** (`.carte-ligne`, `.carte-ouverte`) : panneau, puce de domaine (carré de couleur et nom en mono), titre, avancement `2/4`, échéance ou retard en rouge. Pas de bord coloré.
- **Étape** (`.etape`) : case à cocher dans une cible de 48 px, ou tampon FAIT une fois cochée ; « en attente du réseau » tant que l'écriture n'est pas partie.
- **Feuille** (`.feuille`) : monte du bas sur un voile graphite, poignée, titre, champ à caret ambre, scotch des domaines, Urgent, échéance, Ajouter allumé.
- **Bandeaux d'état** (`.statut`) : en haut, en mono, sur panneau : hors ligne avec l'âge des données, Trello ou Google qui ne répondent pas, notes en attente, Google déconnecté avec « Reconnecter ».
- **Messages** (`.message`) : bande scotch en bas, au-dessus des onglets, avec une action (Annuler) de 44 px.
- **Onglets** : 4 onglets et le + en scotch, l'onglet actif souligné de scotch.

## 4. Les règles

- **Un seul élément allumé par écran**, en ambre plein : GO sur Aujourd'hui, C'est parti après le tirage, Terminer sur une carte, Ajouter dans la feuille. L'afficheur est ambre par nature et ne compte pas.
- **L'ambre est réservé** à l'afficheur et à l'élément allumé. Le reste de l'app est graphite, crème et couleurs de domaine.
- **Le rouge est pour les alertes** : retard, erreur. Jamais pour décorer.
- **Une couleur par domaine**, de la poignée du fader à la puce des cartes et au tampon FAIT.
- **La grille des 4 colonnes reste visible** : les tranches, et sur chaque écran la règle graduée (`.regle`) dont les 3 repères tombent entre les colonnes des tranches ; les rangs (`.rang`) s'alignent sur la même grille.
- **Rien ne disparaît** : ce qui est fait ou passé reste, tamponné FAIT ou JOUÉ.
- **Le mouvement est mécanique et bref** : le tampon frappe (180 ms), l'afficheur du tirage roule colonne par colonne puis la proposition se révèle de gauche à droite (420 ms), la feuille monte. Aucune lueur.
- **Mouvement réduit** : toutes les animations et transitions sont coupées, le tirage affiche sa proposition tout de suite.

## 5. Les exceptions voulues

- **Graduations des faders** : un dégradé répété (`repeating-linear-gradient`) dessine les traits. C'est une échelle, pas une texture de surface.
- **Rebond du tampon** : la frappe dépasse légèrement (`cubic-bezier(0.2, 0.9, 0.3, 1.2)`) puis se pose, comme un tampon sur le papier. C'est le seul rebond de l'app.
- **Contour holographique des cartes rares** (lot 4) : liseré aux 4 couleurs des domaines parcouru par un reflet blanc, figé en mouvement réduit. Seule exception à « aucune lueur », voulue par Colin le 2026-10-08.

## 6. L'accessibilité

- **Cibles** : 44 px au moins pour tout ce qui se touche (onglets 52 px, boutons 44 à 64 px, cases des étapes 48 px, filtres, action des messages, « Reconnecter »).
- **Contraste** (mesuré) : texte principal 13,3 à 16,4 sur les fonds ; texte secondaire 6,3 sur panneau ; ambre 8,7 à 10,8 ; texte d'alerte 5,8 sur panneau ; couleurs de domaine 5,3 à 10,5 ; scotch 14,3. Le GO éteint (ambre doux sur console, 4,0) est un très grand texte (seuil 3). `--texte-3` ne s'utilise que sur graphite (5,7).
- **VoiceOver** : le + s'appelle « Noter quelque chose », GO « GO, je fais quoi maintenant ? » (« GO, éteint pendant le cours » pendant un cours), les cases « Cocher : … » et « Décocher : … », la feuille est un `dialog` nommé « Noter ». Les icônes sont décoratives (`aria-hidden`).
- **Clavier et focus** : contour ambre de 2 px décalé de 3 px (`:focus-visible`), Échap ferme la feuille.
- **Taille de texte** : voir § 2. Les pièces de console (afficheur, scotch, voyants, tampons) gardent leur taille.

## 7. La revue de finition

**Verdict : ship**, le 2026-10-10 (lot 1, plan 3, tâche 12).

Revue faite sur les 11 captures de `tests/e2e/captures.mjs` (iPhone 14, 390 × 844, @3x) puis un audit technique (contraste, cibles, détecteur, débordements à 375, 390 et 768 px, mouvement réduit). Corrigé :
- le nom du cours en STANDBY passait sur deux lignes quand la cote était longue (« lun. 19/10 16:30 ») : il garde sa ligne, la cote se plie ;
- les 5 filtres de Tâches passaient sur deux lignes à 390 px : marges resserrées, ils tiennent sur une ligne à la taille standard (et se replient aux grandes tailles) ;
- « Reconnecter » dans le bandeau Google faisait 36 px de haut : 44 px.

Constats du détecteur gardés exprès : graduations des faders et rebond du tampon (§ 5), interlignage de 1,25 à 1,28 sur des titres de deux lignes (carte ouverte, proposition du tirage).

À trancher par Colin : au marqueur, les chiffres des codes d'UV ressemblent à des lettres (« TN01 » se lit « TNOI », « GE15 » « GEIS »). Le contrat fixe Permanent Marker pour le scotch ; une piste serait d'écrire les chiffres du scotch en Martian Mono.
