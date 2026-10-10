// La file des écritures (SPEC § 8.4) : gardée dans IndexedDB, envoyée dans l'ordre, une à la fois. Un
// renvoi porte le même id_client : le serveur rend le résultat déjà obtenu sans rien refaire.
import { appeler, ErreurApi } from './api.js';
import { decision, remplacerIdLocal } from './etat.js';
import * as stockage from './stockage.js';

let suivant = Date.now();
let envoi = null;
let enVol = null;

export async function charger() {
  return (await stockage.tout('file')).sort((a, b) => a.ordre - b.ordre);
}

export function nouvelleEcriture(route, corps, local = {}) {
  return { id_client: crypto.randomUUID(), route, corps, local, ordre: suivant++, essais: 0 };
}

export async function garder(e) {
  try {
    await stockage.ecrire('file', e.id_client, e);
  } catch {
    // elle reste dans la file en mémoire
  }
}

export async function oublier(id) {
  try {
    await stockage.supprimer('file', id);
  } catch {
    // rien de plus à faire
  }
}

// Si l'écriture id part en ce moment, attend sa réponse et son traitement
export async function attendre(id) {
  if (enVol?.id === id) await enVol.fini;
}

// r : { lire() → file, ecrire(file), succes(e, reponse), refus(e, erreur), garde(e), session() }
export function envoyer(r) {
  envoi ??= (async () => {
    try {
      for (;;) {
        const e = r.lire()[0];
        if (!e) return;
        let terminer;
        enVol = { id: e.id_client, fini: new Promise((resolve) => { terminer = resolve; }) };
        try {
          let reponse = null;
          let erreur = null;
          try {
            reponse = await appeler('POST', e.route, { corps: { ...e.corps, id_client: e.id_client } });
          } catch (err) {
            erreur = err;
          }
          const d = decision(erreur === null ? 200 : erreur instanceof ErreurApi ? erreur.statut : null);
          if (d === 'garder') {
            e.essais++;
            await garder(e);
            r.garde(e);
            return;
          }
          if (d === 'connexion') {
            r.session();
            return;
          }
          let reste = r.lire().filter((x) => x.id_client !== e.id_client);
          await oublier(e.id_client);
          if (erreur === null && e.route === 'taches/ajouter') {
            reste = remplacerIdLocal(reste, e.local.id, reponse.carte.id);
            for (const x of reste) await garder(x);
          }
          r.ecrire(reste);
          if (erreur === null) r.succes(e, reponse);
          else r.refus(e, erreur);
        } finally {
          enVol = null;
          terminer();
        }
      }
    } finally {
      envoi = null;
    }
  })();
  return envoi;
}
