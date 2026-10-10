// Ce que dit Junior en haut d'Aujourd'hui : la salutation et la phrase du jour (SPEC § 8.2). Fonctions pures.
import { T } from './textes.js';
import { enParis, jourSemaine } from './temps.js';

export function salutation(maintenant) {
  const { heure } = enParis(maintenant);
  const s = T.aujourdhui.salutations;
  if (heure >= 5 && heure < 12) return s.matin;
  if (heure >= 12 && heure < 18) return s.apresMidi;
  if (heure >= 18 && heure < 23) return s.soir;
  return s.nuit;
}

function hacher(texte) {
  let n = 2166136261;
  for (const c of texte) {
    n ^= c.codePointAt(0);
    n = Math.imul(n, 16777619);
  }
  return n >>> 0;
}

// Aléa à graine (mulberry32) : la même graine donne toujours la même suite
export function aleaDepuis(graine) {
  let a = hacher(graine);
  return () => {
    a = (a + 0x6D2B79F5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

export function choixPondere(elements, poids, alea) {
  let reste = alea() * poids.reduce((s, p) => s + p, 0);
  for (let i = 0; i < elements.length; i++) {
    reste -= poids[i];
    if (reste < 0) return elements[i];
  }
  return elements[elements.length - 1];
}

// Celles qui dépendent des lots suivants (promotion, joker, 0 mail) n'existent pas encore
export function phrasesDeSituation(f) {
  const P = T.phrases;
  const s = [];
  if (jourSemaine(f.jour) === 0) s.push(P.lundi);
  if (f.nbCours >= 4) s.push(P.chargee);
  if (f.premierCours !== null && f.premierCours >= 13 * 60) s.push(P.pasAvant13h);
  s.push(f.nbRetard === 0 ? P.rienEnRetard : P.enRetard(f.nbRetard));
  if (f.medianDans !== null && f.medianDans <= 7) s.push(P.median(f.medianDans));
  return s;
}

// Graine : la date. Une phrase de situation a 3 fois plus de chances qu'une phrase générale.
export function phraseDuJour(f) {
  const situations = phrasesDeSituation(f);
  const generales = T.phrases.generales;
  return choixPondere([...situations, ...generales], [...situations.map(() => 3), ...generales.map(() => 1)], aleaDepuis(f.jour));
}
