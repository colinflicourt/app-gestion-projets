// Moi (SPEC § 8.1 et § 18.6) : grade et XP de Junior, puis Google Agenda, la version, le lien vers Réglages (où est la
// session). Album, badges, journal et Wrapped arrivent aux sous-lots suivants.
import { h } from '../lib/dom.js';
import { DOMAINES, NOMS } from '../lib/cartes.js';
import { T } from '../lib/textes.js';
import { appeler } from '../api.js';
import { regle } from './commun.js';
import { revoirRecap } from './celebration.js';

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

const barre = (valeur, max, nom) => h('div', {
  class: 'xp-barre', role: 'progressbar', 'aria-label': nom, 'aria-valuemin': '0', 'aria-valuenow': String(valeur), 'aria-valuemax': String(max),
}, h('span', { class: 'xp-barre-plein', vars: { '--plein': String(max > 0 ? Math.min(1, valeur / max) : 1) } }));

const nomDuJour = (jour) => new Intl.DateTimeFormat('fr-FR', { weekday: 'long', timeZone: 'UTC' }).format(new Date(`${jour}T12:00:00Z`));

function blocXp(xp) {
  const plafond = xp.seuil_suivant === null;
  const grade = M.grade(M.grades[xp.grade], xp.total, xp.seuil_suivant);
  const serie = xp.serie;
  return h('section', { class: 'xp' },
    h('div', { class: 'xp-grade' },
      h('p', { class: 'xp-grade-ligne' }, grade),
      plafond ? barre(1, 1, grade) : barre(xp.total - xp.seuil_grade, xp.seuil_suivant - xp.seuil_grade, grade)),
    h('ul', { class: 'xp-domaines' }, DOMAINES.map((d) => {
      const x = xp.domaines[d];
      return h('li', { class: `xp-domaine d-${d}` },
        h('span', { class: 'xp-nom' }, NOMS[d]),
        h('span', { class: 'xp-niveau' }, M.niveau(x.niveau)),
        barre(x.dans_niveau, x.taille_niveau, T.aujourdhui.trancheXp(NOMS[d], x.niveau, x.dans_niveau, x.taille_niveau)));
    })),
    h('p', { class: 'xp-serie' }, M.serie(serie.jours)),
    h('p', { class: 'xp-joker' }, serie.joker_libre || !serie.dernier_joker ? M.jokerLibre : M.jokerUtilise(nomDuJour(serie.dernier_joker))),
    serie.en_jeu && h('p', { class: 'xp-jeu' }, M.jokerEnJeu),
    h('button', { type: 'button', class: 'lien-discret xp-revoir', onclick: () => revoirRecap(xp) }, M.revoirRecap));
}

export function rendre(app) {
  const v = app.vue();
  const connecte = Boolean(v?.agenda.google_connecte) && v.agenda.erreur !== 'google_deconnecte';
  return h('div', { class: 'moi' },
    h('header', { class: 'entete' }, h('h1', { class: 'titre-ecran' }, M.titre)),
    v?.xp && blocXp(v.xp),
    h('h2', { class: 'section-titre' }, M.google), regle(),
    h('p', { class: 'reglage-ligne' }, h('span', { class: `temoin${connecte ? ' allume' : ''}` }), connecte ? M.connecte : M.nonConnecte),
    erreurGoogle && h('p', { class: 'erreur', role: 'alert' }, M.erreurGoogle),
    connecte
      ? h('a', { class: 'bouton bouton-large', href: '/api/google/connexion' }, M.reconnecter)
      : h('div', { class: 'cuvette cuvette-large' }, h('a', { class: 'bouton-allume bouton-large', href: '/api/google/connexion' }, M.connecter)),
    h('h2', { class: 'section-titre' }, M.app), regle(),
    h('a', { class: 'reglage-ligne lien', href: '#/reglages' }, M.reglages),
    h('p', { class: 'reglage-ligne' }, version ? M.version(version) : T.app),
    h('a', { class: 'reglage-ligne lien', href: '/confidentialite.html' }, M.confidentialite));
}
