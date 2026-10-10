// Jarvis Jr. : démarrage et navigation entre les écrans (les données arrivent à la tâche 5).
import { h, icone } from './lib/dom.js';
import { T } from './lib/textes.js';
import * as attente from './ecrans/attente.js';

const ECRANS = { aujourdhui: attente, agenda: attente, taches: attente, carte: attente, moi: attente };
const ONGLETS = ['aujourdhui', 'agenda', 'taches', 'moi'];

function lireRoute() {
  const [chemin, requete = ''] = location.hash.replace(/^#\/?/, '').split('?');
  const [nom, param = null] = chemin.split('/').filter(Boolean);
  return { nom: ECRANS[nom] ? nom : 'aujourdhui', param, requete: new URLSearchParams(requete) };
}

function rendre() {
  const route = lireRoute();
  document.getElementById('ecran').replaceChildren(ECRANS[route.nom].rendre(null, route));
  for (const a of document.querySelectorAll('.onglet')) {
    if (a.dataset.onglet === (route.nom === 'carte' ? 'taches' : route.nom)) a.setAttribute('aria-current', 'page');
    else a.removeAttribute('aria-current');
  }
}

document.getElementById('onglets').replaceChildren(
  ...ONGLETS.map((nom) => h('a', { href: `#/${nom}`, class: 'onglet', dataset: { onglet: nom } }, icone(nom), h('span', {}, T.onglets[nom]))),
  h('button', { type: 'button', class: 'bouton-plus', 'aria-label': T.onglets.noter }, icone('plus', 22, 2.25)),
);
window.addEventListener('hashchange', rendre);
rendre();
document.documentElement.dataset.pret = '1';
