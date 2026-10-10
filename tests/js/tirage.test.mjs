import { test } from 'node:test';
import assert from 'node:assert/strict';
import { candidats, etiquetteProposition, ligneAfficheur, replique, rouler, score, tempsLibre, texteProposition, tirer } from '../../site/js/lib/tirage.js';
import { T } from '../../site/js/lib/textes.js';

const MAINTENANT = new Date('2026-10-14T15:45:00+02:00');
const cours = (uv, debut, fin) => ({ debut: `2026-10-14T${debut}:00+02:00`, fin: `2026-10-14T${fin}:00+02:00`, journee: false, source: 'utc', uv, type: 'TD', salle: 'FA510', titre: `${uv} - TD 1`, median: false });
const JOURS = { '2026-10-14': { bandeaux: [], evenements: [cours('NF93', '08:00', '10:00'), cours('TN01', '16:30', '19:30')] } };
const carte = (id, autres = {}) => ({
  id, domaine: 'qyris', colonne: 'a_faire', position: 1, titre: id, description: '', url: '', echeance: null, echeance_faite: false,
  urgent: false, etiquettes: [], checklists: [], derniere_activite: '', ...autres,
});
const a = (iso) => tempsLibre(JOURS, new Date(iso));

test('temps libre : avant le prochain cours (moins 10 min de trajet), pendant un cours, sans autre cours, après 23 h', () => {
  const avant = a('2026-10-14T15:45:00+02:00');
  assert.deepEqual([avant.enCours, avant.coursDans, avant.minutes, avant.avant.uv, avant.tard], [false, 45, 35, 'TN01', false]);
  assert.equal(a('2026-10-14T17:00:00+02:00').enCours, true);
  const soir = a('2026-10-14T20:15:00+02:00');
  assert.deepEqual([soir.minutes, soir.avant, soir.tard], [165, null, false]);
  assert.equal(a('2026-10-14T23:30:00+02:00').tard, true);
});

test('score : retard (100 + 5 par jour, 30 jours au plus), échéance proche, Urgent, En cours', () => {
  const avec = (echeance) => score(carte('x', { echeance }), MAINTENANT);
  assert.deepEqual(
    ['2026-10-06T08:30:00+02:00', '2026-08-01T18:00:00+02:00', '2026-10-14T18:00:00+02:00', '2026-10-15T18:00:00+02:00', '2026-10-17T18:00:00+02:00', '2026-10-21T19:15:00+02:00', '2026-10-22T19:15:00+02:00'].map(avec),
    [140, 250, 80, 50, 30, 15, 0],
  );
  assert.equal(score(carte('h', { urgent: true, colonne: 'en_cours' }), MAINTENANT), 65);
});

test('candidats : première étape non cochée, carte entière sans étape restante, En attente et Fait exclus, triés par score', () => {
  const etapes = { id: 'cc', nom: 'Étapes', elements: [{ id: 'e1', nom: 'Fait', coche: true }, { id: 'e2', nom: 'Envoyer le mail', coche: false }] };
  const liste = candidats([carte('a', { checklists: [etapes] }), carte('b', { urgent: true }), carte('c', { colonne: 'en_attente', urgent: true }), carte('d', { colonne: 'fait' })], MAINTENANT);
  assert.deepEqual(liste.map((c) => [c.carte.id, c.element?.id ?? null, c.score]), [['b', null, 40], ['a', 'e2', 0]]);
});

test('tirer : parmi les 5 meilleurs pas encore proposés ; texte de la proposition', () => {
  const liste = candidats(['a', 'b', 'c', 'd', 'e', 'f'].map((id, i) => carte(id, { urgent: i < 2 })), MAINTENANT);
  const proposes = new Set();
  for (let i = 0; i < 5; i++) proposes.add(tirer(liste, proposes, () => 0).cle);
  assert.deepEqual([...proposes].map((cle) => cle.split(':')[0]), ['a', 'b', 'c', 'd', 'e']);
  assert.equal(tirer([], new Set(), Math.random), null);
  const etape = (nom) => ({ carte: carte('x', { titre: 'Formation 2 : relire le deck' }), element: { id: 'e', nom } });
  assert.equal(texteProposition(etape('Tester les exercices sur les comptes gratuits')), 'Formation 2 : tester les exercices sur les comptes gratuits.');
  assert.equal(texteProposition(etape('QYRIS : envoyer le devis.')), 'Formation 2 : QYRIS : envoyer le devis.');
  assert.equal(texteProposition({ carte: carte('y', { titre: 'Dentiste' }), element: null }), 'Terminer : Dentiste');
});

test('afficheur, répliques, étiquette et défilement du tirage', () => {
  const avant = a('2026-10-14T15:45:00+02:00');
  assert.equal(ligneAfficheur(avant), '35 MIN AVANT TN01');
  assert.equal(ligneAfficheur(a('2026-10-14T20:15:00+02:00')), "LIBRE JUSQU'À 23 H");
  assert.equal(ligneAfficheur(a('2026-10-14T23:30:00+02:00')), 'IL EST TARD');
  assert.equal(replique(avant, 0), "Je t'ai trouvé 35 min avant ton TD.");
  assert.deepEqual([1, 2, 3, 4].map((n) => replique(avant, n)), T.tirage.relances);
  assert.equal(replique(a('2026-10-14T20:15:00+02:00'), 0), "Libre jusqu'à 23 h.");
  assert.equal(replique(a('2026-10-14T23:30:00+02:00'), 0), 'Il est tard, mais puisque tu insistes :');
  assert.equal(etiquetteProposition({ carte: carte('z', { domaine: 'etudes', etiquettes: ['GE15'], colonne: 'en_cours' }) }, MAINTENANT), 'Études · GE15 · en cours');
  assert.equal(rouler('35 MIN', 1), '35 MIN');
  assert.equal(rouler('35 MIN', 0, () => 0), '00 000');
});
