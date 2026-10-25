<?php
// Tri des mails (fonctions pures) : section UTC, pubs, sections de l'écran. Rien ne touche la base ici.

declare(strict_types=1);

const JR_MAILS_COMPTES = ['utc', 'gmail', 'icloud', 'colin'];
const JR_MAILS_SECTIONS = ['utc', 'qyris', 'gmail', 'icloud'];
const JR_MAILS_AFFICHES_MIN = 20;
const JR_MAILS_PUBS_MAX = 20;

// Valeurs non vides d'un en-tête (noms en minuscules dans le brut)
function mail_valeurs_entete(array $entetes, string $nom): array
{
    $valeurs = [];
    foreach ((array) ($entetes[$nom] ?? []) as $valeur) {
        if (is_string($valeur) && trim($valeur) !== '') {
            $valeurs[] = $valeur;
        }
    }
    return $valeurs;
}

// Un mail écrit à contact@qyris.fr, simple redirection vers colin@ : il se repère à ses destinataires
function mail_pour_contact(array $entetes): bool
{
    foreach (['to', 'cc', 'delivered-to', 'x-forwarded-to'] as $nom) {
        foreach (mail_valeurs_entete($entetes, $nom) as $valeur) {
            if (preg_match('/(?<![\w.+-])contact@qyris\.fr(?![\w-]|\.\w)/i', $valeur)) {
                return true;
            }
        }
    }
    return false;
}

function mail_signes_pub(array $entetes): array
{
    $signes = [];
    if (mail_valeurs_entete($entetes, 'list-unsubscribe') !== []) {
        $signes[] = 'list-unsubscribe';
    }
    foreach (mail_valeurs_entete($entetes, 'precedence') as $valeur) {
        if (in_array(strtolower(trim($valeur)), ['bulk', 'list'], true)) {
            $signes[] = 'precedence';
            break;
        }
    }
    if (mail_valeurs_entete($entetes, 'list-id') !== []) {
        $signes[] = 'list-id';
    }
    return $signes;
}

// X-GM-MSGID est un entier de 64 bits non signé en décimal : conversion en hexadécimal sans dépasser un entier PHP
function mail_decimal_en_hexa(string $decimal): ?string
{
    if (!preg_match('/^[0-9]+$/', $decimal)) {
        return null;
    }
    $chiffres = array_map('intval', str_split(ltrim($decimal, '0') === '' ? '0' : ltrim($decimal, '0')));
    $hexa = '';
    while ($chiffres !== [0] && $chiffres !== []) {
        $reste = 0;
        $quotient = [];
        foreach ($chiffres as $chiffre) {
            $courant = $reste * 10 + $chiffre;
            $quotient[] = intdiv($courant, 16);
            $reste = $courant % 16;
        }
        $hexa = dechex($reste) . $hexa;
        while (count($quotient) > 1 && $quotient[0] === 0) {
            array_shift($quotient);
        }
        $chiffres = $quotient;
    }
    return $hexa === '' ? '0' : $hexa;
}

function mail_pour_copie(array $brut, string $compte): array
{
    $entetes = is_array($brut['entetes'] ?? null) ? $brut['entetes'] : [];
    $gmail = $compte === 'gmail';
    $lien = null;
    if ($gmail && isset($brut['gm_msgid']) && $brut['gm_msgid'] !== '') {
        $hexa = mail_decimal_en_hexa((string) $brut['gm_msgid']);
        $lien = $hexa === null ? null : 'https://mail.google.com/mail/u/0/#all/' . $hexa;
    }
    return [
        'uid' => (int) $brut['uid'],
        'message_id' => (string) ($brut['message_id'] ?? ''),
        'date' => (string) $brut['date'],
        'de_nom' => (string) ($brut['de_nom'] ?? ''),
        'de_adresse' => (string) ($brut['de_adresse'] ?? ''),
        'objet' => (string) ($brut['objet'] ?? ''),
        'extrait' => (string) ($brut['extrait'] ?? ''),
        'non_lu' => (bool) ($brut['non_lu'] ?? false),
        'signes_pub' => mail_signes_pub($entetes),
        'lien' => $lien,
        'pour_contact' => $compte === 'colin' && mail_pour_contact($entetes),
    ];
}

// Dans l'ordre : UTC (compte utc) jamais pub, puis la correction de Colin, puis les signes
function mail_est_pub(array $mail, array $corrections): bool
{
    if (($mail['compte'] ?? null) === 'utc') {
        return false;
    }
    $adresse = strtolower(preg_replace('/\s+/', '', (string) ($mail['de_adresse'] ?? '')));
    $choix = $corrections[$adresse] ?? null;
    if ($choix === 'pub') {
        return true;
    }
    if ($choix === 'afficher') {
        return false;
    }
    return ($mail['signes_pub'] ?? []) !== [];
}

function mail_plus_recent_d_abord(array $a, array $b): int
{
    return [$b['date'], $b['uid']] <=> [$a['date'], $a['uid']];
}

function mails_section(string $nom, array $mails, array $corrections): array
{
    $hors_pubs = [];
    $pubs = [];
    foreach ($mails as $mail) {
        if (mail_est_pub($mail, $corrections)) {
            $pubs[] = $mail;
        } else {
            $hors_pubs[] = $mail;
        }
    }
    usort($hors_pubs, 'mail_plus_recent_d_abord');
    usort($pubs, 'mail_plus_recent_d_abord');

    $non_lus = count(array_filter($hors_pubs, fn(array $m) => $m['non_lu']));
    $places_lus = max(0, JR_MAILS_AFFICHES_MIN - $non_lus);
    $affiches = [];
    foreach ($hors_pubs as $mail) {
        if ($mail['non_lu']) {
            $affiches[] = $mail;
        } elseif ($places_lus > 0) {
            $affiches[] = $mail;
            $places_lus--;
        }
    }
    return [
        'section' => $nom,
        'non_lus' => $non_lus,
        'mails' => $affiches,
        'pubs' => array_slice($pubs, 0, JR_MAILS_PUBS_MAX),
        'pubs_total' => count($pubs),
    ];
}

// $copies : compte => contenu de copie {uidvalidity, mails} ou null. Un message_id vide ne fusionne jamais.
function mails_sections(array $copies, array $corrections): array
{
    $par_section = ['utc' => [], 'qyris' => [], 'gmail' => [], 'icloud' => []];
    foreach (JR_MAILS_COMPTES as $compte) {
        foreach ((array) ($copies[$compte]['mails'] ?? []) as $mail) {
            $mail['compte'] = $compte;
            $section = match ($compte) {
                'utc' => 'utc',
                'gmail' => 'gmail',
                'icloud' => 'icloud',
                default => 'qyris',
            };
            if ($section === 'qyris' && $mail['message_id'] !== '') {
                $cle = 'm:' . $mail['message_id'];
                $deja = $par_section['qyris'][$cle] ?? null;
                if ($deja !== null) {
                    $par_section['qyris'][$cle]['non_lu'] = $deja['non_lu'] || $mail['non_lu'];
                    continue;
                }
                $par_section['qyris'][$cle] = $mail;
                continue;
            }
            $par_section[$section][] = $mail;
        }
    }
    return array_map(fn(string $nom) => mails_section($nom, array_values($par_section[$nom]), $corrections), JR_MAILS_SECTIONS);
}

function mails_resume(array $sections): array
{
    $par_section = array_fill_keys(JR_MAILS_SECTIONS, 0);
    foreach ($sections as $section) {
        $par_section[$section['section']] = $section['non_lus'];
    }
    return ['total' => array_sum($par_section), 'par_section' => $par_section];
}
