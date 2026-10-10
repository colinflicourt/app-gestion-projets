<?php
// Réglages de l'app (table reglages, valeurs JSON) : identifiants des agendas, préférences.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';

function reglage_lire(string $cle): mixed
{
    $requete = db()->prepare('SELECT valeur FROM reglages WHERE cle = ?');
    $requete->execute([$cle]);
    $valeur = $requete->fetchColumn();
    return $valeur === false ? null : json_decode((string) $valeur, true, 512, JSON_THROW_ON_ERROR);
}

function reglage_ecrire(string $cle, mixed $valeur): void
{
    db()->prepare('INSERT INTO reglages (cle, valeur, maj_le) VALUES (?, ?, ?) AS n
        ON DUPLICATE KEY UPDATE valeur = n.valeur, maj_le = n.maj_le')
        ->execute([$cle, json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), utc(maintenant())]);
}
