// Les cartes Trello telles que le serveur les envoie (SPEC § 5.5) : retards, groupes, comptes, libellés.

import { dateCourte, heureParis, heureTexte, jourMois, jourParis } from './temps.js';

export const DOMAINES = ['qyris', 'asso', 'etudes', 'perso'];
export const NOMS = { qyris: 'QYRIS', asso: 'Asso', etudes: 'Études', perso: 'Perso' };
export const SCOTCH = { qyris: 'QYRIS', asso: 'ASSO', etudes: 'ÉTUDES', perso: 'PERSO' };
export const COLONNES = { a_faire: 'À faire', en_cours: 'En cours', en_attente: 'En attente', fait: 'Fait' };

const active = (carte) => carte.colonne === 'a_faire' || carte.colonne === 'en_cours';
const aEcheance = (carte) => carte.colonne !== 'fait' && carte.echeance !== null && !carte.echeance_faite;

export const enRetard = (carte, maintenant) => aEcheance(carte) && new Date(carte.echeance) < maintenant;

export function echeanceAujourdhui(carte, maintenant) {
  return aEcheance(carte) && !enRetard(carte, maintenant) && jourParis(new Date(carte.echeance)) === jourParis(maintenant);
}

export function avancement(carte) {
  let faits = 0;
  let total = 0;
  for (const liste of carte.checklists) {
    for (const element of liste.elements) {
      total++;
      if (element.coche) faits++;
    }
  }
  return total === 0 ? null : `${faits}/${total}`;
}

// Une échéance à 18 h 00 pile est une date seule (convention de trello.py et du serveur)
export function libelleEcheance(carte, maintenant) {
  if (carte.echeance === null) return null;
  const quand = new Date(carte.echeance);
  const jour = jourParis(quand);
  if (enRetard(carte, maintenant)) return { texte: `En retard depuis le ${jourMois(jour)}`, retard: true };
  const heure = heureParis(quand);
  return { texte: `Échéance ${dateCourte(jour)}${heure === '18:00' ? '' : ` à ${heureTexte(heure)}`}`, retard: false };
}

// Cartes À faire et En cours par domaine, et combien sont en retard (En attente ne dépend pas de Colin)
export function compteurs(cartes, maintenant) {
  const comptes = Object.fromEntries(DOMAINES.map((d) => [d, { ouvertes: 0, retard: 0 }]));
  for (const carte of cartes) {
    if (!active(carte) || !comptes[carte.domaine]) continue;
    comptes[carte.domaine].ouvertes++;
    if (enRetard(carte, maintenant)) comptes[carte.domaine].retard++;
  }
  return comptes;
}

// « À faire » d'Aujourd'hui : En retard, Aujourd'hui (échéance plus tard dans la journée), puis Urgent
export function groupesAFaire(cartes, maintenant) {
  const groupes = { retard: [], aujourdhui: [], urgent: [] };
  for (const carte of cartes) {
    if (!active(carte)) continue;
    if (enRetard(carte, maintenant)) groupes.retard.push(carte);
    else if (echeanceAujourdhui(carte, maintenant)) groupes.aujourdhui.push(carte);
    else if (carte.urgent) groupes.urgent.push(carte);
  }
  const parEcheance = (a, b) => new Date(a.echeance) - new Date(b.echeance);
  groupes.retard.sort(parEcheance);
  groupes.aujourdhui.sort(parEcheance);
  return groupes;
}

// Onglet Tâches : un domaine (ou tous si null), dans l'ordre de Trello
export function groupesTaches(cartes, domaine) {
  const groupes = { en_cours: [], a_faire: [], en_attente: [], fait: [] };
  for (const carte of cartes) {
    if ((domaine === null || carte.domaine === domaine) && groupes[carte.colonne]) groupes[carte.colonne].push(carte);
  }
  return groupes;
}

export const etiquetteCarte = (carte) => [NOMS[carte.domaine], ...carte.etiquettes.slice(0, 1)].join(' · ');

export function debutTitre(titre) {
  const i = titre.indexOf(':');
  return (i > 0 ? titre.slice(0, i) : titre).trim();
}

export const trouverCarte = (cartes, id) => cartes.find((c) => c.id === id) ?? null;
