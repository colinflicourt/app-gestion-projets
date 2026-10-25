<?php
declare(strict_types=1);

// XP côté serveur : actions rejouées depuis la base, xp_gagnee des écritures, heure du geste, export.
// Les cartes de ces tests ont un identifiant ancien (créé en 2026-05) : sinon ce seraient des cartes éclair.

function xpd_id(int $n): string
{
    return sprintf('%08x', 1780000000) . str_pad(dechex($n), 16, '0', STR_PAD_LEFT);
}

function xpd_carte(int $n, array $surcharges = []): array
{
    return carte_normalisee(array_replace(['id' => xpd_id($n), 'colonne' => 'en_cours', 'domaine' => 'etudes'], $surcharges));
}

function xpd_terminer(int $n, int $id_client, array $champs = []): array
{
    return appeler('POST', 'taches/terminer', $champs + ['id_client' => uuid_test($id_client), 'carte' => xpd_id($n)]);
}

function xpd_quand_de_la_derniere_action(): string
{
    return (string) db()->query('SELECT quand FROM actions ORDER BY id DESC LIMIT 1')->fetchColumn();
}

test("xp serveur : terminer rend xp_gagnee 30, le domaine et l'objet xp ; la réponse garde xp_gagnee et domaine, pas xp", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello([xpd_carte(1)]);
    faux_http([PUT_CARTE => ['json' => []]]);
    $r = corps(xpd_terminer(1, 1));
    egal([30, 'etudes', 30, 30], [$r['xp_gagnee'], $r['domaine'], $r['xp']['total'], $r['xp']['domaines']['etudes']['xp']]);
    $gardee = ecriture_trouver(uuid_test(1))['resultat'];
    egal([30, 'etudes', false], [$gardee['xp_gagnee'], $gardee['domaine'], array_key_exists('xp', $gardee)]);
});

test("xp serveur : un renvoi rend le même xp_gagnee, mais xp est recalculé (une autre écriture est passée entre les deux)", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello([xpd_carte(1), xpd_carte(2, ['checklists' => [checklist_normalisee(2, [false])]])]);
    faux_http([PUT_CARTE => ['json' => []], CHECK_ITEM => ['json' => []]]);
    $premiere = corps(xpd_terminer(1, 1));
    egal(30, $premiere['xp']['total']);
    $cochee = corps(appeler('POST', 'taches/cocher', ['id_client' => uuid_test(2), 'carte' => xpd_id(2), 'element' => id_test('element', 21), 'coche' => true]));
    egal([5, 35], [$cochee['xp_gagnee'], $cochee['xp']['total']]);
    $renvoi = corps(xpd_terminer(1, 1));
    egal([30, 'etudes', 35], [$renvoi['xp_gagnee'], $renvoi['domaine'], $renvoi['xp']['total']]);
    egal(2, count(appels_http()), 'le renvoi ne refait aucun appel à Trello');
    egal(2, nombre_actions());
});

test("xp serveur : Annuler rend xp_gagnee −30 et l'XP revient ; un second Annuler rend 0", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello([xpd_carte(1)]);
    faux_http([PUT_CARTE => ['json' => []]]);
    xpd_terminer(1, 1);
    fixer_heure('2026-10-09 10:00:05');
    $r = corps(appeler('POST', 'taches/annuler', ['id_client' => uuid_test(2), 'id_client_origine' => uuid_test(1)]));
    egal([-30, 'etudes', 0], [$r['xp_gagnee'], $r['domaine'], $r['xp']['total']]);
    $encore = corps(appeler('POST', 'taches/annuler', ['id_client' => uuid_test(3), 'id_client_origine' => uuid_test(1)]));
    egal([0, 0], [$encore['xp_gagnee'], $encore['xp']['total']]);
});

test("xp serveur : Annuler retire le gain de sa seule action, même si une autre action a rapporté entre-temps", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello([xpd_carte(1)]);
    faux_http([PUT_CARTE => ['json' => []]]);
    xpd_terminer(1, 1);
    action_noter('carte_terminee', 'perso', xpd_id(9), null, maintenant(), 'trello', 'approx', ['urgent' => true, 'echeance' => null]);
    fixer_heure('2026-10-09 10:00:05');
    $r = corps(appeler('POST', 'taches/annuler', ['id_client' => uuid_test(2), 'id_client_origine' => uuid_test(1)]));
    egal([-30, 50], [$r['xp_gagnee'], $r['xp']['total']]);
});

test("xp serveur : déplacer hors de Fait rend un xp_gagnee négatif ; vers une autre colonne ou sur place, 0", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello([xpd_carte(1), xpd_carte(2)]);
    faux_http([PUT_CARTE => ['json' => []]]);
    xpd_terminer(1, 1);
    $r = corps(appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(2), 'carte' => xpd_id(1), 'colonne' => 'a_faire']));
    egal([-30, 'etudes', 0], [$r['xp_gagnee'], $r['domaine'], $r['xp']['total']]);
    $r = corps(appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(3), 'carte' => xpd_id(2), 'colonne' => 'en_attente']));
    egal([0, 'etudes'], [$r['xp_gagnee'], $r['domaine']]);
    $r = corps(appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(4), 'carte' => xpd_id(2), 'colonne' => 'en_attente']));
    egal([0, 'etudes', 0], [$r['xp_gagnee'], $r['domaine'], $r['xp']['total']]);
});

test("xp serveur : une carte rouverte sans aucun gain avant (carte terminée hors du journal) rend 0", function () {
    base_de_test();
    copie_trello([xpd_carte(1, ['colonne' => 'fait'])]);
    faux_http([PUT_CARTE => ['json' => []]]);
    $r = corps(appeler('POST', 'taches/deplacer', ['id_client' => uuid_test(1), 'carte' => xpd_id(1), 'colonne' => 'a_faire']));
    egal([0, 0], [$r['xp_gagnee'], $r['xp']['total']]);
    egal(1, nombre_actions());
});

test("xp serveur : carte urgente terminée avant son échéance : 60 ; sans urgence ni échéance : 30", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello([xpd_carte(1, ['urgent' => true, 'echeance' => '2026-10-20T18:00:00+02:00']), xpd_carte(2)]);
    faux_http([PUT_CARTE => ['json' => []]]);
    egal(60, corps(xpd_terminer(1, 1))['xp_gagnee']);
    egal(30, corps(xpd_terminer(2, 2))['xp_gagnee']);
});

test("xp serveur : Focus 3 : une carte terminée dans Trello au même instant ne change pas xp_gagnee du Terminer", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    copie_trello([xpd_carte(1)]);
    // Le rafraîchissement d'une autre requête note l'autre carte pendant l'appel à Trello du Terminer
    $GLOBALS['JR_CLIENT_HTTP'] = function (string $methode, string $url): array {
        action_noter('carte_terminee', 'perso', xpd_id(9), null, maintenant(), 'trello', 'approx', ['urgent' => false, 'echeance' => null]);
        return ['statut' => 200, 'corps' => '{}'];
    };
    $r = corps(xpd_terminer(1, 1));
    egal([30, 60], [$r['xp_gagnee'], $r['xp']['total']], 'xp_gagnee est le gain de l\'action, pas la différence de totaux');
});

test("xp serveur : Focus 4 : terminé hors ligne le 24/10 à 23 h 50, reçu le 25/10 à 00 h 10 : jour du 24 et bonus d'échéance gagné", function () {
    base_de_test();
    fixer_heure('2026-10-24 22:10:00');
    $echeance = '2026-10-24T18:00:00+02:00';
    copie_trello([xpd_carte(1, ['echeance' => $echeance]), xpd_carte(2, ['echeance' => $echeance])]);
    faux_http([PUT_CARTE => ['json' => []]]);
    $r = corps(xpd_terminer(1, 1, ['fait_le' => '2026-10-24T21:50:00.000Z']));
    egal(40, $r['xp_gagnee']);
    egal('2026-10-24 21:50:00', xpd_quand_de_la_derniere_action());
    egal(['2026-10-24'], xp_rejouer(xp_actions(), maintenant())['jours']);
    // Sans l'heure du geste, la carte compte le 25 (heure de réception) et l'échéance du 24 est dépassée
    egal(30, corps(xpd_terminer(2, 2))['xp_gagnee']);
});

test("xp serveur : fait_le illisible, de 8 jours ou à +3 min : heure de réception ; à +1 min : gardé", function () {
    base_de_test();
    fixer_heure('2026-10-24 22:10:00');
    $cas = [
        'illisible' => ['pas une date', '2026-10-24 22:10:00'],
        'texte vide' => ['', '2026-10-24 22:10:00'],
        'nombre' => [12345, '2026-10-24 22:10:00'],
        'tableau' => [['x'], '2026-10-24 22:10:00'],
        '8 jours' => ['2026-10-17T12:00:00.000Z', '2026-10-24 22:10:00'],
        '+3 min' => ['2026-10-24T22:13:00.000Z', '2026-10-24 22:10:00'],
        '+1 min' => ['2026-10-24T22:11:00.000Z', '2026-10-24 22:11:00'],
    ];
    copie_trello(array_map(fn(int $n): array => xpd_carte($n), range(1, count($cas))));
    faux_http([PUT_CARTE => ['json' => []]]);
    $n = 0;
    foreach ($cas as $nom => [$fait_le, $attendu]) {
        $n++;
        egal(200, xpd_terminer($n, $n, ['fait_le' => $fait_le])['statut'], $nom);
        egal($attendu, xpd_quand_de_la_derniere_action(), $nom);
    }
});

test("xp serveur : fait_le est gardé par cocher, terminer, déplacer et tirage/parti, pas inventé pour les autres champs", function () {
    base_de_test();
    fixer_heure('2026-10-24 22:10:00');
    copie_trello([
        xpd_carte(1), xpd_carte(2, ['colonne' => 'fait']), xpd_carte(3, ['checklists' => [checklist_normalisee(3, [false])]]), xpd_carte(4),
    ]);
    faux_http([PUT_CARTE => ['json' => []], CHECK_ITEM => ['json' => []]]);
    $geste = '2026-10-24T20:00:00Z';
    $appels = [
        ['taches/terminer', ['carte' => xpd_id(1)]],
        ['taches/deplacer', ['carte' => xpd_id(2), 'colonne' => 'a_faire']],
        ['taches/cocher', ['carte' => xpd_id(3), 'element' => id_test('element', 31), 'coche' => true]],
        ['tirage/parti', ['carte' => xpd_id(4), 'element' => null]],
    ];
    foreach ($appels as $i => [$route, $champs]) {
        egal(200, appeler('POST', $route, $champs + ['id_client' => uuid_test($i + 1), 'fait_le' => $geste])['statut'], $route);
        egal('2026-10-24 20:00:00', xpd_quand_de_la_derniere_action(), $route);
    }
});

test("xp serveur : heure_du_geste accepte Z, millisecondes et décalage, et refuse tout le reste", function () {
    fixer_heure('2026-10-25 10:00:00');
    $maintenant = '2026-10-25 10:00:00';
    $lu = fn(mixed $v): string => utc(heure_du_geste($v));
    egal('2026-10-25 09:30:00', $lu('2026-10-25T09:30:00Z'));
    egal('2026-10-25 09:30:00', $lu('2026-10-25T09:30:00.123Z'));
    egal('2026-10-25 09:30:00', $lu('2026-10-25T11:30:00+02:00'));
    egal('2026-10-18 10:00:00', $lu('2026-10-18T10:00:00Z'), '7 jours pile : gardé');
    egal($maintenant, $lu('2026-10-18T09:59:59Z'), 'un peu plus de 7 jours : refusé');
    egal('2026-10-25 10:02:00', $lu('2026-10-25T10:02:00Z'), '+2 min pile : gardé');
    egal($maintenant, $lu('2026-10-25T10:02:01Z'), 'au-delà de 2 min : refusé');
    foreach ([null, true, 0, 'hier', '2026-10-25', '2026-13-45T10:00:00Z', ['2026-10-25T09:30:00Z']] as $mauvais) {
        egal($maintenant, $lu($mauvais), json_encode($mauvais));
    }
});

test("xp serveur : xp_actions, xp_gain_action et xp_etat lisent les actions non annulées de la base", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    $a = action_noter('carte_terminee', 'etudes', xpd_id(1), null, utc_test('2026-10-08 08:00:00'), 'app', 'exacte', ['urgent' => true, 'echeance' => null]);
    $b = action_noter('etape_cochee', 'perso', xpd_id(2), id_test('element', 1), utc_test('2026-10-08 09:00:00'), 'trello', 'approx');
    $c = action_noter('carte_terminee', 'asso', xpd_id(3), null, utc_test('2026-10-08 10:00:00'), 'app', 'exacte');
    action_annuler($c);
    $actions = xp_actions();
    egal([$a, $b], array_column($actions, 'id'));
    egal(['carte_terminee', 'etudes', xpd_id(1), null, '2026-10-08 08:00:00', ['urgent' => true, 'echeance' => null]],
        [$actions[0]['type'], $actions[0]['domaine'], $actions[0]['carte_id'], $actions[0]['element_id'], utc($actions[0]['quand']), $actions[0]['donnees']]);
    egal([], $actions[1]['donnees']);
    egal([50, 5, 0, 0], [xp_gain_action($a), xp_gain_action($b), xp_gain_action($c), xp_gain_action(99999)]);
    $etat = xp_etat();
    egal([55, 'stagiaire', ['qyris' => 0, 'asso' => 0, 'etudes' => 50, 'perso' => 5]], [$etat['total'], $etat['grade'], array_map(fn(array $d): int => $d['xp'], $etat['domaines'])]);
});

test("xp serveur : GET xp/export demande une session (401) ; avec session, les actions non annulées et les seuls champs prévus", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    $a = action_noter('carte_terminee', 'etudes', xpd_id(1), null, utc_test('2026-10-08 08:00:00'), 'app', 'exacte', ['urgent' => true, 'echeance' => '2026-10-10T18:00:00+02:00']);
    $b = action_noter('etape_cochee', 'perso', xpd_id(2), id_test('element', 1), utc_test('2026-10-08 09:00:00'), 'trello', 'approx');
    $c = action_noter('carte_ajoutee', 'asso', xpd_id(3), null, utc_test('2026-10-08 10:00:00'), 'app', 'exacte');
    action_annuler($c);
    $sans = traiter(requete(['chemin' => 'xp/export']));
    egal([401, 'session_expiree'], [$sans['statut'], corps($sans)['erreur']['code']]);
    $r = appeler('GET', 'xp/export');
    egal(200, $r['statut']);
    egal([
        'ok' => true,
        'actions' => [
            ['id' => $a, 'type' => 'carte_terminee', 'domaine' => 'etudes', 'carte_id' => xpd_id(1), 'element_id' => null, 'quand' => '2026-10-08T10:00:00+02:00',
                'source' => 'app', 'precision_heure' => 'exacte', 'urgent' => true, 'echeance' => '2026-10-10T18:00:00+02:00'],
            ['id' => $b, 'type' => 'etape_cochee', 'domaine' => 'perso', 'carte_id' => xpd_id(2), 'element_id' => id_test('element', 1), 'quand' => '2026-10-08T11:00:00+02:00',
                'source' => 'trello', 'precision_heure' => 'approx', 'urgent' => null, 'echeance' => null],
        ],
    ], corps($r));
});

test("xp serveur : l'XP n'est stockée nulle part (aucune table ni colonne xp)", function () {
    base_de_test();
    egal(0, (int) db()->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(column_name) LIKE 'xp%'")->fetchColumn());
    egal(0, (int) db()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) REGEXP '(^|_)xp($|_)'")->fetchColumn());
    egal(6, version_base(db()), 'aucune migration ajoutée');
});
