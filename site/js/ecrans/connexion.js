// Connexion (CONTENU § 1) : un mot de passe, une fois tous les 90 jours environ.
import { h } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { ErreurReseau } from '../api.js';
import { voyant } from './commun.js';

export function rendre(app) {
  const C = T.connexion;
  const message = h('p', { class: 'connexion-message', role: 'alert' }, app.etat.expiree ? C.expiree : '');
  const champ = h('input', { id: 'mot-de-passe', class: 'champ', type: 'password', autocomplete: 'current-password', required: true });
  const bouton = h('button', { type: 'submit', class: 'bouton-allume' }, C.bouton);
  const formulaire = h('form', {
    class: 'connexion-formulaire',
    async onsubmit(ev) {
      ev.preventDefault();
      if (champ.value === '') return;
      bouton.disabled = true;
      message.textContent = '';
      try {
        await app.connecter(champ.value);
      } catch (e) {
        message.textContent = e instanceof ErreurReseau ? C.reseau : e.message;
        champ.select();
      } finally {
        bouton.disabled = false;
      }
    },
  }, h('label', { for: 'mot-de-passe', class: 'connexion-label' }, C.champ), champ, h('div', { class: 'cuvette cuvette-large' }, bouton), message);
  return h('div', { class: 'connexion' },
    h('section', { class: 'console', 'aria-label': T.app },
      h('div', { class: 'afficheur connexion-afficheur' }, voyant(T.aujourdhui.standby), h('h1', { class: 'connexion-titre' }, C.titre), h('p', { class: 'connexion-sous-titre' }, C.sousTitre))),
    formulaire,
    h('p', { class: 'connexion-adresse' }, C.adresse));
}
