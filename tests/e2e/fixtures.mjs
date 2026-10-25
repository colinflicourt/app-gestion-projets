// Données inventées pour les tests d'écran (titres fictifs), à la façon de GET donnees, le mercredi
// 14 octobre 2026 à 15 h 45. La semaine type ressemble à l'emploi du temps A26.

const deux = (n) => String(n).padStart(2, '0');

// Identifiant de 24 caractères comme ceux de Trello : la sorte sur 2, le numéro sur 22
export const id = (sorte, n) => `${sorte}${String(n).padStart(22, '0')}`;

// Les 8 premiers caractères d'un identifiant Trello sont l'horodatage de création (ici le 29 août 2026) :
// sans cela, toutes les cartes de test seraient des cartes éclair
const idCarte = (n) => `6a92b700${String(n).padStart(16, '0')}`;

export const CARTES = {
  linkedin: idCarte(1), formation: idCarte(2), cabaret: idCarte(3), median: idCarte(4), apoint: idCarte(5),
  tp: idCarte(6), profil: idCarte(7), jarvis: idCarte(8), sites: idCarte(9), logo: idCarte(10),
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
  id: idCarte(n), domaine, colonne, position: n * 1000, titre, description: '', url: `https://trello.com/c/test${n}`,
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
      checklists: [etapes(8, ['Choisir le nom', 'Créer un compte cron-job.org', 'Brancher la boîte UTC'], [0])],
    }),
    carte(9, 'perso', 'en_attente', 'Supprimer les anciens sites Netlify'),
  ];
}

const mail = (compte, uid, de_nom, de_adresse, objet, extrait, date, non_lu, autres = {}) => ({
  uid, message_id: `<${compte}-${uid}@exemple.fr>`, date, de_nom, de_adresse, objet, extrait, non_lu, signes_pub: [], lien: null, compte, ...autres,
});

// Ce que rend GET mails le 14 octobre 2026 à 15 h 45 : 4 non lus (UTC 2, QYRIS 1, Gmail 1), 48 pubs dans Gmail,
// boîtes relevées il y a 12 min. Les mails sont inventés.
export function mailsDeBase() {
  const releve = '2026-10-14T15:33:00+02:00';
  return {
    sections: [
      {
        section: 'utc', non_lus: 2, pubs_total: 0, pubs: [],
        mails: [
          mail('utc', 101, 'Secrétariat GI', 'secretariat-gi@utc.fr', 'Inscription aux UV de A27', 'Les inscriptions ouvrent lundi, pense à vérifier ton dossier.', '2026-10-14T12:32:00Z', true),
          mail('utc', 102, 'Prof. Durand', 'durand@utc.fr', 'TD de jeudi déplacé', 'Le TD de jeudi se tiendra en salle FA420.', '2026-10-13T09:10:00Z', true),
          mail('utc', 103, 'BDE', 'bde@utc.fr', 'Compte rendu de la réunion', 'Voici le compte rendu de la réunion de vendredi.', '2026-10-09T15:00:00Z', false),
        ],
      },
      {
        section: 'qyris', non_lus: 1, pubs_total: 0, pubs: [],
        mails: [
          mail('colin', 201, 'Cathy Martin', 'cathy@exemple-idfense.fr', 'Devis du site', 'Bonjour Colin, voici le devis signé.', '2026-10-14T08:05:00Z', true),
          mail('colin', 202, 'Léa Bernard', 'lea@exemple.fr', 'Question sur la formation', "Bonjour, la formation est-elle ouverte aux débutants ?", '2026-10-12T10:00:00Z', false, { pour_contact: true }),
        ],
      },
      {
        section: 'gmail', non_lus: 1, pubs_total: 48,
        mails: [mail('gmail', 301, 'Hébergeur', 'support@exemple-hebergeur.fr', 'Nouvelle connexion détectée', 'Une connexion depuis un nouvel appareil a eu lieu.', '2026-10-14T11:20:00Z', true)],
        pubs: [
          mail('gmail', 302, 'Boutique Exemple', 'promo@exemple-boutique.fr', '-30 % sur tout le site', 'Profitez de la vente flash jusqu à dimanche.', '2026-10-14T06:00:00Z', false, { signes_pub: ['list-unsubscribe'] }),
          mail('gmail', 303, 'La Lettre du mercredi', 'lettre@exemple-news.fr', 'Les nouvelles de la semaine', 'Au sommaire cette semaine : trois idées à tester.', '2026-10-13T05:00:00Z', true, { signes_pub: ['list-id'] }),
        ],
      },
      {
        section: 'icloud', non_lus: 0, pubs_total: 0, pubs: [],
        mails: [mail('icloud', 401, 'Maman', 'maman@exemple.fr', 'Dimanche midi', 'On mange à 12 h 30, tu viens ?', '2025-12-24T10:00:00Z', false)],
      },
    ],
    comptes: Object.fromEntries(['utc', 'gmail', 'icloud', 'colin'].map((c) => [c, { recupere_le: releve, erreur: null }])),
  };
}

export const MAILS_RESUME = { total: 4, par_section: { utc: 2, qyris: 1, gmail: 1, icloud: 0 } };

// Ce que rend xp_objet() : 2 340 XP, grade Assistant (prochain palier 6 000), Études niveau 3 (120 sur 480), série de 5 jours,
// joker libre. Les niveaux sont calculés avec le barème réel (pas de 160).
export function xpDeBase(xp = { qyris: 700, asso: 300, etudes: 600, perso: 740 }, serie = {}, paye = { etapes: 0, eclairs: 0 }) {
  const total = Object.values(xp).reduce((a, b) => a + b, 0);
  const seuil = (n) => (160 * n * (n - 1)) / 2;
  const domaine = (x) => {
    let n = 1;
    while (seuil(n + 1) <= x) n++;
    return { xp: x, niveau: n, dans_niveau: x - seuil(n), taille_niveau: seuil(n + 1) - seuil(n) };
  };
  const assistant = total >= 1500;
  return {
    total, grade: assistant ? 'assistant' : 'stagiaire', seuil_grade: assistant ? 1500 : 0,
    grade_suivant: assistant ? 'bras_droit' : 'assistant', seuil_suivant: assistant ? 6000 : 1500,
    domaines: Object.fromEntries(Object.entries(xp).map(([d, x]) => [d, domaine(x)])),
    serie: { jours: 5, joker_libre: true, dernier_joker: null, en_jeu: false, ...serie },
    aujourdhui_paye: paye, evenements: [],
  };
}

export function donneesDeBase() {
  const maintenant = '2026-10-14T15:45:00+02:00';
  return {
    ok: true,
    maintenant,
    trello: { recupere_le: maintenant, erreur: null, cartes: cartes() },
    agenda: { recupere_le: maintenant, erreur: null, google_connecte: true, jours: joursDepuis('2026-10-14', 15) },
    rappels_du_jour: [
      { id: 'rm000001', type: 'manuel', heure: '2026-10-14T12:00:00+02:00', titre: 'Appeler le dentiste', passe: true, annulable: false },
      { id: 'rm000002', type: 'manuel', heure: '2026-10-14T17:30:00+02:00', titre: 'Envoyer le devis à Cathy', passe: false, annulable: true },
    ],
    mails_resume: structuredClone(MAILS_RESUME),
    xp: xpDeBase(),
    notifications: { cle: 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8' },
  };
}

// Ce que rend GET reglages : réglages par défaut, planificateur passé il y a 1 min (14 oct. 2026, 15 h 44), pas de jeton Siri
export function reglagesDeBase() {
  return {
    rappels: {
      brief: { heure: '07:30', jours: [1, 2, 3, 4, 5, 6, 7] },
      cours: { actif: true, minutes: 15 },
      echeances: { actif: true, heure: '20:00' },
      medians: { actif: true, jours: [7, 3, 1], heure: '09:00' },
    },
    planificateur: { dernier_passage: '2026-10-14T15:44:00+02:00' },
    siri: { jeton_cree: false },
  };
}

// Ce que rend GET comptes-mail : jamais de mot de passe. UTC et iCloud pas encore saisis, Gmail vérifié à 14 h 32,
// colin@ refusé.
export function comptesMailDeBase() {
  const compte = (identifiant, enregistre, dernier_succes, derniere_erreur) => ({ identifiant, enregistre, dernier_succes, derniere_erreur });
  return {
    utc: compte('cflicour', false, null, null),
    gmail: compte('colin.flicourt@gmail.com', true, '2026-10-14T12:32:00Z', null),
    icloud: compte('colin.flicourt@icloud.com', false, null, null),
    colin: compte('colin@qyris.fr', true, '2026-10-13T08:00:00Z', 'refus'),
  };
}

export const JETON_SIRI = 'Zm9vYmFyLWpldG9uLWRlLXRlc3QtMDEyMzQ1Njc4OWFi';

// Le contenu des mails, tel que le serveur le rend (POST mails/ouvrir, GET mails/corps, GET mails/piece). Les clés
// sont « compte/uid ». Un mail absent de cette table n'est pas dans la copie du serveur : 404 hors_copie.
// - erreur : { statut, code } rendue par mails/ouvrir ; - corps : la page du corps, corpsImages avec images=1 ;
// - sansCsp : la page est servie sans les en-têtes du corps (le cadre `sandbox` reste seul contre un mail piégé).
const SVG = (couleur, texte) => `data:image/svg+xml;charset=utf-8,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="240" height="90"><rect width="240" height="90" fill="${couleur}"/><text x="14" y="52" font-size="22" fill="#fff" font-family="sans-serif">${texte}</text></svg>`)}`;
export const IMAGE_CID = SVG('#1f6f4a', 'image insérée');
export const IMAGE_DISTANTE = SVG('#a33b2c', 'image distante');

const STYLE_HTML = 'html{color-scheme:light;background:#fff}body{margin:8px 12px;background:#fff;overflow-wrap:anywhere}img{max-width:100%;height:auto}.jr-bandeau{margin:0 0 12px;padding:8px 10px;border-radius:8px;background:#f2f2f2;color:#555;font:13px/1.4 sans-serif}';
const STYLE_TEXTE = ':root{color-scheme:light dark}body{margin:8px 12px;font:16px/1.45 -apple-system,BlinkMacSystemFont,"Helvetica Neue",sans-serif;white-space:pre-wrap;overflow-wrap:anywhere}a{color:#0a60d8}@media (prefers-color-scheme:dark){body{background:#000;color:#f2f2f7}a{color:#6cb0ff}}';

export const pageHtml = (corps, { bloquees = false } = {}) => `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${STYLE_HTML}</style></head><body${bloquees ? ' data-images-bloquees="1"' : ''}>${corps}</body></html>`;
export const pageTexte = (corpsHtml) => `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${STYLE_TEXTE}</style></head><body>${corpsHtml}</body></html>`;

const personne = (nom, adresse) => ({ nom, adresse });

export function contenusMailDeBase() {
  const piece = (partie, nom, type, taille, trop_lourde = false) => ({ partie, nom, type, taille, trop_lourde });
  const ouvrir = (autres) => ({
    objet: '', de_nom: '', de_adresse: '', a: [personne('Colin Flicourt', 'colin.flicourt@etu.utc.fr')], cc: [], date: '', pour_contact: false,
    pieces: [], repondre_url: '', repondre_repli: null, marque_lu: true, ...autres,
  });
  return {
    // HTML : image distante bloquée puis affichée, image insérée (cid) toujours là, une pièce
    'utc/101': {
      ouvrir: ouvrir({
        objet: 'Inscription aux UV de A27', de_nom: 'Secrétariat GI', de_adresse: 'secretariat-gi@utc.fr', date: '2026-10-14T12:32:00Z',
        a: [personne('Colin Flicourt', 'colin.flicourt@etu.utc.fr'), personne('', 'promo-gi@utc.fr')], cc: [personne('Prof. Durand', 'durand@utc.fr')],
        pieces: [piece('2', 'Calendrier-inscriptions.pdf', 'application/pdf', 358400)],
        repondre_url: 'https://webmail.utc.fr/?_task=mail&_action=compose&_to=secretariat-gi%40utc.fr&_subject=Re%3A%20Inscription%20aux%20UV%20de%20A27',
      }),
      corps: pageHtml(`<h2>Inscriptions aux UV de A27</h2><p>Bonjour, les inscriptions ouvrent <b>lundi à 8 h</b>. Pense à vérifier ton dossier avant cette date.</p><p><img id="cid" alt="Plan" src="${IMAGE_CID}"></p><p>Cordialement,<br>Le secrétariat GI</p>`, { bloquees: true }),
      corpsImages: pageHtml(`<h2>Inscriptions aux UV de A27</h2><p>Bonjour, les inscriptions ouvrent <b>lundi à 8 h</b>. Pense à vérifier ton dossier avant cette date.</p><p><img id="cid" alt="Plan" src="${IMAGE_CID}"></p><p><img id="distante" alt="Bandeau" src="${IMAGE_DISTANTE}"></p><p>Cordialement,<br>Le secrétariat GI</p>`),
      fichiers: { 2: { type: 'application/pdf', octets: '%PDF-1.4\n% calendrier de test\n' } },
    },
    // Texte : un lien, une adresse mail, trois pièces dont une trop lourde
    'utc/102': {
      ouvrir: ouvrir({
        objet: 'TD de jeudi déplacé', de_nom: 'Prof. Durand', de_adresse: 'durand@utc.fr', date: '2026-10-13T09:10:00Z',
        pieces: [
          piece('2', 'Sujet-TD3.pdf', 'application/pdf', 1258291),
          piece('3', 'donnees-mesures.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 358400),
          piece('4', 'Archive-complete.zip', 'application/zip', 25165824, true),
        ],
        repondre_url: 'https://webmail.utc.fr/?_task=mail&_action=compose&_to=durand%40utc.fr&_subject=Re%3A%20TD%20de%20jeudi%20d%C3%A9plac%C3%A9',
      }),
      corps: pageTexte('Bonjour,\n\nLe TD de jeudi se tiendra en salle FA420. Le sujet est ici : <a href="https://exemple.fr/sujet" target="_blank" rel="noopener noreferrer">https://exemple.fr/sujet</a>\n\nQuestions : <a href="mailto:durand@utc.fr" target="_blank" rel="noopener noreferrer">durand@utc.fr</a>\n\nBonne journée,\nPr. Durand'),
      fichiers: { 2: { type: 'application/pdf', octets: '%PDF-1.4\n% sujet de test\n' }, 3: { type: 'application/octet-stream', octets: 'PK-test' } },
    },
    // Disparu de la boîte
    'utc/103': { erreur: { statut: 404, code: 'absent' } },
    // Mail piégé, servi sans nettoyage ni en-têtes : seul le cadre `sandbox` l'arrête
    'colin/201': {
      ouvrir: ouvrir({
        objet: 'Devis du site', de_nom: 'Cathy Martin', de_adresse: 'cathy@exemple-idfense.fr', date: '2026-10-14T08:05:00Z',
        a: [personne('', 'contact@qyris.fr')], repondre_url: 'mailto:cathy%40exemple-idfense.fr?subject=Re%3A%20Devis%20du%20site',
      }),
      sansCsp: true,
      corps: '<!doctype html><html><head><meta charset="utf-8"><script>parent.document.title = "PIRATE"; window.__pirate = "script";</script></head><body><p id="texte">Bonjour Colin, voici le devis signé.</p><img id="piege" src="x" onerror="parent.document.title = \'PIRATE2\'; window.__pirate = \'onerror\'"><a id="js" href="javascript:parent.document.title=\'PIRATE3\'">Voir</a></body></html>',
    },
    'colin/202': {
      ouvrir: ouvrir({
        objet: 'Question sur la formation', de_nom: 'Léa Bernard', de_adresse: 'lea@exemple.fr', date: '2026-10-12T10:00:00Z', pour_contact: true,
        a: [personne('', 'contact@qyris.fr')], repondre_url: 'mailto:lea%40exemple.fr?subject=Re%3A%20Question%20sur%20la%20formation',
      }),
      corps: pageTexte('Bonjour,\n\nLa formation est-elle ouverte aux débutants ?\n\nLéa'),
    },
    'gmail/301': {
      ouvrir: ouvrir({
        objet: 'Nouvelle connexion détectée', de_nom: 'Hébergeur', de_adresse: 'support@exemple-hebergeur.fr', date: '2026-10-14T11:20:00Z',
        a: [personne('', 'colin.flicourt@gmail.com')],
        repondre_url: 'googlegmail://co?to=support%40exemple-hebergeur.fr&subject=Re%3A%20Nouvelle%20connexion%20d%C3%A9tect%C3%A9e',
        repondre_repli: 'https://mail.google.com/mail/?view=cm&to=support%40exemple-hebergeur.fr&su=Re%3A%20Nouvelle%20connexion%20d%C3%A9tect%C3%A9e',
      }),
      corps: pageTexte('Une connexion depuis un nouvel appareil a eu lieu.\n\nCorps-confidentiel-gmail'),
    },
    // Sans nom, sans objet
    'icloud/401': {
      ouvrir: ouvrir({ objet: '', de_nom: '', de_adresse: 'maman@exemple.fr', date: '2025-12-24T10:00:00Z', a: [personne('', 'colin.flicourt@icloud.com')], repondre_url: 'mailto:maman%40exemple.fr?subject=Re%3A%20' }),
      corps: pageTexte('On mange à 12 h 30, tu viens ?'),
    },
  };
}
