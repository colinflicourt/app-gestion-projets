// L'écran de conduite d'Aujourd'hui (SPEC § 8.1) : ce qui est en scène, ce qui suit. Fonctions pures.
import { ajouterJours, jourParis, joursEntre, minutesEntre } from './temps.js';

const VIDE = { bandeaux: [], evenements: [] };

export const estCours = (e) => e.source === 'utc' && !e.journee;
export const jourDe = (jours, jour) => jours?.[jour] ?? VIDE;
export const nomCreneau = (e) => (e.uv ? `${e.uv} · ${e.type}` : e.titre);

const dans = (maintenant, iso) => Math.round(minutesEntre(maintenant, new Date(iso)));

// Les cours connus d'aujourd'hui à la fin de la copie, dans l'ordre, chacun avec son jour
function coursAVenir(jours, aujourdhui) {
  return Object.keys(jours ?? {}).filter((j) => j >= aujourdhui).sort()
    .flatMap((j) => jours[j].evenements.filter(estCours).map((e) => ({ ...e, jour: j })));
}

export function etatConduite(jours, maintenant) {
  const aujourdhui = jourParis(maintenant);
  const cours = coursAVenir(jours, aujourdhui);
  const duJour = cours.filter((e) => e.jour === aujourdhui);
  const enCours = duJour.find((e) => new Date(e.debut) <= maintenant && maintenant < new Date(e.fin)) ?? null;
  const suivant = cours.find((e) => new Date(e.debut) > maintenant) ?? null;
  let scene;
  if (enCours) {
    scene = { type: 'cours', cours: enCours, finDans: dans(maintenant, enCours.fin) };
  } else if (suivant?.jour === aujourdhui) {
    scene = duJour.some((e) => new Date(e.fin) <= maintenant) ? { type: 'entracte', libres: dans(maintenant, suivant.debut) } : { type: 'rien', nbCours: duJour.length };
  } else {
    scene = { type: 'fini' };
  }
  let quand = null;
  if (suivant) quand = suivant.jour === aujourdhui ? 'aujourdhui' : suivant.jour === ajouterJours(aujourdhui, 1) ? 'demain' : 'plus_tard';
  return {
    aujourdhui,
    scene,
    standby: suivant && { cours: suivant, dans: dans(maintenant, suivant.debut), quand },
    enCours: enCours !== null,
    bandeaux: jourDe(jours, aujourdhui).bandeaux,
    nbCours: duJour.length,
    premierCours: duJour[0] ?? null,
  };
}

// Le prochain médian de l'agenda principal (titre « Médian … »), s'il tombe dans les $horizon jours
export function medianProche(jours, maintenant, horizon = 7) {
  const aujourdhui = jourParis(maintenant);
  for (const jour of Object.keys(jours ?? {}).filter((j) => j >= aujourdhui).sort()) {
    const median = jours[jour].evenements.find((e) => e.median && (e.journee || new Date(e.fin) > maintenant));
    if (median) {
      const n = joursEntre(aujourdhui, jour);
      return n <= horizon ? { evenement: median, dans: n } : null;
    }
  }
  return null;
}
