<?php
// Lire un mail en entier : ouverture (et marquage lu), page du corps, pièces jointes, lien Répondre (SPEC § 17.5 à 17.8).
// Rien du contenu d'un mail n'est gardé ni journalisé : seul non_lu change dans la copie.

declare(strict_types=1);

require_once __DIR__ . '/mail_contenu.php';
require_once __DIR__ . '/mail_entetes.php';
require_once __DIR__ . '/mails.php';
require_once __DIR__ . '/nettoyage_html.php';

const JR_MAIL_PIECE_LIMITE_IMAP = 110;
const JR_MAIL_PIECE_DUREE_MAX = 120;

function mail_objet_reponse(string $objet): string
{
    return preg_match('/^\s*re:/i', $objet) === 1 ? $objet : 'Re: ' . $objet;
}

// Lien qui ouvre l'app de la boîte en rédaction ; repondre_repli : page web à ouvrir si l'app ne s'ouvre pas (Gmail seulement)
function mail_repondre(string $compte, string $adresse, string $objet): array
{
    $a = rawurlencode($adresse);
    $sujet = rawurlencode(mail_objet_reponse($objet));
    return match ($compte) {
        'gmail' => [
            'repondre_url' => "googlegmail://co?to=$a&subject=$sujet",
            'repondre_repli' => "https://mail.google.com/mail/?view=cm&to=$a&su=$sujet",
        ],
        'utc' => [
            'repondre_url' => "https://webmail.utc.fr/?_task=mail&_action=compose&_to=$a&_subject=$sujet",
            'repondre_repli' => null,
        ],
        default => ['repondre_url' => "mailto:$a?subject=$sujet", 'repondre_repli' => null],
    };
}

function mail_de_la_copie(string $compte, int $uid): ?array
{
    $copie = copie_lire('mail:' . $compte);
    foreach ($copie['contenu']['mails'] ?? [] as $mail) {
        if (($mail['uid'] ?? null) === $uid) {
            return ['mail' => $mail, 'uidvalidity' => (int) ($copie['contenu']['uidvalidity'] ?? 0)];
        }
    }
    return null;
}

// Copie et compte vérifiés avant tout appel IMAP
function mail_acces_ou_erreur(string $compte, int $uid): array
{
    $dans_la_copie = mail_de_la_copie($compte, $uid);
    if ($dans_la_copie === null) {
        throw new ErreurApi('hors_copie', 404, 'Ce mail est trop ancien pour Junior.');
    }
    $acces = compte_mail_acces($compte);
    if ($acces === null) {
        throw new ErreurApi('non_connecte', 400, 'Pas encore connecté');
    }
    return $dans_la_copie + ['acces' => $acces];
}

// Paramètres d'adresse de mails/corps et mails/piece : 400 requete_invalide au moindre doute
function mail_parametres_get(array $requete, bool $avec_partie): array
{
    $p = $requete['requete'];
    $invalide = new ErreurApi('requete_invalide', 400, 'Requête illisible.');
    $compte = $p['compte'] ?? null;
    $uid = $p['uid'] ?? null;
    if (!is_string($compte) || !in_array($compte, JR_MAILS_COMPTES, true) || !is_string($uid) || preg_match('/^\d{1,10}$/D', $uid) !== 1 || (int) $uid < 1) {
        throw $invalide;
    }
    if ($avec_partie) {
        $partie = $p['partie'] ?? null;
        if (!is_string($partie) || preg_match('/^\d{1,3}(\.\d{1,3}){0,9}$/D', $partie) !== 1) {
            throw $invalide;
        }
        return [$compte, (int) $uid, $partie];
    }
    $images = $p['images'] ?? null;
    if ($images !== null && $images !== '1') {
        throw $invalide;
    }
    return [$compte, (int) $uid, $images === '1'];
}

function mail_ouvrir(string $compte, int $uid): array
{
    $c = mail_acces_ou_erreur($compte, $uid);
    try {
        $mail = ($GLOBALS['JR_IMAP_OUVRIR'] ?? 'imap_ouvrir_mail')($compte, $c['acces']['identifiant'], $c['acces']['mot_de_passe'], microtime(true) + JR_MAILS_LIMITE_ROUTE, $uid, $c['uidvalidity']);
    } catch (ErreurSource $e) {
        journal_erreur("mails/ouvrir/$compte", $e);
        if ($e->codeErreur === 'absent') {
            throw new ErreurApi('absent', 404, "Ce mail n'est plus dans la boîte.");
        }
        $code = isset(JR_MAILS_MESSAGES[$e->codeErreur]) ? $e->codeErreur : 'injoignable';
        throw new ErreurApi($code, 400, JR_MAILS_MESSAGES[$code]);
    }
    $marque_lu = (bool) $mail['marque_lu'];
    if ($marque_lu) {
        copie_modifier('mail:' . $compte, function (array $contenu) use ($uid): array {
            foreach ($contenu['mails'] as $i => $m) {
                if (($m['uid'] ?? null) === $uid) {
                    $contenu['mails'][$i]['non_lu'] = false;
                }
            }
            return $contenu;
        });
    }
    $destinataire = $mail['repondre_a'] ?? null;
    $adresse = is_array($destinataire) && ($destinataire['adresse'] ?? '') !== '' ? (string) $destinataire['adresse'] : (string) $mail['de_adresse'];
    return [
        'objet' => $mail['objet'],
        'de_nom' => $mail['de_nom'],
        'de_adresse' => $mail['de_adresse'],
        'a' => $mail['a'],
        'cc' => $mail['cc'],
        'date' => $mail['date'],
        'pour_contact' => (bool) ($c['mail']['pour_contact'] ?? false),
        'pieces' => array_map(
            fn(array $p): array => ['partie' => $p['partie'], 'nom' => $p['nom'], 'type' => $p['type'], 'taille' => $p['taille'], 'trop_lourde' => $p['trop_lourde']],
            mail_pieces($mail['structure'])
        ),
    ] + mail_repondre($compte, $adresse, (string) $mail['objet']) + ['marque_lu' => $marque_lu];
}

function mail_page_corps(string $compte, int $uid, bool $images): array
{
    $c = mail_acces_ou_erreur($compte, $uid);
    $lu = ($GLOBALS['JR_IMAP_CORPS'] ?? 'imap_lire_corps')($compte, $c['acces']['identifiant'], $c['acces']['mot_de_passe'], microtime(true) + JR_MAILS_LIMITE_ROUTE, $uid, $c['uidvalidity']);
    $bandeau = ($lu['coupe'] ?? false) ? 'Mail coupé : la suite est dans ' . JR_MAILS_NOMS_BOITES[$compte] . '.' : null;
    $html = [];
    $texte = [];
    foreach ($lu['parties'] as $partie) {
        if ($partie['sous_type'] === 'html') {
            $html[] = $partie['texte'];
        } else {
            $texte[] = $partie['texte'];
        }
    }
    if ($html !== []) {
        $cid = [];
        foreach ($lu['images_cid'] ?? [] as $cle => $url) {
            $cid[(string) $cle] = $url;
        }
        $nettoye = html_nettoyer(implode("\n", $html), $cid, $images);
        foreach ($texte as $t) {
            if (trim($t) !== '') {
                $nettoye['corps'] .= '<pre>' . html_echapper($t) . '</pre>';
            }
        }
        $page = page_corps_html($nettoye, $bandeau);
    } else {
        $page = page_corps_texte(implode("\n", $texte), $bandeau);
    }
    return reponse_brute(200, entetes_corps_mail($images), $page);
}

function mail_flux_piece(string $compte, int $uid, string $partie): array
{
    $c = mail_acces_ou_erreur($compte, $uid);
    $producteur = function (callable $commencer, callable $ecrire) use ($compte, $uid, $partie, $c): void {
        set_time_limit(JR_MAIL_PIECE_DUREE_MAX);
        $avant = function (array $piece) use ($commencer): void {
            $commencer(200, entetes_piece(piece_type_servi((string) $piece['type']), (string) $piece['nom']));
        };
        ($GLOBALS['JR_IMAP_PIECE'] ?? 'imap_lire_piece')($compte, $c['acces']['identifiant'], $c['acces']['mot_de_passe'], microtime(true) + JR_MAIL_PIECE_LIMITE_IMAP, $uid, $c['uidvalidity'], $partie, $avant, $ecrire);
    };
    return reponse_flux($producteur, entetes_piece_erreur());
}
