---
version: 1
slug: "site-index-html"
primary_target: "site/index.html"
related_targets: ["site/js","site/css"]
---

# Jarvis Jr. : l'app entière, première surface Aujourd'hui

## Portée et mode

- Portée : le monde visuel de toute l'app (portée direction). Première surface : l'écran Aujourd'hui (`site/index.html`), puis Agenda, Tâches, Mails, Moi, Réglages, capture, tirage, focus et Wrapped dans le même monde.
- Mode : Operate. Colin vient savoir ce qui l'attend et quoi faire maintenant.

## Audience, tâche, contenu, contraintes

- Colin seul, iPhone 14 (390 × 844 points) en iOS 26.6, toujours en mode sombre, web app installée. Au lit dans la pénombre, en marchant d'une main, en cours téléphone posé, le soir au bureau.
- Tâche : en trois secondes, le prochain cours (heure, salle) et ce qui presse, puis GO.
- Contenu : `CONTENU.md` (validé). Données réelles : `INVENTAIRE.md`. Toute donnée de démonstration est marquée fictive.
- Contraintes : `PRODUCT.md` (cibles de 44 px, contraste AA, mouvement réduit). À éviter, selon Colin : l'appli de productivité banale, le cliché IA ou hacker (vert sur noir, néon, hologramme), l'illisible en marchant. Le jeu est présent mais second sur Aujourd'hui.

## Direction choisie et moment mémorable

- La console de régie : carte du tirage, choisie par Colin le 2026-10-07 sur la page de décision, sans consigne.
- Chemin code : pas de génération d'images, donc pas de comp. L'ambition tient dans le contrat ci-dessous.
- Moment mémorable : le top GO.

## Direction contract

THESIS: La journée se pilote comme la régie du Cabaret UTC : un écran de conduite, quatre tranches, un seul GO. Refusé : la liste de cartes blanches arrondies des apps d'organisation, les anneaux de progression, les dégradés, et le cliché IA (vert sur noir, néon, halo).

OWN-WORLD: Graphite #121417, panneaux console #1E2228. Ambre #FFB020 réservé à l'afficheur et à l'unique élément allumé, sans halo. Scotch #EDE6D6 écrit au marqueur pour les étiquettes de tranche. Rouge #E5484D pour les alertes seulement. Une couleur franche par domaine, du capuchon de fader jusqu'aux notifications. Chiffres tabulaires alignés au pixel. Grille des tranches visible sur chaque écran. Rien ne disparaît : tampons FAIT et JOUÉ.

STORY: Colin voit ce qui est en scène et ce qui suit, croit que Junior a tout vérifié, appuie sur GO et part sur l'étape proposée.

FIRST VIEWPORT: Aujourd'hui à 390 × 844. En haut, sur un gros tiers : l'écran de conduite, EN SCÈNE le cours en cours, STANDBY le suivant, heure et salle en chiffres ambre géants lisibles bras tendu, compte à rebours. Dessous, la ligne de talkback : salutation et réplique de Junior, son grade en petit. Au milieu, les tranches QYRIS, Asso, Études, Perso avec leurs compteurs, une seule allumée. En bas, dans la zone du pouce, GO sur six colonnes sur douze, sous-titré « Je fais quoi maintenant ? », au-dessus des onglets et du +. Signature : le top GO, STANDBY puis les chiffres de l'afficheur roulent colonne par colonne, la suggestion tombe, « C'est parti » lance le focus. Mouvement : mécanique et bref, faders à friction, tampons qui frappent, aucune lueur ; en mouvement réduit, changements instantanés.

FORM: La console de régie. Le tirage a assigné la position 4 de ma liste ordonnée. Le tableau des départs de gare, qui l'occupait, sortait des trois univers de Colin. La console, candidat conforme suivant, l'a remplacé avant présentation. Clé du tirage : 0b599a86.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Calibrage de la finition

Chemin code, sans comp. Les planches des six challengers qui ont donné un écart calibrent le niveau de finition, jamais la composition. Elles servent à la revue finale.

| Donneur | Écart repris | Planche | Héros |
|---|---|---|---|
| metro | heure et salle en très gros corps | https://impeccable.style/worlds/cards/digital-design-canon-metro-typographic-tiles.webp | https://impeccable.style/worlds/cards/digital-design-canon-metro-typographic-tiles-hero.webp |
| ticket | tampons FAIT et JOUÉ | https://impeccable.style/worlds/cards/vernacular-ephemera-jet-age-ticket-wallet.webp | https://impeccable.style/worlds/cards/vernacular-ephemera-jet-age-ticket-wallet-hero.webp |
| transit | une couleur par domaine, engagée à fond | https://impeccable.style/worlds/cards/wayfinding-cartography-signage-midnight-transit-diagram.webp | https://impeccable.style/worlds/cards/wayfinding-cartography-signage-midnight-transit-diagram-hero.webp |
| factory | un seul élément allumé | https://impeccable.style/worlds/cards/brand-identity-canon-saville-catalog-sleeve.webp | https://impeccable.style/worlds/cards/brand-identity-canon-saville-catalog-sleeve-hero.webp |
| ikeda | chiffres tabulaires | https://impeccable.style/worlds/cards/ikeda-datamatics.webp | https://impeccable.style/worlds/cards/ikeda-datamatics-hero.webp |
| crouwel | grille des tranches visible | https://impeccable.style/worlds/cards/crouwel-grid-specimen.webp | https://impeccable.style/worlds/cards/crouwel-grid-specimen-hero.webp |

## Décisions de la maquette (validée par Colin le 2026-10-07)

Maquette : https://claude.ai/artifact/ACPbctYmzhyqJrQk4ou4Nj

- **Couleurs des tranches** :
  - tirées de la charte du CV, éclaircies pour le graphite : QYRIS #3DD68C, Asso #A97BFF, Études #6FB0FF, Perso #FF7AC6 ;
  - pas d'orange, trop proche du rouge d'alerte ;
  - texte d'alerte en #FF6B70 sur les panneaux, où #E5484D manque de contraste.
- **Polices** :
  - Archivo pour le texte et le GO ;
  - Martian Mono pour l'afficheur, les chiffres et les étiquettes ;
  - Permanent Marker pour le scotch seulement ;
  - toutes à héberger dans `site/fonts/` au lot 1.
- **Le curseur de chaque tranche** montre le niveau d'XP du domaine, avec le chiffre du niveau sur la poignée.
- **L'élément allumé** :
  - GO est le seul élément allumé d'Aujourd'hui ;
  - après le tirage, « C'est parti » prend le relais ;
  - pendant un cours, GO s'éteint.
- **Sous le pli d'Aujourd'hui** :
  - la conduite du jour, avec les cours et les rappels, et le tampon JOUÉ une fois passés ;
  - puis les tâches à faire et les mails ;
  - l'afficheur se réduit en haut et garde un petit GO.
- **Les UV** : repérées par leur code sur scotch plutôt que par une couleur. Les couleurs restent aux domaines.
- **Les 9 autres écrans** (validés le même jour) :
  - Agenda : vue semaine, cours passés tamponnés JOUÉ, bandeau pour les jours fériés ;
  - Tâches : filtres par domaine avec témoins, checklist avec tampons FAIT, « Ouvrir dans Trello » ;
  - Mails : une section par boîte, nommée sur scotch, sans logo de marque, en lecture seule ;
  - capture : feuille du bas, Tâche ou Rappel, domaine choisi sur scotch ;
  - focus : combat contre le boss du domaine sur une scène sombre, vie du boss en segments ambre dans l'afficheur, tampon K.O. dans la couleur du domaine (remplace la piste de ski le même jour) ;
  - Wrapped : stories plein écran, vumètres par domaine ;
  - Moi : grade de Junior sur l'afficheur, XP par domaine, série, badges ;
  - le grade affiché suit l'XP : Assistant dans les exemples de la maquette, Stagiaire au lancement.
- **Badges** (validés le 2026-10-07) : pictogrammes SVG au trait (grille de 24, trait de 1,75). Gagnés en crème avec tampon GAGNÉ, à gagner en gris avec leur condition.
- **Combat du focus** (validé le 2026-10-07) : textes de `CONTENU.md` § 6, badges « Perfect » et « Chasseur de boss ». Au K.O., la pose vaincue du boss remplace la pose debout, puis le tampon K.O. frappe : pas de passage en gris.
- **Icône** (choisie le 2026-10-07) : « Jr. » sur la touche GO ambre, en Archivo 800 étroit, relief mécanique en bas, sans halo. Écran de lancement : aplat graphite.

- **Récompenses** (décidées le 2026-10-08) :
  - cartes à collectionner (les boss dans le monde de Colin), cadre en code : bande dans la couleur du domaine, numéro en Martian Mono, titre, réplique, date ; rares avec un tampon RARE et un contour brillant, liseré holographique aux 4 couleurs des domaines parcouru par un reflet blanc, figé en mouvement réduit (exception voulue par Colin le 2026-10-08 à « aucune lueur ») ;
  - déblocage : carte face cachée qui se retourne d'un geste mécanique et bref, instantané en mouvement réduit ;
  - album dans Moi, sous le grade de Junior : 4 tranches par domaine, 3 cartes par rangée, dos des cartes à gagner avec leur seuil ;
  - combat : « J'ai fini la tâche » est un bouton de console non allumé, sous le boss ; fond du repaire du boss sous un voile graphite, zone de ton moyen derrière le boss.

## Décisions ouvertes

- Aucune. Le 2026-10-08, la maquette a reçu les vrais boss, le fond du combat, « J'ai fini la tâche », le bloc Album de Moi, l'écran Album et l'arrivée d'une carte rare (contour brillant). À valider par Colin.
