import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { REGLES, creationCarte, estEclair, gainCarte, gainEtape, payeApres, progression, seuilNiveau } from '../../site/js/lib/xp.js';

// Même table de test que tests/unitaires/test_xp.php
const TABLE = {
  carte: 30, bonus_urgent: 20, bonus_echeance: 10, carte_eclair: 5, eclair_secondes: 600, plafond_eclairs: 1000,
  etape: 5, plafond_etapes: 1000, pas_niveau: 100, grades: { stagiaire: 0, assistant: 1000, bras_droit: 4000, majordome: 10000 },
};
const VIDE = { etapes: 0, eclairs: 0 };
const trello = (creation, lettre = 'a') => creation.toString(16).padStart(8, '0') + lettre.repeat(16);
const VIEILLE = trello(1788000000);
const le = (iso) => new Date(iso);

test("xp : REGLES est la copie exacte de JR_XP_REGLES (lu par php)", () => {
  const racine = fileURLToPath(new URL('../../', import.meta.url));
  const sortie = execFileSync('php', ['-r', 'require "serveur/lib/xp.php"; echo json_encode(JR_XP_REGLES);'], { cwd: racine, encoding: 'utf8' });
  assert.deepEqual(REGLES, JSON.parse(sortie));
});

test('xp : creationCarte lit les 8 premiers caractères hexadécimaux, sinon null', () => {
  assert.equal(creationCarte(trello(1788000000))?.getTime(), 1788000000000);
  assert.equal(creationCarte(`${VIEILLE.slice(0, 23)}`), null);
  assert.equal(creationCarte('local-12345'), null);
  assert.equal(creationCarte('6a92b70g' + 'a'.repeat(16)), null);
  assert.equal(creationCarte(''), null);
});

test('xp : gainCarte, 30 plus 20 Urgent plus 10 échéance tenue', () => {
  const quand = le('2026-10-20T08:00:00Z');
  assert.equal(gainCarte({ id: VIEILLE, urgent: false, echeance: null }, quand, VIDE, TABLE), 30);
  assert.equal(gainCarte({ id: VIEILLE, urgent: true, echeance: null }, quand, VIDE, TABLE), 50);
  assert.equal(gainCarte({ id: VIEILLE, urgent: false, echeance: '2026-10-21T19:15:00+02:00' }, quand, VIDE, TABLE), 40);
  assert.equal(gainCarte({ id: VIEILLE, urgent: true, echeance: '2026-10-21T19:15:00+02:00' }, quand, VIDE, TABLE), 60);
  assert.equal(gainCarte({ id: VIEILLE, urgent: false, echeance: '2026-10-19T19:15:00+02:00' }, quand, VIDE, TABLE), 30);
});

test("xp : échéance à 18 h Paris comparée au jour, sinon à la minute", () => {
  const carte = (echeance) => ({ id: VIEILLE, urgent: false, echeance });
  // 20 h 00 à Paris le 14 : le jour de l'échéance sans heure, c'est encore dans les temps
  assert.equal(gainCarte(carte('2026-10-14T18:00:00+02:00'), le('2026-10-14T18:00:00Z'), VIDE, TABLE), 40);
  assert.equal(gainCarte(carte('2026-10-14T18:00:00+02:00'), le('2026-10-14T22:30:00Z'), VIDE, TABLE), 30);
  // Une heure précise : à la minute
  assert.equal(gainCarte(carte('2026-10-14T19:15:00+02:00'), le('2026-10-14T17:15:59Z'), VIDE, TABLE), 40);
  assert.equal(gainCarte(carte('2026-10-14T19:15:00+02:00'), le('2026-10-14T17:16:00Z'), VIDE, TABLE), 30);
  // Passage à l'heure d'hiver : 18 h le 25/10 est à +01:00
  assert.equal(gainCarte(carte('2026-10-25T18:00:00+01:00'), le('2026-10-25T22:00:00Z'), VIDE, TABLE), 40);
});

test('xp : carte éclair, 9 min 59 s rapporte 5, 10 min 00 s rapporte 30, bonus perdus', () => {
  const creation = 1790000000;
  const carte = trello(creation, 'b');
  const apres = (s) => new Date((creation + s) * 1000);
  assert.equal(gainCarte({ id: carte, urgent: false, echeance: null }, apres(599), VIDE, TABLE), 5);
  assert.equal(gainCarte({ id: carte, urgent: false, echeance: null }, apres(600), VIDE, TABLE), 30);
  assert.equal(gainCarte({ id: carte, urgent: true, echeance: '2099-01-01T18:00:00+01:00' }, apres(30), VIDE, TABLE), 5);
  assert.equal(gainCarte({ id: carte, urgent: true, echeance: null }, apres(600), VIDE, TABLE), 50);
});

test("xp : Focus 5, geste antérieur à la création (carte créée puis terminée hors ligne) : carte éclair, jamais négatif", () => {
  const creation = 1790000000;
  const carte = trello(creation, 'c');
  assert.equal(gainCarte({ id: carte, urgent: false, echeance: null }, new Date((creation - 3600) * 1000), VIDE, TABLE), 5);
  assert.equal(gainCarte({ id: carte, urgent: true, echeance: null }, new Date((creation - 1) * 1000), VIDE, TABLE), 5);
});

test("xp : Focus 5, identifiant local-… (carte pas encore créée chez Trello) : estimée carte éclair", () => {
  const quand = le('2026-10-20T08:00:00Z');
  assert.equal(estEclair('local-12345', quand), true);
  assert.equal(gainCarte({ id: 'local-12345', urgent: true, echeance: null }, quand, VIDE, TABLE), 5);
  assert.equal(gainCarte({ id: 'local-12345', urgent: false, echeance: null }, quand), REGLES.carte_eclair);
});

test("xp : plafonds du jour (décision 12), la 3e carte éclair et la 11e étape ne rapportent rien", () => {
  const eclair = { id: 'local-1', urgent: false, echeance: null };
  const quand = le('2026-10-20T08:00:00Z');
  assert.equal(gainCarte(eclair, quand, { etapes: 0, eclairs: 1 }), 5);
  assert.equal(gainCarte(eclair, quand, { etapes: 0, eclairs: 2 }), 0);
  assert.equal(gainCarte(eclair, quand, { etapes: 10, eclairs: 3 }), 0);
  // Une vraie carte n'est jamais plafonnée
  assert.equal(gainCarte({ id: VIEILLE, urgent: false, echeance: null }, quand, { etapes: 10, eclairs: 2 }), 30);
  assert.equal(gainEtape({ etapes: 9, eclairs: 2 }), 5);
  assert.equal(gainEtape({ etapes: 10, eclairs: 0 }), 0);
  assert.equal(gainEtape(), 5);
  assert.equal(gainEtape({ etapes: 999, eclairs: 0 }, TABLE), 5);
});

test('xp : seuilNiveau et progression (barème de test, puis barème réel)', () => {
  assert.deepEqual([1, 2, 3, 13].map((n) => seuilNiveau(n, TABLE)), [0, 100, 300, 7800]);
  assert.deepEqual(progression(420, TABLE), { niveau: 3, dansNiveau: 120, tailleNiveau: 300 });
  assert.deepEqual(progression(0, TABLE), { niveau: 1, dansNiveau: 0, tailleNiveau: 100 });
  assert.deepEqual(progression(99, TABLE), { niveau: 1, dansNiveau: 99, tailleNiveau: 100 });
  assert.deepEqual(progression(7799, TABLE), { niveau: 12, dansNiveau: 1199, tailleNiveau: 1200 });
  assert.deepEqual(progression(7800, TABLE), { niveau: 13, dansNiveau: 0, tailleNiveau: 1300 });
  assert.deepEqual(progression(420), { niveau: 2, dansNiveau: 260, tailleNiveau: 320 });
  assert.deepEqual([seuilNiveau(2), seuilNiveau(3), seuilNiveau(13)], [160, 480, 12480]);
  assert.equal(progression(159).niveau, 1);
  assert.equal(progression(12479).niveau, 12);
  assert.equal(progression(-5).niveau, 1);
});

test("xp : payeApres ajoute les gestes de la file faits aujourd'hui (Paris), sans passer sous 0", () => {
  const e = (genre, n, faitLe) => ({ route: 'x', fait_le: faitLe, local: { xp: { genre, n } } });
  const jour = '2026-10-20';
  const file = [
    e('etapes', 1, '2026-10-20T08:00:00Z'), e('etapes', 1, '2026-10-20T08:01:00Z'), e('eclairs', 1, '2026-10-20T08:02:00Z'),
    e('etapes', 1, '2026-10-19T08:00:00Z'), e('etapes', 1, undefined), { route: 'taches/deplacer', fait_le: '2026-10-20T08:00:00Z', local: {} },
  ];
  assert.deepEqual(payeApres({ etapes: 3, eclairs: 0 }, file, jour), { etapes: 6, eclairs: 1 });
  assert.deepEqual(payeApres({ etapes: 0, eclairs: 0 }, [e('etapes', -1, '2026-10-20T08:00:00Z')], jour), { etapes: 0, eclairs: 0 });
  assert.deepEqual(payeApres(null, [], jour), { etapes: 0, eclairs: 0 });
  // 23 h 50 à Paris le 19 (21 h 50 UTC) n'est pas le 20
  assert.deepEqual(payeApres(VIDE, [e('etapes', 1, '2026-10-19T21:50:00Z')], jour), VIDE);
  assert.deepEqual(payeApres(VIDE, [e('etapes', 1, '2026-10-19T22:10:00Z')], jour), { etapes: 1, eclairs: 0 });
});
