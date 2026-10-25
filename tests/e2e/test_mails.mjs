import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';
import { mailsDeBase } from './fixtures.mjs';

const MERCREDI = '2026-10-14T15:45:00+02:00';

const NOMS = { utc: 'UTC', qyris: 'QYRIS', gmail: 'Gmail', icloud: 'iCloud' };

async function ouvrirMails(page, contexte, serveur, quand = MERCREDI) {
  await connecte(contexte, serveur);
  await heure(page, quand);
  await ouvrir(page, '/#/mails');
  await page.locator('.mails-boite').first().waitFor();
}

const section = (page, nom) => page.getByRole('region', { name: nom });
const boites = (page) => page.locator('.mails-boite').evaluateAll((els) => els.map((e) => [e.querySelector('.mails-boite-nom').textContent, e.querySelector('.mails-boite-etat').textContent]));

// Ouvre une boîte (sans recharger la page) et rend sa section
async function voir(page, cle) {
  await page.evaluate((c) => { location.hash = `#/mails/${c}`; }, cle);
  const s = section(page, NOMS[cle]);
  await s.waitFor();
  return s;
}

test('mails : la liste des 4 boîtes dans l\'ordre UTC, QYRIS, Gmail, iCloud, avec leur nombre de non lus', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  assert.deepEqual(await boites(page), [['UTC', '2 non lus'], ['QYRIS', '1 non lu'], ['Gmail', '1 non lu'], ['iCloud', '0 non lu']]);
  assert.equal(await page.locator('.mails-section').count(), 0, 'aucun mail sur la liste des boîtes');
  assert.equal(await page.locator('.mails-boite.non-lu').count(), 3);
  assert.equal(await texte(page, '.mails-note'), 'Lecture seule : Junior ne marque rien comme lu.');
  assert.equal(await page.locator('.onglet[aria-current="page"]').textContent(), 'Mails');
});

test('mails : toucher une boîte l\'ouvre seule, « Retour aux boîtes » ramène à la liste', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  await page.locator('.mails-boite', { hasText: 'Gmail' }).click();
  await section(page, 'Gmail').waitFor();
  assert.equal(new URL(page.url()).hash, '#/mails/gmail');
  assert.equal(await texte(page, '.titre-ecran'), 'Gmail');
  assert.equal(await page.locator('.mails-section').count(), 1);
  assert.equal(await page.locator('.onglet[aria-current="page"]').textContent(), 'Mails');
  await page.getByRole('button', { name: 'Retour aux boîtes' }).click();
  await page.locator('.mails-boite').first().waitFor();
  assert.equal(new URL(page.url()).hash, '#/mails');
  assert.equal(await page.locator('.mails-section').count(), 0);
});

test('mails : une boîte ouverte directement (#/mails/utc) a son bouton retour vers la liste', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  await ouvrir(page, '/#/mails/utc');
  await section(page, 'UTC').waitFor();
  await page.getByRole('button', { name: 'Retour aux boîtes' }).click();
  await page.locator('.mails-boite').first().waitFor();
  assert.equal(new URL(page.url()).hash, '#/mails');
});

test('mails : un mail montre expéditeur, objet, extrait et heure (« 14 h 32 », « hier », « ven. 09/10 », « 24/12/2025 »), le non lu est mis en évidence', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  const premier = (await voir(page, 'utc')).locator('.mail').first();
  assert.equal(await texte(premier, '.mail-de'), 'Secrétariat GI');
  assert.equal(await texte(premier, '.mail-objet'), 'Inscription aux UV de A27');
  assert.ok((await texte(premier, '.mail-extrait')).startsWith('Les inscriptions ouvrent lundi'));
  assert.deepEqual(await section(page, 'UTC').locator('.mail-heure').allTextContents(), ['14 h 32', 'hier', 'ven. 09/10']);
  assert.deepEqual(await section(page, 'UTC').locator('.mail').evaluateAll((els) => els.map((e) => e.classList.contains('non-lu'))), [true, true, false]);
  assert.equal(await (await voir(page, 'icloud')).locator('.mail-heure').textContent(), '24/12/2025');
  await voir(page, 'gmail');
  assert.equal(await page.getByText('Une connexion depuis un nouvel appareil').count(), 1);
});

test('mails : QYRIS montre les mails de colin@, l\'étiquette « contact@ » seulement sur un mail écrit à contact@', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  const qyris = await voir(page, 'qyris');
  assert.deepEqual(await qyris.locator('.mail-objet').allTextContents(), ['Devis du site', 'Question sur la formation']);
  assert.deepEqual(await qyris.locator('.mail').evaluateAll((els) => els.map((e) => e.querySelector('.mail-etiquette')?.textContent ?? null)), [null, 'contact@']);
  assert.equal(await (await voir(page, 'gmail')).locator('.mail-etiquette').count(), 0);
});

test('mails : pubs repliées sous « + 48 pubs », dépliées puis « Masquer les pubs »', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  const gmail = await voir(page, 'gmail');
  assert.equal(await gmail.locator('.mail').count(), 1);
  const bascule = gmail.getByRole('button', { name: '+ 48 pubs' });
  assert.equal(await bascule.getAttribute('aria-expanded'), 'false');
  await bascule.click();
  assert.equal(await gmail.locator('.mails-pubs .mail').count(), 2);
  assert.equal(await gmail.getByRole('button', { name: 'Masquer les pubs' }).getAttribute('aria-expanded'), 'true');
  await gmail.getByRole('button', { name: 'Masquer les pubs' }).click();
  assert.equal(await gmail.locator('.mails-pubs').count(), 0);
  assert.equal(await (await voir(page, 'utc')).getByRole('button', { name: /pub/ }).count(), 0);
});

test('mails : « C\'est une pub » déplace le mail dans les pubs tout de suite, « Retenu. » et un seul envoi par la file', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  const gmail = await voir(page, 'gmail');
  await gmail.locator('.mail-corps', { hasText: 'Nouvelle connexion' }).click();
  await gmail.getByRole('button', { name: "C'est une pub" }).click();
  await page.locator('.message', { hasText: 'Retenu.' }).waitFor();
  assert.equal(await gmail.locator('.mail').count(), 0);
  assert.equal(await gmail.locator('.section-titre').textContent(), 'Derniers mails0 non lu');
  await gmail.getByRole('button', { name: '+ 49 pubs' }).click();
  assert.equal(await gmail.locator('.mails-pubs .mail').count(), 3);
  await attendre(() => serveur.etat.corrections.length === 1, 'la correction part au serveur');
  assert.deepEqual(serveur.etat.corrections, [{ adresse: 'support@exemple-hebergeur.fr', choix: 'pub' }]);
  assert.equal(serveur.appels('mails/expediteur').length, 1);
});

test('mails : « Ce n\'est pas une pub » remet le mail dans la liste et compte ses non lus', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  const gmail = await voir(page, 'gmail');
  await gmail.getByRole('button', { name: '+ 48 pubs' }).click();
  await gmail.locator('.mail-corps', { hasText: 'Les nouvelles de la semaine' }).click();
  await gmail.getByRole('button', { name: "Ce n'est pas une pub" }).click();
  await page.locator('.message', { hasText: 'Retenu.' }).waitFor();
  assert.equal(await gmail.locator('.mails-liste:not(.mails-pubs) .mail').count(), 2);
  assert.equal(await gmail.locator('.section-titre').textContent(), 'Derniers mails2 non lus');
  await gmail.getByRole('button', { name: 'Masquer les pubs' }).click();
  assert.equal(await gmail.getByRole('button', { name: '+ 47 pubs' }).count(), 1);
  await attendre(() => serveur.etat.corrections.length === 1, 'la correction part au serveur');
  assert.equal(serveur.etat.corrections[0].choix, 'afficher');
});

test('mails : chargement en trois temps, GET mails puis un POST mails/rafraichir par compte', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  await attendre(() => serveur.appels('mails/rafraichir').length === 4, 'les 4 rafraîchissements');
  assert.deepEqual(serveur.appels('mails/rafraichir').map((a) => a.corps.compte).sort(), ['colin', 'gmail', 'icloud', 'utc']);
  assert.equal(serveur.appels('mails').length, 1);
});

test('mails : une section en erreur affiche son message et les autres restent affichées', async ({ page, contexte, serveur }) => {
  serveur.etat.mails.comptes.icloud.erreur = 'refus';
  serveur.etat.mails.comptes.colin.erreur = 'delai';
  await ouvrirMails(page, contexte, serveur);
  assert.deepEqual(await boites(page), [['UTC', '2 non lus'], ['QYRIS', 'Ne répond pas'], ['Gmail', '1 non lu'], ['iCloud', 'Ne répond pas']]);
  assert.equal(await page.locator('.mails-boite.erreur').count(), 2);
  assert.equal(await texte(await voir(page, 'icloud'), '.mails-avis'), "iCloud ne répond pas. Vérifie le mot de passe d'application dans Réglages.");
  const qyris = await voir(page, 'qyris');
  assert.equal(await texte(qyris, '.mails-avis'), 'La boîte colin@qyris.fr ne répond pas. Vérifie son mot de passe dans Réglages.');
  assert.equal(await qyris.locator('.mail').count(), 2, 'la dernière copie reste affichée');
  assert.equal(await (await voir(page, 'gmail')).locator('.mails-avis').count(), 0);
  const utc = await voir(page, 'utc');
  assert.equal(await utc.locator('.mails-avis').count(), 0);
  assert.equal(await utc.locator('.mail').count(), 3);
});

test('mails : la section UTC suit l\'état du compte UTC, et Gmail le sien', async ({ page, contexte, serveur }) => {
  serveur.etat.mails.comptes.utc.erreur = 'refus';
  await ouvrirMails(page, contexte, serveur);
  assert.equal(await texte(await voir(page, 'utc'), '.mails-avis'), 'La boîte UTC ne répond pas. Vérifie ton mot de passe UTC dans Réglages.');
  assert.equal(await (await voir(page, 'gmail')).locator('.mails-avis').count(), 0);
  assert.equal(await (await voir(page, 'qyris')).locator('.mails-avis').count(), 0);
});

test('mails : une erreur de boîte rendue en 400 par le rafraîchissement marque la section', async ({ page, contexte, serveur }) => {
  serveur.etat.pannes['mails/rafraichir'] = { statut: 400, code: 'refus', message: 'Mot de passe refusé.' };
  await ouvrirMails(page, contexte, serveur);
  await (await voir(page, 'icloud')).locator('.mails-avis.erreur').waitFor();
  assert.equal(await (await voir(page, 'qyris')).locator('.mails-avis.erreur').count(), 1);
  assert.equal(await (await voir(page, 'utc')).locator('.mail').count(), 3);
});

test('mails : un compte non enregistré dit « Pas encore connecté » avec un lien vers Réglages, sans rafraîchissement', async ({ page, contexte, serveur }) => {
  serveur.etat.mails.comptes.colin.erreur = 'non_connecte';
  serveur.etat.mails.comptes.colin.recupere_le = null;
  serveur.etat.mails.comptes.icloud.erreur = 'non_connecte';
  serveur.etat.mails.comptes.icloud.recupere_le = null;
  serveur.etat.mails.sections[3].mails = [];
  await ouvrirMails(page, contexte, serveur);
  assert.deepEqual((await boites(page))[3], ['iCloud', 'Pas encore connecté']);
  const icloud = await voir(page, 'icloud');
  assert.equal(await texte(icloud, '.mails-avis p'), 'Pas encore connecté');
  assert.equal(await icloud.getByRole('link', { name: 'Ouvrir Réglages' }).getAttribute('href'), '#/reglages');
  assert.equal(await texte(await voir(page, 'qyris'), '.mails-avis p'), 'colin@qyris.fr : Pas encore connecté');
  await attendre(() => serveur.appels('mails/rafraichir').length === 2, 'rafraîchissement de utc et gmail seulement');
  await page.waitForTimeout(200);
  assert.deepEqual(serveur.appels('mails/rafraichir').map((a) => a.corps.compte).sort(), ['gmail', 'utc']);
});

test('mails : boutons d\'ouverture par section', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  assert.equal(await (await voir(page, 'utc')).getByRole('link', { name: 'Ouvrir le webmail UTC' }).getAttribute('href'), 'https://webmail.utc.fr');
  assert.equal(await (await voir(page, 'gmail')).getByRole('button', { name: 'Ouvrir dans Gmail' }).count(), 1);
  assert.equal(await (await voir(page, 'icloud')).getByRole('link', { name: 'Ouvrir dans iCloud Mail' }).getAttribute('href'), 'https://www.icloud.com/mail/');
  const ovh = (await voir(page, 'qyris')).getByRole('link', { name: 'Ouvrir le webmail OVH' });
  assert.equal(await ovh.getAttribute('href'), 'https://www.ovhcloud.com/fr/mail/');
  assert.equal(await ovh.getAttribute('rel'), 'noopener noreferrer');
});

test('mails : hors ligne, la copie locale s\'affiche avec « à jour il y a 12 min »', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  await attendre(() => serveur.appels('mails/rafraichir').length === 4, 'les 4 rafraîchissements');
  await page.waitForTimeout(150);
  serveur.etat.horsService = true;
  await page.reload();
  await page.waitForFunction(() => document.documentElement.dataset.pret === '1');
  await page.locator('.mails-boite').first().waitFor();
  const utc = await voir(page, 'utc');
  await page.locator('.mails-age').waitFor();
  assert.equal(await texte(page, '.mails-age'), 'à jour il y a 12 min');
  assert.equal(await utc.locator('.mail').count(), 3);
});

test('mails : sans copie ni réseau, « Pas encore de données »', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  serveur.etat.horsService = true;
  await ouvrir(page, '/#/mails');
  await page.locator('#ecran').getByText(/^Pas encore de données/).waitFor();
});

test('aujourd\'hui : le bloc Mails résume les non lus et ouvre l\'onglet Mails', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  await ouvrir(page, '/#/aujourdhui');
  const bloc = page.locator('.mails-resume');
  await bloc.waitFor();
  assert.equal(await texte(page, '.mails-resume'), '4 non lus · UTC 2 · QYRIS 1 · Gmail 1');
  assert.equal(await bloc.evaluate((e) => e.previousElementSibling.previousElementSibling.textContent), 'Mails');
  await bloc.click();
  await page.locator('.mails-boite').first().waitFor();
  assert.equal(new URL(page.url()).hash, '#/mails');
});

test('aujourd\'hui : sans mail non lu « Boîtes à jour. »', async ({ page, contexte, serveur }) => {
  serveur.etat.donnees.mails_resume = { total: 0, par_section: { utc: 0, qyris: 0, gmail: 0, icloud: 0 } };
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  await ouvrir(page, '/#/aujourdhui');
  await page.locator('.mails-resume').waitFor();
  assert.equal(await texte(page, '.mails-resume'), 'Boîtes à jour.');
});

test('mails : la barre d\'onglets (5 onglets et le +) tient à 390 px et à 375 px, rien ne déborde', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  await (await voir(page, 'gmail')).getByRole('button', { name: '+ 48 pubs' }).click();
  for (const largeur of [375, 390, 768]) {
    await page.setViewportSize({ width: largeur, height: 844 });
    await page.waitForTimeout(100);
    const mesures = await page.evaluate(() => ({
      debord: [...document.querySelectorAll('#app *')].filter((e) => e.getClientRects().length > 0 && e.getBoundingClientRect().right > window.innerWidth + 1).map((e) => `${e.tagName.toLowerCase()}.${e.className}`),
      onglets: [...document.querySelectorAll('.onglet span')].map((s) => [s.textContent, s.scrollWidth <= s.parentElement.clientWidth]),
    }));
    assert.deepEqual(mesures.debord, [], `rien ne déborde à ${largeur} px`);
    assert.deepEqual(mesures.onglets.filter(([, tient]) => !tient), [], `libellés des onglets à ${largeur} px`);
  }
});
