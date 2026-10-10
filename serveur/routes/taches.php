<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ecritures.php';
require_once __DIR__ . '/../lib/taches.php';

return [
    'POST taches/cocher' => route_ecriture('taches/cocher', fn(array $c): array => tache_cocher($c['carte'] ?? null, $c['element'] ?? null, $c['coche'] ?? null)),
    'POST taches/terminer' => route_ecriture('taches/terminer', fn(array $c): array => tache_terminer($c['carte'] ?? null)),
    'POST taches/annuler' => route_ecriture('taches/annuler', fn(array $c): array => tache_annuler($c['id_client_origine'] ?? null)),
    'POST taches/deplacer' => route_ecriture('taches/deplacer', fn(array $c): array => tache_deplacer($c['carte'] ?? null, $c['colonne'] ?? null)),
    'POST taches/ajouter' => route_ecriture('taches/ajouter', fn(array $c): array => tache_ajouter($c['domaine'] ?? null, $c['titre'] ?? null, $c['urgent'] ?? null, $c['echeance'] ?? null)),
    'POST tirage/parti' => route_ecriture('tirage/parti', fn(array $c): array => tache_tirage_parti($c['carte'] ?? null, $c['element'] ?? null)),
];
