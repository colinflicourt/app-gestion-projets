<?php
// Copies datées des sources (Trello, agenda), rafraîchies à la demande sous un verrou MySQL par source.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/http.php';

const JR_VERROU_SOURCE_ATTENTE = 10;

// Le verrou d'une source n'a pas été obtenu à temps (lectures lentes de Trello ou de Google en file)
final class VerrouIndisponible extends RuntimeException {}

// Code envoyé à l'iPhone quand une source ne peut être ni lue ni écrite
function code_indisponible(string $source): string
{
    return str_starts_with($source, 'agenda') ? 'google_indisponible' : 'trello_indisponible';
}

function copie_lire(string $source): ?array
{
    $requete = db()->prepare('SELECT contenu, recupere_le, empreinte FROM copies WHERE source = ?');
    $requete->execute([$source]);
    $ligne = $requete->fetch();
    if ($ligne === false) {
        return null;
    }
    return [
        'contenu' => json_decode($ligne['contenu'], true, 512, JSON_THROW_ON_ERROR),
        'recupere_le' => depuis_utc($ligne['recupere_le']),
        'empreinte' => $ligne['empreinte'],
    ];
}

function copie_ecrire(string $source, array $contenu, DateTimeImmutable $recupere_le): void
{
    $json = json_encode($contenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    db()->prepare('INSERT INTO copies (source, contenu, recupere_le, empreinte) VALUES (?, ?, ?, ?) AS n
        ON DUPLICATE KEY UPDATE contenu = n.contenu, recupere_le = n.recupere_le, empreinte = n.empreinte')
        ->execute([$source, $json, utc($recupere_le), hash('sha256', $json)]);
}

// Réentrant (GET_LOCK de MySQL 8) : une écriture qui tient le verrou peut corriger la copie. Les tests
// raccourcissent l'attente par $GLOBALS['JR_VERROU_ATTENTE'].
function verrou_source_prendre(string $source, ?int $attente = null): void
{
    $prise = db()->prepare('SELECT GET_LOCK(?, ?)');
    $prise->execute(['jr_source_' . $source, $attente ?? $GLOBALS['JR_VERROU_ATTENTE'] ?? JR_VERROU_SOURCE_ATTENTE]);
    if ((int) $prise->fetchColumn() !== 1) {
        throw new VerrouIndisponible('Verrou de la source indisponible');
    }
}

function verrou_source_rendre(string $source): void
{
    db()->prepare('SELECT RELEASE_LOCK(?)')->execute(['jr_source_' . $source]);
}

// Corrige la copie après une écriture de l'app. Sous le verrou : un rafraîchissement commencé avant
// l'écriture finit avant cette correction, et ne peut donc pas l'écraser. recupere_le ne change pas.
function copie_modifier(string $source, callable $modifier): void
{
    verrou_source_prendre($source);
    try {
        $copie = copie_lire($source);
        if ($copie !== null) {
            copie_ecrire($source, $modifier($copie['contenu']), $copie['recupere_le']);
        }
    } finally {
        verrou_source_rendre($source);
    }
}

// Rend la copie de $source, relue par $recuperer() si elle a $age_max secondes ou plus, ou si
// $encore_valide la refuse. $apres($avant, $nouveau, $depuis) passe avant l'écriture de la nouvelle copie
// (détection des actions). Source en panne : la dernière copie revient avec le code d'erreur.
function rafraichir(string $source, int $age_max, callable $recuperer, ?callable $apres = null, ?callable $encore_valide = null): array
{
    $fraiche = fn(?array $copie): bool => $copie !== null
        && maintenant()->getTimestamp() - $copie['recupere_le']->getTimestamp() < $age_max
        && ($encore_valide === null || $encore_valide($copie['contenu']));
    $copie = copie_lire($source);
    if ($fraiche($copie)) {
        return resultat_copie($copie, null);
    }
    try {
        verrou_source_prendre($source);
    } catch (VerrouIndisponible $e) {
        // Lectures lentes en file : la dernière copie plutôt qu'une 500
        journal_erreur("rafraichir/$source", $e);
        return resultat_copie($copie, code_indisponible($source));
    }
    try {
        // Un autre appel a peut-être rafraîchi pendant l'attente du verrou
        $copie = copie_lire($source);
        if ($fraiche($copie)) {
            return resultat_copie($copie, null);
        }
        // L'heure du début : une écriture de l'app faite pendant la lecture reste « depuis la copie précédente »
        $debut = maintenant();
        try {
            $nouveau = $recuperer();
        } catch (ErreurSource $e) {
            journal_erreur("rafraichir/$source", $e);
            return resultat_copie($copie, $e->codeErreur);
        }
        if ($apres !== null) {
            $apres($copie['contenu'] ?? null, $nouveau, $copie['recupere_le'] ?? null);
        }
        copie_ecrire($source, $nouveau, $debut);
        return ['contenu' => $nouveau, 'recupere_le' => $debut, 'erreur' => null];
    } finally {
        verrou_source_rendre($source);
    }
}

function resultat_copie(?array $copie, ?string $erreur): array
{
    return ['contenu' => $copie['contenu'] ?? null, 'recupere_le' => $copie['recupere_le'] ?? null, 'erreur' => $erreur];
}
