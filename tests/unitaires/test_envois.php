<?php
declare(strict_types=1);

function rappel_envoi(string $cle, string $prevu_pour, array $surcharges = []): array
{
    return array_replace(['cle' => $cle, 'type' => 'manuel', 'prevu_pour' => utc_test($prevu_pour), 'titre' => 'Titre', 'texte' => 'Texte', 'url' => '/#/aujourdhui'], $surcharges);
}

function statuts_envois(): array
{
    return db()->query('SELECT cle, statut FROM envois ORDER BY cle')->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Bilan de envoyer_dus() : comparé strictement, l'ordre des clés compte, d'où array_replace()
const BILAN_VIDE = ['envoyees' => 0, 'perimees' => 0, 'echouees' => 0, 'reportees' => 0];

function bilan_envois(array $valeurs): array
{
    return array_replace(BILAN_VIDE, $valeurs);
}

test("envois : enregistrés une fois par clé ; une prévue suit le nouveau texte et la nouvelle heure, une envoyée ne bouge plus", function () {
    base_de_test();
    $liste = [rappel_envoi('manuel:1', '2026-10-14 08:00:00'), rappel_envoi('manuel:2', '2026-10-14 09:00:00')];
    envois_enregistrer($liste);
    envois_enregistrer($liste);
    egal(['manuel:1' => 'prevu', 'manuel:2' => 'prevu'], statuts_envois());
    db()->exec("UPDATE envois SET statut = 'envoye' WHERE cle = 'manuel:1'");
    envois_enregistrer([rappel_envoi('manuel:1', '2026-10-14 08:30:00', ['titre' => 'Nouveau']), rappel_envoi('manuel:2', '2026-10-14 09:30:00', ['titre' => 'Nouveau'])]);
    egal([['manuel:1', '2026-10-14 08:00:00', 'Titre'], ['manuel:2', '2026-10-14 09:30:00', 'Nouveau']],
        db()->query('SELECT cle, prevu_pour, titre FROM envois ORDER BY cle')->fetchAll(PDO::FETCH_NUM));
});

test("envois : une prévue absente de la liste est retirée, et redevient prévue si elle réapparaît ; une envoyée reste envoyée", function () {
    base_de_test();
    envois_enregistrer([rappel_envoi('a', '2026-10-14 08:00:00'), rappel_envoi('b', '2026-10-14 09:00:00'), rappel_envoi('c', '2026-10-14 10:00:00')]);
    db()->exec("UPDATE envois SET statut = 'envoye' WHERE cle = 'c'");
    envois_enregistrer([rappel_envoi('a', '2026-10-14 08:00:00')]);
    egal(['a' => 'prevu', 'b' => 'retire', 'c' => 'envoye'], statuts_envois());
    envois_enregistrer([rappel_envoi('a', '2026-10-14 08:00:00'), rappel_envoi('b', '2026-10-14 09:15:00')]);
    egal(['a' => 'prevu', 'b' => 'prevu', 'c' => 'envoye'], statuts_envois());
    egal('2026-10-14 09:15:00', db()->query("SELECT prevu_pour FROM envois WHERE cle = 'b'")->fetchColumn());
    envois_enregistrer([]);
    egal(['a' => 'retire', 'b' => 'retire', 'c' => 'envoye'], statuts_envois());
});

test("envois : seules les dues partent, une fois, la plus ancienne d'abord, avec TTL et urgence selon le type", function () {
    base_de_test();
    fixer_heure('2026-10-14 06:50:00');
    abonnement_enregistrer(abonnement_exemple(), null);
    envois_enregistrer([
        rappel_envoi('manuel:1', '2026-10-14 06:50:00'),
        rappel_envoi('cours:x', '2026-10-14 06:45:00', ['type' => 'cours']),
        rappel_envoi('manuel:2', '2026-10-14 06:51:00'),
    ]);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    egal(bilan_envois(['envoyees' => 2]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    $entetes = array_map(fn(array $a): string => implode("\n", $a['entetes']), $GLOBALS['JR_APPELS_HTTP']);
    vrai(str_contains($entetes[0], 'TTL: 900') && str_contains($entetes[0], 'Urgency: high'), 'cours en premier : TTL 900, urgence haute');
    vrai(str_contains($entetes[1], 'TTL: 43200') && str_contains($entetes[1], 'Urgency: normal'), 'rappel : TTL 12 h, urgence normale');
    egal(['cours:x' => 'envoye', 'manuel:1' => 'envoye', 'manuel:2' => 'prevu'], statuts_envois());
    egal('2026-10-14 06:50:00', db()->query("SELECT envoye_le FROM envois WHERE cle = 'cours:x'")->fetchColumn());
    egal(bilan_envois([]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    egal(2, count($GLOBALS['JR_APPELS_HTTP']));
});

test('envois : trop en retard, une notification est périmée sans partir (cours 10 min, échéance 3 h, brief 2 h, rappel 12 h)', function () {
    base_de_test();
    fixer_heure('2026-10-14 12:00:00');
    abonnement_enregistrer(abonnement_exemple(), null);
    envois_enregistrer([
        rappel_envoi('cours:vieux', '2026-10-14 11:49:59', ['type' => 'cours']),
        rappel_envoi('cours:juste', '2026-10-14 11:50:00', ['type' => 'cours']),
        rappel_envoi('echeance:vieille', '2026-10-14 08:59:59', ['type' => 'echeance']),
        rappel_envoi('brief:vieux', '2026-10-14 09:59:59', ['type' => 'brief']),
        rappel_envoi('brief:juste', '2026-10-14 10:00:00', ['type' => 'brief']),
        rappel_envoi('manuel:vieux', '2026-10-13 23:59:59'),
    ]);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 201, 'corps' => '']]);
    egal(bilan_envois(['envoyees' => 2, 'perimees' => 4]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    egal(['brief:juste' => 'envoye', 'brief:vieux' => 'perime', 'cours:juste' => 'envoye', 'cours:vieux' => 'perime', 'echeance:vieille' => 'perime', 'manuel:vieux' => 'perime'], statuts_envois());
});

test("envois : un 503 est réessayé aux passages suivants, 3 essais au plus ; un 403 échoue tout de suite ; sans abonnement, rien à envoyer", function () {
    base_de_test();
    fixer_heure('2026-10-14 12:00:00');
    envois_enregistrer([rappel_envoi('manuel:personne', '2026-10-14 12:00:00')]);
    faux_http([]);
    egal(bilan_envois(['envoyees' => 1]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    abonnement_enregistrer(abonnement_exemple(), null);
    envois_enregistrer([rappel_envoi('manuel:1', '2026-10-14 12:00:00')]);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 503, 'corps' => '']]);
    egal(bilan_envois(['reportees' => 1]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    egal(bilan_envois(['reportees' => 1]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    egal(bilan_envois(['echouees' => 1]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
    $l = db()->query("SELECT statut, essais FROM envois WHERE cle = 'manuel:1'")->fetch();
    egal(['echoue', 3], [$l['statut'], (int) $l['essais']]);
    envois_enregistrer([rappel_envoi('manuel:2', '2026-10-14 12:00:00')]);
    faux_http(['POST https://web.push.apple.com/' => ['statut' => 403, 'corps' => '']]);
    egal(bilan_envois(['echouees' => 1]), envoyer_dus(maintenant(), PHP_FLOAT_MAX));
});

test("envois : rien ne part une fois la limite de temps passée ; la purge efface au-delà de 30 jours", function () {
    base_de_test();
    fixer_heure('2026-10-14 12:00:00');
    abonnement_enregistrer(abonnement_exemple(), null);
    envois_enregistrer([rappel_envoi('manuel:1', '2026-10-14 12:00:00'), rappel_envoi('manuel:ancien', '2026-09-14 11:59:00')]);
    faux_http([]);
    egal(0, envoyer_dus(maintenant(), 0.0)['envoyees']);
    egal('prevu', statuts_envois()['manuel:1']);
    envois_purger(maintenant());
    egal(['manuel:1'], array_keys(statuts_envois()));
});
