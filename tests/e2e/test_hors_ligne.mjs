import { test, assert, heure, connecte, ouvrir } from './cadre.mjs';
import { CARTES } from './fixtures.mjs';

test("hors ligne : après une première visite, l'app s'ouvre sans réseau, avec sa copie", async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
  await ouvrir(page, '/#/taches');
  await page.getByText('9 CARTES').waitFor();
  await page.waitForFunction(() => Boolean(navigator.serviceWorker?.controller));
  await contexte.setOffline(true);
  await page.reload();
  await page.getByText('9 CARTES').waitFor();
  await page.getByText(/^Hors ligne · données d'il y a/).waitFor();
}, { serviceWorkers: 'allow' });

test('tous les écrans : rien ne déborde à 375, 390 et 768 px, feuille « Noter » comprise', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
  await ouvrir(page, '/#/aujourdhui');
  await page.locator('.tranche-compte').first().waitFor();
  const debords = () => page.evaluate(() => [...document.querySelectorAll('#app *')]
    .filter((e) => e.getClientRects().length > 0 && e.getBoundingClientRect().right > window.innerWidth + 1)
    .map((e) => `${e.tagName.toLowerCase()}.${e.className}`));
  for (const largeur of [375, 390, 768]) {
    await page.setViewportSize({ width: largeur, height: 844 });
    for (const route of ['#/aujourdhui', '#/agenda', '#/taches', `#/carte/${CARTES.apoint}`, '#/moi']) {
      await page.evaluate((r) => { location.hash = r; }, route);
      await page.waitForTimeout(200);
      assert.deepEqual(await debords(), [], `${route} à ${largeur} px`);
    }
    await page.getByRole('button', { name: 'Noter quelque chose' }).click();
    await page.getByRole('dialog', { name: 'Noter' }).waitFor();
    assert.deepEqual(await debords(), [], `feuille « Noter » à ${largeur} px`);
    await page.keyboard.press('Escape');
  }
});
