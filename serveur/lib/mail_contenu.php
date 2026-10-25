<?php
// Contenu d'un mail (SPEC § 17.6 et § 17.7) : fonctions pures sur la BODYSTRUCTURE analysée, noms de pièces,
// décodage par morceaux. Aucun réseau : la lecture des parties est dans imap.php.

declare(strict_types=1);

require_once __DIR__ . '/imap_decodage.php';

const JR_MAIL_CORPS_MAX = 1048576;
const JR_MAIL_IMAGES_MAX = 5242880;
const JR_MAIL_PIECE_MAX = 20971520;
const JR_MAIL_NOM_MAX = 150;
const JR_MAIL_TYPES_IMAGE_CID = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
const JR_MAIL_EXTENSIONS = [
    'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
    'image/heic' => 'heic', 'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-powerpoint' => 'ppt', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    'text/plain' => 'txt', 'text/csv' => 'csv', 'text/calendar' => 'ics',
];

// --- Parcours de la BODYSTRUCTURE ------------------------------------------------------------------

function mail_est_multipart(array $noeud): bool
{
    return isset($noeud[0]) && is_array($noeud[0]);
}

function mail_chaine(mixed $valeur, string $defaut = ''): string
{
    return is_string($valeur) ? $valeur : $defaut;
}

// Sous-type d'un multipart : la première chaîne après les enfants
function mail_sous_type_multipart(array $noeud): string
{
    foreach ($noeud as $element) {
        if (is_string($element)) {
            return strtolower($element);
        }
    }
    return 'mixed';
}

// Content-Disposition d'une feuille : [type en majuscules, paramètres] ou null (son rang dépend du type de la feuille)
function mail_disposition(array $feuille): ?array
{
    $type = strtolower(mail_chaine($feuille[0] ?? null));
    $rang = $type === 'text' ? 9 : ($type === 'message' && strtolower(mail_chaine($feuille[1] ?? null)) === 'rfc822' ? 11 : 8);
    $disposition = $feuille[$rang] ?? null;
    if (!is_array($disposition) || !is_string($disposition[0] ?? null)) {
        return null;
    }
    return [strtoupper($disposition[0]), imap_parametres($disposition[1] ?? null)];
}

// Taille décodée estimée d'une feuille : base64 → 3/4 de la taille annoncée, sinon la taille annoncée
function mail_taille_decodee(array $feuille): int
{
    $taille = max(0, (int) mail_chaine($feuille[6] ?? null, '0'));
    return strtolower(mail_chaine($feuille[5] ?? null)) === 'base64' ? intdiv($taille * 3, 4) : $taille;
}

function mail_explorer(array $noeud, string $chemin, bool $dans_related): array
{
    if (mail_est_multipart($noeud)) {
        $sous_type = mail_sous_type_multipart($noeud);
        $enfants = [];
        foreach ($noeud as $i => $enfant) {
            if (!is_array($enfant)) {
                break;
            }
            $enfants[] = mail_explorer($enfant, ($chemin === '' ? '' : "$chemin.") . ($i + 1), $sous_type === 'related');
        }
        $resultat = ['corps' => [], 'pieces' => [], 'cid' => []];
        $choisi = null;
        foreach ($enfants as $enfant) {
            $resultat['pieces'] = array_merge($resultat['pieces'], $enfant['pieces']);
            $resultat['cid'] += $enfant['cid'];
            if ($sous_type !== 'alternative') {
                $resultat['corps'] = array_merge($resultat['corps'], $enfant['corps']);
            } elseif ($enfant['corps'] !== []) {
                $html = array_filter($enfant['corps'], fn(array $p): bool => $p['sous_type'] === 'html') !== [];
                $choisi_html = $choisi !== null && array_filter($choisi, fn(array $p): bool => $p['sous_type'] === 'html') !== [];
                if ($html || !$choisi_html) {
                    $choisi = $enfant['corps'];
                }
            }
        }
        if ($sous_type === 'alternative') {
            $resultat['corps'] = $choisi ?? [];
        }
        return $resultat;
    }

    $partie = $chemin === '' ? '1' : $chemin;
    $type = strtolower(mail_chaine($noeud[0] ?? null));
    $sous_type = strtolower(mail_chaine($noeud[1] ?? null));
    $disposition = mail_disposition($noeud);
    $resultat = ['corps' => [], 'pieces' => [], 'cid' => []];

    if ($type === 'text' && in_array($sous_type, ['html', 'plain'], true) && ($disposition[0] ?? '') !== 'ATTACHMENT') {
        $resultat['corps'][] = [
            'section' => $partie,
            'sous_type' => $sous_type,
            'encodage' => strtolower(mail_chaine($noeud[5] ?? null, '7bit')),
            'jeu' => imap_parametres($noeud[2] ?? null)['charset'] ?? 'utf-8',
            'taille' => max(0, (int) mail_chaine($noeud[6] ?? null, '0')),
        ];
        return $resultat;
    }

    $cid = strtolower(trim(trim(mail_chaine($noeud[3] ?? null)), '<>'));
    if ($dans_related && $type === 'image' && $cid !== '') {
        $resultat['cid'][$cid] = [
            'partie' => $partie,
            'type' => "$type/$sous_type",
            'encodage' => strtolower(mail_chaine($noeud[5] ?? null, '7bit')),
            'taille' => mail_taille_decodee($noeud),
        ];
        return $resultat;
    }

    $type_complet = "$type/$sous_type";
    $taille = mail_taille_decodee($noeud);
    $resultat['pieces'][] = [
        'partie' => $partie,
        'nom' => mail_nom_piece($noeud, $partie, $type_complet),
        'type' => $type_complet,
        'encodage' => strtolower(mail_chaine($noeud[5] ?? null, '7bit')),
        'taille' => $taille,
        'trop_lourde' => $taille > JR_MAIL_PIECE_MAX,
    ];
    return $resultat;
}

// Parties du corps à afficher, dans l'ordre : [{section, sous_type, encodage, jeu, taille}]
function mail_parties_corps(array $structure): array
{
    return $structure === [] ? [] : mail_explorer($structure, '', false)['corps'];
}

// Pièces jointes : [{partie, nom, type, encodage, taille (décodée estimée), trop_lourde}]
function mail_pieces(array $structure): array
{
    return $structure === [] ? [] : mail_explorer($structure, '', false)['pieces'];
}

// Images insérées d'un multipart/related : cid (minuscules, sans <>) → {partie, type, encodage, taille}
function mail_images_cid(array $structure): array
{
    return $structure === [] ? [] : mail_explorer($structure, '', false)['cid'];
}

// --- Nom d'une pièce -------------------------------------------------------------------------------

// Valeur d'un paramètre MIME (RFC 2231 : nom*, nom*0*, nom*0 ; sinon nom, avec mots RFC 2047 tolérés), en UTF-8, ou null
function mail_parametre(array $parametres, string $nom): ?string
{
    $suites = [];
    if (isset($parametres["$nom*"])) {
        $suites[0] = [true, $parametres["$nom*"]];
    } else {
        foreach ($parametres as $cle => $valeur) {
            if (preg_match('/\A' . preg_quote($nom, '/') . '\*(\d{1,3})(\*?)\z/', (string) $cle, $m)) {
                $suites[(int) $m[1]] = [$m[2] === '*', $valeur];
            }
        }
        ksort($suites);
    }
    if ($suites !== []) {
        $octets = '';
        $jeu = null;
        foreach ($suites as [$encode, $valeur]) {
            if ($encode) {
                if ($jeu === null && preg_match("/\\A([^']*)'[^']*'(.*)\\z/s", $valeur, $m)) {
                    $jeu = $m[1];
                    $valeur = $m[2];
                }
                $valeur = rawurldecode($valeur);
            }
            $jeu ??= '';
            $octets .= $valeur;
        }
        return imap_vers_utf8($octets, $jeu === '' ? 'utf-8' : $jeu);
    }
    if (!isset($parametres[$nom])) {
        return null;
    }
    $valeur = $parametres[$nom];
    return preg_match('/=\?[^?\s]+\?[BbQq]\?/', $valeur) ? imap_decoder_entete($valeur) : imap_vers_utf8($valeur);
}

// Retire séparateurs de chemin, contrôles et marques de sens d'écriture (remplacés par « _ »), puis coupe à 150 caractères en gardant l'extension
function mail_nom_propre(string $nom): string
{
    $nom = imap_utf8_sur($nom);
    $nom = preg_replace('/[\/\\\\\x00-\x1F\x{007F}-\x{009F}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '_', $nom) ?? '';
    $nom = trim($nom);
    if (preg_match('/\A[.\s]*\z/u', $nom)) {
        return '';
    }
    if (mb_strlen($nom) <= JR_MAIL_NOM_MAX) {
        return $nom;
    }
    $point = mb_strrpos($nom, '.');
    if ($point !== false && $point > 0) {
        $extension = mb_substr($nom, $point);
        if (mb_strlen($extension) <= 16) {
            return mb_substr($nom, 0, JR_MAIL_NOM_MAX - mb_strlen($extension)) . $extension;
        }
    }
    return mb_substr($nom, 0, JR_MAIL_NOM_MAX);
}

function mail_nom_piece(array $noeud, string $partie, string $type): string
{
    $type = strtolower(trim(explode(';', $type, 2)[0]));
    $candidats = [
        ['filename', mail_disposition($noeud)[1] ?? []],
        ['name', imap_parametres($noeud[2] ?? null)],
    ];
    foreach ($candidats as [$cle, $parametres]) {
        $nom = mail_parametre($parametres, $cle);
        if ($nom !== null && ($propre = mail_nom_propre($nom)) !== '') {
            return $propre;
        }
    }
    if ($type === 'message/rfc822') {
        return 'mail-joint.eml';
    }
    return "piece-jointe-$partie." . (JR_MAIL_EXTENSIONS[$type] ?? 'bin');
}

// --- Décodage par morceaux -------------------------------------------------------------------------

// Décode base64 ou quoted-printable morceau par morceau ; le résultat cumulé est celui d'un décodage d'un bloc
// (imap_decoder_corps). Ce qui ne peut pas encore être décodé est gardé pour le morceau suivant.
final class DecodeurMorceaux
{
    private string $encodage;
    private string $reste = '';

    public function __construct(string $encodage)
    {
        $this->encodage = strtolower(trim($encodage));
    }

    public function ajouter(string $octets): string
    {
        if ($this->encodage === 'base64') {
            $propre = preg_replace('/[^A-Za-z0-9+\/]/', '', $this->reste . $octets) ?? '';
            $utile = strlen($propre) - strlen($propre) % 4;
            $this->reste = substr($propre, $utile);
            return $utile > 0 ? (string) base64_decode(substr($propre, 0, $utile)) : '';
        }
        if ($this->encodage === 'quoted-printable') {
            $donnees = $this->reste . $octets;
            $this->reste = '';
            if (preg_match('/=+(?:[0-9A-Fa-f]|[ \t]{0,200}\r?)\z/', $donnees, $m, PREG_OFFSET_CAPTURE)) {
                $this->reste = substr($donnees, $m[0][1]);
                $donnees = substr($donnees, 0, $m[0][1]);
            }
            return quoted_printable_decode($donnees);
        }
        return $octets;
    }

    // Fin de la partie : décode ce qui restait
    public function finir(): string
    {
        $reste = $this->reste;
        $this->reste = '';
        if ($reste === '') {
            return '';
        }
        if ($this->encodage === 'base64') {
            return strlen($reste) % 4 === 1 ? '' : (string) base64_decode($reste);
        }
        if ($this->encodage === 'quoted-printable') {
            return quoted_printable_decode($reste);
        }
        return '';
    }
}

// --- Texte -------------------------------------------------------------------------------------------

// Octets décodés d'une partie texte → UTF-8 valide d'après son charset MIME (absent ou inconnu : repli de imap_vers_utf8).
// $coupe : la partie a été coupée, donc on retire un caractère UTF-8 incomplet en fin avant de convertir.
function mail_texte_utf8(string $octets, string $jeu, bool $coupe): string
{
    if ($coupe && in_array(strtolower(trim($jeu, " \t\"'")), ['', 'utf-8', 'utf8', 'us-ascii', 'ascii', 'ansi_x3.4-1968'], true)) {
        $octets = imap_couper_utf8_incomplet($octets);
    }
    return imap_nettoyer(imap_vers_utf8($octets, $jeu));
}
