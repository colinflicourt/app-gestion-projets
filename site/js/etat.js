// Les écritures en attente appliquées par-dessus la dernière copie du serveur (SPEC § 8.4) : l'écran montre
// tout de suite ce que Colin vient de faire, marqué « attente » jusqu'à la réponse. Fonctions pures.

const ORDRE = ['qyris', 'asso', 'etudes', 'perso'];

export function trierCartes(cartes) {
  return cartes.sort((a, b) => ORDRE.indexOf(a.domaine) - ORDRE.indexOf(b.domaine) || a.position - b.position);
}

// Position d'une carte posée en haut d'une colonne, comme le serveur et Trello (pos=top)
function enHaut(cartes, domaine, colonne, sauf = null) {
  const positions = cartes.filter((c) => c.domaine === domaine && c.colonne === colonne && c.id !== sauf).map((c) => c.position);
  if (positions.length === 0) return 65536;
  const min = Math.min(...positions);
  return min > 0 ? min / 2 : min - 1;
}

function deplacer(cartes, carte, colonne) {
  carte.position = enHaut(cartes, carte.domaine, colonne, carte.id);
  carte.colonne = colonne;
  carte.attente = true;
}

// e : { route, corps, local }. Modifie cartes (une copie, jamais celle du serveur).
export function appliquerEcriture(cartes, e) {
  const c = e.corps;
  const carte = cartes.find((x) => x.id === (e.route === 'taches/annuler' ? e.local.carte : c.carte));
  if (e.route === 'taches/cocher' && carte) {
    for (const liste of carte.checklists) {
      for (const element of liste.elements) {
        if (element.id !== c.element) continue;
        element.coche = c.coche;
        element.attente = true;
        carte.attente = true;
      }
    }
  } else if (e.route === 'taches/terminer' && carte && carte.colonne !== 'fait') {
    carte.echeance_faite = carte.echeance !== null || carte.echeance_faite;
    deplacer(cartes, carte, 'fait');
  } else if (e.route === 'taches/annuler' && carte) {
    Object.assign(carte, { colonne: e.local.colonne_avant, position: e.local.position_avant, echeance_faite: e.local.echeance_faite_avant, attente: true });
  } else if (e.route === 'taches/deplacer' && carte && carte.colonne !== c.colonne) {
    if (carte.colonne === 'fait' && carte.echeance !== null) carte.echeance_faite = false;
    deplacer(cartes, carte, c.colonne);
  } else if (e.route === 'taches/ajouter' && !cartes.some((x) => x.id === e.local.id)) {
    cartes.push({
      id: e.local.id, domaine: c.domaine, colonne: 'a_faire', position: enHaut(cartes, c.domaine, 'a_faire'), titre: c.titre,
      description: '', url: '', echeance: e.local.echeance_iso, echeance_faite: false, urgent: c.urgent === true,
      etiquettes: [], checklists: [], derniere_activite: e.local.cree_le, attente: true,
    });
  }
  return cartes;
}

const parHeure = (a, b) => a.heure.localeCompare(b.heure);

// Rappels du jour : un rappel en attente s'ajoute (sans bouton « Annuler » tant qu'il n'a pas d'identifiant), une annulation le retire
function appliquerRappel(rappels, e) {
  if (e.route === 'rappels' && !rappels.some((r) => r.id === e.local.id)) {
    rappels.push({ id: e.local.id, type: 'manuel', heure: e.corps.quand, titre: e.corps.texte, passe: false, annulable: false, attente: true });
    rappels.sort(parHeure);
  } else if (e.route === 'rappels/annuler') {
    return rappels.filter((r) => r.id !== e.corps.id);
  }
  return rappels;
}

// Ce que montre l'écran : la copie du serveur, puis chaque écriture en attente, dans l'ordre
export function vue(donnees, file) {
  if (!donnees) return null;
  const v = structuredClone(donnees);
  for (const e of file) appliquerEcriture(v.trello.cartes, e);
  v.rappels_du_jour = file.reduce(appliquerRappel, v.rappels_du_jour ?? []);
  trierCartes(v.trello.cartes);
  return v;
}

// Une écriture acceptée par le serveur entre dans la copie, en attendant la prochaine lecture
export function integrer(donnees, e, reponse) {
  const d = structuredClone(donnees);
  if (e.route === 'taches/ajouter') {
    d.trello.cartes = d.trello.cartes.filter((c) => c.id !== e.local.id && c.id !== reponse.carte.id);
    d.trello.cartes.push(reponse.carte);
  } else if (e.route === 'rappels') {
    const r = reponse.rappel;
    d.rappels_du_jour = (d.rappels_du_jour ?? []).filter((x) => x.id !== r.id);
    d.rappels_du_jour.push({ id: r.id, type: 'manuel', heure: r.quand, titre: r.texte, passe: false, annulable: true });
    d.rappels_du_jour.sort(parHeure);
  } else if (e.route === 'rappels/annuler') {
    d.rappels_du_jour = (d.rappels_du_jour ?? []).filter((x) => x.id !== e.corps.id);
  } else {
    appliquerEcriture(d.trello.cartes, e);
  }
  for (const carte of d.trello.cartes) {
    delete carte.attente;
    for (const liste of carte.checklists) for (const element of liste.elements) delete element.attente;
  }
  trierCartes(d.trello.cartes);
  return d;
}

// Une capture envoyée : ses écritures suivantes visent la vraie carte
export function remplacerIdLocal(file, idLocal, idReel) {
  const remplacer = (o) => (o.carte === idLocal ? { ...o, carte: idReel } : o);
  return file.map((e) => ({ ...e, corps: remplacer(e.corps), local: remplacer(e.local) }));
}

export const retirerEcriture = (file, id) => file.filter((e) => e.id_client !== id);

// Réponse à une écriture : acceptée ou refusée pour de bon (4xx : carte supprimée, trop tard…), on la
// retire ; réseau absent ou serveur en panne, on la garde ; session expirée, on demande la connexion.
export function decision(statut) {
  if (statut === 401) return 'connexion';
  if (statut !== null && statut >= 200 && statut < 500) return 'retirer';
  return 'garder';
}
