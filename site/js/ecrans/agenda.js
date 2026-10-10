// L'onglet Agenda (SPEC § 8.1) : Jour et Semaine, jours spéciaux, cours JOUÉ, prochain cours en STANDBY,
// événements de l'agenda principal à part. Les 15 jours de la copie d'Aujourd'hui servent directement ;
// les autres se chargent par semaine, gardés pour être relus sans réseau.
import { angle, h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { appeler, ErreurApi } from '../api.js';
import * as stockage from '../stockage.js';
import { etatConduite } from '../lib/conduite.js';
import { ajouterJours, dateLongue, etiquetteJour, heureParis, jourParis, lundi, plageSemaine, semaineIso } from '../lib/temps.js';
import { regle, tampon, voyant } from './commun.js';

const G = T.agenda;
const VIDE = { bandeaux: [], evenements: [] };
let affichage = 'semaine';
let choisi = null;
const semaines = new Map();  // semaine ISO → { etat: 'chargement' | 'pret' | 'absente', jours }

// Une semaine absente (pas de réseau) sera redemandée à la prochaine navigation
export function quitter() {
  for (const [semaine, s] of semaines) if (s.etat === 'absente') semaines.delete(semaine);
}

async function charger(app, semaine) {
  semaines.set(semaine, { etat: 'chargement' });
  let jours = null;
  try {
    jours = (await appeler('GET', 'agenda', { parametres: { semaine } })).agenda.jours;
    stockage.ecrire('agenda', semaine, jours).catch(() => {});
  } catch (e) {
    if (e instanceof ErreurApi && e.statut === 401) {
      semaines.delete(semaine);
      app.demanderConnexion(true);
      return;
    }
    jours = (await stockage.lire('agenda', semaine).catch(() => null)) ?? null;
  }
  semaines.set(semaine, jours ? { etat: 'pret', jours } : { etat: 'absente' });
  app.rendre();
}

// Les jours demandés : ceux de la copie d'Aujourd'hui (les plus frais), complétés par la semaine chargée
function joursPour(app, dates, copie) {
  if (dates.every((d) => copie[d])) return { etat: 'pret', jours: copie };
  const semaine = semaineIso(dates[0]);
  const s = semaines.get(semaine);
  if (!s) {
    charger(app, semaine);
    return { etat: 'chargement' };
  }
  if (s.etat !== 'pret') return s;
  return { etat: 'pret', jours: { ...s.jours, ...Object.fromEntries(dates.filter((d) => copie[d]).map((d) => [d, copie[d]])) } };
}

function evenement(e, aujourdhui, maintenant, prochain) {
  const passe = e.journee ? e.fin <= aujourdhui : new Date(e.fin) <= maintenant;
  const estProchain = prochain && e.debut === prochain.debut && e.titre === prochain.titre;
  const uv = e.source === 'utc' && e.uv;
  return h('div', { class: `evenement${passe ? ' passe' : ''}` },
    uv ? h('span', { class: 'scotch scotch-uv', vars: { '--angle': angle(e.debut + e.uv, 1.5) } }, e.uv) : e.source === 'perso' && h('span', { class: 'marque-perso' }, G.perso),
    h('div', { class: 'evenement-texte' },
      h('p', { class: 'evenement-titre' }, uv ? [e.type, e.salle].filter(Boolean).join(' · ') : e.titre),
      h('p', { class: 'evenement-heure' }, e.journee ? G.journee : G.plage(heureParis(new Date(e.debut)), heureParis(new Date(e.fin))))),
    passe ? tampon(T.aujourdhui.joue, e.debut + e.titre) : estProchain && voyant(T.aujourdhui.standby, false, true));
}

function rangJour(jour, contenu, aujourdhui, maintenant, prochain) {
  const elements = [
    ...contenu.bandeaux.map((b) => h('div', { class: 'bandeau-jour' }, icone(b.type === 'ferie' ? 'ferie' : 'agenda', 18), h('span', {}, b.texte))),
    ...contenu.evenements.map((e) => evenement(e, aujourdhui, maintenant, prochain)),
  ];
  return h('div', { class: 'rang agenda-rang' },
    h('span', { class: `agenda-jour${jour === aujourdhui ? ' aujourdhui' : ''}`, 'aria-current': jour === aujourdhui ? 'date' : null }, etiquetteJour(jour)),
    h('div', { class: 'agenda-contenu' }, elements.length > 0 ? elements : h('span', { class: 'vide-court' }, G.rien)));
}

export function rendre(app) {
  const maintenant = app.maintenant();
  const aujourdhui = jourParis(maintenant);
  choisi ??= aujourdhui;
  const v = app.vue();
  const semaine = affichage === 'semaine';
  const dates = semaine ? Array.from({ length: 7 }, (_, i) => ajouterJours(lundi(choisi), i)) : [choisi];
  const aller = (n) => {
    choisi = n === 0 ? aujourdhui : ajouterJours(choisi, n * (semaine ? 7 : 1));
    quitter();
    app.rendre();
  };
  const bascule = (nom, libelle) => h('button', {
    type: 'button', class: 'bascule-choix', 'aria-pressed': String(affichage === nom),
    onclick: () => {
      affichage = nom;
      app.rendre();
    },
  }, libelle);
  const nonConnecte = v !== null && !v.agenda.google_connecte && Object.keys(v.agenda.jours).length === 0;
  const donnees = nonConnecte ? null : joursPour(app, dates, v?.agenda.jours ?? {});
  let corps;
  if (nonConnecte) corps = h('p', { class: 'vide' }, G.nonConnecte);
  else if (donnees.etat === 'chargement') corps = h('p', { class: 'vide' }, G.chargement);
  else if (donnees.etat === 'absente') corps = h('p', { class: 'vide' }, G.pasChargee);
  else {
    const prochain = etatConduite(donnees.jours, maintenant).standby?.cours ?? null;
    corps = dates.map((d) => rangJour(d, donnees.jours[d] ?? VIDE, aujourdhui, maintenant, prochain));
  }
  return h('div', { class: 'agenda' },
    h('header', { class: 'entete entete-centre' },
      h('h1', { class: 'titre-ecran' }, G.titre),
      h('div', { class: 'bascule', role: 'group', 'aria-label': G.affichage }, bascule('jour', G.jour), bascule('semaine', G.semaine))),
    h('div', { class: 'agenda-nav' },
      h('button', { type: 'button', class: 'bouton-carre', 'aria-label': semaine ? G.precedente : G.veille, onclick: () => aller(-1) }, icone('gauche', 20, 2)),
      h('div', { class: 'agenda-plage' },
        h('span', {}, semaine ? plageSemaine(dates[0]) : dateLongue(choisi)),
        !dates.includes(aujourdhui) && h('button', { type: 'button', class: 'lien-discret', onclick: () => aller(0) }, G.aujourdhui)),
      h('button', { type: 'button', class: 'bouton-carre', 'aria-label': semaine ? G.suivante : G.lendemain, onclick: () => aller(1) }, icone('droite', 20, 2))),
    regle(),
    corps);
}
