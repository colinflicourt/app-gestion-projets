<?php
declare(strict_types=1);

const JETON_GOOGLE = 'POST https://oauth2.googleapis.com/token';
const LISTE_AGENDAS = 'GET https://www.googleapis.com/calendar/v3/users/me/calendarList';

function reponse_calendar_list(): array
{
    return ['json' => ['items' => [
        ['id' => 'colin@exemple.fr', 'summary' => 'colin@exemple.fr', 'primary' => true],
        ['id' => 'utc-a26@group.calendar.google.com', 'summary' => 'UTC · Emploi du temps A26'],
        ['id' => 'fr.french#holiday@group.v.calendar.google.com', 'summary' => 'Jours fériés et autres fêtes en France'],
    ]]];
}

test('Google : réglages et jetons, chiffrés en base, effaçables, expirés vus comme absents', function () {
    $pdo = base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    reglage_ecrire('essai', ['a' => 1]);
    egal(['a' => 1], reglage_lire('essai'));
    egal(null, reglage_lire('absent'));
    jeton_ecrire('google', 'secret-tres-long', null);
    jeton_ecrire('google_acces', 'acces-court', maintenant()->modify('+10 minutes'));
    egal('secret-tres-long', jeton_lire('google'));
    $brut = (string) $pdo->query("SELECT contenu FROM jetons WHERE service = 'google'")->fetchColumn();
    vrai(!str_contains($brut, 'secret-tres-long') && !str_contains((string) base64_decode($brut), 'secret-tres-long'), 'jeton chiffré attendu');
    fixer_heure('2026-10-09 10:10:00');
    egal(null, jeton_lire('google_acces'));
    jeton_effacer('google');
    egal(null, jeton_lire('google'));
});

test("Google : l'adresse de connexion demande calendar.readonly, hors ligne, avec consentement, state et PKCE S256", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    $url = google_url_connexion();
    vrai(str_starts_with($url, 'https://accounts.google.com/o/oauth2/v2/auth?'), 'adresse de Google attendue');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $p);
    egal(['client-de-test', 'http://localhost:8787/api/google-callback.php', 'code', 'https://www.googleapis.com/auth/calendar.readonly', 'offline', 'consent', 'S256'],
        [$p['client_id'], $p['redirect_uri'], $p['response_type'], $p['scope'], $p['access_type'], $p['prompt'], $p['code_challenge_method']]);
    vrai((bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $p['state']), 'state de 32 octets attendu');
    vrai((bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $p['code_challenge']), 'défi PKCE attendu');
    $attente = json_decode((string) jeton_lire('google_oauth_attente'), true);
    egal($p['state'], $attente['state']);
    egal($p['code_challenge'], base64url(hash('sha256', $attente['verificateur'], true)));
    fixer_heure('2026-10-09 10:10:00');
    egal(null, jeton_lire('google_oauth_attente'));
});

test('Google : le retour vérifie le state, échange le code avec le vérificateur, garde les jetons chiffrés et retrouve les 2 agendas', function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    parse_str((string) parse_url(google_url_connexion(), PHP_URL_QUERY), $p);
    faux_http([
        JETON_GOOGLE => ['json' => ['access_token' => 'acces-1', 'expires_in' => 3599, 'refresh_token' => 'longue-1', 'scope' => JR_GOOGLE_PORTEE, 'token_type' => 'Bearer']],
        LISTE_AGENDAS => reponse_calendar_list(),
    ]);
    google_terminer_connexion('code-de-google', $p['state']);
    parse_str((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], $echange);
    egal(['authorization_code', 'code-de-google', 'client-de-test', 'secret-de-test', 'http://localhost:8787/api/google-callback.php'],
        [$echange['grant_type'], $echange['code'], $echange['client_id'], $echange['client_secret'], $echange['redirect_uri']]);
    egal($p['code_challenge'], base64url(hash('sha256', $echange['code_verifier'], true)));
    vrai(in_array('Authorization: Bearer acces-1', $GLOBALS['JR_APPELS_HTTP'][1]['entetes'], true), "jeton d'accès attendu pour la liste des agendas");
    egal('longue-1', jeton_lire('google'));
    egal('acces-1', jeton_lire('google_acces'));
    egal(null, jeton_lire('google_oauth_attente'));
    $agendas = reglage_lire('agenda_calendriers');
    ksort($agendas);
    egal(['perso' => 'colin@exemple.fr', 'utc' => 'utc-a26@group.calendar.google.com'], $agendas);
    egal(true, google_connecte());
});

test('Google : un state faux, expiré ou déjà servi est refusé sans appeler Google', function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    parse_str((string) parse_url(google_url_connexion(), PHP_URL_QUERY), $p);
    egal('google_refuse', leve(ErreurSource::class, fn() => google_terminer_connexion('c', 'pas-le-bon'))->codeErreur);
    egal('google_refuse', leve(ErreurSource::class, fn() => google_terminer_connexion('c', $p['state']))->codeErreur, 'usage unique');
    parse_str((string) parse_url(google_url_connexion(), PHP_URL_QUERY), $p);
    fixer_heure('2026-10-09 10:10:01');
    egal('google_refuse', leve(ErreurSource::class, fn() => google_terminer_connexion('c', $p['state']))->codeErreur, 'expiré');
    egal([], appels_http());
});

test("Google : un jeton d'accès expiré est renouvelé avec le jeton longue durée ; invalid_grant efface tout", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    google_connecte_pour_test();
    egal('jeton-acces-de-test', google_jeton_acces());
    fixer_heure('2026-10-09 11:00:01');
    faux_http([JETON_GOOGLE => ['json' => ['access_token' => 'acces-2', 'expires_in' => 3599]]]);
    egal('acces-2', google_jeton_acces());
    egal('acces-2', google_jeton_acces());
    egal(1, count($GLOBALS['JR_APPELS_HTTP']));
    parse_str((string) $GLOBALS['JR_APPELS_HTTP'][0]['corps'], $champs);
    egal(['refresh_token', 'jeton-longue-duree-de-test', 'client-de-test', 'secret-de-test'], [$champs['grant_type'], $champs['refresh_token'], $champs['client_id'], $champs['client_secret']]);
    fixer_heure('2026-10-09 12:00:00');
    faux_http([JETON_GOOGLE => ['statut' => 400, 'json' => ['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.']]]);
    egal('google_deconnecte', leve(ErreurSource::class, fn() => google_jeton_acces())->codeErreur);
    egal(false, google_connecte());
    egal(null, jeton_lire('google_acces'));
    faux_http([]);
    egal('google_deconnecte', leve(ErreurSource::class, fn() => google_jeton_acces())->codeErreur, 'sans jeton, pas d\'appel');
});

test('Google : panne réseau ou erreur 5xx au renouvellement donnent google_indisponible, sans effacer les jetons', function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    google_connecte_pour_test();
    fixer_heure('2026-10-09 11:30:00');
    foreach ([new ErreurReseau('délai dépassé'), ['statut' => 503, 'corps' => 'indisponible']] as $reponse) {
        faux_http([JETON_GOOGLE => $reponse]);
        egal('google_indisponible', leve(ErreurSource::class, fn() => google_jeton_acces())->codeErreur);
    }
    egal(true, google_connecte());
});

test("Google : événements lus page par page, heure de Paris, occurrences dépliées, adresse de l'agenda encodée, jeton dans l'en-tête", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    google_connecte_pour_test();
    faux_http(['GET https://www.googleapis.com/calendar/v3/calendars/fr.french%23holiday%40group.v.calendar.google.com/events' => [
        ['json' => ['items' => [evenement_google('A', '2026-10-14T08:00', '2026-10-14T09:00')], 'nextPageToken' => 'page-2']],
        ['json' => ['items' => [evenement_google('B', '2026-10-14T10:00', '2026-10-14T11:00')]]],
    ]]);
    $evenements = google_evenements('fr.french#holiday@group.v.calendar.google.com', new DateTimeImmutable('2026-10-14', paris()), new DateTimeImmutable('2026-10-15', paris()));
    egal(['A', 'B'], array_column($evenements, 'summary'));
    parse_str((string) parse_url($GLOBALS['JR_APPELS_HTTP'][1]['url'], PHP_URL_QUERY), $p);
    egal(['true', 'startTime', 'Europe/Paris', 'page-2', '2026-10-14T00:00:00+02:00', '2026-10-15T00:00:00+02:00'],
        [$p['singleEvents'], $p['orderBy'], $p['timeZone'], $p['pageToken'], $p['timeMin'], $p['timeMax']]);
    vrai(in_array('Authorization: Bearer jeton-acces-de-test', $GLOBALS['JR_APPELS_HTTP'][0]['entetes'], true), "jeton attendu dans l'en-tête");
    vrai(!str_contains($GLOBALS['JR_APPELS_HTTP'][0]['url'], 'jeton'), "jamais de jeton dans l'adresse");
});

test("Google : un jeton d'accès refusé avant son heure (401) est renouvelé une fois, puis la lecture reprend", function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    google_connecte_pour_test();
    faux_http([
        'GET https://www.googleapis.com/calendar/v3/calendars/' => [['statut' => 401, 'json' => ['error' => ['code' => 401]]], ['json' => ['items' => [evenement_google('NF93 - TD 1', '2026-10-14T08:00', '2026-10-14T10:00', 'FA400')]]]],
        JETON_GOOGLE => ['json' => ['access_token' => 'acces-2', 'expires_in' => 3599]],
    ]);
    $evenements = google_evenements('utc-a26@group.calendar.google.com', new DateTimeImmutable('2026-10-14', paris()), new DateTimeImmutable('2026-10-15', paris()));
    egal(1, count($evenements));
    egal(['GET', 'POST', 'GET'], array_column($GLOBALS['JR_APPELS_HTTP'], 'methode'));
    vrai(in_array('Authorization: Bearer acces-2', $GLOBALS['JR_APPELS_HTTP'][2]['entetes'], true), 'nouveau jeton attendu');
});

test('Google : les routes de connexion redirigent vers Google, puis vers Moi avec « connecte » ou « erreur »', function () {
    base_de_test();
    fixer_heure('2026-10-09 10:00:00');
    $depart = appeler('GET', 'google/connexion');
    egal(302, $depart['statut']);
    vrai(str_starts_with($depart['entetes']['Location'], 'https://accounts.google.com/o/oauth2/v2/auth?'), 'redirection vers Google attendue');
    parse_str((string) parse_url($depart['entetes']['Location'], PHP_URL_QUERY), $p);
    $refus = appeler('GET', 'google-callback.php', [], ['error' => 'access_denied', 'state' => $p['state']]);
    egal('http://localhost:8787/#/moi?google=erreur', $refus['entetes']['Location']);
    parse_str((string) parse_url(appeler('GET', 'google/connexion')['entetes']['Location'], PHP_URL_QUERY), $p);
    egal('http://localhost:8787/#/moi?google=erreur', appeler('GET', 'google-callback.php', [], ['code' => 'c', 'state' => 'faux'])['entetes']['Location']);
    egal('http://localhost:8787/#/moi?google=erreur', appeler('GET', 'google-callback.php', [], ['code' => ['c'], 'state' => $p['state']])['entetes']['Location']);
    parse_str((string) parse_url(appeler('GET', 'google/connexion')['entetes']['Location'], PHP_URL_QUERY), $p);
    faux_http([JETON_GOOGLE => ['json' => ['access_token' => 'acces-1', 'expires_in' => 3599, 'refresh_token' => 'longue-1']], LISTE_AGENDAS => reponse_calendar_list()]);
    egal('http://localhost:8787/#/moi?google=connecte', appeler('GET', 'google-callback.php', [], ['code' => 'c', 'state' => $p['state']])['entetes']['Location']);
    egal(true, google_connecte());
});
