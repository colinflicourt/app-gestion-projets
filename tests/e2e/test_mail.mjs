// L'écran d'un mail (SPEC § 17.4 et § 17.10) : ouverture, retour, cadre isolé, images, liens, pièces, erreurs, mail piégé.
import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';

const MERCREDI = '2026-10-14T15:45:00+02:00';
const NOMS = { utc: 'UTC', qyris: 'QYRIS', gmail: 'Gmail', icloud: 'iCloud' };

async function boite(page, contexte, serveur, cle) {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  await ouvrir(page, '/#/mails');
  await page.locator('.mails-boite', { hasText: NOMS[cle] }).click();
  await page.getByRole('region', { name: NOMS[cle] }).waitFor();
}

// Ouvre un mail depuis la liste de sa boîte, sans recharger la page
async function ouvrirMail(page, contexte, serveur, cle, objet) {
  await boite(page, contexte, serveur, cle);
  await page.locator('.mail-corps', { hasText: objet }).click();
  await page.locator('.mail-ecran').waitFor();
}

const cadre = (page) => page.frameLocator('iframe.mail-cadre');
const sansDebord = (page) => page.evaluate(() => [...document.querySelectorAll('#app *')]
  .filter((e) => e.getClientRects().length > 0 && e.getBoundingClientRect().right > window.innerWidth + 1)
  .map((e) => `${e.tagName.toLowerCase()}.${e.className}`));

test('mail : toucher un mail de la liste l\'ouvre à #/mails/<boîte>/<compte>/<uid>, en-têtes, expéditeur, date complète, À et Cc', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'utc', 'Inscription aux UV de A27');
  assert.equal(new URL(page.url()).hash, '#/mails/utc/utc/101');
  await page.getByRole('heading', { name: 'Inscription aux UV de A27' }).waitFor();
  assert.equal(await texte(page, '.mail-de-nom'), 'Secrétariat GI');
  assert.equal(await texte(page, '.mail-de-adresse'), 'secretariat-gi@utc.fr');
  assert.equal(await texte(page, '.mail-date'), 'mer. 14/10 à 14 h 32');
  assert.deepEqual(await page.locator('.mail-dest > span').allTextContents(), ['À Colin Flicourt, promo-gi@utc.fr', 'Cc Prof. Durand']);
  assert.equal(await page.locator('.mail-etiquette').count(), 0);
  assert.equal(await page.locator('.onglet[aria-current="page"]').textContent(), 'Mails');
  assert.equal(serveur.appels('mails/ouvrir').length, 1);
  assert.deepEqual(serveur.appels('mails/ouvrir')[0].corps, { compte: 'utc', uid: 101 });
  assert.equal(serveur.appels('mails/corps').length, 1, 'le corps part en même temps');
  assert.deepEqual(serveur.appels('mails/corps')[0].parametres, { compte: 'utc', uid: '101' });
});

test('mail : « Retour à la boîte » ramène à la boîte, le mail est lu et les compteurs baissent sans relire la boîte', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'utc', 'Inscription aux UV de A27');
  await page.getByRole('heading', { name: 'Inscription aux UV de A27' }).waitFor();
  const rafraichissements = serveur.appels('mails/rafraichir').length;
  await page.getByRole('button', { name: 'Retour à la boîte' }).click();
  const utc = page.getByRole('region', { name: 'UTC' });
  await utc.waitFor();
  assert.equal(new URL(page.url()).hash, '#/mails/utc');
  assert.deepEqual(await utc.locator('.mail').evaluateAll((els) => els.map((e) => e.classList.contains('non-lu'))), [false, true, false]);
  assert.equal(await utc.locator('.section-titre').textContent(), 'Derniers mails1 non lu');
  await page.getByRole('button', { name: 'Retour aux boîtes' }).click();
  await page.locator('.mails-boite').first().waitFor();
  assert.equal(await page.locator('.mails-boite-etat').first().textContent(), '1 non lu');
  await page.locator('.onglet[data-onglet="aujourdhui"]').click();
  await page.locator('.mails-resume').waitFor();
  assert.equal(await texte(page, '.mails-resume'), '3 non lus · UTC 1 · QYRIS 1 · Gmail 1');
  assert.equal(serveur.appels('mails/rafraichir').length, rafraichissements, 'aucune relecture de la boîte à l\'ouverture');
  assert.equal(serveur.appels('mails/ouvrir').length, 1);
});

test('mail : la copie locale (IndexedDB) garde le mail lu, hors ligne aussi', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'utc', 'Inscription aux UV de A27');
  await page.getByRole('heading', { name: 'Inscription aux UV de A27' }).waitFor();
  await page.getByRole('button', { name: 'Retour à la boîte' }).click();
  await page.getByRole('region', { name: 'UTC' }).waitFor();
  await page.waitForTimeout(200);
  serveur.etat.horsService = true;
  await page.reload();
  await page.waitForFunction(() => document.documentElement.dataset.pret === '1');
  const utc = page.getByRole('region', { name: 'UTC' });
  await utc.waitFor();
  assert.deepEqual(await utc.locator('.mail').evaluateAll((els) => els.map((e) => e.classList.contains('non-lu'))), [false, true, false]);
  await page.goto('/#/aujourdhui');
  await page.locator('.mails-resume').waitFor();
  assert.equal(await texte(page, '.mails-resume'), '3 non lus · UTC 1 · QYRIS 1 · Gmail 1');
});

test('mail : un mail déjà lu ne change aucun compteur', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'qyris', 'Question sur la formation');
  await page.getByRole('heading', { name: 'Question sur la formation' }).waitFor();
  assert.equal(await texte(page, '.mail-etiquette'), 'contact@');
  await page.getByRole('button', { name: 'Retour à la boîte' }).click();
  const qyris = page.getByRole('region', { name: 'QYRIS' });
  await qyris.waitFor();
  assert.equal(await qyris.locator('.section-titre').textContent(), 'Derniers mails1 non lu');
});

test('mail : un mail HTML s\'affiche dans un cadre isolé, hauteur du contenu, image insérée (cid) visible', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'utc', 'Inscription aux UV de A27');
  await cadre(page).getByRole('heading', { name: 'Inscriptions aux UV de A27' }).waitFor();
  const iframe = page.locator('iframe.mail-cadre');
  assert.equal(await iframe.getAttribute('sandbox'), 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
  assert.equal(await iframe.getAttribute('title'), 'Corps du mail');
  assert.equal(new URL(await iframe.getAttribute('src'), 'http://test').pathname, '/api/mails/corps');
  await page.waitForFunction(() => {
    const f = document.querySelector('iframe.mail-cadre');
    const img = f.contentDocument.getElementById('cid');
    return img?.complete && img.naturalWidth > 0;
  });
  const mesures = await iframe.evaluate((f) => ({ cadre: f.getBoundingClientRect().height, contenu: f.contentDocument.documentElement.scrollHeight, fond: getComputedStyle(f.contentDocument.body).backgroundColor }));
  assert.ok(Math.abs(mesures.cadre - mesures.contenu) <= 2, `hauteur du cadre ${mesures.cadre} px pour un contenu de ${mesures.contenu} px`);
  assert.ok(mesures.cadre > 150);
  assert.equal(mesures.fond, 'rgb(255, 255, 255)', 'un mail HTML reste sur fond clair');
});

test('mail : images distantes bloquées (« Images masquées. »), « Afficher les images » recharge le cadre avec images=1', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'utc', 'Inscription aux UV de A27');
  await page.locator('.mail-images', { hasText: 'Images masquées.' }).waitFor();
  assert.equal(await cadre(page).locator('#distante').count(), 0);
  const avant = await page.locator('iframe.mail-cadre').evaluate((f) => f.getBoundingClientRect().height);
  await page.getByRole('button', { name: 'Afficher les images' }).click();
  await cadre(page).locator('#distante').waitFor();
  assert.equal(await page.locator('.mail-images').isVisible(), false);
  await attendre(() => serveur.appels('mails/corps').length === 2, 'second chargement du corps');
  assert.deepEqual(serveur.appels('mails/corps')[1].parametres, { compte: 'utc', uid: '101', images: '1' });
  assert.equal(serveur.appels('mails/ouvrir').length, 1, 'le mail n\'est pas rouvert');
  await page.waitForFunction((h) => document.querySelector('iframe.mail-cadre').getBoundingClientRect().height > h + 40, avant);
  assert.equal(await page.locator('iframe.mail-cadre').getAttribute('sandbox'), 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
});

test('mail : un mail texte avec lien, le clic est intercepté (nouvel onglet, aucune navigation du cadre ni de l\'app)', async ({ page, contexte, serveur }) => {
  await contexte.route('https://exemple.fr/**', (route) => route.fulfill({ contentType: 'text/html', body: '<p>Sujet</p>' }));
  await ouvrirMail(page, contexte, serveur, 'utc', 'TD de jeudi déplacé');
  const lien = cadre(page).getByRole('link', { name: 'https://exemple.fr/sujet' });
  await lien.waitFor();
  const [popup] = await Promise.all([page.waitForEvent('popup'), lien.click()]);
  assert.equal(popup.url(), 'https://exemple.fr/sujet');
  await popup.close();
  const urlCadre = await page.locator('iframe.mail-cadre').evaluate((f) => f.contentWindow.location.href);
  assert.ok(urlCadre.includes('/api/mails/corps?compte=utc&uid=102'), `le cadre n'a pas navigué : ${urlCadre}`);
  assert.equal(new URL(page.url()).hash, '#/mails/utc/utc/102');
  await cadre(page).getByRole('link', { name: 'durand@utc.fr' }).click();
  assert.equal(new URL(page.url()).hash, '#/mails/utc/utc/102', 'mailto : l\'écran reste ouvert');
  assert.equal(await page.locator('iframe.mail-cadre').evaluate((f) => f.contentWindow.location.href), urlCadre);
});

test('mail : les pièces sont listées avec nom, icône et taille, un toucher ouvre le fichier dans un nouvel onglet', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'utc', 'TD de jeudi déplacé');
  await page.locator('.mail-pieces').waitFor();
  assert.deepEqual(await page.locator('.mail-piece').evaluateAll((els) => els.map((e) => [e.querySelector('.mail-piece-nom').textContent, e.querySelector('.mail-piece-taille').textContent, e.querySelectorAll('svg').length])),
    [['Sujet-TD3.pdf', '1,2 Mo', 1], ['donnees-mesures.xlsx', '350 Ko', 1], ['Archive-complete.zip', '24 Mo', 1]]);
  const premiere = page.locator('a.mail-piece').first();
  assert.equal(await premiere.getAttribute('target'), '_blank');
  assert.equal(await premiere.getAttribute('rel'), 'noopener');
  assert.equal(await premiere.getAttribute('href'), '/api/mails/piece?compte=utc&uid=102&partie=2');
  const reponse = await page.request.get(await premiere.evaluate((a) => a.href));
  assert.equal(reponse.status(), 200);
  assert.match(reponse.headers()['content-disposition'], /^inline; filename="Sujet-TD3\.pdf"/);
  assert.equal(await page.locator('a.mail-piece').count(), 2);
  assert.equal(await page.locator('button.mail-piece').count(), 1, 'la pièce trop lourde n\'est pas un lien');
  assert.equal(await page.getByText('image insérée').count(), 0, 'une image insérée (cid) n\'est pas listée');
});

test('mail : une pièce trop lourde dit « Trop lourde pour Junior : ouvre-la dans le webmail UTC. » sans rien télécharger', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'utc', 'TD de jeudi déplacé');
  await page.getByRole('button', { name: /Archive-complete\.zip/ }).click();
  await page.locator('.message', { hasText: 'Trop lourde pour Junior : ouvre-la dans le webmail UTC.' }).waitFor();
  assert.equal(serveur.appels('mails/piece').length, 0);
});

test('mail : un mail HTML piégé, servi sans nettoyage ni en-têtes, ne s\'exécute pas (le cadre sandbox seul)', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'qyris', 'Devis du site');
  await cadre(page).locator('#texte').waitFor();
  await page.waitForTimeout(300);
  const titre = await page.title();
  assert.ok(!titre.includes('PIRATE'), `titre de l'app : ${titre}`);
  const dans = await page.locator('iframe.mail-cadre').evaluate((f) => ({
    script: f.contentDocument.querySelectorAll('script').length,
    onerror: f.contentDocument.getElementById('piege').getAttribute('onerror') !== null,
    pirate: f.contentWindow.__pirate ?? null,
  }));
  assert.deepEqual(dans, { script: 1, onerror: true, pirate: null }, 'la page piégée est bien arrivée telle quelle, sans que rien ne tourne');
  await cadre(page).locator('#js').click();
  assert.ok(!(await page.title()).includes('PIRATE'), 'javascript: ne s\'exécute pas non plus');
  assert.equal(new URL(page.url()).hash, '#/mails/qyris/colin/201');
});

test('mail : « Répondre » ouvre repondre_url (adresse web : nouvel onglet)', async ({ page, contexte, serveur }) => {
  await contexte.route('https://webmail.utc.fr/**', (route) => route.fulfill({ contentType: 'text/html', body: '<p>Webmail</p>' }));
  await ouvrirMail(page, contexte, serveur, 'utc', 'TD de jeudi déplacé');
  const [popup] = await Promise.all([page.waitForEvent('popup'), page.getByRole('button', { name: 'Répondre' }).click()]);
  assert.equal(popup.url(), 'https://webmail.utc.fr/?_task=mail&_action=compose&_to=durand%40utc.fr&_subject=Re%3A%20TD%20de%20jeudi%20d%C3%A9plac%C3%A9');
});

test('mail : « Répondre » dans Gmail essaie l\'app, puis la page web de repli si l\'écran est encore visible 1,5 s après', async ({ page, contexte, serveur }) => {
  await contexte.route('https://mail.google.com/**', (route) => route.fulfill({ contentType: 'text/html', body: '<p>Gmail</p>' }));
  await ouvrirMail(page, contexte, serveur, 'gmail', 'Nouvelle connexion détectée');
  const requete = page.waitForRequest((r) => r.url().startsWith('https://mail.google.com/mail/?view=cm'));
  await page.getByRole('button', { name: 'Répondre' }).click();
  const r = await requete;
  assert.equal(r.url(), 'https://mail.google.com/mail/?view=cm&to=support%40exemple-hebergeur.fr&su=Re%3A%20Nouvelle%20connexion%20d%C3%A9tect%C3%A9e');
});

test('mail : « Ce mail n\'est plus dans la boîte. » (absent), sans cadre ni « Répondre »', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'utc', 'Compte rendu de la réunion');
  await page.locator('.mails-avis').waitFor();
  assert.equal(await texte(page, '.mails-avis'), 'Ce mail n\'est plus dans la boîte.');
  assert.equal(await page.locator('iframe.mail-cadre').isVisible(), false);
  assert.equal(await page.getByRole('button', { name: 'Répondre' }).count(), 0);
  assert.equal(await page.getByRole('button', { name: 'Retour à la boîte' }).count(), 1);
});

test('mail : un mail hors de la copie du serveur dit qu\'il est trop ancien, avec le bouton de la boîte', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  await ouvrir(page, '/#/mails/utc/utc/999');
  await page.locator('.mails-avis').waitFor();
  assert.equal(await texte(page, '.mails-avis'), 'Ce mail est trop ancien pour Junior : ouvre-le dans le webmail UTC.');
  assert.equal(await page.getByRole('link', { name: 'Ouvrir le webmail UTC' }).getAttribute('href'), 'https://webmail.utc.fr');
  assert.equal(await page.locator('iframe.mail-cadre').isVisible(), false);
  await page.getByRole('button', { name: 'Retour à la boîte' }).click();
  await page.getByRole('region', { name: 'UTC' }).waitFor();
  assert.equal(new URL(page.url()).hash, '#/mails/utc');
});

test('mail : boîte qui ne répond pas, « Ce mail ne s\'ouvre pas pour l\'instant. » et le bouton de la boîte, cadre masqué', async ({ page, contexte, serveur }) => {
  serveur.etat.pannes['mails/ouvrir'] = { statut: 400, code: 'injoignable', message: 'Pas de réponse.' };
  await ouvrirMail(page, contexte, serveur, 'gmail', 'Nouvelle connexion détectée');
  await page.locator('.mails-avis').waitFor();
  assert.equal(await texte(page, '.mails-avis'), 'Ce mail ne s\'ouvre pas pour l\'instant.');
  assert.equal(await page.getByRole('button', { name: 'Ouvrir dans Gmail' }).count(), 1);
  assert.equal(await page.locator('iframe.mail-cadre').isVisible(), false);
  assert.equal(await page.getByRole('button', { name: 'Répondre' }).count(), 0);
});

test('mail : hors ligne, le mail ne s\'ouvre pas et rien du contenu n\'est servi', async ({ page, contexte, serveur }) => {
  await boite(page, contexte, serveur, 'utc');
  serveur.etat.horsService = true;
  await page.locator('.mail-corps', { hasText: 'Inscription aux UV de A27' }).click();
  await page.locator('.mails-avis').waitFor();
  assert.equal(await texte(page, '.mails-avis'), 'Ce mail ne s\'ouvre pas pour l\'instant.');
  assert.equal(await page.locator('iframe.mail-cadre').isVisible(), false);
  assert.equal(await page.locator('.mail-pieces').count(), 0);
});

test('mail : l\'écran n\'est pas refait quand l\'app se redessine (le cadre ne se recharge pas)', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'utc', 'TD de jeudi déplacé');
  await cadre(page).getByRole('link', { name: 'https://exemple.fr/sujet' }).waitFor();
  await page.evaluate(() => { document.querySelector('iframe.mail-cadre').dataset.marque = 'oui'; });
  await page.evaluate(() => window.dispatchEvent(new HashChangeEvent('hashchange')));
  await page.waitForTimeout(150);
  assert.equal(await page.locator('iframe.mail-cadre').getAttribute('data-marque'), 'oui');
  assert.equal(serveur.appels('mails/corps').length, 1);
});

test('mail : une adresse mal formée garde la boîte', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  await ouvrir(page, '/#/mails/utc/gmail/101');
  await page.getByRole('region', { name: 'UTC' }).waitFor();
  assert.equal(serveur.appels('mails/ouvrir').length, 0);
});

test('mail : rien du contenu n\'est gardé (IndexedDB) ni mis en cache par le service worker', async ({ page, contexte, serveur }) => {
  await ouvrirMail(page, contexte, serveur, 'gmail', 'Nouvelle connexion détectée');
  await cadre(page).getByText('Corps-confidentiel-gmail').waitFor();
  await page.waitForFunction(() => Boolean(navigator.serviceWorker?.controller));
  const gardes = await page.evaluate(async () => {
    const urls = [];
    for (const nom of await caches.keys()) for (const r of await (await caches.open(nom)).keys()) urls.push(r.url);
    const bases = await indexedDB.databases();
    let idb = '';
    for (const { name } of bases) {
      const db = await new Promise((ok, ko) => { const r = indexedDB.open(name); r.onsuccess = () => ok(r.result); r.onerror = () => ko(r.error); });
      for (const magasin of db.objectStoreNames) {
        const tout = await new Promise((ok, ko) => { const r = db.transaction(magasin).objectStore(magasin).getAll(); r.onsuccess = () => ok(r.result); r.onerror = () => ko(r.error); });
        idb += JSON.stringify(tout);
      }
      db.close();
    }
    return { urls, idb };
  });
  assert.ok(gardes.urls.length > 0, 'le cache de l\'app existe');
  assert.deepEqual(gardes.urls.filter((u) => u.includes('/api/')), [], 'aucune réponse de l\'API en cache');
  assert.ok(!gardes.idb.includes('Corps-confidentiel-gmail'), 'le corps n\'est pas dans IndexedDB');
  assert.ok(gardes.idb.includes('Nouvelle connexion détectée'), 'la copie de la liste y est, elle');
}, { serviceWorkers: 'allow' });

test('mail : l\'écran tient à 375, 390 et 768 px (mail HTML, mail texte, erreur), rien ne déborde', async ({ page, contexte, serveur }) => {
  await connecte(contexte, serveur);
  await heure(page, MERCREDI);
  await ouvrir(page, '/#/mails/utc/utc/101');
  await page.getByRole('heading', { name: 'Inscription aux UV de A27' }).waitFor();
  for (const largeur of [375, 390, 768]) {
    await page.setViewportSize({ width: largeur, height: 844 });
    for (const adresse of ['#/mails/utc/utc/101', '#/mails/utc/utc/102', '#/mails/utc/utc/103']) {
      await page.evaluate((a) => { location.hash = a; }, adresse);
      await page.waitForTimeout(250);
      assert.deepEqual(await sansDebord(page), [], `${adresse} à ${largeur} px`);
      const cadreLargeur = await page.locator('iframe.mail-cadre').evaluate((f) => (f.hidden ? 0 : f.getBoundingClientRect().width));
      assert.ok(cadreLargeur <= largeur, `cadre de ${cadreLargeur} px à ${largeur} px`);
    }
  }
});
