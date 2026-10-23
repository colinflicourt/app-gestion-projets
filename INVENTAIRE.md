# Inventaire (étape 2)

> Fait le 2026-10-07. Ce que l'app va afficher (tes données réelles) et ce qu'on peut réutiliser. Sert de base au contenu (étape 4), à la maquette (étape 6) et à la spec (étape 8a).

---

## 1. La to-do : les 4 tableaux Trello Jarvis

Espace « Jarvis · Colin ». Identifiants des tableaux, colonnes et étiquettes dans `.claude/scripts/trello-ids.json`.

| Tableau | Étiquettes | Cartes ouvertes au 07/10 |
|---|---|---|
| QYRIS | Urgent, Site, LinkedIn, Formations, Sécurité, ROADY | 7 |
| Asso | Urgent, SKIUT, Gala Etuville | 3 |
| Études | Urgent, MT23, GE15 | 4 (dont 1 En cours) |
| Perso | Urgent, CV, Ménage | 5 (dont la carte de l'app, En cours) |

Les 4 tableaux ont les mêmes colonnes : À faire, En cours, En attente, Fait.

**Ce que contient une carte** (ce que l'app doit savoir afficher) :
- un titre souvent long : « Domaine : action », par exemple « MT23 : préparer le médian (mer 21/10, 19h15 à 20h45) » ;
- une échéance éventuelle, parfois avec une heure (08:30, 19:15, 07:45) ;
- l'étiquette Urgent (aucune carte ne l'a aujourd'hui) et une étiquette de projet ;
- une description de plusieurs lignes, avec chemins de fichiers et liens ;
- une checklist de 1 à 6 éléments, dont l'avancement s'affiche (« 2/4 »).

**Ce que ça implique** :
- **Retards réels** : la carte LinkedIn de QYRIS est en retard depuis le 06/10, et celle de Perso depuis le 05/10. Il faut un état « en retard » bien visible, sans culpabiliser.
- **« Je fais quoi maintenant ? »** : aucune carte n'a d'estimation de durée. L'app propose donc la **prochaine étape non cochée de la checklist**, plus petite qu'une carte entière. Exemple : « Formation 2 : tester les exercices sur les comptes gratuits ».
- **XP** : la colonne Fait de chaque tableau sert à repérer les cartes finies ailleurs (par Jarvis ou dans l'app Trello).
- **Titres longs** : il faut deux lignes au plus sur téléphone, avec le domaine en couleur plutôt que répété.

---

## 2. L'emploi du temps : Google Agenda

| Agenda | Contenu |
|---|---|
| Principal (`colin.flicourt@gmail.com`) | **Vide** du 07/10 au 21/10. Alerte par défaut : 30 min avant |
| « UTC · Emploi du temps A26 » | 159 événements du 25/08/2026 au 29/01/2027 |
| « Jours fériés et autres fêtes en France » | Agenda public de Google |

**Format des créneaux UTC** : `UV - Type n`, avec la salle dans le lieu :
- exemples : `NF93 - TD 1` en FA400, `GE15 - Cours 1` en FA201, `LA14 - TD 2` en FC114 ;
- 7 séries de 16 ou 17 créneaux : GE15 (cours et TD), LA14, MT23, NF05, NF93 et TN01 ;
- le code UV (4 caractères), le type et la salle se lisent sans ambiguïté. On peut donc colorer par UV et afficher « TN01 · TD · FA510 ».

**Journées entières** à traiter à part :
- « Vacances universitaires » (21 jours) ;
- « Jour d'examen » : 15 jours, soit du 20 au 24/10, le 02/11 et du 07 au 15/01 ;
- « Ferié » : 2 jours, dont le jeudi 15/10, qui n'est pourtant pas un jour férié national. C'est un jour propre à l'UTC ;
- « Devient un samedi », « Devient un mercredi », « Devient un jeudi » : jours où l'UTC applique l'emploi du temps d'un autre jour. **À afficher en bandeau**, c'est facile à rater.

**Semaine type** (12 au 16/10) :

| Jour | Créneaux |
|---|---|
| Lundi | LA14 TD à 16 h 30 |
| Mardi | GE15 cours à 13 h, GE15 TD à 16 h 30 |
| Mercredi | NF93 TD à 8 h, TN01 TD à 16 h 30 |
| Jeudi | Férié UTC, donc pas de TD de MT23 |

Il y a de grands créneaux libres entre les cours : c'est là que « Je fais quoi maintenant ? » prend tout son sens.

**Médians** : ils n'existent pas encore comme créneaux précis dans tes agendas, seuls les « Jours d'examen » y sont. On les crée avant le lot 2, avec ton accord :

| UV | Date | Horaire |
|---|---|---|
| MT23 | Mercredi 21/10 | 19 h 15 à 20 h 45 |
| NF93 | Lundi 02/11 | 7 h 45 à 9 h 15 |
| TN01 | Vendredi 06/11 | 16 h 45 à 18 h 00 |

**Heure** : tout est en Europe/Paris, et le passage à l'heure d'hiver tombe le dimanche 25/10/2026.

---

## 3. Les mails

| Boîte | Accès prévu | État au 07/10 |
|---|---|---|
| UTC (etu.utc.fr) | Redirigée vers Gmail depuis le webmail UTC, section « UTC » dans l'app | Redirection à faire par toi (avant le lot 3) |
| Gmail | IMAP, mot de passe d'application | Validation en 2 étapes à vérifier, mot de passe à créer |
| iCloud | IMAP, mot de passe d'application Apple | À créer |
| colin@qyris.fr | IMAP OVH | Mot de passe connu de toi |
| contact@qyris.fr | IMAP OVH | Jamais testée : la carte LinkedIn demande déjà un mail de test |

---

## 4. Ce qu'on réutilise

### Code

| Quoi | Où | Pour |
|---|---|---|
| Connexion (bcrypt, hash factice, 10 essais par 15 min) | `projects/cavanicos/site/api/auth.php`, `includes/account-rules.php` | Connexion de l'app, adaptée à un seul utilisateur et à une session de 90 jours |
| Accès Trello (`lire`, `ajouter`, `terminer`, `cocher`…) | `.claude/scripts/trello.py` (490 lignes, Python) | Porté en PHP côté serveur |
| Identifiants Trello | `.claude/scripts/trello-ids.json` | Liste blanche des 4 tableaux, colonnes, étiquettes |
| Aperçu local sans cache | `etudes/GE15/a-point/outils/apercu.py` | Transposé en `php -S` avec routeur |
| Détourage sur fond magenta | `asso/skiut/candid-colin/tools/remove-bg.py` | Les 4 boss de la session focus |

### Visuels

- **Candid Colin** (`asso/skiut/candid-colin/site/assets/img/`, images DALL-E générées par toi) :
  - `skieur.png` (159 × 240, détouré) ;
  - `piste.png` (512 × 512, texture qui se répète verticalement) ;
  - `sapin.png`, `rocher.png`, `flocon.png`, `fanion.png`, `bulle.png`, `menu-bg.jpg`.
  - Prévus pour la session focus au ski, qui est devenue un combat contre un boss le 2026-10-07 : plus utilisés.
- **Charte du CV** (`projects/portfolio/DESIGN.md`), pour la piste « HEAD » :
  - polices : Archivo (titres larges) et Martian Mono (étiquettes) ;
  - fond `#F1F3F2`, encre `#121518` ;
  - six couleurs de branches : études `#1F4DD8`, travail `#E4572E`, assos `#8B3FD1`, compétences `#0E9660`, projets `#D63384`, scène `#D9A00B`.
- **Lineup du Cabaret** (`asso/cabaret-utc/lineup/construire_lineup.py`), pour la piste « La lineup » :
  - nuit `#1E2047` et `#2E3266`, or `#F2C14E` ;
  - une couleur par type de numéro : musique `#3B6FB6`, danse `#C2457A`, magie `#7B4FB3`, discours `#D9A21B`, repas `#2E8B57` ;
  - hauteur des lignes proportionnelle à la durée.

### Le ton

- Les répliques de Candid Colin (`asso/skiut/candid-colin/site/js/dialogue.js`, 24 phrases) donnent le registre de Jarvis : de l'autodérision, un peu d'arrogance, de la tendresse. Exemples : « Je sais débugger un site, pas une chute. », « Ma vraie piste de prédilection, c'est VS Code, pas celle-ci. ».
- Le brief du matin et les répliques du boss reprendront ce registre, avec des phrases écrites pour l'app.
