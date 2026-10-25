// Les mails (SPEC § 16.8) : heure d'un mail, résumé d'Aujourd'hui, état d'une section, correction d'expéditeur
// appliquée tout de suite à l'écran. Fonctions pures.
import { T } from './textes.js';
import { dateCourte, heureParis, heureTexte, joursEntre, jourParis } from './temps.js';

const SECTIONS = ['utc', 'qyris', 'gmail', 'icloud'];
// Les comptes dont dépend l'état de chaque section : UTC a son compte, QYRIS suit colin
const COMPTES_DE = { utc: ['utc'], gmail: ['gmail'], icloud: ['icloud'], qyris: ['colin'] };

// « 14 h 32 » aujourd'hui, « hier », « lun. 19/10 » cette année, « 19/10/2025 » avant. iso : UTC avec Z.
export function heureMail(iso, maintenant) {
  const date = new Date(iso);
  const jour = jourParis(date);
  const aujourdhui = jourParis(maintenant);
  if (jour === aujourdhui) return heureTexte(heureParis(date));
  if (joursEntre(jour, aujourdhui) === 1) return T.mails.hier;
  if (jour.slice(0, 4) === aujourdhui.slice(0, 4)) return dateCourte(jour);
  return `${jour.slice(8)}/${jour.slice(5, 7)}/${jour.slice(0, 4)}`;
}

// « 3 non lus · UTC 2 · QYRIS 1 », « Boîtes à jour. » ; resume : { total, par_section }, ou null
export function resumeMails(resume) {
  if (!resume) return null;
  if (resume.total === 0) return T.mails.aJour;
  const parts = SECTIONS.filter((s) => resume.par_section[s] > 0).map((s) => ` · ${T.mails.sections[s]} ${resume.par_section[s]}`);
  return `${T.mails.nonLus(resume.total)}${parts.join('')}`;
}

// Les comptes d'une section qui ne répondent pas : [{ compte, code }] (code : refus, injoignable, delai, non_connecte)
export function etatSection(section, comptes) {
  if (!comptes) return [];
  return COMPTES_DE[section].filter((c) => comptes[c]?.erreur).map((c) => ({ compte: c, code: comptes[c].erreur }));
}

const plusRecent = (a, b) => (b.date ?? '').localeCompare(a.date ?? '');

// corrections : adresse → 'afficher' | 'pub'. Une copie des sections où les mails corrigés ont changé de liste ;
// la section UTC n'est jamais filtrée. Appliquer deux fois donne le même résultat.
export function deplacerCorrections(sections, corrections) {
  return sections.map((s) => {
    if (s.section === 'utc' || corrections.size === 0) return s;
    const versPubs = s.mails.filter((m) => corrections.get(m.de_adresse) === 'pub');
    const versListe = s.pubs.filter((m) => corrections.get(m.de_adresse) === 'afficher');
    if (versPubs.length + versListe.length === 0) return s;
    const nonLus = (liste) => liste.filter((m) => m.non_lu).length;
    return {
      ...s,
      non_lus: s.non_lus - nonLus(versPubs) + nonLus(versListe),
      pubs_total: s.pubs_total + versPubs.length - versListe.length,
      mails: [...s.mails.filter((m) => !versPubs.includes(m)), ...versListe].sort(plusRecent),
      pubs: [...s.pubs.filter((m) => !versListe.includes(m)), ...versPubs].sort(plusRecent),
    };
  });
}

// « lun. 19/10 à 14 h 32 » cette année, « lun. 19/10/2025 à 14 h 32 » une autre année. iso : UTC avec Z.
export function dateComplete(iso, maintenant) {
  const date = new Date(iso);
  const jour = jourParis(date);
  const courte = dateCourte(jour);
  const annee = jour.slice(0, 4) === jourParis(maintenant).slice(0, 4) ? '' : `/${jour.slice(0, 4)}`;
  return `${courte}${annee} à ${heureTexte(heureParis(date))}`;
}

// « 800 o », « 350 Ko », « 1,2 Mo », « 12 Mo » (base 1024)
export function taillePiece(octets) {
  if (octets < 1024) return `${octets} o`;
  const ko = Math.round(octets / 1024);
  if (ko < 1024) return `${ko} Ko`;
  const mo = Math.round((octets / 1048576) * 10) / 10;
  return `${Number.isInteger(mo) ? mo : String(mo).replace('.', ',')} Mo`;
}

// Le mail ouvert passe lu dans une copie des sections. Rend { sections, change, section, liste ('mails' ou 'pubs') } ; change est faux si le
// mail est absent ou déjà lu. Le compteur d'une section ne compte pas ses pubs.
export function marquerLu(sections, compte, uid) {
  for (const [i, s] of sections.entries()) {
    for (const cle of ['mails', 'pubs']) {
      const j = s[cle].findIndex((m) => m.compte === compte && m.uid === uid);
      if (j < 0) continue;
      if (!s[cle][j].non_lu) return { sections, change: false, section: s.section };
      const copie = { ...s, [cle]: s[cle].map((m, k) => (k === j ? { ...m, non_lu: false } : m)) };
      if (cle === 'mails') copie.non_lus = s.non_lus - 1;
      return { sections: sections.map((x, k) => (k === i ? copie : x)), change: true, section: s.section, liste: cle };
    }
  }
  return { sections, change: false, section: null };
}
