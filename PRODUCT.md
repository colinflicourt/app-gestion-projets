# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Décidé par Colin dans le plan du 2026-10-07 :
- côté iPhone : une web app installable (manifeste et service worker) en HTML, CSS et JS natifs, sans framework ni build ;
- côté serveur : une API en PHP 8 sans Composer, avec une base MySQL ;
- hébergement mutualisé OVH, sur le sous-domaine jr.qyris.fr ;
- une bibliothèque éventuelle (GSAP par exemple) est copiée dans `vendor/` avec une version figée. Aucun CDN, aucun outil de suivi.

## Users

Un seul utilisateur : Colin Flicourt.
- Étudiant en 2e année à l'UTC (Compiègne).
- Auto-entrepreneur : QYRIS, formations à l'IA et à Claude Code.
- Engagé dans plusieurs associations : Cabaret UTC, SKIUT, Gala Etuville.
- Il travaille tous les jours dans Claude Code avec son assistant « Jarvis ».

Son besoin sur l'iPhone : savoir d'un coup d'œil ce qui l'attend et quoi faire maintenant, sans passer par cinq apps.

## Product Purpose

Jarvis Jr. réunit dans une seule app installée sur l'iPhone :
- **l'emploi du temps** : Google Agenda, avec l'agenda principal et « UTC · Emploi du temps A26 » ;
- **la to-do** : les 4 tableaux Trello Jarvis (QYRIS, Asso, Études, Perso) ;
- **les rappels par notification** : avant les cours, la veille des échéances, à J-7, J-3 et J-1 des médians, et un brief du matin ;
- **les mails, en lecture seule** : UTC (via Gmail), Gmail, iCloud, colin@ et contact@qyris.fr.

Il ajoute de quoi donner envie d'avancer :
- « Je fais quoi maintenant ? » ;
- XP, niveaux, séries et badges ;
- un journal et un Wrapped du dimanche ;
- une session focus contre un boss de fin (le skieur prévu au départ a été jugé pas assez drôle).

**Ce qui fera son succès** :
- Colin l'ouvre plusieurs fois par jour au lieu de jongler entre ses apps ;
- il ne rate plus un cours, une échéance ou un médian ;
- ses cartes Trello avancent.

## Positioning

C'est le petit frère de Jarvis, l'assistant Claude Code de Colin. Quatre choses le distinguent :
- **Les mêmes tableaux Trello que Jarvis** : ce que Colin note sur le téléphone, Jarvis le voit à sa prochaine session, et inversement.
- **Le vrai emploi du temps UTC** : chaque UV avec son type et sa salle, et les jours où l'UTC applique l'emploi du temps d'un autre jour.
- **Une suggestion qui tient dans le temps libre** : la prochaine étape de checklist qui rentre avant le prochain cours.
- **Une voix** : celle de Junior, piquante.

## Operating Context

**Les moments d'usage** :
- au réveil, au lit, dans la pénombre : le brief du matin ;
- entre deux cours, en marchant : d'une main, pendant quelques secondes ;
- en cours ou en TD : discrètement, le téléphone posé sur la table ;
- le soir au bureau : journal, session focus, organisation du lendemain.

**L'appareil** :
- iPhone 14 (390 × 844 points) en iOS 26.6 ;
- apparence **toujours en mode sombre**, taille de texte standard ;
- la web app est installée sur l'écran d'accueil : plein écran, ses propres cookies, notifications web, écran maintenu allumé pendant le focus ;
- en amphi, le réseau manque parfois : l'app affiche alors les dernières données, avec leur heure.

**Les données réelles**, détaillées dans `INVENTAIRE.md` :
- Créneaux au format « UV - Type n », avec la salle en lieu (FA201, FC114…).
- Journées entières à part :
  - « Vacances universitaires » ;
  - « Jour d'examen » ;
  - « Ferié » ;
  - « Devient un samedi », « Devient un mercredi », « Devient un jeudi ».
- Cartes Trello :
  - titres longs de la forme « Domaine : action » ;
  - échéances, parfois avec une heure ;
  - checklists de 1 à 6 éléments, étiquette Urgent.
- L'agenda principal est vide au 07/10/2026.

## Capabilities and Constraints

**Accès et sécurité** :
- Mails en lecture seule : l'app ne marque jamais un mail comme lu.
- Le mot de passe UTC n'est jamais stocké : la boîte UTC sera redirigée vers Gmail par Tuttifruti.
- L'app ne touche qu'aux 4 tableaux Trello Jarvis, jamais à Courses ni à Repas étudiant.
- Les mots de passe d'application sont saisis par Colin dans Réglages et chiffrés. Aucun secret dans le code ni dans git.
- Un seul compte, une session de 90 jours, 10 essais de mot de passe par 15 min.

**Fonctionnement** :
- Un planificateur est appelé chaque minute par cron-job.org, parce que le cron d'OVH ne tourne qu'une fois par heure.
- Fuseau Europe/Paris. Le passage à l'heure d'hiver tombe le 25/10/2026.
- Le développement se fait en 4 lots : socle et quotidien, rappels, mails, fun.

**Écarté pour l'instant** : espace auto-entrepreneur, rendus Moodle, comptes à rebours, widget d'écran d'accueil.

## Brand Commitments

**Identité** :
- Nom : **Jarvis Jr.**, appelé « Junior » dans l'app.
- Icône « Jr. », adresse jr.qyris.fr.

**Voix** (validée dans `CONTENU.md`) :
- tutoiement, première personne ;
- l'info d'abord, la pique ensuite ;
- plus piquant que Candid Colin, mais jamais sur les médians, la sécurité ou les erreurs ;
- pas d'émoji, sauf pour les humeurs du journal.

**Univers** :
- Junior monte en grade avec l'XP de Colin : Stagiaire, Assistant, Bras droit, Majordome. Le dernier est un clin d'œil à Edwin Jarvis, le majordome des Stark.
- Trois univers retenus par Colin pour la direction artistique : le dev et l'IA, la scène (le Cabaret UTC), le jeu vidéo.
- La session focus est un combat contre un boss de fin, un par domaine : Le Bug, Le 99+, Le Poly, La Flemme (choisi le 2026-10-07, à la place du skieur de Candid Colin).

**Nom Marvel** : « Jarvis » en est un. Aucun souci pour une app privée, mais on montrera seulement « Junior » si l'app est présentée un jour en public.

## Evidence on Hand

**Disponible** :
- `CONTENU.md` (validé) : libellés, notifications, phrases de Junior, barème d'XP, grades, 12 badges, Wrapped, messages d'erreur.
- `INVENTAIRE.md` : tableaux Trello, agendas, boîtes mail, éléments réutilisables.
- Les 4 boss de la session focus : à générer par Colin, avec les prompts de `VISUELS.md`.
- Charte du CV de Colin : `projects/portfolio/DESIGN.md`.
- Palette de la lineup du Cabaret : `asso/cabaret-utc/lineup/construire_lineup.py`.
- Répliques de Candid Colin : `asso/skiut/candid-colin/site/js/dialogue.js`.

**Absent, à ne pas inventer** :
- l'icône de l'app ;
- toute donnée d'usage : les chiffres d'XP et du Wrapped de `CONTENU.md` sont des exemples.

## Product Principles

1. **Le coup d'œil d'abord** : en trois secondes, Colin sait ce qui vient (prochain cours, salle, tâche).
2. **Une seule source de vérité** : Trello pour les tâches, Google pour l'agenda. L'app ne recopie rien.
3. **Honnête et sûr** :
   - rien d'inventé ;
   - mails en lecture seule ;
   - aucun mot de passe UTC ;
   - données hors ligne toujours datées.
4. **Donner envie, pas culpabiliser** : le jeu récompense l'action réelle, et Junior pique mais propose toujours une action.
5. **Personnel** : l'app de Colin, avec sa voix et ses univers, pas un produit générique.

## Accessibility & Inclusion

- Respecter le réglage de taille de texte de l'iPhone. Il est standard aujourd'hui.
- Cibles tactiles d'au moins 44 px, utilisables d'une main en marchant.
- Contrastes au niveau AA en mode sombre : lisible dans la pénombre, sans éblouir au réveil.
- Respecter le réglage « mouvement réduit » pour le tirage, le combat du focus et le Wrapped.
