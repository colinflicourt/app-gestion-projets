// Jarvis Jr. : l'état de l'app, la navigation, le chargement des données et la file des écritures.
// L'iPhone affiche sa copie tout de suite, puis demande les données au serveur (SPEC § 8.4).
import { h, icone } from './lib/dom.js';
import { T } from './lib/textes.js';
import { appeler, ErreurApi, ErreurReseau } from './api.js';
import * as stockage from './stockage.js';
import * as file from './file.js';
import { integrer, retirerEcriture, vue } from './etat.js';
import { rendreMessage, rendreStatut, tirerPourActualiser } from './ecrans/commun.js';
import * as connexion from './ecrans/connexion.js';
import * as taches from './ecrans/taches.js';
import * as carte from './ecrans/carte.js';
import * as aujourdhui from './ecrans/aujourdhui.js';
import * as capture from './ecrans/capture.js';
import * as agenda from './ecrans/agenda.js';
import * as mails from './ecrans/mails.js';
import * as moi from './ecrans/moi.js';
import * as reglages from './ecrans/reglages.js';
import * as notifications from './notifications.js';

const ECRANS = {
  aujourdhui,
  agenda,
  taches,
  carte,
  mails,
  moi,
  reglages,
};
const FEUILLES = {
  capture,
};
const ONGLETS = ['aujourdhui', 'agenda', 'taches', 'mails', 'moi'];
const PARENTS = { carte: 'taches', reglages: 'moi' };  // l'onglet allumé sur un écran sans onglet
const DOMAINES = ['qyris', 'asso', 'etudes', 'perso'];
const RENVOI = 30000;

const etat = {
  donnees: null,        // dernière réponse de GET donnees, avec recu_le (heure de réception, en ms)
  file: [],             // écritures en attente (file.js)
  horsLigne: !navigator.onLine,
  erreur: null,         // erreur du serveur au dernier chargement
  connexion: false,     // l'écran de connexion est demandé…
  expiree: false,       // … parce que la session a expiré
  charge: false,
  dernierChargement: 0,
  capture: null,        // feuille « Noter » ouverte : { domaine, titre, urgent, date, heure, erreur }
  precedente: null,     // route affichée avant celle-ci, pour « Retour »
};

export function lireRoute(hash = location.hash) {
  const [chemin, requete = ''] = hash.replace(/^#\/?/, '').split('?');
  const [nom, param = null] = chemin.split('/').filter(Boolean);
  return { nom: ECRANS[nom] ? nom : 'aujourdhui', param, requete: new URLSearchParams(requete) };
}

const app = {
  etat,
  maintenant: () => new Date(),
  vue: () => vue(etat.donnees, etat.file),
  aller(hash) {
    if (location.hash === hash) rendre();
    else location.hash = hash;
  },
  retour(defaut) {
    if (etat.precedente !== null) history.back();
    else app.aller(defaut);
  },
  rendre: () => rendre(),
  actualiser,
  ecrire,
  annulerTerminer,
  message,
  connecter,
  deconnecter,
  demanderConnexion,
  ouvrirCapture(domaine = null) {
    etat.capture = { domaine, titre: '', urgent: false, date: '', heure: '', erreur: null };
    rendreCapture();
  },
  fermerCapture() {
    etat.capture = null;
    rendreCapture();
    document.querySelector('.bouton-plus')?.focus();
  },
  rendreCapture: () => rendreCapture(),
};

let affichee = null;

function rendre() {
  const route = lireRoute();
  const cle = etat.connexion ? 'connexion' : `${route.nom}/${route.param ?? ''}`;
  const changement = cle !== affichee;
  const ecran = document.getElementById('ecran');
  if (changement && affichee !== null) {
    etat.precedente = affichee;
    ECRANS[affichee.split('/')[0]]?.quitter?.();
  }
  const haut = changement ? 0 : ecran.scrollTop;
  const module = etat.connexion ? connexion : ECRANS[route.nom];
  document.getElementById('app').classList.toggle('sans-onglets', etat.connexion);
  ecran.replaceChildren(module.rendre(app, route));
  ecran.scrollTop = haut;
  affichee = cle;
  for (const a of document.querySelectorAll('.onglet')) {
    if (!etat.connexion && a.dataset.onglet === (PARENTS[route.nom] ?? route.nom)) a.setAttribute('aria-current', 'page');
    else a.removeAttribute('aria-current');
  }
  rendreStatut(document.getElementById('statut'), app);
  if (changement) ecran.focus({ preventScroll: true });
  module.apres?.(app, route, ecran, changement);
}

function rendreCapture() {
  const conteneur = document.getElementById('feuille');
  const ouverte = etat.capture !== null && FEUILLES.capture !== null;
  document.getElementById('principal').inert = ouverte;
  if (!ouverte) {
    conteneur.replaceChildren();
    return;
  }
  conteneur.replaceChildren(FEUILLES.capture.rendre(app, etat.capture));
  FEUILLES.capture.apres?.(conteneur, etat.capture);
}

async function actualiser(force = false) {
  if (etat.charge || etat.connexion) return;
  etat.charge = true;
  try {
    const d = await appeler('GET', 'donnees', { parametres: force ? { force: '1' } : null });
    d.recu_le = Date.now();
    Object.assign(etat, { donnees: d, horsLigne: false, erreur: null, dernierChargement: Date.now() });
    try {
      await stockage.ecrire('donnees', 'derniere', d);
    } catch {
      // la copie reste en mémoire
    }
    envoyerFile();
  } catch (e) {
    if (e instanceof ErreurApi && e.statut === 401) {
      demanderConnexion(etat.donnees !== null || etat.file.length > 0);
      return;
    }
    if (e instanceof ErreurReseau) etat.horsLigne = true;
    else etat.erreur = e.message;
  } finally {
    etat.charge = false;
  }
  rendre();
}

function demanderConnexion(expiree) {
  etat.connexion = true;
  etat.expiree = expiree;
  etat.capture = null;
  rendreCapture();
  rendre();
}

async function connecter(motDePasse) {
  await appeler('POST', 'connexion', { corps: { mot_de_passe: motDePasse } });
  etat.connexion = false;
  etat.expiree = false;
  rendre();
  await actualiser(true);
}

async function deconnecter(partout) {
  await notifications.oublier();
  try {
    await appeler('POST', partout ? 'deconnexion-partout' : 'deconnexion', { corps: {} });
  } catch (e) {
    // Session déjà expirée ou effacée ailleurs : il ne reste qu'à vider l'iPhone
    if (!(e instanceof ErreurApi && e.statut === 401)) throw e;
  }
  try {
    await stockage.vider();
  } catch {
    // rien de plus à effacer
  }
  etat.donnees = null;
  etat.file = [];
  demanderConnexion(false);
}

async function ecrire(route, corps, local = {}) {
  const e = file.nouvelleEcriture(route, corps, local);
  etat.file = [...etat.file, e];
  await file.garder(e);
  rendre();
  envoyerFile();
  return e;
}

let minuteurRenvoi = null;

function envoyerFile() {
  if (etat.connexion || etat.file.length === 0) return;
  file.envoyer({
    lire: () => etat.file,
    ecrire(f) {
      etat.file = f;
    },
    succes(e, reponse) {
      if (etat.donnees) {
        etat.donnees = integrer(etat.donnees, e, reponse);
        stockage.ecrire('donnees', 'derniere', etat.donnees).catch(() => {});
      }
      rendre();
    },
    refus(e, erreur) {
      // Annuler un « Terminer » qui n'était jamais arrivé au serveur : il n'y a rien à dire
      if (!(e.route === 'taches/annuler' && erreur.code === 'annulation_impossible')) message(erreur.message);
      if (['annulation_trop_tard', 'annulation_impossible', 'carte_introuvable', 'element_introuvable'].includes(erreur.code)) actualiser(true);
      else rendre();
    },
    garde() {
      rendre();
      clearTimeout(minuteurRenvoi);
      minuteurRenvoi = setTimeout(envoyerFile, RENVOI);
    },
    session: () => demanderConnexion(true),
  });
}

// « Annuler » pendant 5 s après « Terminer ». Jamais parti : retiré de la file. Parti (ou peut-être
// arrivé, réponse perdue) : on envoie taches/annuler, qui répond « rien à annuler » s'il n'était pas arrivé.
async function annulerTerminer(e) {
  await file.attendre(e.id_client);
  const enFile = etat.file.find((x) => x.id_client === e.id_client);
  if (enFile) {
    etat.file = retirerEcriture(etat.file, e.id_client);
    await file.oublier(e.id_client);
    if (enFile.essais === 0) {
      rendre();
      return;
    }
  }
  await ecrire('taches/annuler', { id_client_origine: e.id_client }, {
    carte: e.corps.carte, colonne_avant: e.local.colonne_avant, position_avant: e.local.position_avant, echeance_faite_avant: e.local.echeance_faite_avant,
  });
}

let minuteurMessage = null;

function message(texte, { action = null, delai = 4000 } = {}) {
  clearTimeout(minuteurMessage);
  const conteneur = document.getElementById('messages');
  rendreMessage(conteneur, texte, action, () => message(null));
  if (texte !== null) minuteurMessage = setTimeout(() => rendreMessage(conteneur, null), delai);
}

function domaineCourant() {
  const route = lireRoute();
  if (route.nom === 'taches' && DOMAINES.includes(route.param)) return route.param;
  try {
    const dernier = localStorage.getItem('jr-domaine');
    return DOMAINES.includes(dernier) ? dernier : null;
  } catch {
    return null;
  }
}

// Au changement de minute, l'écran se redessine (comptes à rebours, JOUÉ), sauf pendant un tirage ou la connexion
function tictac() {
  setTimeout(() => {
    if (!etat.connexion && !ECRANS[lireRoute().nom].occupe?.()) rendre();
    tictac();
  }, 60000 - (Date.now() % 60000) + 50);
}

async function demarrer() {
  document.getElementById('onglets').replaceChildren(
    ...ONGLETS.map((nom) => h('a', { href: `#/${nom}`, class: 'onglet', dataset: { onglet: nom } }, icone(nom), h('span', {}, T.onglets[nom]))),
    h('button', { type: 'button', class: 'bouton-plus', 'aria-label': T.onglets.noter, onclick: () => app.ouvrirCapture(domaineCourant()) }, icone('plus', 22, 2.25)),
  );
  tirerPourActualiser(document.getElementById('ecran'), document.getElementById('tirer'), () => actualiser(true));
  try {
    etat.file = await file.charger();
  } catch {
    etat.file = [];
  }
  try {
    etat.donnees = (await stockage.lire('donnees', 'derniere')) ?? null;
  } catch {
    etat.donnees = null;
  }
  rendre();
  document.documentElement.dataset.pret = '1';
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
  window.addEventListener('hashchange', () => rendre());
  window.addEventListener('online', () => {
    etat.horsLigne = false;
    actualiser();
  });
  window.addEventListener('offline', () => {
    etat.horsLigne = true;
    rendre();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && Date.now() - etat.dernierChargement > 60000) actualiser();
  });
  tictac();
  await actualiser();
  notifications.renvoyer();
}

demarrer();
