// Réglages (SPEC § 15.7 et § 15.8) : notifications, brief du matin, rappels, Siri, session.
import { h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { appeler, ErreurApi, ErreurReseau } from '../api.js';
import * as notifications from '../notifications.js';
import { ageTexte, minutesEntre } from '../lib/temps.js';
import { regle } from './commun.js';

const R = T.reglages;
const MINUTES = [5, 10, 15, 30, 60];
const JOURS_MEDIANS = [14, 7, 3, 1];
const SEUIL_PLANIFICATEUR = 5;  // minutes sans passage avant d'alerter

let etatNotif = null;  // null tant que l'état n'est pas lu
let occupe = false;    // une demande en cours (activer, tester, jeton)
let reg = null;        // réponse de GET reglages
let echecReg = null;   // texte si la lecture a échoué
let jeton = null;      // jeton Siri tout juste créé : en mémoire seulement, jamais stocké
let copie = false;
let focus = null;      // data-cle de la commande à refocaliser après un nouveau rendu

export function quitter() {
  etatNotif = null;
  reg = null;
  echecReg = null;
  jeton = null;
  copie = false;
  focus = null;
}

export function apres(app, route, ecran, changement) {
  if (changement) {
    notifications.etatNotifications().then((e) => {
      etatNotif = e;
      app.rendre();
    });
    appeler('GET', 'reglages')
      .then((r) => {
        reg = r;
        echecReg = null;
        app.rendre();
      })
      .catch((e) => {
        echecReg = e instanceof ErreurReseau ? R.reseau : e.message;
        app.rendre();
      });
    return;
  }
  // Un nouveau rendu vide le DOM : la commande touchée retrouve le focus, sauf si Colin est ailleurs
  if (focus && (document.activeElement === document.body || !ecran.contains(document.activeElement))) {
    ecran.querySelector(`[data-cle="${focus}"]`)?.focus({ preventScroll: true });
  }
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
    try {
      etatNotif = await notifications.activer(cle);
    } catch (e) {
      if (e instanceof ErreurApi || e instanceof ErreurReseau) throw e;
      app.message(R.echecActiver);
    }
  });
}

function tester(app) {
  faire(app, async () => {
    const r = await notifications.envoyerTest();
    app.message(r.envoyes > 0 ? R.testEnvoye : R.testPersonne);
  });
}

async function sortir(app, partout) {
  const n = app.etat.file.length;
  if (n > 0 && !window.confirm(R.confirmerFile(n))) return;
  if (partout && !window.confirm(R.confirmerTout)) return;
  try {
    await app.deconnecter(partout);
  } catch (e) {
    app.message(e instanceof ErreurReseau ? R.reseau : e.message);
  }
}

// Une modification : l'objet complet part par la file, l'écran se met à jour tout de suite
function modifier(app, cle, changer) {
  const rappels = structuredClone(reg.rappels);
  changer(rappels);
  if (JSON.stringify(rappels) === JSON.stringify(reg.rappels)) return;
  reg.rappels = rappels;
  focus = cle;
  app.rendre();
  app.ecrire('reglages/rappels', { rappels });
  app.message(app.etat.horsLigne || !navigator.onLine ? T.capture.horsLigne : R.enregistre);
}

// Ajoute ou retire une valeur de la liste, sans jamais éteindre la dernière
const basculer = (liste, valeur) => {
  if (!liste.includes(valeur)) return [...liste, valeur].sort((a, b) => a - b);
  return liste.length > 1 ? liste.filter((x) => x !== valeur) : liste;
};

function interrupteur(app, cle, libelle, actif, changer) {
  return h('button', {
    type: 'button', class: 'interrupteur', role: 'switch', 'aria-checked': String(actif), 'aria-label': libelle, 'data-cle': cle,
    onclick: () => modifier(app, cle, (r) => changer(!actif, r)),
  }, h('span', { class: 'interrupteur-bille' }));
}

function champHeure(app, cle, libelle, valeur, changer) {
  return h('input', {
    type: 'time', class: 'champ champ-heure', 'aria-label': libelle, 'data-cle': cle, value: valeur, required: true,
    onchange: (ev) => {
      if (ev.target.value) modifier(app, cle, (r) => changer(ev.target.value, r));
    },
  });
}

function bouton(app, cle, texte, allume, aria, changer) {
  return h('button', {
    type: 'button', class: 'filtre', 'aria-pressed': String(allume), 'aria-label': aria, 'data-cle': cle,
    onclick: () => modifier(app, cle, changer),
  }, texte);
}

function blocNotifications(app) {
  const etat = etatNotif ?? notifications.etatRapide();
  const actives = etat === 'actives';
  return [
    h('p', { class: 'reglage-ligne' }, h('span', { class: `temoin${actives ? ' allume' : ''}` }), R.etats[etat]),
    etat === 'coupees' && h('p', { class: 'erreur', role: 'alert' }, R.aideCoupees),
    etat === 'indisponibles' && h('p', { class: 'reglage-aide' }, R.aideIndisponibles),
    etat === 'inactives' && h('div', { class: 'cuvette cuvette-large' },
      h('button', { type: 'button', class: 'bouton-allume bouton-large', disabled: occupe, onclick: () => activer(app) }, R.activer)),
    actives && h('button', { type: 'button', class: 'bouton bouton-large', disabled: occupe, onclick: () => tester(app) }, R.tester),
  ];
}

function blocBrief(app) {
  const { heure, jours } = reg.rappels.brief;
  return [
    h('div', { class: 'reglage-ligne' }, h('span', { class: 'reglage-nom' }, R.heure), champHeure(app, 'brief-heure', R.heure, heure, (v, r) => { r.brief.heure = v; })),
    h('div', { class: 'reglage-groupe' },
      h('span', { class: 'reglage-nom' }, R.jours),
      h('div', { class: 'bascules', role: 'group', 'aria-label': R.jours },
        R.initialesJours.map((initiale, i) => bouton(app, `brief-jour-${i + 1}`, initiale, jours.includes(i + 1), R.nomsJours[i],
          (r) => { r.brief.jours = basculer(r.brief.jours, i + 1); })))),
  ];
}

function planificateur(app) {
  const passage = reg.planificateur?.dernier_passage;
  if (!passage) return h('p', { class: 'reglage-aide', role: 'status' }, R.planificateurJamais);
  const minutes = minutesEntre(new Date(passage), app.maintenant());
  const ok = minutes < SEUIL_PLANIFICATEUR;
  return h('p', { class: ok ? 'reglage-aide' : 'erreur', role: ok ? 'status' : 'alert' },
    ok ? R.planificateurOk(ageTexte(minutes)) : R.planificateurArret(ageTexte(minutes)));
}

function blocRappels(app) {
  const { cours, echeances, medians } = reg.rappels;
  return [
    h('div', { class: `reglage-bloc${cours.actif ? '' : ' eteint'}` },
      h('div', { class: 'reglage-ligne' }, h('span', { class: 'reglage-nom' }, R.avantCours),
        interrupteur(app, 'cours-actif', R.avantCours, cours.actif, (v, r) => { r.cours.actif = v; })),
      h('div', { class: 'bascules', role: 'group', 'aria-label': R.avantCours },
        MINUTES.map((m) => bouton(app, `cours-${m}`, R.minutes(m), cours.minutes === m, null, (r) => { r.cours.minutes = m; })))),
    h('div', { class: `reglage-bloc${echeances.actif ? '' : ' eteint'}` },
      h('div', { class: 'reglage-ligne' }, h('span', { class: 'reglage-nom' }, R.echeances),
        champHeure(app, 'echeances-heure', R.heureEcheances, echeances.heure, (v, r) => { r.echeances.heure = v; }),
        interrupteur(app, 'echeances-actif', R.echeances, echeances.actif, (v, r) => { r.echeances.actif = v; }))),
    h('div', { class: `reglage-bloc${medians.actif ? '' : ' eteint'}` },
      h('div', { class: 'reglage-ligne' }, h('span', { class: 'reglage-nom' }, R.medians),
        interrupteur(app, 'medians-actif', R.medians, medians.actif, (v, r) => { r.medians.actif = v; })),
      h('div', { class: 'bascules', role: 'group', 'aria-label': R.medians },
        JOURS_MEDIANS.map((j) => bouton(app, `medians-${j}`, R.medianJour(j), medians.jours.includes(j), null,
          (r) => { r.medians.jours = basculer(r.medians.jours, j).sort((a, b) => b - a); }))),
      h('div', { class: 'reglage-ligne' }, h('span', { class: 'reglage-nom' }, R.heureMedians),
        champHeure(app, 'medians-heure', R.heureMediansAria, medians.heure, (v, r) => { r.medians.heure = v; }))),
    planificateur(app),
  ];
}

function creerJeton(app) {
  faire(app, async () => {
    if (reg.siri.jeton_cree && !window.confirm(R.siriConfirmer)) return;
    const r = await appeler('POST', 'reglages/siri', { corps: {} });
    jeton = r.jeton;
    copie = false;
    reg.siri.jeton_cree = true;
  });
}

async function copier(app) {
  try {
    await navigator.clipboard.writeText(jeton);
    copie = true;
    app.rendre();
  } catch {
    // le jeton reste sélectionnable à la main
  }
}

function blocSiri(app) {
  const horsLigne = app.etat.horsLigne || !navigator.onLine;
  const creer = reg.siri.jeton_cree
    ? h('button', { type: 'button', class: 'bouton bouton-large', disabled: occupe || horsLigne, onclick: () => creerJeton(app) }, R.siriChanger)
    : h('div', { class: 'cuvette cuvette-large' },
      h('button', { type: 'button', class: 'bouton-allume bouton-large', disabled: occupe || horsLigne, onclick: () => creerJeton(app) }, R.siriCreer));
  return [
    reg.siri.jeton_cree && !jeton && h('p', { class: 'reglage-aide' }, R.siriExiste),
    creer,
    horsLigne && h('p', { class: 'reglage-aide' }, R.reseau),
    jeton && h('div', { class: 'jeton-bloc' },
      h('p', { class: 'jeton', 'aria-label': R.siriJeton }, jeton),
      h('button', { type: 'button', class: 'bouton bouton-large', onclick: () => copier(app) }, copie ? R.siriCopie : R.siriCopier),
      h('p', { class: 'erreur', role: 'alert' }, R.siriAvertissement),
      h('h3', { class: 'sous-titre' }, R.siriModeEmploi),
      h('ol', { class: 'etapes-siri' }, R.siriEtapes.map((e) => h('li', {}, e)))),
  ];
}

export function rendre(app) {
  return h('div', { class: 'moi reglages' },
    h('header', { class: 'entete-retour' },
      h('button', { type: 'button', class: 'bouton-carre', 'aria-label': R.retour, onclick: () => app.retour('#/moi') }, icone('gauche', 20, 2)),
      h('h1', { class: 'titre-ecran' }, R.titre)),
    h('h2', { class: 'section-titre' }, R.notifications), regle(),
    blocNotifications(app),
    echecReg && h('p', { class: 'reglage-aide' }, echecReg),
    reg && [
      h('h2', { class: 'section-titre' }, R.brief), regle(), blocBrief(app),
      h('h2', { class: 'section-titre' }, R.rappels), regle(), blocRappels(app),
      h('h2', { class: 'section-titre' }, R.siri), regle(), blocSiri(app),
    ],
    h('h2', { class: 'section-titre' }, R.session), regle(),
    h('button', { type: 'button', class: 'bouton bouton-large', onclick: () => sortir(app, false) }, R.deconnecter),
    h('button', { type: 'button', class: 'bouton bouton-large', onclick: () => sortir(app, true) }, R.deconnecterTout));
}
