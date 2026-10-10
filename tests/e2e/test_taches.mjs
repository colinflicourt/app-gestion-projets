import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';
import { MOT_DE_PASSE } from './serveur.mjs';
import { CARTES, id } from './fixtures.mjs';

const MERCREDI = '2026-10-14T15:45:00+02:00';

async function preparer(page, contexte, serveur) {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
}

const carteDuServeur = (serveur, cle) => serveur.etat.donnees.trello.cartes.find((c) => c.id === CARTES[cle]);
const fileVide = (page) => attendre(async () => !(await page.locator('#statut').isVisible()), 'plus rien en attente');

test('tâches : 9 cartes ouvertes, 3 groupes, retard en rouge, filtre Études, cartes faites à la demande', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/taches');
  await page.getByText('9 CARTES').waitFor();
  // Tout et les 4 domaines tiennent sur une ligne à la taille de texte standard
  assert.equal(new Set(await page.locator('.filtre').evaluateAll((l) => l.map((e) => e.offsetTop))).size, 1);
  assert.deepEqual(await page.locator('.groupe-titre span:first-child').allTextContents(), ['En cours', 'À faire', 'En attente']);
  assert.equal(await texte(page, '.carte-echeance.retard'), 'En retard depuis le 06/10');
  await page.getByRole('button', { name: 'Études', exact: true }).click();
  await page.getByText('3 CARTES').waitFor();
  assert.equal(new URL(page.url()).hash, '#/taches/etudes');
  await page.getByRole('button', { name: 'Tout', exact: true }).click();
  await page.getByRole('button', { name: 'Voir les cartes faites' }).click();
  await page.getByText('Logo : variantes PNG').waitFor();
});

test("carte : cocher une étape envoie une seule écriture, le tampon FAIT apparaît, l'avancement passe à 1/3", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, `/#/carte/${CARTES.linkedin}`);
  await page.getByRole('heading', { name: 'Campagne LinkedIn : programmer les posts' }).waitFor();
  await page.getByRole('button', { name: 'Cocher : Envoyer un mail de test' }).click();
  await page.getByRole('button', { name: 'Décocher : Envoyer un mail de test' }).waitFor();
  await attendre(() => serveur.appels('taches/cocher').length === 1, 'écriture reçue');
  const { corps } = serveur.appels('taches/cocher')[0];
  assert.deepEqual([corps.carte, corps.element, corps.coche], [CARTES.linkedin, id('ee', 11), true]);
  assert.match(corps.id_client, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
  await attendre(async () => (await texte(page, '.carte-ouverte .carte-progres')) === '1/3', 'avancement 1/3');
});

test('carte : Terminer, puis Annuler dans les 5 s, remet la carte dans « À faire »', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/taches/etudes');
  await page.getByText('MT23 : préparer le médian').click();
  await page.getByRole('button', { name: 'Terminer' }).click();
  await page.getByText('Carte terminée.').waitFor();
  assert.equal(new URL(page.url()).hash, '#/taches/etudes');
  await page.getByRole('button', { name: 'Annuler' }).click();
  // « Terminer » parti puis annulé, ou retiré de la file avant de partir : dans les deux cas, autant de l'un que de l'autre
  await attendre(async () => serveur.appels('taches/terminer').length === serveur.appels('taches/annuler').length
    && carteDuServeur(serveur, 'median').colonne === 'a_faire' && !(await page.locator('#statut').isVisible()), 'carte remise, plus rien en attente');
  assert.ok((await page.locator('.groupe[aria-label="À faire"] .carte-titre').allTextContents()).includes('MT23 : préparer le médian'));
});

test("hors ligne : cocher, recharger l'app, puis retour du réseau : l'étape reste cochée et part une seule fois", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, `/#/carte/${CARTES.cabaret}`);
  await page.getByRole('heading', { name: 'Cabaret : faire valider la lineup' }).waitFor();
  serveur.etat.horsService = true;
  await page.getByRole('button', { name: 'Cocher : Valider les horaires avec la team bouffe' }).click();
  await page.getByText('1 note attend le réseau. Elle partira toute seule.').waitFor();
  await page.reload();
  await page.getByRole('button', { name: 'Décocher : Valider les horaires avec la team bouffe' }).waitFor();
  await page.getByText('1 note attend le réseau. Elle partira toute seule.').waitFor();
  serveur.etat.horsService = false;
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await attendre(() => serveur.appels('taches/cocher').length === 1, 'envoyée au retour du réseau');
  await fileVide(page);
  assert.equal(carteDuServeur(serveur, 'cabaret').checklists[0].elements[0].coche, true);
});

test('réponse perdue : le renvoi porte le même id_client et le serveur ne refait rien', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, `/#/carte/${CARTES.formation}`);
  await page.getByRole('heading', { name: 'Formation 2 : relire le deck, tester les exercices' }).waitFor();
  serveur.etat.perdre = 'taches/cocher';
  await page.getByRole('button', { name: 'Cocher : Relire le deck en mode présentation' }).click();
  await attendre(() => serveur.appels('taches/cocher').length >= 1, 'premier envoi');
  // Chromium renvoie parfois seul une requête restée sans réponse ; sinon, la note attend et la file la renvoie
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await attendre(() => serveur.appels('taches/cocher').length >= 2, 'renvoi');
  assert.equal(new Set(serveur.appels('taches/cocher').map((a) => a.corps.id_client)).size, 1, 'même id_client à chaque envoi');
  await fileVide(page);
});

test('description piégée : le HTML reste du texte, seul le lien https devient un lien, rien ne déborde', async ({ page, contexte, serveur }) => {
  const piege = carteDuServeur(serveur, 'sites');
  piege.titre = `<img src=x onerror="window.pirate=1">${' très long'.repeat(300)}`;
  piege.description = '<script>window.pirate=2</script> javascript:window.pirate=3 voir https://exemple.fr/doc.';
  await preparer(page, contexte, serveur);
  await ouvrir(page, `/#/carte/${CARTES.sites}`);
  await page.locator('.carte-description').waitFor();
  assert.equal(await page.evaluate(() => window.pirate), undefined);
  assert.equal(await page.locator('.carte-ouverte img, .carte-ouverte script').count(), 0);
  assert.deepEqual(await page.locator('.carte-description a').evaluateAll((liens) => liens.map((a) => a.getAttribute('href'))), ['https://exemple.fr/doc']);
  assert.ok(await page.locator('.carte-ouverte-titre').evaluate((e) => e.getBoundingClientRect().right <= window.innerWidth), 'le titre reste dans l\'écran');
});

test("session expirée pendant une écriture : elle attend, l'écran de connexion s'ouvre, puis elle part", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, `/#/carte/${CARTES.apoint}`);
  await page.getByRole('heading', { name: "A'point : tester sur téléphone et clôturer" }).waitFor();
  await contexte.clearCookies();
  await page.getByRole('button', { name: 'Cocher : Vérifier le site en ligne, téléphone compris' }).click();
  await page.getByText('Ta session a expiré. Reconnecte-toi.').waitFor();
  await page.getByLabel('Mot de passe').fill(MOT_DE_PASSE);
  await page.getByRole('button', { name: 'Entrer' }).click();
  await attendre(() => carteDuServeur(serveur, 'apoint').checklists[0].elements[2].coche === true, 'écriture partie après la connexion');
});

test("carte inconnue : « Cette carte n'existe plus dans Trello. »", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/carte/ca9999999999999999999999');
  await page.getByText("Cette carte n'existe plus dans Trello.").waitFor();
});
