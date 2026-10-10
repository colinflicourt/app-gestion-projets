// Heure de Paris, quel que soit le fuseau de l'iPhone. Les dates du serveur portent leur décalage
// (2026-10-21T19:15:00+02:00) ; un jour est un texte AAAA-MM-JJ, compté sans fuseau.

const FUSEAU = 'Europe/Paris';
const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const format = new Intl.DateTimeFormat('en-GB', {
  timeZone: FUSEAU, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
});
const deux = (n) => String(n).padStart(2, '0');

export function enParis(date) {
  const p = {};
  for (const { type, value } of format.formatToParts(date)) p[type] = value;
  return { annee: Number(p.year), mois: Number(p.month), jour: Number(p.day), heure: Number(p.hour), minute: Number(p.minute) };
}

export function jourParis(date) {
  const p = enParis(date);
  return `${p.annee}-${deux(p.mois)}-${deux(p.jour)}`;
}

export function heureParis(date) {
  const p = enParis(date);
  return `${deux(p.heure)}:${deux(p.minute)}`;
}

function versUtc(jour) {
  const [a, m, j] = jour.split('-').map(Number);
  return Date.UTC(a, m - 1, j);
}

function depuisUtc(ms) {
  const d = new Date(ms);
  return `${d.getUTCFullYear()}-${deux(d.getUTCMonth() + 1)}-${deux(d.getUTCDate())}`;
}

export const ajouterJours = (jour, n) => depuisUtc(versUtc(jour) + n * 86400000);
export const joursEntre = (de, a) => Math.round((versUtc(a) - versUtc(de)) / 86400000);
export const jourSemaine = (jour) => (new Date(versUtc(jour)).getUTCDay() + 6) % 7;
export const lundi = (jour) => ajouterJours(jour, -jourSemaine(jour));

// La semaine ISO est celle de son jeudi
export function semaineIso(jour) {
  const jeudi = ajouterJours(jour, 3 - jourSemaine(jour));
  const annee = Number(jeudi.slice(0, 4));
  return `${annee}-W${deux(Math.floor(joursEntre(`${annee}-01-01`, jeudi) / 7) + 1)}`;
}

// Le 4 janvier est toujours dans la semaine 1
export function lundiDeSemaine(semaine) {
  return ajouterJours(lundi(`${semaine.slice(0, 4)}-01-04`), (Number(semaine.slice(6)) - 1) * 7);
}

const numeroDuJour = (jour) => (Number(jour.slice(8)) === 1 ? '1er' : String(Number(jour.slice(8))));
const mois = (jour) => MOIS[Number(jour.slice(5, 7)) - 1];

export function dateLongue(jour) {
  const nom = JOURS[jourSemaine(jour)];
  return `${nom[0].toUpperCase()}${nom.slice(1)} ${numeroDuJour(jour)} ${mois(jour)}`;
}

export const etiquetteJour = (jour) => `${JOURS[jourSemaine(jour)].slice(0, 3).toUpperCase()} ${Number(jour.slice(8))}`;
export const jourMois = (jour) => `${jour.slice(8)}/${jour.slice(5, 7)}`;
export const dateCourte = (jour) => `${JOURS[jourSemaine(jour)].slice(0, 3)}. ${jourMois(jour)}`;

export function plageSemaine(premier) {
  const dernier = ajouterJours(premier, 6);
  const debut = mois(premier) === mois(dernier) ? numeroDuJour(premier) : `${numeroDuJour(premier)} ${mois(premier)}`;
  return `${debut} au ${numeroDuJour(dernier)} ${mois(dernier)}`;
}

export function heureTexte(hhmm) {
  const [h, m] = hhmm.split(':').map(Number);
  return m === 0 ? `${h} h` : `${h} h ${deux(m)}`;
}

export function dureeTexte(minutes) {
  const m = Math.max(0, Math.round(minutes));
  if (m < 60) return `${m} min`;
  return m % 60 === 0 ? `${Math.floor(m / 60)} h` : `${Math.floor(m / 60)} h ${deux(m % 60)}`;
}

export function ageTexte(minutes) {
  if (minutes < 1) return "moins d'une minute";
  if (minutes < 60) return `${Math.floor(minutes)} min`;
  if (minutes < 48 * 60) return `${Math.floor(minutes / 60)} h`;
  return `${Math.floor(minutes / 1440)} jours`;
}

export const minutesEntre = (a, b) => (b.getTime() - a.getTime()) / 60000;

// Un jour et une heure de Paris en ISO avec le décalage de ce jour-là (carte ajoutée hors ligne)
export function isoParis(jour, hhmm) {
  const [a, mo, j] = jour.split('-').map(Number);
  const [h, mi] = hhmm.split(':').map(Number);
  const voulu = Date.UTC(a, mo - 1, j, h, mi);
  let essai = voulu;
  for (let i = 0; i < 2; i++) {
    const p = enParis(new Date(essai));
    essai += voulu - Date.UTC(p.annee, p.mois - 1, p.jour, p.heure, p.minute);
  }
  const decalage = Math.round((voulu - essai) / 60000);
  const absolu = Math.abs(decalage);
  return `${jour}T${deux(h)}:${deux(mi)}:00${decalage < 0 ? '-' : '+'}${deux(Math.floor(absolu / 60))}:${deux(absolu % 60)}`;
}
