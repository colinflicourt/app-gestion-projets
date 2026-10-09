<?php
// Connexion MySQL (mode strict, heure UTC) et migrations.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';

const JR_SQL_MODE = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION';
const JR_DOSSIER_MIGRATIONS = __DIR__ . '/../migrations';

function db(): PDO
{
    if (!isset($GLOBALS['JR_PDO'])) {
        $b = config('base');
        $pdo = new PDO("mysql:host={$b['hote']};dbname={$b['nom']};charset=utf8mb4", $b['utilisateur'], $b['mot_de_passe'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        // Le sql_mode d'OVH n'est pas strict, et son horloge MySQL est à l'heure de Paris
        $pdo->exec("SET SESSION sql_mode = '" . JR_SQL_MODE . "', time_zone = '+00:00'");
        $GLOBALS['JR_PDO'] = $pdo;
    }
    return $GLOBALS['JR_PDO'];
}

function db_oublier(): void
{
    unset($GLOBALS['JR_PDO']);
}

function version_attendue(string $dossier = JR_DOSSIER_MIGRATIONS): int
{
    $fichiers = glob($dossier . '/[0-9][0-9][0-9]_*.sql');
    return $fichiers ? (int) basename(end($fichiers)) : 0;
}

function version_base(PDO $pdo): int
{
    try {
        $valeur = $pdo->query("SELECT valeur FROM reglages WHERE cle = 'version_base'")->fetchColumn();
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            return 0;  // table absente : base vide
        }
        throw $e;
    }
    return $valeur === false ? 0 : (int) json_decode((string) $valeur);
}

// Applique les migrations en retard, sous un verrou : deux requêtes juste après un déploiement
// ne migrent pas en même temps. Renvoie la version atteinte.
function migrer(PDO $pdo, string $dossier = JR_DOSSIER_MIGRATIONS, int $attente = 10): int
{
    if ((int) $pdo->query("SELECT GET_LOCK('jr_migrations', $attente)")->fetchColumn() !== 1) {
        throw new RuntimeException('Verrou des migrations indisponible');
    }
    try {
        $version = version_base($pdo);
        foreach (glob($dossier . '/[0-9][0-9][0-9]_*.sql') as $fichier) {
            $numero = (int) basename($fichier);
            if ($numero <= $version) {
                continue;
            }
            foreach (preg_split('/;\s*$/m', (string) file_get_contents($fichier)) as $instruction) {
                if (trim(preg_replace('/^--.*$/m', '', $instruction)) !== '') {
                    $pdo->exec($instruction);
                }
            }
            $pdo->prepare("INSERT INTO reglages (cle, valeur, maj_le) VALUES ('version_base', ?, ?) AS n
                ON DUPLICATE KEY UPDATE valeur = n.valeur, maj_le = n.maj_le")
                ->execute([json_encode($numero), utc(maintenant())]);
            $version = $numero;
        }
        return $version;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('jr_migrations')");
    }
}

function assurer_migrations(): void
{
    $pdo = db();
    if (version_base($pdo) < version_attendue()) {
        migrer($pdo);
    }
}
