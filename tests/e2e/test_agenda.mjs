import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';

async function ouvrirAgenda(page, contexte, serveur) {
  await connecte(contexte, serveur);
  await heure(page, '2026-10-14T15:45:00+02:00');
  await ouvrir(page, '/#/agenda');
  await page.locator('.agenda-rang').first().waitFor();
}

const rangs = (page) => page.locator('.agenda-rang').allTextContents();

test('agenda : semaine du 12 au 18 octobre, cours passés JOUÉ, férié, STANDBY sur le prochain cours', async ({ page, contexte, serveur }) => {
  await ouvrirAgenda(page, contexte, serveur);
  await page.getByText('12 au 18 octobre').waitFor();
  const r = await rangs(page);
  assert.equal(r.length, 7);
  assert.ok(r[0].startsWith('LUN 12') && r[0].includes('LA14') && r[0].includes('TD · FC114') && r[0].includes('16:30 à 18:30') && r[0].includes('JOUÉ'), r[0]);
  assert.ok(r[2].includes('NF93') && r[2].includes('Déjeuner avec Léon') && r[2].includes('PERSO') && r[2].includes('TN01') && r[2].includes('STANDBY'), r[2]);
  assert.ok(r[3].includes("Jour férié à l'UTC : pas de cours."), r[3]);
  assert.ok(r[4].includes('Rien de prévu.'), r[4]);
  assert.equal(await texte(page, '.agenda-jour[aria-current="date"]'), 'MER 14');
  assert.deepEqual(serveur.appels('agenda').map((a) => a.parametres.semaine), ['2026-W42']);
});

test("agenda : semaine suivante (« devient un jeudi », examens, médian), vue Jour, puis retour à aujourd'hui", async ({ page, contexte, serveur }) => {
  await ouvrirAgenda(page, contexte, serveur);
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await page.getByText('19 au 25 octobre').waitFor();
  const r = await rangs(page);
  assert.ok(r[0].includes("Aujourd'hui, l'UTC suit l'emploi du temps du jeudi.") && r[0].includes('MT23'), r[0]);
  assert.ok(r[2].includes("Jour d'examen.") && r[2].includes('Médian MT23'), r[2]);
  assert.deepEqual(serveur.appels('agenda').map((a) => a.parametres.semaine), ['2026-W42'], 'jours déjà dans la copie : pas de second appel');
  await page.getByRole('button', { name: 'Jour', exact: true }).click();
  await page.getByText('Mercredi 21 octobre').waitFor();
  assert.equal((await rangs(page)).length, 1);
  await page.getByRole('button', { name: "Aujourd'hui" }).click();
  await page.getByText('Mercredi 14 octobre').waitFor();
});

test("agenda : semaine du 26 octobre (heure d'hiver), le TD du lundi reste à 16:30", async ({ page, contexte, serveur }) => {
  await ouvrirAgenda(page, contexte, serveur);
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await page.getByText('26 octobre au 1er novembre').waitFor();
  await attendre(async () => (await rangs(page))[0]?.includes('LA14'), 'semaine chargée');
  assert.ok((await rangs(page))[0].includes('16:30 à 18:30'));
  assert.deepEqual(serveur.appels('agenda').map((a) => a.parametres.semaine), ['2026-W42', '2026-W44']);
});

test('agenda hors ligne : une semaine jamais chargée le dit, une semaine déjà vue reste lisible', async ({ page, contexte, serveur }) => {
  await ouvrirAgenda(page, contexte, serveur);
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await attendre(async () => (await rangs(page))[0]?.includes('LA14'), 'semaine du 26 chargée');
  await contexte.setOffline(true);
  await page.getByRole('button', { name: 'Semaine suivante' }).click();
  await page.getByText("Hors ligne : cette semaine n'est pas encore chargée.").waitFor();
  await page.getByRole('button', { name: 'Semaine précédente' }).click();
  await attendre(async () => (await rangs(page))[0]?.includes('LA14'), 'semaine du 26 relue sans réseau');
});
