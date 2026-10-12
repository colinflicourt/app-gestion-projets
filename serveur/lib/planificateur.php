<?php
// Le planificateur (SPEC § 15.4) : un passage par minute, lancé par cron-job.org (route POST cron)
// ou, en secours, par le cron d'OVH (serveur/cron-ovh.php).

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/donnees.php';
require_once __DIR__ . '/envois.php';
require_once __DIR__ . '/rappels.php';
require_once __DIR__ . '/reglages.php';

const JR_CRON_AGE_TRELLO = 900;
const JR_CRON_AGE_AGENDA = 3600;
const JR_CRON_DUREE = 20;

// Les copies Trello et agenda, relues si trop vieilles. Une source en panne laisse sa dernière copie
// (ou rien) : les rappels de l'autre partent quand même.
function copies_pour_rappels(): array
{
    try {
        $cartes = donnees_trello(false, JR_CRON_AGE_TRELLO)['contenu']['cartes'] ?? [];
    } catch (Throwable $e) {
        journal_erreur('cron/trello', $e);
        $cartes = copie_lire('trello')['contenu']['cartes'] ?? [];
    }
    try {
        $jours = donnees_agenda('agenda', aujourdhui_paris()->format('Y-m-d'), JR_JOURS_DONNEES, false, JR_CRON_AGE_AGENDA)['jours'];
    } catch (Throwable $e) {
        journal_erreur('cron/agenda', $e);
        $jours = copie_lire('agenda')['contenu']['jours'] ?? [];
    }
    // Agenda jamais connecté : donnees_agenda() rend un objet vide
    return [$cartes, is_array($jours) ? $jours : []];
}

// Rappels manuels non annulés de la fenêtre de génération
function rappels_a_venir(DateTimeImmutable $maintenant): array
{
    $requete = db()->prepare('SELECT id, texte, quand, cree_le FROM rappels WHERE annule = 0 AND quand > ? AND quand <= ?');
    $requete->execute([utc($maintenant->modify('-' . JR_RAPPELS_APRES . ' seconds')), utc($maintenant->modify('+' . JR_RAPPELS_AVANT . ' seconds'))]);
    return array_map(fn(array $r): array => [
        'id' => (int) $r['id'],
        'texte' => $r['texte'],
        'quand' => depuis_utc($r['quand']),
        'cree_le' => depuis_utc($r['cree_le']),
    ], $requete->fetchAll());
}

// Un passage, sous verrou : un second appel pendant un passage repart aussitôt. Au-delà de $limite
// (microtime, 20 s par défaut), plus rien ne part : le reste partira au passage suivant.
function planificateur_passage(?float $limite = null): array
{
    $limite ??= microtime(true) + JR_CRON_DUREE;
    if ((int) db()->query("SELECT GET_LOCK('jr_cron', 0)")->fetchColumn() !== 1) {
        return ['verrou' => false];
    }
    try {
        [$cartes, $jours] = copies_pour_rappels();
        $maintenant = maintenant();
        envois_enregistrer(generer_rappels($cartes, $jours, reglages_rappels(), rappels_a_venir($maintenant), $maintenant));
        $bilan = envoyer_dus(maintenant(), $limite);
        envois_purger(maintenant());
        reglage_ecrire('cron_dernier_passage', iso_paris(maintenant()));
        return ['verrou' => true] + $bilan;
    } finally {
        db()->query("SELECT RELEASE_LOCK('jr_cron')");
    }
}
