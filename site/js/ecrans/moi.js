// Moi au lot 1 (SPEC § 8.1) : Google Agenda, la session, la version. Grade, XP, album et badges
// arrivent au lot 4, avec un lien vers Réglages.
import { h } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { appeler, ErreurReseau } from '../api.js';
import { regle } from './commun.js';

const M = T.moi;
let version = null;
let erreurGoogle = false;

// Retour de Google (#/moi?google=…) : message, actualisation, puis l'adresse est nettoyée
export function apres(app, route) {
  const google = route.requete.get('google');
  if (google) {
    history.replaceState(null, '', '#/moi');
    erreurGoogle = google === 'erreur';
    if (google === 'connecte') {
      app.message(M.connecteMessage);
      app.actualiser(true);
    }
    app.rendre();
    return;
  }
  if (version === null) {
    version = '';
    appeler('GET', 'sante')
      .then((r) => {
        version = r.version_app;
        app.rendre();
      })
      .catch(() => {
        version = null;
      });
  }
}

async function sortir(app, partout) {
  const n = app.etat.file.length;
  if (n > 0 && !window.confirm(M.confirmerFile(n))) return;
  if (partout && !window.confirm(M.confirmerTout)) return;
  try {
    await app.deconnecter(partout);
  } catch (e) {
    app.message(e instanceof ErreurReseau ? M.reseau : e.message);
  }
}

export function rendre(app) {
  const v = app.vue();
  const connecte = Boolean(v?.agenda.google_connecte) && v.agenda.erreur !== 'google_deconnecte';
  return h('div', { class: 'moi' },
    h('header', { class: 'entete' }, h('h1', { class: 'titre-ecran' }, M.titre)),
    h('h2', { class: 'section-titre' }, M.google), regle(),
    h('p', { class: 'reglage-ligne' }, h('span', { class: `temoin${connecte ? ' allume' : ''}` }), connecte ? M.connecte : M.nonConnecte),
    erreurGoogle && h('p', { class: 'erreur', role: 'alert' }, M.erreurGoogle),
    connecte
      ? h('a', { class: 'bouton bouton-large', href: '/api/google/connexion' }, M.reconnecter)
      : h('div', { class: 'cuvette cuvette-large' }, h('a', { class: 'bouton-allume bouton-large', href: '/api/google/connexion' }, M.connecter)),
    h('h2', { class: 'section-titre' }, M.session), regle(),
    h('button', { type: 'button', class: 'bouton bouton-large', onclick: () => sortir(app, false) }, M.deconnecter),
    h('button', { type: 'button', class: 'bouton bouton-large', onclick: () => sortir(app, true) }, M.deconnecterTout),
    h('h2', { class: 'section-titre' }, M.app), regle(),
    h('a', { class: 'reglage-ligne lien', href: '#/reglages' }, M.reglages),
    h('p', { class: 'reglage-ligne' }, version ? M.version(version) : T.app),
    h('a', { class: 'reglage-ligne lien', href: '/confidentialite.html' }, M.confidentialite));
}
