<?php
// Google Agenda : connexion OAuth avec PKCE, jetons chiffrés, lecture des 2 agendas (SPEC § 7).

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/chiffrement.php';
require_once __DIR__ . '/creneaux.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/reglages.php';

const JR_GOOGLE_AUTORISATION = 'https://accounts.google.com/o/oauth2/v2/auth';
const JR_GOOGLE_JETON = 'https://oauth2.googleapis.com/token';
const JR_GOOGLE_AGENDA = 'https://www.googleapis.com/calendar/v3';
const JR_GOOGLE_PORTEE = 'https://www.googleapis.com/auth/calendar.readonly';
const JR_GOOGLE_NOM_AGENDA_UTC = 'UTC · Emploi du temps A26';
const JR_GOOGLE_ATTENTE = 600;

// Jetons chiffrés (libsodium), gardés en base64 dans la colonne BLOB
function jeton_ecrire(string $service, string $clair, ?DateTimeImmutable $expire_le): void
{
    db()->prepare('INSERT INTO jetons (service, contenu, expire_le, maj_le) VALUES (?, ?, ?, ?) AS n
        ON DUPLICATE KEY UPDATE contenu = n.contenu, expire_le = n.expire_le, maj_le = n.maj_le')
        ->execute([$service, base64_encode(chiffrer($clair)), $expire_le === null ? null : utc($expire_le), utc(maintenant())]);
}

// Le jeton en clair, ou null s'il manque ou a expiré
function jeton_lire(string $service): ?string
{
    $requete = db()->prepare('SELECT contenu, expire_le FROM jetons WHERE service = ?');
    $requete->execute([$service]);
    $ligne = $requete->fetch();
    if ($ligne === false || ($ligne['expire_le'] !== null && depuis_utc($ligne['expire_le']) <= maintenant())) {
        return null;
    }
    $chiffre = base64_decode((string) $ligne['contenu'], true);
    return $chiffre === false ? null : dechiffrer($chiffre);
}

function jeton_effacer(string $service): void
{
    db()->prepare('DELETE FROM jetons WHERE service = ?')->execute([$service]);
}

function base64url(string $octets): string
{
    return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
}

// Page de consentement de Google. Le state et le vérificateur PKCE sont gardés chiffrés 10 min.
function google_url_connexion(): string
{
    $state = base64url(random_bytes(32));
    $verificateur = base64url(random_bytes(48));
    jeton_ecrire('google_oauth_attente', json_encode(['state' => $state, 'verificateur' => $verificateur], JSON_THROW_ON_ERROR), maintenant()->modify('+' . JR_GOOGLE_ATTENTE . ' seconds'));
    $g = config('google');
    return JR_GOOGLE_AUTORISATION . '?' . http_build_query([
        'client_id' => $g['client_id'],
        'redirect_uri' => $g['retour'],
        'response_type' => 'code',
        'scope' => JR_GOOGLE_PORTEE,
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state,
        'code_challenge' => base64url(hash('sha256', $verificateur, true)),
        'code_challenge_method' => 'S256',
    ], '', '&', PHP_QUERY_RFC3986);
}

// Retour de Google : state vérifié (usage unique), code échangé, jetons gardés, agendas retrouvés
function google_terminer_connexion(string $code, string $state): void
{
    $attente = jeton_lire('google_oauth_attente');
    jeton_effacer('google_oauth_attente');
    $attendu = $attente === null ? null : json_decode($attente, true);
    if (!is_array($attendu) || !is_string($attendu['state'] ?? null) || !hash_equals($attendu['state'], $state) || $code === '') {
        throw new ErreurSource('google_refuse', 'Google : state absent, expiré ou différent');
    }
    $g = config('google');
    $jetons = google_jeton_post([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => $g['client_id'],
        'client_secret' => $g['client_secret'],
        'redirect_uri' => $g['retour'],
        'code_verifier' => $attendu['verificateur'],
    ]);
    if (empty($jetons['refresh_token'])) {
        throw new ErreurSource('google_refuse', 'Google : pas de jeton longue durée');
    }
    jeton_ecrire('google', $jetons['refresh_token'], null);
    google_garder_acces($jetons);
    reglage_ecrire('agenda_calendriers', google_trouver_agendas());
}

// POST au point d'échange (formulaire). invalid_grant : accès révoqué, ou code périmé.
function google_jeton_post(array $champs): array
{
    try {
        $reponse = http_requete('POST', JR_GOOGLE_JETON, ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], http_build_query($champs, '', '&'));
    } catch (ErreurReseau $e) {
        throw new ErreurSource('google_indisponible', 'Google : ' . $e->getMessage());
    }
    $json = http_json($reponse);
    if ($reponse['statut'] === 200 && is_array($json) && is_string($json['access_token'] ?? null)) {
        return $json;
    }
    $code = is_array($json) && is_string($json['error'] ?? null) ? $json['error'] : '';
    if ($code === 'invalid_grant') {
        throw new ErreurSource('google_deconnecte', 'Google : invalid_grant', $reponse['statut']);
    }
    throw new ErreurSource('google_indisponible', "Google a répondu {$reponse['statut']} au jeton" . ($code !== '' ? " ($code)" : ''), $reponse['statut']);
}

function google_garder_acces(array $jetons): void
{
    $duree = max(60, (int) ($jetons['expires_in'] ?? 3600) - 60);
    jeton_ecrire('google_acces', $jetons['access_token'], maintenant()->modify("+$duree seconds"));
}

// Jeton d'accès valable, renouvelé si besoin. Accès révoqué : jetons effacés, ErreurSource google_deconnecte.
function google_jeton_acces(): string
{
    $acces = jeton_lire('google_acces');
    if ($acces !== null) {
        return $acces;
    }
    $longue = jeton_lire('google');
    if ($longue === null) {
        throw new ErreurSource('google_deconnecte', 'Google : pas connecté');
    }
    $g = config('google');
    try {
        $jetons = google_jeton_post(['grant_type' => 'refresh_token', 'refresh_token' => $longue, 'client_id' => $g['client_id'], 'client_secret' => $g['client_secret']]);
    } catch (ErreurSource $e) {
        if ($e->codeErreur === 'google_deconnecte') {
            jeton_effacer('google');
            jeton_effacer('google_acces');
        }
        throw $e;
    }
    google_garder_acces($jetons);
    return $jetons['access_token'];
}

function google_connecte(): bool
{
    return jeton_lire('google') !== null;
}

// Lecture dans l'API Agenda. Un jeton d'accès refusé avant son heure est jeté, et l'appel refait une fois.
function google_get(string $chemin, array $parametres): array
{
    for ($essai = 1; ; $essai++) {
        $url = JR_GOOGLE_AGENDA . $chemin . '?' . http_build_query($parametres, '', '&', PHP_QUERY_RFC3986);
        try {
            $reponse = http_requete('GET', $url, ['Authorization: Bearer ' . google_jeton_acces(), 'Accept: application/json']);
        } catch (ErreurReseau $e) {
            throw new ErreurSource('google_indisponible', 'Google : ' . $e->getMessage());
        }
        if ($reponse['statut'] === 401 && $essai === 1) {
            jeton_effacer('google_acces');
            continue;
        }
        $json = http_json($reponse);
        if ($reponse['statut'] !== 200 || !is_array($json)) {
            throw new ErreurSource('google_indisponible', "Google a répondu {$reponse['statut']} sur l'agenda", $reponse['statut']);
        }
        return $json;
    }
}

// Le principal, et « UTC · Emploi du temps A26 » retrouvé par son nom (sans casse ni accents). Absent : null.
function google_trouver_agendas(): array
{
    $liste = google_get('/users/me/calendarList', ['fields' => 'items(id,summary,primary)', 'maxResults' => 250]);
    $agendas = ['perso' => null, 'utc' => null];
    foreach ($liste['items'] ?? [] as $agenda) {
        if (!empty($agenda['primary'])) {
            $agendas['perso'] = (string) $agenda['id'];
        } elseif (normaliser_texte((string) ($agenda['summary'] ?? '')) === normaliser_texte(JR_GOOGLE_NOM_AGENDA_UTC)) {
            $agendas['utc'] = (string) $agenda['id'];
        }
    }
    return $agendas;
}

// Événements entre deux instants, répétitions dépliées, heure de Paris, 5 pages de 250 au plus
function google_evenements(string $agenda, DateTimeImmutable $debut, DateTimeImmutable $fin): array
{
    $parametres = [
        'singleEvents' => 'true',
        'orderBy' => 'startTime',
        'timeZone' => JR_FUSEAU,
        'timeMin' => $debut->format(DATE_RFC3339),
        'timeMax' => $fin->format(DATE_RFC3339),
        'maxResults' => 250,
        'fields' => 'items(id,status,summary,location,start,end),nextPageToken',
    ];
    $evenements = [];
    for ($page = 0; $page < 5; $page++) {
        $reponse = google_get('/calendars/' . rawurlencode($agenda) . '/events', $parametres);
        array_push($evenements, ...array_values($reponse['items'] ?? []));
        if (empty($reponse['nextPageToken'])) {
            break;
        }
        $parametres['pageToken'] = $reponse['nextPageToken'];
    }
    return $evenements;
}

// Les $nombre jours à partir de $premier (AAAA-MM-JJ, Paris), de minuit à minuit, lus dans les 2 agendas
function google_lire_jours(string $premier, int $nombre): array
{
    $agendas = reglage_lire('agenda_calendriers') ?? [];
    $debut = new DateTimeImmutable($premier, paris());
    $fin = $debut->modify("+$nombre days");
    $utc = ($agendas['utc'] ?? null) !== null ? google_evenements($agendas['utc'], $debut, $fin) : [];
    $perso = ($agendas['perso'] ?? null) !== null ? google_evenements($agendas['perso'], $debut, $fin) : [];
    return ['premier' => $premier, 'jours' => jours_agenda($utc, $perso, $premier, $nombre)];
}
