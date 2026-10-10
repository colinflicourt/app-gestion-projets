import { test } from 'node:test';
import assert from 'node:assert/strict';
import { aleaDepuis, choixPondere, phraseDuJour, phrasesDeSituation, salutation } from '../../site/js/lib/phrases.js';
import { T } from '../../site/js/lib/textes.js';

test("salutation selon l'heure de Paris", () => {
  const heures = ['2026-10-14T07:32:00+02:00', '2026-10-14T12:00:00+02:00', '2026-10-14T18:30:00+02:00', '2026-10-14T23:10:00+02:00', '2026-10-15T04:59:00+02:00'];
  assert.deepEqual(heures.map((iso) => salutation(new Date(iso))), ['Bonjour Colin.', 'Bon après-midi.', 'Bonsoir.', 'Encore debout ?', 'Encore debout ?']);
});

test('phrases de situation : lundi, journée chargée, pas de cours avant 13 h, retard ou pas, médian', () => {
  assert.deepEqual(phrasesDeSituation({ jour: '2026-10-19', nbCours: 4, premierCours: 13 * 60 + 30, nbRetard: 2, medianDans: 2 }), [
    T.phrases.lundi, T.phrases.chargee, T.phrases.pasAvant13h,
    "2 tâches en retard. Elles ne vont pas se faire seules, j'ai essayé.", 'Médian dans 2 jours. Je ne panique pas, donc toi non plus.',
  ]);
  assert.deepEqual(phrasesDeSituation({ jour: '2026-10-14', nbCours: 2, premierCours: 480, nbRetard: 0, medianDans: null }), [T.phrases.rienEnRetard]);
  assert.equal(T.phrases.enRetard(1), "1 tâche en retard. Elle ne va pas se faire seule, j'ai essayé.");
});

test('phrase du jour : même date, même phrase ; toujours une phrase possible ; plusieurs phrases sur un mois', () => {
  const faits = (jour) => ({ jour, nbCours: 2, premierCours: 480, nbRetard: 2, medianDans: 7 });
  assert.equal(phraseDuJour(faits('2026-10-14')), phraseDuJour(faits('2026-10-14')));
  const vues = new Set();
  for (let j = 1; j <= 30; j++) {
    const f = faits(`2026-11-${String(j).padStart(2, '0')}`);
    const phrase = phraseDuJour(f);
    assert.ok([...phrasesDeSituation(f), ...T.phrases.generales].includes(phrase), phrase);
    vues.add(phrase);
  }
  assert.ok(vues.size >= 3, `${vues.size} phrases différentes sur un mois`);
});

test('aléa à graine et choix pondéré', () => {
  const a = aleaDepuis('2026-10-14');
  const b = aleaDepuis('2026-10-14');
  assert.deepEqual([a(), a(), a()], [b(), b(), b()]);
  assert.deepEqual([0.2, 0.5, 0.9999].map((x) => choixPondere(['x', 'y'], [1, 3], () => x)), ['x', 'y', 'y']);
});
