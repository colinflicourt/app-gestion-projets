// L'onglet Mails (SPEC § 16.8) : la liste des 4 boîtes (#/mails), puis une boîte à la fois (#/mails/utc), en lecture
// seule, pubs repliées. L'écran montre sa copie locale, puis GET mails, puis remplace chaque section à mesure que le
// rafraîchissement de sa boîte revient.
import { h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { appeler, ErreurApi, ErreurReseau } from '../api.js';
import * as stockage from '../stockage.js';
import { deplacerCorrections, etatSection, heureMail } from '../lib/mails.js';
import { ageTexte, minutesEntre } from '../lib/temps.js';

const M = T.mails;
const CODES_BOITE = ['refus', 'injoignable', 'delai'];
const LIENS = { utc: 'https://webmail.utc.fr', icloud: 'https://www.icloud.com/mail/', qyris: 'https://www.ovhcloud.com/fr/mail/' };
const GMAIL_APP = 'googlegmail://';
const GMAIL_WEB = 'https://mail.google.com/';
const REPLI_GMAIL = 1500;
const RECENT = 30000;

const etat = {
  sections: null,        // [{ section, non_lus, mails, pubs, pubs_total }] dans l'ordre utc, qyris, gmail, icloud
  comptes: null,         // compte → { recupere_le, erreur }
  charge: false,
  dernier: 0,
  pubsOuvertes: new Set(),
  ouvert: null,          // le mail dont les actions sont affichées
  corrections: new Map(), // adresse → choix, envoyées depuis cet écran
};

const cleMail = (m) => `${m.compte}/${m.uid}`;

function redessiner(app) {
  if (location.hash.startsWith('#/mails')) app.rendre();
}

async function sauvegarder() {
  try {
    await stockage.ecrire('donnees', 'mails', { sections: etat.sections, comptes: etat.comptes });
  } catch {
    // la copie reste en mémoire
  }
}

function remplacer(sections) {
  etat.sections ??= [];
  for (const s of sections) {
    const i = etat.sections.findIndex((x) => x.section === s.section);
    if (i >= 0) etat.sections[i] = s;
    else etat.sections.push(s);
  }
}

async function rafraichir(app, compte) {
  try {
    const r = await appeler('POST', 'mails/rafraichir', { corps: { compte }, delai: 30000 });
    remplacer(r.sections);
    etat.comptes = { ...etat.comptes, ...r.comptes };
    await sauvegarder();
  } catch (e) {
    if (e instanceof ErreurApi && e.statut === 401) app.demanderConnexion(true);
    else if (e instanceof ErreurApi && CODES_BOITE.includes(e.code)) etat.comptes = { ...etat.comptes, [compte]: { ...etat.comptes[compte], erreur: e.code } };
    else if (e instanceof ErreurReseau) app.etat.horsLigne = true;
  }
  redessiner(app);
}

async function charger(app) {
  if (etat.charge || app.etat.connexion) return;
  etat.charge = true;
  redessiner(app);
  try {
    if (etat.sections === null) {
      try {
        const copie = await stockage.lire('donnees', 'mails');
        if (copie && etat.sections === null) Object.assign(etat, { sections: copie.sections, comptes: copie.comptes });
      } catch {
        // pas de copie locale
      }
      redessiner(app);
    }
    const r = await appeler('GET', 'mails');
    Object.assign(etat, { sections: r.sections, comptes: r.comptes, dernier: Date.now() });
    await sauvegarder();
    redessiner(app);
    await Promise.all(Object.entries(r.comptes).filter(([, c]) => c.erreur !== 'non_connecte').map(([compte]) => rafraichir(app, compte)));
  } catch (e) {
    if (e instanceof ErreurApi && e.statut === 401) app.demanderConnexion(etat.sections !== null);
    else if (e instanceof ErreurReseau) app.etat.horsLigne = true;
  } finally {
    etat.charge = false;
    redessiner(app);
  }
}

export function apres(app, route, ecran, changement) {
  if (changement && (etat.sections === null || Date.now() - etat.dernier > RECENT)) charger(app);
}

function corriger(app, mail, choix) {
  etat.corrections.set(mail.de_adresse, choix);
  etat.ouvert = null;
  app.rendre();
  app.ecrire('mails/expediteur', { adresse: mail.de_adresse, choix });
  app.message(M.retenu);
}

// La section UTC n'est jamais filtrée : pas de correction possible
function actionDe(mail, estPub, section) {
  if (section === 'utc') return null;
  if (estPub) return [M.pasUnePub, 'afficher'];
  return mail.signes_pub.length > 0 ? [M.toujoursAfficher, 'afficher'] : [M.cestUnePub, 'pub'];
}

function carteMail(app, mail, { estPub, section, qyris }) {
  const ouvert = etat.ouvert === cleMail(mail);
  const action = actionDe(mail, estPub, section);
  return h('li', { class: `mail${mail.non_lu ? ' non-lu' : ''}${estPub ? ' est-pub' : ''}` },
    h('button', {
      type: 'button', class: 'mail-corps', 'aria-expanded': action ? String(ouvert) : null,
      onclick: () => {
        etat.ouvert = ouvert ? null : cleMail(mail);
        app.rendre();
      },
    },
    h('span', { class: 'mail-ligne' },
      h('span', { class: 'mail-de' }, mail.de_nom || mail.de_adresse),
      qyris && h('span', { class: 'mail-etiquette' }, M.etiquetteCompte[mail.compte]),
      h('span', { class: 'mail-heure' }, heureMail(mail.date, app.maintenant()))),
    h('span', { class: 'mail-objet' }, mail.objet),
    mail.extrait && h('span', { class: 'mail-extrait' }, mail.extrait)),
    action && ouvert && h('div', { class: 'mail-actions' },
      h('button', { type: 'button', class: 'bouton bouton-petit', onclick: () => corriger(app, mail, action[1]) }, action[0])));
}

function avis(section, { compte, code }) {
  if (code === 'non_connecte') {
    return h('div', { class: 'mails-avis' },
      h('p', {}, section === 'qyris' && h('strong', {}, `${M.adresseCompte[compte]} : `), M.pasConnecte),
      h('a', { class: 'bouton bouton-petit', href: '#/reglages' }, M.ouvrirReglages));
  }
  const texte = { utc: M.erreurUtc, gmail: M.erreurGmail, icloud: M.erreurIcloud }[compte] ?? M.erreurQyris(M.adresseCompte[compte]);
  return h('div', { class: 'mails-avis erreur', role: 'status' }, h('p', {}, texte));
}

function ouvrirGmail() {
  const repli = setTimeout(() => {
    if (document.visibilityState === 'visible') location.href = GMAIL_WEB;
  }, REPLI_GMAIL);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') clearTimeout(repli);
  }, { once: true });
  location.href = GMAIL_APP;
}

function boutonOuvrir(section) {
  const contenu = [M.ouvrir[section], icone('externe', 16)];
  if (LIENS[section]) return h('a', { class: 'bouton-contour bouton-large mails-ouvrir', href: LIENS[section], target: '_blank', rel: 'noopener noreferrer' }, contenu);
  return h('button', { type: 'button', class: 'bouton-contour bouton-large mails-ouvrir', onclick: ouvrirGmail }, contenu);
}

function ageSection(app, section) {
  const comptes = section === 'qyris' ? ['colin', 'contact'] : [section];
  const dates = comptes.map((c) => etat.comptes?.[c]?.recupere_le).filter(Boolean).map((iso) => new Date(iso));
  if (dates.length === 0) return null;
  const plusVieux = dates.reduce((a, b) => (a < b ? a : b));
  return M.aJourIlYa(ageTexte(minutesEntre(plusVieux, app.maintenant())));
}

function rendreSection(app, s) {
  const qyris = s.section === 'qyris';
  const ouvertes = etat.pubsOuvertes.has(s.section);
  const age = app.etat.horsLigne ? ageSection(app, s.section) : null;
  const liste = (mails, estPub) => h('ul', { class: `mails-liste${estPub ? ' mails-pubs' : ''}` }, mails.map((m) => carteMail(app, m, { estPub, section: s.section, qyris })));
  return h('section', { class: 'mails-section', 'aria-label': M.sections[s.section] },
    h('h2', { class: 'section-titre groupe-titre' }, h('span', {}, M.derniers), h('span', {}, M.nonLus(s.non_lus))),
    age && h('p', { class: 'mails-age' }, age),
    etatSection(s.section, etat.comptes).map((a) => avis(s.section, a)),
    s.mails.length > 0 && liste(s.mails, false),
    s.pubs_total > 0 && h('button', {
      type: 'button', class: 'bouton-contour bouton-large mails-bascule', 'aria-expanded': String(ouvertes),
      onclick: () => {
        if (ouvertes) etat.pubsOuvertes.delete(s.section);
        else etat.pubsOuvertes.add(s.section);
        app.rendre();
      },
    }, ouvertes ? M.masquerPubs : M.pubs(s.pubs_total)),
    ouvertes && s.pubs.length > 0 && liste(s.pubs, true),
    boutonOuvrir(s.section));
}

// Une ligne par boîte : son nom, ses non lus (ou « Ne répond pas », « Pas encore connecté »), et elle ouvre la boîte
function ligneBoite(s) {
  const avisBoite = etatSection(s.section, etat.comptes);
  const enPanne = avisBoite.some((a) => a.code !== 'non_connecte');
  const resume = enPanne ? M.neRepondPas : avisBoite.length > 0 && s.mails.length === 0 ? M.pasConnecte : M.nonLus(s.non_lus);
  return h('li', {},
    h('a', { class: `mails-boite${s.non_lus > 0 ? ' non-lu' : ''}${enPanne ? ' erreur' : ''}`, href: `#/mails/${s.section}` },
      h('span', { class: 'mails-boite-nom' }, M.sections[s.section]),
      h('span', { class: 'mails-boite-etat' }, resume),
      icone('droite', 18)));
}

export function rendre(app, route) {
  const enFile = app.etat.file.filter((e) => e.route === 'mails/expediteur').map((e) => [e.corps.adresse, e.corps.choix]);
  const sections = etat.sections && deplacerCorrections(etat.sections, new Map([...etat.corrections, ...enFile]));
  const actualisation = etat.charge && sections !== null ? T.etats.actualisation : '';
  const attente = h('p', { class: 'vide' }, app.etat.horsLigne ? T.etats.vide : T.agenda.chargement);
  const boite = M.sections[route?.param] ? route.param : null;
  if (boite) {
    const s = sections?.find((x) => x.section === boite);
    return h('div', { class: 'mails' },
      h('header', { class: 'entete-retour' },
        h('button', { type: 'button', class: 'bouton-carre', 'aria-label': M.retour, onclick: () => app.retour('#/mails') }, icone('gauche', 20, 2)),
        h('h1', { class: 'titre-ecran' }, M.sections[boite]),
        h('span', { class: 'entete-cote' }, actualisation)),
      s ? rendreSection(app, s) : attente);
  }
  return h('div', { class: 'mails' },
    h('header', { class: 'entete' }, h('h1', { class: 'titre-ecran' }, M.titre), h('span', { class: 'entete-cote' }, actualisation)),
    h('p', { class: 'mails-note' }, M.lectureSeule),
    sections === null ? attente : h('ul', { class: 'mails-boites', 'aria-label': M.boites }, sections.map(ligneBoite)));
}
