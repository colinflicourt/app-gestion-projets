import { test, assert, heure, connecte, ouvrir, attendre, texte } from './cadre.mjs';

async function preparer(page, contexte, serveur, chemin) {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
  await ouvrir(page, chemin);
  await attendre(() => serveur.appels('donnees').length > 0, 'données demandées');
}

test("capture : titre, Études, Urgent et échéance ; confirmation ; la carte part et s'affiche dans Tâches", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/aujourdhui');
  await page.getByRole('button', { name: 'Noter quelque chose' }).click();
  const feuille = page.getByRole('dialog', { name: 'Noter' });
  await feuille.waitFor();
  await feuille.getByLabel('Une tâche, un rappel').fill('  Réviser le   chapitre 3  ');
  await feuille.getByRole('button', { name: 'Études' }).click();
  await feuille.getByRole('button', { name: 'Urgent' }).click();
  await feuille.getByLabel('Échéance').fill('2026-10-20');
  await feuille.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText('Ajouté dans Études · À faire. Jarvis le verra à ta prochaine session.').waitFor();
  assert.equal(await page.getByRole('dialog').count(), 0);
  await attendre(() => serveur.appels('taches/ajouter').length === 1, 'capture reçue');
  const { corps } = serveur.appels('taches/ajouter')[0];
  assert.deepEqual([corps.domaine, corps.titre, corps.urgent, corps.echeance], ['etudes', 'Réviser le chapitre 3', true, '2026-10-20']);
  await page.getByRole('link', { name: 'Tâches' }).click();
  await page.getByText('Réviser le chapitre 3').waitFor();
});

test("capture : sans titre ou sans domaine, un message et rien n'est envoyé ; Échap ferme la feuille", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/aujourdhui');
  await page.getByRole('button', { name: 'Noter quelque chose' }).click();
  await page.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText('Il me faut un titre (300 caractères au plus).').waitFor();
  await page.getByLabel('Une tâche, un rappel').fill('Appeler Sébastien');
  await page.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText('Choisis un domaine.').waitFor();
  await page.keyboard.press('Escape');
  assert.equal(await page.getByRole('dialog').count(), 0);
  assert.equal(serveur.appels('taches/ajouter').length, 0);
});

test('capture hors ligne : « Pas de réseau… », carte en attente, puis une seule carte au retour du réseau, même si la réponse se perd', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/taches/perso');
  await page.getByText('3 CARTES').waitFor();
  await contexte.setOffline(true);
  await page.getByRole('button', { name: 'Noter quelque chose' }).click();
  const feuille = page.getByRole('dialog', { name: 'Noter' });
  assert.equal(await feuille.getByRole('button', { name: 'Perso' }).getAttribute('aria-pressed'), 'true');
  await feuille.getByLabel('Une tâche, un rappel').fill('Dentiste');
  await feuille.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText("Pas de réseau. Je l'envoie dès que ça revient.").waitFor();
  await page.getByText('Dentiste').waitFor();
  await page.getByText('en attente du réseau').waitFor();
  serveur.etat.perdre = 'taches/ajouter';
  await contexte.setOffline(false);
  await attendre(() => serveur.appels('taches/ajouter').length >= 1, 'premier envoi');
  // Chromium renvoie parfois seul une requête restée sans réponse ; sinon, c'est la file de l'app qui renvoie
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await attendre(() => serveur.appels('taches/ajouter').length >= 2, 'renvoi');
  assert.equal(new Set(serveur.appels('taches/ajouter').map((a) => a.corps.id_client)).size, 1, 'même id_client à chaque envoi');
  await attendre(async () => !(await page.locator('#statut').isVisible()), 'plus rien en attente');
  assert.equal(serveur.etat.donnees.trello.cartes.filter((c) => c.titre === 'Dentiste').length, 1);
  assert.equal(await page.getByText('Dentiste').count(), 1);
});

async function ouvrirFeuille(page) {
  await page.getByRole('button', { name: 'Noter quelque chose' }).click();
  const feuille = page.getByRole('dialog', { name: 'Noter' });
  await feuille.waitFor();
  return feuille;
}

test('capture : Tâche par défaut, bascule vers Rappel (aria-pressed), les 4 choix, « Dans 1 h » par défaut', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/aujourdhui');
  const feuille = await ouvrirFeuille(page);
  assert.equal(await feuille.getByRole('button', { name: 'Tâche' }).getAttribute('aria-pressed'), 'true');
  assert.equal(await feuille.getByRole('button', { name: 'Rappel' }).getAttribute('aria-pressed'), 'false');
  await feuille.getByRole('button', { name: 'Rappel' }).click();
  assert.equal(await feuille.getByRole('button', { name: 'Rappel' }).getAttribute('aria-pressed'), 'true');
  assert.equal(await feuille.getByRole('button', { name: 'Études' }).count(), 0);
  assert.deepEqual(await feuille.locator('.capture-quand-choix').allTextContents(), ['Dans 1 h', 'Ce soir 20 h', 'Demain 8 h', 'Choisir…']);
  assert.deepEqual(await feuille.locator('.capture-quand-choix').evaluateAll((l) => l.map((b) => b.getAttribute('aria-pressed'))), ['true', 'false', 'false', 'false']);
  assert.equal(await feuille.getByLabel('Date et heure du rappel').count(), 0);
  await feuille.getByRole('button', { name: 'Choisir…' }).click();
  assert.equal(await feuille.getByLabel('Date et heure du rappel').getAttribute('type'), 'datetime-local');
  assert.equal(await feuille.getByRole('button', { name: 'Dans 1 h' }).getAttribute('aria-pressed'), 'false');
});

test('capture d\'un rappel « Dans 1 h » : corps envoyé, confirmation, le rappel apparaît dans Aujourd\'hui', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/aujourdhui');
  const feuille = await ouvrirFeuille(page);
  await feuille.getByRole('button', { name: 'Rappel' }).click();
  await feuille.getByLabel('Une tâche, un rappel').fill('  Sortir   le linge ');
  await feuille.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText('Rappel prévu dans 1 h.').waitFor();
  assert.equal(await page.getByRole('dialog').count(), 0);
  await attendre(() => serveur.appels('rappels').length === 1, 'rappel reçu');
  const { corps } = serveur.appels('rappels')[0];
  assert.deepEqual([corps.texte, corps.quand], ['Sortir le linge', '2026-10-14T16:45:00+02:00']);
  assert.equal(serveur.appels('taches/ajouter').length, 0);
  await page.locator('.rappel-rang', { hasText: 'Sortir le linge' }).waitFor();
  assert.match(await texte(page, '.rappel-rang:has-text("Sortir le linge")'), /^16:45/);
});

test('capture d\'un rappel « Ce soir 20 h » (avant 19 h 30) puis « Demain 8 h »', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/aujourdhui');
  for (const [choix, quand, phrase] of [['Ce soir 20 h', '2026-10-14T20:00:00+02:00', 'Rappel prévu ce soir à 20 h.'], ['Demain 8 h', '2026-10-15T08:00:00+02:00', 'Rappel prévu demain à 8 h.']]) {
    const feuille = await ouvrirFeuille(page);
    await feuille.getByRole('button', { name: 'Rappel' }).click();
    await feuille.getByRole('button', { name: choix }).click();
    await feuille.getByLabel('Une tâche, un rappel').fill('Test');
    await feuille.getByRole('button', { name: 'Ajouter' }).click();
    await page.getByText(phrase).waitFor();
    await attendre(() => serveur.appels('rappels').some((a) => a.corps.quand === quand), `envoi ${quand}`);
  }
});

test('capture : après 19 h 30, « Ce soir 20 h » devient « Demain 20 h »', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T19:45:00+02:00');
  await ouvrir(page, '/#/aujourdhui');
  const feuille = await ouvrirFeuille(page);
  await feuille.getByRole('button', { name: 'Rappel' }).click();
  assert.equal(await feuille.getByRole('button', { name: 'Ce soir 20 h' }).count(), 0);
  await feuille.getByRole('button', { name: 'Demain 20 h' }).click();
  await feuille.getByLabel('Une tâche, un rappel').fill('Test');
  await feuille.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText('Rappel prévu demain à 20 h.').waitFor();
  await attendre(() => serveur.appels('rappels').length === 1, 'rappel reçu');
  assert.equal(serveur.appels('rappels')[0].corps.quand, '2026-10-15T20:00:00+02:00');
});

test('capture : « Choisir… » dans le passé refusé, à venir accepté avec le décalage de Paris', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/aujourdhui');
  const feuille = await ouvrirFeuille(page);
  await feuille.getByRole('button', { name: 'Rappel' }).click();
  await feuille.getByLabel('Une tâche, un rappel').fill('Appeler Léon');
  await feuille.getByRole('button', { name: 'Choisir…' }).click();
  await feuille.getByRole('button', { name: 'Ajouter' }).click();
  await feuille.getByText('Choisis une heure à venir.').waitFor();
  await feuille.getByLabel('Date et heure du rappel').fill('2026-10-14T10:00');
  await feuille.getByRole('button', { name: 'Ajouter' }).click();
  await feuille.getByText('Choisis une heure à venir.').waitFor();
  assert.equal(serveur.appels('rappels').length, 0);
  await feuille.getByLabel('Date et heure du rappel').fill('2026-10-26T14:30');
  await feuille.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText('Rappel prévu lundi 26 octobre à 14 h 30.').waitFor();
  await attendre(() => serveur.appels('rappels').length === 1, 'rappel reçu');
  assert.equal(serveur.appels('rappels')[0].corps.quand, '2026-10-26T14:30:00+01:00');
});

test('capture d\'un rappel hors ligne : « Pas de réseau… », puis envoyé au retour du réseau', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, '/#/aujourdhui');
  await contexte.setOffline(true);
  const feuille = await ouvrirFeuille(page);
  await feuille.getByRole('button', { name: 'Rappel' }).click();
  await feuille.getByLabel('Une tâche, un rappel').fill('Prendre le pain');
  await feuille.getByRole('button', { name: 'Ajouter' }).click();
  await page.getByText("Pas de réseau. Je l'envoie dès que ça revient.").waitFor();
  await page.locator('.rappel-rang', { hasText: 'Prendre le pain' }).waitFor();
  await contexte.setOffline(false);
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await attendre(() => serveur.appels('rappels').length >= 1, 'rappel reçu');
});
