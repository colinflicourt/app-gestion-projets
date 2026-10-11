import { test, assert, heure, connecte, ouvrir, attendre } from './cadre.mjs';

// Faux Push de l'iPhone : Notification, PushManager et service worker prêt, avec un journal lu par le test
async function preparer(page, contexte, serveur, { permission = 'default', reponse = 'granted', abonne = false, push = true } = {}) {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
  await page.addInitScript((o) => {
    const journal = { demandes: 0, abonnements: [], desabonnements: 0 };
    window.__push = journal;
    const creer = () => ({
      endpoint: 'https://web.push.apple.com/QGuQ-essai',
      toJSON() { return { endpoint: this.endpoint, keys: { p256dh: 'p256dh-essai', auth: 'auth-essai' } }; },
      async unsubscribe() { journal.desabonnements++; actuel = null; return true; },
    });
    let actuel = o.abonne ? creer() : null;
    window.Notification = class {
      static permission = o.permission;
      static async requestPermission() { journal.demandes++; this.permission = o.reponse; return o.reponse; }
    };
    if (!o.push) {
      delete window.PushManager;
      return;
    }
    const pushManager = {
      async getSubscription() { return actuel; },
      async subscribe(options) {
        journal.abonnements.push({ userVisibleOnly: options.userVisibleOnly, octets: options.applicationServerKey.length });
        actuel = creer();
        return actuel;
      },
    };
    Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: { ready: Promise.resolve({ pushManager }), register: async () => ({}) } });
  }, { permission, reponse, abonne, push });
}

const journal = (page) => page.evaluate(() => window.__push);

test('Moi mène à Réglages : notifications « Pas encore activées », bouton « Activer les notifications », retour sur Moi', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/moi');
  await page.getByRole('link', { name: 'Réglages' }).click();
  await page.getByRole('heading', { name: 'Réglages' }).waitFor();
  await page.getByText('Pas encore activées').waitFor();
  await page.getByRole('button', { name: 'Activer les notifications' }).waitFor();
  assert.equal(await page.locator('.onglet[aria-current="page"]').getAttribute('data-onglet'), 'moi');
  await page.getByRole('button', { name: 'Retour' }).click();
  await page.getByRole('heading', { name: 'Moi' }).waitFor();
});

test("Activer : permission demandée, abonnement avec la clé du serveur (65 octets), envoyé au serveur, « Actives sur cet iPhone »", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByRole('button', { name: 'Activer les notifications' }).click();
  await page.getByText('Actives sur cet iPhone').waitFor();
  const j = await journal(page);
  assert.equal(j.demandes, 1);
  assert.deepEqual(j.abonnements, [{ userVisibleOnly: true, octets: 65 }]);
  assert.equal(serveur.appels('abonnements').at(-1).corps.abonnement.endpoint, 'https://web.push.apple.com/QGuQ-essai');
  await page.getByRole('button', { name: 'Envoyer une notification de test' }).waitFor();
});

test("Activer refusé : « Coupées » et le chemin dans les Réglages de l'iPhone, rien n'est envoyé", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { reponse: 'denied' });
  await ouvrir(page, '/#/reglages');
  await page.getByRole('button', { name: 'Activer les notifications' }).click();
  await page.getByText("Les notifications sont coupées sur cet iPhone. Va dans Réglages de l'iPhone, Notifications, Jarvis Jr.").waitFor();
  await page.getByText('Coupées', { exact: true }).waitFor();
  assert.equal(serveur.appels('abonnements').length, 0);
  assert.equal(await page.getByRole('button', { name: 'Activer les notifications' }).count(), 0);
});

test('Notification de test : envoyée au serveur, puis « Notification envoyée… » ; sans appareil abonné, le dire', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { permission: 'granted', abonne: true });
  await ouvrir(page, '/#/reglages');
  await page.getByRole('button', { name: 'Envoyer une notification de test' }).click();
  await page.getByText('Notification envoyée. Elle arrive dans quelques secondes.').waitFor();
  assert.equal(serveur.appels('notifications/test').length, 1);
  serveur.etat.testEnvoyes = 0;
  await page.getByRole('button', { name: 'Envoyer une notification de test' }).click();
  await page.getByText("Aucun appareil n'est abonné. Réactive les notifications sur cet iPhone.").waitFor();
});

test("À l'ouverture de l'app, un abonnement existant est renvoyé au serveur (iOS n'a pas pushsubscriptionchange)", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { permission: 'granted', abonne: true });
  await ouvrir(page, '/#/aujourdhui');
  await attendre(() => serveur.appels('abonnements').length === 1, 'abonnement renvoyé');
});

test("Se déconnecter : le serveur oublie l'abonnement avant la fin de session, puis l'iPhone se désabonne", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { permission: 'granted', abonne: true });
  await ouvrir(page, '/#/moi');
  await page.getByRole('button', { name: 'Se déconnecter' }).click();
  await page.getByRole('heading', { name: 'JARVIS JR.' }).waitFor();
  const routes = serveur.etat.appels.map((a) => a.route);
  assert.ok(routes.indexOf('abonnements/supprimer') !== -1 && routes.indexOf('abonnements/supprimer') < routes.indexOf('deconnexion'), routes.join(', '));
  assert.equal(serveur.appels('abonnements/supprimer')[0].corps.endpoint, 'https://web.push.apple.com/QGuQ-essai');
  assert.equal((await journal(page)).desabonnements, 1);
});

test("Navigateur sans notifications web (Safari hors de l'app installée) : « Indisponibles ici », l'explication, aucun bouton", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { push: false });
  await ouvrir(page, '/#/reglages');
  await page.getByText('Indisponibles ici').waitFor();
  await page.getByText("Les notifications marchent seulement dans l'app installée sur l'écran d'accueil (iOS 16.4 ou plus).").waitFor();
  assert.equal(await page.getByRole('button', { name: 'Activer les notifications' }).count(), 0);
});
