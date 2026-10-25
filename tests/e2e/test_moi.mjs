import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';
import { xpDeBase } from './fixtures.mjs';

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
  await page.getByText('Version 0.4.1').waitFor();
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

test('Moi : plus de Session (déménagée dans Réglages), un lien « Réglages » qui y mène', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi');
  await page.getByText('Agenda connecté', { exact: true }).waitFor();
  assert.equal(await page.getByRole('button', { name: 'Se déconnecter' }).count(), 0);
  assert.equal(await page.getByRole('button', { name: 'Déconnecter tous les appareils' }).count(), 0);
  assert.equal(await page.getByRole('heading', { name: 'Session' }).count(), 0);
  await page.getByRole('link', { name: 'Réglages' }).click();
  await page.getByRole('heading', { name: 'Session' }).waitFor();
});

test("Réglages, Session : se déconnecter : la copie de l'iPhone est effacée, écran de connexion sans message d'expiration", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await attendre(async () => (await copieLocale(page)) !== undefined, 'copie gardée');
  await page.getByRole('button', { name: 'Se déconnecter' }).click();
  await page.getByRole('heading', { name: 'JARVIS JR.' }).waitFor();
  assert.equal(await page.getByText('Ta session a expiré. Reconnecte-toi.').count(), 0);
  assert.equal(await copieLocale(page), undefined);
  assert.equal(serveur.appels('deconnexion').length, 1);
});

test("Réglages, Session : se déconnecter avec une session déjà effacée ailleurs : la copie est quand même effacée, écran de connexion", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await attendre(async () => (await copieLocale(page)) !== undefined, 'copie gardée');
  await contexte.clearCookies();
  await page.getByRole('button', { name: 'Se déconnecter' }).click();
  await page.getByRole('heading', { name: 'JARVIS JR.' }).waitFor();
  assert.equal(await copieLocale(page), undefined);
});

test('Réglages, Session : déconnecter tous les appareils : rien sans confirmation, puis la déconnexion partout', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
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

test('Moi : grade, barre, 4 domaines avec leur niveau, série et joker, au-dessus de Google Agenda', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi');
  await page.getByText('Junior · Assistant · 2 340 / 6 000 XP').waitFor();
  assert.equal(await page.locator('.xp-grade [role="progressbar"]').getAttribute('aria-valuenow'), '840');
  assert.deepEqual(await page.locator('.xp-domaine .xp-nom').allTextContents(), ['QYRIS', 'Asso', 'Études', 'Perso']);
  assert.deepEqual(await page.locator('.xp-domaine .xp-niveau').allTextContents(), ['Niveau 3', 'Niveau 2', 'Niveau 3', 'Niveau 3']);
  assert.equal(await page.locator('.xp-domaine.d-etudes [role="progressbar"]').getAttribute('aria-valuenow'), '120');
  assert.equal(await page.locator('.xp-domaine.d-etudes [role="progressbar"]').getAttribute('aria-valuemax'), '480');
  assert.equal(await texte(page, '.xp-serie'), "Série : 5 jours d'affilée");
  assert.equal(await texte(page, '.xp-joker'), 'Joker disponible cette semaine');
  assert.equal(await page.getByText('Joker en jeu').count(), 0);
  const ordre = await page.locator('.xp-grade, .section-titre').evaluateAll((l) => l.map((e) => e.className.includes('xp-grade') ? 'xp' : e.textContent));
  assert.deepEqual(ordre.slice(0, 2), ['xp', 'Google Agenda']);
  for (const absent of ['Album', 'Badges', 'Journal', 'Wrapped']) assert.equal(await page.getByText(absent).count(), 0);
});

test('Moi : séries et jokers selon la fixture (1 jour, pas de série, joker utilisé jeudi, joker en jeu)', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  const donnees = serveur.etat.donnees;
  donnees.xp = xpDeBase(undefined, { jours: 1, joker_libre: false, dernier_joker: '2026-10-08' });
  await ouvrir(page, '/#/moi');
  await page.getByText("Série : 1 jour d'affilée").waitFor();
  assert.equal(await texte(page, '.xp-joker'), 'Joker utilisé jeudi');
  donnees.xp = xpDeBase(undefined, { jours: 0, joker_libre: true, en_jeu: true });
  await page.reload();
  await page.getByText('Pas encore de série').waitFor();
  assert.equal(await texte(page, '.xp-joker'), 'Joker disponible cette semaine');
  await page.getByText("Joker en jeu : agis aujourd'hui pour garder ta série").waitFor();
});

test("Moi : au grade Majordome, « Junior · Majordome · 25 000 XP » sans palier suivant", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  serveur.etat.donnees.xp = xpDeBase({ qyris: 10000, asso: 5000, etudes: 6000, perso: 4000 });
  serveur.etat.donnees.xp.grade = 'majordome';
  serveur.etat.donnees.xp.grade_suivant = null;
  serveur.etat.donnees.xp.seuil_suivant = null;
  await ouvrir(page, '/#/moi');
  await page.getByText('Junior · Majordome · 25 000 XP').waitFor();
});

test("Moi : sans XP (calcul échoué côté serveur, première ouverture), l'écran tient sans erreur", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  serveur.etat.donnees.xp = null;
  await ouvrir(page, '/#/moi');
  await page.getByText('Agenda connecté', { exact: true }).waitFor();
  assert.equal(await page.locator('.xp-grade').count(), 0);
});
