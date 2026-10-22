<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ecritures.php';
require_once __DIR__ . '/../lib/rappels_manuels.php';

return [
    'POST rappels' => route_ecriture('rappels', fn(array $c): array => rappel_creer($c['texte'] ?? null, $c['quand'] ?? null, $c['id_client'])),
    'POST rappels/annuler' => route_ecriture('rappels/annuler', fn(array $c): array => rappel_annuler($c['id'] ?? null)),
];
