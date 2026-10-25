// Mini-cadre des tests d'écran : Playwright (playwright-core 1.63.0, Chromium déjà en cache), un iPhone 14
// réglé exprès sur le fuseau de New York, le serveur de test et sa fausse API.
import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import { demarrer } from './serveur.mjs';

export { assert };

const tests = [];

export function test(nom, f, options = {}) {
  tests.push({ nom, f, options });
}

export const IPHONE = {
  viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true,
  locale: 'fr-FR', timezoneId: 'America/New_York',
};

// Messages de la console qui ne sont pas des erreurs de l'app : un échec réseau voulu (hors ligne, 401), et le script
// d'horloge que Playwright (page.clock) tente de lancer dans le cadre d'un mail, que son `sandbox` refuse
const IGNORES = [/^Failed to load resource/, /^Blocked script execution in '[^']*\/api\/mails\/corps\?/];

export async function lancer(filtre) {
  const serveur = await demarrer();
  const navigateur = await chromium.launch();
  // Le Chromium complet, lancé seulement si un test le demande : la version allégée refuse toujours les notifications
  let complet = null;
  let reussis = 0;
  let echecs = 0;
  for (const { nom, f, options } of tests) {
    if (filtre && !nom.includes(filtre)) continue;
    serveur.reinitialiser();
    const nav = options.chromiumComplet ? (complet ??= await chromium.launch({ channel: 'chromium' })) : navigateur;
    const contexte = await nav.newContext({ ...IPHONE, baseURL: serveur.origine, serviceWorkers: options.serviceWorkers ?? 'block' });
    const page = await contexte.newPage();
    const erreurs = [];
    page.on('console', (m) => {
      if (m.type() === 'error' && !IGNORES.some((r) => r.test(m.text()))) erreurs.push(m.text());
    });
    page.on('pageerror', (e) => erreurs.push(e.message));
    try {
      await f({ page, contexte, serveur });
      assert.deepEqual(erreurs, [], 'console sans erreur');
      reussis++;
      console.log(`  ok      ${nom}`);
    } catch (e) {
      echecs++;
      console.log(`  ÉCHEC   ${nom}\n          ${String(e.message).split('\n').join('\n          ')}`);
    } finally {
      await contexte.close();
    }
  }
  await navigateur.close();
  await complet?.close();
  await serveur.arreter();
  console.log(`${reussis} réussi(s), ${echecs} échec(s)`);
  return echecs === 0 ? 0 : 1;
}

// L'heure de l'iPhone, fixe (les minuteurs continuent de tourner). Avant ouvrir().
export async function heure(page, iso) {
  await page.clock.setFixedTime(new Date(iso));
}

export async function connecte(contexte, serveur) {
  await contexte.addCookies([{ name: 'jr-test', value: '1', url: serveur.origine }]);
}

export async function ouvrir(page, chemin = '/') {
  await page.goto(chemin);
  await page.waitForFunction(() => document.documentElement.dataset.pret === '1');
}

// Le texte d'un élément, espaces normalisés (textContent : sans les majuscules ajoutées par le CSS)
export async function texte(page, selecteur) {
  return ((await page.locator(selecteur).first().textContent()) ?? '').replace(/\s+/g, ' ').trim();
}

export async function attendre(condition, message, delai = 4000) {
  const fin = Date.now() + delai;
  while (!(await condition())) {
    if (Date.now() > fin) throw new Error(`Délai dépassé : ${message}`);
    await new Promise((r) => setTimeout(r, 25));
  }
}
