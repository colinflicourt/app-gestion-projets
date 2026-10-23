import { test, assert, heure, connecte, ouvrir, attendre } from './cadre.mjs';
import { JETON_SIRI } from './fixtures.mjs';

// Faux Push de l'iPhone : Notification, PushManager et service worker prêt, avec un journal lu par le test
async function preparer(page, contexte, serveur, { permission = 'default', reponse = 'granted', abonne = false, push = true, echecAbonnement = false } = {}) {
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
        if (o.echecAbonnement) throw new DOMException('Registration failed - push service error', 'AbortError');
        journal.abonnements.push({ userVisibleOnly: options.userVisibleOnly, octets: options.applicationServerKey.length });
        actuel = creer();
        return actuel;
      },
    };
    Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: { ready: Promise.resolve({ pushManager }), register: async () => ({}) } });
  }, { permission, reponse, abonne, push, echecAbonnement });
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
  await ouvrir(page, '/#/reglages');
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

test("Activer, abonnement impossible : message en français, pas celui du navigateur", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { echecAbonnement: true });
  await ouvrir(page, '/#/reglages');
  await page.getByRole('button', { name: 'Activer les notifications' }).click();
  await page.getByText("Je n'ai pas pu activer les notifications. Réessaie dans un moment.").waitFor();
  assert.equal(await page.getByText('Registration failed', { exact: false }).count(), 0);
  assert.equal(serveur.appels('abonnements').length, 0);
});

test("Notifications : l'état connu s'affiche tout de suite, même si le service worker n'est jamais prêt", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur, { permission: 'granted', abonne: true });
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: { ready: new Promise(() => {}), register: async () => ({}) } });
  });
  await ouvrir(page, '/#/reglages');
  await page.getByText('Actives sur cet iPhone').waitFor({ timeout: 1500 });
});

const ordre = ['Notifications', 'Brief du matin', 'Rappels', 'Comptes mail', 'Siri', 'Session'];
const corpsRappels = (serveur) => serveur.appels('reglages/rappels').at(-1).corps;

test('Réglages : les parties dans l\'ordre, valeurs lues dans GET reglages', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByRole('heading', { name: 'Siri' }).waitFor();
  const titres = await page.locator('h2.section-titre').allTextContents();
  assert.deepEqual(titres.map((t) => t.trim()), ordre);
  assert.equal(await page.getByLabel('Heure', { exact: true }).inputValue(), '07:30');
  assert.equal(await page.getByRole('button', { name: 'Lundi' }).getAttribute('aria-pressed'), 'true');
  assert.equal(await page.getByRole('switch', { name: 'Avant les cours' }).getAttribute('aria-checked'), 'true');
  assert.equal(await page.getByRole('button', { name: '15 min' }).getAttribute('aria-pressed'), 'true');
  assert.equal(await page.getByRole('button', { name: '5 min', exact: true }).getAttribute('aria-pressed'), 'false');
  assert.equal(await page.getByLabel('Heure des échéances').inputValue(), '20:00');
  assert.equal(await page.getByRole('button', { name: 'J-14' }).getAttribute('aria-pressed'), 'false');
  assert.equal(await page.getByRole('button', { name: 'J-7' }).getAttribute('aria-pressed'), 'true');
  assert.equal(await page.getByLabel('Heure des médians').inputValue(), '09:00');
});

test("Modifier l'heure du brief : l'objet complet part par la file, « Réglages enregistrés. »", async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByLabel('Heure', { exact: true }).fill('06:45');
  await page.getByText('Réglages enregistrés.').waitFor();
  await attendre(() => serveur.appels('reglages/rappels').length === 1, 'écriture');
  const c = corpsRappels(serveur);
  assert.ok(c.id_client);
  assert.deepEqual(c.rappels, { ...serveur.etat.reglages.rappels });
  assert.deepEqual(c.rappels, {
    brief: { heure: '06:45', jours: [1, 2, 3, 4, 5, 6, 7] },
    cours: { actif: true, minutes: 15 },
    echeances: { actif: true, heure: '20:00' },
    medians: { actif: true, jours: [7, 3, 1], heure: '09:00' },
  });
  assert.equal(await page.getByLabel('Heure', { exact: true }).inputValue(), '06:45');
});

test("Modifier un jour du brief : corps complet, le jour s'éteint ; le dernier jour allumé ne s'éteint pas", async ({ page, contexte, serveur }) => {
  serveur.etat.reglages.rappels.brief.jours = [1, 3];
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByRole('button', { name: 'Mercredi' }).click();
  await attendre(() => serveur.appels('reglages/rappels').length === 1, 'écriture');
  assert.deepEqual(corpsRappels(serveur).rappels.brief, { heure: '07:30', jours: [1] });
  assert.equal(corpsRappels(serveur).rappels.medians.heure, '09:00');
  assert.equal(await page.getByRole('button', { name: 'Mercredi' }).getAttribute('aria-pressed'), 'false');
  await page.getByRole('button', { name: 'Lundi' }).click();
  await page.waitForTimeout(300);
  assert.equal(serveur.appels('reglages/rappels').length, 1);
  assert.equal(await page.getByRole('button', { name: 'Lundi' }).getAttribute('aria-pressed'), 'true');
  await page.getByRole('button', { name: 'Dimanche' }).click();
  await attendre(() => serveur.appels('reglages/rappels').length === 2, 'écriture');
  assert.deepEqual(corpsRappels(serveur).rappels.brief.jours, [1, 7]);
});

test('Rappels : interrupteur éteint (réglages atténués mais visibles), minutes, médians dont le dernier ne s\'éteint pas', async ({ page, contexte, serveur }) => {
  serveur.etat.reglages.rappels.medians.jours = [7];
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByRole('switch', { name: 'Avant les cours' }).click();
  await attendre(() => serveur.appels('reglages/rappels').length === 1, 'écriture');
  assert.equal(corpsRappels(serveur).rappels.cours.actif, false);
  assert.equal(await page.getByRole('switch', { name: 'Avant les cours' }).getAttribute('aria-checked'), 'false');
  assert.equal(await page.getByRole('button', { name: '30 min' }).isVisible(), true);
  assert.equal(await page.locator('.reglage-bloc.eteint').count(), 1);
  await page.getByRole('button', { name: '30 min' }).click();
  await attendre(() => serveur.appels('reglages/rappels').length === 2, 'écriture');
  assert.deepEqual(corpsRappels(serveur).rappels.cours, { actif: false, minutes: 30 });
  await page.getByRole('button', { name: 'J-7' }).click();
  await page.waitForTimeout(300);
  assert.equal(serveur.appels('reglages/rappels').length, 2);
  await page.getByRole('button', { name: 'J-14' }).click();
  await attendre(() => serveur.appels('reglages/rappels').length === 3, 'écriture');
  assert.deepEqual(corpsRappels(serveur).rappels.medians.jours, [14, 7]);
  await page.getByRole('switch', { name: 'Échéances, la veille à' }).click();
  await attendre(() => serveur.appels('reglages/rappels').length === 4, 'écriture');
  assert.equal(corpsRappels(serveur).rappels.echeances.actif, false);
  await page.getByLabel('Heure des médians').fill('08:15');
  await attendre(() => serveur.appels('reglages/rappels').length === 5, 'écriture');
  assert.equal(corpsRappels(serveur).rappels.medians.heure, '08:15');
});

test('Hors ligne : la modification attend dans la file', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByRole('heading', { name: 'Siri' }).waitFor();
  await contexte.setOffline(true);
  await page.getByRole('switch', { name: 'Médians' }).click();
  await page.getByText("Pas de réseau. Je l'envoie dès que ça revient.").waitFor();
  assert.equal(await page.getByRole('switch', { name: 'Médians' }).getAttribute('aria-checked'), 'false');
  assert.equal(serveur.appels('reglages/rappels').length, 0);
});

test('Planificateur : 3 états (il y a 1 min, arrêté depuis 2 h, pas encore lancé)', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByText('Dernier passage il y a 1 min.').waitFor();
  serveur.etat.reglages.planificateur.dernier_passage = '2026-10-14T13:44:00+02:00';
  await page.reload();
  await page.getByText('Le planificateur ne tourne plus depuis 2 h. Tes rappels ne partent pas.').waitFor();
  serveur.etat.reglages.planificateur.dernier_passage = null;
  await page.reload();
  await page.getByText("Le planificateur n'est pas encore lancé.").waitFor();
});

const stockageJeton = (page) => page.evaluate(async (jeton) => {
  const trouve = [];
  for (const [nom, magasin] of [['local', localStorage], ['session', sessionStorage]]) {
    for (let i = 0; i < magasin.length; i++) if ((magasin.getItem(magasin.key(i)) ?? '').includes(jeton) || magasin.key(i).includes(jeton)) trouve.push(nom);
  }
  for (const { name } of await indexedDB.databases()) {
    const base = await new Promise((ok) => { const r = indexedDB.open(name); r.onsuccess = () => ok(r.result); });
    for (const magasin of base.objectStoreNames) {
      const tout = await new Promise((ok) => { const r = base.transaction(magasin).objectStore(magasin).getAll(); r.onsuccess = () => ok(r.result); });
      if (JSON.stringify(tout).includes(jeton)) trouve.push(`indexedDB:${magasin}`);
    }
    base.close();
  }
  return trouve;
}, JETON_SIRI);

test("Siri : création du jeton, affichage, « Copier » puis « Copié. », mode d'emploi, jamais stocké", async ({ page, contexte, serveur }) => {
  await contexte.grantPermissions(['clipboard-read', 'clipboard-write']);
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByRole('button', { name: 'Créer le jeton Siri' }).click();
  await page.getByText(JETON_SIRI, { exact: true }).waitFor();
  assert.equal(serveur.appels('reglages/siri').length, 1);
  await page.getByText('Copie-le maintenant : il ne sera plus affiché.').waitFor();
  assert.equal(await page.locator('.etapes-siri li').count(), 7);
  assert.equal(await page.getByRole('button', { name: 'Créer le jeton Siri' }).count(), 0);
  await page.getByRole('button', { name: 'Copier' }).click();
  await page.getByRole('button', { name: 'Copié.' }).waitFor();
  assert.equal(await page.evaluate(() => navigator.clipboard.readText()), JETON_SIRI);
  assert.deepEqual(await stockageJeton(page), []);
  assert.equal(serveur.etat.appels.some((a) => JSON.stringify(a.corps).includes(JETON_SIRI)), false);
  await page.getByRole('button', { name: 'Retour' }).click();
  await page.getByRole('link', { name: 'Réglages' }).click();
  await page.getByText('Un jeton existe. Le changer coupe l\'ancien raccourci.').waitFor();
  assert.equal(await page.getByText(JETON_SIRI).count(), 0);
});

test('Siri : jeton existant, « Changer le jeton » demande confirmation', async ({ page, contexte, serveur }) => {
  serveur.etat.reglages.siri.jeton_cree = true;
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByText("Un jeton existe. Le changer coupe l'ancien raccourci.").waitFor();
  let message = '';
  page.once('dialog', (d) => { message = d.message(); d.dismiss(); });
  await page.getByRole('button', { name: 'Changer le jeton' }).click();
  await attendre(() => message !== '', 'confirmation');
  assert.equal(serveur.appels('reglages/siri').length, 0);
  page.once('dialog', (d) => d.accept());
  await page.getByRole('button', { name: 'Changer le jeton' }).click();
  await page.getByText(JETON_SIRI, { exact: true }).waitFor();
  assert.equal(serveur.appels('reglages/siri').length, 1);
});

test('Siri hors ligne : bouton désactivé, texte du hors ligne', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByRole('heading', { name: 'Siri' }).waitFor();
  await contexte.setOffline(true);
  await page.getByText('Pas de réseau. Réessaie quand ça revient.').waitFor();
  assert.equal(await page.getByRole('button', { name: 'Créer le jeton Siri' }).isDisabled(), true);
});

// Réglages > Comptes mail (SPEC § 16.8)
const compteMail = (page, nom) => page.getByRole('region', { name: nom });
const envois = (serveur, route) => serveur.appels(route).filter((a) => a.methode === 'POST');
const corpsMail = (serveur, route) => envois(serveur, route).map((a) => a.corps);

const stockageMotDePasse = (page, secret) => page.evaluate(async (valeur) => {
  const trouve = [];
  for (const [nom, magasin] of [['local', localStorage], ['session', sessionStorage]]) {
    for (let i = 0; i < magasin.length; i++) if ((magasin.getItem(magasin.key(i)) ?? '').includes(valeur) || magasin.key(i).includes(valeur)) trouve.push(nom);
  }
  for (const { name } of await indexedDB.databases()) {
    const base = await new Promise((ok) => { const r = indexedDB.open(name); r.onsuccess = () => ok(r.result); });
    for (const magasin of base.objectStoreNames) {
      const tout = await new Promise((ok) => { const r = base.transaction(magasin).objectStore(magasin).getAll(); r.onsuccess = () => ok(r.result); });
      if (JSON.stringify(tout).includes(valeur)) trouve.push(`${name}/${magasin}`);
    }
    base.close();
  }
  return trouve;
}, secret);

test('Comptes mail : un état par compte, identifiants pré-remplis, champ mot de passe masqué et vide, modes d\'emploi', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await page.getByRole('heading', { name: 'Comptes mail' }).waitFor();
  await compteMail(page, 'Gmail').getByText('Connecté, vérifié à 14 h 32', { exact: true }).waitFor();
  await compteMail(page, 'iCloud').getByText('Pas encore connecté', { exact: true }).waitFor();
  await compteMail(page, 'colin@qyris.fr').getByText('Mot de passe refusé', { exact: true }).waitFor();
  await compteMail(page, 'contact@qyris.fr').getByText("Boîte injoignable pour l'instant.", { exact: true }).waitFor();
  for (const [nom, id] of [['UTC', 'cflicour'], ['Gmail', 'colin.flicourt@gmail.com'], ['iCloud', 'colin.flicourt@icloud.com'], ['colin@qyris.fr', 'colin@qyris.fr'], ['contact@qyris.fr', 'contact@qyris.fr']]) {
    const c = compteMail(page, nom);
    assert.equal(await c.getByLabel('Identifiant').inputValue(), id);
    const mdp = c.getByLabel('Mot de passe');
    assert.equal(await mdp.inputValue(), '');
    assert.equal(await mdp.getAttribute('type'), 'password');
    assert.equal(await mdp.getAttribute('autocomplete'), 'off');
  }
  await compteMail(page, 'UTC').getByText('Ton mot de passe UTC, celui du CAS.', { exact: true }).waitFor();
  await compteMail(page, 'Gmail').getByText('Compte Google > Sécurité > Validation en deux étapes, puis Mots de passe des applications. Colle ici le mot de passe de 16 lettres.').waitFor();
  await compteMail(page, 'iCloud').getByText('Compte Apple > Connexion et sécurité > Mots de passe pour app. Colle ici le mot de passe créé.').waitFor();
  await compteMail(page, 'colin@qyris.fr').getByText('Le mot de passe de la boîte, celui du webmail OVH.').waitFor();
  // Pas encore connecté : ni « Tester » ni « Supprimer »
  assert.equal(await compteMail(page, 'UTC').getByRole('button', { name: 'Supprimer' }).count(), 0);
  assert.equal(await compteMail(page, 'iCloud').getByRole('button', { name: 'Tester la connexion' }).count(), 0);
  assert.equal(await compteMail(page, 'iCloud').getByRole('button', { name: 'Supprimer' }).count(), 0);
  assert.equal(await compteMail(page, 'Gmail').getByRole('button', { name: 'Tester la connexion' }).count(), 1);
  assert.equal(await page.getByText('Connecté, vérifié', { exact: false }).count(), 1);
});

test('Comptes mail : « trop de temps », vérifié hier ou plus tôt (forme des heures de mail)', async ({ page, contexte, serveur }) => {
  const { comptesMail } = serveur.etat;
  comptesMail.colin.derniere_erreur = 'delai';
  comptesMail.contact.derniere_erreur = null;
  comptesMail.contact.dernier_succes = '2026-10-13T08:05:00Z';
  Object.assign(comptesMail.icloud, { enregistre: true, dernier_succes: '2026-10-09T08:05:00Z' });
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  await compteMail(page, 'contact@qyris.fr').getByText('Connecté, vérifié hier', { exact: true }).waitFor();
  await compteMail(page, 'iCloud').getByText('Connecté, vérifié ven. 09/10', { exact: true }).waitFor();
  await compteMail(page, 'colin@qyris.fr').getByText('La boîte a mis trop de temps à répondre.', { exact: true }).waitFor();
});

test('Enregistrer : le mot de passe part en direct (pas dans la file), le champ se vide, « Compte enregistré. », état à jour', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  const c = compteMail(page, 'iCloud');
  const bouton = c.getByRole('button', { name: 'Enregistrer' });
  await c.getByText('Pas encore connecté').waitFor();
  assert.equal(await bouton.isDisabled(), true);
  await c.getByLabel('Mot de passe').fill('abcd-efgh-ijkl-mnop');
  assert.equal(await bouton.isDisabled(), false);
  await bouton.click();
  await page.getByText('Compte enregistré.').waitFor();
  await c.getByText('Connecté, vérifié à 15 h 45', { exact: true }).waitFor();
  assert.deepEqual(corpsMail(serveur, 'comptes-mail'), [{ compte: 'icloud', identifiant: 'colin.flicourt@icloud.com', mot_de_passe: 'abcd-efgh-ijkl-mnop' }]);
  assert.equal(await c.getByLabel('Mot de passe').inputValue(), '');
  assert.equal(await c.getByRole('button', { name: 'Enregistrer' }).isDisabled(), true);
  await c.getByRole('button', { name: 'Tester la connexion' }).waitFor();
  assert.equal(serveur.etat.vus.size, 0);
  assert.deepEqual(await stockageMotDePasse(page, 'abcd-efgh-ijkl-mnop'), []);
  assert.equal(await page.evaluate(() => document.body.innerHTML.includes('abcd-efgh-ijkl-mnop')), false);
});

test('Enregistrer avec un autre identifiant : l\'identifiant modifié part, le mot de passe reste à saisir', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  const c = compteMail(page, 'Gmail');
  await c.getByLabel('Identifiant').fill('autre@gmail.com');
  await c.getByLabel('Mot de passe').fill('mdp-d-appli');
  await c.getByRole('button', { name: 'Enregistrer' }).click();
  await page.getByText('Compte enregistré.').waitFor();
  assert.equal(corpsMail(serveur, 'comptes-mail')[0].identifiant, 'autre@gmail.com');
  assert.equal(await c.getByLabel('Identifiant').inputValue(), 'autre@gmail.com');
});

test('Enregistrer refusé : « Mot de passe refusé » par le code, champ vidé, rien d\'enregistré', async ({ page, contexte, serveur }) => {
  serveur.etat.essaisMail.icloud = 'refus';
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  const c = compteMail(page, 'iCloud');
  await c.getByLabel('Mot de passe').fill('mauvais-mdp');
  await c.getByRole('button', { name: 'Enregistrer' }).click();
  await c.getByRole('alert').getByText('Mot de passe refusé', { exact: true }).waitFor();
  assert.equal(await page.getByText('Message du serveur pour refus').count(), 0);
  assert.equal(await c.getByLabel('Mot de passe').inputValue(), '');
  await c.getByText('Pas encore connecté', { exact: true }).waitFor();
  assert.equal(serveur.etat.comptesMail.icloud.enregistre, false);
  assert.equal(await page.getByText('Compte enregistré.').count(), 0);
  assert.deepEqual(await stockageMotDePasse(page, 'mauvais-mdp'), []);
  // un échec de délai se dit aussi
  serveur.etat.essaisMail.icloud = 'delai';
  await c.getByLabel('Mot de passe').fill('encore-un');
  await c.getByRole('button', { name: 'Enregistrer' }).click();
  await c.getByRole('alert').getByText('La boîte a mis trop de temps à répondre.', { exact: true }).waitFor();
});

test('Tester la connexion : appel direct, « Connexion réussie. », et un échec met l\'état à jour', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  const c = compteMail(page, 'colin@qyris.fr');
  await c.getByText('Mot de passe refusé', { exact: true }).waitFor();
  await c.getByRole('button', { name: 'Tester la connexion' }).click();
  await page.getByText('Connexion réussie.').waitFor();
  await c.getByText('Connecté, vérifié à 15 h 45', { exact: true }).waitFor();
  assert.deepEqual(corpsMail(serveur, 'comptes-mail/tester'), [{ compte: 'colin' }]);
  assert.equal(serveur.etat.vus.size, 0);
  serveur.etat.essaisMail.colin = 'injoignable';
  await c.getByRole('button', { name: 'Tester la connexion' }).click();
  await c.getByRole('alert').getByText("Boîte injoignable pour l'instant.", { exact: true }).waitFor();
  assert.equal(serveur.appels('comptes-mail/tester').length, 2);
});

test('Supprimer : par la file, « Compte supprimé. », le compte repasse à « Pas encore connecté »', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  const c = compteMail(page, 'Gmail');
  await c.getByRole('button', { name: 'Supprimer' }).click();
  await page.getByText('Compte supprimé.').waitFor();
  await c.getByText('Pas encore connecté', { exact: true }).waitFor();
  await attendre(() => serveur.appels('comptes-mail/supprimer').length === 1, 'suppression');
  const e = serveur.appels('comptes-mail/supprimer')[0].corps;
  assert.equal(e.compte, 'gmail');
  assert.ok(e.id_client);
  assert.equal(serveur.etat.comptesMail.gmail.enregistre, false);
  assert.equal(await c.getByRole('button', { name: 'Supprimer' }).count(), 0);
});

test('Comptes mail hors ligne : « Il faut du réseau pour tester une boîte. », Enregistrer et Tester éteints, rien n\'est envoyé', async ({ page, contexte, serveur }) => {
  await preparer(page, contexte, serveur);
  await ouvrir(page, '/#/reglages');
  const c = compteMail(page, 'Gmail');
  await c.getByLabel('Mot de passe').fill('saisi-avant-la-coupure');
  await contexte.setOffline(true);
  await c.getByText('Il faut du réseau pour tester une boîte.').waitFor();
  assert.equal(await c.getByRole('button', { name: 'Enregistrer' }).isDisabled(), true);
  assert.equal(await c.getByRole('button', { name: 'Tester la connexion' }).isDisabled(), true);
  assert.equal(envois(serveur, 'comptes-mail').length + envois(serveur, 'comptes-mail/tester').length, 0);
});
