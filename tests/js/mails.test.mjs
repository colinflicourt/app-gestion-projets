import { test } from 'node:test';
import assert from 'node:assert/strict';
import { heureMail, resumeMails, etatSection, deplacerCorrections, dateComplete, taillePiece, marquerLu } from '../../site/js/lib/mails.js';

// Le mercredi 14 octobre 2026, 15 h 45 à Paris
const MAINTENANT = new Date('2026-10-14T13:45:00Z');

test("heureMail : aujourd'hui « 14 h 32 », « 14 h » pile, « 9 h 05 », minuit passé « 0 h 30 »", () => {
  assert.equal(heureMail('2026-10-14T12:32:00Z', MAINTENANT), '14 h 32');
  assert.equal(heureMail('2026-10-14T12:00:00Z', MAINTENANT), '14 h');
  assert.equal(heureMail('2026-10-14T07:05:00Z', MAINTENANT), '9 h 05');
  assert.equal(heureMail('2026-10-13T22:30:00Z', MAINTENANT), '0 h 30');
});

test('heureMail : hier (même à 0 h 30), cette semaine « lun. 12/10 », autre année « 19/10/2025 »', () => {
  assert.equal(heureMail('2026-10-13T08:00:00Z', MAINTENANT), 'hier');
  assert.equal(heureMail('2026-10-12T22:30:00Z', new Date('2026-10-14T05:00:00Z')), 'hier');
  assert.equal(heureMail('2026-10-12T09:00:00Z', MAINTENANT), 'lun. 12/10');
  assert.equal(heureMail('2026-01-02T09:00:00Z', MAINTENANT), 'ven. 02/01');
  assert.equal(heureMail('2025-10-19T09:00:00Z', MAINTENANT), '19/10/2025');
});

test("heureMail : heure d'hiver du 25/10/2026 (UTC+1 après 3 h) et passage à minuit de Paris", () => {
  const dimanche = new Date('2026-10-25T14:00:00Z');
  assert.equal(heureMail('2026-10-25T12:32:00Z', dimanche), '13 h 32');
  assert.equal(heureMail('2026-10-24T22:30:00Z', dimanche), '0 h 30');
  assert.equal(heureMail('2026-10-24T21:30:00Z', dimanche), 'hier');
  assert.equal(heureMail('2026-10-25T23:30:00Z', new Date('2026-10-26T09:00:00Z')), '0 h 30');
});

const resume = (utc, qyris, gmail, icloud) => ({ total: utc + qyris + gmail + icloud, par_section: { utc, qyris, gmail, icloud } });

test('resumeMails : 0, 1, plusieurs, sections à 0 omises, ordre UTC QYRIS Gmail iCloud', () => {
  assert.equal(resumeMails(resume(0, 0, 0, 0)), 'Boîtes à jour.');
  assert.equal(resumeMails(resume(1, 0, 0, 0)), '1 non lu · UTC 1');
  assert.equal(resumeMails(resume(2, 1, 0, 0)), '3 non lus · UTC 2 · QYRIS 1');
  assert.equal(resumeMails(resume(0, 0, 2, 4)), '6 non lus · Gmail 2 · iCloud 4');
  assert.equal(resumeMails(resume(1, 1, 1, 1)), '4 non lus · UTC 1 · QYRIS 1 · Gmail 1 · iCloud 1');
  assert.equal(resumeMails(null), null);
});

const comptes = (o = {}) => ({
  utc: { recupere_le: null, erreur: null },  gmail: { recupere_le: null, erreur: null }, icloud: { recupere_le: null, erreur: null },
  colin: { recupere_le: null, erreur: null }, ...o,
});

test("etatSection : UTC suit son compte utc, Gmail le sien, QYRIS suit colin, une boîte sans erreur n'a aucun avis", () => {
  assert.deepEqual(etatSection('utc', comptes()), []);
  assert.deepEqual(etatSection('utc', comptes({ utc: { erreur: 'refus' } })), [{ compte: 'utc', code: 'refus' }]);
  assert.deepEqual(etatSection('utc', comptes({ gmail: { erreur: 'refus' } })), []);
  assert.deepEqual(etatSection('gmail', comptes({ utc: { erreur: 'refus' } })), []);
  assert.deepEqual(etatSection('gmail', comptes({ gmail: { erreur: 'non_connecte' } })), [{ compte: 'gmail', code: 'non_connecte' }]);
  assert.deepEqual(etatSection('icloud', comptes({ icloud: { erreur: 'delai' } })), [{ compte: 'icloud', code: 'delai' }]);
  assert.deepEqual(etatSection('qyris', comptes({ colin: { erreur: 'non_connecte' } })), [{ compte: 'colin', code: 'non_connecte' }]);
  assert.deepEqual(etatSection('qyris', comptes({ colin: { erreur: 'injoignable' } })), [{ compte: 'colin', code: 'injoignable' }]);
  assert.deepEqual(etatSection('qyris', comptes({ gmail: { erreur: 'refus' } })), []);
  assert.deepEqual(etatSection('gmail', null), []);
});

const mail = (uid, adresse, nonLu, autres = {}) => ({ uid, compte: 'gmail', de_adresse: adresse, non_lu: nonLu, signes_pub: [], ...autres });

test('deplacerCorrections : « pub » déplace dans les pubs, « afficher » en sort, compteurs ajustés, UTC jamais touchée', () => {
  const sections = [
    { section: 'gmail', non_lus: 2, mails: [mail(1, 'a@x.fr', true), mail(2, 'b@x.fr', true), mail(3, 'a@x.fr', false)], pubs: [mail(4, 'c@x.fr', true, { signes_pub: ['list-unsubscribe'] })], pubs_total: 30 },
    { section: 'utc', non_lus: 1, mails: [mail(5, 'a@x.fr', true)], pubs: [], pubs_total: 0 },
  ];
  const copie = structuredClone(sections);
  const r = deplacerCorrections(sections, new Map([['a@x.fr', 'pub'], ['c@x.fr', 'afficher']]));
  assert.deepEqual(sections, copie, "l'entrée n'est pas modifiée");
  assert.deepEqual(r[0].mails.map((m) => m.uid), [2, 4]);
  assert.deepEqual(r[0].pubs.map((m) => m.uid), [1, 3]);
  assert.equal(r[0].non_lus, 2);
  assert.equal(r[0].pubs_total, 31);
  assert.deepEqual(r[1].mails.map((m) => m.uid), [5]);
  assert.equal(r[1].non_lus, 1);
  const encore = deplacerCorrections(r, new Map([['a@x.fr', 'pub'], ['c@x.fr', 'afficher']]));
  assert.deepEqual(encore, r, 'appliquer deux fois ne change plus rien');
});

test('deplacerCorrections : les mails restent triés du plus récent au plus ancien', () => {
  const m = (uid, date, nonLu = false) => mail(uid, 'z@x.fr', nonLu, { date });
  const sections = [{ section: 'gmail', non_lus: 0, mails: [m(1, '2026-10-14T10:00:00Z'), m(3, '2026-10-12T10:00:00Z')], pubs: [m(2, '2026-10-13T10:00:00Z')], pubs_total: 1 }];
  const r = deplacerCorrections(sections, new Map([['z@x.fr', 'afficher']]));
  assert.deepEqual(r[0].mails.map((x) => x.uid), [1, 2, 3]);
  assert.equal(r[0].pubs_total, 0);
});

test('dateComplete : « lun. 19/10 à 14 h 32 » cette année, avec l\'année sinon, heure d\'hiver du 25/10/2026', () => {
  assert.equal(dateComplete('2026-10-19T12:32:00Z', MAINTENANT), 'lun. 19/10 à 14 h 32');
  assert.equal(dateComplete('2025-10-20T12:05:00Z', MAINTENANT), 'lun. 20/10/2025 à 14 h 05');
  assert.equal(dateComplete('2026-10-25T12:32:00Z', MAINTENANT), 'dim. 25/10 à 13 h 32');
  assert.equal(dateComplete('2026-10-25T00:30:00Z', MAINTENANT), 'dim. 25/10 à 2 h 30');
  assert.equal(dateComplete('2026-10-14T12:00:00Z', MAINTENANT), 'mer. 14/10 à 14 h');
});

test('taillePiece : 800 o, 350 Ko, 1,2 Mo, 12 Mo', () => {
  assert.equal(taillePiece(800), '800 o');
  assert.equal(taillePiece(358400), '350 Ko');
  assert.equal(taillePiece(1258291), '1,2 Mo');
  assert.equal(taillePiece(12582912), '12 Mo');
  assert.equal(taillePiece(0), '0 o');
  assert.equal(taillePiece(1048575), '1 Mo');
});

test('marquerLu : le mail passe lu, le compteur de sa section baisse, une pub ou un mail déjà lu ne change rien', () => {
  const m = (uid, non_lu) => ({ compte: 'gmail', uid, non_lu });
  const sections = [{ section: 'gmail', non_lus: 2, mails: [m(1, true), m(2, true), m(3, false)], pubs: [m(4, true)], pubs_total: 1 }];
  const a = marquerLu(sections, 'gmail', 2);
  assert.equal(a.change, true);
  assert.equal(a.section, 'gmail');
  assert.equal(a.sections[0].non_lus, 1);
  assert.deepEqual(a.sections[0].mails.map((x) => x.non_lu), [true, false, false]);
  assert.equal(sections[0].non_lus, 2, 'la copie reçue n\'est pas modifiée');
  assert.equal(marquerLu(sections, 'gmail', 3).change, false);
  assert.equal(marquerLu(sections, 'gmail', 99).change, false);
  const p = marquerLu(sections, 'gmail', 4);
  assert.equal(p.change, true);
  assert.equal(p.sections[0].non_lus, 2);
  assert.equal(p.sections[0].pubs[0].non_lu, false);
});
