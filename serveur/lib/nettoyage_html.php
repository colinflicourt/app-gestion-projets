<?php
// Corps d'un mail : nettoyage du HTML par liste blanche et pages à afficher dans le cadre de l'écran (fonctions pures,
// ni réseau, ni base, ni fichier). Le mail n'est jamais pris tel quel : tout ce qui sort est reconstruit balise par balise.

declare(strict_types=1);

const JR_HTML_BALISES = [
    'a', 'abbr', 'address', 'b', 'bdi', 'bdo', 'big', 'blockquote', 'br', 'caption', 'center', 'cite', 'code', 'col',
    'colgroup', 'dd', 'del', 'dfn', 'div', 'dl', 'dt', 'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img',
    'ins', 'kbd', 'li', 'ol', 'p', 'pre', 'q', 's', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody',
    'td', 'tfoot', 'th', 'thead', 'tr', 'tt', 'u', 'ul',
];
const JR_HTML_ATTRIBUTS = [
    'align', 'alt', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'class', 'color', 'colspan', 'dir', 'face', 'height',
    'id', 'lang', 'rowspan', 'size', 'span', 'style', 'title', 'valign', 'width',
];
// Balises retirées avec tout ce qu'elles contiennent (les autres balises inconnues ne perdent que leur balise)
const JR_HTML_BALISES_RETIREES = [
    'script', 'iframe', 'frame', 'frameset', 'noframes', 'object', 'applet', 'form', 'input', 'button', 'select',
    'textarea', 'option', 'link', 'meta', 'base', 'svg', 'math', 'template', 'style', 'noscript', 'title', 'head',
    'audio', 'video', 'canvas', 'noembed', 'xmp', 'plaintext',
];
const JR_HTML_BALISES_VIDES = ['br', 'hr', 'img', 'col'];
// Vides en HTML mais inconnues de libxml, qui y range ce qui suit : la balise part, pas la suite
const JR_HTML_BALISES_VIDES_RETIREES = ['embed', 'param', 'source', 'track', 'keygen'];
const JR_HTML_PROFONDEUR_MAX = 200;
const JR_CSS_DANGEREUX = '/expression\s*\(|(?<![\w-])behavior\s*:|-moz-binding|javascript\s*:|(?<![\w-])(?:-webkit-)?(?:image-set|cross-fade)\s*\(|(?<![\w-])(?:element|image|src)\s*\(/i';
const JR_HTML_DATA_IMAGE = '#^data:image/(?:png|jpeg|jpg|gif|webp|bmp);base64,[A-Za-z0-9+/=]*$#i';

// $images_cid : cid en minuscules, sans <> => URL data: ou null (image trop lourde : le cadre reste, sans image)
function html_nettoyer(string $html, array $images_cid, bool $images_distantes): array
{
    $etat = [
        'cid' => array_change_key_case($images_cid, CASE_LOWER),
        'distantes' => $images_distantes,
        'bloquees' => false,
        'styles' => [],
    ];
    $racine = html_charger($html);
    $corps = '';
    if ($racine !== null) {
        foreach ($racine->getElementsByTagName('head') as $tete) {
            html_styles_de($tete, $etat);
        }
        foreach ($racine->getElementsByTagName('body') as $contenu) {
            $corps = html_enfants($contenu, $etat, 0);
            break;
        }
    }
    return ['corps' => $corps, 'styles' => implode("\n", array_filter($etat['styles'], fn ($s) => $s !== '')), 'images_bloquees' => $etat['bloquees']];
}

// Le préfixe d'encodage évite que DOMDocument prenne l'UTF-8 pour du Latin-1 ; les meta charset du mail sont ignorés
function html_charger(string $html): ?DOMDocument
{
    $html = str_replace("\0", '', mb_scrub($html, 'UTF-8'));
    if (trim($html) === '') {
        return null;
    }
    // Les meta charset du mail feraient relire le texte en Windows-1252 ; un < qui n'ouvre pas de balise coupe le texte pour libxml
    $html = preg_replace(['/<meta\b[^>]*>/i', '/<(?![A-Za-z\/!?])/'], ['', '&lt;'], $html) ?? '';
    $doc = new DOMDocument();
    $precedent = libxml_use_internal_errors(true);
    $charge = $doc->loadHTML('<?xml encoding="utf-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($precedent);
    return $charge ? $doc : null;
}

// Les <style> de la tête, sans descendre dans ce qui est retiré
function html_styles_de(DOMNode $noeud, array &$etat): void
{
    foreach ($noeud->childNodes as $enfant) {
        if (!$enfant instanceof DOMElement) {
            continue;
        }
        $nom = strtolower($enfant->nodeName);
        if ($nom === 'style') {
            $etat['styles'][] = css_nettoyer($enfant->textContent, $etat);
        }
    }
}

function html_enfants(DOMNode $noeud, array &$etat, int $profondeur): string
{
    $sortie = '';
    foreach ($noeud->childNodes as $enfant) {
        if ($enfant instanceof DOMText) {
            $sortie .= htmlspecialchars($enfant->data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            continue;
        }
        if (!$enfant instanceof DOMElement) {
            continue;
        }
        $nom = strtolower($enfant->nodeName);
        if ($nom === 'style') {
            $etat['styles'][] = css_nettoyer($enfant->textContent, $etat);
            continue;
        }
        if (in_array($nom, JR_HTML_BALISES_RETIREES, true)) {
            continue;
        }
        // Au-delà de la profondeur permise, on ne garde que le texte : plus de balise à ouvrir
        if ($profondeur >= JR_HTML_PROFONDEUR_MAX || in_array($nom, JR_HTML_BALISES_VIDES_RETIREES, true) || !in_array($nom, JR_HTML_BALISES, true)) {
            $sortie .= html_enfants($enfant, $etat, $profondeur + 1);
            continue;
        }
        $balise = '<' . $nom . html_attributs($enfant, $nom, $etat) . '>';
        if (in_array($nom, JR_HTML_BALISES_VIDES, true)) {
            $sortie .= $balise;
            continue;
        }
        $interieur = html_enfants($enfant, $etat, $profondeur + 1);
        // Le navigateur ignore le premier retour à la ligne d'un <pre> : un retour voulu en demande un de plus
        $sortie .= $balise . ($nom === 'pre' && str_starts_with($interieur, "\n") ? "\n" : '') . $interieur . '</' . $nom . '>';
    }
    return $sortie;
}

function html_attributs(DOMElement $element, string $nom, array &$etat): string
{
    $valeurs = [];
    foreach ($element->attributes as $attribut) {
        $cle = strtolower($attribut->nodeName);
        $valeur = $attribut->value;
        if ($cle === 'style') {
            $valeur = trim(css_nettoyer($valeur, $etat), "; \n\t");
            if ($valeur !== '') {
                $valeurs['style'] = $valeur;
            }
        } elseif (in_array($cle, JR_HTML_ATTRIBUTS, true) || (in_array($cle, ['start', 'type'], true) && in_array($nom, ['ol', 'ul'], true))) {
            $valeurs[$cle] = $valeur;
        } elseif ($cle === 'href' && $nom === 'a') {
            $lien = html_url_absolue($valeur, ['http', 'https', 'mailto', 'tel']);
            if ($lien !== null) {
                $valeurs['href'] = $lien;
            }
        } elseif ($cle === 'src' && $nom === 'img') {
            $source = html_src_image($valeur, $etat);
            if ($source !== null) {
                $valeurs['src'] = $source;
            }
        }
    }
    if ($nom === 'a') {
        $valeurs['target'] = '_blank';
        $valeurs['rel'] = 'noopener noreferrer';
    }
    // Une image sans source garde sa place (cadre vide) : la feuille de base fixe height: auto, donc la hauteur passe en ligne
    if ($nom === 'img' && !isset($valeurs['src'])) {
        $largeur = html_pixels($valeurs['width'] ?? '');
        $hauteur = html_pixels($valeurs['height'] ?? '');
        if ($largeur !== null && $hauteur !== null) {
            $en_ligne = "display:inline-block;width:{$largeur}px;height:{$hauteur}px";
            $valeurs['style'] = isset($valeurs['style']) ? $valeurs['style'] . ';' . $en_ligne : $en_ligne;
        }
    }
    $texte = '';
    foreach ($valeurs as $cle => $valeur) {
        $texte .= ' ' . $cle . '="' . htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }
    return $texte;
}

function html_pixels(string $valeur): ?int
{
    return preg_match('/^\s*(\d{1,4})(?:px)?\s*$/i', $valeur, $m) ? (int) $m[1] : null;
}

// URL telle que le navigateur la lit : caractères de contrôle ignorés (java&#x09;script:), espaces des bords retirés
function html_url_lue(string $url): string
{
    return trim(preg_replace('/[\x00-\x1f\x7f]+/', '', $url) ?? '', " \t\n\r\f");
}

// Seules les URL absolues d'un schéma permis sont gardées : pas de relatif, de //hôte, de javascript: ni de data:
function html_url_absolue(string $url, array $schemas): ?string
{
    $url = html_url_lue($url);
    if (!preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $m) || !in_array(strtolower($m[1]), $schemas, true)) {
        return null;
    }
    if (in_array(strtolower($m[1]), ['http', 'https'], true) && !preg_match('#^https?://[^/?\#\s]#i', $url)) {
        return null;
    }
    return $url;
}

function html_src_image(string $valeur, array &$etat): ?string
{
    $url = html_url_lue($valeur);
    if (preg_match('/^cid:(.*)$/is', $url, $m)) {
        $cid = strtolower(trim($m[1], " <>"));
        foreach ([$cid, strtolower(trim(rawurldecode($cid), " <>"))] as $cle) {
            if (isset($etat['cid'][$cle]) && is_string($etat['cid'][$cle]) && preg_match(JR_HTML_DATA_IMAGE, $etat['cid'][$cle])) {
                return $etat['cid'][$cle];
            }
        }
        return null;
    }
    if (preg_match(JR_HTML_DATA_IMAGE, $url)) {
        return $url;
    }
    $distante = html_url_absolue($url, ['http', 'https']);
    if ($distante === null) {
        return null;
    }
    if ($etat['distantes']) {
        return $distante;
    }
    $etat['bloquees'] = true;
    return null;
}

// CSS d'un <style> ou d'un attribut style : sans @import, expression(), behavior, -moz-binding, javascript:, et sans url() autre
// qu'une image data: (ou distante si permis). Les échappements sont défaits d'abord pour qu'ils ne cachent rien.
function css_nettoyer(string $css, array &$etat): string
{
    $css = preg_replace('/[\x00-\x08\x0b\x0e-\x1f\x7f]/', '', $css) ?? '';
    $css = preg_replace('~/\*.*?(?:\*/|$)~s', '', $css) ?? '';
    $css = str_replace(['<!--', '-->'], ' ', $css);
    $css = css_defaire_echappements($css);
    // Les polices distantes ne passent pas de toute façon (CSP) : on les retire sans compter une image bloquée
    $css = preg_replace('/@font-face\s*\{[^{}]*\}/i', '', $css) ?? '';
    $css = preg_replace('/@import\b[^;{}]*(?:;|(?=[{}])|$)/i', '', $css) ?? '';
    $css = css_sans_declarations_dangereuses($css);

    $gardees = [];
    $css = preg_replace_callback(
        '/url\s*\(\s*(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'|([^)]*))\s*\)/is',
        function (array $m) use (&$etat, &$gardees): string {
            $url = html_url_lue($m[1] !== '' ? $m[1] : (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '')));
            if (preg_match(JR_HTML_DATA_IMAGE, $url)) {
                $gardees[] = 'url("' . $url . '")';
                return "\x01" . (count($gardees) - 1) . "\x02";
            }
            $distante = html_url_absolue($url, ['http', 'https']);
            if ($distante === null) {
                return 'none';
            }
            if (!$etat['distantes']) {
                $etat['bloquees'] = true;
                return 'none';
            }
            $gardees[] = 'url("' . strtr($distante, ['"' => '%22', '\\' => '%5C', '<' => '%3C', '>' => '%3E', "\n" => '', "\r" => '', "\f" => '']) . '")';
            return "\x01" . (count($gardees) - 1) . "\x02";
        },
        $css
    ) ?? '';
    // Un url( jamais refermé reste valable jusqu'à la fin du texte pour un navigateur
    $css = preg_replace('/url\s*\(.*$/is', 'none', $css) ?? '';
    $css = preg_replace_callback('/\x01(\d+)\x02/', fn (array $m) => $gardees[(int) $m[1]], $css) ?? '';
    // Plus de < : jamais de fermeture de <style> ni de balise dans le CSS
    $css = str_replace('<', '\\3c ', $css);
    if (preg_match('/@import|expression\s*\(|(?<![\w-])behavior\s*:|-moz-binding|javascript\s*:/i', $css)) {
        return '';
    }
    return trim($css);
}

// Chaque morceau entre ; { et } qui contient une construction dangereuse est supprimé en entier
function css_sans_declarations_dangereuses(string $css): string
{
    $morceaux = preg_split('/([;{}])/', $css, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    foreach ($morceaux as $i => $morceau) {
        if ($i % 2 === 0 && preg_match(JR_CSS_DANGEREUX, $morceau)) {
            $morceaux[$i] = '';
        }
    }
    return implode('', $morceaux);
}

// \75 rl( et \u rl( se lisent « url( » : on les réécrit en clair, sauf les caractères qui changeraient la structure du CSS
function css_defaire_echappements(string $css): string
{
    return preg_replace_callback(
        '/\\\\\\\\|\\\\([0-9a-fA-F]{1,6})[ \t\n\r\f]?|\\\\([g-zG-Z])/',
        function (array $m): string {
            if (($m[2] ?? '') !== '') {
                return $m[2];
            }
            if (($m[1] ?? '') === '') {
                return $m[0];
            }
            $caractere = hexdec($m[1]);
            return $caractere < 128 && preg_match('/^[A-Za-z0-9():_.\/,%#+=?&@!-]$/', chr($caractere)) ? chr($caractere) : $m[0];
        },
        $css
    ) ?? '';
}

const JR_HTML_STYLE_PAGE_HTML = 'html { color-scheme: light; background: #fff; } '
    . 'body { margin: 8px 12px; background: #fff; overflow-wrap: anywhere; word-wrap: break-word; } '
    . 'img { max-width: 100%; height: auto; } '
    . '.jr-bandeau { margin: 0 0 12px; padding: 8px 10px; border-radius: 8px; background: #f2f2f2; color: #555; '
    . 'font: 13px/1.4 -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif; }';

function page_corps_html(array $nettoye, ?string $bandeau): string
{
    $styles = str_ireplace('</style', '<\\/style', (string) ($nettoye['styles'] ?? ''));
    $page = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<style>' . JR_HTML_STYLE_PAGE_HTML . '</style>'
        . ($styles !== '' ? '<style>' . $styles . '</style>' : '')
        . '</head><body' . (!empty($nettoye['images_bloquees']) ? ' data-images-bloquees="1"' : '') . '>'
        . page_bandeau($bandeau)
        . (string) ($nettoye['corps'] ?? '')
        . '</body></html>';
    return $page;
}

function page_bandeau(?string $bandeau): string
{
    return $bandeau === null || $bandeau === '' ? '' : '<div class="jr-bandeau">' . html_echapper($bandeau) . '</div>';
}

function html_echapper(string $texte): string
{
    return htmlspecialchars(mb_scrub($texte, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

const JR_HTML_STYLE_PAGE_TEXTE = ':root { color-scheme: light dark; } '
    . 'body { margin: 8px 12px; font: 16px/1.45 -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif; '
    . 'white-space: pre-wrap; overflow-wrap: anywhere; word-wrap: break-word; } '
    . 'a { color: #0a60d8; } '
    . '.jr-bandeau { margin: 0 0 12px; padding: 8px 10px; border-radius: 8px; background: rgba(128, 128, 128, 0.15); '
    . 'font-size: 13px; line-height: 1.4; white-space: normal; opacity: 0.85; } '
    . '@media (prefers-color-scheme: dark) { body { background: #000; color: #f2f2f7; } a { color: #6cb0ff; } }';

function page_corps_texte(string $texte, ?string $bandeau): string
{
    $texte = str_replace(["\r\n", "\r"], "\n", mb_scrub($texte, 'UTF-8'));
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<style>' . JR_HTML_STYLE_PAGE_TEXTE . '</style></head><body>'
        . page_bandeau($bandeau)
        . texte_avec_liens($texte)
        . '</body></html>';
}

// Texte échappé où les adresses http(s) et les adresses mail deviennent des liens
function texte_avec_liens(string $texte): string
{
    $motif = '~https?://[^\s<>"\x{00A0}]+|[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}~u';
    if (!preg_match_all($motif, $texte, $trouves, PREG_OFFSET_CAPTURE)) {
        return html_echapper($texte);
    }
    $sortie = '';
    $position = 0;
    foreach ($trouves[0] as [$trouve, $debut]) {
        if ($debut < $position) {
            continue;
        }
        $trouve = lien_sans_ponctuation_finale($trouve);
        $href = preg_match('~^https?://~i', $trouve) ? $trouve : 'mailto:' . $trouve;
        $sortie .= html_echapper(substr($texte, $position, $debut - $position))
            . '<a href="' . html_echapper($href) . '" target="_blank" rel="noopener noreferrer">' . html_echapper($trouve) . '</a>';
        $position = $debut + strlen($trouve);
    }
    return $sortie . html_echapper(substr($texte, $position));
}

// « voir https://x.fr/a. » ou « (https://x.fr/a) » : la ponctuation qui suit le lien n'en fait pas partie
function lien_sans_ponctuation_finale(string $lien): string
{
    while ($lien !== '') {
        $dernier = substr($lien, -1);
        $ferme = $dernier === ')' && substr_count($lien, '(') < substr_count($lien, ')');
        if (str_contains('.,;:!?\'»]}', $dernier) || $ferme) {
            $lien = substr($lien, 0, -1);
            continue;
        }
        break;
    }
    return $lien;
}

function page_corps_erreur(string $message): string
{
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<style>:root { color-scheme: light dark; } body { margin: 0; padding: 32px 16px; text-align: center; '
        . 'font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif; color: #666; } '
        . '@media (prefers-color-scheme: dark) { body { background: #000; color: #a1a1a6; } }</style></head><body><p>'
        . html_echapper($message) . '</p></body></html>';
}
