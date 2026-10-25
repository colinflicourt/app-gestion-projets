<?php
// XP du lot 4.1 côté base : les actions non annulées sont rejouées à chaque demande (SPEC § 18.4 et 18.5).
// Rien n'est stocké : ni colonne, ni table, ni copie.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/xp.php';

const JR_GESTE_JOURS = 7;
const JR_GESTE_AVANCE = 120;  // secondes d'avance tolérées sur l'heure du serveur

// Actions non annulées, au format d'entrée de xp_rejouer(), triées par quand puis id
function xp_actions(): array
{
    $actions = [];
    foreach (db()->query('SELECT id, type, domaine, carte_id, element_id, quand, donnees FROM actions WHERE annulee = 0 ORDER BY quand, id') as $ligne) {
        $actions[] = [
            'id' => (int) $ligne['id'],
            'type' => $ligne['type'],
            'domaine' => $ligne['domaine'],
            'carte_id' => $ligne['carte_id'],
            'element_id' => $ligne['element_id'],
            'quand' => depuis_utc($ligne['quand']),
            'donnees' => $ligne['donnees'] === null ? [] : json_decode($ligne['donnees'], true, 512, JSON_THROW_ON_ERROR),
        ];
    }
    return $actions;
}

function xp_etat(): array
{
    return xp_objet(xp_rejouer(xp_actions(), maintenant()));
}

// Ce que l'action $id a gagné ou retiré dans le rejeu (0 si elle n'y est pas : annulée ou inconnue)
function xp_gain_action(int $id): int
{
    return xp_rejouer(xp_actions(), maintenant())['gains'][$id] ?? 0;
}

// Heure d'un geste fait hors ligne : gardée si elle est lisible, des 7 derniers jours et au plus 2 minutes
// dans le futur (décision 7 du plan 4.1), sinon l'heure de réception
function heure_du_geste(mixed $fait_le): DateTimeImmutable
{
    $maintenant = maintenant();
    if (!is_string($fait_le) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $fait_le)) {
        return $maintenant;
    }
    try {
        $geste = new DateTimeImmutable($fait_le);
    } catch (Exception) {
        return $maintenant;
    }
    // 2026-02-30 serait « corrigé » en mars par PHP : refusé
    if (DateTimeImmutable::getLastErrors() !== false) {
        return $maintenant;
    }
    $ecart = $geste->getTimestamp() - $maintenant->getTimestamp();
    return $ecart > JR_GESTE_AVANCE || $ecart < -JR_GESTE_JOURS * 86400 ? $maintenant : $geste;
}

// Export pour les régulations suivantes (décision 11) : rien d'autre que ces champs, jamais de titre
function xp_export(): array
{
    $actions = [];
    foreach (db()->query('SELECT id, type, domaine, carte_id, element_id, quand, source, precision_heure, donnees FROM actions WHERE annulee = 0 ORDER BY quand, id') as $ligne) {
        $donnees = $ligne['donnees'] === null ? [] : json_decode($ligne['donnees'], true, 512, JSON_THROW_ON_ERROR);
        $actions[] = [
            'id' => (int) $ligne['id'],
            'type' => $ligne['type'],
            'domaine' => $ligne['domaine'],
            'carte_id' => $ligne['carte_id'],
            'element_id' => $ligne['element_id'],
            'quand' => iso_paris(depuis_utc($ligne['quand'])),
            'source' => $ligne['source'],
            'precision_heure' => $ligne['precision_heure'],
            'urgent' => $donnees['urgent'] ?? null,
            'echeance' => $donnees['echeance'] ?? null,
        ];
    }
    return $actions;
}
