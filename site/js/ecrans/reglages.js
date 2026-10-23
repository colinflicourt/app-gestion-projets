// Réglages (SPEC § 15.7, § 15.8 et § 16.8) : notifications, brief du matin, rappels, comptes mail, Siri, session.
import { h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { appeler, ErreurApi, ErreurReseau } from '../api.js';
import * as notifications from '../notifications.js';
import { ageTexte, heureParis, heureTexte, jourParis, minutesEntre } from '../lib/temps.js';
import { heureMail } from '../lib/mails.js';
import { regle } from './commun.js';

const R = T.reglages;
const MINUTES = [5, 10, 15, 30, 60];
const JOURS_MEDIANS = [14, 7, 3, 1];
const SEUIL_PLANIFICATEUR = 5;  // minutes sans passage avant d'alerter
const COMPTES_MAIL = ['gmail', 'icloud', 'colin', 'contact'];
const DELAI_BOITE = 30000;  // le serveur s'arrête à 25 s

let etatNotif = null;  // null tant que l'état n'est pas lu
let occupe = false;    // une demande en cours (activer, tester, jeton)
let reg = null;        // réponse de GET reglages
let echecReg = null;   // texte si la lecture a échoué
let jeton = null;      // jeton Siri tout juste créé : en mémoire seulement, jamais stocké
let copie = false;
let comptes = null;    // réponse de GET comptes-mail : jamais de mot de passe
let identifiants = {}; // identifiant en cours de saisie, par compte
let motsDePasse = {};  // mot de passe en cours de saisie, par compte : en mémoire seulement, jamais écrit ni mis en file
let retours = {};      // dernier échec d'un envoi, par compte : code d'erreur
let focus = null;      // data-cle de la commande à refocaliser après un nouveau rendu

export function quitter() {
  etatNotif = null;
  reg = null;
  echecReg = null;
  jeton = null;
  copie = false;
  comptes = null;
  identifiants = {};
  motsDePasse = {};
  retours = {};
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
    appeler('GET', 'comptes-mail')
      .then((r) => {
        comptes = r.comptes;
        app.rendre();
      })
      .catch(() => {});
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

const horsLigne = (app) => app.etat.horsLigne || !navigator.onLine;

// « Connecté, vérifié à 14 h 32 » aujourd'hui, « … vérifié hier », « … vérifié lun. 19/10 » (comme l'heure d'un mail)
function etatCompte(app, c) {
  if (!c.enregistre) return { texte: T.mails.pasConnecte, ok: false };
  if (c.derniere_erreur) return { texte: R.etatsMail[c.derniere_erreur] ?? R.etatsMail.injoignable, ok: false };
  if (!c.dernier_succes) return { texte: T.mails.pasConnecte, ok: false };
  const date = new Date(c.dernier_succes);
  const aujourdhui = jourParis(date) === jourParis(app.maintenant());
  return { texte: aujourdhui ? R.connecteA(heureTexte(heureParis(date))) : R.connecteLe(heureMail(c.dernier_succes, app.maintenant())), ok: true };
}

// Un appel direct (pas la file : il faut une réponse tout de suite). Un échec garde le code, jamais le message du serveur.
async function agirCompte(app, compte, cle, envoyer, succes) {
  if (occupe) return;
  if (horsLigne(app)) {
    app.message(R.reseauMail);
    return;
  }
  occupe = true;
  focus = cle;
  app.rendre();
  try {
    const r = await envoyer();
    comptes = r.comptes;
    delete retours[compte];
    app.message(succes);
    return true;
  } catch (e) {
    if (e instanceof ErreurReseau) {
      app.message(R.reseau);
    } else if (e instanceof ErreurApi && e.statut === 401) {
      app.message(e.message);
    } else {
      retours[compte] = e instanceof ErreurApi ? e.code : 'injoignable';
      if (e instanceof ErreurApi && ['refus', 'injoignable', 'delai'].includes(e.code)) {
        appeler('GET', 'comptes-mail').then((r) => { comptes = r.comptes; app.rendre(); }).catch(() => {});
      }
    }
  } finally {
    occupe = false;
    app.rendre();
  }
}

function enregistrerCompte(app, compte) {
  const identifiant = (identifiants[compte] ?? comptes[compte].identifiant).trim();
  const motDePasse = motsDePasse[compte] ?? '';
  if (!identifiant || !motDePasse) return;
  delete motsDePasse[compte];  // vidé dès l'envoi, réussi ou non
  agirCompte(app, compte, `mail-${compte}-enregistrer`,
    () => appeler('POST', 'comptes-mail', { corps: { compte, identifiant, mot_de_passe: motDePasse }, delai: DELAI_BOITE }), R.compteEnregistre);
}

function testerCompte(app, compte) {
  agirCompte(app, compte, `mail-${compte}-tester`,
    () => appeler('POST', 'comptes-mail/tester', { corps: { compte }, delai: DELAI_BOITE }), R.connexionReussie);
}

// Passe par la file, comme les autres écritures : l'écran change tout de suite
function supprimerCompte(app, compte) {
  comptes = { ...comptes, [compte]: { ...comptes[compte], enregistre: false, dernier_succes: null, derniere_erreur: null } };
  delete retours[compte];
  delete motsDePasse[compte];
  focus = `mail-${compte}-supprimer`;
  app.rendre();
  app.ecrire('comptes-mail/supprimer', { compte });
  app.message(horsLigne(app) ? T.capture.horsLigne : R.compteSupprime);
}

function ligneCompte(app, compte) {
  const c = comptes[compte];
  const etat = etatCompte(app, c);
  const nom = R.comptesMailNoms[compte];
  const sansReseau = horsLigne(app);
  const enregistrer = h('button', {
    type: 'button', class: 'bouton', 'data-cle': `mail-${compte}-enregistrer`,
    disabled: occupe || sansReseau || !(motsDePasse[compte] ?? ''),
    onclick: () => enregistrerCompte(app, compte),
  }, R.enregistrer);
  return h('section', { class: 'compte-mail', 'aria-label': nom },
    h('h3', {}, nom),
    h('p', { class: 'reglage-ligne', role: 'status' }, h('span', { class: `temoin${etat.ok ? ' allume' : ''}` }), etat.texte),
    retours[compte] && h('p', { class: 'erreur', role: 'alert' }, R.etatsMail[retours[compte]] ?? R.etatsMail.injoignable),
    h('label', {}, h('span', {}, R.identifiant),
      h('input', {
        type: 'email', class: 'champ', autocomplete: 'off', autocapitalize: 'none', autocorrect: 'off', spellcheck: 'false',
        value: identifiants[compte] ?? c.identifiant, 'data-cle': `mail-${compte}-identifiant`,
        oninput: (ev) => { identifiants[compte] = ev.target.value; },
      })),
    h('label', {}, h('span', {}, R.motDePasse),
      h('input', {
        type: 'password', class: 'champ', autocomplete: 'off', autocapitalize: 'none', autocorrect: 'off', spellcheck: 'false',
        value: motsDePasse[compte] ?? '', 'data-cle': `mail-${compte}-mot-de-passe`,
        oninput: (ev) => {
          motsDePasse[compte] = ev.target.value;
          enregistrer.disabled = occupe || horsLigne(app) || ev.target.value === '';
        },
      })),
    h('p', { class: 'reglage-aide' }, R.modeEmploiMail[compte]),
    h('div', { class: 'compte-mail-actions' },
      enregistrer,
      c.enregistre && h('button', {
        type: 'button', class: 'bouton', 'data-cle': `mail-${compte}-tester`, disabled: occupe || sansReseau, onclick: () => testerCompte(app, compte),
      }, R.testerBoite),
      c.enregistre && h('button', {
        type: 'button', class: 'bouton', 'data-cle': `mail-${compte}-supprimer`, disabled: occupe, onclick: () => supprimerCompte(app, compte),
      }, R.supprimerCompte)),
    sansReseau && h('p', { class: 'reglage-aide' }, R.reseauMail));
}

function blocComptesMail(app) {
  return COMPTES_MAIL.map((compte) => ligneCompte(app, compte));
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
      comptes && [h('h2', { class: 'section-titre' }, R.comptesMail), regle(), blocComptesMail(app)],
      h('h2', { class: 'section-titre' }, R.siri), regle(), blocSiri(app),
    ],
    h('h2', { class: 'section-titre' }, R.session), regle(),
    h('button', { type: 'button', class: 'bouton bouton-large', onclick: () => sortir(app, false) }, R.deconnecter),
    h('button', { type: 'button', class: 'bouton bouton-large', onclick: () => sortir(app, true) }, R.deconnecterTout));
}
