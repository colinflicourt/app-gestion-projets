import { test, assert, connecte, ouvrir, texte } from './cadre.mjs';

test('socle : la page charge sa police et ses modules, sans erreur ni refus de la CSP', async ({ page }) => {
  await ouvrir(page, '/');
  await page.evaluate(() => document.fonts.ready);
  const chargees = await page.evaluate(() => [...document.fonts].filter((f) => f.status === 'loaded').map((f) => f.family.replaceAll('"', '')));
  assert.ok(chargees.includes('Archivo'), `Archivo chargée (vu : ${chargees.join(', ')})`);
  for (const police of ['archivo', 'martian-mono', 'permanent-marker']) {
    const r = await page.request.get(`/fonts/${police}.woff2`);
    assert.equal(r.status(), 200, police);
  }
});

// Connecté : à partir de la tâche 5, sans session, l'app affiche l'écran de connexion sans onglets
test("socle : 5 onglets et « Noter quelque chose », cibles de 44 px au moins, navigation par #/", async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await ouvrir(page, '/');
  const cibles = page.locator('#onglets a, #onglets button');
  assert.equal(await cibles.count(), 6);
  const tailles = await cibles.evaluateAll((els) => els.map((e) => {
    const r = e.getBoundingClientRect();
    return [r.width, r.height];
  }));
  for (const [largeur, hauteur] of tailles) assert.ok(largeur >= 44 && hauteur >= 44, `cible de ${largeur} × ${hauteur}`);
  await page.getByRole('button', { name: 'Noter quelque chose' }).waitFor();
  await page.getByRole('link', { name: 'Agenda' }).click();
  await page.getByRole('heading', { name: 'Agenda' }).waitFor();
  assert.equal(await texte(page, '.onglet[aria-current="page"]'), 'Agenda');
});

test('socle : manifeste servi avec son type, en plein écran, couleurs de la console', async ({ page }) => {
  const r = await page.request.get('/manifest.webmanifest');
  assert.equal(r.headers()['content-type'], 'application/manifest+json');
  const m = await r.json();
  assert.deepEqual([m.name, m.display, m.background_color, m.theme_color], ['Jarvis Jr.', 'standalone', '#121417', '#121417']);
});
