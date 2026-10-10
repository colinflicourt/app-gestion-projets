import { test, assert, heure, connecte, ouvrir, texte, attendre } from './cadre.mjs';

async function ouvrirAujourdhui(page, contexte, serveur, quand, attendu) {
  await connecte(contexte, serveur);
  await heure(page, quand);
  await ouvrir(page, '/#/aujourdhui');
  await attendre(async () => (await texte(page, '.aujourdhui-haut .afficheur')).includes(attendu), `afficheur avec « ${attendu} »`);
}

const afficheur = (page) => texte(page, '.aujourdhui-haut .afficheur');

async function contient(page, morceaux) {
  const a = await afficheur(page);
  for (const m of morceaux) assert.ok(a.includes(m), `afficheur : « ${m} » (vu : ${a})`);
}

test("aujourd'hui, 15 h 45 entre deux cours : Entracte, 45 min libres, STANDBY TN01 à 16:30 en FA510", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Entracte');
  await contient(page, ['Mercredi 14 octobre', 'EN SCÈNE', '45 min libres', 'STANDBY', 'TN01 · TD', '16:30', '45 min', 'FA510']);
  assert.ok((await texte(page, '.talkback-texte')).startsWith('Bon après-midi.'));
  assert.deepEqual(await page.locator('.tranche-compte').allTextContents(), ['2', '1', '3', '2']);
  assert.deepEqual(await page.locator('.tranche-alerte').allTextContents(), ['1 en retard', '', 'médian J-7', '1 en retard']);
  assert.equal(await page.getByRole('button', { name: 'QYRIS : 2 à faire, 1 en retard' }).count(), 1);
});

test("pendant le TD de 17 h 10 : EN SCÈNE TN01 jusqu'à 19:30, fin dans 2 h 20, GO éteint", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T17:10:00+02:00', 'fin dans');
  await contient(page, ['TN01 · TD', '19:30', '2 h 20', 'FA510', 'MT23 · TD', 'lun. 19/10 16:30']);
  // Le nom du cours suivant tient sur une ligne : c'est la cote (jour et heure) qui passe à la ligne
  const lignesTitre = await page.locator('.afficheur-titre', { hasText: 'MT23 · TD' }).evaluate((e) => Math.round(e.offsetHeight / parseFloat(getComputedStyle(e).lineHeight)));
  assert.equal(lignesTitre, 1);
  const go = page.getByRole('button', { name: 'GO, éteint pendant le cours' });
  assert.equal(await go.getAttribute('aria-disabled'), 'true');
  await go.click({ force: true });
  assert.equal(await page.locator('.tirage-ligne').count(), 0);
});

test("au réveil, 7 h 32 : « Rien pour l'instant », 2 cours, STANDBY NF93 dans 28 min, « Bonjour Colin. »", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T07:32:00+02:00', "Rien pour l'instant");
  await contient(page, ['2 cours', 'NF93 · TD', '08:00', '28 min', 'FA400']);
  assert.ok((await texte(page, '.talkback-texte')).startsWith('Bonjour Colin.'));
});

test("le soir, 21 h : « Plus de cours aujourd'hui », prochain cours lundi 19/10, « Bonsoir. »", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T21:00:00+02:00', "Plus de cours aujourd'hui");
  await contient(page, ['MT23 · TD', '16:30', 'lun. 19/10', 'FA420']);
  assert.ok((await texte(page, '.talkback-texte')).startsWith('Bonsoir.'));
});

test("jeudi 15 férié : le bandeau « Jour férié à l'UTC : pas de cours. »", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-15T10:00:00+02:00', 'Jeudi 15 octobre');
  await page.getByText("Jour férié à l'UTC : pas de cours.").waitFor();
});

test('sous le pli : conduite du jour (NF93 JOUÉ, TN01 STANDBY), À faire en 3 groupes, barre compacte', async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Entracte');
  const rangs = await page.locator('.conduite-rang').allTextContents();
  assert.equal(rangs.length, 3);
  assert.ok(rangs[0].includes('NF93 · TD') && rangs[0].includes('JOUÉ'), rangs[0]);
  assert.ok(rangs[1].includes('Déjeuner avec Léon') && rangs[1].includes('Perso'), rangs[1]);
  assert.ok(rangs[2].includes('TN01 · TD') && rangs[2].includes('STANDBY'), rangs[2]);
  assert.deepEqual(await page.locator('.rang-etiquette').allTextContents(), ['En retard', "Aujourd'hui", 'Urgent']);
  assert.deepEqual(await page.locator('.rang-groupe').first().locator('.carte-titre').allTextContents(), ['LinkedIn : mettre à jour mon profil', 'Campagne LinkedIn : programmer les posts']);
  assert.equal(await texte(page, '.rang-groupe .carte-echeance.retard'), 'en retard depuis le 05/10');
  await page.locator('.sous-le-pli .rang-groupe').last().scrollIntoViewIfNeeded();
  await attendre(() => page.locator('.compacte').evaluate((e) => e.classList.contains('visible')), 'barre compacte visible');
});

test("GO : la proposition tombe, 3 relances avec leurs répliques, puis Junior choisit ; « C'est parti » ouvre la carte", async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Entracte');
  // Les 5 meilleures (scores 140, 140, 80, 40, 25), puis les autres candidates : le tirage se fait parmi les
  // 5 meilleures pas encore proposées, une moins bonne peut donc sortir après quelques relances
  const meilleures = [
    'Campagne LinkedIn : envoyer un mail de test.', 'Terminer : LinkedIn : mettre à jour mon profil', 'Terminer : TN01 : rendre le TP',
    'Cabaret : valider les horaires avec la team bouffe.', "A'point : vérifier le site en ligne, téléphone compris.",
  ];
  const possibles = [...meilleures, 'Jarvis Jr. : créer un compte cron-job.org.', 'Terminer : MT23 : préparer le médian', 'Formation 2 : relire le deck en mode présentation.'];
  const repliques = ["Je t'ai trouvé 35 min avant ton TD.", "D'accord, une autre.", "Encore ? Tu cherches la tâche qui se fait toute seule. Elle n'existe pas.", "Dernière proposition. Après, c'est moi le patron.", "J'ai choisi. On y va ?"];
  await page.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).click();
  const vues = [];
  for (let i = 0; i < 5; i++) {
    await page.locator('.tirage-texte').waitFor();
    assert.equal(await texte(page, '.tirage-ligne'), '35 MIN AVANT TN01');
    assert.equal(await texte(page, '.talkback-texte'), repliques[i]);
    vues.push(await texte(page, '.tirage-texte'));
    if (i < 4) await page.getByRole('button', { name: 'Autre chose' }).click();
  }
  assert.equal(await page.getByRole('button', { name: 'Autre chose' }).count(), 0);
  assert.equal(new Set(vues).size, 5, 'jamais deux fois la même proposition');
  for (const vue of vues) assert.ok(possibles.includes(vue), vue);
  assert.ok(meilleures.includes(vues[0]), 'la première proposition est parmi les 5 meilleures');
  await page.getByRole('button', { name: "C'est parti" }).click();
  await page.getByText("C'est parti. Coche l'étape quand c'est fait, je note.").waitFor();
  assert.match(new URL(page.url()).hash, /^#\/carte\/ca\d{22}/);
  await attendre(() => serveur.appels('tirage/parti').length === 1, 'tirage/parti envoyé');
});

test("mouvement réduit : la proposition s'affiche tout de suite, sans défilement", async ({ page, contexte, serveur }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Entracte');
  await page.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).click();
  assert.equal(await page.locator('.tirage-attente').count(), 0);
  assert.equal(await page.locator('.tirage-texte').count(), 1);
});

test('cours dans 10 min : « Le meilleur choix : y aller. », puis Retour', async ({ page, contexte, serveur }) => {
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T16:20:00+02:00', 'Entracte');
  await page.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).click();
  assert.equal(await texte(page, '.talkback-texte'), 'Ton cours commence dans 10 min. Le meilleur choix : y aller.');
  await page.getByRole('button', { name: 'Retour' }).click();
  await page.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).waitFor();
});

test('to-do vide : « Plus rien dans ta to-do… »', async ({ page, contexte, serveur }) => {
  serveur.etat.donnees.trello.cartes = [];
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Entracte');
  await page.getByText("Rien d'urgent. Profite, ou jette un œil à l'onglet Tâches.").waitFor();
  await page.getByRole('button', { name: 'GO, je fais quoi maintenant ?' }).click();
  assert.equal(await texte(page, '.talkback-texte'), 'Plus rien dans ta to-do. Génie ou amnésie ? Ajoute une tâche, ou repose-toi.');
});

test("agenda pas connecté : l'afficheur le dit, sans erreur", async ({ page, contexte, serveur }) => {
  serveur.etat.donnees.agenda = { recupere_le: null, erreur: null, google_connecte: false, jours: {} };
  await ouvrirAujourdhui(page, contexte, serveur, '2026-10-14T15:45:00+02:00', 'Agenda pas encore connecté. Ça se fait dans Moi.');
});
