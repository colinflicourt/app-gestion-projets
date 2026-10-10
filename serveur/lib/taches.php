<?php
// Écritures de la to-do venues de l'iPhone (SPEC § 5.4, § 6.3) : liste blanche, Trello, copie corrigée
// aussitôt, journal d'actions (source app, heure exacte).

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/ecritures.php';
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

const JR_ANNULER_DELAI = 10;  // l'iPhone propose « Annuler » 5 s, le reste couvre la latence

// Position d'une carte posée en haut de $colonne (Trello fait de même avec pos=top)
function position_en_haut(array $cartes, string $domaine, string $colonne, ?string $sauf = null): float
{
    $positions = [];
    foreach ($cartes as $c) {
        if ($c['domaine'] === $domaine && $c['colonne'] === $colonne && $c['id'] !== $sauf) {
            $positions[] = (float) $c['position'];
        }
    }
    if ($positions === []) {
        return 65536.0;
    }
    $min = min($positions);
    return $min > 0 ? $min / 2 : $min - 1;
}

// La carte passe dans $colonne, en haut ou à $position (Annuler), dans la copie du serveur
function deplacer_dans_la_copie(string $carte_id, string $colonne, bool $echeance_faite, ?float $position = null): void
{
    copie_modifier('trello', function (array $contenu) use ($carte_id, $colonne, $echeance_faite, $position): array {
        foreach ($contenu['cartes'] as $i => $c) {
            if ($c['id'] === $carte_id) {
                $contenu['cartes'][$i]['position'] = $position ?? position_en_haut($contenu['cartes'], $c['domaine'], $colonne, $carte_id);
                $contenu['cartes'][$i]['colonne'] = $colonne;
                $contenu['cartes'][$i]['echeance_faite'] = $echeance_faite;
                $contenu['cartes'][$i]['derniere_activite'] = iso_paris(maintenant());
            }
        }
        $contenu['cartes'] = trier_cartes($contenu['cartes']);
        return $contenu;
    });
}

// En haut de Fait, échéance marquée faite (comme trello.py terminer). Ce qu'il faut pour annuler est rendu,
// donc gardé dans ecritures.resultat.
function tache_terminer(mixed $carte_id): array
{
    $carte = trello_carte_autorisee($carte_id);
    if ($carte['colonne'] === 'fait') {
        throw new ErreurApi('deja_faite', 409, 'Cette carte est déjà terminée.');
    }
    $corps = ['idList' => trello_id_colonne($carte['domaine'], 'fait'), 'pos' => 'top'];
    if ($carte['echeance'] !== null) {
        $corps['dueComplete'] = true;
    }
    trello_ecrire('PUT', "/cards/{$carte['id']}", $corps);
    deplacer_dans_la_copie($carte['id'], 'fait', $carte['echeance'] !== null || $carte['echeance_faite']);
    $action = action_noter('carte_terminee', $carte['domaine'], $carte['id'], null, maintenant(), 'app', 'exacte', ['urgent' => $carte['urgent'], 'echeance' => $carte['echeance']]);
    return [
        'carte' => $carte['id'],
        'colonne' => 'fait',
        'colonne_avant' => $carte['colonne'],
        'position_avant' => (float) $carte['position'],
        'echeance_faite_avant' => $carte['echeance_faite'],
        'action' => $action,
    ];
}

function tache_annuler(mixed $id_client_origine): array
{
    if (!is_string($id_client_origine)) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    $origine = ecriture_trouver($id_client_origine);
    if ($origine === null || $origine['route'] !== 'taches/terminer') {
        throw new ErreurApi('annulation_impossible', 404, 'Rien à annuler.');
    }
    if (maintenant()->getTimestamp() - depuis_utc($origine['recu_le'])->getTimestamp() > JR_ANNULER_DELAI) {
        throw new ErreurApi('annulation_trop_tard', 409, 'Trop tard pour annuler.');
    }
    $r = $origine['resultat'];
    $carte = trello_carte_autorisee($r['carte']);
    trello_ecrire('PUT', "/cards/{$carte['id']}", [
        'idList' => trello_id_colonne($carte['domaine'], $r['colonne_avant']),
        'pos' => (float) $r['position_avant'],
        'dueComplete' => (bool) $r['echeance_faite_avant'],
    ]);
    deplacer_dans_la_copie($carte['id'], $r['colonne_avant'], (bool) $r['echeance_faite_avant'], (float) $r['position_avant']);
    action_annuler((int) $r['action']);
    return ['carte' => $carte['id'], 'colonne' => $r['colonne_avant']];
}

// Vers Fait, c'est « Terminer ». Sortir de Fait rouvre la carte.
function tache_deplacer(mixed $carte_id, mixed $colonne): array
{
    if (!in_array($colonne, ['a_faire', 'en_cours', 'en_attente'], true)) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    $carte = trello_carte_autorisee($carte_id);
    if ($carte['colonne'] === $colonne) {
        return ['carte' => $carte['id'], 'colonne' => $colonne];
    }
    trello_ecrire('PUT', "/cards/{$carte['id']}", ['idList' => trello_id_colonne($carte['domaine'], $colonne), 'pos' => 'top']);
    deplacer_dans_la_copie($carte['id'], $colonne, $carte['echeance_faite']);
    if ($carte['colonne'] === 'fait') {
        action_noter('carte_rouverte', $carte['domaine'], $carte['id'], null, maintenant(), 'app', 'exacte');
    }
    return ['carte' => $carte['id'], 'colonne' => $colonne];
}

const JR_TITRE_MAX = 300;

// Échéance de l'iPhone, heure de Paris : AAAA-MM-JJ (18 h 00, comme trello.py) ou AAAA-MM-JJTHH:MM
function echeance_lire(mixed $texte): ?DateTimeImmutable
{
    if ($texte === null || $texte === '') {
        return null;
    }
    $refus = new ErreurApi('echeance_invalide', 400, 'Date illisible.');
    if (!is_string($texte) || !preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $texte)) {
        throw $refus;
    }
    $format = strlen($texte) === 10 ? 'Y-m-d' : 'Y-m-d\TH:i';
    $moment = DateTimeImmutable::createFromFormat('!' . $format, $texte, paris());
    // 2026-02-30 ou 25:00 sont « corrigés » par PHP : la relecture ne redonne alors pas le texte
    if ($moment === false || $moment->format($format) !== $texte) {
        throw $refus;
    }
    return strlen($texte) === 10 ? $moment->setTime(18, 0) : $moment;
}

function tache_ajouter(mixed $domaine, mixed $titre, mixed $urgent, mixed $echeance): array
{
    if (!in_array($domaine, JR_DOMAINES, true) || !is_string($titre) || ($urgent !== null && !is_bool($urgent))) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    $titre = trim((string) preg_replace('/\s+/u', ' ', $titre));
    if ($titre === '' || mb_strlen($titre) > JR_TITRE_MAX) {
        throw new ErreurApi('titre_invalide', 400, 'Il me faut un titre (300 caractères au plus).');
    }
    $urgent = $urgent === true;
    $quand = echeance_lire($echeance);
    $corps = ['idList' => trello_id_colonne($domaine, 'a_faire'), 'name' => $titre, 'pos' => 'top'];
    if ($urgent) {
        $corps['idLabels'] = [trello_ids()['tableaux'][$domaine]['etiquettes']['Urgent']];
    }
    if ($quand !== null) {
        $corps['due'] = $quand->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
    $cree = trello_ecrire('POST', '/cards', $corps);
    if (is_array($cree)) {
        $carte = trello_normaliser_carte($domaine, 'a_faire', $cree, []);
    } else {
        // Simulée : la carte n'existe que dans la copie
        $carte = [
            'id' => bin2hex(random_bytes(12)),
            'domaine' => $domaine,
            'colonne' => 'a_faire',
            'position' => position_en_haut(copie_lire('trello')['contenu']['cartes'] ?? [], $domaine, 'a_faire'),
            'titre' => $titre,
            'description' => '',
            'url' => '',
            'echeance' => $quand === null ? null : iso_paris($quand),
            'echeance_faite' => false,
            'urgent' => $urgent,
            'etiquettes' => [],
            'checklists' => [],
            'derniere_activite' => iso_paris(maintenant()),
        ];
    }
    copie_modifier('trello', fn(array $contenu): array => ['cartes' => trier_cartes([...$contenu['cartes'], $carte])] + $contenu);
    action_noter('carte_ajoutee', $domaine, $carte['id'], null, maintenant(), 'app', 'exacte', ['urgent' => $urgent, 'echeance' => $carte['echeance']]);
    return ['carte' => $carte];
}
