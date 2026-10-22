import { test } from 'node:test';
import assert from 'node:assert/strict';
import { choixRappel, choixLibre, texteConfirmation } from '../../site/js/lib/heures-rappel.js';

const par = (maintenant) => Object.fromEntries(choixRappel(new Date(maintenant)).map((c) => [c.id, c]));

test('les 4 choix un mercredi après-midi', () => {
  const c = choixRappel(new Date('2026-10-14T15:45:00+02:00'));
  assert.deepEqual(c.map((x) => x.libelle), ['Dans 1 h', 'Ce soir 20 h', 'Demain 8 h', 'Choisir…']);
  assert.deepEqual(c.map((x) => x.quand), ['2026-10-14T16:45:00+02:00', '2026-10-14T20:00:00+02:00', '2026-10-15T08:00:00+02:00', null]);
});

test('« Ce soir 20 h » devient « Demain 20 h » à partir de 19 h 30', () => {
  assert.equal(par('2026-10-14T19:29:00+02:00').soir.libelle, 'Ce soir 20 h');
  assert.equal(par('2026-10-14T19:30:00+02:00').soir.libelle, 'Demain 20 h');
  assert.equal(par('2026-10-14T19:30:00+02:00').soir.quand, '2026-10-15T20:00:00+02:00');
});

test('23 h 59 et minuit : « Demain 8 h » est le bon jour', () => {
  assert.equal(par('2026-10-14T23:59:00+02:00').demain8.quand, '2026-10-15T08:00:00+02:00');
  assert.equal(par('2026-10-15T00:00:00+02:00').demain8.quand, '2026-10-16T08:00:00+02:00');
  assert.equal(par('2026-10-14T23:59:00+02:00').soir.libelle, 'Demain 20 h');
});

test("heure de Paris même si l'appareil est ailleurs : 22 h UTC = minuit à Paris", () => {
  assert.equal(par('2026-10-14T22:00:00Z').demain8.quand, '2026-10-16T08:00:00+02:00');
});

test("passage à l'heure d'hiver : samedi 24/10 22 h, « Demain 8 h » est dimanche en +01:00", () => {
  const c = par('2026-10-24T22:00:00+02:00');
  assert.equal(c.demain8.quand, '2026-10-25T08:00:00+01:00');
  assert.equal(c.soir.quand, '2026-10-25T20:00:00+01:00');
});

test('« Dans 1 h » à 2 h 30 le 25/10 (deux fois 2 h 30) garde le bon décalage', () => {
  assert.equal(par('2026-10-25T02:30:00+02:00')['1h'].quand, '2026-10-25T02:30:00+01:00');
  assert.equal(par('2026-10-25T02:30:00+01:00')['1h'].quand, '2026-10-25T03:30:00+01:00');
});

test('textes de confirmation', () => {
  const m = new Date('2026-10-14T15:45:00+02:00');
  const c = par('2026-10-14T15:45:00+02:00');
  assert.equal(texteConfirmation(c['1h'], m), 'Rappel prévu dans 1 h.');
  assert.equal(texteConfirmation(c.soir, m), 'Rappel prévu ce soir à 20 h.');
  assert.equal(texteConfirmation(c.demain8, m), 'Rappel prévu demain à 8 h.');
  const tard = new Date('2026-10-14T19:45:00+02:00');
  assert.equal(texteConfirmation(par('2026-10-14T19:45:00+02:00').soir, tard), 'Rappel prévu demain à 20 h.');
  assert.equal(texteConfirmation(choixLibre('2026-10-26T14:30', m), m), 'Rappel prévu lundi 26 octobre à 14 h 30.');
  assert.equal(texteConfirmation(choixLibre('2026-11-01T08:00', m), m), 'Rappel prévu dimanche 1er novembre à 8 h.');
});

test('« Choisir… » : dans le passé ou vide, refusé ; à venir, ISO avec décalage de Paris', () => {
  const m = new Date('2026-10-14T15:45:00+02:00');
  assert.deepEqual(choixLibre('2026-10-14T15:00', m), { erreur: 'Choisis une heure à venir.' });
  assert.deepEqual(choixLibre('2026-10-14T15:45', m), { erreur: 'Choisis une heure à venir.' });
  assert.deepEqual(choixLibre('', m), { erreur: 'Choisis une heure à venir.' });
  assert.equal(choixLibre('2026-10-14T15:46', m).quand, '2026-10-14T15:46:00+02:00');
  assert.equal(choixLibre('2026-10-25T12:00', m).quand, '2026-10-25T12:00:00+01:00');
});
