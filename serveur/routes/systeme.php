<?php
declare(strict_types=1);

return [
    'GET sante' => ['publique' => true, 'action' => function (array $requete, ?array $session): array {
        $version = version_base(db());
        return reponse_json(['version_app' => JR_VERSION, 'version_base' => $version, 'base_ok' => $version === version_attendue()]);
    }],
];
