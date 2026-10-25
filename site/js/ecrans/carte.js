// Une carte ouverte : étapes à cocher, Terminer (avec Annuler 5 s), Déplacer, Ouvrir dans Trello.
import { h, icone, texteAvecLiens } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { COLONNES, NOMS, avancement, libelleEcheance, trouverCarte } from '../lib/cartes.js';
import { estEclair, gainCarte, gainEtape } from '../lib/xp.js';
import { tampon } from './commun.js';

const DUREE_GAIN = 1600;

let choixColonne = null;  // la carte dont « Déplacer vers » est ouvert
let gainAffiche = null;   // « +5 XP » près d'une étape : { element, gain, depuis }
let minuteurGain = null;

export function quitter() {
  choixColonne = null;
  gainAffiche = null;
  clearTimeout(minuteurGain);
}

// L'étape proposée par le tirage est amenée au milieu de l'écran
export function apres(app, route, ecran, changement) {
  if (changement) ecran.querySelector('.etape-proposee')?.scrollIntoView({ block: 'center' });
}

// Le gain d'une étape cochée est estimé ici (décision 8) : le serveur recale les totaux à sa réponse
function cocher(app, carte, element) {
  const coche = !element.coche;
  const local = {};
  if (coche) {
    const gain = gainEtape(app.vue().xp_paye);
    if (gain > 0) {
      local.xp = { genre: 'etapes', n: 1 };
      gainAffiche = { element: element.id, gain, depuis: performance.now() };
      clearTimeout(minuteurGain);
      minuteurGain = setTimeout(() => {
        gainAffiche = null;
        app.rendre();
      }, DUREE_GAIN);
    }
  } else {
    local.xp = { genre: 'etapes', n: -1 };
  }
  app.ecrire('taches/cocher', { carte: carte.id, element: element.id, coche }, local);
}

function ligneEtape(app, carte, element, proposee) {
  return h('li', { class: `etape${proposee ? ' etape-proposee' : ''}${element.coche ? ' est-cochee' : ''}` },
    h('button', {
      type: 'button', class: 'etape-case', 'aria-pressed': String(element.coche),
      'aria-label': element.coche ? T.carte.decocher(element.nom) : T.carte.cocher(element.nom),
      onclick: () => cocher(app, carte, element),
    }, element.coche ? tampon(T.carte.fait, element.id, `tampon-fait${element.attente ? ' frappe' : ''}`) : h('span', { class: 'case' })),
    h('span', { class: 'etape-nom' }, element.nom),
    gainAffiche?.element === element.id && h('span', { class: 'etape-xp', role: 'status', vars: { '--ecoule': `-${Math.round(performance.now() - gainAffiche.depuis)}ms` } }, T.carte.gainEtape(gainAffiche.gain)),
    element.attente && h('span', { class: 'carte-attente' }, T.taches.attente));
}

async function terminer(app, carte) {
  const quand = app.maintenant();
  const gain = gainCarte(carte, quand, app.vue().xp_paye);
  const local = { colonne_avant: carte.colonne, position_avant: carte.position, echeance_faite_avant: carte.echeance_faite };
  if (gain > 0 && estEclair(carte.id, quand)) local.xp = { genre: 'eclairs', n: 1 };
  const e = await app.ecrire('taches/terminer', { carte: carte.id }, local);
  app.message(gain > 0 ? T.carte.termineeXp(gain, NOMS[carte.domaine]) : T.carte.terminee, { action: { libelle: T.carte.annuler, faire: () => app.annulerTerminer(e) }, delai: 5000 });
  app.retour(`#/taches/${carte.domaine}`);
}

function deplacer(app, carte, colonne) {
  choixColonne = null;
  if (carte.colonne === colonne) app.rendre();
  else app.ecrire('taches/deplacer', { carte: carte.id, colonne });
}

export function rendre(app, route) {
  const v = app.vue();
  const carte = trouverCarte(v?.trello.cartes ?? [], route.param);
  const retour = h('button', { type: 'button', class: 'bouton-carre', 'aria-label': T.carte.retour, onclick: () => app.retour('#/taches') }, icone('gauche', 20, 2));
  if (!carte) return h('div', { class: 'carte-ecran' }, h('header', { class: 'entete-retour' }, retour), h('p', { class: 'vide' }, v ? T.carte.introuvable : T.etats.vide));
  const etape = route.requete.get('etape');
  const echeance = libelleEcheance(carte, app.maintenant());
  const progres = avancement(carte);
  return h('div', { class: `carte-ecran d-${carte.domaine}` },
    h('header', { class: 'entete-retour' }, retour),
    h('article', { class: 'carte-ouverte' },
      h('div', { class: 'carte-puce' }, h('span', { class: 'carre' }), h('span', { class: 'carte-puce-texte' }, [NOMS[carte.domaine], ...carte.etiquettes].join(' · ')), progres && h('span', { class: 'carte-progres' }, progres)),
      h('h1', { class: 'carte-ouverte-titre' }, carte.titre),
      (echeance || carte.urgent || carte.colonne === 'fait') && h('p', { class: 'carte-meta' },
        carte.colonne === 'fait' && tampon(T.carte.fait, carte.id, 'carte-tampon'),
        carte.urgent && h('span', { class: 'etiquette-urgent' }, T.carte.urgent),
        echeance && h('span', { class: `carte-echeance${echeance.retard ? ' retard' : ''}` }, echeance.texte)),
      carte.description && h('div', { class: 'carte-description' }, carte.description.split(/\n{2,}/).map((p) => h('p', {}, texteAvecLiens(p)))),
      carte.checklists.map((liste) => h('section', { class: 'checklist', 'aria-label': liste.nom },
        carte.checklists.length > 1 && h('h2', { class: 'section-titre' }, liste.nom),
        h('ul', { class: 'etapes' }, liste.elements.map((element) => ligneEtape(app, carte, element, element.id === etape))))),
      h('div', { class: 'carte-actions' },
        carte.colonne !== 'fait' && h('div', { class: 'cuvette carte-terminer' }, h('button', { type: 'button', class: 'bouton-allume', onclick: () => terminer(app, carte) }, T.carte.terminer)),
        h('button', {
          type: 'button', class: 'bouton carte-deplacer', 'aria-expanded': String(choixColonne === carte.id),
          onclick: () => {
            choixColonne = choixColonne === carte.id ? null : carte.id;
            app.rendre();
          },
        }, T.carte.deplacer)),
      choixColonne === carte.id && h('div', { class: 'choix-colonnes', role: 'group', 'aria-label': T.carte.deplacerVers },
        ['a_faire', 'en_cours', 'en_attente'].map((colonne) => h('button', {
          type: 'button', class: 'filtre', 'aria-pressed': String(carte.colonne === colonne), onclick: () => deplacer(app, carte, colonne),
        }, COLONNES[colonne]))),
      carte.url.startsWith('https://trello.com/') && h('a', { class: 'bouton-contour bouton-large', href: carte.url, target: '_blank', rel: 'noopener noreferrer' }, T.carte.ouvrirTrello, icone('externe', 16, 2))));
}
