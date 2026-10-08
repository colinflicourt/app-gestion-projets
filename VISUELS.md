# Visuels de Jarvis Jr.

> Étape 7 du guide `guides/CREER-UN-SITE-WEB.md`. Proposé le 2026-10-07. Le même jour, Colin a choisi l'icône, validé les badges, donné son modèle d'iPhone et remplacé le skieur par un boss de fin (§ 4). Le 2026-10-08 : boss détourés, fonds des combats (§ 4 bis) et cartes à collectionner (§ 7) ajoutés. Les planches sont sur la maquette, dans la rangée « Les visuels » : https://claude.ai/artifact/ACPbctYmzhyqJrQk4ou4Nj

## Le principe

Presque toute l'app est dessinée en code : l'afficheur, les tranches, les faders, les tampons, le Wrapped, les onglets, leurs pictogrammes et le cadre des cartes sont en HTML, CSS et SVG. Il ne reste que cinq familles d'images :

1. l'icône « Jr. » et l'écran de lancement, dessinés par Claude ;
2. les 12 badges, des pictogrammes SVG dessinés par Claude ;
3. les 4 boss de la session focus, debout et K.O., générés par toi avec ChatGPT (§ 4) ;
4. les 4 fonds des combats, un repaire par boss, à générer par toi (§ 4 bis) ;
5. les 70 illustrations des cartes à collectionner, à générer par toi, par lots (§ 7 et `CARTES.md`).

Pas de photo. Pas de logo de marque : les boîtes mail sont nommées sur du scotch. Pas d'avatar pour Junior : il existe par sa voix, dans la ligne de talkback.

## Inventaire

| Visuel | Où | Qui le produit, avec quoi | Origine | Statut |
|---|---|---|---|---|
| Icône « Jr. » | Écran d'accueil, notifications, onglet du navigateur | Claude : dessin en HTML et CSS, rendu en PNG par Chrome sans fenêtre (`outils/visuels/rendre.py`) | Dessin original, police Archivo (SIL OFL 1.1) | Piste C choisie, fichiers produits |
| Écran de lancement | Ouverture de l'app installée | Claude, même script | Aplat graphite | iPhone 14, fichier produit |
| 12 badges | Moi, écran Badges, Wrapped | Claude : SVG dessiné à la main | Dessin original | Validés, dont 2 renommés avec le boss |
| Pictogrammes de l'interface | Onglets, boutons | Claude : SVG | Ceux de la maquette | Validés avec la maquette |
| 4 boss : Le Bug, Le 99+, Le Poly, La Flemme, chacun debout et K.O. (8 images) | Session focus | Toi : ChatGPT, avec les prompts du § 4 ; détourage par Claude (`outils/visuels/detourer_boss.py`) | Images générées | Détourés le 2026-10-08 : `site/img/boss/` |
| 4 fonds des combats | Session focus, derrière le boss | Toi : ChatGPT, avec les prompts du § 4 bis ; encodage par Claude, voile en code | Images générées | Générés et encodés le 2026-10-08 : `site/img/fonds/` |
| 70 cartes à collectionner (52 de niveau, 18 rares) | Album dans Moi, déblocage, Wrapped | Toi : ChatGPT, avec `CARTES.md` ; encodage par Claude | Images générées | Toutes générées et encodées le 2026-10-08 : `site/img/cartes/` |

## 1. L'icône « Jr. »

**Choix de Colin (2026-10-07) : C, la touche GO.** La touche ambre allumée de l'app, avec « Jr. » dessus. Écartées : A (l'afficheur) et B (le scotch).

Fabrication :

- source `outils/visuels/icone.html` à 1 024 × 1 024 px, avec Archivo, copiée dans `assets-originaux/polices/` avec sa licence ;
- `python3 outils/visuels/rendre.py` la rend en PNG avec Chrome sans fenêtre, puis la réduit à chaque taille :
  - `site/apple-touch-icon.png` (180 px), celle de l'écran d'accueil de l'iPhone ;
  - `site/icons/icon-192.png` et `icon-512.png`, pour le manifeste ;
  - `site/favicon-32.png`, pour l'onglet du navigateur sur le Mac ;
- fond plein, sans transparence (iOS remplace la transparence par du noir), et sans coins arrondis (iOS les ajoute lui-même) ;
- pas de version « maskable » : elle ne sert qu'à Android.

## 2. L'écran de lancement

Sans lui, une web app installée peut s'ouvrir sur un écran blanc, ce qui casse le mode sombre. iOS demande une image au pixel près pour chaque modèle :

- iPhone 14 : 390 × 844 points, soit 1 170 × 2 532 pixels ;
- `site/img/lancement-1170x2532.png`, produit par le même script ;
- aplat graphite #121417, le fond de l'app, sans logo : Apple recommande un écran de lancement qui ressemble au premier écran de l'app.

## 3. Les 12 badges

**Validés par Colin le 2026-10-07.** Planche « Badges, les 12 », qui s'ouvre depuis Moi en touchant « 3 / 12 » :

- pictogrammes SVG dans le style des onglets : grille de 24, trait de 1,75, bouts arrondis ;
- badge gagné : pictogramme crème, tampon GAGNÉ et date ;
- badge à gagner : pictogramme gris, avec sa condition tirée de `CONTENU.md` ;
- écart avec `CONTENU.md`, qui disait « en couleur » : les badges gagnés sont crème. L'ambre reste à l'élément allumé, les couleurs aux domaines.

| Badge | Pictogramme |
|---|---|
| Premier commit | Un commit : un rond sur une ligne |
| Lève-tôt | Soleil levant |
| Couche-tard | Croissant de lune |
| Mieux vaut tard | Escargot |
| Semaine parfaite | Calendrier coché |
| Tous terrains | 4 faders poussés à fond |
| Inbox zéro | Bac de réception coché |
| Perfect | Cœur intact |
| Chasseur de boss | Tête de monstre K.O., les yeux en croix |
| Machine à sous | Machine à sous |
| Médian en vue | Jumelles |
| Plume | Plume |

« Perfect » et « Chasseur de boss » remplacent « Descente parfaite » et « Habitué de la station », qui parlaient de ski.

Fichier : un seul `site/img/badges.svg`, avec un `<symbol>` par badge appelé par `<use>`. Aucune image matricielle.

## 4. La session focus : le boss de fin

**Choix de Colin (2026-10-07) : le boss de fin**, à la place du skieur, jugé pas assez drôle. Textes dans `CONTENU.md` § 6.

- La tâche lancée par « C'est parti » devient un boss, joué par le boss de son domaine : Le Bug (QYRIS), Le 99+ (Asso), Le Poly (Études), La Flemme (Perso).
- **8 images à générer par toi** : chaque boss debout, puis vaincu. Le reste est en code : la barre de vie, les « −1 », le tremblement à chaque coup, le tampon K.O. et la vie qui remonte quand tu fuis.
- Taille : le boss s'affiche sur environ 220 points de haut, soit 660 pixels sur l'iPhone 14. Le personnage doit donc occuper environ 70 % de la hauteur d'une image carrée.
- Fond à détourer : magenta pour trois boss, vert pour La Flemme, qui est rose (le détourage du magenta risquerait de mordre dedans).
- Sur la maquette, la planche Focus montre le combat avec un Bug provisoire, dessiné par Claude en attendant les vraies images. Son K.O. passe encore en gris : la pose vaincue le remplacera.

### Les prompts

Dans une seule conversation ChatGPT, une image par message, dans l'ordre, pour garder le même style. Colle chaque texte tel quel : il sera inscrit dans l'image finale. Si tu le modifies, dis-le-moi.

**1. Le Poly** (Études) :

> Génère un boss de jeu vidéo 2D en illustration vectorielle plate, style cartoon drôle et pas effrayant, avec des contours sombres nets et épais, un ombrage à plat sans dégradé, sans texture et sans reflet brillant. Le boss : « Le Poly », un monstre fait d'une pile de polycopiés de cours agrafés, dominante bleu clair #6FB0FF (couvertures bleues, pages blanches), gros yeux furieux, une bouche pleine de dents en pages déchirées, des post-it jaunes collés comme des cheveux, une tache de café, deux petits bras en trombones et un stylo coincé sur le côté. Il est seul, en pied, de face, en posture de boss prêt au combat. Il occupe environ 70 % de la hauteur de l'image, centré, avec au moins 12 % de marge jusqu'aux bords : rien ne touche les bords. Fond uni magenta vif #FF00FF, parfaitement plat, sans dégradé, sans vignette, sans sol et sans ombre portée. Format carré. Aucun texte dans l'image.

**2. Le Bug** (QYRIS) :

> Même style exactement que l'image précédente : trait, épaisseur des contours, ombrage, proportions, cadrage et fond magenta vif #FF00FF uni. Le boss : « Le Bug », un insecte trapu et vexé, dominante vert #3DD68C, six pattes, deux antennes terminées par des boules, une carapace avec des taches vert foncé, des sourcils froncés et un sourire narquois plein de petites dents. Quelques accolades de code { } flottent autour de lui. Il est seul, en pied, de face, en posture de boss prêt au combat, sur environ 70 % de la hauteur de l'image, centré, avec au moins 12 % de marge jusqu'aux bords. Format carré. Aucun autre texte dans l'image.

**3. Le 99+** (Asso) :

> Même style exactement que les images précédentes, sur le même fond magenta vif #FF00FF uni. Le boss : « Le 99+ », une énorme bulle de discussion vivante, dominante violet #A97BFF, avec de petits bras et de petites jambes, des yeux exorbités et une bouche grande ouverte qui crie. Sur sa tête, une pastille de notification rouge où est écrit « 99+ ». Trois petites bulles de discussion vides s'agitent autour de lui. Il est seul, en pied, de face, en posture de boss prêt au combat, sur environ 70 % de la hauteur de l'image, centré, avec au moins 12 % de marge jusqu'aux bords. Format carré. Aucun autre texte que « 99+ ».

**4. La Flemme** (Perso) :

> Même style exactement que les images précédentes. Le boss : « La Flemme », un gros monstre tout mou et avachi qui ressemble à un coussin de canapé vivant, dominante rose #FF7AC6, paupières mi-closes, sourire satisfait, un filet de bave, une main qui tient un téléphone et l'autre derrière la tête, une petite couverture sur les pieds. Elle est seule, en pied, de face, sur environ 70 % de la hauteur de l'image, centrée, avec au moins 12 % de marge jusqu'aux bords. Attention, fond différent pour celle-ci : vert vif uni #00FF00, parfaitement plat, sans dégradé, sans vignette, sans sol et sans ombre portée. Format carré. Aucun texte dans l'image.

### Les poses K.O.

Idée de Colin (2026-10-07) : chaque boss a aussi sa pose vaincue. Au K.O., elle remplace le boss debout, puis le tampon K.O. frappe. C'est la récompense après 45 min de concentration : une vraie pose vaincue est bien plus drôle qu'un boss qui passe en gris. Pas de pose pour la fuite : la vie qui remonte et la réplique de Junior suffisent.

Dans la même conversation, une fois les 4 boss faits : pour chaque K.O., joins l'image du boss debout que tu as enregistrée, puis colle le texte.

**1 bis. Le Poly, K.O.** :

> Le même personnage que sur l'image jointe, exactement dans le même style, avec les mêmes couleurs et la même taille, cette fois vaincu, K.O. : la pile de polycopiés s'est effondrée en tas, les feuilles éparpillées autour, les yeux en croix, la langue tirée, les post-it qui s'envolent et le stylo cassé en deux. Il est posé au sol, centré, avec au moins 12 % de marge jusqu'aux bords. Même fond uni magenta vif #FF00FF, parfaitement plat, sans dégradé, sans vignette, sans sol et sans ombre portée. Format carré. Aucun texte dans l'image.

**2 bis. Le Bug, K.O.** :

> Le même personnage que sur l'image jointe, exactement dans le même style, avec les mêmes couleurs et la même taille, cette fois vaincu, K.O. : retourné sur le dos, les six pattes en l'air, les yeux en croix, la langue qui pend, les antennes tordues, de petites étoiles qui tournent au-dessus de lui et ses accolades { } tombées par terre. Il est posé au sol, centré, avec au moins 12 % de marge jusqu'aux bords. Même fond uni magenta vif #FF00FF, parfaitement plat, sans dégradé, sans vignette, sans sol et sans ombre portée. Format carré. Aucun autre texte dans l'image.

**3 bis. Le 99+, K.O.** :

> Le même personnage que sur l'image jointe, exactement dans le même style, avec les mêmes couleurs et la même taille, cette fois vaincu, K.O. : dégonflé comme un ballon crevé, tout plat et fripé au sol, les yeux en croix, la bouche en zigzag, et sa pastille de notification, devenue grise, affiche maintenant « 0 ». Il est posé au sol, centré, avec au moins 12 % de marge jusqu'aux bords. Même fond uni magenta vif #FF00FF, parfaitement plat, sans dégradé, sans vignette, sans sol et sans ombre portée. Format carré. Aucun autre texte que « 0 ».

**4 bis. La Flemme, K.O.** :

> Le même personnage que sur l'image jointe, exactement dans le même style, avec les mêmes couleurs et la même taille, cette fois vaincue, K.O. : écrasée et aplatie comme une crêpe sur sa couverture, les yeux en croix, la langue tirée, son téléphone tombé à côté d'elle avec une batterie vide affichée à l'écran. Elle est posée au sol, centrée, avec au moins 12 % de marge jusqu'aux bords. Même fond uni vert vif #00FF00, parfaitement plat, sans dégradé, sans vignette, sans sol et sans ombre portée. Format carré. Aucun texte dans l'image.

### Le détourage (fait le 2026-10-08)

- Les 8 images reçues le 2026-10-07 dans `assets-originaux/generees/` (1 254 × 1 254 px, fonds magenta et vert bien plats), toutes dans le même style : aucune à régénérer.
- Script `outils/visuels/detourer_boss.py` (Pillow et numpy), plutôt que `remove-bg.py` : le violet du 99+ est trop proche du magenta pour un simple seuil. Le fond est mesuré sur les coins ; un pixel n'est du fond que s'il en est très proche ; l'alpha progressif et le dé-mélange ne touchent qu'une bande de 3 px autour du fond, donc le contour noir. L'intérieur des boss reste opaque.
- Sorties : `site/img/boss/boss-{poly,bug,99,flemme}[-ko].webp`, toutes sur une même toile transparente de 1 020 × 660 px (340 × 220 points), personnage centré et posé en bas : la pose K.O. remplace la pose debout sans que rien ne bouge. Le boss debout fait 654 px de haut ; sa pose K.O. garde la même échelle, réduite seulement si elle déborde (Le Poly K.O., trop large). De 57 à 142 Ko chacune.
- Vérifié : aucun liseré magenta ou vert, bords nets à la loupe sur le graphite et sur le scotch, violet du 99+ intact, prompt exact inscrit dans chaque WebP (XMP).
- À savoir pour l'écran : les contours noirs des boss se perdent sur le graphite pur. Le fond du combat (§ 4 bis) prévoit donc une zone de ton moyen derrière le boss.
- Maquette mise à jour le 2026-10-08 (version 28) : le Bug provisoire est remplacé par les vraies images sur son fond, la pose K.O. remplace le passage en gris, et la maquette ajoute le bouton « J'ai fini la tâche », l'album dans Moi et l'arrivée d'une carte rare avec son contour brillant.

## 4 bis. Les fonds des combats

**Décidé le 2026-10-08.** Derrière chaque boss, son repaire dans ton monde. Le fond remplit l'écran du combat ; en code, un voile graphite l'assombrit pour garder l'afficheur lisible.

| Boss | Repaire | Touche de couleur |
|---|---|---|
| Le Bug | Le bureau de QYRIS la nuit | Vert #3DD68C |
| Le 99+ | La scène du Cabaret avant le spectacle | Violet #A97BFF |
| Le Poly | Un amphi de l'UTC vide | Bleu #6FB0FF |
| La Flemme | Un salon d'étudiant le soir | Rose #FF7AC6 |

Contraintes, écrites dans chaque prompt :
- même style que les boss, mais contours plus fins et moins de détails, pour que le boss ressorte ;
- nuit, couleurs sombres et désaturées, pas de néon, pas de lueur, aucun texte ;
- portrait 2:3, sol sur le tiers bas, zone dégagée de ton moyen au centre où le boss se pose (ses contours noirs disparaîtraient sur du noir), tiers haut calme sous l'afficheur ;
- objets importants dans la moitié centrale de la largeur : l'écran de l'iPhone, plus étroit que le 2:3, rogne les côtés.

**Comment générer** : une nouvelle conversation ChatGPT « Fonds », sans joindre les boss (sinon il les dessine dans le décor), une image par message. Dépôt : `assets-originaux/generees/fonds/fond-bug.png`, `fond-99.png`, `fond-poly.png`, `fond-flemme.png`. Je les encode en WebP à leur taille d'origine dans `site/img/fonds/`, avec leur origine.

**1. Le bureau de QYRIS** (Le Bug) :

> Génère un décor de jeu vidéo 2D, sans aucun personnage ni animal, en illustration vectorielle plate, style cartoon, avec des contours sombres fins, un ombrage à plat sans dégradé, sans texture et sans reflet brillant. Le lieu : un bureau la nuit, avec un bureau en bois, un ordinateur portable refermé, des post-it, une pile de carnets, une tasse noire décorée d'un grand Q argenté, une lampe de bureau éteinte, et derrière, une fenêtre sur une ville endormie. Couleurs sombres et désaturées, dominante bleu nuit et gris, avec une seule touche discrète de vert #3DD68C. Pas de néon, aucune source de lumière qui brille, pas de halo, aucun texte en dehors du « Q » de la tasse. Composition pour un écran de téléphone vertical : le sol occupe le tiers bas de l'image ; au centre, juste au-dessus du sol, une large zone dégagée d'un ton moyen, un peu plus claire que le reste, sans aucun objet, où un personnage sera ajouté plus tard ; le tiers haut reste calme et sombre, sans détail important. Les objets importants restent dans la moitié centrale de la largeur, car les côtés seront rognés. Format portrait 2:3.

**2. La scène du Cabaret** (Le 99+) :

> Génère un décor de jeu vidéo 2D, sans aucun personnage ni animal, en illustration vectorielle plate, style cartoon, avec des contours sombres fins, un ombrage à plat sans dégradé, sans texture et sans reflet brillant. Le lieu : la scène d'un cabaret juste avant le spectacle, avec un plancher en bois, un grand rideau de velours fermé, deux pieds de micro, une table de régie sur le côté, et au premier plan le haut des dossiers de quelques chaises de spectateurs vides. Couleurs sombres et désaturées, dominante bleu nuit et bordeaux éteint, avec une seule touche discrète de violet #A97BFF. Pas de néon, aucun projecteur allumé, pas de halo, aucun texte. Composition pour un écran de téléphone vertical : le sol occupe le tiers bas de l'image ; au centre, juste au-dessus du sol, une large zone dégagée d'un ton moyen, un peu plus claire que le reste, sans aucun objet, où un personnage sera ajouté plus tard ; le tiers haut reste calme et sombre, sans détail important. Les objets importants restent dans la moitié centrale de la largeur, car les côtés seront rognés. Format portrait 2:3.

**3. L'amphi** (Le Poly) :

> Génère un décor de jeu vidéo 2D, sans aucun personnage ni animal, en illustration vectorielle plate, style cartoon, avec des contours sombres fins, un ombrage à plat sans dégradé, sans texture et sans reflet brillant. Le lieu : un amphithéâtre d'université vide le soir, avec des rangées de sièges en bois en gradins sur les côtés, une estrade au fond, un grand tableau couvert de calculs à la craie illisibles (des crochets de matrices et des chiffres flous), et des piles de polycopiés sur le bureau du professeur. Couleurs sombres et désaturées, dominante bleu nuit et gris, avec une seule touche discrète de bleu clair #6FB0FF. Pas de néon, aucune source de lumière qui brille, pas de halo, aucun texte lisible. Composition pour un écran de téléphone vertical : le sol occupe le tiers bas de l'image ; au centre, juste au-dessus du sol, une large zone dégagée d'un ton moyen, un peu plus claire que le reste, sans aucun objet, où un personnage sera ajouté plus tard ; le tiers haut reste calme et sombre, sans détail important. Les objets importants restent dans la moitié centrale de la largeur, car les côtés seront rognés. Format portrait 2:3.

**4. Le salon** (La Flemme) :

> Génère un décor de jeu vidéo 2D, sans aucun personnage ni animal, en illustration vectorielle plate, style cartoon, avec des contours sombres fins, un ombrage à plat sans dégradé, sans texture et sans reflet brillant. Le lieu : un salon d'étudiant le soir, avec un vieux canapé et un plaid, une télévision éteinte, des cartons de pizza empilés, un tapis, une plante qui pend d'une étagère et des rideaux tirés. Couleurs sombres et désaturées, dominante bleu nuit et gris, avec une seule touche discrète de rose #FF7AC6. Pas de néon, aucune source de lumière qui brille, pas de halo, aucun texte. Composition pour un écran de téléphone vertical : le sol occupe le tiers bas de l'image ; au centre, juste au-dessus du sol, une large zone dégagée d'un ton moyen, un peu plus claire que le reste, sans aucun objet, où un personnage sera ajouté plus tard ; le tiers haut reste calme et sombre, sans détail important. Les objets importants restent dans la moitié centrale de la largeur, car les côtés seront rognés. Format portrait 2:3.

## 5. Origine inscrite dans chaque image

- Chaque PNG et WebP publié reçoit son origine dans ses métadonnées : auteur, outil, date, et le prompt pour une image générée.
  - PNG : `impeccable embed-prompt` (fait pour l'icône et l'écran de lancement, `impeccable embed-prompt --read <fichier>` pour la relire).
  - WebP : `impeccable embed-prompt` n'écrit pas dans un WebP (il crée un `.json` à côté), donc nos scripts écrivent l'origine en XMP (`dc:description`) au moment de l'encodage. Pour la relire : `python3 -c "from PIL import Image; print(Image.open('site/img/boss/boss-bug.webp').info['xmp'].decode())"`.
- Chaque SVG porte un commentaire d'origine en tête.
- Les originaux restent dans `assets-originaux/`, jamais publiés tels quels.

## 6. Décisions

| # | Sujet | Décision |
|---|---|---|
| 1 | Icône | C, la touche GO (2026-10-07) |
| 2 | Badges | Les 12 pictogrammes validés (2026-10-07) |
| 3 | Session focus | Le boss de fin, à la place du skieur, avec une pose K.O. par boss (2026-10-07) |
| 4 | iPhone | iPhone 14 (2026-10-07) |
| 5 | Détourage des boss | Script `detourer_boss.py`, toile commune de 1 020 × 660 px, origine en XMP (2026-10-08) |
| 6 | Fonds des combats | Un repaire par boss, sombre, zone de ton moyen derrière le boss (2026-10-08) |
| 7 | Récompenses | Cartes à collectionner, les boss dans ton monde, une rare par badge, générées par lots (2026-10-08) |

## 7. Les cartes à collectionner

**Décidé le 2026-10-08.** Règles dans `CONTENU.md` § 8 bis, banque des 70 prompts dans `CARTES.md`.

- **L'image** : une illustration carrée avec un vrai décor, dans le style des boss. Pas de détourage. Pas de texte, sauf quelques mots demandés entre guillemets (« FAIT », « GI », « ASSISTANT »).
- **Le cadre** est en code : bande dans la couleur du domaine, numéro, titre, réplique de Junior, date, tampon RARE. Le titre n'est jamais dans l'image, pour pouvoir le corriger sans régénérer.
- **Contour brillant des rares** (demandé par Colin le 2026-10-08) : liseré holographique en CSS aux 4 couleurs des domaines (#3DD68C, #A97BFF, #6FB0FF, #FF7AC6), parcouru lentement par un reflet blanc ; figé en mouvement réduit. Seule exception à la règle « sans dégradé ni lueur » de la direction artistique.
- **Génération** : Colin a généré les 70 cartes et les 4 fonds en une fois, le 2026-10-08, sans passer par les lots. Originaux renommés selon la convention (`RENOMMAGES-20261008.txt` dans `assets-originaux/generees/`). Relues sur planche : style et boss cohérents, aucune à régénérer. Détails mineurs gardés : logo Apple sur le Mac de Q09, « JANUARY » en anglais sur P10.
- **Encodage** : WebP de 900 × 900 px (300 points sur l'iPhone 14) dans `site/img/cartes/`, nommé par le code de la carte (`q01.webp`), avec en XMP l'origine, le message de départ de la série et le message exact de la carte. Les fonds gardent leur taille (1 024 × 1 536) dans `site/img/fonds/`. Script `outils/visuels/encoder_cartes.py`, qui écrit aussi `site/data/cartes.json` (code, série, condition, titre, réplique, image) depuis `CARTES.md`, seule source des textes : une correction de titre se fait dans `CARTES.md`, puis on relance le script.
- **Contrôle** : chaque carte relue à la taille du téléphone. Une carte où le boss a dérivé (forme, couleurs) est régénérée dans la même conversation, image du boss jointe.
- **Poids** : environ 130 Ko par carte, 8,9 Mo pour les 70, 172 Ko pour les 4 fonds. L'album charge les images à la demande, jamais toutes au lancement.
