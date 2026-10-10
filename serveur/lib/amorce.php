<?php
// Amorce commune : configuration, heure, journal d'erreurs. Chargée par toute la partie serveur.

declare(strict_types=1);

const JR_VERSION = '0.1.0';
const JR_DOMAINES = ['qyris', 'asso', 'etudes', 'perso'];
const JR_JOURNAL_MAX = 1048576;
const JR_FUSEAU = 'Europe/Paris';

date_default_timezone_set('UTC');

// La config vient de serveur/config.php (généré, hors git). Les tests la posent dans $GLOBALS['JR_CONFIG'].
// Le serveur d'aperçu des tests d'intégration peut désigner un autre fichier par JR_CONFIG_FICHIER.
function config(?string $cle = null): mixed
{
    if (!isset($GLOBALS['JR_CONFIG'])) {
        $fichier = __DIR__ . '/../config.php';
        if (PHP_SAPI === 'cli-server' && getenv('JR_CONFIG_FICHIER')) {
            $fichier = getenv('JR_CONFIG_FICHIER');
        }
        $GLOBALS['JR_CONFIG'] = require $fichier;
    }
    return $cle === null ? $GLOBALS['JR_CONFIG'] : ($GLOBALS['JR_CONFIG'][$cle] ?? null);
}

function maintenant(): DateTimeImmutable
{
    return $GLOBALS['JR_MAINTENANT'] ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

// Format des dates en base : UTC, 'AAAA-MM-JJ HH:MM:SS'.
function utc(DateTimeImmutable $moment): string
{
    return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function depuis_utc(string $texte): DateTimeImmutable
{
    $moment = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $texte, new DateTimeZone('UTC'));
    if ($moment === false) {
        throw new InvalidArgumentException('Date UTC illisible');
    }
    return $moment;
}

function paris(): DateTimeZone
{
    return new DateTimeZone(JR_FUSEAU);
}

// Heure de Paris avec son décalage, pour l'iPhone : 2026-10-14T08:00:00+02:00
function iso_paris(DateTimeImmutable $moment): string
{
    return $moment->setTimezone(paris())->format('Y-m-d\TH:i:sP');
}

// Minuit du jour courant, heure de Paris
function aujourdhui_paris(): DateTimeImmutable
{
    return maintenant()->setTimezone(paris())->setTime(0, 0);
}

// Pour comparer des titres sans casse, accents, apostrophe courbe ni espaces en trop
function normaliser_texte(string $texte): string
{
    $texte = strtr(mb_strtolower(trim($texte)), [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => "'",
    ]);
    return (string) preg_replace('/\s+/u', ' ', $texte);
}

function dossier_data(): string
{
    $dossier = $GLOBALS['JR_DOSSIER_DATA'] ?? __DIR__ . '/../data';
    if (!is_dir($dossier)) {
        mkdir($dossier, 0700, true);
    }
    return $dossier;
}

// Une ligne par erreur : heure, route, type, message technique. Jamais de contenu personnel :
// un message PDO peut citer des valeurs, on n'en garde que le code SQLSTATE.
// Le journal ne lève jamais : un dossier plein ou sans droits ne doit pas empêcher la réponse.
function journal_erreur(string $route, Throwable $e): void
{
    try {
        $fichier = dossier_data() . '/erreurs.log';
        if (is_file($fichier) && filesize($fichier) > JR_JOURNAL_MAX) {
            rename($fichier, $fichier . '.1');
        }
        $route = preg_replace('/[^a-z0-9\/._-]/i', '?', substr($route, 0, 60));
        $message = $e instanceof PDOException ? 'SQLSTATE ' . $e->getCode() : $e->getMessage();
        $message = str_replace(["\r", "\n"], ' ', mb_substr($message, 0, 300));
        $ligne = sprintf("%s | %s | %s | %s | %s:%d\n", utc(maintenant()), $route, get_class($e), $message, basename($e->getFile()), $e->getLine());
        file_put_contents($fichier, $ligne, FILE_APPEND | LOCK_EX);
    } catch (Throwable) {
    }
}

// Gestionnaire d'erreurs PHP de l'API : un avertissement devient une exception (réponse 500 et journal).
// Une dépréciation, ou une erreur masquée par @, laisse la requête aboutir : une mise à jour de PHP
// chez OVH ne doit pas casser l'app.
function erreur_php(int $niveau, string $message, string $fichier, int $ligne): bool
{
    if (!(error_reporting() & $niveau) || ($niveau & (E_DEPRECATED | E_USER_DEPRECATED))) {
        return false;
    }
    throw new ErrorException($message, 0, $niveau, $fichier, $ligne);
}
