// Serveur des tests d'écran : les fichiers de site/ avec les en-têtes de sécurité du .htaccess (une seule
// source), et une fausse API (Trello et Google simulés) dont l'état est lu et changé par les tests.
import http from 'node:http';
import { readFile } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';
import { donneesDeBase, mailsDeBase, joursSemaine, reglagesDeBase, comptesMailDeBase, JETON_SIRI } from './fixtures.mjs';

const SITE = fileURLToPath(new URL('../../site/', import.meta.url));
const TYPES = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json', '.webmanifest': 'application/manifest+json', '.png': 'image/png', '.webp': 'image/webp',
  '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.txt': 'text/plain; charset=utf-8',
};
const ENTETES = Object.fromEntries(
  [...readFileSync(join(SITE, '.htaccess'), 'utf8').matchAll(/^\s*Header always set (\S+) "([^"]*)"$/gm)]
    .map((m) => [m[1], m[2]])
    .filter(([nom]) => nom !== 'Strict-Transport-Security'),
);

export const MOT_DE_PASSE = 'phrase de test';

export async function demarrer() {
  const etat = {};
  const s = {
    etat,
    origine: '',
    reinitialiser(donnees = donneesDeBase()) {
      for (const cle of Object.keys(etat)) delete etat[cle];
      // horsService : le réseau manque (connexion coupée). perdre : la prochaine écriture de cette route est
      // faite, mais sa réponse se perd. pannes : route → { statut, code, message }.
      Object.assign(etat, { donnees, appels: [], vus: new Map(), pannes: {}, horsService: false, perdre: null, testEnvoyes: 1, reglages: reglagesDeBase(), mails: mailsDeBase(), corrections: [], comptesMail: comptesMailDeBase(), essaisMail: {} });
    },
    appels(route) {
      return etat.appels.filter((a) => a.route === route);
    },
  };
  s.reinitialiser();
  const serveur = http.createServer((req, res) => {
    traiter(req, res, etat).catch((e) => {
      res.writeHead(500);
      res.end(String(e));
    });
  });
  await new Promise((resolve) => serveur.listen(0, '127.0.0.1', resolve));
  s.origine = `http://127.0.0.1:${serveur.address().port}`;
  s.arreter = () => new Promise((resolve) => {
    serveur.closeAllConnections();
    serveur.close(resolve);
  });
  return s;
}

async function traiter(req, res, etat) {
  const url = new URL(req.url, 'http://test');
  if (url.pathname.startsWith('/api/')) return api(req, res, url, etat);
  const chemin = decodeURIComponent(url.pathname === '/' ? '/index.html' : url.pathname);
  const fichier = normalize(join(SITE, chemin));
  if (!fichier.startsWith(SITE) || chemin.split('/').some((p) => p.startsWith('.'))) {
    return envoyer(res, 404, 'text/plain; charset=utf-8', 'Introuvable');
  }
  let contenu;
  try {
    contenu = await readFile(fichier);
  } catch {
    return envoyer(res, 404, 'text/plain; charset=utf-8', 'Introuvable');
  }
  envoyer(res, 200, TYPES[extname(fichier)] ?? 'application/octet-stream', contenu);
}

function envoyer(res, statut, type, corps, autres = {}) {
  res.writeHead(statut, { ...ENTETES, 'Content-Type': type, 'Cache-Control': 'no-cache', ...autres });
  res.end(corps);
}

function json(res, statut, donnees, autres = {}) {
  const corps = statut < 400 ? { ok: true, ...donnees } : { ok: false, erreur: donnees };
  envoyer(res, statut, 'application/json', JSON.stringify(corps), { 'Cache-Control': 'no-store', ...autres });
}

async function api(req, res, url, etat) {
  if (etat.horsService) {
    req.socket.destroy();
    return;
  }
  const route = url.pathname.slice('/api/'.length);
  let corps = {};
  if (req.method === 'POST') {
    let brut = '';
    for await (const morceau of req) brut += morceau;
    corps = brut === '' ? {} : JSON.parse(brut);
  }
  etat.appels.push({ route, methode: req.method, corps, parametres: Object.fromEntries(url.searchParams) });
  const panne = etat.pannes[route];
  if (panne) return json(res, panne.statut, { code: panne.code, message: panne.message });
  if (req.method === 'GET' && route === 'sante') return json(res, 200, { version_app: '0.2.0', version_base: 2, base_ok: true });
  if (req.method === 'POST' && route === 'connexion') {
    if (corps.mot_de_passe !== MOT_DE_PASSE) {
      return json(res, 401, { code: 'mot_de_passe_incorrect', message: 'Mot de passe incorrect. Encore 9 essais avant une pause de 15 min.' });
    }
    return json(res, 200, {}, { 'Set-Cookie': 'jr-test=1; Path=/; HttpOnly; SameSite=Lax' });
  }
  if (!/(?:^|;\s*)jr-test=1(?:;|$)/.test(req.headers.cookie ?? '')) {
    return json(res, 401, { code: 'session_expiree', message: 'Ta session a expiré. Reconnecte-toi.' });
  }
  if (req.method === 'POST' && (route === 'deconnexion' || route === 'deconnexion-partout')) {
    return json(res, 200, {}, { 'Set-Cookie': 'jr-test=; Path=/; Max-Age=0' });
  }
  if (req.method === 'GET' && route === 'donnees') return json(res, 200, structuredClone(etat.donnees));
  if (req.method === 'GET' && route === 'agenda') {
    const semaine = url.searchParams.get('semaine');
    return json(res, 200, {
      maintenant: etat.donnees.maintenant, semaine,
      agenda: { recupere_le: etat.donnees.maintenant, erreur: null, google_connecte: true, jours: joursSemaine(semaine) },
    });
  }
  if (req.method === 'GET' && route === 'google/connexion') {
    etat.donnees.agenda.google_connecte = true;
    etat.donnees.agenda.erreur = null;
    return envoyer(res, 302, 'text/plain; charset=utf-8', '', { Location: '/#/moi?google=connecte' });
  }
  if (req.method === 'GET' && route === 'mails') return json(res, 200, structuredClone(etat.mails));
  if (req.method === 'POST' && route === 'mails/rafraichir') {
    const parSection = { utc: ['utc'], gmail: ['gmail'], icloud: ['icloud'], colin: ['qyris'], contact: ['qyris'] }[corps.compte];
    if (!parSection) return json(res, 400, { code: 'compte_invalide', message: 'Compte inconnu.' });
    return json(res, 200, {
      compte: corps.compte, sections: structuredClone(etat.mails.sections.filter((s) => parSection.includes(s.section))),
      comptes: { [corps.compte]: structuredClone(etat.mails.comptes[corps.compte]) },
    });
  }
  if (req.method === 'GET' && route === 'comptes-mail') return json(res, 200, { comptes: structuredClone(etat.comptesMail) });
  if (req.method === 'POST' && (route === 'comptes-mail' || route === 'comptes-mail/tester')) return comptesMail(res, route, corps, etat);
  if (req.method === 'GET' && route === 'reglages') return json(res, 200, structuredClone(etat.reglages));
  if (req.method === 'POST' && route === 'reglages/siri') {
    etat.reglages.siri.jeton_cree = true;
    return json(res, 200, { jeton: JETON_SIRI });
  }
  if (req.method === 'POST' && (route === 'abonnements' || route === 'abonnements/supprimer')) return json(res, 200, {});
  if (req.method === 'POST' && route === 'notifications/test') return json(res, 200, { envoyes: etat.testEnvoyes, supprimes: 0, echecs: 0 });
  if (req.method === 'POST') return ecrire(req, res, route, corps, etat);
  return json(res, 404, { code: 'route_inconnue', message: 'Adresse inconnue.' });
}

// Essai de connexion d'une boîte : etat.essaisMail[compte] = code d'échec (refus, injoignable, delai), sinon réussi.
// Le faux serveur ne garde jamais le mot de passe et ne le rend jamais.
function comptesMail(res, route, corps, etat) {
  const compte = etat.comptesMail[corps.compte];
  if (!compte) return json(res, 400, { code: 'compte_invalide', message: 'Compte inconnu.' });
  const echec = etat.essaisMail[corps.compte] ?? null;
  const tester = route === 'comptes-mail/tester';
  if (tester && !compte.enregistre) return json(res, 400, { code: 'non_connecte', message: 'Pas encore connecté' });
  if (echec) {
    if (tester) compte.derniere_erreur = echec;
    return json(res, 400, { code: echec, message: `Message du serveur pour ${echec}` });
  }
  if (!tester) {
    if (typeof corps.mot_de_passe !== 'string' || corps.mot_de_passe === '') return json(res, 400, { code: 'requete_invalide', message: 'Requête illisible.' });
    compte.identifiant = corps.identifiant.trim();
    compte.enregistre = true;
  }
  compte.derniere_erreur = null;
  compte.dernier_succes = new Date(etat.donnees.maintenant).toISOString().replace(/\.\d+Z$/, 'Z');
  return json(res, 200, { comptes: structuredClone(etat.comptesMail) });
}

// Les écritures, comme le vrai serveur : un id_client déjà vu rend le même résultat sans rien refaire
function ecrire(req, res, route, corps, etat) {
  const deja = etat.vus.get(corps.id_client);
  if (deja) return json(res, 200, deja.resultat);
  const cartes = etat.donnees.trello.cartes;
  const carte = cartes.find((c) => c.id === corps.carte);
  const rappels = (etat.donnees.rappels_du_jour ??= []);
  let resultat;
  if (route === 'reglages/rappels') {
    etat.reglages.rappels = corps.rappels;
    resultat = { rappels: corps.rappels };
  } else if (route === 'mails/expediteur') {
    resultat = { adresse: corps.adresse.trim().toLowerCase(), choix: corps.choix };
    etat.corrections.push(resultat);
  } else if (route === 'comptes-mail/supprimer') {
    Object.assign(etat.comptesMail[corps.compte], { enregistre: false, dernier_succes: null, derniere_erreur: null });
    resultat = { comptes: structuredClone(etat.comptesMail) };
  } else if (route === 'rappels') {
    const rappel = { id: `rm${String(etat.vus.size + 1).padStart(6, '0')}`, texte: corps.texte, quand: corps.quand };
    if (rappel.quand.slice(0, 10) === etat.donnees.maintenant.slice(0, 10)) {
      rappels.push({ id: rappel.id, type: 'manuel', heure: rappel.quand, titre: rappel.texte, passe: false, annulable: true });
      rappels.sort((a, b) => a.heure.localeCompare(b.heure));
    }
    resultat = { rappel };
  } else if (route === 'rappels/annuler') {
    if (!rappels.some((r) => r.id === corps.id)) return json(res, 404, { code: 'rappel_introuvable', message: "Ce rappel n'existe plus." });
    etat.donnees.rappels_du_jour = rappels.filter((r) => r.id !== corps.id);
    resultat = { id: corps.id };
  } else if (route === 'taches/ajouter') {
    const heure = corps.echeance && corps.echeance.length > 10 ? corps.echeance.slice(11, 16) : '18:00';
    const nouvelle = {
      id: `cb${String(etat.vus.size + 1).padStart(22, '0')}`, domaine: corps.domaine, colonne: 'a_faire', position: 1,
      titre: corps.titre, description: '', url: 'https://trello.com/c/nouvelle',
      echeance: corps.echeance ? `${corps.echeance.slice(0, 10)}T${heure}:00+02:00` : null, echeance_faite: false,
      urgent: corps.urgent === true, etiquettes: [], checklists: [], derniere_activite: etat.donnees.maintenant,
    };
    cartes.unshift(nouvelle);
    resultat = { carte: nouvelle };
  } else if (route === 'taches/annuler') {
    const origine = etat.vus.get(corps.id_client_origine);
    if (!origine || origine.route !== 'taches/terminer') return json(res, 404, { code: 'annulation_impossible', message: 'Rien à annuler.' });
    const annulee = cartes.find((c) => c.id === origine.resultat.carte);
    annulee.colonne = origine.resultat.colonne_avant;
    resultat = { carte: annulee.id, colonne: annulee.colonne };
  } else if (!carte) {
    return json(res, 404, { code: 'carte_introuvable', message: "Cette carte n'existe plus dans Trello." });
  } else if (route === 'taches/cocher') {
    for (const liste of carte.checklists) for (const e of liste.elements) if (e.id === corps.element) e.coche = corps.coche;
    resultat = { carte: carte.id, element: corps.element, coche: corps.coche };
  } else if (route === 'taches/terminer') {
    resultat = { carte: carte.id, colonne: 'fait', colonne_avant: carte.colonne };
    carte.colonne = 'fait';
  } else if (route === 'taches/deplacer') {
    carte.colonne = corps.colonne;
    resultat = { carte: carte.id, colonne: corps.colonne };
  } else if (route === 'tirage/parti') {
    resultat = { carte: carte.id, element: corps.element };
  } else {
    return json(res, 404, { code: 'route_inconnue', message: 'Adresse inconnue.' });
  }
  etat.vus.set(corps.id_client, { route, resultat });
  if (etat.perdre === route) {
    etat.perdre = null;
    req.socket.destroy();
    return;
  }
  return json(res, 200, resultat);
}
