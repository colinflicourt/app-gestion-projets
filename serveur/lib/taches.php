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
require_once __DIR__ . '/xp_donnees.php';

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

// Une écriture tient le verrou de la copie Trello de l'appel à Trello jusqu'à la notation de l'action :
// un rafraîchissement ne peut pas lire Trello déjà modifié avant que l'action de l'app soit notée (elle
// serait notée une seconde fois comme faite ailleurs). Verrou pas obtenu : 502, l'iPhone réessaiera.
function sous_verrou_trello(callable $ecriture): mixed
{
    try {
        verrou_source_prendre('trello');
    } catch (VerrouIndisponible) {
        throw new ErreurSource('trello_indisponible', 'Trello : verrou de la copie indisponible');
    }
    try {
        return $ecriture();
    } finally {
        verrou_source_rendre('trello');
    }
}

// PUT sur une carte. Supprimée dans Trello depuis la copie (400 ou 404) : 404 pour que l'iPhone retire
// l'écriture de sa file, et la carte quitte la copie. Une étape disparue garde la carte.
function trello_ecrire_carte(string $carte_id, string $chemin, array $corps, string $introuvable = 'carte_introuvable'): void
{
    try {
        trello_ecrire('PUT', $chemin, $corps);
    } catch (ErreurSource $e) {
        if (!in_array($e->statutHttp, [400, 404], true)) {
            throw $e;
        }
        if ($introuvable === 'carte_introuvable') {
            copie_modifier('trello', fn(array $c): array => ['cartes' => array_values(array_filter($c['cartes'], fn(array $x): bool => $x['id'] !== $carte_id))] + $c);
        }
        throw new ErreurApi($introuvable, 404, JR_MESSAGES_INTROUVABLE[$introuvable]);
    }
}

function tache_cocher(mixed $carte_id, mixed $element_id, mixed $coche, mixed $fait_le = null): array
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
        throw new ErreurApi('element_introuvable', 404, JR_MESSAGES_INTROUVABLE['element_introuvable']);
    }
    $action = sous_verrou_trello(function () use ($carte, $element_id, $coche, $avant, $fait_le): ?int {
        trello_ecrire_carte($carte['id'], "/cards/{$carte['id']}/checkItem/$element_id", ['state' => $coche ? 'complete' : 'incomplete'], 'element_introuvable');
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
        return $avant === $coche ? null : action_noter($coche ? 'etape_cochee' : 'etape_decochee', $carte['domaine'], $carte['id'], $element_id, heure_du_geste($fait_le), 'app', 'exacte');
    });
    return ['carte' => $carte['id'], 'element' => $element_id, 'coche' => $coche, 'xp_gagnee' => $action === null ? 0 : xp_gain_action($action), 'domaine' => $carte['domaine']];
}

// « C'est parti » après un tirage, pour le badge « Machine à sous » du lot 4. Carte entière : élément nul.
function tache_tirage_parti(mixed $carte_id, mixed $element_id, mixed $fait_le = null): array
{
    if ($element_id !== null && (!is_string($element_id) || !preg_match(JR_REGEX_ID_TRELLO, $element_id))) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    $carte = trello_carte_autorisee($carte_id);
    $etapes = array_merge(...array_map(fn(array $l): array => array_column($l['elements'], 'id'), $carte['checklists']));
    if ($element_id !== null && !in_array($element_id, $etapes, true)) {
        throw new ErreurApi('element_introuvable', 404, JR_MESSAGES_INTROUVABLE['element_introuvable']);
    }
    action_noter('tirage_parti', $carte['domaine'], $carte['id'], $element_id, heure_du_geste($fait_le), 'app', 'exacte');
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
function tache_terminer(mixed $carte_id, mixed $fait_le = null): array
{
    $carte = trello_carte_autorisee($carte_id);
    if ($carte['colonne'] === 'fait') {
        throw new ErreurApi('deja_faite', 409, 'Cette carte est déjà terminée.');
    }
    $corps = ['idList' => trello_id_colonne($carte['domaine'], 'fait'), 'pos' => 'top'];
    if ($carte['echeance'] !== null) {
        $corps['dueComplete'] = true;
    }
    $action = sous_verrou_trello(function () use ($carte, $corps, $fait_le): int {
        trello_ecrire_carte($carte['id'], "/cards/{$carte['id']}", $corps);
        deplacer_dans_la_copie($carte['id'], 'fait', $carte['echeance'] !== null || $carte['echeance_faite']);
        return action_noter('carte_terminee', $carte['domaine'], $carte['id'], null, heure_du_geste($fait_le), 'app', 'exacte', ['urgent' => $carte['urgent'], 'echeance' => $carte['echeance']]);
    });
    return [
        'carte' => $carte['id'],
        'colonne' => 'fait',
        'colonne_avant' => $carte['colonne'],
        'position_avant' => (float) $carte['position'],
        'echeance_faite_avant' => $carte['echeance_faite'],
        'action' => $action,
        'xp_gagnee' => xp_gain_action($action),
        'domaine' => $carte['domaine'],
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
    // Ce que l'action annulée avait rapporté, lu dans le rejeu juste avant de l'annuler (0 si déjà annulée)
    $gain = sous_verrou_trello(function () use ($carte, $r): int {
        trello_ecrire_carte($carte['id'], "/cards/{$carte['id']}", [
            'idList' => trello_id_colonne($carte['domaine'], $r['colonne_avant']),
            'pos' => (float) $r['position_avant'],
            'dueComplete' => (bool) $r['echeance_faite_avant'],
        ]);
        deplacer_dans_la_copie($carte['id'], $r['colonne_avant'], (bool) $r['echeance_faite_avant'], (float) $r['position_avant']);
        $gain = xp_gain_action((int) $r['action']);
        action_annuler((int) $r['action']);
        return $gain;
    });
    return ['carte' => $carte['id'], 'colonne' => $r['colonne_avant'], 'xp_gagnee' => -$gain, 'domaine' => $carte['domaine']];
}

// Vers Fait, c'est « Terminer ». Sortir de Fait rouvre la carte.
function tache_deplacer(mixed $carte_id, mixed $colonne, mixed $fait_le = null): array
{
    if (!in_array($colonne, ['a_faire', 'en_cours', 'en_attente'], true)) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    $carte = trello_carte_autorisee($carte_id);
    if ($carte['colonne'] === $colonne) {
        return ['carte' => $carte['id'], 'colonne' => $colonne, 'xp_gagnee' => 0, 'domaine' => $carte['domaine']];
    }
    $action = sous_verrou_trello(function () use ($carte, $colonne, $fait_le): ?int {
        $corps = ['idList' => trello_id_colonne($carte['domaine'], $colonne), 'pos' => 'top'];
        $echeance_faite = $carte['echeance_faite'];
        // Une carte rouverte redevient à faire : son échéance n'est plus marquée faite (elle peut être en retard)
        if ($carte['colonne'] === 'fait' && $carte['echeance'] !== null) {
            $corps['dueComplete'] = false;
            $echeance_faite = false;
        }
        trello_ecrire_carte($carte['id'], "/cards/{$carte['id']}", $corps);
        deplacer_dans_la_copie($carte['id'], $colonne, $echeance_faite);
        return $carte['colonne'] === 'fait' ? action_noter('carte_rouverte', $carte['domaine'], $carte['id'], null, heure_du_geste($fait_le), 'app', 'exacte') : null;
    });
    return ['carte' => $carte['id'], 'colonne' => $colonne, 'xp_gagnee' => $action === null ? 0 : xp_gain_action($action), 'domaine' => $carte['domaine']];
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

const JR_TENTATIVES_JOURS = 1;

// Une capture dont l'envoi à Trello a commencé (revue du plan 2) : si sa réponse se perd, le renvoi
// cherche la carte avant d'en créer une seconde. ecriture_unique() tient déjà le verrou de l'id_client.
function tentative_debut(string $id_client): ?DateTimeImmutable
{
    $requete = db()->prepare('SELECT debut FROM tentatives WHERE id_client = ?');
    $requete->execute([$id_client]);
    $debut = $requete->fetchColumn();
    return $debut === false ? null : depuis_utc((string) $debut);
}

function tentative_noter(string $id_client): void
{
    $maintenant = maintenant();
    db()->prepare('INSERT INTO tentatives (id_client, debut) VALUES (?, ?)')->execute([$id_client, utc($maintenant)]);
    db()->prepare('DELETE FROM tentatives WHERE debut < ? LIMIT 50')
        ->execute([utc($maintenant->modify('-' . JR_TENTATIVES_JOURS . ' days'))]);
}

function tentative_oublier(string $id_client): void
{
    db()->prepare('DELETE FROM tentatives WHERE id_client = ?')->execute([$id_client]);
}

// La carte de ce titre créée depuis $debut (une minute de marge) dans « À faire », ou null.
// Les 8 premiers caractères d'un identifiant Trello donnent l'heure de création, en secondes.
function trello_carte_deja_creee(string $domaine, string $titre, DateTimeImmutable $debut): ?array
{
    $cartes = trello_appel('GET', '/lists/' . trello_id_colonne($domaine, 'a_faire') . '/cards', [
        'fields' => 'name,desc,idBoard,idList,idLabels,labels,pos,due,dueComplete,closed,shortUrl,dateLastActivity',
    ]);
    foreach (is_array($cartes) ? $cartes : [] as $carte) {
        if (is_array($carte) && ($carte['name'] ?? null) === $titre && preg_match(JR_REGEX_ID_TRELLO, (string) ($carte['id'] ?? ''))
            && hexdec(substr($carte['id'], 0, 8)) >= $debut->getTimestamp() - 60) {
            return $carte;
        }
    }
    return null;
}

function tache_ajouter(mixed $domaine, mixed $titre, mixed $urgent, mixed $echeance, ?string $id_client = null): array
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
    $carte = sous_verrou_trello(function () use ($domaine, $titre, $urgent, $quand, $corps, $id_client): array {
        $suivie = $id_client !== null && config('trello')['ecritures'] === 'reelles';
        $debut = $suivie ? tentative_debut($id_client) : null;
        $cree = $debut !== null ? trello_carte_deja_creee($domaine, $titre, $debut) : null;
        if ($cree === null) {
            if ($suivie && $debut === null) {
                tentative_noter($id_client);
            }
            $cree = trello_ecrire('POST', '/cards', $corps);
        }
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
        if ($suivie) {
            tentative_oublier($id_client);
        }
        return $carte;
    });
    return ['carte' => $carte];
}
