// « Je fais quoi maintenant ? » (SPEC § 8.3). Fonctions pures : l'aléa est passé en paramètre.
import { T } from './textes.js';
import { debutTitre, enRetard, etiquetteCarte } from './cartes.js';
import { estCours } from './conduite.js';
import { choixPondere } from './phrases.js';
import { dureeTexte, enParis, jourParis, joursEntre, minutesEntre } from './temps.js';

export const TRAJET = 10;
export const IMMINENT = 15;
export const LIMITE = 23;
export const RELANCES = 3;
export const PAS = 14;

// Du moment présent au prochain cours du jour moins le trajet, ou jusqu'à 23 h. Il ne filtre rien :
// aucune carte n'a d'estimation de durée, il est seulement affiché.
export function tempsLibre(jours, maintenant) {
  const cours = (jours?.[jourParis(maintenant)]?.evenements ?? []).filter(estCours);
  const rien = { enCours: false, coursDans: null, minutes: null, avant: null, tard: false };
  if (cours.some((e) => new Date(e.debut) <= maintenant && maintenant < new Date(e.fin))) return { ...rien, enCours: true };
  const prochain = cours.find((e) => new Date(e.debut) > maintenant);
  if (prochain) {
    const dans = Math.round(minutesEntre(maintenant, new Date(prochain.debut)));
    return { ...rien, coursDans: dans, minutes: dans - TRAJET, avant: prochain };
  }
  const { heure, minute } = enParis(maintenant);
  if (heure >= LIMITE || heure < 5) return { ...rien, tard: true };
  return { ...rien, minutes: LIMITE * 60 - (heure * 60 + minute) };
}

// Points d'une carte (réglables, à recaler à l'usage)
export function score(carte, maintenant) {
  let points = 0;
  if (carte.echeance !== null && !carte.echeance_faite) {
    if (enRetard(carte, maintenant)) {
      points += 100 + 5 * Math.min(Math.floor(minutesEntre(new Date(carte.echeance), maintenant) / 1440), 30);
    } else {
      const ecart = joursEntre(jourParis(maintenant), jourParis(new Date(carte.echeance)));
      if (ecart === 0) points += 80;
      else if (ecart === 1) points += 50;
      else if (ecart <= 3) points += 30;
      else if (ecart <= 7) points += 15;
    }
  }
  if (carte.urgent) points += 40;
  if (carte.colonne === 'en_cours') points += 25;
  return points;
}

// Pour chaque carte En cours ou À faire : sa première étape non cochée, ou la carte entière
export function candidats(cartes, maintenant) {
  const liste = [];
  for (const carte of cartes) {
    if (carte.colonne !== 'en_cours' && carte.colonne !== 'a_faire') continue;
    let element = null;
    for (const l of carte.checklists) {
      element = l.elements.find((e) => !e.coche) ?? null;
      if (element) break;
    }
    liste.push({ cle: `${carte.id}:${element?.id ?? ''}`, carte, element, score: score(carte, maintenant) });
  }
  return liste.sort((a, b) => b.score - a.score);
}

// Au hasard, pondéré par (score + 10), parmi les 5 meilleurs pas encore proposés dans ce tirage
export function tirer(liste, dejaProposes, alea) {
  const restants = liste.filter((c) => !dejaProposes.has(c.cle));
  const pool = (restants.length > 0 ? restants : liste).slice(0, 5);
  return pool.length === 0 ? null : choixPondere(pool, pool.map((c) => c.score + 10), alea);
}

// « Formation 2 : tester les exercices sur les comptes gratuits. » (un sigle en tête garde ses capitales)
export function texteProposition(c) {
  if (c.element === null) return T.tirage.terminer(c.carte.titre);
  const nom = c.element.nom.trim();
  const etape = /^\p{Lu}{2}/u.test(nom) ? nom : nom.charAt(0).toLocaleLowerCase('fr') + nom.slice(1);
  return `${debutTitre(c.carte.titre)} : ${etape}${/[.!?…]$/.test(etape) ? '' : '.'}`;
}

export function ligneAfficheur(libre) {
  if (libre.tard) return T.tirage.ligneTard;
  if (libre.avant) return T.tirage.ligneAvant(dureeTexte(libre.minutes), libre.avant.uv ?? libre.avant.titre);
  return T.tirage.ligneLibre;
}

// La réplique de Junior : celle du premier résultat, puis celles des relances (la 4e : Junior choisit)
export function replique(libre, relances) {
  if (relances > 0) return T.tirage.relances[Math.min(relances, T.tirage.relances.length) - 1];
  if (libre.tard) return T.tirage.tard;
  if (libre.avant) return T.tirage.trouve(dureeTexte(libre.minutes), libre.avant.type);
  return T.tirage.libre;
}

export function etiquetteProposition(c, maintenant) {
  const etat = enRetard(c.carte, maintenant) ? T.tirage.enRetard : c.carte.colonne === 'en_cours' ? T.tirage.enCours : null;
  return [etiquetteCarte(c.carte), etat].filter(Boolean).join(' · ');
}

// L'afficheur qui roule colonne par colonne : les caractères déjà fixés restent
export function rouler(texte, avancement, alea = Math.random) {
  const pool = '0123456789ABCDEFGHJKLMNPRSTUVXYZ';
  const fixes = Math.floor(texte.length * avancement);
  return [...texte].map((c, i) => (c === ' ' || i < fixes ? c : pool[Math.floor(alea() * pool.length)])).join('');
}
