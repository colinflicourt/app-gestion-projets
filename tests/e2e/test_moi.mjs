import { test, assert, heure, connecte, ouvrir, attendre } from './cadre.mjs';

async function preparer(page, contexte, serveur) {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
}

const copieLocale = (page) => page.evaluate(async () => (await import('/js/stockage.js')).lire('donnees', 'derniere'));

test("Moi : agenda connecté, « Reconnecter », version de l'app, lien vers les règles de confidentialité", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi');
  await page.getByText('Agenda connecté', { exact: true }).waitFor();
  assert.equal(await page.getByRole('link', { name: 'Reconnecter' }).getAttribute('href'), '/api/google/connexion');
  await page.getByText('Version 0.2.0').waitFor();
  assert.equal(await page.getByRole('link', { name: 'Confidentialité' }).getAttribute('href'), '/confidentialite.html');
});

test('Moi : retour de Google « connecte » : « Agenda connecté. », actualisation forcée, adresse nettoyée', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi?google=connecte');
  await page.getByText('Agenda connecté.', { exact: true }).waitFor();
  await attendre(() => serveur.appels('donnees').some((a) => a.parametres.force === '1'), 'actualisation forcée');
  assert.equal(new URL(page.url()).hash, '#/moi');
});

test("Moi : retour « erreur » : « La connexion à Google n'a pas abouti. Réessaie. »", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi?google=erreur');
  await page.getByText("La connexion à Google n'a pas abouti. Réessaie.").waitFor();
});

test('Google déconnecté : « Reconnecter » mène à Google, puis revient sur Moi avec « Agenda connecté. »', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  serveur.etat.donnees.agenda.erreur = 'google_deconnecte';
  await ouvrir(page, '/#/aujourdhui');
  await page.getByRole('link', { name: 'Reconnecter' }).click();
  await page.getByText('Agenda connecté.', { exact: true }).waitFor();
  assert.equal(new URL(page.url()).hash, '#/moi');
  await attendre(async () => !(await page.locator('#statut').isVisible()), 'bandeau de Google retiré');
});

test("Se déconnecter : la copie de l'iPhone est effacée, écran de connexion sans message d'expiration", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi');
  await attendre(async () => (await copieLocale(page)) !== undefined, 'copie gardée');
  await page.getByRole('button', { name: 'Se déconnecter' }).click();
  await page.getByRole('heading', { name: 'JARVIS JR.' }).waitFor();
  assert.equal(await page.getByText('Ta session a expiré. Reconnecte-toi.').count(), 0);
  assert.equal(await copieLocale(page), undefined);
  assert.equal(serveur.appels('deconnexion').length, 1);
});

test('Déconnecter tous les appareils : rien sans confirmation, puis la déconnexion partout', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi');
  await page.getByRole('button', { name: 'Déconnecter tous les appareils' }).click();
  assert.equal(serveur.appels('deconnexion-partout').length, 0);
  page.once('dialog', (d) => d.accept());
  await page.getByRole('button', { name: 'Déconnecter tous les appareils' }).click();
  await page.getByRole('heading', { name: 'JARVIS JR.' }).waitFor();
  assert.equal(serveur.appels('deconnexion-partout').length, 1);
});

test('règles de confidentialité : page publique, lisible sans connexion, sans erreur', async ({ page }) => {
  await page.goto('/confidentialite.html');
  await page.getByRole('heading', { name: 'Règles de confidentialité' }).waitFor();
  await page.getByText('calendar.readonly', { exact: false }).first().waitFor();
});
