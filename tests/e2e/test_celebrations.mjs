import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';
import { CARTES, xpDeBase } from './fixtures.mjs';

const MERCREDI = '2026-10-14T15:45:00+02:00';
const REPLIQUES = [
  "Je l'encadre au-dessus de la cafetière.",
  "Je n'ai rien fait, mais je prends le crédit.",
  'Les autres domaines sont jaloux. Ils te regardent.',
  "Continue, j'ai parié sur toi. Pas gros, mais quand même.",
  "Noté. Je le ressortirai le jour où tu diras que tu n'avances pas.",
];
const CAPTURES = fileURLToPath(new URL('./captures/', import.meta.url));
const HIER = '2026-10-13T20:00:00+02:00';

const niveau = (domaine, n, emis = HIER) => ({ cle: `niveau:${domaine}:${n}`, type: 'niveau', domaine, niveau: n, date: emis, emis_le: emis });
const grade = (g, emis = HIER) => ({ cle: `grade:${g}`, type: 'grade', grade: g, date: emis, emis_le: emis });
const joker = (jour, emis) => ({ cle: `joker:${jour}`, type: 'joker', date: jour, emis_le: emis });

async function preparer(page, contexte, serveur, evenements, xp = null) {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  if (xp) serveur.etat.donnees.xp = xp;
  serveur.etat.donnees.xp.evenements = evenements;
}

// Ce que l'iPhone a déjà célébré, écrit avant le premier chargement de l'app (page statique de la même origine)
async function semer(page, memo) {
  await page.goto('/confidentialite.html');
  await page.evaluate(async (m) => (await import('/js/stockage.js')).ecrire('donnees', 'celebrations', m), memo);
}

const celebration = (page) => page.locator('#celebration .celebration');
const titre = (page) => texte(page, '#celebration .celebration-titre');
const fermer = (page) => page.getByRole('button', { name: 'Fermer' }).click();
const vu = (page) => page.evaluate(async () => (await import('/js/stockage.js')).lire('donnees', 'celebrations'));
const donneesChargees = (serveur, n = 1) => attendre(() => serveur.appels('donnees').length >= n, 'données chargées');

async function capturer(page, nom) {
  if (!process.env.CAPTURES) return;
  mkdirSync(CAPTURES, { recursive: true });
  await page.waitForTimeout(700);
  await page.screenshot({ path: `${CAPTURES}${nom}.png` });
}

test('célébration : un passage de niveau en plein écran, une réplique, annoncé une fois, se ferme au toucher, pas de répétition au rechargement', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, [niveau('etudes', 3)]);
  await ouvrir(page, '/#/aujourdhui');
  await celebration(page).waitFor();
  assert.equal(await titre(page), 'Niveau 3 en Études');
  assert.ok(REPLIQUES.includes(await texte(page, '#celebration .celebration-replique')));
  assert.equal(await page.locator('#celebration').getAttribute('aria-live'), 'polite');
  assert.ok(await page.locator('#principal').evaluate((e) => e.inert), "l'app derrière est inerte");
  // marquerVus est écrit AVANT l'affichage
  const memo = await vu(page);
  assert.deepEqual([memo.emis_le_max, memo.cles, memo.total_vu], [HIER, ['niveau:etudes:3'], 2340]);
  const boite = await celebration(page).boundingBox();
  assert.deepEqual([boite.width, boite.height], [390, 844]);
  await capturer(page, '18-celebration-niveau');
  await fermer(page);
  await celebration(page).waitFor({ state: 'detached' });
  assert.ok(!(await page.locator('#principal').evaluate((e) => e.inert)));
  await page.reload();
  await page.waitForFunction(() => document.documentElement.dataset.pret === '1');
  await donneesChargees(serveur, 2);
  await page.waitForTimeout(300);
  assert.equal(await celebration(page).count(), 0);
});

test("célébration : une promotion, ses deux phrases, puis le niveau qui l'accompagne (promotion d'abord)", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, [niveau('qyris', 2), grade('assistant')]);
  await ouvrir(page, '/#/aujourdhui');
  await celebration(page).waitFor();
  assert.equal(await titre(page), 'Junior passe Assistant. Il réclame déjà une augmentation.');
  assert.equal(await texte(page, '#celebration .celebration-replique'), 'Assistant. Je me suis acheté une cravate avec ton XP.');
  await capturer(page, '19-celebration-promotion');
  await fermer(page);
  await attendre(async () => (await celebration(page).count()) === 1 && (await titre(page)) === 'Niveau 2 en QYRIS', 'le niveau vient après la promotion');
  await fermer(page);
  await celebration(page).waitFor({ state: 'detached' });
});

test('célébration : à la première ouverture, plus de deux événements donnent un seul récapitulatif', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, [niveau('etudes', 2), niveau('etudes', 3), niveau('asso', 2)], xpDeBase({ qyris: 0, asso: 160, etudes: 480, perso: 0 }));
  await ouvrir(page, '/#/aujourdhui');
  await celebration(page).waitFor();
  assert.equal(await titre(page), 'Pendant ton absence : +640 XP. Études niveau 3, Asso niveau 2.');
  await capturer(page, '20-celebration-recapitulatif');
  await fermer(page);
  await celebration(page).waitFor({ state: 'detached' });
  assert.equal((await vu(page)).cles.length, 3);
  await page.waitForTimeout(300);
  assert.equal(await celebration(page).count(), 0);
});

test("célébration : une promotion s'ajoute en tête du récapitulatif, et seule l'XP depuis la dernière vue compte", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, [niveau('etudes', 2), niveau('etudes', 3), grade('assistant')], xpDeBase({ qyris: 0, asso: 160, etudes: 480, perso: 0 }));
  await semer(page, { emis_le_max: '2026-10-01T00:00:00+02:00', cles: [], total_vu: 100 });
  await ouvrir(page, '/#/moi');
  await celebration(page).waitFor();
  assert.equal(await titre(page), 'Pendant ton absence : +540 XP. Junior passe Assistant. Études niveau 3.');
});

test("célébration : un joker en bandeau discret qui ne bloque pas l'app, émis après son jour", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, [joker('2026-10-12', '2026-10-14T08:00:00+02:00')]);
  await ouvrir(page, '/#/aujourdhui');
  await celebration(page).waitFor();
  assert.ok((await celebration(page).getAttribute('class')).includes('celebration-joker'));
  assert.equal(await titre(page), 'Joker utilisé. La série tient, ta discipline un peu moins.');
  assert.ok(!(await page.locator('#principal').evaluate((e) => e.inert)), "le bandeau ne bloque pas l'app");
  const boite = await celebration(page).boundingBox();
  assert.ok(boite.height < 150, `bandeau discret (${boite.height} px)`);
  await capturer(page, '21-celebration-joker');
  await page.keyboard.press('Escape');
  await celebration(page).waitFor({ state: 'detached' });
});

test('célébration : Focus 1, un joker de date ancienne mais émis après le dernier événement vu est célébré', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, [grade('assistant'), joker('2026-10-12', '2026-10-14T08:00:00+02:00')]);
  await semer(page, { emis_le_max: HIER, cles: ['grade:assistant'], total_vu: 2340 });
  await ouvrir(page, '/#/aujourdhui');
  await celebration(page).waitFor();
  assert.equal(await titre(page), 'Joker utilisé. La série tient, ta discipline un peu moins.');
});

test("célébration : Focus 2, une clé déjà célébrée et redatée plus tard n'est pas recélébrée, un niveau neuf l'est", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, [niveau('etudes', 3, '2026-10-14T14:00:00+02:00'), niveau('etudes', 4, '2026-10-14T14:30:00+02:00')]);
  await semer(page, { emis_le_max: '2026-10-14T09:00:00+02:00', cles: ['niveau:etudes:3'], total_vu: 2340 });
  await ouvrir(page, '/#/aujourdhui');
  await celebration(page).waitFor();
  assert.equal(await titre(page), 'Niveau 4 en Études');
  await fermer(page);
  await celebration(page).waitFor({ state: 'detached' });
  await page.waitForTimeout(300);
  assert.equal(await celebration(page).count(), 0);
});

test('célébration : fermeture au clavier (Échap), le focus revient où il était', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, [niveau('perso', 2)]);
  await ouvrir(page, '/#/aujourdhui');
  await celebration(page).waitFor();
  assert.equal(await page.evaluate(() => document.activeElement?.getAttribute('aria-label')), 'Fermer');
  await page.keyboard.press('Escape');
  await celebration(page).waitFor({ state: 'detached' });
  assert.notEqual(await page.evaluate(() => document.activeElement?.getAttribute('aria-label')), 'Fermer');
});

test('célébration : mouvement réduit, aucune animation', async ({ page, contexte, serveur }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await preparer(page, contexte, serveur, [grade('assistant')]);
  await ouvrir(page, '/#/aujourdhui');
  await celebration(page).waitFor();
  for (const sel of ['#celebration .celebration', '#celebration .celebration-titre', '#celebration .celebration-replique']) {
    assert.equal(await page.locator(sel).evaluate((e) => getComputedStyle(e).animationName), 'none', sel);
  }
});

test('célébration : sans mouvement réduit, le titre est animé', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, [grade('assistant')]);
  await ouvrir(page, '/#/aujourdhui');
  await celebration(page).waitFor();
  assert.notEqual(await page.locator('#celebration .celebration-titre').evaluate((e) => getComputedStyle(e).animationName), 'none');
});

test('célébration : un niveau gagné en terminant une carte est célébré à la réponse du serveur, une seule fois', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, []);
  await semer(page, { emis_le_max: '2026-10-14T00:00:00+02:00', cles: [], total_vu: 2340 });
  await ouvrir(page, `/#/carte/${CARTES.apoint}`);
  await donneesChargees(serveur);
  serveur.etat.donnees.xp.evenements = [niveau('etudes', 4, '2026-10-14T15:45:00+02:00')];
  await page.getByRole('button', { name: 'Terminer' }).click();
  await celebration(page).waitFor();
  assert.equal(await titre(page), 'Niveau 4 en Études');
  await fermer(page);
  await celebration(page).waitFor({ state: 'detached' });
  await page.waitForTimeout(300);
  assert.equal(await celebration(page).count(), 0);
});

// Une carte créée 5 minutes avant l'heure fixe des tests : la terminer est une carte éclair
const MINUTES_AVANT = (min) => Math.floor((Date.parse(MERCREDI) - min * 60000) / 1000).toString(16).padStart(8, '0') + '0'.repeat(16);

test("Annuler une carte éclair déjà répondue : l'estimation des éclairs payés baisse tout de suite (l'annulation attend le réseau)", async ({ page, contexte, serveur }) => {
  const cartes = serveur.etat.donnees.trello.cartes;
  const a = cartes.find((c) => c.id === CARTES.median);
  const b = cartes.find((c) => c.id === CARTES.tp);
  a.id = `${MINUTES_AVANT(5).slice(0, 23)}4`;
  b.id = `${MINUTES_AVANT(5).slice(0, 23)}6`;
  await preparer(page, contexte, serveur, []);
  serveur.etat.donnees.xp.aujourdhui_paye = { etapes: 0, eclairs: 1 };
  await ouvrir(page, `/#/carte/${a.id}`);
  await page.getByRole('button', { name: 'Terminer' }).click();
  await page.getByText('Carte terminée. +5 XP Études.', { exact: true }).waitFor();
  await attendre(() => serveur.etat.donnees.xp.aujourdhui_paye.eclairs === 2, 'éclair payé côté serveur');
  serveur.etat.horsService = true;
  await page.getByRole('button', { name: 'Annuler' }).click();
  await page.locator('#statut').waitFor();
  // Plafond de 2 : sans l'ajustement, l'iPhone croirait encore 2 éclairs payés et estimerait 0
  await page.evaluate((hash) => { location.hash = hash; }, `#/carte/${b.id}`);
  await page.getByRole('button', { name: 'Terminer' }).click();
  await page.getByText('Carte terminée. +5 XP Études.', { exact: true }).waitFor();
});
