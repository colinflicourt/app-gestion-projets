<?php
// Aides des tests du client IMAP : réponses enregistrées, faux serveur local, scénario standard.

declare(strict_types=1);

// Réponse enregistrée (tests/fixtures/imap/) → octets tels que le serveur les enverrait.
// Fins de ligne LF → CRLF ; « <<<\n…\n>>> » est un littéral {n} dont le contenu finit par CRLF (blocs d'en-têtes) ;
// « [[[…]]] » est un littéral {n} sans CRLF final (objet, extrait).
function fixture_imap(string $nom): string
{
    $texte = file_get_contents(dirname(__DIR__) . '/fixtures/imap/' . $nom);
    if ($texte === false) {
        throw new EchecTest("Fixture IMAP introuvable : $nom");
    }
    $parties = preg_split('/(<<<\n.*?\n>>>|\[\[\[.*?\]\]\])/s', $texte, -1, PREG_SPLIT_DELIM_CAPTURE);
    $sortie = '';
    foreach ($parties as $i => $partie) {
        if ($i % 2 === 0) {
            $sortie .= str_replace("\n", "\r\n", $partie);
            continue;
        }
        $avec_fin = str_starts_with($partie, '<<<');
        $contenu = $avec_fin ? substr($partie, 4, -4) : substr($partie, 3, -3);
        $contenu = str_replace("\n", "\r\n", $contenu) . ($avec_fin ? "\r\n" : '');
        $sortie .= '{' . strlen($contenu) . "}\r\n" . $contenu;
    }
    return $sortie;
}

function port_libre(): int
{
    $s = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr((string) stream_socket_get_name($s, false), ':'), 1);
    fclose($s);
    return $port;
}

// Scénario d'une boîte à 5 mails (UID 101 à 105) ; les clés de $surcharges remplacent celles du scénario.
// Les règles de la clé « regles_avant » passent avant les règles standard.
function scenario_imap_standard(array $surcharges = []): array
{
    $entetes = fixture_imap('fetch-101-texte-simple.txt') . fixture_imap('fetch-102-alternative.txt')
        . fixture_imap('fetch-103-mixte-piece-jointe.txt') . fixture_imap('fetch-104-html-seul.txt') . fixture_imap('fetch-105-vide.txt');
    $avant = $surcharges['regles_avant'] ?? [];
    unset($surcharges['regles_avant']);
    return array_replace([
        'duree_max' => 25,
        'accueil' => "* OK [CAPABILITY IMAP4rev1] Serveur de test pret\r\n",
        'identifiants' => ['colin.test@exemple.org', 'phrase de test assez longue'],
        'regles' => array_merge($avant, [
            ['motif' => '/\ACAPABILITY\z/', 'reponse' => "* CAPABILITY IMAP4rev1 X-GM-EXT-1 UIDPLUS\r\n{ETIQUETTE} OK Fait\r\n"],
            ['motif' => '/\AEXAMINE "INBOX"\z/', 'reponse' => "* 5 EXISTS\r\n* OK [UIDVALIDITY 424242] UIDs valides\r\n* OK [READ-ONLY] ok\r\n{ETIQUETTE} OK [READ-ONLY] EXAMINE fait\r\n"],
            ['motif' => '/\AUID SEARCH UNSEEN\z/', 'reponse' => "* SEARCH 101 103\r\n{ETIQUETTE} OK Fait\r\n"],
            ['motif' => '/\AUID SEARCH ALL\z/', 'reponse' => "* SEARCH 101 102 103 104 105\r\n{ETIQUETTE} OK Fait\r\n"],
            ['motif' => '/\AUID FETCH [\d:,]+ \(UID FLAGS INTERNALDATE ENVELOPE BODYSTRUCTURE/', 'reponse' => $entetes . "{ETIQUETTE} OK Fait\r\n"],
            ['motif' => '/\AUID FETCH [\d:,]+ \(UID BODY\.PEEK\[1\]<0\.2000>\)\z/', 'reponse' => fixture_imap('corps-101-1.txt') . fixture_imap('corps-102-1.txt') . fixture_imap('corps-104-1.txt') . "{ETIQUETTE} OK Fait\r\n"],
            ['motif' => '/\AUID FETCH [\d:,]+ \(UID BODY\.PEEK\[1\.1\]<0\.2000>\)\z/', 'reponse' => fixture_imap('corps-103-1.1.txt') . "{ETIQUETTE} OK Fait\r\n"],
            ['motif' => '/\ALOGOUT\z/', 'reponse' => "* BYE A bientot\r\n{ETIQUETTE} OK Deconnecte\r\n"],
        ]),
    ], $surcharges);
}

// Lance le faux serveur, appelle $f($adresse, $commandes) puis l'arrête, même si $f échoue.
// $commandes() rend la liste des commandes reçues (littéraux compris, sans étiquette).
function avec_faux_imap(array $scenario, callable $f): mixed
{
    $dossier = sys_get_temp_dir() . '/jr-faux-imap-' . getmypid() . '-' . bin2hex(random_bytes(4));
    mkdir($dossier);
    $scenario['port_fichier'] = "$dossier/port";
    $scenario['journal'] = "$dossier/commandes.jsonl";
    file_put_contents($scenario['journal'], '');
    file_put_contents("$dossier/scenario.ser", serialize($scenario));
    $serveur = proc_open(
        [PHP_BINARY, __DIR__ . '/faux-serveur-imap.php', "$dossier/scenario.ser"],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', "$dossier/sortie.txt", 'w'], 2 => ['file', "$dossier/sortie.txt", 'w']],
        $tubes
    );
    try {
        for ($i = 0; $i < 200 && !is_file($scenario['port_fichier']); $i++) {
            usleep(25000);
        }
        if (!is_file($scenario['port_fichier'])) {
            throw new EchecTest('Le faux serveur IMAP ne démarre pas : ' . trim((string) @file_get_contents("$dossier/sortie.txt")));
        }
        $port = (int) file_get_contents($scenario['port_fichier']);
        $schema = isset($scenario['tls_pem']) ? 'tls' : 'tcp';
        $commandes = fn(): array => array_map(
            fn(string $l): string => json_decode($l, true),
            array_values(array_filter(explode("\n", (string) file_get_contents($scenario['journal']))))
        );
        return $f("$schema://127.0.0.1:$port", $commandes);
    } finally {
        $pid = (int) proc_get_status($serveur)['pid'];
        proc_terminate($serveur);
        proc_close($serveur);
        if ($pid > 0) {
            @posix_kill($pid, SIGKILL);
        }
        supprimer_dossier($dossier);
    }
}

// Le journal d'erreurs de l'app (écrit par journal_erreur) : texte, ou '' s'il n'existe pas
function journal_texte(): string
{
    $fichier = dossier_data() . '/erreurs.log';
    return is_file($fichier) ? (string) file_get_contents($fichier) : '';
}
