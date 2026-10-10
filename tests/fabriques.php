<?php
// Données inventées pour les tests : identifiants, tableaux Trello, cartes, événements Google.

declare(strict_types=1);

// Identifiant de 24 caractères hexadécimaux, comme ceux de Trello : la sorte sur 2, le numéro sur 22
function id_test(string $sorte, int $n): string
{
    $sortes = ['tableau' => 'b0', 'colonne' => 'c0', 'etiquette' => 'e0', 'carte' => 'ca', 'checklist' => 'cc', 'element' => 'ee', 'autre' => 'ff'];
    return $sortes[$sorte] . str_pad(dechex($n), 22, '0', STR_PAD_LEFT);
}

function utc_test(string $texte): DateTimeImmutable
{
    return new DateTimeImmutable($texte, new DateTimeZone('UTC'));
}

// Même forme que .claude/scripts/trello-ids.json. Tableau n (1 à 4 dans l'ordre de JR_DOMAINES) :
// colonnes n1 à n4 (À faire, En cours, En attente, Fait), étiquettes n1 (Urgent) puis n2, n3…
function fabrique_trello_ids(): array
{
    $tableaux = [];
    $definitions = ['qyris' => ['QYRIS', ['Site']], 'asso' => ['Asso', ['SKIUT']], 'etudes' => ['Études', ['MT23', 'GE15']], 'perso' => ['Perso', ['CV']]];
    foreach ($definitions as $domaine => [$nom, $projets]) {
        $n = (int) array_search($domaine, JR_DOMAINES, true) + 1;
        $colonnes = [];
        foreach (['À faire', 'En cours', 'En attente', 'Fait'] as $i => $colonne) {
            $colonnes[$colonne] = id_test('colonne', $n * 10 + $i + 1);
        }
        $etiquettes = ['Urgent' => id_test('etiquette', $n * 10 + 1)];
        foreach ($projets as $i => $projet) {
            $etiquettes[$projet] = id_test('etiquette', $n * 10 + $i + 2);
        }
        $tableaux[$domaine] = ['id' => id_test('tableau', $n), 'url' => "https://trello.com/b/test$n", 'nom' => $nom, 'colonnes' => $colonnes, 'etiquettes' => $etiquettes];
    }
    return ['espace' => id_test('autre', 1), 'tableaux' => $tableaux];
}
