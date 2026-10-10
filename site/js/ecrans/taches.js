// L'onglet Tâches : filtres par domaine, groupes En cours, À faire, En attente, cartes faites à la demande.
import { h } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { DOMAINES, NOMS, avancement, etiquetteCarte, groupesTaches, libelleEcheance } from '../lib/cartes.js';
import { regle, tampon } from './commun.js';

let voirFaites = false;

// Une carte dans une liste ; dans l'À faire d'Aujourd'hui, l'échéance commence par une minuscule
export function ligneCarte(app, carte, { minuscule = false } = {}) {
  const echeance = libelleEcheance(carte, app.maintenant());
  const progres = avancement(carte);
  const texteEcheance = echeance && (minuscule ? echeance.texte[0].toLowerCase() + echeance.texte.slice(1) : echeance.texte);
  return h('button', { type: 'button', class: `carte-ligne d-${carte.domaine}${carte.colonne === 'fait' ? ' est-faite' : ''}`, onclick: () => app.aller(`#/carte/${carte.id}`) },
    h('span', { class: 'carte-puce' }, h('span', { class: 'carre' }), h('span', { class: 'carte-puce-texte' }, etiquetteCarte(carte)), progres && h('span', { class: 'carte-progres' }, progres)),
    h('span', { class: 'carte-titre' }, carte.titre),
    echeance && h('span', { class: `carte-echeance${echeance.retard ? ' retard' : ''}` }, echeance.retard && h('span', { class: 'point' }), texteEcheance),
    carte.attente && h('span', { class: 'carte-attente' }, T.taches.attente),
    carte.colonne === 'fait' && tampon(T.carte.fait, carte.id, 'carte-tampon'));
}

export function rendre(app, route) {
  const domaine = DOMAINES.includes(route.param) ? route.param : null;
  const g = groupesTaches(app.vue()?.trello.cartes ?? [], domaine);
  const ouvertes = g.en_cours.length + g.a_faire.length + g.en_attente.length;
  const filtre = (cle, libelle, cible) => h('button', {
    type: 'button', class: `filtre${cle ? ` d-${cle}` : ''}`, 'aria-pressed': String(domaine === cle), onclick: () => app.aller(cible),
  }, cle && h('span', { class: 'pastille' }), libelle);
  const groupe = (colonne, liste) => liste.length > 0 && h('section', { class: 'groupe', 'aria-label': T.taches.groupes[colonne] },
    h('h2', { class: 'section-titre groupe-titre' }, h('span', {}, T.taches.groupes[colonne]), h('span', {}, String(liste.length))),
    h('div', { class: 'groupe-cartes' }, liste.map((carte) => ligneCarte(app, carte))));
  return h('div', { class: 'taches' },
    h('header', { class: 'entete' }, h('h1', { class: 'titre-ecran' }, T.taches.titre), h('span', { class: 'entete-cote' }, T.taches.compte(ouvertes))),
    h('div', { class: 'filtres', role: 'group', 'aria-label': T.taches.filtrer }, filtre(null, T.taches.tout, '#/taches'), DOMAINES.map((d) => filtre(d, NOMS[d], `#/taches/${d}`))),
    regle(),
    ouvertes === 0 && h('p', { class: 'vide' }, T.taches.aucune),
    groupe('en_cours', g.en_cours),
    groupe('a_faire', g.a_faire),
    groupe('en_attente', g.en_attente),
    voirFaites && groupe('fait', g.fait),
    (g.fait.length > 0 || voirFaites) && h('button', {
      type: 'button', class: 'bouton-contour bouton-large voir-faites', 'aria-expanded': String(voirFaites),
      onclick: () => {
        voirFaites = !voirFaites;
        app.rendre();
      },
    }, voirFaites ? T.taches.masquerFaites : T.taches.voirFaites));
}
