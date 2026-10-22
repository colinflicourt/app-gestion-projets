import { test } from 'node:test';
import assert from 'node:assert/strict';
import { decision, integrer, remplacerIdLocal, retirerEcriture, vue } from '../../site/js/etat.js';

const carte = (id, domaine, colonne, position, autres = {}) => ({
  id, domaine, colonne, position, titre: id, description: '', url: '', echeance: null, echeance_faite: false, urgent: false,
  etiquettes: [], checklists: [], derniere_activite: '2026-10-13T10:00:00+02:00', ...autres,
});
const etapes = () => ({ id: 'cc1', nom: 'Étapes', elements: [{ id: 'e1', nom: 'Lire', coche: false }, { id: 'e2', nom: 'Écrire', coche: false }] });
const donnees = () => ({
  maintenant: '2026-10-14T15:45:00+02:00',
  trello: { recupere_le: null, erreur: null, cartes: [
    carte('q1', 'qyris', 'a_faire', 1000, { checklists: [etapes()] }),
    carte('q2', 'qyris', 'fait', 3000, { echeance: '2026-10-06T08:30:00+02:00', echeance_faite: true }),
    carte('e1', 'etudes', 'a_faire', 2000, { echeance: '2026-10-21T19:15:00+02:00' }),
  ] },
  agenda: { recupere_le: null, erreur: null, google_connecte: false, jours: {} },
});
const ecriture = (route, corps, local = {}) => ({ id_client: `id-${route}`, route, corps, local, ordre: 1, essais: 0 });

test("vue : les écritures en attente s'appliquent dans l'ordre, marquées « attente », sans toucher la copie", () => {
  const d = donnees();
  const v = vue(d, [ecriture('taches/cocher', { carte: 'q1', element: 'e1', coche: true }), ecriture('taches/terminer', { carte: 'e1' })]);
  const q1 = v.trello.cartes.find((c) => c.id === 'q1');
  assert.deepEqual([q1.checklists[0].elements[0].coche, q1.checklists[0].elements[0].attente, q1.attente], [true, true, true]);
  const e1 = v.trello.cartes.find((c) => c.id === 'e1');
  assert.deepEqual([e1.colonne, e1.echeance_faite, e1.attente], ['fait', true, true]);
  assert.equal(d.trello.cartes[0].checklists[0].elements[0].coche, false);
  assert.equal(d.trello.cartes[2].colonne, 'a_faire');
  assert.equal(vue(null, []), null);
});

test("vue : déplacer en haut d'une colonne, sortir de Fait rouvre l'échéance, ajouter crée la carte locale en haut d'À faire", () => {
  const v = vue(donnees(), [
    ecriture('taches/deplacer', { carte: 'q2', colonne: 'en_cours' }),
    ecriture('taches/ajouter', { domaine: 'qyris', titre: 'Appeler Sébastien', urgent: true, echeance: '2026-10-20' }, { id: 'local-1', echeance_iso: '2026-10-20T18:00:00+02:00', cree_le: '2026-10-14T13:45:00.000Z' }),
  ]);
  const q2 = v.trello.cartes.find((c) => c.id === 'q2');
  assert.deepEqual([q2.colonne, q2.echeance_faite], ['en_cours', false]);
  const nouvelle = v.trello.cartes.find((c) => c.id === 'local-1');
  assert.deepEqual([nouvelle.colonne, nouvelle.urgent, nouvelle.echeance, nouvelle.attente], ['a_faire', true, '2026-10-20T18:00:00+02:00', true]);
  assert.deepEqual(v.trello.cartes.map((c) => c.id), ['local-1', 'q1', 'q2', 'e1']);
});

test("annuler : la carte revient à sa colonne, sa position et son échéance d'avant", () => {
  const v = vue(donnees(), [
    ecriture('taches/terminer', { carte: 'e1' }),
    ecriture('taches/annuler', { id_client_origine: 'id-taches/terminer' }, { carte: 'e1', colonne_avant: 'a_faire', position_avant: 2000, echeance_faite_avant: false }),
  ]);
  const e1 = v.trello.cartes.find((c) => c.id === 'e1');
  assert.deepEqual([e1.colonne, e1.position, e1.echeance_faite], ['a_faire', 2000, false]);
});

test('intégrer : une capture acceptée remplace la carte locale par celle du serveur, plus rien « en attente »', () => {
  const ajout = ecriture('taches/ajouter', { domaine: 'etudes', titre: 'Réviser', urgent: false, echeance: null }, { id: 'local-1', echeance_iso: null, cree_le: 'x' });
  const duServeur = carte('ca99', 'etudes', 'a_faire', 100, { titre: 'Réviser' });
  assert.deepEqual(integrer(donnees(), ajout, { carte: duServeur }).trello.cartes.map((c) => c.id), ['q1', 'q2', 'ca99', 'e1']);
  const d = integrer(donnees(), ecriture('taches/cocher', { carte: 'q1', element: 'e2', coche: true }), {});
  const q1 = d.trello.cartes.find((c) => c.id === 'q1');
  assert.deepEqual([q1.checklists[0].elements[1].coche, 'attente' in q1, 'attente' in q1.checklists[0].elements[1]], [true, false, false]);
});

test("file : remplacer l'identifiant local, retirer une écriture, décider selon la réponse", () => {
  const f = [
    ecriture('taches/cocher', { carte: 'local-1', element: 'x', coche: true }),
    ecriture('taches/annuler', { id_client_origine: 'a' }, { carte: 'local-1' }),
    ecriture('taches/deplacer', { carte: 'q1', colonne: 'en_cours' }),
  ];
  const g = remplacerIdLocal(f, 'local-1', 'ca99');
  assert.deepEqual(g.map((e) => [e.corps.carte ?? null, e.local.carte ?? null]), [['ca99', null], [null, 'ca99'], ['q1', null]]);
  assert.equal(retirerEcriture(f, 'id-taches/annuler').length, 2);
  assert.deepEqual([200, 401, 404, 409, 400, 500, 502, null].map(decision), ['retirer', 'connexion', 'retirer', 'retirer', 'retirer', 'garder', 'garder', 'garder']);
});

test('rappels du jour : un rappel en attente s\'ajoute sans bouton, une annulation le retire, la réponse le confirme', () => {
  const d = { ...donnees(), rappels_du_jour: [{ id: 'r1', type: 'manuel', heure: '2026-10-14T17:30:00+02:00', titre: 'Devis', passe: false, annulable: true }] };
  const creation = ecriture('rappels', { texte: 'Pain', quand: '2026-10-14T16:45:00+02:00' }, { id: 'local-1' });
  const v = vue(d, [creation]);
  assert.deepEqual(v.rappels_du_jour.map((r) => [r.id, r.annulable]), [['local-1', false], ['r1', true]]);
  assert.equal(d.rappels_du_jour.length, 1);
  assert.deepEqual(vue(d, [ecriture('rappels/annuler', { id: 'r1' })]).rappels_du_jour, []);
  const apres = integrer(d, creation, { rappel: { id: 'r2', texte: 'Pain', quand: '2026-10-14T16:45:00+02:00' } });
  assert.deepEqual(apres.rappels_du_jour.map((r) => [r.id, r.annulable]), [['r2', true], ['r1', true]]);
  assert.deepEqual(integrer(apres, ecriture('rappels/annuler', { id: 'r2' }), { id: 'r2' }).rappels_du_jour.map((r) => r.id), ['r1']);
});
