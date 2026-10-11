// Réglages (SPEC § 15.8). Au plan 1 du lot 2 : la partie Notifications. Brief, rappels, Siri et Session
// arrivent au plan 3.
import { h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { ErreurReseau } from '../api.js';
import * as notifications from '../notifications.js';
import { regle } from './commun.js';

const R = T.reglages;
let etatNotif = null;  // null tant que l'état n'est pas lu
let occupe = false;    // une demande en cours (activer, tester)

export function quitter() {
  etatNotif = null;
}

export function apres(app, route, ecran, changement) {
  if (!changement) return;
  notifications.etatNotifications().then((e) => {
    etatNotif = e;
    app.rendre();
  });
}

async function faire(app, action) {
  if (occupe) return;
  occupe = true;
  try {
    await action();
  } catch (e) {
    app.message(e instanceof ErreurReseau ? R.reseau : e.message);
  } finally {
    occupe = false;
    app.rendre();
  }
}

// Pas d'await avant notifications.activer() : la permission doit être demandée dans le geste
function activer(app) {
  const cle = app.etat.donnees?.notifications?.cle;
  if (!cle) {
    app.message(R.reseau);
    return;
  }
  faire(app, async () => {
    etatNotif = await notifications.activer(cle);
  });
}

function tester(app) {
  faire(app, async () => {
    const r = await notifications.envoyerTest();
    app.message(r.envoyes > 0 ? R.testEnvoye : R.testPersonne);
  });
}

function blocNotifications(app) {
  if (etatNotif === null) return [];
  const actives = etatNotif === 'actives';
  return [
    h('p', { class: 'reglage-ligne' }, h('span', { class: `temoin${actives ? ' allume' : ''}` }), R.etats[etatNotif]),
    etatNotif === 'coupees' && h('p', { class: 'erreur', role: 'alert' }, R.aideCoupees),
    etatNotif === 'indisponibles' && h('p', { class: 'reglage-aide' }, R.aideIndisponibles),
    etatNotif === 'inactives' && h('div', { class: 'cuvette cuvette-large' },
      h('button', { type: 'button', class: 'bouton-allume bouton-large', disabled: occupe, onclick: () => activer(app) }, R.activer)),
    actives && h('button', { type: 'button', class: 'bouton bouton-large', disabled: occupe, onclick: () => tester(app) }, R.tester),
  ];
}

export function rendre(app) {
  return h('div', { class: 'moi reglages' },
    h('header', { class: 'entete-retour' },
      h('button', { type: 'button', class: 'bouton-carre', 'aria-label': R.retour, onclick: () => app.retour('#/moi') }, icone('gauche', 20, 2)),
      h('h1', { class: 'titre-ecran' }, R.titre)),
    h('h2', { class: 'section-titre' }, R.notifications), regle(),
    blocNotifications(app));
}
