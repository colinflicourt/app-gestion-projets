<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ecritures.php';
require_once __DIR__ . '/../lib/taches.php';

return [
    'POST taches/cocher' => route_ecriture('taches/cocher', fn(array $c): array => tache_cocher($c['carte'] ?? null, $c['element'] ?? null, $c['coche'] ?? null)),
    'POST tirage/parti' => route_ecriture('tirage/parti', fn(array $c): array => tache_tirage_parti($c['carte'] ?? null, $c['element'] ?? null)),
];
