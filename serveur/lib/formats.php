<?php
// Dates et heures écrites comme Junior les dit, à l'heure de Paris : « 8 h 30 », « Mercredi 14 octobre ».

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/creneaux.php';

const JR_MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

// 8 h, 8 h 30, 19 h 15
function heure_lisible(DateTimeImmutable $moment): string
{
    $p = $moment->setTimezone(paris());
    return $p->format('G') . ' h' . ($p->format('i') === '00' ? '' : ' ' . $p->format('i'));
}

function jour_nom(DateTimeImmutable $moment): string
{
    return ucfirst(JR_JOURS_SEMAINE[(int) $moment->setTimezone(paris())->format('N') - 1]);
}

// Mercredi 14 octobre, Dimanche 1er novembre
function date_longue(DateTimeImmutable $moment): string
{
    $p = $moment->setTimezone(paris());
    $jour = (int) $p->format('j');
    return jour_nom($p) . ' ' . ($jour === 1 ? '1er' : (string) $jour) . ' ' . JR_MOIS[(int) $p->format('n') - 1];
}

function pluriel(int $nombre, string $mot): string
{
    return $nombre . ' ' . $mot . ($nombre > 1 ? 's' : '');
}

// $max caractères au plus, points de suspension compris
function couper(string $texte, int $max): string
{
    return mb_strlen($texte) <= $max ? $texte : rtrim(mb_substr($texte, 0, $max - 1)) . '…';
}

// Jours de calendrier entre deux dates AAAA-MM-JJ, sans souci de changement d'heure
function jours_entre(string $de, string $a): int
{
    $utc = new DateTimeZone('UTC');
    return intdiv((new DateTimeImmutable($a, $utc))->getTimestamp() - (new DateTimeImmutable($de, $utc))->getTimestamp(), 86400);
}
