// La feuille « Noter » (bouton +, CONTENU § 4) : une tâche dans l'un des 4 tableaux, ou un rappel. La carte
// ou le rappel s'affiche tout de suite ; sans réseau, il part dès qu'il revient.
import { h, icone } from '../lib/dom.js';
import { T } from '../lib/textes.js';
import { DOMAINES, NOMS, SCOTCH } from '../lib/cartes.js';
import { isoParis } from '../lib/temps.js';
import { choixLibre, choixRappel, texteConfirmation } from '../lib/heures-rappel.js';

const C = T.capture;
const ANGLES = { qyris: '-1.4deg', asso: '0.9deg', etudes: '-0.7deg', perso: '1.4deg' };
const TITRE_MAX = 300;

// Le champ reçoit le focus à l'ouverture seulement (le clavier de l'iPhone ne doit pas revenir à chaque
// choix) ; ensuite, le focus reste dans la feuille, pour VoiceOver et pour la touche Échap
export function apres(conteneur, f) {
  if (!f.focus) {
    f.focus = true;
    conteneur.querySelector('#capture-titre')?.focus();
  } else if (!conteneur.contains(document.activeElement)) {
    conteneur.querySelector('.feuille')?.focus({ preventScroll: true });
  }
}

async function ajouterRappel(app, f, texte) {
  const maintenant = app.maintenant();
  const choix = f.rappel === 'choisir' ? choixLibre(f.quandChoisi, maintenant) : choixRappel(maintenant).find((c) => c.id === f.rappel);
  if (choix.erreur) {
    f.erreur = choix.erreur;
    app.rendreCapture();
    return;
  }
  const horsLigne = app.etat.horsLigne || !navigator.onLine;
  app.fermerCapture();
  await app.ecrire('rappels', { texte, quand: choix.quand }, { id: `local-${crypto.randomUUID()}` });
  app.message(horsLigne ? C.horsLigne : texteConfirmation(choix, maintenant));
}

async function ajouter(app, f) {
  const titre = f.titre.trim().replace(/\s+/gu, ' ');
  if (titre === '' || titre.length > TITRE_MAX) {
    f.erreur = C.titreInvalide;
    app.rendreCapture();
    return;
  }
  if (f.mode === 'rappel') return ajouterRappel(app, f, titre);
  if (!DOMAINES.includes(f.domaine)) {
    f.erreur = C.choisirDomaine;
    app.rendreCapture();
    return;
  }
  try {
    localStorage.setItem('jr-domaine', f.domaine);
  } catch {
    // simple préférence, sans importance
  }
  const horsLigne = app.etat.horsLigne || !navigator.onLine;
  app.fermerCapture();
  await app.ecrire('taches/ajouter', { domaine: f.domaine, titre, urgent: f.urgent, echeance: f.date ? (f.heure ? `${f.date}T${f.heure}` : f.date) : null }, {
    id: `local-${crypto.randomUUID()}`,
    echeance_iso: f.date ? isoParis(f.date, f.heure || '18:00') : null,
    cree_le: app.maintenant().toISOString(),
  });
  app.message(horsLigne ? C.horsLigne : C.ajoute(NOMS[f.domaine]));
}

function choixDuType(f, redessiner) {
  const bouton = (mode, texte) => h('button', {
    type: 'button', class: 'bascule-choix', 'aria-pressed': String(f.mode === mode),
    onclick: () => {
      f.mode = mode;
      f.erreur = null;
      redessiner();
    },
  }, texte);
  return h('div', { class: 'bascule capture-type', role: 'group', 'aria-label': C.type }, bouton('tache', C.tache), bouton('rappel', C.rappelNom));
}

function choixDuRappel(app, f, redessiner) {
  const date = h('input', {
    id: 'capture-quand', class: 'champ champ-date', type: 'datetime-local',
    onchange: (e) => {
      f.quandChoisi = e.target.value;
      f.erreur = null;
    },
  });
  date.value = f.quandChoisi;
  return [
    h('div', { class: 'capture-quand', role: 'group', 'aria-label': C.quand }, choixRappel(app.maintenant()).map((c) => h('button', {
      type: 'button', class: 'bouton capture-quand-choix', 'aria-pressed': String(f.rappel === c.id),
      onclick: () => {
        f.rappel = c.id;
        f.erreur = null;
        redessiner();
      },
    }, c.libelle))),
    f.rappel === 'choisir' && h('label', { class: 'capture-echeance capture-heure', for: 'capture-quand' }, h('span', { class: 'sr-only' }, C.dateRappel), date),
  ];
}

export function rendre(app, f) {
  f.mode ??= 'tache';
  f.rappel ??= '1h';
  f.quandChoisi ??= '';
  const redessiner = () => app.rendreCapture();
  const champ = h('textarea', {
    id: 'capture-titre', class: 'champ capture-champ', rows: 2, placeholder: C.champ, maxlength: TITRE_MAX,
    oninput: (e) => { f.titre = e.target.value; },
  });
  champ.value = f.titre;
  const date = h('input', {
    id: 'capture-date', class: 'champ champ-date', type: 'date',
    onchange: (e) => {
      f.date = e.target.value;
      redessiner();
    },
  });
  date.value = f.date;
  const heure = f.date && h('input', { id: 'capture-heure', class: 'champ champ-date', type: 'time', onchange: (e) => { f.heure = e.target.value; } });
  if (heure) heure.value = f.heure;
  return h('div', {
    class: 'voile',
    onclick: (e) => { if (e.target === e.currentTarget) app.fermerCapture(); },
    onkeydown: (e) => { if (e.key === 'Escape') app.fermerCapture(); },
  },
    h('section', { class: 'feuille', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'capture-nom', tabindex: '-1' },
      h('div', { class: 'feuille-poignee', 'aria-hidden': 'true' }),
      h('div', { class: 'feuille-entete' },
        h('h1', { id: 'capture-nom', class: 'feuille-titre' }, C.titre),
        h('button', { type: 'button', class: 'bouton-carre bouton-console', 'aria-label': C.fermer, onclick: () => app.fermerCapture() }, icone('fermer', 18, 2.25))),
      h('label', { for: 'capture-titre', class: 'sr-only' }, C.champAria),
      champ,
      h('p', { class: 'capture-aide' }, icone('micro', 14, 2), C.dicter),
      choixDuType(f, redessiner),
      f.mode === 'rappel' ? choixDuRappel(app, f, redessiner) : [
        h('p', { id: 'capture-domaine', class: 'section-titre' }, C.domaine),
        h('div', { class: 'capture-domaines', role: 'group', 'aria-labelledby': 'capture-domaine' }, DOMAINES.map((d) => h('button', {
          type: 'button', class: `capture-domaine d-${d}`, 'aria-pressed': String(f.domaine === d), 'aria-label': NOMS[d],
          onclick: () => {
            f.domaine = d;
            f.erreur = null;
            redessiner();
          },
        }, h('span', { class: 'scotch', vars: { '--angle': ANGLES[d] } }, SCOTCH[d]), h('span', { class: 'capture-trait' })))),
        h('div', { class: 'capture-options' },
          h('button', {
            type: 'button', class: 'bouton capture-urgent', 'aria-pressed': String(f.urgent),
            onclick: () => {
              f.urgent = !f.urgent;
              redessiner();
            },
          }, h('span', { class: 'lampe' }), C.urgent),
          h('label', { class: 'capture-echeance', for: 'capture-date' }, h('span', {}, C.echeance), date)),
        heure && h('label', { class: 'capture-echeance capture-heure', for: 'capture-heure' }, h('span', {}, C.heure), heure),
        f.date && h('button', {
          type: 'button', class: 'lien-discret',
          onclick: () => {
            f.date = '';
            f.heure = '';
            redessiner();
          },
        }, C.effacer),
      ],
      f.erreur && h('p', { class: 'erreur', role: 'alert' }, f.erreur),
      h('div', { class: 'cuvette cuvette-large' }, h('button', { type: 'button', class: 'bouton-allume bouton-large', onclick: () => ajouter(app, f) }, C.ajouter))));
}
