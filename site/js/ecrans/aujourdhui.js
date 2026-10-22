// Aujourd'hui (SPEC § 8.1) : l'écran de conduite, Junior, les 4 tranches et GO ; sous le pli, la conduite
// du jour et l'À faire. Le tirage (§ 8.3) vit ici : GO, Autre chose, C'est parti.
import { h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { DOMAINES, NOMS, SCOTCH, compteurs, groupesAFaire } from '../lib/cartes.js';
import { etatConduite, jourDe, medianProche, nomCreneau } from '../lib/conduite.js';
import { phraseDuJour, salutation } from '../lib/phrases.js';
import { IMMINENT, PAS, RELANCES, candidats, etiquetteProposition, ligneAfficheur, replique, rouler, tempsLibre, texteProposition, tirer } from '../lib/tirage.js';
import { dateCourte, dateLongue, dureeTexte, enParis, heureParis, heureTexte, jourParis } from '../lib/temps.js';
import { regle, tampon, voyant } from './commun.js';
import { ligneCarte } from './taches.js';

const A = T.aujourdhui;
const ANGLES = { qyris: '-1.6deg', asso: '0.9deg', etudes: '-0.7deg', perso: '1.4deg' };

// Le tirage en cours : { phase: 'roule' | 'resultat' | 'special', liste, proposes, choix, libre, relances, replique, ligne }
let tirage = null;
let minuteur = null;
let observateur = null;

export const occupe = () => tirage?.phase === 'roule';

export function quitter() {
  clearInterval(minuteur);
  tirage = null;
  observateur?.disconnect();
}

const ligne = (...enfants) => h('div', { class: 'afficheur-ligne' }, enfants);
const titre = (texte, court) => h('span', { class: court ? 'afficheur-titre afficheur-titre-court' : 'afficheur-titre' }, texte);
const cote = (texte) => texte && h('span', { class: 'afficheur-cote' }, texte);
const trait = () => h('div', { class: 'afficheur-trait' });
const heureDe = (iso) => heureParis(new Date(iso));

function chiffres(heure, legende, salle) {
  return [
    h('div', { class: 'chiffres-ligne' }, h('span', { class: 'chiffres' }, heure), h('span', { class: 'chiffres-cote' }, legende.map((l, i) => [i > 0 && h('br'), l]))),
    salle && h('div', { class: 'chiffres' }, salle),
  ];
}

function quand(standby) {
  if (standby.quand === 'aujourdhui') return [A.dans, dureeTexte(standby.dans)];
  return [standby.quand === 'demain' ? A.demain : dateCourte(standby.cours.jour)];
}

function afficheurConduite(c, agenda) {
  if (agenda === 'absent') return ligne(voyant(A.standby), titre(T.agenda.chargement));
  if (agenda === 'non_connecte') return ligne(voyant(A.standby), titre(A.agendaNonConnecte));
  const s = c.scene;
  const suite = c.standby;
  if (s.type === 'cours') {
    const coteSuite = suite && (suite.quand === 'aujourdhui' ? [heureDe(suite.cours.debut), suite.cours.salle].filter(Boolean).join(' · ') : `${quand(suite)[0]} ${heureDe(suite.cours.debut)}`);
    return [
      ligne(voyant(A.enScene, true), titre(nomCreneau(s.cours))),
      chiffres(heureDe(s.cours.fin), [A.finDans, dureeTexte(s.finDans)], s.cours.salle),
      trait(),
      ligne(voyant(A.standby), titre(suite ? nomCreneau(suite.cours) : A.rienDePrevu, Boolean(suite)), cote(coteSuite)),
    ];
  }
  const enScene = { entracte: [A.entracte, s.libres !== undefined && A.libres(dureeTexte(s.libres))], rien: [A.rien, A.nbCours(s.nbCours)], fini: [A.fini, null] }[s.type];
  return [
    ligne(voyant(A.enScene), titre(enScene[0]), cote(enScene[1])),
    trait(),
    suite ? [ligne(voyant(A.standby, true), titre(nomCreneau(suite.cours))), chiffres(heureDe(suite.cours.debut), quand(suite), suite.cours.salle)] : ligne(voyant(A.standby), titre(A.rienDePrevu)),
  ];
}

function afficheurTirage(c, maintenant) {
  const suite = c.standby?.quand === 'aujourdhui' ? c.standby : null;
  return [
    suite && [ligne(voyant(A.standby), titre(nomCreneau(suite.cours)), cote([heureDe(suite.cours.debut), suite.cours.salle].filter(Boolean).join(' · '))), trait()],
    ligne(voyant(A.proposition, true)),
    h('div', { class: 'tirage-ligne' }, tirage.ligne),
    tirage.phase === 'roule'
      ? h('div', { class: 'tirage-attente', 'aria-hidden': 'true' }, '· · · · · · · ·')
      : h('div', { class: 'tirage-resultat' },
        h('p', { class: 'tirage-texte' }, texteProposition(tirage.choix)),
        h('p', { class: `tirage-domaine d-${tirage.choix.carte.domaine}` }, h('span', { class: 'carre' }), etiquetteProposition(tirage.choix, maintenant))),
  ];
}

function talkback(c, cartes, jours, maintenant) {
  let paroles = tirage?.replique;
  if (!paroles) {
    const premier = c.premierCours ? enParis(new Date(c.premierCours.debut)) : null;
    const faits = {
      jour: c.aujourdhui,
      nbCours: c.nbCours,
      premierCours: premier ? premier.heure * 60 + premier.minute : null,
      nbRetard: Object.values(compteurs(cartes, maintenant)).reduce((s, k) => s + k.retard, 0),
      medianDans: medianProche(jours, maintenant)?.dans ?? null,
    };
    paroles = [h('strong', {}, `${salutation(maintenant)} `), phraseDuJour(faits)];
  }
  return h('div', { class: 'talkback' },
    h('div', { class: 'talkback-qui' }, icone('micro', 14, 2), h('span', {}, A.junior)),
    h('p', { class: 'talkback-texte', 'aria-live': 'polite' }, paroles),
    c.bandeaux.map((b) => h('p', { class: 'bandeau' }, icone(b.type === 'ferie' ? 'ferie' : 'agenda', 16), b.texte)));
}

function tranches(app, cartes, jours, maintenant) {
  const comptes = compteurs(cartes, maintenant);
  const median = medianProche(jours, maintenant);
  return h('div', { class: 'tranches' }, DOMAINES.map((d) => {
    const k = comptes[d];
    const medianIci = d === 'etudes' && median ? median.dans : null;
    let alerte = h('span', { class: 'tranche-alerte' });
    if (k.retard > 0) alerte = h('span', { class: 'tranche-alerte retard' }, h('span', { class: 'point' }), A.enRetard(k.retard));
    else if (medianIci !== null) alerte = h('span', { class: 'tranche-alerte' }, h('span', { class: 'anneau' }), A.median(medianIci));
    return h('button', { type: 'button', class: `tranche d-${d}`, 'aria-label': A.tranche(NOMS[d], k.ouvertes, k.retard, medianIci), onclick: () => app.aller(`#/taches/${d}`) },
      h('span', { class: 'scotch', vars: { '--angle': ANGLES[d] } }, SCOTCH[d]),
      h('span', { class: 'tranche-compte' }, String(k.ouvertes)),
      h('span', { class: 'tranche-legende' }, A.aFaire),
      alerte,
      h('span', { class: 'fader', 'aria-hidden': 'true' }, h('span', { class: 'fader-piste' }), h('span', { class: 'fader-graduations' }), h('span', { class: 'fader-graduations droite' }), h('span', { class: 'fader-poignee' })));
  }));
}

function panneauGo(app, libre) {
  let contenu;
  if (tirage?.phase === 'resultat') {
    contenu = h('div', { class: 'go-choix' },
      tirage.relances <= RELANCES && h('button', { type: 'button', class: 'bouton bouton-haut', onclick: () => relancer(app) }, T.tirage.autreChose),
      h('div', { class: 'cuvette' }, h('button', { type: 'button', class: 'bouton-allume bouton-parti', onclick: () => parti(app) }, T.tirage.cestParti)));
  } else if (tirage?.phase === 'special') {
    contenu = h('button', {
      type: 'button', class: 'bouton bouton-haut',
      onclick: () => {
        tirage = null;
        app.rendre();
      },
    }, T.tirage.retour);
  } else {
    const roule = tirage?.phase === 'roule';
    contenu = [
      h('div', { class: 'cuvette' }, h('button', {
        type: 'button', class: `go${roule ? ' appuye' : ''}`, 'aria-label': libre.enCours ? A.goEteint : A.goAria,
        'aria-disabled': libre.enCours || roule ? 'true' : null, onclick: () => go(app),
      }, A.go)),
      h('p', { class: 'go-legende' }, A.goLegende),
    ];
  }
  return h('div', { class: 'go-panneau' }, contenu);
}

function go(app) {
  if (tirage?.phase === 'roule') return;
  const maintenant = app.maintenant();
  const v = app.vue();
  const libre = tempsLibre(v?.agenda.jours ?? {}, maintenant);
  if (libre.enCours) return;
  const base = { relances: 0, proposes: new Set(), choix: null, libre, ligne: '' };
  if (libre.coursDans !== null && libre.coursDans < IMMINENT) {
    tirage = { ...base, phase: 'special', replique: T.tirage.coursImminent(dureeTexte(libre.coursDans)) };
    app.rendre();
    return;
  }
  const liste = candidats(v?.trello.cartes ?? [], maintenant);
  if (liste.length === 0) {
    tirage = { ...base, phase: 'special', replique: T.tirage.vide };
    app.rendre();
    return;
  }
  tirage = { ...base, liste };
  lancer(app);
}

function relancer(app) {
  tirage.relances++;
  lancer(app);
}

// STANDBY, l'afficheur roule colonne par colonne, puis la proposition tombe (instantané en mouvement réduit)
function lancer(app) {
  tirage.choix = tirer(tirage.liste, tirage.proposes, Math.random);
  tirage.proposes.add(tirage.choix.cle);
  const cible = ligneAfficheur(tirage.libre);
  const fin = () => {
    clearInterval(minuteur);
    Object.assign(tirage, { phase: 'resultat', ligne: cible, replique: replique(tirage.libre, tirage.relances) });
    app.rendre();
  };
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
    fin();
    return;
  }
  Object.assign(tirage, { phase: 'roule', ligne: rouler(cible, 0), replique: T.tirage.cherche });
  app.rendre();
  let pas = 0;
  clearInterval(minuteur);
  minuteur = setInterval(() => {
    pas++;
    if (pas >= PAS) {
      fin();
      return;
    }
    tirage.ligne = rouler(cible, pas / PAS);
    const el = document.querySelector('.aujourdhui-haut .tirage-ligne');
    if (el) el.textContent = tirage.ligne;
  }, 55);
}

function parti(app) {
  const { carte, element } = tirage.choix;
  quitter();
  app.ecrire('tirage/parti', { carte: carte.id, element: element?.id ?? null });
  app.message(T.tirage.parti);
  app.aller(`#/carte/${carte.id}${element ? `?etape=${element.id}` : ''}`);
}

// Quand l'écran de conduite sort de la vue, une barre compacte garde le prochain cours et un petit GO
function compacte(app, c, libre, agenda) {
  let s = null;
  if (c.scene.type === 'cours') s = { voyant: A.enScene, nom: nomCreneau(c.scene.cours), cote: `${A.finDans} ${dureeTexte(c.scene.finDans)}`, chiffres: [heureDe(c.scene.cours.fin), c.scene.cours.salle] };
  else if (c.standby) s = { voyant: A.standby, nom: nomCreneau(c.standby.cours), cote: quand(c.standby).join(' '), chiffres: [heureDe(c.standby.cours.debut), c.standby.cours.salle] };
  return h('div', { class: 'compacte', inert: true, 'aria-hidden': 'true' },
    h('div', { class: 'compacte-barre' },
      h('div', { class: 'afficheur afficheur-compact' },
        s ? [ligne(voyant(s.voyant, true), titre(s.nom), cote(s.cote)), h('div', { class: 'chiffres-compacts' }, s.chiffres.filter(Boolean).join('  '))]
          : ligne(voyant(A.standby), titre(agenda === 'non_connecte' ? A.agendaNonConnecte : A.rienDePrevu))),
      h('div', { class: 'cuvette' }, h('button', {
        type: 'button', class: 'go go-petit', 'aria-label': libre.enCours ? A.goEteint : A.goAria, 'aria-disabled': libre.enCours ? 'true' : null,
        onclick: () => {
          document.getElementById('ecran').scrollTo({ top: 0 });
          go(app);
        },
      }, A.go))));
}

function annulerRappel(app, r) {
  app.ecrire('rappels/annuler', { id: r.id });
  app.message(A.rappelAnnule);
}

function rappelsDuJour(app, rappels, jour, maintenant) {
  const lignes = rappels.filter((r) => jourParis(new Date(r.heure)) === jour).map((r) => {
    const passe = r.passe || new Date(r.heure) <= maintenant;
    return h('div', { class: `rang rappel-rang${passe ? ' rappel-passe' : ''}` },
      h('span', { class: 'rang-heure' }, heureParis(new Date(r.heure))),
      h('div', { class: 'rang-corps' },
        h('div', { class: 'rang-texte' }, h('p', { class: 'rang-titre' }, r.titre)),
        r.annulable && !passe && h('button', { type: 'button', class: 'bouton bouton-petit rappel-annuler', 'aria-label': A.annulerRappelAria(r.titre), onclick: () => annulerRappel(app, r) }, A.annulerRappel)));
  });
  return [
    h('h2', { class: 'section-titre' }, A.rappelsTitre), regle(),
    lignes.length > 0 ? lignes : h('p', { class: 'vide' }, A.aucunRappel),
  ];
}

function sousLePli(app, c, cartes, jours, rappels, maintenant) {
  const prochain = c.standby?.cours;
  const rangs = jourDe(jours, c.aujourdhui).evenements.map((e) => {
    const passe = !e.journee && new Date(e.fin) <= maintenant;
    const estProchain = prochain && e.debut === prochain.debut && e.titre === prochain.titre;
    const details = e.journee ? [A.journee] : [e.source === 'perso' ? A.perso : e.salle, A.jusqua(heureDe(e.fin))];
    return h('div', { class: `rang conduite-rang${passe ? ' passe' : ''}` },
      h('span', { class: 'rang-heure' }, e.journee ? '' : heureDe(e.debut)),
      h('div', { class: 'rang-corps' },
        h('div', { class: 'rang-texte' }, h('p', { class: 'rang-titre' }, nomCreneau(e)), h('p', { class: 'rang-sous' }, details.filter(Boolean).join(' · '))),
        passe ? tampon(A.joue, e.debut + e.titre) : estProchain && voyant(A.standby, false, true)));
  });
  const g = groupesAFaire(cartes, maintenant);
  const groupe = (cle, liste) => liste.length > 0 && h('div', { class: 'rang rang-groupe' },
    h('span', { class: `rang-etiquette${cle === 'retard' ? ' retard' : ''}` }, cle === 'retard' && h('span', { class: 'point' }), A.groupes[cle]),
    h('div', { class: 'rang-cartes' }, liste.map((carte) => ligneCarte(app, carte, { minuscule: true }))));
  const rien = g.retard.length + g.aujourdhui.length + g.urgent.length === 0;
  return h('div', { class: 'sous-le-pli' },
    h('h2', { class: 'section-titre' }, A.conduite), regle(),
    rangs.length > 0 ? rangs : h('p', { class: 'vide' }, A.rienDePrevu),
    h('h2', { class: 'section-titre' }, A.aFaireTitre), regle(),
    rien ? h('p', { class: 'vide' }, A.rienDUrgent) : [groupe('retard', g.retard), groupe('aujourdhui', g.aujourdhui), groupe('urgent', g.urgent)],
    rappelsDuJour(app, rappels, c.aujourdhui, maintenant));
}

export function rendre(app) {
  const maintenant = app.maintenant();
  const v = app.vue();
  const jours = v?.agenda.jours ?? {};
  const cartes = v?.trello.cartes ?? [];
  const c = etatConduite(jours, maintenant);
  const libre = tempsLibre(jours, maintenant);
  if (tirage && libre.enCours) quitter();
  let agenda = 'ok';
  if (v === null) agenda = 'absent';
  else if (!v.agenda.google_connecte && Object.keys(jours).length === 0) agenda = 'non_connecte';
  return h('div', { class: 'aujourdhui' },
    compacte(app, c, libre, agenda),
    h('div', { class: 'aujourdhui-haut' },
      h('section', { class: 'console', 'aria-label': A.ecranConduite },
        h('div', { class: 'afficheur' },
          h('div', { class: 'afficheur-date' }, dateLongue(c.aujourdhui)),
          tirage?.choix ? afficheurTirage(c, maintenant) : afficheurConduite(c, agenda)),
        talkback(c, cartes, jours, maintenant)),
      tranches(app, cartes, jours, maintenant),
      panneauGo(app, libre)),
    sousLePli(app, c, cartes, jours, v?.rappels_du_jour ?? [], maintenant));
}

export function apres(app, route, ecran) {
  observateur?.disconnect();
  const grand = ecran.querySelector('.aujourdhui-haut .console');
  const barre = ecran.querySelector('.compacte');
  if (!grand || !barre || !('IntersectionObserver' in window)) return;
  observateur = new IntersectionObserver(([entree]) => {
    const visible = !entree.isIntersecting;
    barre.classList.toggle('visible', visible);
    barre.inert = !visible;
    if (visible) barre.removeAttribute('aria-hidden');
    else barre.setAttribute('aria-hidden', 'true');
  }, { root: ecran });
  observateur.observe(grand);
}
