// Les heures proposées pour un rappel (CONTENU § 4), à l'heure de Paris. Fonctions pures : « maintenant » vient de l'appelant.
import { T } from './textes.js';
import { ajouterJours, dateLongue, enParis, heureParis, heureTexte, isoParis, jourParis } from './temps.js';

const R = T.capture.rappel;
const deux = (n) => String(n).padStart(2, '0');

// Un instant en ISO avec le décalage de Paris à cet instant (l'heure 2 h 30 du 25/10/2026 existe deux fois)
function isoInstant(date) {
  const minute = Math.floor(date.getTime() / 60000) * 60000;
  const p = enParis(new Date(minute));
  const decalage = Math.round((Date.UTC(p.annee, p.mois - 1, p.jour, p.heure, p.minute) - minute) / 60000);
  const absolu = Math.abs(decalage);
  return `${jourParis(new Date(minute))}T${deux(p.heure)}:${deux(p.minute)}:00${decalage < 0 ? '-' : '+'}${deux(Math.floor(absolu / 60))}:${deux(absolu % 60)}`;
}

export function choixRappel(maintenant) {
  const jour = jourParis(maintenant);
  const tard = heureParis(maintenant) >= '19:30';
  return [
    { id: '1h', libelle: R.dans1h, quand: isoInstant(new Date(maintenant.getTime() + 3600000)) },
    { id: 'soir', libelle: tard ? R.demain20 : R.ce20, quand: isoParis(tard ? ajouterJours(jour, 1) : jour, '20:00') },
    { id: 'demain8', libelle: R.demain8, quand: isoParis(ajouterJours(jour, 1), '08:00') },
    { id: 'choisir', libelle: R.choisir, quand: null },
  ];
}

// valeur : AAAA-MM-JJTHH:MM, comme le champ datetime-local, lue à l'heure de Paris
export function choixLibre(valeur, maintenant) {
  const [jour, heure] = (valeur ?? '').split('T');
  if (!jour || !heure) return { erreur: R.passe };
  const quand = isoParis(jour, heure.slice(0, 5));
  if (new Date(quand).getTime() <= maintenant.getTime()) return { erreur: R.passe };
  return { id: 'choisir', libelle: R.choisir, quand };
}

export function texteConfirmation(choix, maintenant) {
  const date = new Date(choix.quand);
  const jour = jourParis(date);
  const heure = heureTexte(heureParis(date));
  if (choix.id === '1h') return R.prevu(R.dans1hMilieu);
  if (choix.id === 'soir' && jour === jourParis(maintenant)) return R.prevu(`ce soir à ${heure}`);
  if (choix.id !== 'choisir' && jour === ajouterJours(jourParis(maintenant), 1)) return R.prevu(`demain à ${heure}`);
  const long = dateLongue(jour);
  return R.prevu(`${long[0].toLowerCase()}${long.slice(1)} à ${heure}`);
}
