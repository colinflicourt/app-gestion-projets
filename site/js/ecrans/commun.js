// Pièces partagées par les écrans : bandeaux d'état, messages, règle graduée, voyants, tampons, et
// « tirer pour actualiser » (une web app installée n'a pas celui de Safari).
import { angle, h } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { ageTexte, minutesEntre } from '../lib/temps.js';

export const regle = () => h('div', { class: 'regle', 'aria-hidden': 'true' }, h('span'), h('span'), h('span'));

export const voyant = (texte, allume = false, creme = false) => h('span', { class: `voyant${allume ? ' allume' : ''}${creme ? ' creme' : ''}` }, texte);

export const tampon = (texte, cle, classe = '') => h('span', { class: `tampon ${classe}`.trim(), vars: { '--angle': angle(cle) } }, texte);

export function rendreStatut(conteneur, app) {
  const { etat } = app;
  const lignes = [];
  if (!etat.connexion) {
    const d = etat.donnees;
    const depuis = (iso) => ageTexte(minutesEntre(new Date(iso), app.maintenant()));
    if (etat.horsLigne) lignes.push(h('p', {}, d ? T.etats.horsLigne(ageTexte((Date.now() - d.recu_le) / 60000)) : T.etats.vide));
    else if (etat.erreur) lignes.push(h('p', {}, etat.erreur));
    if (d && !etat.horsLigne) {
      if (d.trello.erreur && d.trello.recupere_le) lignes.push(h('p', {}, T.etats.trello(depuis(d.trello.recupere_le))));
      if (d.agenda.erreur === 'google_deconnecte') {
        lignes.push(h('div', { class: 'statut-action' }, h('span', {}, T.etats.googleDeconnecte), h('a', { class: 'bouton bouton-petit', href: '/api/google/connexion' }, T.etats.reconnecter)));
      } else if (d.agenda.erreur && d.agenda.recupere_le) {
        lignes.push(h('p', {}, T.etats.googleIndisponible(depuis(d.agenda.recupere_le))));
      }
    }
    if (etat.file.length > 0) lignes.push(h('p', {}, T.etats.file(etat.file.length)));
  }
  conteneur.replaceChildren(...lignes);
  conteneur.hidden = lignes.length === 0;
}

export function rendreMessage(conteneur, texte, action = null, fermer = null) {
  if (texte === null) {
    conteneur.replaceChildren();
    return;
  }
  conteneur.replaceChildren(h('div', { class: 'message' },
    h('span', {}, texte),
    action && h('button', { type: 'button', class: 'message-action', onclick: () => { fermer?.(); action.faire(); } }, action.libelle)));
}

export function tirerPourActualiser(ecran, indicateur, actualiser) {
  let depart = null;
  let pret = false;
  ecran.addEventListener('touchstart', (e) => {
    depart = ecran.scrollTop <= 0 ? e.touches[0].clientY : null;
    pret = false;
  }, { passive: true });
  ecran.addEventListener('touchmove', (e) => {
    if (depart === null) return;
    const ecart = e.touches[0].clientY - depart;
    pret = ecart > 70;
    indicateur.hidden = ecart <= 12;
    indicateur.textContent = pret ? T.etats.relacher : T.etats.tirer;
  }, { passive: true });
  ecran.addEventListener('touchend', async () => {
    if (depart === null) return;
    depart = null;
    if (!pret) {
      indicateur.hidden = true;
      return;
    }
    indicateur.textContent = T.etats.actualisation;
    await actualiser();
    indicateur.hidden = true;
  });
}
