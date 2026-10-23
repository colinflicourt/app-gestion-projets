// Captures de tous les écrans pour la revue de finition : node tests/e2e/captures.mjs
// → tests/e2e/captures/*.png (hors git), en iPhone 14, sur la fausse API des tests.
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import { IPHONE } from './cadre.mjs';
import { demarrer } from './serveur.mjs';
import { CARTES } from './fixtures.mjs';

const DOSSIER = fileURLToPath(new URL('./captures/', import.meta.url));
const MERCREDI = '2026-10-14T15:45:00+02:00';
const go = async (p) => {
  await p.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).click();
  await p.locator('.tirage-texte').waitFor();
  await p.waitForTimeout(500);
};
const pli = async (p) => {
  await p.locator('.sous-le-pli .rang-groupe').last().scrollIntoViewIfNeeded();
  await p.waitForTimeout(300);
};
const pubs = async (p) => {
  await p.getByRole('region', { name: 'Gmail' }).getByRole('button', { name: '+ 48 pubs' }).click();
  await p.getByRole('region', { name: 'Gmail' }).scrollIntoViewIfNeeded();
  await p.waitForTimeout(300);
};
const blocMails = async (p) => {
  await p.locator('.mails-resume').scrollIntoViewIfNeeded();
  await p.waitForTimeout(300);
};
const comptesMail = async (p) => {
  await p.getByRole('heading', { name: 'Comptes mail' }).evaluate((el) => el.scrollIntoView({ block: 'start' }));
  await p.waitForTimeout(300);
};
const noter = async (p) => {
  await p.getByRole('button', { name: 'Noter quelque chose' }).click();
  await p.getByRole('dialog').waitFor();
};

// [nom, heure de l'iPhone, adresse, connecté, geste avant la capture]
const PLANCHES = [
  ['01-connexion', MERCREDI, '/', false, null],
  ['02-matin', '2026-10-14T07:32:00+02:00', '/#/aujourdhui', true, null],
  ['03-entre-deux-cours', MERCREDI, '/#/aujourdhui', true, null],
  ['04-tirage', MERCREDI, '/#/aujourdhui', true, go],
  ['05-en-td', '2026-10-14T17:10:00+02:00', '/#/aujourdhui', true, null],
  ['06-sous-le-pli', MERCREDI, '/#/aujourdhui', true, pli],
  ['07-taches', MERCREDI, '/#/taches', true, null],
  ['08-carte', MERCREDI, `/#/carte/${CARTES.apoint}`, true, null],
  ['09-noter', MERCREDI, '/#/aujourdhui', true, noter],
  ['10-agenda', MERCREDI, '/#/agenda', true, null],
  ['11-moi', MERCREDI, '/#/moi', true, null],
  ['12-mails', MERCREDI, '/#/mails', true, null],
  ['13-mails-pubs', MERCREDI, '/#/mails', true, pubs],
  ['14-aujourdhui-mails', MERCREDI, '/#/aujourdhui', true, blocMails],
  ['15-reglages-comptes-mail', MERCREDI, '/#/reglages', true, comptesMail],
];

mkdirSync(DOSSIER, { recursive: true });
const serveur = await demarrer();
const navigateur = await chromium.launch();
for (const [nom, heure, adresse, connecte, geste] of PLANCHES) {
  const contexte = await navigateur.newContext({ ...IPHONE, baseURL: serveur.origine, serviceWorkers: 'block' });
  if (connecte) await contexte.addCookies([{ name: 'jr-test', value: '1', url: serveur.origine }]);
  const page = await contexte.newPage();
  await page.clock.setFixedTime(new Date(heure));
  await page.goto(adresse);
  await page.waitForTimeout(600);
  if (geste) await geste(page);
  await page.screenshot({ path: `${DOSSIER}${nom}.png` });
  await contexte.close();
}
await navigateur.close();
await serveur.arreter();
console.log(`${PLANCHES.length} captures dans ${DOSSIER}`);
