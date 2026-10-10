<?php
declare(strict_types=1);

test('créneaux : « TN01 - TD 2 » en FA510 donne l\'UV, le type, la salle et les heures de Paris', function () {
    egal([
        'debut' => '2026-10-14T16:30:00+02:00', 'fin' => '2026-10-14T18:30:00+02:00', 'journee' => false, 'source' => 'utc',
        'uv' => 'TN01', 'type' => 'TD', 'salle' => 'FA510', 'titre' => 'TN01 - TD 2', 'median' => false,
    ], creneau_lire(evenement_google('TN01 - TD 2', '2026-10-14T16:30', '2026-10-14T18:30', 'FA510'), 'utc'));
});

test('créneaux : Cours, TD et TP, numéro facultatif, salle absente', function () {
    egal(['GE15', 'Cours', 'FA201'], array_values(array_intersect_key(creneau_lire(evenement_google('GE15 - Cours 1', '2026-10-13T13:00', '2026-10-13T15:00', 'FA201'), 'utc'), ['uv' => 1, 'type' => 1, 'salle' => 1])));
    $tp = creneau_lire(evenement_google('MT23 - TP', '2026-10-15T08:00', '2026-10-15T10:00'), 'utc');
    egal(['MT23', 'TP', null], [$tp['uv'], $tp['type'], $tp['salle']]);
});

test("créneaux : le lundi 26/10, après le passage à l'heure d'hiver, un TD de 8 h reste à 8 h", function () {
    $c = creneau_lire(evenement_google('NF93 - TD 1', '2026-10-26T08:00', '2026-10-26T10:00', 'FA400'), 'utc');
    egal('2026-10-26T08:00:00+01:00', $c['debut']);
    // Google peut aussi renvoyer l'heure en UTC
    $utc = ['summary' => 'NF93 - TD 1', 'start' => ['dateTime' => '2026-10-26T07:00:00Z'], 'end' => ['dateTime' => '2026-10-26T09:00:00Z']];
    egal('2026-10-26T08:00:00+01:00', creneau_lire($utc, 'utc')['debut']);
});

test('créneaux : un titre au format inattendu est gardé tel quel, sans UV', function () {
    foreach (['Réunion GE15', 'nf93 - td 1', 'NF93 – TD 1'] as $titre) {
        $c = creneau_lire(evenement_google($titre, '2026-10-14T10:00', '2026-10-14T11:00', 'FA400'), 'utc');
        egal([null, null, null, $titre], [$c['uv'], $c['type'], $c['salle'], $c['titre']], $titre);
    }
});

test("créneaux : dans l'agenda principal, un titre qui commence par « Médian » est un médian", function () {
    egal(true, creneau_lire(evenement_google('Médian MT23', '2026-10-21T19:15', '2026-10-21T20:45'), 'perso')['median']);
    egal(true, creneau_lire(evenement_google('median NF93', '2026-11-02T07:45', '2026-11-02T09:15'), 'perso')['median']);
    egal(false, creneau_lire(evenement_google('Réviser le médian', '2026-10-20T14:00', '2026-10-20T16:00'), 'perso')['median']);
    egal('perso', creneau_lire(evenement_google('Dentiste', '2026-10-20T14:00', '2026-10-20T15:00'), 'perso')['source']);
});

test('créneaux : les journées entières de l\'agenda UTC deviennent des bandeaux, sans casse ni accents', function () {
    egal(['type' => 'vacances', 'jour' => null, 'texte' => 'Vacances universitaires.'], bandeau_du_titre('Vacances universitaires'));
    egal(['type' => 'examen', 'jour' => null, 'texte' => "Jour d'examen."], bandeau_du_titre("Jour d'examen"));
    egal('examen', bandeau_du_titre('Jour d’examen')['type']);
    egal(['type' => 'ferie', 'jour' => null, 'texte' => "Jour férié à l'UTC : pas de cours."], bandeau_du_titre('Ferié'));
    egal('ferie', bandeau_du_titre('FÉRIÉ')['type']);
    egal(['type' => 'devient', 'jour' => 'jeudi', 'texte' => "Aujourd'hui, l'UTC suit l'emploi du temps du jeudi."], bandeau_du_titre('Devient un jeudi'));
    egal('samedi', bandeau_du_titre('devient un Samedi')['jour']);
    egal(null, bandeau_du_titre('Devient un jour'));
    egal(null, bandeau_du_titre('NF93 - TD 1'));
});

test("créneaux : jours_agenda range bandeaux et événements par jour, journées entières d'abord, puis par heure", function () {
    $utc = [
        evenement_google('TN01 - TD 8', '2026-10-14T16:30', '2026-10-14T18:30', 'FA510'),
        evenement_google('NF93 - TD 1', '2026-10-14T08:00', '2026-10-14T10:00', 'FA400'),
        evenement_google('Ferié', '2026-10-15', '2026-10-16'),
        evenement_google('Vacances universitaires', '2026-10-16', '2026-10-18'),
        evenement_google('Rentrée des associations', '2026-10-14', '2026-10-15'),
    ];
    $perso = [evenement_google('Médian MT23', '2026-10-16T19:15', '2026-10-16T20:45')];
    $jours = jours_agenda($utc, $perso, '2026-10-14', 4);
    egal(['2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17'], array_keys($jours));
    egal(['Rentrée des associations', 'NF93 - TD 1', 'TN01 - TD 8'], array_column($jours['2026-10-14']['evenements'], 'titre'));
    egal([], $jours['2026-10-14']['bandeaux']);
    egal(['ferie'], array_column($jours['2026-10-15']['bandeaux'], 'type'));
    egal(['vacances'], array_column($jours['2026-10-16']['bandeaux'], 'type'));
    egal(['vacances'], array_column($jours['2026-10-17']['bandeaux'], 'type'));
    egal([true], array_column($jours['2026-10-16']['evenements'], 'median'));
    egal([], $jours['2026-10-17']['evenements']);
});

test('créneaux : événements inattendus (annulé, sans titre, date illisible, hors période, sans fin) : ignorés ou gardés, jamais une erreur', function () {
    $annule = evenement_google('NF93 - TD 1', '2026-10-14T08:00', '2026-10-14T10:00') + [];
    $annule['status'] = 'cancelled';
    $illisible = ['summary' => 'Bizarre', 'start' => ['dateTime' => "n'importe quoi"], 'end' => ['dateTime' => 'non']];
    $sans_titre = evenement_google('', '2026-10-14T12:00', '2026-10-14T13:00');
    unset($sans_titre['summary']);
    $sans_fin = ['summary' => 'Appel', 'start' => ['dateTime' => '2026-10-14T14:00:00+02:00']];
    $loin = evenement_google('LA14 - TD 2', '2026-12-01T16:30', '2026-12-01T18:30');
    $jours = jours_agenda([$annule, $illisible, $loin], [$sans_titre, $sans_fin, ['pas un événement']], '2026-10-14', 1);
    egal(['Sans titre', 'Appel'], array_column($jours['2026-10-14']['evenements'], 'titre'));
    egal('2026-10-14T14:00:00+02:00', $jours['2026-10-14']['evenements'][1]['fin']);
});
