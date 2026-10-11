import { test, assert, connecte, ouvrir, attendre } from './cadre.mjs';

// Un push livré au vrai service worker par le protocole de Chromium (ce que fait le bouton « Push » des DevTools)
async function pousser(page, contexte, serveur, donnees) {
  const cdp = await contexte.newCDPSession(page);
  const inscriptions = [];
  cdp.on('ServiceWorker.workerRegistrationUpdated', (e) => inscriptions.push(...e.registrations));
  await cdp.send('ServiceWorker.enable');
  await attendre(() => inscriptions.some((r) => !r.isDeleted), 'inscription du service worker');
  const { registrationId } = inscriptions.find((r) => !r.isDeleted);
  await cdp.send('ServiceWorker.deliverPushMessage', { origin: serveur.origine, registrationId, data: donnees });
  await cdp.detach();
}

const affichees = (page) => page.evaluate(async () => {
  const inscription = await navigator.serviceWorker.ready;
  return (await inscription.getNotifications()).map((n) => ({ titre: n.title, texte: n.body, url: n.data?.url, tag: n.tag }));
});

async function attendreNotification(page, tag) {
  let trouvee;
  await attendre(async () => {
    trouvee = (await affichees(page)).find((n) => n.tag === tag);
    return trouvee !== undefined;
  }, `notification ${tag}`, 10000);
  return trouvee;
}

test('service worker : un push affiche sa notification, un push illisible celle de repli, une adresse externe est ramenée dans l\'app', async ({ page, contexte, serveur }) => {
  await contexte.grantPermissions(['notifications'], { origin: serveur.origine });
  await connecte(contexte, serveur);
  await ouvrir(page, '/#/aujourdhui');
  await page.evaluate(() => navigator.serviceWorker.ready);

  await pousser(page, contexte, serveur, JSON.stringify({ titre: 'Essai', texte: 'Bonjour', url: '/#/taches', tag: 'essai' }));
  assert.deepEqual(await attendreNotification(page, 'essai'), { titre: 'Essai', texte: 'Bonjour', url: `${serveur.origine}/#/taches`, tag: 'essai' });

  await pousser(page, contexte, serveur, 'pas du json');
  assert.deepEqual(await attendreNotification(page, 'jr'), { titre: 'Jarvis Jr.', texte: 'Jarvis Jr. a quelque chose pour toi.', url: `${serveur.origine}/#/aujourdhui`, tag: 'jr' });

  await pousser(page, contexte, serveur, JSON.stringify({ titre: 'Piège', texte: 'x', url: 'https://exemple.fr/', tag: 'piege' }));
  assert.equal((await attendreNotification(page, 'piege')).url, `${serveur.origine}/#/aujourdhui`);
}, { serviceWorkers: 'allow', chromiumComplet: true });
