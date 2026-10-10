<?php
// Écritures de la to-do venues de l'iPhone (SPEC § 5.4, § 6.3) : liste blanche, Trello, copie corrigée
// aussitôt, journal d'actions (source app, heure exacte).

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/rafraichir.php';
require_once __DIR__ . '/reponse.php';
require_once __DIR__ . '/trello.php';

function copie_modifier_carte(string $carte_id, callable $modifier): void
{
    copie_modifier('trello', function (array $contenu) use ($carte_id, $modifier): array {
        foreach ($contenu['cartes'] as $i => $carte) {
            if ($carte['id'] === $carte_id) {
                $contenu['cartes'][$i] = $modifier($carte);
            }
        }
        return $contenu;
    });
}

function tache_cocher(mixed $carte_id, mixed $element_id, mixed $coche): array
{
    if (!is_bool($coche) || !is_string($element_id) || !preg_match(JR_REGEX_ID_TRELLO, $element_id)) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    $carte = trello_carte_autorisee($carte_id);
    $avant = null;
    foreach ($carte['checklists'] as $liste) {
        foreach ($liste['elements'] as $element) {
            if ($element['id'] === $element_id) {
                $avant = $element['coche'];
            }
        }
    }
    if ($avant === null) {
        throw new ErreurApi('element_introuvable', 404, "Cette étape n'existe plus dans Trello.");
    }
    trello_ecrire('PUT', "/cards/{$carte['id']}/checkItem/$element_id", ['state' => $coche ? 'complete' : 'incomplete']);
    copie_modifier_carte($carte['id'], function (array $c) use ($element_id, $coche): array {
        foreach ($c['checklists'] as $l => $liste) {
            foreach ($liste['elements'] as $e => $element) {
                if ($element['id'] === $element_id) {
                    $c['checklists'][$l]['elements'][$e]['coche'] = $coche;
                }
            }
        }
        $c['derniere_activite'] = iso_paris(maintenant());
        return $c;
    });
    // Pas d'action pour une étape déjà dans cet état : pas d'XP pour rien
    if ($avant !== $coche) {
        action_noter($coche ? 'etape_cochee' : 'etape_decochee', $carte['domaine'], $carte['id'], $element_id, maintenant(), 'app', 'exacte');
    }
    return ['carte' => $carte['id'], 'element' => $element_id, 'coche' => $coche];
}

// « C'est parti » après un tirage, pour le badge « Machine à sous » du lot 4. Carte entière : élément nul.
function tache_tirage_parti(mixed $carte_id, mixed $element_id): array
{
    if ($element_id !== null && (!is_string($element_id) || !preg_match(JR_REGEX_ID_TRELLO, $element_id))) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    $carte = trello_carte_autorisee($carte_id);
    action_noter('tirage_parti', $carte['domaine'], $carte['id'], $element_id, maintenant(), 'app', 'exacte');
    return ['carte' => $carte['id'], 'element' => $element_id];
}
