<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/outils/aide-imap.php';

const IMAP_ID_TEST = 'colin.test@exemple.org';
const IMAP_MDP_TEST = 'phrase de test assez longue';

// Les 5 mails du scénario standard, avec leurs extraits, du plus récent au plus ancien
function mails_attendus_standard(bool $gmail = true): array
{
    $extraits = [
        101 => "Bonjour Colin, On se retrouve demain à 9 h pour la réunion de l'équipe. Pense à apporter tes notes. -- Camille",
        102 => 'Profitez de -20 % sur toute la boutique jusqu’à dimanche. Désabonnement possible en bas de page.',
        103 => 'Voilà la facture de septembre, merci de votre confiance.',
        104 => 'Bonjour Colin, Votre colis est en route & arrivera demain.',
        105 => '',
    ];
    $mails = [];
    foreach ([101 => 'fetch-101-texte-simple.txt', 102 => 'fetch-102-alternative.txt', 103 => 'fetch-103-mixte-piece-jointe.txt',
        104 => 'fetch-104-html-seul.txt', 105 => 'fetch-105-vide.txt'] as $uid => $fixture) {
        $elements = imap_reponse_fetch(rtrim(fixture_imap($fixture), "\r\n"));
        [$brut] = imap_brut_depuis_fetch($elements);
        $brut['extrait'] = $extraits[$uid];
        $mails[] = $brut;
    }
    return $mails;
}

function lire_chez(string $adresse, string $compte = 'gmail', float $duree = 20.0, string $mdp = IMAP_MDP_TEST, string $id = IMAP_ID_TEST): array
{
    $GLOBALS['JR_IMAP_ADRESSE'] = $adresse;
    return imap_lire_boite($compte, $id, $mdp, microtime(true) + $duree);
}

function code_de(callable $f): string
{
    $e = leve(ErreurSource::class, $f);
    return $e->codeErreur;
}

// Une connexion branchée sur une paire de sockets locale : [connexion, bout du « serveur »]
function connexion_de_paire(float $duree = 5.0): array
{
    [$client, $serveur] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    return [new ConnexionImap($client, microtime(true) + $duree), $serveur];
}

test('IMAP liste blanche : seules CAPABILITY, LOGIN, EXAMINE "INBOX", UID SEARCH, UID FETCH avec PEEK et LOGOUT sont permises', function () {
    foreach ([
        'CAPABILITY', 'LOGOUT', 'EXAMINE "INBOX"', 'UID SEARCH UNSEEN', 'UID SEARCH ALL',
        'LOGIN "alice" "mot de passe"', 'LOGIN {5}' . "\r\n" . 'alice "mdp"', 'LOGIN "alice" {8}' . "\r\n" . 'mot"\\ é', 'LOGIN {5}' . "\r\n" . 'alice {3}' . "\r\n" . 'a b',
        'UID FETCH 101 (UID FLAGS)',
        'UID FETCH 101:105,107 (UID FLAGS INTERNALDATE ENVELOPE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (LIST-UNSUBSCRIBE LIST-ID PRECEDENCE RECEIVED TO CC DELIVERED-TO X-FORWARDED-TO)] X-GM-MSGID)',
        'UID FETCH 101 (UID BODY.PEEK[1]<0.2000>)', 'UID FETCH 101,103 (UID BODY.PEEK[1.1]<0.2000>)', 'UID FETCH 7 (BODY.PEEK[2.1.3])',
    ] as $commande) {
        vrai(imap_commande_autorisee($commande), 'devrait être permise : ' . json_encode($commande));
    }
});

test('IMAP liste blanche : SELECT, STORE, COPY, EXPUNGE, UID STORE, FETCH sans PEEK, EXAMINE d\'une autre boîte et toute variante sont refusés', function () {
    foreach ([
        'SELECT "INBOX"', 'STORE 1 +FLAGS (\\Deleted)', 'UID STORE 101 +FLAGS (\\Seen)', 'COPY 1 "Corbeille"', 'UID COPY 1 "Corbeille"', 'EXPUNGE', 'UID EXPUNGE 1',
        'EXAMINE "Spam"', 'EXAMINE "INBOX" ', 'examine "INBOX"', 'EXAMINE INBOX', 'STATUS "INBOX" (UNSEEN)', 'APPEND "INBOX" {1}', 'DELETE "INBOX"', 'RENAME "a" "b"',
        'IDLE', 'NOOP', 'LIST "" "*"', 'LSUB "" "*"', 'CREATE "x"', 'CLOSE', 'LOGOUT now', 'CAPABILITY ', "CAPABILITY\r\nSTORE 1 +FLAGS (\\Deleted)", '',
        'FETCH 1 (BODY.PEEK[1])', 'FETCH 1 (BODY[1])', 'UID FETCH 101 (BODY[1])', 'UID FETCH 101 (BODY[1]<0.2000>)', 'UID FETCH 101 (BODY[])', 'UID FETCH 101 (BODY.PEEK[])',
        'UID FETCH 101 (RFC822)', 'UID FETCH 101 (RFC822.TEXT)', 'UID FETCH 101 (BODY[HEADER])', 'UID FETCH 101 (BODY.PEEK[TEXT])', 'UID FETCH 101 (UID BODY[1])',
        'UID FETCH 101 (UID FLAGS) ', "UID FETCH 101 (UID)\r\nA9 STORE 1 FLAGS (\\Seen)", 'UID FETCH 101 UID', 'UID FETCH * (UID)', 'UID FETCH 1 (UID FLAGS) (BODY[1])',
        'UID SEARCH', 'UID SEARCH DELETED', 'UID SEARCH ALL ', 'SEARCH UNSEEN', 'UID SEARCH UNSEEN ALL',
        'LOGIN alice mdp', 'LOGIN "alice"', 'LOGIN "alice" "mdp" "x"', "LOGIN \"alice\" \"mdp\"\r\nA2 STORE 1 FLAGS (\\Seen)", "LOGIN \"al\"ice\" \"mdp\"", 'LOGIN {5}' . "\r\n" . 'ali',
        'LOGIN "alice" "mot\\"de passe"',
    ] as $commande) {
        vrai(!imap_commande_autorisee($commande), 'devrait être refusée : ' . json_encode($commande));
    }
});

test('IMAP : une commande interdite lève LogicException AVANT d\'écrire sur la socket, sans citer la commande', function () {
    [$c, $serveur] = connexion_de_paire();
    stream_set_blocking($serveur, false);
    foreach (['STORE 1 +FLAGS (\\Deleted)', 'SELECT "INBOX"', 'UID FETCH 101 (BODY[1])', "LOGIN \"alice\" \"secret-123\"\r\nSTORE 1 FLAGS (\\Seen)", 'EXPUNGE'] as $commande) {
        $e = leve(LogicException::class, fn() => imap_envoyer($c, $commande));
        vrai(!str_contains($e->getMessage(), 'secret-123') && !str_contains($e->getMessage(), '\\Deleted'), 'le message ne doit citer que le verbe : ' . $e->getMessage());
        egal('', (string) fread($serveur, 4096), 'rien ne doit être parti vers le serveur');
    }
    egal(0, $c->numero, "aucune étiquette n'a été consommée");
    fclose($c->prise);
    fclose($serveur);
});

test('IMAP : une commande permise part avec son étiquette, LOGIN à littéraux attend la continuation du serveur', function () {
    [$c, $serveur] = connexion_de_paire();
    fwrite($serveur, "* CAPABILITY IMAP4rev1\r\nJ1 OK fait\r\n");
    egal('OK', imap_envoyer($c, 'CAPABILITY')['statut']);
    egal("J1 CAPABILITY\r\n", fread($serveur, 4096));
    fwrite($serveur, "+ pret\r\n+ pret\r\nJ2 OK connecte\r\n");
    egal('OK', imap_envoyer($c, "LOGIN {5}\r\nalice {4}\r\nm\"d\\")['statut']);
    egal("J2 LOGIN {5}\r\nalice {4}\r\nm\"d\\\r\n", fread($serveur, 4096));
    fclose($c->prise);
    fclose($serveur);
});

test('IMAP lecture : réponses découpées en morceaux de 3 octets (littéraux coupés, lignes non sollicitées au milieu)', function () {
    [$c, $serveur] = connexion_de_paire();
    $c->taille_lecture = 3;
    $reponse = "* 5 EXISTS\r\n" . rtrim(fixture_imap('fetch-101-texte-simple.txt'), "\r\n") . "\r\n* 2 FETCH (FLAGS (\\Seen))\r\n"
        . "* SEARCH 4 7\r\nJ1 OK Fait [CAPABILITY X-GM-EXT-1]\r\n";
    fwrite($serveur, $reponse);
    $r = imap_lire_reponse($c, 'J1');
    egal('OK', $r['statut']);
    egal('Fait [CAPABILITY X-GM-EXT-1]', $r['texte']);
    egal(4, count($r['non_sollicitees']));
    egal('* 5 EXISTS', $r['non_sollicitees'][0]);
    $fetch = imap_reponse_fetch($r['non_sollicitees'][1]);
    egal('101', $fetch['UID']);
    vrai(str_contains($fetch['HEADER'], 'relais.etu.utc.fr'), "l'en-tête littéral doit être recollé entier");
    egal(['X-GM-EXT-1'], imap_capacites($r));
    fclose($c->prise);
    fclose($serveur);
});

test('IMAP lecture : le serveur qui ferme la connexion donne injoignable, le serveur muet donne delai', function () {
    [$c, $serveur] = connexion_de_paire(5.0);
    fclose($serveur);
    egal('injoignable', code_de(fn() => imap_ligne($c)));
    fclose($c->prise);
    [$c, $serveur] = connexion_de_paire(0.4);
    $debut = microtime(true);
    egal('delai', code_de(fn() => imap_ligne($c)));
    vrai(microtime(true) - $debut < 1.5, 'le délai doit être respecté');
    fclose($c->prise);
    fclose($serveur);
});

test('IMAP : serveurs réels connus, TLS sur le port 993, compte inconnu refusé', function () {
    egal('tls://imap.gmail.com:993', imap_adresse('gmail'));
    egal('tls://imap.mail.me.com:993', imap_adresse('icloud'));
    egal('tls://ssl0.ovh.net:993', imap_adresse('colin'));
    egal('tls://ssl0.ovh.net:993', imap_adresse('contact'));
    leve(InvalidArgumentException::class, fn() => imap_adresse('inconnu'));
    egal(['gmail', 'icloud', 'colin', 'contact'], array_keys(JR_IMAP_SERVEURS));
    egal(null, $GLOBALS['JR_IMAP_ADRESSE'], 'le lanceur de tests remet JR_IMAP_ADRESSE à null avant chaque test');
});

test('IMAP client : lecture complète d\'un compte Gmail, uniquement des commandes autorisées, dans cet ordre', function () {
    avec_faux_imap(scenario_imap_standard(), function (string $adresse, callable $commandes) {
        $boite = lire_chez($adresse);
        egal(424242, $boite['uidvalidity']);
        egal(mails_attendus_standard(), $boite['mails']);
        $recues = $commandes();
        $elements = 'UID FLAGS INTERNALDATE ENVELOPE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (' . implode(' ', array_map('strtoupper', JR_IMAP_ENTETES_UTILES)) . ')] X-GM-MSGID';
        egal([
            'CAPABILITY',
            'LOGIN "' . IMAP_ID_TEST . '" "' . IMAP_MDP_TEST . '"',
            'EXAMINE "INBOX"',
            'UID SEARCH UNSEEN',
            'UID SEARCH ALL',
            "UID FETCH 101:105 ($elements)",
            'UID FETCH 101:102,104 (UID BODY.PEEK[1]<0.2000>)',
            'UID FETCH 103 (UID BODY.PEEK[1.1]<0.2000>)',
            'LOGOUT',
        ], $recues);
        foreach ($recues as $commande) {
            vrai(imap_commande_autorisee($commande), 'commande hors liste blanche reçue : ' . $commande);
            vrai(!preg_match('/\b(SELECT|STORE|COPY|EXPUNGE|APPEND|DELETE)\b/', $commande), 'commande en écriture : ' . $commande);
            vrai(!preg_match('/BODY\[/', $commande), 'BODY[ sans PEEK : ' . $commande);
        }
    });
});

test('IMAP client : hors Gmail, pas de X-GM-MSGID demandé', function () {
    $scenario = scenario_imap_standard(['regles_avant' => [
        ['motif' => '/\ACAPABILITY\z/', 'reponse' => "* CAPABILITY IMAP4rev1 UIDPLUS\r\n{ETIQUETTE} OK Fait\r\n"],
    ]]);
    avec_faux_imap($scenario, function (string $adresse, callable $commandes) {
        $boite = lire_chez($adresse, 'colin');
        egal(5, count($boite['mails']));
        $fetch = array_values(array_filter($commandes(), fn(string $c): bool => str_contains($c, 'BODYSTRUCTURE')));
        egal(1, count($fetch));
        vrai(!str_contains($fetch[0], 'X-GM-MSGID'), 'X-GM-MSGID ne vaut que pour Gmail');
    });
    avec_faux_imap(scenario_imap_standard(), function (string $adresse, callable $commandes) {
        lire_chez($adresse, 'icloud');
        $fetch = array_values(array_filter($commandes(), fn(string $c): bool => str_contains($c, 'BODYSTRUCTURE')));
        vrai(!str_contains($fetch[0], 'X-GM-MSGID'), 'icloud ne demande pas X-GM-MSGID même si le serveur annonce X-GM-EXT-1');
    });
});

test('IMAP client : 200 non lus les plus récents et 60 derniers UID, par lots de 60, sans rien lire d\'autre', function () {
    $non_lus = 'SEARCH ' . implode(' ', range(1, 250));
    $tous = 'SEARCH ' . implode(' ', range(1, 500));
    $scenario = scenario_imap_standard(['regles_avant' => [
        ['motif' => '/\AUID SEARCH UNSEEN\z/', 'reponse' => "* $non_lus\r\n{ETIQUETTE} OK Fait\r\n"],
        ['motif' => '/\AUID SEARCH ALL\z/', 'reponse' => "* $tous\r\n{ETIQUETTE} OK Fait\r\n"],
        ['motif' => '/\AUID FETCH/', 'reponse' => "{ETIQUETTE} OK Fait\r\n"],
    ]]);
    avec_faux_imap($scenario, function (string $adresse, callable $commandes) {
        $boite = lire_chez($adresse);
        egal([], $boite['mails']);
        $ensembles = [];
        foreach ($commandes() as $commande) {
            if (preg_match('/\AUID FETCH ([\d:,]+) /', $commande, $m)) {
                $ensembles[] = $m[1];
            }
        }
        egal(['51:110', '111:170', '171:230', '231:250,441:480', '481:500'], $ensembles);
    });
});

test('IMAP client : le mot de passe refusé (NO au LOGIN) donne refus, sans LOGOUT ni lecture', function () {
    avec_faux_imap(scenario_imap_standard(), function (string $adresse, callable $commandes) {
        $debut = microtime(true);
        egal('refus', code_de(fn() => lire_chez($adresse, 'gmail', 20.0, 'mauvais mot de passe')));
        vrai(microtime(true) - $debut < 9, 'le refus doit être rapide');
        egal(['CAPABILITY', 'LOGIN "' . IMAP_ID_TEST . '" "mauvais mot de passe"'], $commandes());
    });
});

test('IMAP client : LOGINDISABLED annoncé donne refus sans envoyer LOGIN', function () {
    $scenario = scenario_imap_standard(['regles_avant' => [
        ['motif' => '/\ACAPABILITY\z/', 'reponse' => "* CAPABILITY IMAP4rev1 LOGINDISABLED STARTTLS\r\n{ETIQUETTE} OK Fait\r\n"],
    ]]);
    avec_faux_imap($scenario, function (string $adresse, callable $commandes) {
        egal('refus', code_de(fn() => lire_chez($adresse)));
        egal(['CAPABILITY'], $commandes());
    });
});

test('IMAP client : port fermé donne injoignable en moins de 9 s', function () {
    $debut = microtime(true);
    egal('injoignable', code_de(fn() => lire_chez('tcp://127.0.0.1:' . port_libre())));
    vrai(microtime(true) - $debut < 9, 'injoignable doit être rapide');
    $GLOBALS['JR_IMAP_ADRESSE'] = 'tcp://127.0.0.1:' . port_libre();
    egal('injoignable', code_de(fn() => imap_essayer('gmail', IMAP_ID_TEST, IMAP_MDP_TEST, microtime(true) + 20)));
});

test('IMAP client : serveur muet donne delai en moins de 9 s (8 s par réponse)', function () {
    avec_faux_imap(['accueil' => null, 'duree_max' => 14, 'regles' => []], function (string $adresse) {
        $debut = microtime(true);
        egal('delai', code_de(fn() => lire_chez($adresse)));
        $duree = microtime(true) - $debut;
        vrai($duree >= 7.5 && $duree < 9, 'délai par réponse de 8 s : ' . round($duree, 2) . ' s');
    });
});

test('IMAP client : la limite globale coupe une lecture lente (serveur qui se tait après LOGIN)', function () {
    $scenario = scenario_imap_standard(['regles_avant' => [['motif' => '/\AUID SEARCH UNSEEN\z/', 'silence' => true]]]);
    avec_faux_imap($scenario, function (string $adresse) {
        $debut = microtime(true);
        egal('delai', code_de(fn() => lire_chez($adresse, 'gmail', 1.5)));
        $duree = microtime(true) - $debut;
        vrai($duree >= 1.4 && $duree < 3, 'limite globale de 1,5 s : ' . round($duree, 2) . ' s');
    });
    $GLOBALS['JR_IMAP_ADRESSE'] = 'tcp://127.0.0.1:' . port_libre();
    egal('delai', code_de(fn() => imap_lire_boite('gmail', IMAP_ID_TEST, IMAP_MDP_TEST, microtime(true) - 1)), 'limite déjà dépassée');
});

test('IMAP client : connexion coupée en route, BAD ou NO inattendu donnent injoignable', function () {
    foreach ([
        ['motif' => '/\AUID SEARCH ALL\z/', 'couper' => true],
        ['motif' => '/\AUID SEARCH ALL\z/', 'reponse' => "{ETIQUETTE} BAD Argument invalide\r\n"],
        ['motif' => '/\AUID FETCH/', 'reponse' => "{ETIQUETTE} NO Erreur interne\r\n"],
        ['motif' => '/\AEXAMINE/', 'reponse' => "{ETIQUETTE} NO Boite introuvable\r\n"],
        ['motif' => '/\ACAPABILITY\z/', 'reponse' => "{ETIQUETTE} BAD\r\n"],
    ] as $regle) {
        avec_faux_imap(scenario_imap_standard(['regles_avant' => [$regle]]), function (string $adresse) use ($regle) {
            egal('injoignable', code_de(fn() => lire_chez($adresse)), json_encode($regle['motif']));
        });
    }
});

test('IMAP client : des réponses absurdes ne lèvent jamais autre chose qu\'ErreurSource (le mail illisible est écarté, les autres restent)', function () {
    foreach ([
        ['motif' => '/\AUID SEARCH ALL\z/', 'reponse' => "* SEARCH 1 2 ((\r\n{ETIQUETTE} OK\r\n"],
        ['motif' => '/\AUID FETCH [\d:,]+ \(UID FLAGS/', 'reponse' => "* 1 FETCH (UID 101 ENVELOPE (\"x\r\n* 2 FETCH \r\n* 3 FETCH ()\r\n* 4 FETCH (UID 104 FLAGS (\\Seen)\r\n{ETIQUETTE} OK\r\n"],
        ['motif' => '/\AUID FETCH [\d:,]+ \(UID FLAGS/', 'reponse' => "{ETIQUETTE} OK\r\n"],
    ] as $regle) {
        avec_faux_imap(scenario_imap_standard(['regles_avant' => [$regle]]), function (string $adresse) use ($regle) {
            try {
                $boite = lire_chez($adresse);
                vrai(is_array($boite['mails']), 'mails doit être une liste');
            } catch (ErreurSource $e) {
                vrai(in_array($e->codeErreur, ['injoignable', 'delai'], true), $e->codeErreur);
            }
        });
    }
});

test('IMAP client : un accueil qui n\'est pas « * OK » donne injoignable', function () {
    avec_faux_imap(scenario_imap_standard(['accueil' => "* BYE Trop de connexions\r\n"]), function (string $adresse) {
        egal('injoignable', code_de(fn() => lire_chez($adresse)));
    });
});

test('IMAP client : imap_essayer ne fait que CAPABILITY, LOGIN, EXAMINE "INBOX", LOGOUT', function () {
    avec_faux_imap(scenario_imap_standard(), function (string $adresse, callable $commandes) {
        $GLOBALS['JR_IMAP_ADRESSE'] = $adresse;
        egal(null, imap_essayer('icloud', IMAP_ID_TEST, IMAP_MDP_TEST, microtime(true) + 20));
        egal(['CAPABILITY', 'LOGIN "' . IMAP_ID_TEST . '" "' . IMAP_MDP_TEST . '"', 'EXAMINE "INBOX"', 'LOGOUT'], $commandes());
    });
    avec_faux_imap(scenario_imap_standard(), function (string $adresse) {
        $GLOBALS['JR_IMAP_ADRESSE'] = $adresse;
        egal('refus', code_de(fn() => imap_essayer('icloud', IMAP_ID_TEST, 'faux', microtime(true) + 20)));
    });
    $GLOBALS['JR_IMAP_ADRESSE'] = 'tcp://127.0.0.1:' . port_libre();
    egal('injoignable', code_de(fn() => imap_essayer('icloud', IMAP_ID_TEST, IMAP_MDP_TEST, microtime(true) + 20)));
});

test('Focus 2 : réponses découpées en petits morceaux, lignes non sollicitées (EXISTS, FETCH FLAGS) au milieu, résultat identique', function () {
    $intrus = "* 6 EXISTS\r\n* 6 RECENT\r\n* 2 FETCH (FLAGS (\\Seen \\Answered))\r\n* 9 FETCH (UID 999 FLAGS (\\Seen))\r\n* 7 FETCH (UID 101 FLAGS (\\Seen))\r\n";
    $scenario = scenario_imap_standard(['morceau' => 5, 'pause_us' => 100, 'regles_avant' => [
        ['motif' => '/\AUID SEARCH UNSEEN\z/', 'reponse' => "* 6 EXISTS\r\n* SEARCH 101\r\n* SEARCH 103\r\n{ETIQUETTE} OK Fait\r\n"],
        ['motif' => '/\AUID FETCH [\d:,]+ \(UID FLAGS INTERNALDATE ENVELOPE BODYSTRUCTURE/', 'reponse' =>
            fixture_imap('fetch-101-texte-simple.txt') . "* 6 EXISTS\r\n" . fixture_imap('fetch-102-alternative.txt') . "* 2 FETCH (FLAGS (\\Seen \\Answered))\r\n"
            . fixture_imap('fetch-103-mixte-piece-jointe.txt') . "* 9 FETCH (UID 999 FLAGS (\\Seen))\r\n" . fixture_imap('fetch-104-html-seul.txt') . fixture_imap('fetch-105-vide.txt')
            . "{ETIQUETTE} OK Fait\r\n"],
        ['motif' => '/\AUID FETCH [\d:,]+ \(UID BODY\.PEEK\[1\]<0\.2000>\)\z/', 'reponse' => "* 6 RECENT\r\n" . fixture_imap('corps-101-1.txt') . $intrus
            . fixture_imap('corps-102-1.txt') . fixture_imap('corps-104-1.txt') . "{ETIQUETTE} OK Fait\r\n"],
    ]]);
    avec_faux_imap($scenario, function (string $adresse) {
        $boite = lire_chez($adresse);
        $attendus = mails_attendus_standard();
        egal(5, count($boite['mails']));
        egal($attendus, $boite['mails']);
    });
});

test('Focus 2 : réponses envoyées d\'un bloc ou par morceaux de 1 octet, même résultat', function () {
    $resultats = [];
    foreach ([0, 1] as $morceau) {
        avec_faux_imap(scenario_imap_standard(['morceau' => $morceau, 'pause_us' => 0]), function (string $adresse) use (&$resultats) {
            $resultats[] = lire_chez($adresse);
        });
    }
    egal($resultats[0], $resultats[1]);
    egal(mails_attendus_standard(), $resultats[0]['mails']);
});

test('Focus 3 : mots de passe avec guillemet, barre oblique inverse, espace, accent, emoji : connexion réussie', function () {
    foreach (['mot"de passe', 'a\\b', 'avec espace', 'accentué é', 'tout "\\ é 😀', '"', '\\', ' ', 'simple', ''] as $mdp) {
        avec_faux_imap(scenario_imap_standard(['identifiants' => [IMAP_ID_TEST, $mdp]]), function (string $adresse, callable $commandes) use ($mdp) {
            $GLOBALS['JR_IMAP_ADRESSE'] = $adresse;
            egal(null, imap_essayer('gmail', IMAP_ID_TEST, $mdp, microtime(true) + 20), 'connexion avec ' . json_encode($mdp));
            $login = $commandes()[1];
            $quoi_citer = preg_match('/\A[\x20\x21\x23-\x5B\x5D-\x7E]*\z/', $mdp) ? 'entre guillemets' : 'en littéral';
            vrai(
                $quoi_citer === 'entre guillemets' ? str_ends_with($login, ' "' . $mdp . '"') : str_contains($login, '{' . strlen($mdp) . "}\r\n" . $mdp),
                "mot de passe " . json_encode($mdp) . " devrait partir $quoi_citer : " . json_encode($login)
            );
        });
    }
});

test('Focus 3 : identifiant accentué ou avec guillemet, en littéral lui aussi', function () {
    foreach (['léa@exemple.org', 'a"b@exemple.org'] as $id) {
        avec_faux_imap(scenario_imap_standard(['identifiants' => [$id, 'mdp "x"']]), function (string $adresse) use ($id) {
            $GLOBALS['JR_IMAP_ADRESSE'] = $adresse;
            egal(null, imap_essayer('colin', $id, 'mdp "x"', microtime(true) + 20));
        });
    }
});

test('Focus 3 : un octet nul dans le mot de passe est refusé sans rien envoyer', function () {
    avec_faux_imap(scenario_imap_standard(), function (string $adresse, callable $commandes) {
        egal('refus', code_de(fn() => lire_chez($adresse, 'gmail', 20.0, "mot\0de passe")));
        egal(['CAPABILITY'], $commandes());
    });
});

test('Focus 4 : boîte vide, aucun UID FETCH n\'est envoyé et la copie est {uidvalidity, mails: []}', function () {
    $scenario = scenario_imap_standard(['regles_avant' => [
        ['motif' => '/\AUID SEARCH (UNSEEN|ALL)\z/', 'reponse' => "* SEARCH\r\n{ETIQUETTE} OK Fait\r\n"],
    ]]);
    avec_faux_imap($scenario, function (string $adresse, callable $commandes) {
        egal(['uidvalidity' => 424242, 'mails' => []], lire_chez($adresse));
        egal(['CAPABILITY', 'LOGIN "' . IMAP_ID_TEST . '" "' . IMAP_MDP_TEST . '"', 'EXAMINE "INBOX"', 'UID SEARCH UNSEEN', 'UID SEARCH ALL', 'LOGOUT'], $commandes());
    });
});

test('Focus 4 : aucun non lu, les 60 derniers sont lus quand même, sans UID FETCH vide', function () {
    $scenario = scenario_imap_standard(['regles_avant' => [
        ['motif' => '/\AUID SEARCH UNSEEN\z/', 'reponse' => "{ETIQUETTE} OK Fait\r\n"],
    ]]);
    avec_faux_imap($scenario, function (string $adresse, callable $commandes) {
        $boite = lire_chez($adresse);
        egal(5, count($boite['mails']));
        foreach ($commandes() as $commande) {
            vrai(!preg_match('/\AUID FETCH\s*(\(|\z)/', $commande), 'UID FETCH sans ensemble : ' . $commande);
        }
    });
});

test('Focus 4 : une boîte vide donne bien du JSON valide pour la copie', function () {
    $scenario = scenario_imap_standard(['regles_avant' => [
        ['motif' => '/\AUID SEARCH (UNSEEN|ALL)\z/', 'reponse' => "* SEARCH\r\n{ETIQUETTE} OK Fait\r\n"],
    ]]);
    avec_faux_imap($scenario, function (string $adresse) {
        egal('{"uidvalidity":424242,"mails":[]}', json_encode(lire_chez($adresse)));
    });
});

test('IMAP journal : ni LOGIN, ni mot de passe, ni identifiant, ni texte du serveur dans le journal, même en erreur', function () {
    $mdp = 'S3cret"\\mdp é';
    $cas = [
        'refus' => [scenario_imap_standard(['identifiants' => [IMAP_ID_TEST, 'autre']]), 20.0],
        'delai' => [scenario_imap_standard(['identifiants' => [IMAP_ID_TEST, $mdp], 'regles_avant' => [['motif' => '/\AEXAMINE/', 'silence' => true]]]), 1.0],
        'injoignable' => [scenario_imap_standard(['identifiants' => [IMAP_ID_TEST, $mdp], 'regles_avant' => [['motif' => '/\AUID SEARCH ALL\z/', 'reponse' => "{ETIQUETTE} BAD LOGIN {$mdp} interdit\r\n"]]]), 20.0],
    ];
    foreach ($cas as $attendu => [$scenario, $duree]) {
        avec_faux_imap($scenario, function (string $adresse) use ($attendu, $duree, $mdp) {
            $e = leve(ErreurSource::class, fn() => lire_chez($adresse, 'gmail', $duree, $mdp));
            egal($attendu, $e->codeErreur);
            journal_erreur('mails/rafraichir', $e);
        });
    }
    $journal = journal_texte();
    vrai($journal !== '', "le journal doit contenir les erreurs");
    egal(3, substr_count($journal, 'ErreurSource'));
    foreach (['LOGIN', 'S3cret', 'mdp', IMAP_ID_TEST, 'exemple.org', 'Invalid', 'AUTHENTICATIONFAILED', 'interdit', 'colin.test'] as $interdit) {
        vrai(!str_contains($journal, $interdit), "« $interdit » ne doit pas être dans le journal : $journal");
    }
});

test('IMAP : le certificat du serveur est vérifié (un certificat auto-signé est refusé, injoignable)', function () {
    $cle = openssl_pkey_new(['private_key_bits' => 2048]);
    if ($cle === false) {
        throw new EchecTest('openssl_pkey_new indisponible : ' . openssl_error_string());
    }
    $demande = openssl_csr_new(['commonName' => '127.0.0.1'], $cle, ['digest_alg' => 'sha256']);
    $certificat = openssl_csr_sign($demande, null, $cle, 1);
    openssl_x509_export($certificat, $pem_certificat);
    openssl_pkey_export($cle, $pem_cle);
    $pem = fichier_temporaire($pem_certificat . $pem_cle);
    avec_faux_imap(scenario_imap_standard(['tls_pem' => $pem]), function (string $adresse, callable $commandes) {
        vrai(str_starts_with($adresse, 'tls://'), 'le faux serveur doit parler TLS');
        $debut = microtime(true);
        egal('injoignable', code_de(fn() => lire_chez($adresse)));
        vrai(microtime(true) - $debut < 9, 'refus du certificat rapide');
        egal([], $commandes(), 'aucune commande ne doit partir sur une connexion non vérifiée');
    });
});

test('IMAP : toute écriture sur la socket passe par imap_ecrire, appelée seulement par imap_envoyer', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/serveur/lib/imap.php');
    egal(1, preg_match_all('/\bfwrite\s*\(/', $source), 'un seul fwrite dans le client');
    egal(1, preg_match_all('/\bimap_ecrire\s*\(/', preg_replace('/function imap_ecrire\b/', '', $source)), 'imap_ecrire appelée à un seul endroit');
    foreach (['fputs', 'stream_socket_sendto', 'socket_write', 'file_put_contents', 'stream_copy_to_stream'] as $autre) {
        vrai(!str_contains($source, $autre), "$autre ne doit pas servir à écrire sur la socket");
    }
});
