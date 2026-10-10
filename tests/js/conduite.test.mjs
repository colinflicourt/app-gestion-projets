import { test } from 'node:test';
import assert from 'node:assert/strict';
import { etatConduite, medianProche, nomCreneau } from '../../site/js/lib/conduite.js';

const cours = (jour, uv, type, debut, fin, salle) => ({ debut: `${jour}T${debut}:00+02:00`, fin: `${jour}T${fin}:00+02:00`, journee: false, source: 'utc', uv, type, salle, titre: `${uv} - ${type} 1`, median: false });
const perso = (jour, titre, debut, fin) => ({ ...cours(jour, null, null, debut, fin, null), source: 'perso', titre, median: titre.startsWith('Médian') });
const JOURS = {
  '2026-10-14': { bandeaux: [], evenements: [cours('2026-10-14', 'NF93', 'TD', '08:00', '10:00', 'FA400'), perso('2026-10-14', 'Déjeuner', '12:30', '13:30'), cours('2026-10-14', 'TN01', 'TD', '16:30', '19:30', 'FA510')] },
  '2026-10-15': { bandeaux: [{ type: 'ferie', jour: null, texte: "Jour férié à l'UTC : pas de cours." }], evenements: [] },
  '2026-10-19': { bandeaux: [], evenements: [cours('2026-10-19', 'MT23', 'TD', '16:30', '18:30', 'FA420')] },
  '2026-10-21': { bandeaux: [], evenements: [perso('2026-10-21', 'Médian MT23', '19:15', '20:45')] },
};
const a = (iso) => etatConduite(JOURS, new Date(iso));

test('conduite : au réveil, entre deux cours, pendant un cours, le soir', () => {
  const matin = a('2026-10-14T07:32:00+02:00');
  assert.deepEqual([matin.scene, matin.standby.cours.uv, matin.standby.dans, matin.standby.quand], [{ type: 'rien', nbCours: 2 }, 'NF93', 28, 'aujourdhui']);
  const entracte = a('2026-10-14T15:45:00+02:00');
  assert.deepEqual([entracte.scene, entracte.standby.cours.uv, entracte.standby.dans], [{ type: 'entracte', libres: 45 }, 'TN01', 45]);
  const td = a('2026-10-14T17:10:00+02:00');
  assert.deepEqual([td.scene.type, td.scene.cours.uv, td.scene.finDans, td.enCours, td.standby.cours.uv, td.standby.quand], ['cours', 'TN01', 140, true, 'MT23', 'plus_tard']);
  const soir = a('2026-10-14T21:00:00+02:00');
  assert.deepEqual([soir.scene.type, soir.standby.cours.jour, soir.nbCours, soir.premierCours.uv], ['fini', '2026-10-19', 2, 'NF93']);
});

test('conduite : jour férié (bandeau), veille du cours (« demain »), aucune donnée', () => {
  assert.equal(a('2026-10-15T10:00:00+02:00').bandeaux[0].type, 'ferie');
  assert.equal(a('2026-10-18T20:00:00+02:00').standby.quand, 'demain');
  const rien = etatConduite({}, new Date('2026-10-14T15:45:00+02:00'));
  assert.deepEqual([rien.scene.type, rien.standby, rien.nbCours], ['fini', null, 0]);
});

test('médian : dans 7 jours ou moins, avec son nombre de jours ; nom des créneaux', () => {
  assert.equal(medianProche(JOURS, new Date('2026-10-14T15:45:00+02:00')).dans, 7);
  assert.equal(medianProche(JOURS, new Date('2026-10-13T15:45:00+02:00')), null);
  assert.equal(medianProche(JOURS, new Date('2026-10-21T21:00:00+02:00')), null);
  assert.equal(nomCreneau(JOURS['2026-10-14'].evenements[0]), 'NF93 · TD');
  assert.equal(nomCreneau(JOURS['2026-10-14'].evenements[1]), 'Déjeuner');
});
