// L'écran d'un mail (SPEC § 17.4), adresse #/mails/<section>/<compte>/<uid>. L'ouverture (POST mails/ouvrir : en-têtes,
// pièces, marque lu) et le corps (GET mails/corps, dans un cadre isolé) partent en même temps. Rien du contenu n'est
// gardé : ni copie locale, ni cache. Seul le compteur de non lus change, par `ctx.marquerLu`.
// L'écran garde son propre nœud : le cadre se recharge dès qu'on le retire du document, donc app.rendre() ne doit pas le refaire.
import { h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { appeler, ErreurApi, ErreurReseau } from '../api.js';
import { dateComplete, taillePiece } from '../lib/mails.js';

const M = T.mails;
const SANDBOX = 'allow-same-origin allow-popups allow-popups-to-escape-sandbox';
const REPLI_REPONSE = 1500;
const SCHEMAS_REPONSE = /^(https?|mailto|googlegmail):/i;

let session = null;

export function quitter() {
  session?.observateur?.disconnect();
  session = null;
}

// cible : { section, compte, uid } ; ctx : { marquerLu(compte, uid), boutonOuvrir(section) }
export function rendre(app, cible, ctx) {
  const cle = `${cible.compte}/${cible.uid}`;
  if (session?.cle !== cle) {
    quitter();
    session = creer(app, cible, cle, ctx);
  }
  return session.racine;
}

function adresseCorps(cible, images) {
  const p = new URLSearchParams({ compte: cible.compte, uid: String(cible.uid) });
  if (images) p.set('images', '1');
  return `/api/mails/corps?${p}`;
}

function creer(app, cible, cle, ctx) {
  const s = {
    cle, cible, ctx, phase: 'attente', mail: null, code: null, images: false, imagesBloquees: false, observateur: null, mesurer: null,
    barre: h('header', { class: 'mail-barre' }),
    tete: h('div', { class: 'mail-tete' }),
    etat: h('div', { class: 'mail-etat' }),
    pieces: h('div', { class: 'mail-pieces-zone' }),
  };
  const horsLigne = app.etat.horsLigne;
  s.cadre = h('iframe', {
    class: 'mail-cadre', title: M.corps, sandbox: SANDBOX, referrerpolicy: 'no-referrer', hidden: true,
    src: horsLigne ? null : adresseCorps(cible, false),
  });
  s.cadre.addEventListener('load', () => attacher(s));
  s.racine = h('div', { class: 'mail-ecran' }, s.barre, s.tete, s.etat, h('div', { class: 'mail-cadre-zone' }, s.cadre), s.pieces);
  dessiner(app, s);
  if (horsLigne) erreur(app, s, 'reseau');
  else ouvrir(app, s);
  return s;
}

async function ouvrir(app, s) {
  try {
    const r = await appeler('POST', 'mails/ouvrir', { corps: { compte: s.cible.compte, uid: s.cible.uid }, delai: 30000 });
    if (r.marque_lu === true) s.ctx.marquerLu(s.cible.compte, s.cible.uid);
    if (session !== s) return;
    s.mail = r;
    s.phase = 'ok';
    s.cadre.hidden = false;
    s.mesurer?.();
    dessiner(app, s);
  } catch (e) {
    if (session !== s) return;
    if (e instanceof ErreurApi && e.statut === 401) {
      app.demanderConnexion(true);
      return;
    }
    erreur(app, s, e instanceof ErreurApi && e.statut === 404 && ['hors_copie', 'absent'].includes(e.code) ? e.code : e instanceof ErreurReseau ? 'reseau' : 'autre');
  }
}

function erreur(app, s, code) {
  s.phase = 'erreur';
  s.code = code;
  s.cadre.hidden = true;
  dessiner(app, s);
}

// Le cadre est chargé : hauteur, liens, images bloquées. Appelé à chaque chargement (le bouton « Afficher les images » recharge).
function attacher(s) {
  let doc;
  try {
    doc = s.cadre.contentDocument;
  } catch {
    return;
  }
  if (!doc?.documentElement || doc.location.href === 'about:blank') return;
  s.observateur?.disconnect();
  s.mesurer = () => s.cadre.style.setProperty('--hauteur', `${Math.ceil(doc.documentElement.offsetHeight)}px`);
  s.mesurer();
  s.observateur = new ResizeObserver(s.mesurer);
  s.observateur.observe(doc.documentElement);
  doc.addEventListener('click', ouvrirLien);
  const bloquees = doc.body?.dataset.imagesBloquees === '1';
  if (bloquees !== s.imagesBloquees) {
    s.imagesBloquees = bloquees;
    dessinerImages(s);
  }
}

// Un lien du mail s'ouvre hors de Junior, jamais dans le cadre
function ouvrirLien(e) {
  const a = e.target?.closest?.('a[href]');
  if (!a) return;
  e.preventDefault();
  let url;
  try {
    url = new URL(a.href);
  } catch {
    return;
  }
  if (url.protocol === 'http:' || url.protocol === 'https:') window.open(url.href, '_blank', 'noopener');
  else if (url.protocol === 'mailto:' || url.protocol === 'tel:') location.href = url.href;
}

function afficherImages(s) {
  s.images = true;
  s.imagesBloquees = false;
  dessinerImages(s);
  s.cadre.src = adresseCorps(s.cible, true);
}

// « Répondre » : une adresse web s'ouvre dans un nouvel onglet ; une app (mailto, Gmail) par l'adresse, avec le repli web
// de Gmail si la page est encore visible 1,5 s après (même mécanique que « Ouvrir dans Gmail »)
function repondre(mail) {
  if (!SCHEMAS_REPONSE.test(mail.repondre_url ?? '')) return;
  if (/^https?:/i.test(mail.repondre_url)) {
    window.open(mail.repondre_url, '_blank', 'noopener');
    return;
  }
  if (mail.repondre_repli) {
    const repli = setTimeout(() => {
      if (document.visibilityState === 'visible') location.href = mail.repondre_repli;
    }, REPLI_REPONSE);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden') clearTimeout(repli);
    }, { once: true });
  }
  location.href = mail.repondre_url;
}

// Pièce jointe : un lien vers la route en flux (décision 11). Isolé ici pour passer plus tard à fetch + partage si l'essai
// sur l'iPhone l'exige.
function ouvrirPiece(s, piece) {
  const p = new URLSearchParams({ compte: s.cible.compte, uid: String(s.cible.uid), partie: piece.partie });
  return h('a', { class: 'mail-piece', href: `/api/mails/piece?${p}`, target: '_blank', rel: 'noopener' }, contenuPiece(piece));
}

function contenuPiece(piece) {
  return [
    icone(piece.type?.startsWith('image/') ? 'image' : 'fichier', 22),
    h('span', { class: 'mail-piece-nom' }, piece.nom),
    h('span', { class: 'mail-piece-taille' }, taillePiece(piece.taille)),
  ];
}

function noms(liste) {
  return liste.map((p) => p.nom || p.adresse).join(', ');
}

function dessiner(app, s) {
  const { cible, mail } = s;
  s.barre.replaceChildren(
    h('button', { type: 'button', class: 'mail-retour', onclick: () => app.retour(`#/mails/${cible.section}`) }, icone('gauche', 18, 2), M.retourBoite),
    s.phase === 'ok' && h('button', { type: 'button', class: 'bouton bouton-petit mail-repondre', onclick: () => repondre(mail) }, icone('repondre', 16, 2), M.repondre));
  if (s.phase === 'ok') {
    s.tete.replaceChildren(
      h('h1', { class: 'mail-titre' }, mail.objet || M.sansObjet),
      h('div', { class: 'mail-de-ligne' },
        h('div', { class: 'mail-de-qui' },
          mail.de_nom && h('span', { class: 'mail-de-nom' }, mail.de_nom),
          h('span', { class: mail.de_nom ? 'mail-de-adresse' : 'mail-de-nom' }, mail.de_adresse)),
        mail.pour_contact && h('span', { class: 'mail-etiquette' }, M.etiquetteContact)),
      h('p', { class: 'mail-date' }, dateComplete(mail.date, app.maintenant())),
      (mail.a.length > 0 || mail.cc.length > 0) && h('p', { class: 'mail-dest' },
        mail.a.length > 0 && h('span', {}, h('span', { class: 'mail-dest-nom' }, `${M.a} `), noms(mail.a)),
        mail.cc.length > 0 && h('span', {}, h('span', { class: 'mail-dest-nom' }, `${M.cc} `), noms(mail.cc))));
    s.pieces.replaceChildren(mail.pieces.length > 0 && h('ul', { class: 'mail-pieces', 'aria-label': M.pieces },
      mail.pieces.map((p) => h('li', {}, p.trop_lourde
        ? h('button', { type: 'button', class: 'mail-piece', onclick: () => app.message(M.tropLourde(M.nomsBoites[cible.section])) }, contenuPiece(p))
        : ouvrirPiece(s, p)))));
  } else {
    s.tete.replaceChildren();
    s.pieces.replaceChildren();
  }
  dessinerEtat(s);
}

function dessinerEtat(s) {
  if (s.phase === 'attente') {
    s.etat.replaceChildren(h('p', { class: 'vide', role: 'status' }, M.ouverture));
  } else if (s.phase === 'erreur') {
    const texte = { hors_copie: M.tropAncien(M.nomsBoites[s.cible.section]), absent: M.plusDansBoite }[s.code] ?? M.nOuvrePas;
    s.etat.replaceChildren(
      h('div', { class: 'mails-avis erreur', role: 'status' }, h('p', {}, texte)),
      s.code !== 'absent' && s.ctx.boutonOuvrir(s.cible.section));
  } else {
    s.etat.replaceChildren(h('div', { class: 'mail-images', hidden: !s.imagesBloquees }));
    dessinerImages(s);
  }
}

function dessinerImages(s) {
  const zone = s.etat.querySelector('.mail-images');
  if (!zone) return;
  zone.hidden = !s.imagesBloquees;
  zone.replaceChildren(...(s.imagesBloquees ? [
    h('span', {}, M.imagesMasquees),
    h('button', { type: 'button', class: 'bouton bouton-petit', onclick: () => afficherImages(s) }, M.afficherImages),
  ] : []));
}
