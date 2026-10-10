<?php
// Actions faites ailleurs (Jarvis, l'app Trello) : comparaison de deux copies de Trello (SPEC § 6.4).
// Fonction pure. Le filtre des doublons et la notation dans le journal sont dans donnees.php.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';

// $avant : la copie précédente (null pour la toute première : rien n'est détecté, ce qui était déjà dans
// Fait avant le lot 1 ne compte pas). Heure : la dernière activité de la carte, approximative.
function detecter_actions(?array $avant, array $apres): array
{
    if ($avant === null) {
        return [];
    }
    $anciennes = array_column($avant['cartes'], null, 'id');
    $actions = [];
    foreach ($apres['cartes'] as $carte) {
        $ancienne = $anciennes[$carte['id']] ?? null;
        $quand = new DateTimeImmutable($carte['derniere_activite']);
        $etait_faite = $ancienne !== null && $ancienne['colonne'] === 'fait';
        if ($carte['colonne'] === 'fait' && !$etait_faite) {
            $actions[] = action_detectee('carte_terminee', $carte, null, $quand, ['urgent' => $carte['urgent'], 'echeance' => $carte['echeance']]);
        } elseif ($carte['colonne'] !== 'fait' && $etait_faite) {
            $actions[] = action_detectee('carte_rouverte', $carte, null, $quand);
        }
        if ($ancienne === null) {
            continue;
        }
        $etats = [];
        foreach ($ancienne['checklists'] as $liste) {
            foreach ($liste['elements'] as $element) {
                $etats[$element['id']] = $element['coche'];
            }
        }
        foreach ($carte['checklists'] as $liste) {
            foreach ($liste['elements'] as $element) {
                $etait = $etats[$element['id']] ?? null;
                if ($etait !== null && $etait !== $element['coche']) {
                    $actions[] = action_detectee($element['coche'] ? 'etape_cochee' : 'etape_decochee', $carte, $element['id'], $quand);
                }
            }
        }
    }
    return $actions;
}

function action_detectee(string $type, array $carte, ?string $element_id, DateTimeImmutable $quand, array $donnees = []): array
{
    return ['type' => $type, 'domaine' => $carte['domaine'], 'carte_id' => $carte['id'], 'element_id' => $element_id, 'quand' => $quand, 'donnees' => $donnees];
}
