import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as t from '../../site/js/lib/temps.js';

test("heure et jour de Paris, quel que soit le fuseau de la machine, heure d'hiver comprise", () => {
  assert.equal(t.heureParis(new Date('2026-10-14T13:45:00Z')), '15:45');
  assert.equal(t.jourParis(new Date('2026-10-14T22:30:00Z')), '2026-10-15');
  assert.equal(t.heureParis(new Date('2026-10-26T07:00:00Z')), '08:00');
  assert.equal(t.heureParis(new Date('2026-10-14T22:00:00Z')), '00:00');
  assert.deepEqual(t.enParis(new Date('2026-10-21T17:15:00Z')), { annee: 2026, mois: 10, jour: 21, heure: 19, minute: 15 });
});

test('jours : écart, jour de la semaine, lundi, semaine ISO (2026 a 53 semaines)', () => {
  assert.equal(t.ajouterJours('2026-10-31', 1), '2026-11-01');
  assert.equal(t.ajouterJours('2026-10-25', 1), '2026-10-26');
  assert.equal(t.joursEntre('2026-10-14', '2026-10-21'), 7);
  assert.equal(t.jourSemaine('2026-10-14'), 2);
  assert.equal(t.jourSemaine('2026-10-18'), 6);
  assert.equal(t.lundi('2026-10-14'), '2026-10-12');
  assert.equal(t.semaineIso('2026-10-14'), '2026-W42');
  assert.equal(t.semaineIso('2026-10-19'), '2026-W43');
  assert.equal(t.semaineIso('2027-01-01'), '2026-W53');
  assert.equal(t.lundiDeSemaine('2026-W43'), '2026-10-19');
  assert.equal(t.lundiDeSemaine('2026-W01'), '2025-12-29');
});

test('textes : dates, semaines, heures, durées, âges', () => {
  assert.equal(t.dateLongue('2026-10-14'), 'Mercredi 14 octobre');
  assert.equal(t.dateLongue('2026-11-01'), 'Dimanche 1er novembre');
  assert.equal(t.etiquetteJour('2026-10-14'), 'MER 14');
  assert.equal(t.dateCourte('2026-10-21'), 'mer. 21/10');
  assert.equal(t.jourMois('2026-10-06'), '06/10');
  assert.equal(t.plageSemaine('2026-10-12'), '12 au 18 octobre');
  assert.equal(t.plageSemaine('2026-10-26'), '26 octobre au 1er novembre');
  assert.equal(t.heureTexte('08:00'), '8 h');
  assert.equal(t.heureTexte('19:15'), '19 h 15');
  assert.deepEqual([8, 45, 60, 70, 140, -3].map(t.dureeTexte), ['8 min', '45 min', '1 h', '1 h 10', '2 h 20', '0 min']);
  assert.deepEqual([0.4, 12, 125, 3000].map(t.ageTexte), ["moins d'une minute", '12 min', '2 h', '2 jours']);
  assert.equal(t.minutesEntre(new Date('2026-10-14T13:45:00Z'), new Date('2026-10-14T14:30:00Z')), 45);
});

test("isoParis : une échéance saisie à Paris garde son heure, avant et après le passage à l'heure d'hiver", () => {
  assert.equal(t.isoParis('2026-10-20', '18:00'), '2026-10-20T18:00:00+02:00');
  assert.equal(t.isoParis('2026-10-27', '08:30'), '2026-10-27T08:30:00+01:00');
  assert.equal(new Date(t.isoParis('2026-10-27', '18:00')).toISOString(), '2026-10-27T17:00:00.000Z');
});
