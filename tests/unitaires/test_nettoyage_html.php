<?php
declare(strict_types=1);

function fixture_mail_html(string $nom): string
{
    $contenu = file_get_contents(dirname(__DIR__) . '/fixtures/mails-html/' . $nom);
    vrai($contenu !== false, "fixture introuvable : $nom");
    return $contenu;
}

function nettoye_html(string $html, array $cid = [], bool $distantes = false): array
{
    return html_nettoyer($html, $cid, $distantes);
}

// Fragment de la sortie lu comme un DOM, pour juger les balises et attributs et non le texte brut
function dom_fragment(string $corps): DOMDocument
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"><html><body>' . $corps . '</body></html>', LIBXML_NONET);
    libxml_clear_errors();
    return $doc;
}

function elements_du(string $corps, string $balise): array
{
    return iterator_to_array(dom_fragment($corps)->getElementsByTagName($balise), false);
}

function attributs_du(string $corps, string $balise, string $attribut): array
{
    $valeurs = [];
    foreach (elements_du($corps, $balise) as $element) {
        $valeurs[] = $element->hasAttribute($attribut) ? $element->getAttribute($attribut) : null;
    }
    return $valeurs;
}

function sans_balise(string $sortie, array $balises): void
{
    foreach ($balises as $balise) {
        vrai(!preg_match('/<' . preg_quote($balise, '/') . '[\s>\/]/i', $sortie), "balise <$balise> présente");
    }
}

test('nettoyage html : script, son contenu, commentaires et titre disparaissent (corps et tête)', function () {
    $r = nettoye_html(fixture_mail_html('01-pieges-actifs.html'));
    foreach (['script', 'SCRIPT_CORPS', "alert('tête')", 'commentaire', 'Titre secret', '<!--'] as $interdit) {
        vrai(!str_contains(strtolower($r['corps']), strtolower($interdit)), "$interdit présent dans le corps");
    }
    sans_balise($r['corps'], ['script', 'title', 'head', 'body', 'html']);
    egal(1, preg_match('/Bonjour <b>Colin<\/b>/', $r['corps']), 'le contenu sain reste');
    vrai(str_contains($r['corps'], 'div stylé') && str_contains($r['corps'], 'Pied de mail'), 'un <embed> (non fermé) ne doit pas emporter la suite du mail');
});

test('nettoyage html : onerror, onclick, onload, formaction et tout attribut on* partent', function () {
    $r = nettoye_html(fixture_mail_html('01-pieges-actifs.html'));
    foreach (['onerror', 'onclick', 'onmouseover', 'onload', 'formaction'] as $attribut) {
        vrai(!str_contains(strtolower($r['corps']), $attribut), "$attribut présent");
    }
    $r = nettoye_html('<p ONCLICK="x()" OnMouseOver=y() data-x="1" accesskey="a" contenteditable>t</p><img src="data:image/png;base64,AAAA" onerror=z()>');
    egal('<p>t</p><img src="data:image/png;base64,AAAA">', $r['corps']);
});

test('nettoyage html : href javascript (casse, entités, espaces, contrôle) sans href, autres schémas aussi', function () {
    $r = nettoye_html(fixture_mail_html('01-pieges-actifs.html'));
    $href = attributs_du($r['corps'], 'a', 'href');
    egal([null, null, null, null, null, null, null, 'https://exemple.com/page?a=1&b=2', 'mailto:alice@exemple.fr', 'tel:+33600000000'], $href);
    vrai(!str_contains(strtolower($r['corps']), 'script:'), 'javascript: encore visible');
    vrai(!str_contains(strtolower($r['corps']), 'vbscript'), 'vbscript encore visible');
    $r = nettoye_html("<a href=\"java\nscr\tipt:alert(1)\">a</a><a href=\"\x01javascript:alert(1)\">b</a><a href=\" &#x0A;javascript:alert(1)\">c</a>");
    egal([null, null, null], attributs_du($r['corps'], 'a', 'href'));
});

test('nettoyage html : chaque lien reçoit target=_blank et rel=noopener noreferrer, même sans href', function () {
    $r = nettoye_html('<a href="https://exemple.com" target="_self" rel="opener">a</a><a href="mailto:x@exemple.fr">b</a><a href="javascript:1">c</a><a name="x">d</a>');
    $liens = elements_du($r['corps'], 'a');
    egal(4, count($liens));
    foreach ($liens as $lien) {
        egal('_blank', $lien->getAttribute('target'));
        egal('noopener noreferrer', $lien->getAttribute('rel'));
    }
    egal(['https://exemple.com', 'mailto:x@exemple.fr', null, null], attributs_du($r['corps'], 'a', 'href'));
});

test('nettoyage html : svg (avec script), math, template, noscript, form et ses champs partent avec leur contenu', function () {
    $r = nettoye_html(fixture_mail_html('01-pieges-actifs.html'));
    sans_balise($r['corps'], ['svg', 'circle', 'math', 'mtext', 'template', 'noscript', 'form', 'input', 'button', 'select', 'option', 'textarea']);
    foreach (['SVG_SCRIPT', 'MATHS', 'GABARIT', 'NOSCRIPT', 'CHAMP', 'BOUTON', 'OPTION', 'ZONE'] as $texte) {
        vrai(!str_contains($r['corps'], $texte), "$texte présent");
    }
});

test('nettoyage html : meta refresh, base, link, iframe, object, embed absents', function () {
    $r = nettoye_html(fixture_mail_html('01-pieges-actifs.html'));
    sans_balise($r['corps'], ['meta', 'base', 'link', 'iframe', 'object', 'embed', 'frame']);
    foreach (['pirate.invalide/cadre', 'pirate.invalide/o.swf', 'pirate.invalide/e.swf', 'CADRE', 'OBJET', 'refresh', 'pirate.invalide/style.css'] as $texte) {
        vrai(!str_contains($r['corps'] . $r['styles'], $texte), "$texte présent");
    }
    $r = nettoye_html('<p>a</p><meta http-equiv="refresh" content="0;url=https://x.invalide"><base href="https://x.invalide"><link rel="stylesheet" href="https://x.invalide/a.css"><frameset><frame src="https://x.invalide"></frameset>');
    egal('<p>a</p>', $r['corps']);
});

test('nettoyage html : @import, expression(, behavior, -moz-binding, javascript: absents des styles', function () {
    $r = nettoye_html(fixture_mail_html('01-pieges-actifs.html'));
    foreach (['@import', 'expression(', 'behavior', '-moz-binding', 'javascript:', 'pirate.invalide'] as $interdit) {
        vrai(!str_contains(strtolower($r['styles'] . $r['corps']), $interdit), "$interdit présent");
    }
    vrai(str_contains($r['styles'], '#123456'), 'la règle saine est perdue');
    vrai(str_contains($r['styles'], 'margin: 0'), 'la déclaration saine voisine est perdue');
    egal(1, preg_match('/color: red/', $r['corps']), 'le style en ligne sain reste');
});

test('nettoyage html : les échappements CSS ne cachent ni @import ni expression ni url', function () {
    $css = '@\69mport "https://x.invalide/a.css"; a { width: \65xpression(alert(1)); b: ex/**/pression(alert(2)); c: \75rl(/api/x); d: \url(/api/y); e: expr\65ssion (alert(3)) }';
    $r = nettoye_html('<style>' . $css . '</style><p style="width:\65xpression(alert(4)); background: u\72l(//x.invalide/a)">t</p>');
    foreach (['@import', 'import', 'expression', '/api/', 'x.invalide'] as $interdit) {
        vrai(!str_contains(strtolower($r['styles'] . $r['corps']), $interdit), "$interdit présent : " . $r['styles'] . $r['corps']);
    }
});

test('nettoyage html : les styles de tout le document sont mis bout à bout, sans <!-- --> ni fermeture de style', function () {
    $r = nettoye_html(fixture_mail_html('04-newsletter-images.html'), [], true);
    vrai(str_contains($r['styles'], 'body { margin: 0 }'), 'style de la tête perdu');
    vrai(str_contains($r['styles'], '.dans-le-corps { color: blue }'), 'style du corps perdu');
    vrai(strpos($r['styles'], 'margin: 0') < strpos($r['styles'], 'color: blue'), 'ordre du document');
    vrai(!str_contains($r['styles'], '<!--') && !str_contains($r['styles'], '-->'), 'commentaires HTML dans les styles');
    sans_balise($r['corps'], ['style']);
    $r = nettoye_html('<style>p { color: red } </style><script>x</script><p>a</p>');
    egal(false, str_contains($r['styles'], '<'), 'jamais de < brut dans les styles');
    $r = nettoye_html('<style>/* </style><script>alert(1)</script> */ p { content: "<\/style>" }</style>');
    vrai(!str_contains(strtolower($r['styles']), '</style'), 'fermeture de style dans les styles');
});

test('nettoyage html : cid remplacé par la data: donnée, comparaison sans casse, inconnu ou null donne un cadre sans src', function () {
    $png = 'data:image/png;base64,iVBORw0KGgo=';
    $r = nettoye_html(fixture_mail_html('04-newsletter-images.html'), ['logo@exemple' => $png, 'grosse@exemple' => null], true);
    $imgs = elements_du($r['corps'], 'img');
    egal($png, $imgs[0]->getAttribute('src'), 'cid en majuscules dans le mail');
    egal('120', $imgs[0]->getAttribute('width'));
    egal(false, $imgs[1]->hasAttribute('src'), 'cid null');
    egal('600', $imgs[1]->getAttribute('width'));
    egal('300', $imgs[1]->getAttribute('height'));
    egal(false, $imgs[2]->hasAttribute('src'), 'cid inconnu');
    egal('10', $imgs[2]->getAttribute('height'));
    vrai(!str_contains($r['corps'], 'cid:'), 'cid: encore présent');
    $r = nettoye_html('<img src="cid:a%40b.fr"><img src="cid:&lt;c@d&gt;">', ['a@b.fr' => $png, 'c@d' => $png]);
    egal([$png, $png], attributs_du($r['corps'], 'img', 'src'), 'cid encodé ou entre chevrons');
});

test("nettoyage html : une data: de cid qui n'est pas une image sûre est refusée", function () {
    $r = nettoye_html('<img src="cid:x">', ['x' => 'data:image/svg+xml;base64,PHN2Zy8+']);
    egal([null], attributs_du($r['corps'], 'img', 'src'));
    $r = nettoye_html('<img src="cid:x">', ['x' => 'https://exemple.com/a.png']);
    egal([null], attributs_du($r['corps'], 'img', 'src'));
});

test('nettoyage html : Focus 1, liens, images, styles relatifs ou en //hôte : tous retirés ou neutralisés', function () {
    $r = nettoye_html(fixture_mail_html('02-liens-relatifs.html'), [], true);
    egal([null, null, null, null, null], attributs_du($r['corps'], 'a', 'href'));
    egal([null, null, null, null], attributs_du($r['corps'], 'img', 'src'));
    foreach (['srcset', 'background=', '/api/', 'exemple.com', '../images', 'image-set'] as $interdit) {
        vrai(!str_contains($r['corps'] . $r['styles'], $interdit), "$interdit présent : " . $r['corps'] . $r['styles']);
    }
    vrai(str_contains($r['styles'], 'none'), 'url() relative devrait devenir none');
    egal(1, preg_match('/color: green/', $r['corps']), 'le reste du style en ligne est gardé');
});

test('nettoyage html : Focus 1 en ligne, background-image: url(/api/x) et url(\'//exemple.com/a.png\')', function () {
    $r = nettoye_html('<p style="background-image: url(/api/x)">a</p><p style="background:url(\'//exemple.com/a.png\') no-repeat">b</p><p style="background: URL( &quot;/api/x&quot; )">c</p>');
    foreach (['/api/', 'exemple.com', 'url('] as $interdit) {
        vrai(!str_contains(strtolower($r['corps']), $interdit), "$interdit présent : " . $r['corps']);
    }
    egal(1, preg_match('/background-image: none/', $r['corps']));
});

test('nettoyage html : Focus 2, accents, emoji et caractères hors latin intacts malgré un charset Windows-1252 dans le mail', function () {
    $r = nettoye_html(fixture_mail_html('03-accents-emoji.html'));
    foreach (['Été à Compiègne', '5 €', '😀', '« ça vaut le coup »', '日本語', 'œuf', "Noël à l'école"] as $texte) {
        vrai(str_contains(html_entity_decode($r['corps'], ENT_QUOTES, 'UTF-8'), $texte), "$texte perdu ou abîmé : " . $r['corps']);
    }
    vrai(!str_contains($r['corps'], 'Ã'), 'mojibake « Ã »');
    vrai(!str_contains($r['corps'], 'â‚¬'), 'mojibake euro');
    vrai(str_contains($r['styles'], 'content: "é"'), 'accent perdu dans les styles : ' . $r['styles']);
    vrai(mb_check_encoding($r['corps'] . $r['styles'], 'UTF-8'), 'UTF-8 invalide');
    $r = nettoye_html("<p>Octet invalide \xE9 puis fin \xF0\x9F</p>");
    vrai(mb_check_encoding($r['corps'], 'UTF-8'), 'UTF-8 invalide en sortie');
    $r = nettoye_html("<p>a\0b</p>");
    vrai(str_contains($r['corps'], 'b'), 'un octet nul ne coupe pas le mail');
});

test('nettoyage html : image distante bloquée (src retiré, images_bloquees vrai), puis gardée avec $images_distantes', function () {
    $html = '<img src="https://suivi.exemple.com/p.gif" width="1" height="1" alt="">';
    $r = nettoye_html($html);
    egal(true, $r['images_bloquees']);
    egal([null], attributs_du($r['corps'], 'img', 'src'));
    egal(['1'], attributs_du($r['corps'], 'img', 'width'));
    $r = nettoye_html($html, [], true);
    egal(false, $r['images_bloquees']);
    egal(['https://suivi.exemple.com/p.gif'], attributs_du($r['corps'], 'img', 'src'));
    $r = nettoye_html('<img src="http://suivi.exemple.com/p.gif">');
    egal(true, $r['images_bloquees']);
    egal([null], attributs_du($r['corps'], 'img', 'src'));
});

test('nettoyage html : url(https://…) en CSS bloquée met images_bloquees, gardée avec $images_distantes', function () {
    $html = '<style>.a { background: url("https://suivi.exemple.com/f.png") red }</style><p style="color: blue">t</p>';
    $r = nettoye_html($html);
    egal(true, $r['images_bloquees']);
    vrai(!str_contains($r['styles'], 'suivi.exemple.com'), 'url distante gardée');
    vrai(str_contains($r['styles'], 'background: none red'), 'neutralisée en none : ' . $r['styles']);
    $r = nettoye_html($html, [], true);
    egal(false, $r['images_bloquees']);
    vrai(str_contains($r['styles'], 'url("https://suivi.exemple.com/f.png")'), 'url distante perdue : ' . $r['styles']);
    $r = nettoye_html('<p style="background-image:url(http://x.exemple.com/a.png)">t</p>');
    egal(true, $r['images_bloquees']);
    vrai(!str_contains($r['corps'], 'x.exemple.com'), 'url http gardée');
});

test("nettoyage html : une police distante (@font-face) n'est pas une image bloquée", function () {
    $r = nettoye_html('<style>@font-face { font-family: f; src: url(https://exemple.com/f.woff) } p { color: red }</style><p>t</p>');
    egal(false, $r['images_bloquees']);
    egal('p { color: red }', trim($r['styles']));
});

test('nettoyage html : images_bloquees faux sans aucune image distante', function () {
    foreach (['01-pieges-actifs.html', '02-liens-relatifs.html', '03-accents-emoji.html', '05-vieux-html.html', '06-fragment-sans-balises.html'] as $fixture) {
        egal(false, nettoye_html(fixture_mail_html($fixture))['images_bloquees'], $fixture);
    }
    egal(false, nettoye_html('<img src="data:image/png;base64,AAAA"><a href="https://exemple.com/a.png">lien</a><img src="cid:x">', ['x' => 'data:image/png;base64,AAAA'])['images_bloquees']);
    egal(false, nettoye_html('<p style="background:url(data:image/png;base64,AAAA)">t</p>')['images_bloquees']);
    egal(true, nettoye_html(fixture_mail_html('04-newsletter-images.html'))['images_bloquees']);
});

test('nettoyage html : data:image/svg+xml refusé, data:image/png;base64 gardé, autres data: refusés', function () {
    $r = nettoye_html(fixture_mail_html('04-newsletter-images.html'), [], true);
    $src = attributs_du($r['corps'], 'img', 'src');
    egal('data:image/png;base64,iVBORw0KGgo=', $src[5]);
    egal([null, null, null], array_slice($src, 6, 3));
    vrai(!str_contains($r['corps'], 'svg'), 'svg encore là');
    vrai(!str_contains($r['corps'], 'text/html'), 'data:text/html encore là');
    vrai(str_contains($r['styles'], 'url("data:image/png;base64,iVBORw0KGgo=")'), 'data: png en CSS perdue : ' . $r['styles']);
    $r = nettoye_html('<p style="background:url(data:image/svg+xml;base64,PHN2Zy8+)">t</p>');
    vrai(!str_contains($r['corps'], 'svg'), 'svg en CSS gardé');
});

test('nettoyage html : balises inconnues retirées mais leur contenu gardé, anciennes balises et attributs de mise en page gardés', function () {
    $r = nettoye_html(fixture_mail_html('05-vieux-html.html'));
    sans_balise($r['corps'], ['o:p', 'blink', 'marquee', 'label', 'video', 'audio', 'xmp']);
    foreach (['clignotant', 'défilant', 'Étiquette'] as $texte) {
        vrai(str_contains($r['corps'], $texte), "$texte perdu");
    }
    foreach (['VIDEO', 'AUDIO'] as $texte) {
        vrai(!str_contains($r['corps'], $texte), "$texte gardé");
    }
    vrai(str_contains($r['corps'], '<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">'), $r['corps']);
    vrai(str_contains($r['corps'], '<td valign="top" align="left" width="50%" colspan="2" bgcolor="#EEEEEE">Cellule<br>deux lignes</td>'), $r['corps']);
    vrai(str_contains($r['corps'], '<font face="Arial" size="2" color="red">'), $r['corps']);
    vrai(str_contains($r['corps'], '<ol type="a" start="3">'), $r['corps']);
    vrai(str_contains($r['corps'], "<pre>\n\n ligne  1\nligne 2</pre>"), 'le premier retour à la ligne du pre est gardé : ' . $r['corps']);
    vrai(str_contains($r['styles'], 'P.MsoNormal { margin: 0cm; font-size: 11pt }'), $r['styles']);
    egal(['HTTP://EXEMPLE.COM/X'], attributs_du($r['corps'], 'a', 'href'));
});

test("nettoyage html : un fragment sans balises donne un corps échappé, une imbrication très profonde ne plante pas", function () {
    $r = nettoye_html(fixture_mail_html('06-fragment-sans-balises.html'));
    vrai(str_contains($r['corps'], 'café'), $r['corps']);
    vrai(str_contains($r['corps'], '<b>faux gras</b>'), 'la vraie balise <b> reste');
    vrai(str_contains($r['corps'], '&amp; 5 &lt; 6.'), 'esperluette et < échappés : ' . $r['corps']);
    $r = nettoye_html(fixture_mail_html('07-imbrication-profonde.html'));
    vrai(!str_contains($r['corps'], 'alert'), 'script de fin gardé');
    vrai($r['corps'] !== '', 'corps vide');
    vrai(substr_count($r['corps'], '<div>') <= JR_HTML_PROFONDEUR_MAX, 'trop de niveaux gardés');
    egal(substr_count($r['corps'], '<div>'), substr_count($r['corps'], '</div>'), 'balises non fermées');
    $r = nettoye_html('<div>' . str_repeat('<div>', 249) . 'FOND' . str_repeat('</div>', 250) . '<p>APRES</p>');
    vrai(str_contains($r['corps'], 'FOND') && str_contains($r['corps'], '<p>APRES</p>'), 'texte perdu au-delà de la profondeur permise');
    egal(JR_HTML_PROFONDEUR_MAX, substr_count($r['corps'], '<div>'), 'niveaux gardés');
    egal(['corps' => '', 'styles' => '', 'images_bloquees' => false], nettoye_html(''));
    egal('', nettoye_html('   ')['corps']);
});

test('nettoyage html : toute la sortie des fixtures reste dans la liste blanche de balises et attributs', function () {
    $balises = explode(' ', 'a abbr address b bdi bdo big blockquote br caption center cite code col colgroup dd del dfn div dl dt em font h1 h2 h3 h4 h5 h6 hr i img ins kbd li ol p pre q s small span strike strong sub sup table tbody td tfoot th thead tr tt u ul');
    $attributs = explode(' ', 'align alt bgcolor border cellpadding cellspacing class color colspan dir face height id lang rowspan size span style title valign width');
    $fixtures = glob(dirname(__DIR__) . '/fixtures/mails-html/*.html');
    vrai(count($fixtures) >= 6, 'fixtures manquantes');
    foreach ($fixtures as $fichier) {
        foreach ([[false, []], [true, ['logo@exemple' => 'data:image/png;base64,AAAA']]] as [$distantes, $cid]) {
            $r = html_nettoyer((string) file_get_contents($fichier), $cid, $distantes);
            $doc = dom_fragment($r['corps']);
            $nb = 0;
            foreach ((new DOMXPath($doc))->query('//body//*') as $element) {
                $nb++;
                $nom = $element->nodeName;
                vrai(in_array($nom, $balises, true), basename($fichier) . " : balise hors liste <$nom>");
                foreach ($element->attributes as $attribut) {
                    $a = $attribut->nodeName;
                    $permis = in_array($a, $attributs, true)
                        || ($a === 'href' && $nom === 'a') || ($a === 'target' && $nom === 'a') || ($a === 'rel' && $nom === 'a')
                        || ($a === 'src' && $nom === 'img')
                        || (in_array($a, ['start', 'type'], true) && in_array($nom, ['ol', 'ul'], true));
                    vrai($permis, basename($fichier) . " : attribut hors liste $a sur <$nom>");
                    if ($a === 'href') {
                        vrai(preg_match('#^(https?://|mailto:|tel:)#i', $attribut->value) === 1, "href non absolu : {$attribut->value}");
                    }
                    if ($a === 'src') {
                        vrai(preg_match('#^(https?://|data:image/(png|jpeg|jpg|gif|webp|bmp);base64,)#i', $attribut->value) === 1, "src non permis : {$attribut->value}");
                    }
                }
            }
            vrai($nb > 0, basename($fichier) . ' : sortie vide');
            vrai(!preg_match('/url\(\s*["\']?+(?!data:image|https:)/i', $r['styles']), basename($fichier) . ' : url() hors règle dans les styles : ' . $r['styles']);
        }
    }
});

test("page corps html : document reconstruit, UTF-8, styles de base puis styles du mail, bandeau échappé en haut", function () {
    $page = page_corps_html(['corps' => '<p>Bonjour</p>', 'styles' => 'p { color: red }', 'images_bloquees' => false], 'Mail coupé : la suite est dans <Gmail> & plus.');
    vrai(str_starts_with($page, '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'), substr($page, 0, 150));
    foreach (['color-scheme: light', 'max-width: 100%', 'height: auto', 'overflow-wrap: anywhere'] as $regle) {
        vrai(str_contains($page, $regle), "$regle absent");
    }
    vrai(strpos($page, 'overflow-wrap') < strpos($page, 'p { color: red }'), 'les styles du mail viennent après les styles de base');
    vrai(strpos($page, 'Mail coupé') < strpos($page, '<p>Bonjour</p>'), 'bandeau en haut');
    vrai(str_contains($page, 'Mail coupé : la suite est dans &lt;Gmail&gt; &amp; plus.'), 'bandeau non échappé');
    vrai(!str_contains($page, '<Gmail>'), 'bandeau brut');
    $doc = new DOMDocument();
    $doc->loadHTML($page, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    egal(1, $doc->getElementsByTagName('body')->length);
    egal(2, $doc->getElementsByTagName('style')->length);
});

test('page corps html : data-images-bloquees présent seulement si des images distantes sont bloquées, pas de bandeau sans bandeau', function () {
    $avec = page_corps_html(['corps' => '<p>a</p>', 'styles' => '', 'images_bloquees' => true], null);
    $sans = page_corps_html(['corps' => '<p>a</p>', 'styles' => '', 'images_bloquees' => false], null);
    vrai(str_contains($avec, '<body data-images-bloquees="1">'), 'attribut absent');
    vrai(!str_contains($sans, 'data-images-bloquees'), 'attribut présent à tort');
    vrai(str_contains($sans, '<body>'), 'body sans attribut');
    vrai(!str_contains($sans, 'class="jr-bandeau"'), 'bandeau présent sans bandeau');
    egal(1, substr_count($sans, '<style>'), 'pas de style du mail vide');
    $r = html_nettoyer('<img src="https://suivi.exemple.com/p.gif">', [], false);
    vrai(str_contains(page_corps_html($r, null), 'data-images-bloquees="1"'), 'de bout en bout');
    $r = html_nettoyer('<img src="https://suivi.exemple.com/p.gif">', [], true);
    vrai(!str_contains(page_corps_html($r, null), 'data-images-bloquees'), 'de bout en bout, images permises');
});

test('page corps html : une page construite sur les fixtures ne contient ni script, ni iframe, ni gestionnaire', function () {
    foreach (glob(dirname(__DIR__) . '/fixtures/mails-html/*.html') as $fichier) {
        $page = page_corps_html(html_nettoyer((string) file_get_contents($fichier), [], true), 'bandeau');
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8">' . $page, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach (['script', 'iframe', 'object', 'embed', 'form', 'base', 'link', 'svg'] as $balise) {
            egal(0, $doc->getElementsByTagName($balise)->length, basename($fichier) . " : $balise");
        }
        egal(1, $doc->getElementsByTagName('body')->length, basename($fichier));
        egal(0, preg_match('/\son[a-z]+\s*=/i', $page), basename($fichier) . ' : gestionnaire on*');
    }
});

test('page corps texte : texte échappé, retours gardés, liens et adresses cliquables avec target et rel', function () {
    $page = page_corps_texte("Bonjour <b>Colin</b> & Cie\nVoir https://exemple.com/a?b=1&c=2, ou écrire à colin@exemple.com.\n(http://x.fr/p)", null);
    vrai(str_contains($page, '&lt;b&gt;Colin&lt;/b&gt; &amp; Cie'), 'texte non échappé');
    vrai(!str_contains($page, '<b>'), 'balise brute');
    vrai(str_contains($page, 'white-space: pre-wrap'), 'pre-wrap');
    vrai(str_contains($page, 'color-scheme: light dark'), 'thème');
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8">' . $page, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $liens = iterator_to_array($doc->getElementsByTagName('a'), false);
    egal(3, count($liens));
    egal(['https://exemple.com/a?b=1&c=2', 'mailto:colin@exemple.com', 'http://x.fr/p'], array_map(fn ($a) => $a->getAttribute('href'), $liens));
    egal(['https://exemple.com/a?b=1&c=2', 'colin@exemple.com', 'http://x.fr/p'], array_map(fn ($a) => $a->textContent, $liens));
    foreach ($liens as $lien) {
        egal('_blank', $lien->getAttribute('target'));
        egal('noopener noreferrer', $lien->getAttribute('rel'));
    }
    vrai(str_contains($page, '</a>, ou écrire à'), 'la virgule finale reste hors du lien');
    vrai(str_contains($page, "colin@exemple.com</a>."), 'le point final reste hors du lien');
    vrai(str_contains($page, "Bonjour &lt;b&gt;Colin&lt;/b&gt; &amp; Cie\nVoir"), 'retour à la ligne perdu');
});

test('page corps texte : javascript: et autres schémas ne deviennent jamais des liens, bandeau échappé en haut, UTF-8', function () {
    $page = page_corps_texte("javascript:alert(1) \"><script>alert(2)</script> data:text/html,x ftp://x.fr Été 😀\r\nfin", 'Mail <coupé>');
    egal(0, substr_count($page, '<a '), 'lien inattendu');
    vrai(!str_contains($page, '<script'), 'script brut');
    vrai(str_contains($page, 'Été 😀'), 'accents ou emoji');
    vrai(!str_contains($page, "\r"), 'CR gardé');
    vrai(strpos($page, 'Mail &lt;coupé&gt;') < strpos($page, 'javascript:'), 'bandeau en haut');
    vrai(str_starts_with($page, '<!doctype html><html><head><meta charset="utf-8">'), 'entête');
    vrai(mb_check_encoding(page_corps_texte("a \xE9 b", null), 'UTF-8'), 'UTF-8 invalide');
    $page = page_corps_texte('https://exemple.com/"><img src=x onerror=alert(1)>', null);
    vrai(!str_contains($page, '<img'), 'injection par URL');
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8">' . $page, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    egal(0, $doc->getElementsByTagName('img')->length);
});

test('page corps erreur : petite page neutre, message échappé', function () {
    $page = page_corps_erreur('Ce mail ne s\'ouvre pas <pour> l\'instant.');
    vrai(str_starts_with($page, '<!doctype html><html'), 'document complet');
    vrai(str_contains($page, '<meta charset="utf-8">'), 'charset');
    vrai(str_contains($page, 'Ce mail ne s&#039;ouvre pas &lt;pour&gt; l&#039;instant.'), 'message non échappé : ' . $page);
    vrai(!str_contains($page, '<pour>'), 'balise brute');
    vrai(!str_contains($page, '<script'), 'script');
});
