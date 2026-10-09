<?php
// Journal des actions réelles de Colin (étape cochée, carte terminée…), tenu dès le lot 1.
// L'XP du lot 4 en sera recalculée : elle n'est jamais stockée.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';

const JR_TYPES_ACTIONS = ['etape_cochee', 'etape_decochee', 'carte_terminee', 'carte_rouverte', 'carte_ajoutee', 'tirage_parti'];

function action_noter(string $type, string $domaine, string $carte_id, ?string $element_id, DateTimeImmutable $quand, string $source, string $precision, array $donnees = []): int
{
    if (!in_array($type, JR_TYPES_ACTIONS, true) || !in_array($domaine, JR_DOMAINES, true)
        || !in_array($source, ['app', 'trello'], true) || !in_array($precision, ['exacte', 'approx'], true)) {
        throw new InvalidArgumentException("Action invalide : $type");
    }
    db()->prepare('INSERT INTO actions (type, domaine, carte_id, element_id, quand, source, precision_heure, donnees) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $type, $domaine, $carte_id, $element_id, utc($quand), $source, $precision,
            $donnees === [] ? null : json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    return (int) db()->lastInsertId();
}

function action_annuler(int $id): void
{
    db()->prepare('UPDATE actions SET annulee = 1 WHERE id = ?')->execute([$id]);
}

// Pour la détection des actions faites ailleurs : la même action a-t-elle déjà été notée par l'app ?
function action_app_existe_depuis(string $type, string $carte_id, ?string $element_id, DateTimeImmutable $depuis): bool
{
    $requete = db()->prepare("SELECT 1 FROM actions WHERE type = ? AND carte_id = ? AND element_id <=> ?
        AND source = 'app' AND annulee = 0 AND quand >= ? LIMIT 1");
    $requete->execute([$type, $carte_id, $element_id, utc($depuis)]);
    return $requete->fetchColumn() !== false;
}
