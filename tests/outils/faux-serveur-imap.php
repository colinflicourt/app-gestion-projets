<?php
// Faux serveur IMAP pour les tests du client (un seul client, en clair ou en TLS auto-signé).
// Lancé par proc_open : php faux-serveur-imap.php scenario.ser
// Le scénario (serialize) : port_fichier, journal, duree_max, accueil, morceau, pause_us, tls_pem, identifiants, regles.
// Chaque commande reçue (sans étiquette, littéraux compris) est ajoutée au journal, une ligne JSON par commande.
// Une règle : motif (regex sur la commande) et reponse (« {ETIQUETTE} » remplacé), ou silence, ou couper.

declare(strict_types=1);

$scenario = unserialize((string) file_get_contents($argv[1]));
$fin = microtime(true) + ($scenario['duree_max'] ?? 25);

$contexte = stream_context_create(isset($scenario['tls_pem'])
    ? ['ssl' => ['local_cert' => $scenario['tls_pem'], 'verify_peer' => false, 'allow_self_signed' => true]]
    : []);
$schema = isset($scenario['tls_pem']) ? 'tls' : 'tcp';
$serveur = stream_socket_server("$schema://127.0.0.1:0", $no, $texte, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $contexte);
if ($serveur === false) {
    exit(2);
}
$port = (int) substr(strrchr((string) stream_socket_get_name($serveur, false), ':'), 1);
file_put_contents($scenario['port_fichier'] . '.tmp', (string) $port);
rename($scenario['port_fichier'] . '.tmp', $scenario['port_fichier']);

$client = @stream_socket_accept($serveur, max(1.0, $fin - microtime(true)));
if ($client === false) {
    exit(0);
}

function envoyer($client, array $scenario, string $texte): void
{
    $morceau = (int) ($scenario['morceau'] ?? 0);
    if ($morceau <= 0) {
        @fwrite($client, $texte);
        return;
    }
    foreach (str_split($texte, $morceau) as $partie) {
        @fwrite($client, $partie);
        usleep((int) ($scenario['pause_us'] ?? 200));
    }
}

// Arguments d'un LOGIN, lus comme le ferait un vrai serveur (chaînes entre guillemets avec échappements, littéraux)
function arguments_login(string $commande): array
{
    $arguments = [];
    $p = 6;
    $n = strlen($commande);
    while ($p < $n && count($arguments) < 2) {
        if ($commande[$p] === ' ') {
            $p++;
        } elseif ($commande[$p] === '"') {
            $p++;
            $valeur = '';
            while ($p < $n && $commande[$p] !== '"') {
                if ($commande[$p] === '\\') {
                    $p++;
                }
                $valeur .= $commande[$p++];
            }
            $p++;
            $arguments[] = $valeur;
        } elseif (preg_match('/\G\{(\d+)\}\r\n/', $commande, $m, 0, $p)) {
            $p += strlen($m[0]);
            $arguments[] = substr($commande, $p, (int) $m[1]);
            $p += (int) $m[1];
        } else {
            break;
        }
    }
    return $arguments;
}

if (array_key_exists('accueil', $scenario) && $scenario['accueil'] !== null) {
    envoyer($client, $scenario, $scenario['accueil']);
}

$tampon = '';
$lire_ligne = function () use ($client, &$tampon, $fin): ?string {
    while (($pos = strpos($tampon, "\r\n")) === false) {
        if (microtime(true) > $fin) {
            return null;
        }
        $donnees = @fread($client, 8192);
        if ($donnees === false || ($donnees === '' && feof($client))) {
            return null;
        }
        $tampon .= $donnees;
    }
    $ligne = substr($tampon, 0, $pos);
    $tampon = substr($tampon, $pos + 2);
    return $ligne;
};
$lire_octets = function (int $n) use ($client, &$tampon, $fin): ?string {
    while (strlen($tampon) < $n) {
        if (microtime(true) > $fin) {
            return null;
        }
        $donnees = @fread($client, 8192);
        if ($donnees === false || ($donnees === '' && feof($client))) {
            return null;
        }
        $tampon .= $donnees;
    }
    $octets = substr($tampon, 0, $n);
    $tampon = substr($tampon, $n);
    return $octets;
};

while (microtime(true) < $fin) {
    $ligne = $lire_ligne();
    if ($ligne === null) {
        break;
    }
    [$etiquette, $commande] = array_pad(explode(' ', $ligne, 2), 2, '');
    while (preg_match('/\{(\d+)\}\z/', $commande, $m)) {
        envoyer($client, $scenario, "+ pret\r\n");
        $donnees = $lire_octets((int) $m[1]);
        $suite = $lire_ligne();
        if ($donnees === null || $suite === null) {
            break 2;
        }
        $commande .= "\r\n" . $donnees . $suite;
    }
    file_put_contents($scenario['journal'], json_encode($commande, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n", FILE_APPEND);

    $reponse = null;
    if (str_starts_with($commande, 'LOGIN ') && isset($scenario['identifiants'])) {
        $reponse = arguments_login($commande) === $scenario['identifiants']
            ? "{ETIQUETTE} OK [CAPABILITY IMAP4rev1 X-GM-EXT-1] Authentifie\r\n"
            : "{ETIQUETTE} NO [AUTHENTICATIONFAILED] Invalid credentials\r\n";
    } else {
        foreach ($scenario['regles'] ?? [] as $regle) {
            if (!preg_match($regle['motif'], $commande)) {
                continue;
            }
            if (!empty($regle['couper'])) {
                break 2;
            }
            if (!empty($regle['silence'])) {
                $reponse = false;
            } else {
                $reponse = $regle['reponse'];
            }
            break;
        }
        $reponse ??= "{ETIQUETTE} BAD Commande inconnue\r\n";
    }
    if ($reponse === false) {
        continue;
    }
    envoyer($client, $scenario, str_replace('{ETIQUETTE}', $etiquette, $reponse));
    if ($commande === 'LOGOUT') {
        break;
    }
}
@fclose($client);
