<?php
declare(strict_types=1);

function brut_test(int $uid, array $surcharges = []): array
{
    return array_replace([
        'uid' => $uid, 'message_id' => "<m$uid@exemple.fr>", 'date' => sprintf('2026-10-23T%02d:00:00Z', $uid % 24),
        'de_nom' => 'Alice', 'de_adresse' => 'alice@exemple.fr', 'objet' => "Objet $uid", 'extrait' => 'Bonjour',
        'non_lu' => false, 'entetes' => [], 'gm_msgid' => null,
    ], $surcharges);
}

function mail_test(int $uid, array $surcharges = []): array
{
    return array_replace(mail_pour_copie(brut_test($uid), 'icloud'), $surcharges);
}

test('tri mails : UTC par To, Cc, Delivered-To, X-Forwarded-To et Received', function () {
    foreach (['to', 'cc', 'delivered-to', 'x-forwarded-to'] as $nom) {
        vrai(mail_est_utc([$nom => ['Colin <colin@etu.utc.fr>']]), $nom);
    }
    vrai(mail_est_utc(['received' => ['from mx.utc.fr by mx.google.com']]), 'received');
    vrai(mail_est_utc(['to' => ['x@etu.utc.fr']]), 'etu.utc.fr');
    vrai(mail_est_utc(['to' => ['x@utc.fr.exemple.com']]), 'règle large');
    vrai(mail_est_utc(['to' => ['x@UTC.FR']]), 'casse');
    vrai(!mail_est_utc(['to' => ['colin@gmail.com'], 'received' => ['from smtp.exemple.com']]), 'autre');
    vrai(!mail_est_utc([]), 'sans en-têtes');
    vrai(!mail_est_utc(['to' => ['']]), 'en-tête vide');
});

test("tri mails : un mail d'iCloud ou de QYRIS n'est jamais utc, un mail de Gmail oui", function () {
    $brut = brut_test(1, ['entetes' => ['to' => ['colin@etu.utc.fr']]]);
    egal(false, mail_pour_copie($brut, 'icloud')['utc']);
    egal(false, mail_pour_copie($brut, 'colin')['utc']);
    egal(true, mail_pour_copie($brut, 'gmail')['utc']);
});

test('tri mails : signes de pub, Precedence bulk et list mais pas junk', function () {
    egal(['list-unsubscribe', 'precedence', 'list-id'], mail_signes_pub(['list-unsubscribe' => ['<mailto:x@y.fr>'], 'precedence' => ['bulk'], 'list-id' => ['<a.b>']]));
    egal(['precedence'], mail_signes_pub(['precedence' => [' List ']]));
    egal([], mail_signes_pub(['precedence' => ['junk']]));
    egal([], mail_signes_pub(['list-unsubscribe' => [''], 'to' => ['a@b.fr']]));
});

test("tri mails : forme d'un mail de copie et lien Gmail en hexadécimal minuscule", function () {
    $brut = brut_test(7, ['gm_msgid' => '1234567890123456789', 'non_lu' => true]);
    $mail = mail_pour_copie($brut, 'gmail');
    egal(['uid', 'message_id', 'date', 'de_nom', 'de_adresse', 'objet', 'extrait', 'non_lu', 'utc', 'signes_pub', 'lien'], array_keys($mail));
    egal('2026-10-23T07:00:00Z', $mail['date']);
    egal('https://mail.google.com/mail/u/0/#all/112210f47de98115', $mail['lien']);
    egal('https://mail.google.com/mail/u/0/#all/ffffffffffffffff', mail_pour_copie(brut_test(1, ['gm_msgid' => '18446744073709551615']), 'gmail')['lien']);
    egal('https://mail.google.com/mail/u/0/#all/0', mail_pour_copie(brut_test(1, ['gm_msgid' => '0']), 'gmail')['lien']);
    egal(null, mail_pour_copie(brut_test(1), 'gmail')['lien']);
    egal(null, mail_pour_copie($brut, 'icloud')['lien']);
});

test('tri mails : pub, UTC jamais pub, correction pub ou afficher', function () {
    $news = ['signes_pub' => ['list-unsubscribe'], 'de_adresse' => 'News@Exemple.fr'];
    vrai(mail_est_pub(mail_test(1, $news), []), 'signe seul');
    vrai(!mail_est_pub(mail_test(1), []), 'rien');
    vrai(!mail_est_pub(mail_test(1, $news), ['news@exemple.fr' => 'afficher']), 'afficher sur newsletter');
    vrai(mail_est_pub(mail_test(1, ['de_adresse' => 'alice@exemple.fr']), ['alice@exemple.fr' => 'pub']), 'pub sur mail propre');
    vrai(!mail_est_pub(mail_test(1, ['utc' => true, 'signes_pub' => ['list-id']]), []), 'utc avec signes');
    vrai(!mail_est_pub(mail_test(1, ['utc' => true]), ['alice@exemple.fr' => 'pub']), 'correction pub sans effet sur utc');
});

function copie_test(array $mails): array
{
    return ['uidvalidity' => 1, 'mails' => $mails];
}

test('tri mails : sections dans l ordre, utc séparé de gmail, QYRIS réunit colin et contact', function () {
    $s = mails_sections([
        'gmail' => copie_test([
            mail_test(1, ['utc' => true, 'non_lu' => true]),
            mail_test(2, ['utc' => false, 'non_lu' => true]),
        ]),
        'icloud' => copie_test([mail_test(3, ['non_lu' => true])]),
        'colin' => copie_test([mail_test(4, ['non_lu' => true])]),
        'contact' => copie_test([mail_test(5, ['non_lu' => true])]),
    ], []);
    egal(['utc', 'qyris', 'gmail', 'icloud'], array_column($s, 'section'));
    egal([1, 2, 1, 1], array_column($s, 'non_lus'));
    egal([1], array_column($s[0]['mails'], 'uid'));
    $comptes = array_column($s[1]['mails'], 'compte');
    sort($comptes);
    egal(['colin', 'contact'], $comptes);
    egal([2], array_column($s[2]['mails'], 'uid'));
    egal('icloud', $s[3]['mails'][0]['compte']);
});

test('tri mails : tous les non lus, complétés par les plus récents jusqu à 20, du plus récent au plus ancien', function () {
    $mails = [];
    for ($i = 1; $i <= 30; $i++) {
        $mails[] = mail_test($i, ['date' => sprintf('2026-10-%02dT10:00:00Z', $i), 'non_lu' => in_array($i, [2, 4], true)]);
    }
    $s = mails_sections(['icloud' => copie_test($mails)], [])[3];
    egal(2, $s['non_lus']);
    egal(20, count($s['mails']));
    egal(range(30, 13), array_column(array_slice($s['mails'], 0, 18), 'uid'));
    egal([4, 2], array_column(array_slice($s['mails'], 18), 'uid'));
    egal([], $s['pubs']);
    egal(0, $s['pubs_total']);

    $beaucoup = [];
    for ($i = 1; $i <= 25; $i++) {
        $beaucoup[] = mail_test($i, ['non_lu' => true]);
    }
    $s = mails_sections(['icloud' => copie_test($beaucoup)], [])[3];
    egal([25, 25], [count($s['mails']), $s['non_lus']]);
});

test('tri mails : pubs limitées à 20 avec leur total, hors compteur', function () {
    $mails = [];
    for ($i = 1; $i <= 25; $i++) {
        $mails[] = mail_test($i, ['date' => sprintf('2026-10-%02dT10:00:00Z', $i), 'non_lu' => true, 'signes_pub' => ['list-id']]);
    }
    $mails[] = mail_test(26, ['non_lu' => true]);
    $s = mails_sections(['icloud' => copie_test($mails)], [])[3];
    egal([1, 25, 20, 25], [$s['non_lus'], $s['pubs_total'], count($s['pubs']), $s['pubs_total']]);
    egal(25, $s['pubs'][0]['uid']);
    egal(['total' => 1, 'par_section' => ['utc' => 0, 'qyris' => 0, 'gmail' => 0, 'icloud' => 1]], mails_resume(mails_sections(['icloud' => copie_test($mails)], [])));
});

test('tri mails : une correction déplace le mail entre mails et pubs sans relire la boîte', function () {
    $copies = ['icloud' => copie_test([mail_test(1, ['non_lu' => true])])];
    egal([1, 0], [mails_sections($copies, [])[3]['non_lus'], mails_sections($copies, [])[3]['pubs_total']]);
    $s = mails_sections($copies, ['alice@exemple.fr' => 'pub'])[3];
    egal([0, 0, 1], [$s['non_lus'], count($s['mails']), $s['pubs_total']]);
});

test('tri mails : doublon QYRIS fusionné par message_id, messages sans Message-ID jamais fusionnés (focus 5)', function () {
    $commun = ['message_id' => '<commun@exemple.fr>', 'non_lu' => true];
    $s = mails_sections([
        'colin' => copie_test([mail_test(1, $commun), mail_test(2, ['message_id' => '', 'non_lu' => true])]),
        'contact' => copie_test([mail_test(11, $commun), mail_test(12, ['message_id' => '', 'non_lu' => true])]),
    ], [])[1];
    egal(3, count($s['mails']));
    egal(3, $s['non_lus']);
    egal(2, count(array_filter($s['mails'], fn($m) => $m['message_id'] === '')));
    egal(1, count(array_filter($s['mails'], fn($m) => $m['message_id'] === '<commun@exemple.fr>')));
    egal(2, count(mails_sections(['colin' => copie_test([mail_test(1, ['message_id' => '0']), mail_test(2, ['message_id' => ''])])], [])[1]['mails']));
});

test('tri mails : copie absente ou nulle donne une section vide sans erreur', function () {
    $s = mails_sections(['gmail' => null, 'icloud' => null, 'colin' => null], []);
    egal(4, count($s));
    foreach ($s as $section) {
        egal([0, [], [], 0], [$section['non_lus'], $section['mails'], $section['pubs'], $section['pubs_total']]);
    }
    egal(0, mails_resume($s)['total']);
});
