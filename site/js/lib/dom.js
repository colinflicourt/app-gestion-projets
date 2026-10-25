// Construction du DOM sans innerHTML : le texte passe toujours par textContent (titres de cartes,
// descriptions et événements viennent de Trello et de Google). Aucun style en ligne (CSP) : les valeurs
// variables passent par des propriétés CSS (option vars).

export function h(tag, props = {}, ...enfants) {
  const el = document.createElement(tag);
  for (const [cle, valeur] of Object.entries(props ?? {})) {
    if (valeur === null || valeur === undefined || valeur === false) continue;
    if (cle === 'class') el.className = valeur;
    else if (cle === 'vars') for (const [nom, v] of Object.entries(valeur)) el.style.setProperty(nom, v);
    else if (cle === 'dataset') Object.assign(el.dataset, valeur);
    else if (cle.startsWith('on')) el.addEventListener(cle.slice(2), valeur);
    else el.setAttribute(cle, valeur === true ? '' : String(valeur));
  }
  ajouter(el, enfants);
  return el;
}

function ajouter(el, enfants) {
  for (const enfant of enfants) {
    if (enfant === null || enfant === undefined || enfant === false) continue;
    if (Array.isArray(enfant)) ajouter(el, enfant);
    else el.append(enfant instanceof Node ? enfant : String(enfant));
  }
}

const NS = 'http://www.w3.org/2000/svg';
const ICONES = {
  aujourdhui: [['path', { d: 'M6 4v16M12 4v16M18 4v16' }], ['rect', { x: 4, y: 13, width: 4, height: 3, rx: 1, fill: 'currentColor' }], ['rect', { x: 10, y: 7, width: 4, height: 3, rx: 1, fill: 'currentColor' }], ['rect', { x: 16, y: 10, width: 4, height: 3, rx: 1, fill: 'currentColor' }]],
  agenda: [['rect', { x: 3.5, y: 5, width: 17, height: 15, rx: 2 }], ['path', { d: 'M3.5 10h17M8 3v4M16 3v4' }]],
  taches: [['path', { d: 'M4 6.5l1.6 1.6 2.9-2.9M4 12.5l1.6 1.6 2.9-2.9M4 18.5l1.6 1.6 2.9-2.9M11.5 7h8.5M11.5 13h8.5M11.5 19h8.5' }]],
  mails: [['rect', { x: 3.5, y: 5.5, width: 17, height: 13, rx: 2 }], ['path', { d: 'M4 7.5l8 6 8-6' }]],
  moi: [['circle', { cx: 12, cy: 8.5, r: 3.5 }], ['path', { d: 'M5 20c1.2-3.6 4-5.5 7-5.5s5.8 1.9 7 5.5' }]],
  plus: [['path', { d: 'M12 5v14M5 12h14' }]],
  micro: [['rect', { x: 9, y: 3, width: 6, height: 11, rx: 3 }], ['path', { d: 'M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21' }]],
  gauche: [['path', { d: 'M15 5l-7 7 7 7' }]],
  droite: [['path', { d: 'M9 5l7 7-7 7' }]],
  fermer: [['path', { d: 'M6 6l12 12M18 6L6 18' }]],
  externe: [['path', { d: 'M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5' }]],
  fichier: [['path', { d: 'M7 3.5h7l4 4V20a.5.5 0 0 1-.5.5h-10A.5.5 0 0 1 7 20z' }], ['path', { d: 'M14 3.5V8h4M9.5 13h5M9.5 16.5h5' }]],
  image: [['rect', { x: 3.5, y: 5, width: 17, height: 14, rx: 2 }], ['circle', { cx: 9, cy: 10, r: 1.6 }], ['path', { d: 'M4 17l5-4.5 3.5 3L15 13l5 4.5' }]],
  points: [['circle', { cx: 6, cy: 12, r: 1.2, fill: 'currentColor' }], ['circle', { cx: 12, cy: 12, r: 1.2, fill: 'currentColor' }], ['circle', { cx: 18, cy: 12, r: 1.2, fill: 'currentColor' }]],
  repondre: [['path', { d: 'M10 6L4 12l6 6M4 12h9a7 7 0 0 1 7 7' }]],
  ferie: [['rect', { x: 3.5, y: 5, width: 17, height: 15, rx: 2 }], ['path', { d: 'M3.5 10h17M9 14l6 4M15 14l-6 4' }]],
};

export function icone(nom, taille = 22, epaisseur = 1.75) {
  const svg = document.createElementNS(NS, 'svg');
  const attributs = { width: taille, height: taille, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': epaisseur, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true', focusable: 'false' };
  for (const [cle, valeur] of Object.entries(attributs)) svg.setAttribute(cle, String(valeur));
  for (const [tag, attrs] of ICONES[nom]) {
    const trait = document.createElementNS(NS, tag);
    for (const [cle, valeur] of Object.entries(attrs)) trait.setAttribute(cle, String(valeur));
    svg.append(trait);
  }
  return svg;
}

// Texte brut avec ses adresses http(s) en liens ; toute autre « adresse » (javascript:…) reste du texte
export function texteAvecLiens(texte) {
  const morceaux = [];
  let depuis = 0;
  for (const m of texte.matchAll(/https?:\/\/[^\s<>"')\]]+/g)) {
    const adresse = m[0].replace(/[.,;:!?]+$/, '');
    morceaux.push(texte.slice(depuis, m.index), h('a', { href: adresse, target: '_blank', rel: 'noopener noreferrer' }, adresse));
    depuis = m.index + adresse.length;
  }
  morceaux.push(texte.slice(depuis));
  return morceaux.filter((x) => x !== '');
}

// Angle stable pour un tampon ou un scotch : la même clé penche toujours pareil
export function angle(cle, max = 8) {
  let n = 0;
  for (const c of String(cle)) n = (n * 31 + c.codePointAt(0)) | 0;
  return `${((Math.abs(n) % (max * 20)) / 10 - max).toFixed(1)}deg`;
}
