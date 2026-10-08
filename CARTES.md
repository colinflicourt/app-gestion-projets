# Les cartes à collectionner de Jarvis Jr.

> Banque de prompts des 70 cartes. Design validé par Colin le 2026-10-08, règles dans `CONTENU.md` § 8 bis, format et fabrication dans `VISUELS.md` § 7. Les titres et les répliques de Junior sont **à relire par Colin** : ils s'affichent sur le cadre de la carte, dessiné en code, jamais dans l'image.

## Le principe

- **52 cartes de niveau**, 13 par domaine. Chacune met le boss du domaine dans un moment de ta vraie année, et les 13 racontent une petite histoire. La n° 1 est offerte à l'installation, les n° 2 à 13 se gagnent aux niveaux 2 à 13 du domaine.
- **18 cartes rares** : une par badge (12), les 3 promotions de Junior, la série de 30 jours, 25 et 50 boss battus. Elles réunissent souvent plusieurs boss.
- Thèmes écartés : l'intégration UTC (accident du 26/08), ta famille, la sécurité de tes comptes. Junior ne pique jamais sur les médians ni les finaux.
- Junior n'a pas de visage, comme décidé à l'étape 7 : il n'apparaît dans aucune image.

Seuils des niveaux (barème de `CONTENU.md` § 7, chaque niveau demande 100 XP de plus que le précédent) :

| Niveau | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| XP du domaine | 0 | 100 | 300 | 600 | 1 000 | 1 500 | 2 100 | 2 800 | 3 600 | 4 500 | 5 500 | 6 600 | 7 800 |

## Comment générer

1. **Une conversation ChatGPT par série** : QYRIS, Asso, Études, Perso, puis Rares. Dans le premier message, joins l'image du boss debout d'origine (`assets-originaux/generees/boss-bug.png` pour QYRIS, etc. ; pour les Rares, les 4 boss), puis colle le message de départ suivi de la carte.
2. **Ensuite, une carte par message**, texte collé tel quel. Il sera inscrit dans l'image finale : si tu le modifies, dis-le-moi.
3. **Si le boss dérive** (forme, couleurs), joins de nouveau son image avec le message de la carte.
4. **Dépôt** : `assets-originaux/generees/cartes/`, un fichier par carte nommé par son code en minuscules : `q01.png`, `a07.png`, `e13.png`, `p04.png`, `r18.png`.
5. Je recadre, j'encode en WebP et j'inscris l'origine (`VISUELS.md` § 7). Rien n'est détouré : les cartes ont un vrai décor.

**Fait le 2026-10-08 : les 70 cartes et les 4 fonds ont été générés en une fois**, encodés dans `site/img/`. Le système de lots reste utile pour les futures cartes hors série. Une carte gagnée sans image s'affiche « EN IMPRESSION » dans l'app. Plan initial des lots :

- **Lot 1, maintenant** (24 images) : Q01 à Q04, A01 à A04, E01 à E04, P01 à P04, R01, R02, R03, R13, et les 4 fonds des combats (`VISUELS.md` § 4 bis).
- **Lots suivants** : quand l'album affiche des cartes à imprimer, ou quand un domaine a moins de 2 cartes d'avance.

---

## QYRIS · Le Bug

**Message de départ** (avec `boss-bug.png` joint, suivi de la carte Q01) :

> Je vais te demander une série de 13 cartes à collectionner, une image par message, toutes avec le personnage de l'image jointe : « Le Bug ». Règles pour toute la série : exactement le style de l'image jointe (illustration vectorielle plate, cartoon drôle et pas effrayant, contours sombres nets et épais, ombrage à plat sans dégradé, sans texture et sans reflet brillant) ; le personnage garde toujours sa forme, ses couleurs et ses détails ; chaque carte est une scène complète avec un vrai décor coloré, jamais un fond uni ; le personnage est au premier plan, bien lisible ; format carré, avec une marge d'environ 8 % sur les bords ; pas de cadre, pas de bordure, pas de titre ; aucun texte dans l'image, sauf celui que je donne entre guillemets ; aucune personne reconnaissable : s'il y a des humains, on ne voit que leurs mains ou leur dos.

| Code | Gagnée à | Titre | Junior |
|---|---|---|---|
| Q01 | Installation | Le Bug s'installe dans qyris.fr | Il a signé un bail de longue durée. Moi, je prépare l'expulsion. |
| Q02 | Niveau 2 | Le Bug et le blog de 6 h 53 | Un article par jour depuis septembre. Lui n'a jamais rien publié. |
| Q03 | Niveau 3 | Le Bug à la Formation 1 | Il a voté « faux » à tout. Il n'a pas compris le principe. |
| Q04 | Niveau 4 | Le Bug programme la campagne LinkedIn | Mardi et jeudi, 8 h 30. Il voulait 3 h du matin, je l'ai calmé. |
| Q05 | Niveau 5 | Le Bug relit le devis de ROADY | Il cherche une ligne à facturer en trop. Il ne la trouvera pas. |
| Q06 | Niveau 6 | Le Bug tente sa chance chez IDFENSE | Une entreprise de sécurité. Il a vraiment choisi la pire cible. |
| Q07 | Niveau 7 | Le Bug déclare le chiffre d'affaires | Déclaration faite. Il a demandé s'il pouvait se déclarer à charge. |
| Q08 | Niveau 8 | Le Bug à la Formation 2 | Quatre IA en même temps. Il a six pattes, il trouvait ça logique. |
| Q09 | Niveau 9 | Le Bug face à Jarvis | Il a tenté sa chance sur le Mac. Mon grand frère l'a repéré en trois secondes. |
| Q10 | Niveau 10 | Le Bug sur scène | « L'IA, votre nouveau collaborateur. » Lui se présente comme votre ancien problème. |
| Q11 | Niveau 11 | Le Bug livre l'appli de ROADY | Livrée, testée, signée. Il a quand même essayé de rester caché dedans. |
| Q12 | Niveau 12 | Le Bug et la salle pleine | Plus une chaise de libre. Il a dû s'asseoir sur le radiateur. |
| Q13 | Niveau 13 | Le Bug, consultant senior | Il a fini par trouver sa place : il facture ses visites. |

**Q01** :

> Carte 1 : Le Bug emménage à l'intérieur d'un site web géant, une immense fenêtre de navigateur au design noir et blanc élégant, avec un grand Q argenté en métal brossé sur la page. Il déballe des cartons de déménagement, accroche au mur un cadre contenant des accolades { } et pose une plante verte. Seul texte, dans la barre d'adresse : « qyris.fr ».

**Q02** :

> Carte 2, même style, même personnage, mêmes règles : à l'aube, Le Bug en pyjama rayé et bonnet de nuit bâille devant un ordinateur portable d'où sort tout seul, comme d'une imprimante, un nouvel article de blog. Un réveil posé à côté indique 6 h 53, une tasse de café fume, le ciel par la fenêtre est rose et orange.

**Q03** :

> Carte 3, même style, même personnage, mêmes règles : Le Bug est assis au premier rang d'une petite salle de formation lumineuse et lève fièrement une patte qui tient une pancarte « FAUX ». Au mur, l'écran de projection montre trois grands pictogrammes : une loupe, un bouclier et une boussole. On voit le dos de quelques participants.

**Q04** :

> Carte 4, même style, même personnage, mêmes règles : Le Bug, un mégaphone dans une patte, se tient devant un grand calendrier mural où les mardis et les jeudis sont entourés en bleu, couvert de douze post-it alignés. Il a un crayon derrière l'antenne et l'air très organisé, une pile de visuels carrés à côté de lui.

**Q05** :

> Carte 5, même style, même personnage, mêmes règles : dans le bureau vitré d'un garage automobile, Le Bug porte des lunettes de lecture et inspecte avec une loupe un très long devis en papier qui se déroule jusqu'au sol. Derrière la vitre, une voiture est sur un pont élévateur et un tableau à clés est accroché au mur. Seul texte, en tête du devis : « DEVIS ».

**Q06** :

> Carte 6, même style, même personnage, mêmes règles : Le Bug, déguisé en cambrioleur de bande dessinée avec un loup noir sur les yeux, un pull rayé et un sac sur le dos, se tient devant une énorme porte blindée bleu marine aux détails cyan, fermée par trois serrures. Il a l'air très déçu. Décor de façade industrielle moderne, la nuit, sous un réverbère.

**Q07** :

> Carte 7, même style, même personnage, mêmes règles : Le Bug est assis à un petit bureau sous une pile de formulaires administratifs, une calculatrice dans une patte, un stylo dans une autre, une goutte de sueur sur le front. Il coche des cases sur un grand formulaire en ligne affiché sur un ordinateur. Une tirelire en forme de cochon le regarde.

**Q08** :

> Carte 8, même style, même personnage, mêmes règles : Le Bug utilise quatre téléphones à la fois, un dans chacune de ses quatre pattes avant, chacun affichant une bulle de discussion de couleur différente (vert, orange, bleu, violet). Il a l'air débordé mais ravi. Décor : une salle de formation avec des tables en bois et des participants vus de dos.

**Q09** :

> Carte 9, même style, même personnage, mêmes règles : Le Bug tente de se faufiler en rampant derrière un ordinateur Mac posé sur un bureau rangé. Un post-it jaune collé sur l'écran porte le mot « Jarvis ». Sur l'écran, un grand œil bienveillant dessiné en traits simples le regarde directement. Le Bug se fige, pris la patte dans le sac.

**Q10** :

> Carte 10, même style, même personnage, mêmes règles : Le Bug se tient sur une scène de conférence, seul devant un pupitre, un micro à la main, l'air un peu nerveux. Derrière lui, un grand écran affiche une diapositive noire et blanche avec un Q argenté. Au premier plan, les têtes du public vues de dos, dans la pénombre.

**Q11** :

> Carte 11, même style, même personnage, mêmes règles : dans un garage automobile, Le Bug tend un smartphone qui affiche une grille de photos de voitures à une main gantée de mécanicien qui entre dans l'image par le côté. Le Bug sourit, mais une de ses pattes s'agrippe encore au téléphone comme s'il voulait rester dedans.

**Q12** :

> Carte 12, même style, même personnage, mêmes règles : une salle de formation complètement pleine, chaque chaise occupée par un participant vu de dos. Le Bug, faute de place, est assis tout au fond sur le radiateur, les pattes croisées, l'air vexé mais fier.

**Q13** :

> Carte 13, même style, même personnage, mêmes règles : Le Bug en costume trois-pièces gris avec une cravate verte et une mallette, les antennes grisonnantes, debout dans un bureau vitré élégant avec vue sur une ville au coucher du soleil. Il tient une tasse noire décorée d'un grand Q argenté et prend la pose comme sur un portrait officiel.

---

## Asso · Le 99+

**Message de départ** (avec `boss-99.png` joint, suivi de la carte A01) :

> Je vais te demander une série de 13 cartes à collectionner, une image par message, toutes avec le personnage de l'image jointe : « Le 99+ ». Règles pour toute la série : exactement le style de l'image jointe (illustration vectorielle plate, cartoon drôle et pas effrayant, contours sombres nets et épais, ombrage à plat sans dégradé, sans texture et sans reflet brillant) ; le personnage garde toujours sa forme, ses couleurs et ses détails, dont sa pastille rouge « 99+ » ; chaque carte est une scène complète avec un vrai décor coloré, jamais un fond uni ; le personnage est au premier plan, bien lisible ; format carré, avec une marge d'environ 8 % sur les bords ; pas de cadre, pas de bordure, pas de titre ; aucun texte dans l'image en dehors de « 99+ », sauf celui que je donne entre guillemets ; aucune personne reconnaissable : s'il y a des humains, on ne voit que leurs mains ou leur dos.

| Code | Gagnée à | Titre | Junior |
|---|---|---|---|
| A01 | Installation | Le 99+ envahit le Discord du Gala | Le Gala a un bot qui distribue les rôles. Le 99+ a pris celui de chef. |
| A02 | Niveau 2 | Le 99+ dans la régie du Cabaret | Il a tout poussé à fond. La régie, ce n'est pas un concours. |
| A03 | Niveau 3 | Le 99+ refait la lineup | Il a mis le Quatuor en ouverture. On a remis le Quatuor à la fin. |
| A04 | Niveau 4 | Le 99+ au cours de breakdance | Il a tenté une coupole. Résultat : 99+ bleus. |
| A05 | Niveau 5 | Le 99+ joue au Password Game | Douze conditions absurdes. Il est bloqué à la quatrième depuis mardi. |
| A06 | Niveau 6 | Le 99+ et le magicien muet | Un numéro sans un bruit. Pour lui, c'était une torture. |
| A07 | Niveau 7 | Le 99+ à La La Land | Il a dansé sous le réverbère. Personne ne lui avait demandé. |
| A08 | Niveau 8 | Le 99+ et les Larsen | Trois créneaux de Larsen. Lui pensait que c'était une panne de micro. |
| A09 | Niveau 9 | Le 99+ dans la team info de SKIUT | Il a demandé si le serveur avait besoin de chaînes. |
| A10 | Niveau 10 | Le 99+ le jour du shotgun | 300 places et tout le monde en même temps. Pour une fois, il n'est pas le pire. |
| A11 | Niveau 11 | Le 99+ au Gala Etuville | Nœud papillon et vibreur. Il fait des efforts. |
| A12 | Niveau 12 | Le 99+ sur les pistes | Piste verte. Il a quand même trouvé le moyen de tomber. |
| A13 | Niveau 13 | Le 99+ en haut des pistes | Téléphone éteint, vue sur les sommets. Il a fini par comprendre. |

**A01** :

> Carte 1 : Le 99+ jaillit d'un écran de téléphone géant qui affiche une application de discussion pleine de pastilles de notification. À côté de lui, un petit robot rond en forme de noix de coco, avec deux antennes, distribue des badges colorés ; Le 99+ en a attrapé un énorme, doré, et se l'est accroché fièrement sur le ventre.

**A02** :

> Carte 2, même style, même personnage, mêmes règles : Le 99+ est assis à une grande console de régie de théâtre pleine de faders et de boutons, et pousse tous les faders à fond avec ses deux mains, l'air extatique. Par la vitre de la régie, on voit une scène avec un grand rideau rouge et des projecteurs.

**A03** :

> Carte 3, même style, même personnage, mêmes règles : Le 99+ se tient devant un immense planning mural fait de blocs de couleur (bleu, rose, jaune, vert) empilés comme un emploi du temps, et déplace un bloc avec effort pour le mettre tout en haut. Des post-it volent autour de lui, un rouleau de scotch pend à son bras.

**A04** :

> Carte 4, même style, même personnage, mêmes règles : dans un gymnase, sur un tapis de danse, Le 99+ en casquette à l'envers tente une figure de breakdance, la tête en bas, en équilibre sur sa pastille. Au mur, de grands graffitis bleus et jaunes. Des petites étoiles de choc indiquent qu'il est sur le point de tomber.

**A05** :

> Carte 5, même style, même personnage, mêmes règles : Le 99+ fait face à un champ de mot de passe géant entouré d'une liste de conditions cochées de croix rouges. Il transpire, tape au clavier avec ses deux mains. Un petit panda roux malicieux le regarde depuis le coin de l'écran, et un émoji skieur flotte au-dessus du champ.

**A06** :

> Carte 6, même style, même personnage, mêmes règles : sur une scène de cabaret, un magicien en haut-de-forme vu de dos fait apparaître des foulards d'un chapeau. Le 99+, debout à côté de lui, se tient la bouche fermée à deux mains, les joues gonflées, les yeux exorbités, en train de se retenir de faire le moindre bruit.

**A07** :

> Carte 7, même style, même personnage, mêmes règles : au crépuscule, sur un chemin à flanc de colline au-dessus des lumières d'une ville, Le 99+ danse sous un réverbère avec un chapeau de claquettes, une jambe en l'air. Le ciel est violet et bleu, avec les premières étoiles.

**A08** :

> Carte 8, même style, même personnage, mêmes règles : sur une petite scène, à côté d'un énorme ampli de guitare d'où sortent des ondes en zigzag, Le 99+ se bouche les oreilles, les yeux plissés. Une guitare électrique est posée sur son support, des câbles serpentent au sol.

**A09** :

> Carte 9, même style, même personnage, mêmes règles : dans un chalet de montagne, Le 99+ en doudoune de ski, masque relevé sur le front, tape sur un ordinateur portable posé sur une table en bois. Par la fenêtre, des montagnes enneigées. À côté de lui, une petite armoire de serveur porte des chaînes à neige.

**A10** :

> Carte 10, même style, même personnage, mêmes règles : Le 99+ se tient devant une grande horloge qui marque l'heure d'ouverture, et une avalanche de centaines de petites enveloppes et de bulles de notification déferle vers lui comme une vague de neige. Il écarte les bras pour la retenir, l'air pour une fois débordé.

**A11** :

> Carte 11, même style, même personnage, mêmes règles : dans une salle de gala avec des lustres dorés et une piste de danse, Le 99+ porte un nœud papillon noir et tient une coupe de jus d'orange. Dans son autre main, un téléphone vibre avec de petits traits de vibration, et il fait semblant de ne pas le voir.

**A12** :

> Carte 12, même style, même personnage, mêmes règles : sur une piste de ski verte ensoleillée, Le 99+ en tenue de ski fait une chute spectaculaire, les skis croisés en l'air, la neige qui gicle. Un panneau de piste vert est planté à côté. Dans le fond, des sapins et des montagnes.

**A13** :

> Carte 13, même style, même personnage, mêmes règles : au lever du soleil, au sommet d'une montagne enneigée, Le 99+ est assis paisiblement sur la neige, sa pastille rouge toujours là, un téléphone posé à côté de lui avec l'écran éteint. Il contemple une mer de nuages dorés, l'air serein pour la première fois.

---

## Études · Le Poly

**Message de départ** (avec `boss-poly.png` joint, suivi de la carte E01) :

> Je vais te demander une série de 13 cartes à collectionner, une image par message, toutes avec le personnage de l'image jointe : « Le Poly ». Règles pour toute la série : exactement le style de l'image jointe (illustration vectorielle plate, cartoon drôle et pas effrayant, contours sombres nets et épais, ombrage à plat sans dégradé, sans texture et sans reflet brillant) ; le personnage garde toujours sa forme, ses couleurs et ses détails ; chaque carte est une scène complète avec un vrai décor coloré, jamais un fond uni ; le personnage est au premier plan, bien lisible ; format carré, avec une marge d'environ 8 % sur les bords ; pas de cadre, pas de bordure, pas de titre ; aucun texte dans l'image, sauf celui que je donne entre guillemets ; aucune personne reconnaissable : s'il y a des humains, on ne voit que leurs mains ou leur dos.

| Code | Gagnée à | Titre | Junior |
|---|---|---|---|
| E01 | Installation | Le Poly débarque à l'UTC | Il a fait sa rentrée en même temps que toi. Lui redouble chaque semestre. |
| E02 | Niveau 2 | Le Poly au TD de 8 h | Il était là avant toi, et il s'en vante. |
| E03 | Niveau 3 | Le Poly dans l'espace vectoriel | Un espace vectoriel, ce n'est pas l'espace. Il a acheté le casque quand même. |
| E04 | Niveau 4 | Le Poly à la BU, veille de médian | Veille de médian. On révise ce qui tombe, et on dort avant minuit. |
| E05 | Niveau 5 | Le Poly face au déterminant | Trois lignes, trois colonnes, un seul vainqueur. Ce n'est pas la matrice. |
| E06 | Niveau 6 | Le Poly passe en 3D | Modélisé pour TN01. Même en fil de fer, il a l'air fâché. |
| E07 | Niveau 7 | Le Poly dans la boucle infinie | Il tourne depuis mardi. Quelqu'un a oublié la condition d'arrêt. |
| E08 | Niveau 8 | Le Poly monte une boîte | Un projet d'entreprise en GE15. Il veut vendre des photocopies. |
| E09 | Niveau 9 | Le Poly et l'humour britannique | Oral d'anglais sur l'humour anglais. Il a fait rire, sans le vouloir. |
| E10 | Niveau 10 | Le Poly sous la neige des finaux | Finaux de janvier. Tu les as préparés, et il le sait. |
| E11 | Niveau 11 | Le Poly en intersemestre | Rangé dans un carton pour deux semaines. Il proteste. |
| E12 | Niveau 12 | Le Poly et le panneau GI | Génie Informatique au bout du chemin. Il a essayé de cacher le panneau. |
| E13 | Niveau 13 | Le Poly rend les armes | Fin d'année. Il rend les armes, et ses agrafes. |

**E01** :

> Carte 1 : Le Poly arrive avec une valise à roulettes devant un bâtiment universitaire moderne en verre et en béton, un matin d'automne. Des feuilles orange tombent des arbres, des vélos sont garés devant l'entrée, et il tient un plan du campus à l'envers.

**E02** :

> Carte 2, même style, même personnage, mêmes règles : dans une salle de TD encore vide au petit matin, Le Poly est assis tout seul au premier rang, une tasse de café fumante devant lui, les yeux cernés mais l'air fier d'être le premier. Par la fenêtre, le ciel est encore bleu nuit, et l'horloge murale indique 8 h.

**E03** :

> Carte 3, même style, même personnage, mêmes règles : Le Poly flotte dans l'espace avec un casque d'astronaute rond sur la tête, entouré de flèches de couleur qui partent d'un même point comme des constellations, sur un fond bleu nuit étoilé. Il a l'air émerveillé et un peu perdu.

**E04** :

> Carte 4, même style, même personnage, mêmes règles : le soir, dans une bibliothèque universitaire calme, Le Poly est assis à une grande table sous une lampe de bureau, entouré de piles de livres et de fiches, concentré, un surligneur à la main. Une horloge murale indique 22 h, une bouteille d'eau est posée à côté de lui.

**E05** :

> Carte 5, même style, même personnage, mêmes règles : Le Poly, un stylo brandi comme une épée, affronte une matrice géante de trois lignes et trois colonnes de gros chiffres, encadrée par deux immenses crochets qui ressemblent aux barreaux d'une arène. Pose de combat héroïque, petites étoiles d'impact.

**E06** :

> Carte 6, même style, même personnage, mêmes règles : sur un grand écran d'ordinateur, une version en fil de fer bleu du Poly tourne dans un logiciel de modélisation 3D, entourée d'engrenages et de pièces mécaniques en vue technique. Le vrai Poly, devant l'écran, regarde son double avec méfiance.

**E07** :

> Carte 7, même style, même personnage, mêmes règles : Le Poly court à toute vitesse sur une immense flèche courbe qui forme une boucle fermée, comme un hamster dans sa roue, haletant. Autour de lui, des formes d'organigramme (losanges, rectangles, flèches) flottent sur un fond pastel.

**E08** :

> Carte 8, même style, même personnage, mêmes règles : Le Poly, en veste de costume un peu trop grande, présente un projet devant un tableau blanc, tenant un long ticket de caisse jaune qui se déroule jusqu'au sol. À côté de lui, un réfrigérateur ouvert rempli de légumes. Le dos de trois membres d'un jury est visible au premier plan.

**E09** :

> Carte 9, même style, même personnage, mêmes règles : Le Poly en veste de tweed marron, trop petite pour lui, tient un ours en peluche dans les bras et fait une grimace comique devant un micro, sur une petite estrade de salle de classe. Une tasse de thé fume sur la table, une petite voiture verte jouet est posée à côté.

**E10** :

> Carte 10, même style, même personnage, mêmes règles : dans une grande salle d'examen aux rangées de tables bien alignées, Le Poly se tient droit et tremblant derrière une table, tandis que de gros flocons de neige tombent derrière les hautes fenêtres. Des copies blanches et des stylos sont prêts sur chaque table.

**E11** :

> Carte 11, même style, même personnage, mêmes règles : Le Poly est coincé dans un carton de déménagement ouvert, seuls ses yeux furieux et ses bras en trombones dépassent. Le carton est posé dans un couloir d'université désert, avec un tas de neige qui fond devant la porte et un calendrier de février au mur.

**E12** :

> Carte 12, même style, même personnage, mêmes règles : à un carrefour de chemins dans un paysage de printemps, un poteau indicateur porte plusieurs flèches en bois. Le Poly tente, en se mettant sur la pointe des pieds, de cacher avec son corps la flèche principale. Seul texte, sur cette flèche : « GI ».

**E13** :

> Carte 13, même style, même personnage, mêmes règles : en plein soleil d'été, Le Poly, plus mince et tout fripé, agite un drapeau blanc fait d'une feuille de papier au sommet d'une pile de polycopiés terminés. Des agrafes tombent de lui comme des confettis, l'air soulagé et vaincu.

---

## Perso · La Flemme

**Message de départ** (avec `boss-flemme.png` joint, suivi de la carte P01) :

> Je vais te demander une série de 13 cartes à collectionner, une image par message, toutes avec le personnage de l'image jointe : « La Flemme ». Règles pour toute la série : exactement le style de l'image jointe (illustration vectorielle plate, cartoon drôle et pas effrayant, contours sombres nets et épais, ombrage à plat sans dégradé, sans texture et sans reflet brillant) ; le personnage garde toujours sa forme, ses couleurs et ses détails ; chaque carte est une scène complète avec un vrai décor coloré, jamais un fond uni ; le personnage est au premier plan, bien lisible ; format carré, avec une marge d'environ 8 % sur les bords ; pas de cadre, pas de bordure, pas de titre ; aucun texte dans l'image, sauf celui que je donne entre guillemets ; aucune personne reconnaissable : s'il y a des humains, on ne voit que leurs mains ou leur dos.

| Code | Gagnée à | Titre | Junior |
|---|---|---|---|
| P01 | Installation | La Flemme prend le canapé | Elle est arrivée avec ses affaires. Elle n'a pas l'air de partir. |
| P02 | Niveau 2 | La Flemme et le réveil | Elle a appuyé sur Répéter six fois. J'ai compté. |
| P03 | Niveau 3 | La Flemme fait les courses | Courses du dimanche. Elle s'est fait porter jusqu'au rayon des chips. |
| P04 | Niveau 4 | La Flemme aux fourneaux | Pâtes, quatrième soir d'affilée. Elle appelle ça une routine. |
| P05 | Niveau 5 | La Flemme dans la Corbeille | 1,7 Go à vider. Elle s'y est installée. |
| P06 | Niveau 6 | La Flemme sous la pluie de Compiègne | Il pleut. Elle a annulé une sortie que personne n'avait prévue. |
| P07 | Niveau 7 | La Flemme et le portrait du CV | Mur uni, lumière du jour. Elle a pris ta place sur le CV. |
| P08 | Niveau 8 | La Flemme découvre le bouton GO | Elle a vu le bouton GO. Elle fait semblant de ne pas l'avoir vu. |
| P09 | Niveau 9 | La Flemme fête Noël | Vacances de Noël. Elle a demandé une deuxième couverture. |
| P10 | Niveau 10 | La Flemme et les bonnes résolutions | Bonnes résolutions de janvier. Elle a tenu jusqu'au 2. |
| P11 | Niveau 11 | La Flemme et le Wrapped | Ton Wrapped est plein. Le sien tient sur un écran. |
| P12 | Niveau 12 | La Flemme et le premier soleil | Premier soleil du printemps. Elle a sorti le canapé dehors. |
| P13 | Niveau 13 | La Flemme prend sa retraite | Retraite anticipée. Je lui ai payé le billet. |

**P01** :

> Carte 1 : La Flemme s'installe confortablement sur un vieux canapé dans un petit appartement d'étudiant, une télécommande dans une main et un saladier de pop-corn sur le ventre, ses valises encore posées à côté d'elle. Décor : une étagère de livres, une plante, des rideaux, une lumière de fin d'après-midi.

**P02** :

> Carte 2, même style, même personnage, mêmes règles : au petit matin, dans une chambre encore sombre, La Flemme, un masque de sommeil remonté sur le front, écrase de tout son poids le bouton d'un réveil qui sonne en tremblant sur la table de nuit. Elle a les yeux fermés et un sourire satisfait.

**P03** :

> Carte 3, même style, même personnage, mêmes règles : dans l'allée d'un supermarché, La Flemme est allongée dans un chariot de courses poussé par deux mains qui entrent dans l'image par le côté. Elle pointe paresseusement du doigt un rayon rempli de paquets de chips colorés. Une liste de courses froissée dépasse du chariot.

**P04** :

> Carte 4, même style, même personnage, mêmes règles : dans une petite cuisine d'étudiant, La Flemme remue d'une main molle une casserole de pâtes qui déborde, tout en regardant un téléphone posé contre le mur, une recette affichée à l'écran. Un paquet de pâtes vide et une pile de vaisselle sont à côté d'elle.

**P05** :

> Carte 5, même style, même personnage, mêmes règles : La Flemme est confortablement allongée à l'intérieur d'une immense corbeille de bureau en métal grillagé, pleine de dossiers, de feuilles froissées et de vieux fichiers, comme dans un bain moussant. Une grosse flèche de souris géante essaie de la tirer dehors, en vain.

**P06** :

> Carte 6, même style, même personnage, mêmes règles : un jour de pluie, La Flemme est assise contre une fenêtre ruisselante, enroulée dans un plaid, une tasse de thé à la main. Dehors, une rue de petite ville française avec des maisons à colombages et, au loin, une forêt sous la pluie. Une paire de baskets attend près de la porte, jamais enfilée.

**P07** :

> Carte 7, même style, même personnage, mêmes règles : La Flemme prend la pose, très fière, devant un mur uni de couleur claire, éclairée par la lumière douce d'une fenêtre. Un téléphone sur un petit trépied la prend en photo. Elle fait un sourire de mannequin, une main dans les cheveux.

**P08** :

> Carte 8, même style, même personnage, mêmes règles : La Flemme tient un téléphone dont l'écran sombre affiche un seul gros bouton ambre lumineux. Elle détourne la tête, les yeux plissés, et cache l'écran avec son autre main comme si la lumière lui faisait peur.

**P09** :

> Carte 9, même style, même personnage, mêmes règles : La Flemme est ensevelie sous une montagne de cadeaux emballés et de deux couvertures, à côté d'un sapin de Noël décoré, seuls ses yeux mi-clos et sa main qui tient une tasse de chocolat chaud dépassent. Par la fenêtre, de la neige tombe la nuit.

**P10** :

> Carte 10, même style, même personnage, mêmes règles : La Flemme dort profondément, la bouche ouverte, sur une très longue liste de bonnes résolutions qui se déroule jusqu'au sol, une haltère posée intacte à côté d'elle. Un calendrier mural affiche le mois de janvier, avec le 1er et le 2 barrés.

**P11** :

> Carte 11, même style, même personnage, mêmes règles : La Flemme, avachie, regarde sur un téléphone un écran rempli de quatre grandes barres de couleur (vert, violet, bleu, rose) qui montent très haut, comme un vumètre. À côté, un deuxième téléphone, le sien, n'affiche qu'une toute petite barre rose. Elle fait la moue.

**P12** :

> Carte 12, même style, même personnage, mêmes règles : dans un parc au printemps, sous des arbres en fleurs roses et blanches, La Flemme est allongée sur un canapé qu'elle a sorti sur la pelouse, des lunettes de soleil sur le nez, un verre de limonade à la main. Des oiseaux et des pétales volent autour d'elle.

**P13** :

> Carte 13, même style, même personnage, mêmes règles : La Flemme, avec un chapeau de paille et des lunettes de soleil, quitte un appartement parfaitement rangé en tirant une valise à roulettes, et fait un signe d'adieu de la main. Dans sa poche dépasse un billet de train vers la plage. Derrière elle, le canapé est vide et bien arrangé.

---

## Rares · les 4 boss

**Message de départ** (avec les 4 boss debout joints : `boss-bug.png`, `boss-99.png`, `boss-poly.png`, `boss-flemme.png`, suivi de la carte R01) :

> Je vais te demander une série de 18 cartes à collectionner rares, une image par message, avec les quatre personnages des images jointes : « Le Bug » (l'insecte vert), « Le 99+ » (la bulle violette à la pastille rouge « 99+ »), « Le Poly » (la pile de polycopiés bleus) et « La Flemme » (le gros coussin rose). Règles pour toute la série : exactement le style des images jointes (illustration vectorielle plate, cartoon drôle et pas effrayant, contours sombres nets et épais, ombrage à plat sans dégradé, sans texture et sans reflet brillant) ; chaque personnage garde toujours sa forme, ses couleurs et ses détails ; chaque carte est une scène complète avec un vrai décor coloré, jamais un fond uni ; les personnages sont au premier plan, bien lisibles ; format carré, avec une marge d'environ 8 % sur les bords ; pas de cadre, pas de bordure, pas de titre ; aucun texte dans l'image en dehors de « 99+ », sauf celui que je donne entre guillemets ; aucune personne reconnaissable : s'il y a des humains, on ne voit que leurs mains ou leur dos.

| Code | Gagnée avec | Titre | Junior |
|---|---|---|---|
| R01 | Badge Premier commit | La première carte tombe | Première carte terminée. Les quatre l'ont appris en même temps. |
| R02 | Badge Lève-tôt | La Flemme voit le lever du soleil | Avant 8 h. Elle ne savait même pas que le soleil se levait. |
| R03 | Badge Couche-tard | Minuit passé | Après minuit. Le Bug adore cette heure-là, moi beaucoup moins. |
| R04 | Badge Mieux vaut tard | L'arrivée de l'escargot | Sept jours de retard, mais terminée. Je compte, je ne juge pas. |
| R05 | Badge Semaine parfaite | Sept jours, sept K.O. | Sept jours d'affilée. Ils ont demandé une trêve. |
| R06 | Badge Tous terrains | Les quatre terrains | Les quatre tableaux dans la même semaine. Personne n'était à l'abri. |
| R07 | Badge Inbox zéro | Le 99+ à zéro | Cinq boîtes à zéro. Il ne savait pas que c'était possible. |
| R08 | Badge Perfect | Sans une égratignure | Soixante minutes sans lâcher. Ton cœur n'a pas une rayure. |
| R09 | Badge Chasseur de boss | Le tableau de chasse | Dix boss battus. Ils ont fait encadrer leurs photos. |
| R10 | Badge Machine à sous | Jackpot | Dix tirages, dix départs. La machine n'a jamais vu ça. |
| R11 | Badge Médian en vue | Le Poly aux jumelles | Trois sessions avant le médian. Le Poly t'a vu venir de loin. |
| R12 | Badge Plume | Le journal sous clé | Trente lignes de journal. Le 99+ n'a toujours pas trouvé la clé. |
| R13 | Junior passe Assistant | La cravate | Promu Assistant. Je me suis acheté une cravate avec ton XP. |
| R14 | Junior passe Bras droit | La porte du bras droit | Bras droit. Ils frappent avant d'entrer, maintenant. |
| R15 | Junior passe Majordome | Servis sur un plateau | Majordome. Le service est compris. |
| R16 | Série de 30 jours | La tour de trente jours | Trente jours d'affilée. Ils ont tout essayé pour la faire tomber. |
| R17 | 25 boss battus | La salle d'attente | Vingt-cinq K.O. Ils ont pris un ticket. |
| R18 | 50 boss battus | Le drapeau blanc | Cinquante K.O. Ils négocient. |

**R01** :

> Carte 1 : les quatre personnages, alignés côte à côte, découvrent bouche bée une grande fiche de tâche épinglée au mur, sur laquelle vient de frapper un gros tampon rouge. Seul texte, sur le tampon : « FAIT ». Le Bug a les antennes dressées, Le 99+ crie, Le Poly lâche un stylo, La Flemme ouvre enfin les yeux en grand.

**R02** :

> Carte 2, même style, mêmes personnages, mêmes règles : La Flemme, seule, sur le balcon d'un petit immeuble, découvre stupéfaite un lever de soleil orange et rose sur les toits d'une petite ville. Elle tient son plaid serré contre elle, la bouche grande ouverte, comme si elle voyait le soleil pour la première fois.

**R03** :

> Carte 3, même style, mêmes personnages, mêmes règles : la nuit, dans une chambre éclairée seulement par une petite lampe de bureau, Le Bug, bien réveillé et ravi, est assis sur un bureau à côté d'un ordinateur, tandis que La Flemme dort par terre sur un coussin. Par la fenêtre, un grand croissant de lune. Une horloge indique minuit et quart.

**R04** :

> Carte 4, même style, mêmes personnages, mêmes règles : Le Poly, assis sur le dos d'un gros escargot, franchit enfin la ligne d'arrivée d'une course, tendue entre deux piquets. Les trois autres personnages, endormis sur des chaises pliantes à côté de la ligne, se réveillent en sursaut. Des toiles d'araignée sont accrochées aux chaises.

**R05** :

> Carte 5, même style, mêmes personnages, mêmes règles : devant un grand calendrier mural dont les sept jours de la semaine sont tous cochés d'une grosse coche verte, les quatre personnages sont étendus K.O. au sol, côte à côte, les yeux en croix, et l'un d'eux agite un petit drapeau blanc.

**R06** :

> Carte 6, même style, mêmes personnages, mêmes règles : l'image est partagée en quatre cases égales, comme une bande dessinée, chacune avec son décor : Le Bug K.O. devant un ordinateur, Le 99+ K.O. sur une scène de cabaret, Le Poly K.O. dans un amphithéâtre, La Flemme K.O. sur un canapé. Tous ont les yeux en croix.

**R07** :

> Carte 7, même style, mêmes personnages, mêmes règles : Le 99+ se détend dans un hamac tendu entre deux palmiers, des lunettes de soleil sur les yeux. Sa pastille, exceptionnellement grise, affiche « 0 » au lieu de « 99+ ». À côté de lui, un bac à courrier complètement vide. Mer turquoise et sable blanc derrière.

**R08** :

> Carte 8, même style, mêmes personnages, mêmes règles : sur une scène sombre de jeu vidéo, un grand cœur rouge parfaitement intact flotte au centre, entier, sans une fissure, tandis que Le Poly gît K.O. au sol devant lui, les yeux en croix. Le Bug, Le 99+ et La Flemme regardent la scène depuis le bord, impressionnés.

**R09** :

> Carte 9, même style, mêmes personnages, mêmes règles : sur le mur d'un salon douillet, une collection de dix cadres photo dorés montre les quatre personnages en pose K.O., les yeux en croix. Devant le mur, les quatre personnages en vrai, bras croisés et vexés, regardent leurs propres portraits.

**R10** :

> Carte 10, même style, mêmes personnages, mêmes règles : une machine à sous de casino rétro dont les trois rouleaux montrent chacun un petit visage de boss. La Flemme tire le levier de tout son poids, Le 99+ se tient la tête, Le Bug et Le Poly regardent des pièces d'or jaillir de la machine.

**R11** :

> Carte 11, même style, mêmes personnages, mêmes règles : Le Poly, debout sur une petite colline, regarde au loin avec une grosse paire de jumelles. Au loin, un calendrier géant planté dans le paysage montre une date entourée en rouge. Les trois autres personnages, derrière lui, attendent son rapport.

**R12** :

> Carte 12, même style, mêmes personnages, mêmes règles : Le 99+ tente de forcer avec un trombone le cadenas d'un petit journal intime relié en cuir, posé sur une table de nuit à côté d'une plume et d'un encrier. Le Bug fait le guet, La Flemme a abandonné et dort à moitié, Le Poly tient une lampe de poche.

**R13** :

> Carte 13, même style, mêmes personnages, mêmes règles : les quatre personnages se pressent devant un panneau d'affichage en liège où une affiche annonce une promotion, avec une cravate rouge épinglée à côté. Seul texte, en gros sur l'affiche : « ASSISTANT ». Ils ont l'air inquiets.

**R14** :

> Carte 14, même style, mêmes personnages, mêmes règles : les quatre personnages font la queue, nerveux, devant une porte de bureau en bois sombre avec une plaque dorée. Le Bug lève une patte pour frapper poliment. Seul texte, sur la plaque : « BRAS DROIT ».

**R15** :

> Carte 15, même style, mêmes personnages, mêmes règles : une main gantée de blanc de majordome, qui entre dans l'image par le côté, présente un grand plateau d'argent sur lequel les quatre personnages, en miniature, sont assis serrés les uns contre les autres, l'air résigné, avec une petite cloche à plat posée à côté.

**R16** :

> Carte 16, même style, mêmes personnages, mêmes règles : une tour très haute de trente pages de calendrier empilées, chacune marquée d'une coche verte, se dresse au centre. Les quatre personnages s'acharnent pour la faire tomber : Le Bug la pousse, Le 99+ crie dessus, Le Poly lui lance des trombones, La Flemme s'est adossée contre elle. La tour ne bouge pas.

**R17** :

> Carte 17, même style, mêmes personnages, mêmes règles : dans une salle d'attente d'infirmerie, les quatre personnages couverts de pansements et de bandages sont assis sur des chaises en plastique, chacun tenant un petit ticket numéroté. Seul texte, sur un écran au mur : « 25 ».

**R18** :

> Carte 18, même style, mêmes personnages, mêmes règles : au sommet d'une petite colline, les quatre personnages, cabossés et pansés, agitent ensemble un grand drapeau blanc vers le spectateur, l'air de vouloir négocier. Le Poly tient une feuille qui ressemble à un traité de paix.
