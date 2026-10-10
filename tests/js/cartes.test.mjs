import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as c from '../../site/js/lib/cartes.js';

const MAINTENANT = new Date('2026-10-14T15:45:00+02:00');

function carte(autres = {}) {
  return {
    id: 'ca01', domaine: 'etudes', colonne: 'a_faire', position: 1000, titre: 'MT23 : préparer le médian', description: '', url: '',
    echeance: null, echeance_faite: false, urgent: false, etiquettes: ['MT23'], checklists: [], derniere_activite: '2026-10-13T10:00:00+02:00', ...autres,
  };
}

test("retard : échéance passée, pas faite, hors de Fait ; une date seule (18 h) d'aujourd'hui n'est pas encore en retard", () => {
  assert.equal(c.enRetard(carte({ echeance: '2026-10-06T08:30:00+02:00' }), MAINTENANT), true);
  assert.equal(c.enRetard(carte({ echeance: '2026-10-06T08:30:00+02:00', echeance_faite: true }), MAINTENANT), false);
  assert.equal(c.enRetard(carte({ echeance: '2026-10-06T08:30:00+02:00', colonne: 'fait' }), MAINTENANT), false);
  const ceSoir = carte({ echeance: '2026-10-14T18:00:00+02:00' });
  assert.equal(c.enRetard(ceSoir, MAINTENANT), false);
  assert.equal(c.echeanceAujourdhui(ceSoir, MAINTENANT), true);
  assert.equal(c.enRetard(ceSoir, new Date('2026-10-14T18:01:00+02:00')), true);
});

test("libellés : « Échéance mer. 21/10 à 19 h 15 », « Échéance mer. 21/10 » pour une date seule, « En retard depuis le 06/10 »", () => {
  assert.deepEqual(c.libelleEcheance(carte({ echeance: '2026-10-21T19:15:00+02:00' }), MAINTENANT), { texte: 'Échéance mer. 21/10 à 19 h 15', retard: false });
  assert.deepEqual(c.libelleEcheance(carte({ echeance: '2026-10-21T18:00:00+02:00' }), MAINTENANT), { texte: 'Échéance mer. 21/10', retard: false });
  assert.deepEqual(c.libelleEcheance(carte({ echeance: '2026-10-06T08:30:00+02:00' }), MAINTENANT), { texte: 'En retard depuis le 06/10', retard: true });
  assert.equal(c.libelleEcheance(carte(), MAINTENANT), null);
});

test('avancement, étiquette, début du titre', () => {
  const etapes = { id: 'cc', nom: 'Étapes', elements: [{ id: 'e1', nom: 'a', coche: true }, { id: 'e2', nom: 'b', coche: false }] };
  assert.equal(c.avancement(carte({ checklists: [etapes, { ...etapes, id: 'cd' }] })), '2/4');
  assert.equal(c.avancement(carte()), null);
  assert.equal(c.etiquetteCarte(carte()), 'Études · MT23');
  assert.equal(c.etiquetteCarte(carte({ domaine: 'perso', etiquettes: [] })), 'Perso');
  assert.equal(c.debutTitre('Formation 2 : relire le deck'), 'Formation 2');
  assert.equal(c.debutTitre('Supprimer les anciens sites'), 'Supprimer les anciens sites');
});

test("comptes par domaine (À faire et En cours seulement) et groupes d'Aujourd'hui", () => {
  const cartes = [
    carte({ id: 'a', domaine: 'qyris', echeance: '2026-10-06T08:30:00+02:00' }),
    carte({ id: 'b', domaine: 'qyris', colonne: 'en_cours' }),
    carte({ id: 'c', domaine: 'qyris', colonne: 'en_attente', echeance: '2026-10-01T18:00:00+02:00' }),
    carte({ id: 'd', domaine: 'asso', urgent: true }),
    carte({ id: 'e', domaine: 'etudes', echeance: '2026-10-14T18:00:00+02:00', urgent: true }),
    carte({ id: 'f', domaine: 'perso', echeance: '2026-10-05T18:00:00+02:00' }),
    carte({ id: 'g', domaine: 'perso', colonne: 'fait', echeance: '2026-10-01T18:00:00+02:00' }),
  ];
  assert.deepEqual(c.compteurs(cartes, MAINTENANT), {
    qyris: { ouvertes: 2, retard: 1 }, asso: { ouvertes: 1, retard: 0 }, etudes: { ouvertes: 1, retard: 0 }, perso: { ouvertes: 1, retard: 1 },
  });
  const g = c.groupesAFaire(cartes, MAINTENANT);
  assert.deepEqual([g.retard.map((x) => x.id), g.aujourdhui.map((x) => x.id), g.urgent.map((x) => x.id)], [['f', 'a'], ['e'], ['d']]);
  const t = c.groupesTaches(cartes, 'qyris');
  assert.deepEqual([t.en_cours.length, t.a_faire.length, t.en_attente.length, t.fait.length], [1, 1, 1, 0]);
  assert.equal(c.groupesTaches(cartes, null).fait.length, 1);
  assert.equal(c.trouverCarte(cartes, 'd').domaine, 'asso');
  assert.equal(c.trouverCarte(cartes, 'zz'), null);
});
