// Données inventées pour les tests d'écran (titres fictifs), à la façon de GET donnees, le mercredi
// 14 octobre 2026 à 15 h 45. La semaine type ressemble à l'emploi du temps A26.

const deux = (n) => String(n).padStart(2, '0');

// Identifiant de 24 caractères comme ceux de Trello : la sorte sur 2, le numéro sur 22
export const id = (sorte, n) => `${sorte}${String(n).padStart(22, '0')}`;

export const CARTES = {
  linkedin: id('ca', 1), formation: id('ca', 2), cabaret: id('ca', 3), median: id('ca', 4), apoint: id('ca', 5),
  tp: id('ca', 6), profil: id('ca', 7), jarvis: id('ca', 8), sites: id('ca', 9), logo: id('ca', 10),
};

function versUtc(jour) {
  const [a, m, j] = jour.split('-').map(Number);
  return Date.UTC(a, m - 1, j);
}

export function ajouter(jour, n) {
  const d = new Date(versUtc(jour) + n * 86400000);
  return `${d.getUTCFullYear()}-${deux(d.getUTCMonth() + 1)}-${deux(d.getUTCDate())}`;
}

const jourSemaine = (jour) => (new Date(versUtc(jour)).getUTCDay() + 6) % 7;

// Heure d'hiver à Paris à partir du dimanche 25 octobre 2026
export const iso = (jour, hhmm) => `${jour}T${hhmm}:00${jour >= '2026-10-25' ? '+01:00' : '+02:00'}`;

const SEMAINE_TYPE = [
  [['LA14', 'TD', '16:30', '18:30', 'FC114']],
  [['GE15', 'Cours', '13:00', '14:00', 'FA201'], ['GE15', 'TD', '16:30', '18:30', 'FA616']],
  [['NF93', 'TD', '08:00', '10:00', 'FA400'], ['TN01', 'TD', '16:30', '19:30', 'FA510']],
  [['MT23', 'TD', '16:30', '18:30', 'FA420']],
  [], [], [],
];
const PERSO = [['2026-10-14', 'Déjeuner avec Léon', '12:30', '13:30'], ['2026-10-21', 'Médian MT23', '19:15', '20:45']];
const BANDEAUX = {
  ferie: { type: 'ferie', jour: null, texte: "Jour férié à l'UTC : pas de cours." },
  examen: { type: 'examen', jour: null, texte: "Jour d'examen." },
  jeudi: { type: 'devient', jour: 'jeudi', texte: "Aujourd'hui, l'UTC suit l'emploi du temps du jeudi." },
};

export function jourAgenda(jour) {
  let modele = jourSemaine(jour);
  const bandeaux = [];
  if (jour === '2026-10-15') { bandeaux.push(BANDEAUX.ferie); modele = null; }
  if (jour === '2026-10-19') { bandeaux.push(BANDEAUX.jeudi); modele = 3; }
  if (jour >= '2026-10-20' && jour <= '2026-10-24') { bandeaux.push(BANDEAUX.examen); modele = null; }
  const evenements = (modele === null ? [] : SEMAINE_TYPE[modele]).map(([uv, type, debut, fin, salle]) => ({
    debut: iso(jour, debut), fin: iso(jour, fin), journee: false, source: 'utc', uv, type, salle, titre: `${uv} - ${type} 1`, median: false,
  }));
  for (const [date, titre, debut, fin] of PERSO) {
    if (date === jour) {
      evenements.push({ debut: iso(jour, debut), fin: iso(jour, fin), journee: false, source: 'perso', uv: null, type: null, salle: null, titre, median: titre.startsWith('Médian') });
    }
  }
  evenements.sort((a, b) => a.debut.localeCompare(b.debut));
  return { bandeaux, evenements };
}

export function joursDepuis(premier, nombre) {
  return Object.fromEntries(Array.from({ length: nombre }, (_, i) => {
    const jour = ajouter(premier, i);
    return [jour, jourAgenda(jour)];
  }));
}

// Les 7 jours d'une semaine ISO (2026-W42 : du lundi 12 au dimanche 18 octobre)
export function joursSemaine(semaine) {
  const annee = Number(semaine.slice(0, 4));
  const n = Number(semaine.slice(6));
  const quatre = `${annee}-01-04`;
  return joursDepuis(ajouter(ajouter(quatre, -jourSemaine(quatre)), (n - 1) * 7), 7);
}

const etapes = (n, noms, coches = []) => ({
  id: id('cc', n), nom: 'Étapes',
  elements: noms.map((nom, i) => ({ id: id('ee', n * 10 + i + 1), nom, coche: coches.includes(i) })),
});

const carte = (n, domaine, colonne, titre, autres = {}) => ({
  id: id('ca', n), domaine, colonne, position: n * 1000, titre, description: '', url: `https://trello.com/c/test${n}`,
  echeance: null, echeance_faite: false, urgent: false, etiquettes: [], checklists: [],
  derniere_activite: '2026-10-13T10:00:00+02:00', ...autres,
});

// Dans l'ordre du serveur : domaine, puis position
export function cartes() {
  return [
    carte(1, 'qyris', 'a_faire', 'Campagne LinkedIn : programmer les posts', {
      echeance: '2026-10-06T08:30:00+02:00', etiquettes: ['LinkedIn'],
      checklists: [etapes(1, ['Envoyer un mail de test', 'Compléter la page', 'Programmer les posts'])],
    }),
    carte(2, 'qyris', 'a_faire', 'Formation 2 : relire le deck, tester les exercices', {
      etiquettes: ['Formations'], description: 'Deck dans le dossier Formations.\n\nListe des tests : https://exemple.fr/tests',
      checklists: [etapes(2, ['Relire le deck en mode présentation', 'Tester les exercices sur les comptes gratuits'])],
    }),
    carte(10, 'qyris', 'fait', 'Logo : variantes PNG', { derniere_activite: '2026-10-12T18:00:00+02:00' }),
    carte(3, 'asso', 'a_faire', 'Cabaret : faire valider la lineup', {
      urgent: true, checklists: [etapes(3, ['Valider les horaires avec la team bouffe', 'Confirmer Oasis sur la grande scène'])],
    }),
    carte(4, 'etudes', 'a_faire', 'MT23 : préparer le médian', { echeance: '2026-10-21T19:15:00+02:00', etiquettes: ['MT23'] }),
    carte(5, 'etudes', 'en_cours', "A'point : tester sur téléphone et clôturer", {
      etiquettes: ['GE15'],
      checklists: [etapes(5, ['Créer le sous-domaine', 'Mettre en ligne', 'Vérifier le site en ligne, téléphone compris', 'Décider du commit'], [0, 1])],
    }),
    carte(6, 'etudes', 'a_faire', 'TN01 : rendre le TP', { echeance: '2026-10-14T18:00:00+02:00', etiquettes: ['TN01'] }),
    carte(7, 'perso', 'a_faire', 'LinkedIn : mettre à jour mon profil', { echeance: '2026-10-05T18:00:00+02:00' }),
    carte(8, 'perso', 'en_cours', "Jarvis Jr. : construire mon app d'organisation", {
      checklists: [etapes(8, ['Choisir le nom', 'Créer un compte cron-job.org', 'Rediriger la boîte UTC'], [0])],
    }),
    carte(9, 'perso', 'en_attente', 'Supprimer les anciens sites Netlify'),
  ];
}

export function donneesDeBase() {
  const maintenant = '2026-10-14T15:45:00+02:00';
  return {
    ok: true,
    maintenant,
    trello: { recupere_le: maintenant, erreur: null, cartes: cartes() },
    agenda: { recupere_le: maintenant, erreur: null, google_connecte: true, jours: joursDepuis('2026-10-14', 15) },
  };
}
