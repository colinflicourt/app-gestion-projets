import { test, assert, heure, connecte, ouvrir, attendre } from './cadre.mjs';

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
