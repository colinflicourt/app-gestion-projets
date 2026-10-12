<?php
// Secours du planificateur : le cron d'OVH (une fois par heure au plus) lance ce script en ligne de commande.
// À régler par Colin dans l'espace client au plan 3, si l'offre le permet : jr.qyris.fr/serveur/cron-ovh.php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/lib/planificateur.php';

set_error_handler('erreur_php');
try {
    assurer_migrations();
    planificateur_passage();
} catch (Throwable $e) {
    journal_erreur('cron-ovh', $e);
    exit(1);
}
