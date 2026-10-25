<?php
// En-têtes des réponses brutes de la lecture d'un mail : la page du corps et les pièces jointes (SPEC § 17.6 et § 17.7).
// Fonctions pures. Aucune valeur ne contient jamais de retour à la ligne ni d'octet nul.

declare(strict_types=1);

// Types servis tels quels ; tout le reste part en application/octet-stream (jamais de HTML, de SVG, de XML ni de JavaScript)
const JR_PIECE_TYPES_SERVIS = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/heic',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain',
    'text/csv',
    'text/calendar',
];

const JR_PIECE_NOM_PAR_DEFAUT = 'piece-jointe';

// La page du corps vit dans un cadre de l'écran : ces en-têtes remplacent ceux de l'app (frame-ancestors 'none', style-src 'self')
function entetes_corps_mail(bool $images): array
{
    $images_permises = $images ? "'self' data: https:" : "'self' data:";
    return [
        'Content-Type' => 'text/html; charset=utf-8',
        'Content-Security-Policy' => "default-src 'none'; img-src $images_permises; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox allow-same-origin allow-popups allow-popups-to-escape-sandbox",
        'Referrer-Policy' => 'no-referrer',
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
        'X-Robots-Tag' => 'noindex, nofollow',
    ];
}

// Type annoncé par le mail (avec ses paramètres, en majuscules ou non) → type réellement servi
function piece_type_servi(string $type): string
{
    $type = strtolower(trim(explode(';', $type, 2)[0]));
    return in_array($type, JR_PIECE_TYPES_SERVIS, true) ? $type : 'application/octet-stream';
}

function entetes_piece(string $type_servi, string $nom): array
{
    $type = piece_type_servi($type_servi);
    $en_ligne = $type === 'application/pdf' || str_starts_with($type, 'image/') || str_starts_with($type, 'text/');
    $nom = piece_nom_propre($nom);
    return [
        'Content-Type' => $type,
        'Content-Disposition' => ($en_ligne ? 'inline' : 'attachment') . '; filename="' . piece_nom_repli_ascii($nom) . "\"; filename*=UTF-8''" . rawurlencode($nom),
        'Content-Security-Policy' => "default-src 'none'; sandbox",
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'no-store',
        'Referrer-Policy' => 'no-referrer',
    ];
}

// Réponse d'erreur d'une pièce : un texte court, rien d'actif
function entetes_piece_erreur(): array
{
    return [
        'Content-Type' => 'text/plain; charset=utf-8',
        'Content-Security-Policy' => "default-src 'none'; sandbox",
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'no-store',
        'Referrer-Policy' => 'no-referrer',
    ];
}

// UTF-8 valide, sans caractère de contrôle ; vide → nom par défaut
function piece_nom_propre(string $nom): string
{
    $nom = (string) preg_replace('/[\x00-\x1f\x7f]/', '_', trim(mb_scrub($nom, 'UTF-8')));
    return $nom === '' ? JR_PIECE_NOM_PAR_DEFAUT : $nom;
}

// Pour les clients qui ne lisent pas filename* : lettres accentuées translittérées, le reste hors ASCII devient « _ »
function piece_nom_repli_ascii(string $nom): string
{
    $nom = strtr($nom, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ù' => 'u',
        'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ý' => 'y', 'ç' => 'c', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
        'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Å' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Î' => 'I', 'Ï' => 'I', 'Í' => 'I', 'Ì' => 'I', 'Ô' => 'O', 'Ö' => 'O', 'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ù' => 'U',
        'Û' => 'U', 'Ü' => 'U', 'Ú' => 'U', 'Ÿ' => 'Y', 'Ý' => 'Y', 'Ç' => 'C', 'Ñ' => 'N', 'Œ' => 'OE', 'Æ' => 'AE',
        '’' => "'", '‘' => "'", '“' => '_', '”' => '_',
    ]);
    $repli = (string) preg_replace('/[^\x20-\x7e]|["\\\\;\/]/u', '_', $nom);
    return $repli === '' ? JR_PIECE_NOM_PAR_DEFAUT : $repli;
}
