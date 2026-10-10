// Écran pas encore construit : remplacé tâche par tâche, retiré à la tâche 10.
import { h } from '../lib/dom.js';
import { T } from '../lib/textes.js';

export function rendre(app, route) {
  return h('div', {}, h('h1', { class: 'titre-ecran' }, T.onglets[route.nom] ?? T.onglets.taches));
}
