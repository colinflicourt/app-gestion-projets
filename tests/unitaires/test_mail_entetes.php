<?php
declare(strict_types=1);

function verifier_entetes_propres(array $entetes): void
{
    foreach ($entetes as $nom => $valeur) {
        vrai(!preg_match('/[\r\n\0]/', (string) $nom . (string) $valeur), "caractère de contrôle dans l'en-tête $nom");
    }
}

test('en-têtes du corps : page protégée, sans image distante puis avec', function () {
    $sans = entetes_corps_mail(false);
    egal([
        'Content-Type' => 'text/html; charset=utf-8',
        'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox allow-same-origin allow-popups allow-popups-to-escape-sandbox",
        'Referrer-Policy' => 'no-referrer',
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
        'X-Robots-Tag' => 'noindex, nofollow',
    ], $sans);
    $avec = entetes_corps_mail(true);
    egal(str_replace("img-src 'self' data:;", "img-src 'self' data: https:;", $sans['Content-Security-Policy']), $avec['Content-Security-Policy']);
    egal($sans['Content-Security-Policy'] === $avec['Content-Security-Policy'], false);
    unset($sans['Content-Security-Policy'], $avec['Content-Security-Policy']);
    egal($sans, $avec);
    vrai(!str_contains(entetes_corps_mail(true)['Content-Security-Policy'], 'allow-scripts'), 'jamais allow-scripts');
});

test('type servi d une pièce : la liste blanche passe, le reste devient application/octet-stream', function () {
    foreach (['text/html', 'text/html; charset=utf-8', 'image/svg+xml', 'application/xml', 'text/xml', 'application/javascript', 'text/javascript', 'application/xhtml+xml', 'multipart/mixed', '', 'n importe quoi'] as $dangereux) {
        egal('application/octet-stream', piece_type_servi($dangereux), $dangereux);
    }
    egal('application/pdf', piece_type_servi('application/PDF; name=x'));
    egal('image/jpeg', piece_type_servi(' IMAGE/JPEG '));
    egal('text/plain', piece_type_servi('text/plain; charset=iso-8859-1'));
    $docx = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    egal($docx, piece_type_servi($docx));
    foreach (['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'application/msword', 'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/plain', 'text/csv', 'text/calendar'] as $permis) {
        egal($permis, piece_type_servi($permis));
    }
});

test('en-têtes d une pièce : inline pour PDF, images et texte, attachment pour le reste', function () {
    $pdf = entetes_piece(piece_type_servi('application/PDF; name=x'), 'facture.pdf');
    egal('application/pdf', $pdf['Content-Type']);
    egal('inline; filename="facture.pdf"; filename*=UTF-8\'\'facture.pdf', $pdf['Content-Disposition']);
    egal("default-src 'none'; sandbox", $pdf['Content-Security-Policy']);
    egal('nosniff', $pdf['X-Content-Type-Options']);
    egal('no-store', $pdf['Cache-Control']);
    egal('no-referrer', $pdf['Referrer-Policy']);
    egal('inline', explode(';', entetes_piece('image/png', 'photo.png')['Content-Disposition'])[0]);
    egal('inline', explode(';', entetes_piece('text/csv', 'liste.csv')['Content-Disposition'])[0]);
    egal('text/csv', entetes_piece('text/csv', 'liste.csv')['Content-Type']);
    $docx = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    $word = entetes_piece(piece_type_servi($docx), 'lettre.docx');
    egal($docx, $word['Content-Type']);
    egal('attachment', explode(';', $word['Content-Disposition'])[0]);
    foreach (['text/html; charset=utf-8', 'image/svg+xml', 'application/xml', 'application/javascript', 'text/xml'] as $dangereux) {
        $e = entetes_piece(piece_type_servi($dangereux), 'page.html');
        egal('application/octet-stream', $e['Content-Type'], $dangereux);
        egal('attachment', explode(';', $e['Content-Disposition'])[0], $dangereux);
    }
    // Même un type non filtré en amont ne sort jamais tel quel
    egal('application/octet-stream', entetes_piece('text/html', 'a.html')['Content-Type']);
    egal('attachment', explode(';', entetes_piece('text/html', 'a.html')['Content-Disposition'])[0]);
});

test('en-têtes d une pièce : nom accentué, repli ASCII et filename* encodé', function () {
    $e = entetes_piece('application/pdf', 'Résumé été.pdf');
    egal('inline; filename="Resume ete.pdf"; filename*=UTF-8\'\'R%C3%A9sum%C3%A9%20%C3%A9t%C3%A9.pdf', $e['Content-Disposition']);
    $e = entetes_piece('image/png', '日本語😀.png');
    vrai(str_contains($e['Content-Disposition'], 'filename="____.png"'), $e['Content-Disposition']);
    vrai(str_contains($e['Content-Disposition'], "filename*=UTF-8''" . rawurlencode('日本語😀.png')), $e['Content-Disposition']);
});

test('en-têtes d une pièce : nom hostile, une seule ligne, ni guillemet ni antislash dans le repli', function () {
    foreach (["a\"b\r\nSet-Cookie: x.pdf", '../../etc/passwd', 'a\\b;c.pdf', "nul\0octet.pdf", str_repeat('é', 300) . '.pdf', "\xff\xfe.pdf"] as $nom) {
        $e = entetes_piece('application/pdf', $nom);
        verifier_entetes_propres($e);
        egal(['Content-Type', 'Content-Disposition', 'Content-Security-Policy', 'X-Content-Type-Options', 'Cache-Control', 'Referrer-Policy'], array_keys($e));
        vrai(preg_match('/^inline; filename="([^"]*)"; filename\*=UTF-8\'\'([A-Za-z0-9%._~-]*)$/', $e['Content-Disposition'], $m) === 1, 'forme : ' . $e['Content-Disposition']);
        vrai(!preg_match('/[\\\\";\/\x00-\x1f\x7f-\xff]/', $m[1]), 'repli : ' . $m[1]);
    }
    $e = entetes_piece('application/pdf', "a\"b\r\nSet-Cookie: x.pdf");
    egal('inline; filename="a_b__Set-Cookie: x.pdf"; filename*=UTF-8\'\'a%22b__Set-Cookie%3A%20x.pdf', $e['Content-Disposition']);
    $e = entetes_piece('application/pdf', '../../etc/passwd');
    vrai(!str_contains($e['Content-Disposition'], '/'), $e['Content-Disposition']);
});

test('en-têtes d une pièce : nom vide ou sans caractère exploitable', function () {
    foreach (['', '   ', "\r\n"] as $nom) {
        $e = entetes_piece('application/pdf', $nom);
        egal('inline; filename="piece-jointe"; filename*=UTF-8\'\'piece-jointe', $e['Content-Disposition'], bin2hex($nom));
    }
});

test('en-têtes d une pièce d erreur : texte neutre, rien d actif', function () {
    $e = entetes_piece_erreur();
    egal([
        'Content-Type' => 'text/plain; charset=utf-8',
        'Content-Security-Policy' => "default-src 'none'; sandbox",
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'no-store',
        'Referrer-Policy' => 'no-referrer',
    ], $e);
});
