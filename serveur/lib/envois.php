<?php
// La table envois (SPEC § 15.2) : une ligne par notification, la clé interdit les doublons.

declare(strict_types=1);

require_once __DIR__ . '/abonnements.php';
require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';

// Au-delà de ce retard (secondes), une notification ne part plus
const JR_PEREMPTION = ['cours' => 600, 'median' => 10800, 'echeance' => 10800, 'brief' => 7200, 'manuel' => 43200];
const JR_ENVOI_ESSAIS = 3;
const JR_ENVOIS_JOURS = 30;

// Ajoute les nouvelles. Une ligne encore prévue suit la source (heure, texte) ; une retirée qui réapparaît
// redevient prévue ; envoyée, périmée ou échouée, elle ne bouge plus. Les prévues absentes de la liste
// sont retirées (cours supprimé, carte terminée, rappel annulé, réglage coupé).
function envois_enregistrer(array $rappels): void
{
    $ajout = db()->prepare("INSERT INTO envois (cle, type, prevu_pour, titre, texte, url) VALUES (?, ?, ?, ?, ?, ?) AS n
        ON DUPLICATE KEY UPDATE
            statut = IF(envois.statut = 'retire', 'prevu', envois.statut),
            prevu_pour = IF(envois.statut = 'prevu', n.prevu_pour, envois.prevu_pour),
            titre = IF(envois.statut = 'prevu', n.titre, envois.titre),
            texte = IF(envois.statut = 'prevu', n.texte, envois.texte),
            url = IF(envois.statut = 'prevu', n.url, envois.url)");
    foreach ($rappels as $r) {
        $ajout->execute([$r['cle'], $r['type'], utc($r['prevu_pour']), $r['titre'], $r['texte'], $r['url']]);
    }
    $cles = array_column($rappels, 'cle');
    $sauf = $cles === [] ? '' : ' AND cle NOT IN (' . implode(',', array_fill(0, count($cles), '?')) . ')';
    db()->prepare("UPDATE envois SET statut = 'retire' WHERE statut = 'prevu'$sauf")->execute($cles);
}

// Les dues, la plus ancienne d'abord, tant que $limite (microtime) n'est pas passée : le reste part au
// passage suivant. 429, 5xx ou réseau : nouvel essai au passage suivant. Personne d'abonné : rien à faire.
function envoyer_dus(DateTimeImmutable $maintenant, float $limite): array
{
    $bilan = ['envoyees' => 0, 'perimees' => 0, 'echouees' => 0, 'reportees' => 0];
    $dues = db()->prepare("SELECT cle, type, prevu_pour, titre, texte, url, essais FROM envois
        WHERE statut = 'prevu' AND prevu_pour <= ? ORDER BY prevu_pour, cle LIMIT 50");
    $dues->execute([utc($maintenant)]);
    $noter = db()->prepare('UPDATE envois SET statut = ?, essais = ?, envoye_le = ? WHERE cle = ?');
    foreach ($dues->fetchAll() as $e) {
        $essais = (int) $e['essais'];
        if ($maintenant->getTimestamp() - depuis_utc($e['prevu_pour'])->getTimestamp() > (JR_PEREMPTION[$e['type']] ?? 3600)) {
            $noter->execute(['perime', $essais, null, $e['cle']]);
            $bilan['perimees']++;
            continue;
        }
        if (microtime(true) > $limite) {
            break;
        }
        $cours = $e['type'] === 'cours';
        $r = notifier_tous(['titre' => $e['titre'], 'texte' => $e['texte'], 'url' => $e['url'], 'tag' => $e['cle']], $cours ? 'high' : 'normal', $cours ? 900 : 43200);
        $essais++;
        if ($r['envoyes'] === 0 && $r['a_reessayer'] > 0 && $essais < JR_ENVOI_ESSAIS) {
            $noter->execute(['prevu', $essais, null, $e['cle']]);
            $bilan['reportees']++;
        } elseif ($r['envoyes'] === 0 && $r['echecs'] > 0) {
            $noter->execute(['echoue', $essais, null, $e['cle']]);
            $bilan['echouees']++;
        } else {
            $noter->execute(['envoye', $essais, utc($maintenant), $e['cle']]);
            $bilan['envoyees']++;
        }
    }
    return $bilan;
}

function envois_purger(DateTimeImmutable $maintenant): void
{
    db()->prepare('DELETE FROM envois WHERE prevu_pour < ? LIMIT 500')->execute([utc($maintenant->modify('-' . JR_ENVOIS_JOURS . ' days'))]);
}
