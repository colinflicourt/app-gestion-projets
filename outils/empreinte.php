<?php
// Lit un mot de passe sur l'entrée standard et écrit son empreinte bcrypt, rien d'autre.

declare(strict_types=1);

$mot_de_passe = stream_get_contents(STDIN);
if ($mot_de_passe === false || $mot_de_passe === '') {
    fwrite(STDERR, "Mot de passe vide\n");
    exit(1);
}
echo password_hash($mot_de_passe, PASSWORD_DEFAULT);
