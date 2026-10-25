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
  return { id_client: crypto.randomUUID(), route, corps, local, fait_le: new Date().toISOString(), ordre: suivant++, essais: 0 };
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
            reponse = await appeler('POST', e.route, { corps: { ...e.corps, id_client: e.id_client, fait_le: e.fait_le } });
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
          await oublier(e.id_client);
          // Aucun await entre la lecture et l'écriture de la file : une écriture ajoutée ou annulée
          // pendant ce temps n'est ni perdue ni ressuscitée
          const idLocal = erreur === null && e.route === 'taches/ajouter' ? e.local.id : null;
          const reste = r.lire().filter((x) => x.id_client !== e.id_client);
          const changees = idLocal === null ? [] : reste.filter((x) => x.corps.carte === idLocal || x.local.carte === idLocal).map((x) => x.id_client);
          r.ecrire(idLocal === null ? reste : remplacerIdLocal(reste, idLocal, reponse.carte.id));
          if (erreur === null) r.succes(e, reponse);
          else r.refus(e, erreur);
          for (const id of changees) {
            const x = r.lire().find((y) => y.id_client === id);
            if (x) await garder(x);
          }
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
