// Le barème d'XP côté iPhone (SPEC § 18.4 et § 18.6) : fonctions pures. L'iPhone n'estime que le gain d'un
// geste, pour l'afficher tout de suite, hors ligne comprise ; les totaux viennent du serveur (xp dans GET donnees).
import { jourParis } from './temps.js';

// Copie exacte de JR_XP_REGLES (serveur/lib/xp.php), vérifiée par un test
export const REGLES = {
  carte: 30,
  bonus_urgent: 20,
  bonus_echeance: 10,
  carte_eclair: 5,
  eclair_secondes: 600,
  plafond_eclairs: 2,
  etape: 5,
  plafond_etapes: 10,
  pas_niveau: 160,
  grades: { stagiaire: 0, assistant: 1500, bras_droit: 6000, majordome: 22000 },
};

const PAYE_VIDE = { etapes: 0, eclairs: 0 };

// Les 8 premiers caractères hexadécimaux d'un identifiant Trello sont l'horodatage Unix de sa création
export function creationCarte(id) {
  return /^[0-9a-f]{24}$/i.test(id) ? new Date(parseInt(id.slice(0, 8), 16) * 1000) : null;
}

// Carte éclair : terminée moins de eclair_secondes après sa création, durée négative comprise (carte créée puis
// terminée hors ligne). Identifiant non Trello (carte locale pas encore créée) : estimée éclair, le serveur recale.
export function estEclair(id, quand, regles = REGLES) {
  const creation = creationCarte(id);
  return creation === null || (quand.getTime() - creation.getTime()) / 1000 < regles.eclair_secondes;
}

// Échéance à 18 h 00 (posée sans heure) : comparée au jour de Paris ; sinon à la minute
function echeanceTenue(echeance, quand) {
  if (typeof echeance !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/.test(echeance)) return false;
  if (echeance.slice(11, 19) === '18:00:00') return jourParis(quand) <= echeance.slice(0, 10);
  const limite = Date.parse(echeance);
  return !Number.isNaN(limite) && Math.floor(quand.getTime() / 60000) <= Math.floor(limite / 60000);
}

// paye : ce qui est déjà payé aujourd'hui (xp.aujourdhui_paye plus les gestes de la file, voir payeApres)
export function gainCarte({ id, urgent, echeance }, quand, paye = PAYE_VIDE, regles = REGLES) {
  if (estEclair(id, quand, regles)) return paye.eclairs >= regles.plafond_eclairs ? 0 : regles.carte_eclair;
  return regles.carte + (urgent === true ? regles.bonus_urgent : 0) + (echeanceTenue(echeance, quand) ? regles.bonus_echeance : 0);
}

export function gainEtape(paye = PAYE_VIDE, regles = REGLES) {
  return paye.etapes >= regles.plafond_etapes ? 0 : regles.etape;
}

// Ce que les écritures en attente (local.xp = { genre: 'etapes' | 'eclairs', n: +1 ou -1 }) ont payé ou retiré
// aujourd'hui, ajouté à ce que le serveur a déjà compté. Une écriture sans heure de geste compte pour aujourd'hui.
export function payeApres(paye, ecritures, jour) {
  const total = { ...PAYE_VIDE, ...paye };
  for (const e of ecritures) {
    const x = e.local?.xp;
    if (!x?.genre || (e.fait_le && jourParis(new Date(e.fait_le)) !== jour)) continue;
    total[x.genre] = Math.max(0, total[x.genre] + x.n);
  }
  return total;
}

export const seuilNiveau = (n, regles = REGLES) => (n <= 1 ? 0 : (regles.pas_niveau * n * (n - 1)) / 2);

export function progression(xp, regles = REGLES) {
  xp = Math.max(0, xp);
  let niveau = Math.max(1, Math.floor((1 + Math.sqrt(1 + (8 * xp) / regles.pas_niveau)) / 2));
  while (niveau > 1 && seuilNiveau(niveau, regles) > xp) niveau--;
  while (seuilNiveau(niveau + 1, regles) <= xp) niveau++;
  const seuil = seuilNiveau(niveau, regles);
  return { niveau, dansNiveau: xp - seuil, tailleNiveau: seuilNiveau(niveau + 1, regles) - seuil };
}

// Célébrations (SPEC § 18.6, décisions 9 et 10). vu = { emis_le_max, cles, total_vu } gardé dans IndexedDB ; un
// événement est à célébrer s'il a été émis après emis_le_max ET si sa clé n'est pas déjà vue (un barème modifié
// peut redater un niveau déjà célébré). vu absent : tout l'historique est en attente.
const RANG = { grade: 0, niveau: 1, joker: 2 };
const instant = (iso) => Date.parse(iso);

export function aCelebrer(evenements, vu) {
  const max = vu?.emis_le_max ? instant(vu.emis_le_max) : -Infinity;
  const vues = new Set(vu?.cles ?? []);
  const attente = (evenements ?? [])
    .filter((e) => instant(e.emis_le) > max && !vues.has(e.cle))
    .sort((a, b) => RANG[a.type] - RANG[b.type] || instant(a.emis_le) - instant(b.emis_le));
  const mode = attente.length === 0 ? 'aucune' : attente.length <= 2 ? 'simple' : 'recap';
  return { mode, evenements: attente };
}

const JOURS_JOKER = 60;

// Le nouvel état « vu » : à écrire AVANT d'afficher la célébration. Garde les niveaux et grades, et les jokers des
// 60 derniers jours.
export function marquerVus(vu, evenements, total, maintenant = new Date()) {
  const limite = jourParis(new Date(maintenant.getTime() - JOURS_JOKER * 86400000));
  const cles = new Set([...(vu?.cles ?? []), ...evenements.map((e) => e.cle)]);
  for (const cle of cles) if (cle.startsWith('joker:') && cle.slice(6) < limite) cles.delete(cle);
  let max = vu?.emis_le_max ?? null;
  for (const e of evenements) if (max === null || instant(e.emis_le) > instant(max)) max = e.emis_le;
  return { emis_le_max: max, cles: [...cles], total_vu: total };
}

// Ce que dit le récapitulatif : XP gagnée depuis la dernière fois, grade le plus haut gagné, niveau le plus haut par domaine
// (le plus avancé en premier)
export function resumeRecap(evenements, total, vu) {
  const hauts = new Map();
  let grade = null;
  for (const e of evenements) {
    if (e.type === 'niveau') hauts.set(e.domaine, Math.max(hauts.get(e.domaine) ?? 0, e.niveau));
    else if (e.type === 'grade') grade = e.grade;
  }
  const niveaux = [...hauts].map(([domaine, niveau]) => ({ domaine, niveau })).sort((a, b) => b.niveau - a.niveau);
  return { gain: Math.max(0, total - (vu?.total_vu ?? 0)), grade, niveaux };
}
