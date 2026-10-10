import { test } from 'node:test';
import assert from 'node:assert/strict';

// Un faux IndexedDB minimal : chaque transaction se termine au tour suivant de la boucle, comme la vraie,
// pour qu'un toucher de Colin puisse tomber pendant le rangement d'une réponse
const magasins = new Map();
globalThis.indexedDB = {
  open() {
    const r = {};
    setTimeout(() => {
      r.result = {
        objectStoreNames: { contains: (m) => magasins.has(m) },
        createObjectStore: (m) => magasins.set(m, new Map()),
        transaction(m) {
          const s = magasins.get(m);
          const req = (faire) => ({ result: faire() });
          const tx = {
            objectStore: () => ({
              put: (v, k) => req(() => s.set(k, structuredClone(v))),
              get: (k) => req(() => structuredClone(s.get(k))),
              delete: (k) => req(() => s.delete(k)),
              getAll: () => req(() => [...s.values()].map((v) => structuredClone(v))),
              clear: () => req(() => s.clear()),
            }),
          };
          setTimeout(() => tx.oncomplete?.());
          return tx;
        },
      };
      r.onupgradeneeded?.();
      r.onsuccess?.();
    });
    return r;
  },
};

const { appeler, ErreurReseau } = await import('../../site/js/api.js');
const { attendre, envoyer, garder, nouvelleEcriture } = await import('../../site/js/file.js');
const stockage = await import('../../site/js/stockage.js');

const json = (o, statut = 200) => new Response(JSON.stringify(o), { status: statut, headers: { 'Content-Type': 'application/json' } });
let envoyes = [];
let repondre = () => json({ ok: true });
globalThis.fetch = async (adresse, options) => {
  const corps = options.body ? JSON.parse(options.body) : null;
  envoyes.push({ route: adresse.slice('/api/'.length), corps });
  return repondre(adresse, corps);
};

// La file en mémoire de l'app, et ce que l'envoi lui rapporte
function fileDeTest(ecritures) {
  const f = { file: [...ecritures], succes: [], refus: [], gardes: [] };
  f.r = {
    lire: () => f.file,
    ecrire(nouvelle) {
      f.file = nouvelle;
    },
    succes: (e) => f.succes.push(e.id_client),
    refus: (e) => f.refus.push(e.id_client),
    garde: (e) => f.gardes.push(e.id_client),
    session() {},
  };
  return f;
}

const cocher = (element, carte = 'c1') => nouvelleEcriture('taches/cocher', { carte, element, coche: true }, { carte });

test("file : une étape cochée pendant le rangement de la réponse précédente n'est pas perdue et part ensuite", async () => {
  envoyes = [];
  const a = cocher('e1');
  const b = cocher('e2');
  const f = fileDeTest([a]);
  repondre = () => {
    setTimeout(() => {
      f.file = [...f.file, b];
    });
    repondre = () => json({ ok: true });
    return json({ ok: true });
  };
  await envoyer(f.r);
  assert.deepEqual(envoyes.map((x) => x.corps.element), ['e1', 'e2']);
  assert.deepEqual(f.file, []);
});

test("file : un « Terminer » annulé pendant le rangement de la réponse précédente ne part pas", async () => {
  envoyes = [];
  const a = cocher('e1');
  const t = nouvelleEcriture('taches/terminer', { carte: 'c1' }, { carte: 'c1' });
  const f = fileDeTest([a, t]);
  repondre = () => {
    // Annuler, comme l'app : attendre l'envoi en cours s'il s'agit de lui, sinon retirer de la file
    setTimeout(async () => {
      await attendre(t.id_client);
      f.file = f.file.filter((x) => x.id_client !== t.id_client);
    });
    repondre = () => json({ ok: true });
    return json({ ok: true });
  };
  await envoyer(f.r);
  assert.deepEqual(envoyes.map((x) => x.route), ['taches/cocher']);
  assert.deepEqual(f.file, []);
});

test("file : la capture partie, l'étape cochée sur sa carte locale part avec le vrai identifiant, gardé sur l'iPhone", async () => {
  envoyes = [];
  const c = nouvelleEcriture('taches/ajouter', { domaine: 'perso', titre: 'Essai' }, { id: 'local-1' });
  const x = cocher('e1', 'local-1');
  await garder(c);
  await garder(x);
  const f = fileDeTest([c, x]);
  repondre = () => {
    repondre = () => {
      throw new TypeError('réseau coupé');
    };
    return json({ ok: true, carte: { id: 'c-reel' } });
  };
  await envoyer(f.r);
  assert.deepEqual(envoyes.map((e) => e.corps.carte ?? null), [null, 'c-reel']);
  assert.deepEqual(f.file.map((e) => [e.id_client, e.corps.carte, e.local.carte]), [[x.id_client, 'c-reel', 'c-reel']]);
  const gardees = await stockage.tout('file');
  assert.deepEqual(gardees.map((e) => [e.id_client, e.corps.carte]), [[x.id_client, 'c-reel']]);
});

test('api : une réponse 2xx au corps illisible (coupée en route, page HTML) est une erreur de réseau', async () => {
  repondre = () => new Response('<html>Maintenance</html>', { status: 200, headers: { 'Content-Type': 'text/html' } });
  await assert.rejects(appeler('POST', 'taches/cocher', { corps: {} }), ErreurReseau);
});

test("file : réponse 2xx illisible, l'écriture reste, puis repart avec le même id_client", async () => {
  envoyes = [];
  const a = cocher('e1');
  const f = fileDeTest([a]);
  repondre = () => {
    repondre = () => json({ ok: true });
    return new Response('{"ok":tr', { status: 200, headers: { 'Content-Type': 'application/json' } });
  };
  await envoyer(f.r);
  assert.deepEqual([f.file.length, f.gardes, f.refus, a.essais], [1, [a.id_client], [], 1]);
  await envoyer(f.r);
  assert.deepEqual(envoyes.map((x) => x.corps.id_client), [a.id_client, a.id_client]);
  assert.deepEqual([f.file, f.succes], [[], [a.id_client]]);
});
