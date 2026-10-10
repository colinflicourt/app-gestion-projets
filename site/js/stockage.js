// Copie locale dans IndexedDB : dernières données, semaines d'agenda, écritures en attente. Sans IndexedDB
// (navigation privée), tout reste en mémoire le temps de l'ouverture.

const MAGASINS = ['donnees', 'agenda', 'file'];
const memoire = new Map(MAGASINS.map((m) => [m, new Map()]));
let base = null;

function ouvrir() {
  base ??= new Promise((resolve) => {
    try {
      const r = indexedDB.open('jarvis-jr', 1);
      r.onupgradeneeded = () => {
        for (const m of MAGASINS) if (!r.result.objectStoreNames.contains(m)) r.result.createObjectStore(m);
      };
      r.onsuccess = () => resolve(r.result);
      r.onerror = () => resolve(null);
      r.onblocked = () => resolve(null);
    } catch {
      resolve(null);
    }
  });
  return base;
}

function requete(db, magasin, mode, faire) {
  return new Promise((resolve, reject) => {
    const tx = db.transaction(magasin, mode);
    const r = faire(tx.objectStore(magasin));
    tx.oncomplete = () => resolve(r.result);
    tx.onerror = () => reject(tx.error ?? new Error('IndexedDB'));
    tx.onabort = () => reject(tx.error ?? new Error('IndexedDB'));
  });
}

export async function lire(magasin, cle) {
  const db = await ouvrir();
  return db ? requete(db, magasin, 'readonly', (s) => s.get(cle)) : structuredClone(memoire.get(magasin).get(cle));
}

export async function ecrire(magasin, cle, valeur) {
  const db = await ouvrir();
  if (db) await requete(db, magasin, 'readwrite', (s) => s.put(valeur, cle));
  else memoire.get(magasin).set(cle, structuredClone(valeur));
}

export async function supprimer(magasin, cle) {
  const db = await ouvrir();
  if (db) await requete(db, magasin, 'readwrite', (s) => s.delete(cle));
  else memoire.get(magasin).delete(cle);
}

export async function tout(magasin) {
  const db = await ouvrir();
  return db ? requete(db, magasin, 'readonly', (s) => s.getAll()) : [...memoire.get(magasin).values()].map((v) => structuredClone(v));
}

export async function vider() {
  const db = await ouvrir();
  for (const m of MAGASINS) {
    if (db) await requete(db, m, 'readwrite', (s) => s.clear());
    else memoire.get(m).clear();
  }
}
