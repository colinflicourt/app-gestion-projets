<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/reponse.php';
require_once __DIR__ . '/../lib/xp_donnees.php';

return [
    'GET xp/export' => ['publique' => false, 'action' => fn(array $requete, ?array $session): array => reponse_json(['actions' => xp_export()])],
];
