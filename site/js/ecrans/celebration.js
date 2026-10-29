// Les célébrations (SPEC § 18.6) : passage de niveau et promotion en plein écran, joker en bandeau, ou un seul
// récapitulatif. Ce qui a déjà été célébré est gardé dans IndexedDB (donnees / celebrations) et écrit AVANT l'affichage :
// une fermeture brutale de l'app ne fait pas recélébrer. Un toucher ou Échap ferme ; zone aria-live polie, annoncée une fois.
import { h } from '../lib/dom.js';
import { NOMS } from '../lib/cartes.js';
import { T } from '../lib/textes.js';
import { aCelebrer, marquerVus, recapARevoir, resumeRecap } from '../lib/xp.js';
import * as stockage from '../stockage.js';

const C = T.celebration;
const DUREES = { niveau: 4500, joker: 5000 };  // la promotion et le récapitulatif attendent un toucher

const zone = h('div', { id: 'celebration', 'aria-live': 'polite', 'aria-atomic': 'true' });
document.body.append(zone);

const enAttente = [];
let courante = null;   // { modal, minuteur }
let precedent = null;  // l'élément qui avait le focus avant la première célébration d'une série
let chaine = Promise.resolve();

const replique = () => C.repliques[Math.floor(Math.random() * C.repliques.length)];

function contenu(item) {
  if (item.type === 'niveau') return { classe: `celebration-niveau d-${item.domaine}`, titre: C.niveau(item.niveau, NOMS[item.domaine]), replique: replique() };
  if (item.type === 'grade') {
    const p = C.promotions[item.grade];
    return { classe: 'celebration-promotion', titre: `${p.passe} ${p.suite}`, replique: p.replique };
  }
  if (item.type === 'joker') return { classe: 'celebration-joker', titre: C.joker, replique: null };
  const niveaux = item.niveaux.map((n) => ({ domaine: NOMS[n.domaine], niveau: n.niveau }));
  return { classe: 'celebration-recap', titre: C.recap(item.gain, item.grade, niveaux), replique: null };
}

function inerter(oui) {
  for (const id of ['principal', 'feuille']) {
    const el = document.getElementById(id);
    if (el) el.inert = oui;
  }
}

function afficher() {
  if (courante || enAttente.length === 0) return;
  const item = enAttente.shift();
  const { classe, titre, replique: suite } = contenu(item);
  const modal = item.type !== 'joker';
  if (precedent === null) precedent = document.activeElement;
  const bouton = h('button', { type: 'button', class: 'celebration-fermer', 'aria-label': C.fermer, onclick: fermer });
  zone.replaceChildren(h('div', { class: `celebration ${classe}` },
    h('p', { class: 'celebration-titre' }, titre),
    suite && h('p', { class: 'celebration-replique' }, suite),
    bouton));
  courante = { modal, minuteur: DUREES[item.type] ? setTimeout(fermer, DUREES[item.type]) : null };
  if (modal) {
    inerter(true);
    bouton.focus({ preventScroll: true });
  }
}

function fermer() {
  if (!courante) return;
  clearTimeout(courante.minuteur);
  if (courante.modal) inerter(false);
  zone.replaceChildren();
  courante = null;
  if (enAttente.length > 0) {
    afficher();
    return;
  }
  if (precedent?.isConnected) precedent.focus({ preventScroll: true });
  precedent = null;
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && courante) fermer();
});

async function travailler(app, xp) {
  const memo = (await stockage.lire('donnees', 'celebrations')) ?? null;
  const { mode, evenements } = aCelebrer(xp.evenements, memo);
  if (mode === 'aucune' && memo?.total_vu === xp.total) return;
  const recap = mode === 'recap' ? resumeRecap(evenements, xp.total, memo) : null;
  const dernier = recap ?? memo?.dernier_recap;
  await stockage.ecrire('donnees', 'celebrations', { ...marquerVus(memo, evenements, xp.total, app.maintenant()), ...(dernier && { dernier_recap: dernier }) });
  if (mode === 'simple') enAttente.push(...evenements);
  else if (recap) enAttente.push({ type: 'recap', ...recap });
  afficher();
}

// Moi, « Revoir le récapitulatif » : le rejoue sans rien marquer comme vu
export function revoirRecap(xp) {
  chaine = chaine.then(async () => {
    const memo = (await stockage.lire('donnees', 'celebrations')) ?? null;
    enAttente.push({ type: 'recap', ...recapARevoir(memo, xp) });
    afficher();
  }).catch(() => {});
}

// À appeler après chaque mise à jour de l'objet xp (lecture des données, réponse d'une écriture). Les appels
// s'enchaînent : deux vérifications proches ne célèbrent jamais deux fois le même événement.
export function verifier(app, xp) {
  if (!Array.isArray(xp?.evenements)) return;
  chaine = chaine.then(() => travailler(app, xp)).catch(() => {});
}
