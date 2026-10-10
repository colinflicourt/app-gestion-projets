import { test, assert, heure, connecte, ouvrir, attendre } from './cadre.mjs';
import { MOT_DE_PASSE } from './serveur.mjs';

const MERCREDI = '2026-10-14T15:45:00+02:00';

const copieLocale = (page) => page.evaluate(async () => (await import('/js/stockage.js')).lire('donnees', 'derniere'));

test("connexion : mot de passe faux puis juste ; les données arrivent et sont gardées sur l'iPhone", async ({ page, serveur }) => {
  await heure(page, MERCREDI);
  await ouvrir(page, '/');
  await page.getByRole('heading', { name: 'JARVIS JR.' }).waitFor();
  assert.equal(await page.locator('#onglets').isVisible(), false);
  await page.getByLabel('Mot de passe').fill('mauvais');
  await page.getByRole('button', { name: 'Entrer' }).click();
  await page.getByText('Mot de passe incorrect. Encore 9 essais avant une pause de 15 min.').waitFor();
  await page.getByLabel('Mot de passe').fill(MOT_DE_PASSE);
  await page.getByRole('button', { name: 'Entrer' }).click();
  await page.locator('#onglets').waitFor();
  await attendre(async () => (await copieLocale(page))?.maintenant === MERCREDI, 'copie gardée dans IndexedDB');
  assert.deepEqual(serveur.appels('donnees').map((a) => a.parametres.force), [undefined, '1']);
});

test("sans réseau, avec une copie de 12 min : « Hors ligne · données d'il y a 12 min »", async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  await ouvrir(page, '/');
  await attendre(async () => (await copieLocale(page)) !== undefined, 'première copie');
  await page.evaluate(async (recu) => {
    const stockage = await import('/js/stockage.js');
    await stockage.ecrire('donnees', 'derniere', { ...(await stockage.lire('donnees', 'derniere')), recu_le: recu });
  }, new Date(MERCREDI).getTime() - 12 * 60000);
  serveur.etat.horsService = true;
  await page.reload();
  await page.getByText("Hors ligne · données d'il y a 12 min").waitFor();
});

test("sans réseau ni copie : un message clair, pas d'écran blanc", async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  serveur.etat.horsService = true;
  await ouvrir(page, '/');
  await page.getByText('Pas encore de données. Il me faut du réseau une première fois.').waitFor();
  assert.equal(await page.locator('#onglets').isVisible(), true);
});

test('session expirée avec une copie : écran de connexion avec « Ta session a expiré. Reconnecte-toi. »', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await ouvrir(page, '/');
  await attendre(async () => (await copieLocale(page)) !== undefined, 'première copie');
  await contexte.clearCookies();
  await page.reload();
  await page.getByText('Ta session a expiré. Reconnecte-toi.').waitFor();
});

test("Trello ne répond pas : « Tes tâches s'affichent telles qu'il y a 5 min »", async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  Object.assign(serveur.etat.donnees.trello, { erreur: 'trello_indisponible', recupere_le: '2026-10-14T15:40:00+02:00' });
  await ouvrir(page, '/');
  await page.getByText("Trello ne répond pas. Tes tâches s'affichent telles qu'il y a 5 min.").waitFor();
});

test('Google déconnecté : le bandeau propose « Reconnecter », vers la connexion Google', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  serveur.etat.donnees.agenda.erreur = 'google_deconnecte';
  await ouvrir(page, '/');
  await page.getByText("Google m'a déconnecté de ton agenda. Reconnecte-le, ça prend 30 secondes.").waitFor();
  assert.equal(await page.getByRole('link', { name: 'Reconnecter' }).getAttribute('href'), '/api/google/connexion');
});
