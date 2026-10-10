<?php
// Essais sur les vraies sources, en dev, sans rien afficher de personnel : des nombres et des noms de champs.
//   php outils/essai-sources.php trello   forme réelle de /batch, puis cartes lues par domaine et colonne
// Config de dev requise (php outils/generer-config.php --mode=dev --dossier=serveur). Toujours à travers le filtre.

declare(strict_types=1);

require __DIR__ . '/../serveur/lib/trello.php';

function champs(mixed $valeur): string
{
    if (!is_array($valeur)) {
        return gettype($valeur);
    }
    $cles = array_map('strval', array_keys($valeur));
    sort($cles);
    return implode(', ', $cles);
}

$quoi = $argv[1] ?? '';
if ($quoi === 'trello') {
    $brut = trello_appel('GET', '/batch', ['urls' => implode(',', trello_adresses_batch())]);
    echo '/batch : ' . (is_array($brut) ? count($brut) . ' réponse(s)' : gettype($brut)) . "\n";
    foreach (JR_DOMAINES as $i => $domaine) {
        $element = $brut[$i] ?? null;
        $tableau = $element[200] ?? null;
        echo "  $domaine : clés [" . champs($element) . '] ; tableau [' . champs($tableau) . "]\n";
        if (!is_array($tableau)) {
            continue;
        }
        printf("    %d colonne(s), %d carte(s), %d checklist(s), %d étiquette(s)\n", count($tableau['lists'] ?? []), count($tableau['cards'] ?? []), count($tableau['checklists'] ?? []), count($tableau['labels'] ?? []));
        if (isset($tableau['cards'][0])) {
            echo '    carte [' . champs($tableau['cards'][0]) . "]\n";
        }
        if (isset($tableau['checklists'][0])) {
            echo '    checklist [' . champs($tableau['checklists'][0]) . '] ; élément [' . champs($tableau['checklists'][0]['checkItems'][0] ?? null) . "]\n";
        }
    }
    echo "Lecture normalisée :\n";
    $compte = [];
    foreach (trello_lire()['cartes'] as $carte) {
        $compte[$carte['domaine']][$carte['colonne']] = ($compte[$carte['domaine']][$carte['colonne']] ?? 0) + 1;
    }
    foreach ($compte as $domaine => $colonnes) {
        echo "  $domaine : " . implode(', ', array_map(fn($c, $n) => "$c $n", array_keys($colonnes), $colonnes)) . "\n";
    }
} else {
    fwrite(STDERR, "Usage : php outils/essai-sources.php trello\n");
    exit(1);
}
