<?php
// Siri (SPEC § 15.7) : une phrase dictée devient une carte Trello. Le jeton du raccourci n'est gardé que sous
// forme d'empreinte SHA-256, les appels réussis sous forme d'empreinte du texte nettoyé.

declare(strict_types=1);

require_once __DIR__ . '/amorce.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/google.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/reponse.php';
require_once __DIR__ . '/taches.php';

const JR_SIRI_TEXTE_MAX = 500;
const JR_SIRI_PAR_HEURE = 30;
const JR_SIRI_DOUBLON_SECONDES = 120;
const JR_SIRI_NOMS = ['qyris' => 'QYRIS', 'asso' => 'Asso', 'etudes' => 'Études', 'perso' => 'Perso'];

// Domaine et titre d'une phrase dictée. Le titre est vide si la phrase se réduit à un nom de domaine.
function siri_destination(string $texte): array
{
    $texte = trim((string) preg_replace('/\s+/u', ' ', $texte));
    $domaine = 'perso';
    if (preg_match('/^([^\s,;:.!?…]+)[\s\p{P}]*(.*)$/su', $texte, $m)) {
        $mot = strtr(mb_strtolower($m[1]), ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c']);
        if (in_array($mot, JR_DOMAINES, true)) {
            $domaine = $mot;
            $texte = $m[2];
        }
    }
    return ['domaine' => $domaine, 'titre' => mb_strtoupper(mb_substr($texte, 0, 1)) . mb_substr($texte, 1)];
}

// Empreinte du texte nettoyé (minuscules, espaces réduits) : sert à repérer la même phrase dite deux fois
function siri_empreinte(string $texte): string
{
    return hash('sha256', mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $texte))));
}

// Crée (ou remplace) le jeton du raccourci. Il n'est rendu qu'ici : seule son empreinte reste en base.
function siri_jeton_creer(): string
{
    $jeton = base64url(random_bytes(32));
    jeton_ecrire('siri', hash('sha256', $jeton), null);
    return $jeton;
}

// Faux, mal formé, ou aucun jeton créé : false
function siri_jeton_valide(string $jeton): bool
{
    $attendu = jeton_lire('siri');
    return $attendu !== null && preg_match('/^[A-Za-z0-9_-]{43}$/', $jeton) === 1 && hash_equals($attendu, hash('sha256', $jeton));
}

// Une phrase dictée par un appelant authentifié. Rend [statut, phrase lue par Siri]. Tout le travail en base
// se fait sous un verrou : deux appels simultanés de la même phrase ne créent qu'une carte.
function siri_noter(string $texte): array
{
    if (mb_strlen($texte) > JR_SIRI_TEXTE_MAX) {
        return [400, "Je n'ai rien entendu."];
    }
    ['domaine' => $domaine, 'titre' => $titre] = siri_destination($texte);
    if ($titre === '') {
        return [400, "Je n'ai rien entendu."];
    }
    $pdo = db();
    if ((int) $pdo->query("SELECT GET_LOCK('jr_siri', 5)")->fetchColumn() !== 1) {
        throw new RuntimeException('Verrou de Siri indisponible');
    }
    try {
        $maintenant = maintenant();
        $requete = $pdo->prepare('SELECT COUNT(*) FROM siri_appels WHERE recu_le > ?');
        $requete->execute([utc($maintenant->modify('-1 hour'))]);
        if ((int) $requete->fetchColumn() >= JR_SIRI_PAR_HEURE) {
            return [429, "Trop de notes d'un coup. Réessaie dans une heure."];
        }
        $empreinte = siri_empreinte($texte);
        $requete = $pdo->prepare('SELECT COUNT(*) FROM siri_appels WHERE empreinte = ? AND recu_le > ?');
        $requete->execute([$empreinte, utc($maintenant->modify('-' . JR_SIRI_DOUBLON_SECONDES . ' seconds'))]);
        if ((int) $requete->fetchColumn() > 0) {
            return [200, 'Déjà noté.'];
        }
        try {
            tache_ajouter($domaine, $titre, false, null);
        } catch (ErreurApi $e) {
            return [$e->statut, $e->getMessage()];
        } catch (ErreurSource $e) {
            journal_erreur('siri', $e);
            return [502, 'Trello ne répond pas. Réessaie dans un moment.'];
        }
        $pdo->prepare('INSERT INTO siri_appels (recu_le, empreinte) VALUES (?, ?)')->execute([utc($maintenant), $empreinte]);
        $pdo->prepare('DELETE FROM siri_appels WHERE recu_le < ?')->execute([utc($maintenant->modify('-24 hours'))]);
        return [200, 'Ajouté dans ' . JR_SIRI_NOMS[$domaine] . '.'];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('jr_siri')");
    }
}
