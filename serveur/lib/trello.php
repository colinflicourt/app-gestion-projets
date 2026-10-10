<?php
// Trello : les 4 tableaux Jarvis lus en un appel, mis au format de l'iPhone. Ne connaît rien des écrans.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/rafraichir.php';
require_once __DIR__ . '/reponse.php';

const JR_TRELLO_API = 'https://api.trello.com/1';
const JR_COLONNES = ['À faire' => 'a_faire', 'En cours' => 'en_cours', 'En attente' => 'en_attente', 'Fait' => 'fait'];
const JR_REGEX_ID_TRELLO = '/^[0-9a-f]{24}$/';

// serveur/trello-ids.json, copié depuis .claude/scripts/ par generer-config.php (une seule source).
// Les tests posent $GLOBALS['JR_TRELLO_IDS'].
function trello_ids(): array
{
    if (!isset($GLOBALS['JR_TRELLO_IDS'])) {
        $GLOBALS['JR_TRELLO_IDS'] = json_decode((string) file_get_contents(__DIR__ . '/../trello-ids.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    return $GLOBALS['JR_TRELLO_IDS'];
}

// id de colonne => code (a_faire, en_cours, en_attente, fait)
function trello_colonnes(string $domaine): array
{
    $codes = [];
    foreach (trello_ids()['tableaux'][$domaine]['colonnes'] as $nom => $id) {
        $codes[(string) $id] = JR_COLONNES[$nom];
    }
    return $codes;
}

function trello_id_colonne(string $domaine, string $code): string
{
    $id = array_search($code, trello_colonnes($domaine), true);
    if ($id === false) {
        throw new InvalidArgumentException("Colonne inconnue : $code");
    }
    return (string) $id;
}

// Clé et jeton dans l'en-tête Authorization, jamais dans l'adresse (ni donc dans le journal)
function trello_appel(string $methode, string $chemin, array $parametres = [], ?array $corps = null): mixed
{
    $t = config('trello');
    $entetes = ['Authorization: OAuth oauth_consumer_key="' . $t['cle'] . '", oauth_token="' . $t['jeton'] . '"', 'Accept: application/json'];
    if ($corps !== null) {
        $entetes[] = 'Content-Type: application/json';
    }
    $url = JR_TRELLO_API . $chemin . ($parametres === [] ? '' : '?' . http_build_query($parametres, '', '&', PHP_QUERY_RFC3986));
    try {
        $reponse = http_requete($methode, $url, $entetes, $corps === null ? null : json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    } catch (ErreurReseau $e) {
        throw new ErreurSource('trello_indisponible', 'Trello : ' . $e->getMessage());
    }
    if ($reponse['statut'] < 200 || $reponse['statut'] >= 300) {
        $chemin_court = preg_replace('#/[0-9a-f]{24}#', '/…', $chemin);
        throw new ErreurSource('trello_indisponible', "Trello a répondu {$reponse['statut']} sur $methode $chemin_court", $reponse['statut']);
    }
    $json = http_json($reponse);
    if ($json === null && trim($reponse['corps']) !== 'null') {
        throw new ErreurSource('trello_indisponible', "Trello : réponse illisible sur $methode");
    }
    return $json;
}

// Une adresse par tableau. Aucune virgule dedans : /batch sépare les adresses par des virgules.
function trello_adresses_batch(): array
{
    $adresses = [];
    foreach (JR_DOMAINES as $domaine) {
        $adresses[] = '/boards/' . trello_ids()['tableaux'][$domaine]['id'] . '?fields=name&lists=open&cards=open&checklists=all&labels=all';
    }
    return $adresses;
}

// Les 4 tableaux en un appel (SPEC § 6.2) : cartes ouvertes, Fait compris, avec étiquettes et checklists
function trello_lire(): array
{
    $reponses = trello_appel('GET', '/batch', ['urls' => implode(',', trello_adresses_batch())]);
    if (!is_array($reponses) || count($reponses) !== count(JR_DOMAINES)) {
        throw new ErreurSource('trello_indisponible', 'Trello : réponse de /batch inattendue');
    }
    $cartes = [];
    foreach (JR_DOMAINES as $i => $domaine) {
        $tableau = $reponses[$i][200] ?? null;
        if (!is_array($tableau) || !isset($tableau['cards']) || !is_array($tableau['cards'])) {
            throw new ErreurSource('trello_indisponible', "Trello : tableau $domaine illisible dans /batch");
        }
        $checklists = [];
        foreach ($tableau['checklists'] ?? [] as $liste) {
            $checklists[$liste['idCard']][] = $liste;
        }
        $colonnes = trello_colonnes($domaine);
        foreach ($tableau['cards'] as $carte) {
            // Colonne ajoutée à la main dans Trello, ou carte archivée : ignorée
            if (!isset($colonnes[$carte['idList']]) || !empty($carte['closed'])) {
                continue;
            }
            $cartes[] = trello_normaliser_carte($domaine, $colonnes[$carte['idList']], $carte, $checklists[$carte['id']] ?? []);
        }
    }
    return ['cartes' => trier_cartes($cartes)];
}

// Format de l'iPhone (SPEC § 5.5), plus la position Trello (ordre des colonnes, « Annuler »)
function trello_normaliser_carte(string $domaine, string $colonne, array $carte, array $checklists): array
{
    $urgent_id = trello_ids()['tableaux'][$domaine]['etiquettes']['Urgent'] ?? null;
    usort($checklists, fn(array $a, array $b): int => $a['pos'] <=> $b['pos']);
    $listes = [];
    foreach ($checklists as $liste) {
        $elements = $liste['checkItems'] ?? [];
        usort($elements, fn(array $a, array $b): int => $a['pos'] <=> $b['pos']);
        $listes[] = [
            'id' => $liste['id'],
            'nom' => (string) $liste['name'],
            'elements' => array_map(fn(array $e): array => ['id' => $e['id'], 'nom' => (string) $e['name'], 'coche' => $e['state'] === 'complete'], $elements),
        ];
    }
    $etiquettes = [];
    foreach ($carte['labels'] ?? [] as $etiquette) {
        if ($etiquette['id'] !== $urgent_id && (string) ($etiquette['name'] ?? '') !== '') {
            $etiquettes[] = (string) $etiquette['name'];
        }
    }
    return [
        'id' => $carte['id'],
        'domaine' => $domaine,
        'colonne' => $colonne,
        'position' => (float) $carte['pos'],
        'titre' => (string) $carte['name'],
        'description' => (string) ($carte['desc'] ?? ''),
        'url' => (string) ($carte['shortUrl'] ?? ''),
        'echeance' => empty($carte['due']) ? null : iso_paris(new DateTimeImmutable($carte['due'])),
        'echeance_faite' => (bool) ($carte['dueComplete'] ?? false),
        'urgent' => $urgent_id !== null && in_array($urgent_id, $carte['idLabels'] ?? [], true),
        'etiquettes' => $etiquettes,
        'checklists' => $listes,
        'derniere_activite' => iso_paris(new DateTimeImmutable($carte['dateLastActivity'])),
    ];
}

function trier_cartes(array $cartes): array
{
    $rang = array_flip(JR_DOMAINES);
    usort($cartes, fn(array $a, array $b): int => [$rang[$a['domaine']], (float) $a['position']] <=> [$rang[$b['domaine']], (float) $b['position']]);
    return $cartes;
}

// Liste blanche (SPEC § 6.1) : la carte doit être dans l'un des 4 tableaux, d'après la copie ou, si elle y
// manque, d'après Trello. Rend la carte au format de l'iPhone. Rien n'est écrit avant ce contrôle.
function trello_carte_autorisee(mixed $carte_id): array
{
    if (!is_string($carte_id) || !preg_match(JR_REGEX_ID_TRELLO, $carte_id)) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    foreach (copie_lire('trello')['contenu']['cartes'] ?? [] as $carte) {
        if ($carte['id'] === $carte_id) {
            return $carte;
        }
    }
    try {
        $carte = trello_appel('GET', "/cards/$carte_id", ['checklists' => 'all']);
    } catch (ErreurSource $e) {
        if (in_array($e->statutHttp, [400, 404], true)) {
            throw new ErreurApi('carte_introuvable', 404, "Cette carte n'existe plus dans Trello.");
        }
        throw $e;
    }
    foreach (trello_ids()['tableaux'] as $domaine => $tableau) {
        $colonnes = trello_colonnes($domaine);
        if (is_array($carte) && ($carte['idBoard'] ?? null) === $tableau['id'] && isset($colonnes[$carte['idList'] ?? ''])) {
            return trello_normaliser_carte($domaine, $colonnes[$carte['idList']], $carte, $carte['checklists'] ?? []);
        }
    }
    throw new ErreurApi('carte_refusee', 403, 'Requête refusée.');
}

// Écriture vers Trello, ou simulée en dev (SPEC § 9) : rien ne part. Rend la réponse, ou null si simulée.
function trello_ecrire(string $methode, string $chemin, array $corps): mixed
{
    if (config('trello')['ecritures'] !== 'reelles') {
        return null;
    }
    return trello_appel($methode, $chemin, [], $corps);
}
