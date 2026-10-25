# Régulation de l'XP de Jarvis Jr. (lot 4.1, mission M2)

Rapport pour Colin, du 25/10/2026. Il faut 5 minutes pour lire la synthèse (partie 1). Le reste est le détail.

Rien n'est appliqué : `serveur/lib/xp.php` et `JR_XP_REGLES` n'ont pas bougé. C'est toi qui décides.

---

## 1. Synthèse

### Le constat

- **Les vraies données sont très courtes** : 3,5 semaines de Trello, 4 cartes terminées, 58 étapes cochées. Elles valent environ **107 XP par semaine** avec le barème actuel, contre 475 estimés dans le § 7 de `CONTENU.md`.
- **Tes étapes cochées font l'essentiel de l'XP réelle** (68 %), pas les cartes. Tu fais peu de cartes, mais chacune a une grosse checklist (6 à 20 étapes).
- **Le barème actuel rate 3 critères sur 5 (1, 4 et 5)** : une heure de combat rapporte 17 XP (moins qu'une carte à 30), et une soirée de triche rapporte 2 300 XP. Pour les critères 1 et 5, aucun réglage des seuils ne corrige ça.
- **Sans plafond par jour, aucun barème raisonnable ne tient le critère 5.** Créer et finir une carte éclair prend 40 secondes, cocher une étape 15 secondes : en 2 heures on gagne 2 300 XP, alors qu'une heure de combat en vaut 17 à 70. Seuls des plafonds quotidiens règlent ça.

### Les 5 critères, par barème (simulation de l'année, calendrier réaliste)

| # | Critère | A : § 7 actuel | B : combat 1 XP/min | C : recommandé |
|---|---|---|---|---|
| 1 | 1 h de combat vaut au moins une carte ordinaire | NON (17 contre 30) | oui (65 contre 30) | **oui (70 contre 30)** |
| 2 | Aucune source ne dépasse 50 % d'une semaine normale | oui (étapes 46 %) | oui (combat 43 %) | **oui (combat 46 %)** |
| 3 | Un niveau toutes les 2 semaines au début, puis plus espacé | oui (5, 11, 13 jours) | oui (3, 8, 9, 13) | **oui (5, 11, 14)** |
| 4 | Niveau 13 Études vers les finaux de juin, Majordome pas avant janvier | NON (Majordome le 03/03) | NON (niveau 13 le 08/03, Majordome le 01/01) | **oui (17/06 et 31/05)** |
| 5 | Une soirée de triche rapporte moins qu'une heure de combat | NON (2 300 contre 17) | NON (2 300 contre 65) | **oui (66 contre 70)** |

Honnêteté sur le critère 4 : A et B gardent les seuils du § 7 et C a des seuils recalés. Les critères 1 et 5, eux, ne se rattrapent pas par les seuils.

### Barème recommandé (C), en une ligne par source

| Source | Aujourd'hui (§ 7) | Recommandé |
|---|---|---|
| Carte terminée, Urgent, échéance | 30, +20, +10 | **inchangé** |
| Carte éclair (moins de 10 min) | 5 | 5, **au plus 2 par jour paient** |
| Étape cochée | 5 | 5, **au plus 10 par jour paient** |
| Combat | 1 XP par tranche de 5 min | **1 XP par minute** |
| Boss battu | +5 | **+10** |
| Ligne de journal | 5 | **3, au plus 2 par jour paient** |
| Niveau n d'un domaine | 100 XP de plus à chaque niveau (niveau 13 : 7 800 XP) | **160 XP de plus à chaque niveau (niveau 13 : 12 480 XP)** |
| Grades Assistant, Bras droit, Majordome | 1 000, 4 000, 10 000 | **1 500, 6 000, 22 000** |

### Ce que tu dois trancher

1. **Les plafonds par jour** (étapes, cartes éclair, journal) : c'est ce qui rend le critère 5 possible. Ils touchent à peine un usage normal (moins de 0,4 % de l'XP de l'année simulée). Mais le moteur M1 ne les connaît pas : il faut les ajouter à `xp_rejouer()` et à son miroir JS. Oui ou non ?
2. **Les seuils sont calés sur un rythme que je suppose**, pas que je mesure (voir partie 6). Si tu acceptes C, prévois de les recaler après 3 semaines d'usage réel : un seul chiffre à changer (`pas_niveau`, et les 3 grades).
3. **Les cartes de niveau** : seul Études atteint le niveau 13 dans l'année. QYRIS plafonne au niveau 7 (mai), Perso au 8 (mai), Asso au 6 (mai). Les cartes 8 à 13 de ces trois domaines resteront invisibles. Acceptable, ou faut-il un pas plus bas pour eux ?

### Les limites à retenir

- Historique Trello de 3,5 semaines seulement, sur une période où tu installais Trello.
- Les actions de Jarvis Jr. en base MySQL (production) ne sont pas lisibles depuis le Mac : aucune donnée de combat, de journal ou de série réelle.
- Le combat, le journal et le « J'ai fini la tâche » n'existent pas encore : leurs volumes sont des hypothèses.
- Les dates du semestre P27 sont des estimations.

---

## 2. Ce que disent les données réelles

Source : Trello, tableaux QYRIS, Asso, Études, Perso, du 01/10 au 25/10/2026 (3,5 semaines). Extraction en lecture seule : identifiants, dates, colonnes, nombres. Aucun titre ni description n'a été lu ou conservé.

| Domaine | Cartes vues | Terminées | Étapes cochées | Étapes décochées | Étapes nettes par semaine |
|---|---|---|---|---|---|
| QYRIS | 9 | 1 | 8 | 2 | 1,7 |
| Asso | 5 | 1 | 7 | 1 | 1,7 |
| Études | 7 | 1 | 31 | 0 | 8,8 |
| Perso | 14 | 1 | 17 | 2 | 4,2 |
| **Total** | **35** | **4** | **63** | **5** | **16,4** |

| Constat | Mesure |
|---|---|
| Cartes terminées | 1,1 par semaine, une par domaine |
| Étapes par carte terminée | 6 à 20 |
| Jour le plus chargé en étapes | 13 coches (13/10), puis 12 (25/10) |
| Cartes Urgent | 1 sur 35 |
| Cartes avec échéance | 9 sur 35 ; sur les 4 terminées, 2 en avaient une, aucune n'a été tenue |
| Cartes éclair (finies en moins de 10 min) | 0 sur 4. 5 cartes Perso ont été créées puis archivées en moins d'un quart d'heure sans passer par Fait (rangement) : elles ne rapportent rien |

**XP que ces gestes auraient rapporté** (rejeu par le vrai moteur, `xp_rejouer`) :

| Domaine | XP | Remarque |
|---|---|---|
| QYRIS | 60 | |
| Asso | 60 | |
| Études | 185 | la moitié du total |
| Perso | 75 | |
| **Total** | **380** | soit **107 par semaine**, dont 68 % d'étapes |

À retenir : le § 7 estimait 475 XP par semaine avec 8 cartes et 30 étapes. Tu fais 1 carte et 16 étapes. Le combat et le journal, qui n'existent pas encore, devront donc porter une grande part de l'XP, ou les étapes resteront la source principale.

---

## 3. Comment la simulation est construite

`php outils/simuler-xp.php` (aucun réseau, lit `outils/donnees/historique-trello.json` et l'emploi du temps A26). Elle réutilise `xp_rejouer()`, `xp_progression()` et `xp_grade()` avec des tables de règles candidates.

**Période** : du 01/09/2026 au 30/06/2027, soit 44 semaines.

| Type de semaine | Nombre | Profil appliqué dans le scénario « calendrier » |
|---|---|---|
| Ordinaire | 27 | normale |
| Examens et semaine de révision qui précède | 12 | médians |
| Vacances | 5 | calme |

Examens A26 pris dans l'emploi du temps : 19 au 24/10, 2/11 (et 7/11 d'après ton contexte), 7 au 15/01. **P27 estimé** (absent de l'emploi du temps) : médians 12 au 17/04, finaux 14 au 25/06, vacances 30/01 au 14/02, 29/03 au 04/04 et fin juin.

**Les 3 profils de semaine** (bâtis sur le rythme mesuré par domaine) :

| Profil | Cartes finies | Étapes cochées | Séances de combat | Lignes de journal |
|---|---|---|---|---|
| Calme | 1,1 (rythme mesuré) | 16 (rythme mesuré) | 2 | 3 |
| Normale | 3,4 (mesuré × 3) | 33 (mesuré × 2) | 5 (environ 4 h) | 6 |
| Médians | 3,4 | 59 (Études × 5, autres × 2) | 9 (environ 7 h) | 5 |

Hypothèses, toutes à confirmer : 10 % de cartes Urgent, 14 % de cartes finies avant l'échéance, 10 % de cartes éclair. Une séance sur 10 : 7 victoires, 2 « J'ai fini la tâche » à 60 % de la durée, 1 forfait à 40 %. Durées de 25 à 70 minutes. Le combat est plafonné à 25 % des heures libres (43 h par semaine ordinaire d'après l'emploi du temps : 16 h de cours par semaine). Le journal est rattaché à Perso.

Trois autres scénarios servent de bornes : **calme toute l'année**, **normale toute l'année**, **médians toute l'année**. Les 5 critères sont jugés sur le scénario « calendrier ».

**Comment j'ai lu les critères** (à valider) :

| # | Test appliqué |
|---|---|
| 1 | 60 min de combat avec boss battu >= une carte de base |
| 2 | Parts par famille (cartes, étapes, combat, journal) sur la moyenne des 27 semaines ordinaires : max 50 % |
| 3 | Domaine le plus actif (Études) : au moins 3 niveaux en 6 semaines, jamais plus de 14 jours entre deux niveaux |
| 4 | Niveau 13 Études entre le 10/06 et le 30/06 ; Majordome entre le 15/04 et le 30/06 |
| 5 | Soirée de triche (voir partie 5) strictement inférieure à 1 h de combat |

---

## 4. Résultats

### Une semaine ordinaire, par barème (moyenne des 27 semaines)

| Source | A : XP | A : part | B : XP | B : part | C : XP | C : part |
|---|---|---|---|---|---|---|
| Carte terminée | 84 | 24 % | 84 | 17 % | 84 | 16 % |
| Bonus urgent et échéance | 11 | 3 % | 11 | 2 % | 11 | 2 % |
| Carte éclair | 1 | 0 % | 1 | 0 % | 1 | 0 % |
| Étape cochée | 163 | 46 % | 163 | 32 % | 163 | 32 % |
| Combat (minutes) | 30 | 8 % | 148 | 29 % | 148 | 29 % |
| Boss battu | 17 | 5 % | 17 | 3 % | 35 | 7 % |
| « J'ai fini la tâche » (minutes et boss) | 12 | 3 % | 41 | 8 % | 46 | 9 % |
| Forfait | 1 | 0 % | 9 | 2 % | 9 | 2 % |
| Ligne de journal | 30 | 9 % | 30 | 6 % | 18 | 3 % |
| **Total par semaine** | **350** | | **504** | | **515** | |
| Famille la plus lourde | étapes 46 % | | combat 43 % | | combat 46 % | |

L'XP de la tâche elle-même (carte ou étape) est comptée dans « Carte terminée » et « Étape cochée », pas dans « J'ai fini la tâche ».

### Dates d'atteinte avec le barème C (scénario calendrier, 25 177 XP sur l'année)

| Niveau | Seuil | QYRIS | Asso | Études | Perso |
|---|---|---|---|---|---|
| 2 | 160 | 16/09 | 18/09 | 06/09 | 08/09 |
| 3 | 480 | 03/10 | 20/10 | 17/09 | 27/09 |
| 4 | 960 | 10/11 | 09/12 | 01/10 | 27/10 |
| 5 | 1 600 | 03/01 | 27/02 | 15/10 | 30/11 |
| 6 | 2 400 | 02/03 | 31/05 | 23/10 | 15/01 |
| 7 | 3 360 | 14/05 | non | 05/11 | 23/03 |
| 8 | 4 480 | non | non | 06/12 | 30/05 |
| 9 | 5 760 | non | non | 05/01 | non |
| 10 | 7 200 | non | non | 31/01 | non |
| 11 | 8 800 | non | non | 29/03 | non |
| 12 | 10 560 | non | non | 04/05 | non |
| 13 | 12 480 | non | non | **17/06** | non |

XP finale : QYRIS 4 131, Asso 2 746, Études 13 333, Perso 4 967.

### Grades, avec leurs scénarios

| Grade | Seuil C | Calendrier | Calme toute l'année | Normale toute l'année | Médians toute l'année |
|---|---|---|---|---|---|
| Assistant | 1 500 | 21/09 | 20/10 | 21/09 | 14/09 |
| Bras droit | 6 000 | 05/11 | 12/03/2027 | 19/11 | 22/10 |
| Majordome | 22 000 | **31/05/2027** | non atteint | 21/06/2027 | 04/03/2027 |
| Niveau 13 Études | 12 480 XP | **17/06/2027** | non atteint | non atteint | 01/02/2027 |

### Comparaison des deux autres barèmes (scénario calendrier)

| | A : § 7 | B : combat 1 XP/min | C |
|---|---|---|---|
| XP de l'année | 16 749 | 24 610 | 25 177 |
| Niveau 13 Études | 23/06 | 08/03 | 17/06 |
| Assistant | 21/09 | 16/09 | 21/09 |
| Bras droit | 06/11 | 20/10 | 05/11 |
| Majordome | 03/03 | 01/01 | 31/05 |
| Niveau 3 Études (début d'année) | 17/09 | 12/09 | 17/09 |

Le passage de A à B fait grimper l'XP de 47 % : le combat à 1 XP par minute accélère tout, d'où Majordome dès le 01/01 si on ne recale pas les seuils. C reprend B et décale seuils de niveau et grades pour retomber sur le calendrier voulu.

### Autres paliers touchés par la simulation

| Palier | Résultat (calendrier, 226 boss battus dans l'année) |
|---|---|
| Badge « Chasseur de boss » (10 boss) | 17/09 |
| Rare « 25 boss » | 10/10 (6 semaines) |
| Rare « 50 boss » | 01/11 (9 semaines) |
| Série de 30 jours, badges autres | non simulés (dépendent des habitudes, pas du barème) |

Les rares à 25 et 50 boss tombent tôt avec 5 séances par semaine. À remonter (50 et 100 par exemple) si tu veux qu'elles restent rares. Ce n'est pas dans le barème d'XP, je ne l'ai pas modifié.

---

## 5. Barème recommandé (C), complet

### Gains

| Source | Règle | Exemple |
|---|---|---|
| Carte terminée | 30 | |
| Bonus Urgent | +20 | |
| Bonus échéance tenue | +10 | |
| Carte éclair (terminée moins de 10 min après sa création) | 5, sans bonus. **Au plus 2 par jour de Paris paient** | |
| Étape cochée | 5. **Au plus 10 par jour paient** | |
| Combat, séance menée au bout | 1 XP par minute + **10** si le boss est battu | 45 min : 45 + 10 = 55 XP (14 aujourd'hui) |
| « J'ai fini la tâche » | minutes faites à 1 XP, +10 si au moins 5 min de combat, plus l'XP de la tâche | 18 min : 18 + 10 + 5 (étape) = 33 XP (13 aujourd'hui) |
| Forfait | 1 XP par minute faite, pas de bonus | 18 min : 18 XP (3 aujourd'hui) |
| Ligne de journal | 3. **Au plus 2 par jour paient** | |

Règle des plafonds, à implémenter dans le rejeu : on rejoue les gestes dans l'ordre ; le geste qui dépasse le plafond du jour rapporte 0 XP mais reste valide (la carte est bien terminée). Annuler un geste retire ce qu'il avait rapporté (0 s'il n'avait rien rapporté) et libère sa place dans le plafond.

### Seuils de niveau (par domaine, 160 XP de plus à chaque niveau)

Niveau n atteint à 80 × n × (n − 1) XP.

| Niveau | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| XP du domaine | 160 | 480 | 960 | 1 600 | 2 400 | 3 360 | 4 480 | 5 760 | 7 200 | 8 800 | 10 560 | 12 480 |

### Seuils de grade (sur l'XP totale)

| Grade | Aujourd'hui | Recommandé | Date visée (calendrier) |
|---|---|---|---|
| Stagiaire | 0 | 0 | départ |
| Assistant | 1 000 | 1 500 | 3 semaines |
| Bras droit | 4 000 | 6 000 | 2 mois |
| Majordome | 10 000 | 22 000 | fin mai |

### Ce qui change par rapport au § 7

| Élément | Avant | Après | Pourquoi |
|---|---|---|---|
| Combat | 1 XP par 5 min | 1 XP par minute | critère 1 : une heure doit valoir plus qu'une carte |
| Boss battu | +5 | +10 | critère 5 : récompenser la séance menée au bout, et garder 1 h de combat au-dessus du plafond de triche |
| Journal | 5 | 3 | éviter qu'il pèse comme une étape : c'est une phrase, pas une tâche |
| Plafonds par jour | aucun | étapes 10, éclairs 2, journal 2 | critère 5 |
| Pas de niveau | 100 | 160 | l'XP de l'année a doublé avec le combat à la minute |
| Grades | 1 000 / 4 000 / 10 000 | 1 500 / 6 000 / 22 000 | critère 4 |
| Cartes, bonus, éclair, étape | 30, +20, +10, 5, 5 | identiques | rien dans les données ne justifie de les toucher |

Les textes de `CONTENU.md` qui citent des gains (« +14 XP », « +13 XP », « +3 XP ») devront suivre le barème retenu.

### Soirée de triche : les chiffres

Hypothèse : 2 heures, 40 minutes de chaque. 60 cartes éclair (une toutes les 40 s), 160 étapes ajoutées puis cochées (une toutes les 15 s), 240 lignes de journal (une toutes les 10 s).

| Source | A et B | C avec plafonds |
|---|---|---|
| Cartes éclair | 300 | 10 |
| Étapes | 800 | 50 |
| Journal | 1 200 (A, B) | 6 |
| **Total** | **2 300** | **66** |
| Une heure de combat (60 min, boss battu) | 17 (A) ou 65 (B) | 70 |

Marge faible (66 contre 70) : un plafond d'étapes à 11 ou un boss à +5 ferait échouer le critère 5. Si tu veux plus de marge, baisse le plafond d'étapes à 8.

Ce que les plafonds coûtent à un usage normal : 95 XP sur 25 272 dans l'année simulée. Sur tes vrais jours chargés (13 coches le 13/10), tu perdrais 3 étapes ce jour-là.

---

## 6. Sensibilité : les seuils dépendent de ton rythme

Les seuils de C sont calés sur la semaine ordinaire simulée (515 XP). Voici ce qui arrive si ton rythme réel s'en écarte (barème C inchangé, scénario calendrier) :

| Variante | Critère 2 | Critère 3 | Critère 4 : niveau 13 Études, Majordome |
|---|---|---|---|
| Référence | oui (46 %) | oui | 17/06, 31/05 |
| Combat deux fois moins | oui (étapes 41 %) | NON (écart de 21 jours) | non atteint, non atteint |
| Combat une fois et demie plus | NON (combat 56 %) | NON | 20/04, 10/04 |
| Cartes et étapes à 60 % | NON (combat 58 %) | NON | non atteint, non atteint |
| Cartes et étapes à 150 % | oui (étapes 37 %) | NON | 29/04, 13/04 |

À retenir :

- Un écart de 50 % sur ton effort décale le niveau 13 de **2 mois**. Les dates de C sont donc un ordre de grandeur, pas une promesse.
- Le critère 2 est le plus fragile : il suppose que le combat reste en dessous de la moitié de l'XP. Si tu fais beaucoup plus de combat que de tâches, il bascule.
- **Règle de recalage** : après 3 semaines réelles, divise l'XP d'une semaine ordinaire par 515. Multiplie `pas_niveau` et les 3 grades par ce rapport. Un seul endroit à changer (`JR_XP_REGLES`, son miroir JS, et le test qui épingle les valeurs).

---

## 7. Limites

| Limite | Conséquence |
|---|---|
| Historique Trello court (3,5 semaines), pris pendant la mise en place de Trello | Les rythmes de cartes et d'étapes sont fragiles. Les profils « normale » et « médians » sont des multiples du mesuré, pas des mesures |
| Actions de Jarvis Jr. en MySQL (production) inaccessibles en local | Aucune donnée réelle de lots 1 à 3 côté Jarvis. La route `GET xp/export` prévue en M3 permettra la prochaine régulation sur les vraies actions |
| Heure de fin des cartes | J'ai utilisé le journal daté des gestes Trello (étapes cochées, cartes déplacées vers Fait), plus fiable que `dateLastActivity`. Il couvre tout octobre |
| Combat, boss, « J'ai fini la tâche », journal inexistants | Volumes et durées inventés (partie 3). Le critère 2 en dépend directement |
| Calendrier P27 estimé | Ni médians ni finaux d'avril à juin ne sont dans l'emploi du temps A26. Les dates du niveau 13 et de Majordome bougent si elles changent |
| Simulation sur 44 semaines depuis le 01/09 | L'XP n'existera pas avant la mise en ligne du 4.1. Les écarts relatifs comptent, pas les dates absolues : décale tout d'autant |
| Badges, série, jokers, cartes rares autres que les boss | Non simulés |

## 8. Relancer

| Commande | Effet |
|---|---|
| `{ python3 outils/historique-trello.py ; } 2>&1 \| ~/.claude/hooks/redact-secrets` | Relit Trello (GET uniquement) et réécrit `outils/donnees/historique-trello.json` (hors git) |
| `php outils/simuler-xp.php` | Rapport complet, sans réseau. Argument `A`, `B` ou `C` pour un seul barème |
| `php outils/simuler-xp.php C pas=150 grades=1500,6000,22000 combat=0.5 rythme=1.5` | Essais : pas de niveau, grades, multiplicateur de combat, multiplicateur de cartes et d'étapes |
