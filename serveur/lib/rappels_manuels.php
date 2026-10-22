<?php
// Rappels manuels (SPEC § 15.2 et § 15.6) : création, annulation, et la liste « rappels du jour » de GET donnees.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/reponse.php';

const JR_RAPPEL_TEXTE_MAX = 500;
const JR_RAPPEL_PASSE_TOLERE = 60;
const JR_RAPPEL_TYPES_JOUR = ['cours', 'echeance', 'median', 'brief'];

// ISO 8601 avec décalage ; rend le moment, ou null s'il est illisible ou sans décalage
function rappel_quand_lire(mixed $quand): ?DateTimeImmutable
{
    if (!is_string($quand) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/', $quand)) {
        return null;
    }
    try {
        $moment = new DateTimeImmutable($quand);
    } catch (Exception) {
        return null;
    }
    $erreurs = DateTimeImmutable::getLastErrors();
    return $erreurs !== false && ($erreurs['warning_count'] > 0 || $erreurs['error_count'] > 0) ? null : $moment;
}

function rappel_creer(mixed $texte, mixed $quand, string $id_client): array
{
    $texte = is_string($texte) ? trim($texte) : '';
    $longueur = mb_strlen($texte);
    if ($longueur < 1 || $longueur > JR_RAPPEL_TEXTE_MAX) {
        throw new ErreurApi('rappel_invalide', 400, 'Le texte du rappel doit faire entre 1 et 500 caractères.');
    }
    $moment = rappel_quand_lire($quand);
    $maintenant = maintenant();
    if ($moment === null
        || $moment < $maintenant->modify('-' . JR_RAPPEL_PASSE_TOLERE . ' seconds')
        || $moment > $maintenant->modify('+1 year')) {
        throw new ErreurApi('rappel_invalide', 400, "L'heure du rappel est invalide.");
    }
    $pdo = db();
    $pdo->prepare('INSERT INTO rappels (id_client, texte, quand, cree_le, source) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id_client, $texte, utc($moment), utc($maintenant), 'app']);
    return ['rappel' => ['id' => (int) $pdo->lastInsertId(), 'texte' => $texte, 'quand' => iso_paris($moment)]];
}

function rappel_annuler(mixed $id): array
{
    if (is_string($id) && ctype_digit($id)) {
        $id = (int) $id;
    }
    if (!is_int($id) || $id < 1) {
        throw new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    }
    $pdo = db();
    $existe = $pdo->prepare('SELECT COUNT(*) FROM rappels WHERE id = ?');
    $existe->execute([$id]);
    if ((int) $existe->fetchColumn() === 0) {
        throw new ErreurApi('rappel_introuvable', 404, 'Rappel introuvable.');
    }
    $pdo->prepare('UPDATE rappels SET annule = 1 WHERE id = ?')->execute([$id]);
    return ['id' => $id];
}

// Pure. $rappels : lignes non annulées {id, texte, quand}. $envois : lignes {cle, type, prevu_pour, titre, statut}.
// Dates en DateTimeImmutable. Rend la journée de Paris de $maintenant, triée par heure.
function rappels_du_jour_construire(array $rappels, array $envois, DateTimeImmutable $maintenant): array
{
    $debut = $maintenant->setTimezone(paris())->setTime(0, 0);
    $fin = $debut->modify('+1 day');
    $dans_le_jour = fn(DateTimeImmutable $m): bool => $m >= $debut && $m < $fin;
    $elements = [];
    foreach ($rappels as $r) {
        if ($dans_le_jour($r['quand'])) {
            $elements[] = ['id' => (int) $r['id'], 'type' => 'manuel', 'moment' => $r['quand'], 'titre' => $r['texte'], 'annulable' => $r['quand'] >= $maintenant];
        }
    }
    foreach ($envois as $e) {
        if (in_array($e['type'], JR_RAPPEL_TYPES_JOUR, true) && in_array($e['statut'], ['prevu', 'envoye'], true) && $dans_le_jour($e['prevu_pour'])) {
            $elements[] = ['id' => $e['cle'], 'type' => $e['type'], 'moment' => $e['prevu_pour'], 'titre' => $e['titre'], 'annulable' => false];
        }
    }
    usort($elements, fn(array $a, array $b): int => $a['moment'] <=> $b['moment']);
    return array_map(fn(array $e): array => [
        'id' => $e['id'],
        'type' => $e['type'],
        'heure' => iso_paris($e['moment']),
        'titre' => $e['titre'],
        'passe' => $e['moment'] < $maintenant,
        'annulable' => $e['annulable'],
    ], $elements);
}

function rappels_du_jour_lire(): array
{
    $maintenant = maintenant();
    $debut = aujourdhui_paris();
    $bornes = [utc($debut), utc($debut->modify('+1 day'))];
    $pdo = db();
    $requete = $pdo->prepare('SELECT id, texte, quand FROM rappels WHERE annule = 0 AND quand >= ? AND quand < ?');
    $requete->execute($bornes);
    $rappels = array_map(fn(array $l): array => ['id' => $l['id'], 'texte' => $l['texte'], 'quand' => depuis_utc($l['quand'])], $requete->fetchAll());
    $requete = $pdo->prepare("SELECT cle, type, prevu_pour, titre, statut FROM envois WHERE type <> 'manuel' AND statut IN ('prevu', 'envoye') AND prevu_pour >= ? AND prevu_pour < ?");
    $requete->execute($bornes);
    $envois = array_map(fn(array $l): array => ['prevu_pour' => depuis_utc($l['prevu_pour'])] + $l, $requete->fetchAll());
    return rappels_du_jour_construire($rappels, $envois, $maintenant);
}
