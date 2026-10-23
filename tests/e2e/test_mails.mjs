import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';
import { mailsDeBase } from './fixtures.mjs';

const MERCREDI = '2026-10-14T15:45:00+02:00';

async function ouvrirMails(page, contexte, serveur, quand = MERCREDI) {
  await connecte(contexte, serveur);
  await heure(page, quand);
  await ouvrir(page, '/#/mails');
  await page.locator('.mails-section').first().waitFor();
}

const section = (page, nom) => page.getByRole('region', { name: nom });
const titres = (page) => page.locator('.mails-section .section-titre').allTextContents();

test('mails : 4 sections dans l\'ordre UTC, QYRIS, Gmail, iCloud, avec leur nombre de non lus', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  assert.deepEqual(await titres(page), ['UTC2 non lus', 'QYRIS1 non lu', 'Gmail1 non lu', 'iCloud0 non lu']);
  assert.equal(await texte(page, '.mails-note'), 'Lecture seule : Junior ne marque rien comme lu.');
  assert.equal(await page.locator('.onglet[aria-current="page"]').textContent(), 'Mails');
});

test('mails : un mail montre expéditeur, objet, extrait et heure (« 14 h 32 », « hier », « ven. 09/10 », « 24/12/2025 »), le non lu est mis en évidence', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  const premier = section(page, 'UTC').locator('.mail').first();
  assert.equal(await texte(premier, '.mail-de'), 'Secrétariat GI');
  assert.equal(await texte(premier, '.mail-objet'), 'Inscription aux UV de A27');
  assert.ok((await texte(premier, '.mail-extrait')).startsWith('Les inscriptions ouvrent lundi'));
  assert.deepEqual(await section(page, 'UTC').locator('.mail-heure').allTextContents(), ['14 h 32', 'hier', 'ven. 09/10']);
  assert.deepEqual(await section(page, 'UTC').locator('.mail').evaluateAll((els) => els.map((e) => e.classList.contains('non-lu'))), [true, true, false]);
  assert.equal(await section(page, 'iCloud').locator('.mail-heure').textContent(), '24/12/2025');
  assert.equal(await page.getByText('Une connexion depuis un nouvel appareil').count(), 1);
});

test('mails : QYRIS réunit colin@ et contact@, chaque mail porte l\'étiquette de son adresse', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  assert.deepEqual(await section(page, 'QYRIS').locator('.mail-etiquette').allTextContents(), ['colin@', 'contact@']);
  assert.equal(await section(page, 'Gmail').locator('.mail-etiquette').count(), 0);
});

test('mails : pubs repliées sous « + 48 pubs », dépliées puis « Masquer les pubs »', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  const gmail = section(page, 'Gmail');
  assert.equal(await gmail.locator('.mail').count(), 1);
  const bascule = gmail.getByRole('button', { name: '+ 48 pubs' });
  assert.equal(await bascule.getAttribute('aria-expanded'), 'false');
  await bascule.click();
  assert.equal(await gmail.locator('.mails-pubs .mail').count(), 2);
  assert.equal(await gmail.getByRole('button', { name: 'Masquer les pubs' }).getAttribute('aria-expanded'), 'true');
  await gmail.getByRole('button', { name: 'Masquer les pubs' }).click();
  assert.equal(await gmail.locator('.mails-pubs').count(), 0);
  assert.equal(await section(page, 'UTC').getByRole('button', { name: /pub/ }).count(), 0);
});

test('mails : « C\'est une pub » déplace le mail dans les pubs tout de suite, « Retenu. » et un seul envoi par la file', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  const gmail = section(page, 'Gmail');
  await gmail.locator('.mail-corps', { hasText: 'Nouvelle connexion' }).click();
  await gmail.getByRole('button', { name: "C'est une pub" }).click();
  await page.locator('.message', { hasText: 'Retenu.' }).waitFor();
  assert.equal(await gmail.locator('.mail').count(), 0);
  assert.equal(await gmail.locator('.section-titre').textContent(), 'Gmail0 non lu');
  await gmail.getByRole('button', { name: '+ 49 pubs' }).click();
  assert.equal(await gmail.locator('.mails-pubs .mail').count(), 3);
  await attendre(() => serveur.etat.corrections.length === 1, 'la correction part au serveur');
  assert.deepEqual(serveur.etat.corrections, [{ adresse: 'support@exemple-hebergeur.fr', choix: 'pub' }]);
  assert.equal(serveur.appels('mails/expediteur').length, 1);
});

test('mails : « Ce n\'est pas une pub » remet le mail dans la liste et compte ses non lus', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  const gmail = section(page, 'Gmail');
  await gmail.getByRole('button', { name: '+ 48 pubs' }).click();
  await gmail.locator('.mail-corps', { hasText: 'Les nouvelles de la semaine' }).click();
  await gmail.getByRole('button', { name: "Ce n'est pas une pub" }).click();
  await page.locator('.message', { hasText: 'Retenu.' }).waitFor();
  assert.equal(await gmail.locator('.mails-liste:not(.mails-pubs) .mail').count(), 2);
  assert.equal(await gmail.locator('.section-titre').textContent(), 'Gmail2 non lus');
  await gmail.getByRole('button', { name: 'Masquer les pubs' }).click();
  assert.equal(await gmail.getByRole('button', { name: '+ 47 pubs' }).count(), 1);
  await attendre(() => serveur.etat.corrections.length === 1, 'la correction part au serveur');
  assert.equal(serveur.etat.corrections[0].choix, 'afficher');
});

test('mails : chargement en trois temps, GET mails puis un POST mails/rafraichir par compte', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  await attendre(() => serveur.appels('mails/rafraichir').length === 4, 'les 4 rafraîchissements');
  assert.deepEqual(serveur.appels('mails/rafraichir').map((a) => a.corps.compte).sort(), ['colin', 'contact', 'gmail', 'icloud']);
  assert.equal(serveur.appels('mails').length, 1);
});

test('mails : une section en erreur affiche son message et les autres restent affichées', async ({ page, contexte, serveur }) => {
  serveur.etat.mails.comptes.icloud.erreur = 'refus';
  serveur.etat.mails.comptes.contact.erreur = 'delai';
  await ouvrirMails(page, contexte, serveur);
  assert.equal(await texte(section(page, 'iCloud'), '.mails-avis'), "iCloud ne répond pas. Vérifie le mot de passe d'application dans Réglages.");
  assert.equal(await texte(section(page, 'QYRIS'), '.mails-avis'), 'La boîte contact@qyris.fr ne répond pas. Vérifie son mot de passe dans Réglages.');
  assert.equal(await section(page, 'QYRIS').locator('.mail').count(), 2, 'la dernière copie reste affichée');
  assert.equal(await section(page, 'UTC').locator('.mails-avis').count(), 0);
  assert.equal(await section(page, 'Gmail').locator('.mails-avis').count(), 0);
  assert.equal(await section(page, 'UTC').locator('.mail').count(), 3);
});

test('mails : la section UTC suit l\'état de Gmail', async ({ page, contexte, serveur }) => {
  serveur.etat.mails.comptes.gmail.erreur = 'injoignable';
  await ouvrirMails(page, contexte, serveur);
  const texteGmail = "Gmail ne répond pas. Vérifie le mot de passe d'application dans Réglages.";
  assert.equal(await texte(section(page, 'UTC'), '.mails-avis'), texteGmail);
  assert.equal(await texte(section(page, 'Gmail'), '.mails-avis'), texteGmail);
  assert.equal(await section(page, 'QYRIS').locator('.mails-avis').count(), 0);
});

test('mails : une erreur de boîte rendue en 400 par le rafraîchissement marque la section', async ({ page, contexte, serveur }) => {
  serveur.etat.pannes['mails/rafraichir'] = { statut: 400, code: 'refus', message: 'Mot de passe refusé.' };
  await ouvrirMails(page, contexte, serveur);
  await section(page, 'iCloud').locator('.mails-avis.erreur').waitFor();
  assert.equal(await section(page, 'QYRIS').locator('.mails-avis.erreur').count(), 2);
  assert.equal(await section(page, 'UTC').locator('.mail').count(), 3);
});

test('mails : un compte non enregistré dit « Pas encore connecté » avec un lien vers Réglages, sans rafraîchissement', async ({ page, contexte, serveur }) => {
  serveur.etat.mails.comptes.colin.erreur = 'non_connecte';
  serveur.etat.mails.comptes.colin.recupere_le = null;
  serveur.etat.mails.comptes.icloud.erreur = 'non_connecte';
  serveur.etat.mails.comptes.icloud.recupere_le = null;
  serveur.etat.mails.sections[3].mails = [];
  await ouvrirMails(page, contexte, serveur);
  assert.equal(await texte(section(page, 'iCloud'), '.mails-avis p'), 'Pas encore connecté');
  assert.equal(await texte(section(page, 'QYRIS'), '.mails-avis p'), 'colin@qyris.fr : Pas encore connecté');
  assert.equal(await section(page, 'iCloud').getByRole('link', { name: 'Ouvrir Réglages' }).getAttribute('href'), '#/reglages');
  await attendre(() => serveur.appels('mails/rafraichir').length === 2, 'rafraîchissement de gmail et contact seulement');
  await page.waitForTimeout(200);
  assert.deepEqual(serveur.appels('mails/rafraichir').map((a) => a.corps.compte).sort(), ['contact', 'gmail']);
});

test('mails : boutons d\'ouverture par section', async ({ page, contexte, serveur }) => {
  await ouvrirMails(page, contexte, serveur);
  assert.equal(await section(page, 'UTC').getByRole('button', { name: 'Ouvrir dans Gmail' }).count(), 1);
  assert.equal(await section(page, 'Gmail').getByRole('button', { name: 'Ouvrir dans Gmail' }).count(), 1);
  assert.equal(await section(page, 'iCloud').getByRole('link', { name: 'Ouvrir dans iCloud Mail' }).getAttribute('href'), 'https://www.icloud.com/mail/');
  const ovh = section(page, 'QYRIS').getByRole('link', { name: 'Ouvrir le webmail OVH' });
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
  await page.locator('.mails-section').first().waitFor();
  await page.locator('.mails-age').first().waitFor();
  assert.deepEqual(await page.locator('.mails-age').allTextContents(), ['à jour il y a 12 min', 'à jour il y a 12 min', 'à jour il y a 12 min', 'à jour il y a 12 min']);
  assert.equal(await section(page, 'UTC').locator('.mail').count(), 3);
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
  await page.locator('.mails-section').first().waitFor();
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
  await section(page, 'Gmail').getByRole('button', { name: '+ 48 pubs' }).click();
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
