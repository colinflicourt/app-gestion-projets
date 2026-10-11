<?php
declare(strict_types=1);

// Ce que le service worker n'a pas à garder au lot 1 : l'API, les cartes à collectionner et les boss
// (lot 4, 8,9 Mo), l'écran de lancement (lu par iOS à l'installation), la page publique, les licences.
const JR_SW_HORS_CACHE = ['/.htaccess', '/robots.txt', '/sw.js', '/confidentialite.html', '/api/', '/data/', '/img/', '/fonts/licences/'];

function sw_source(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2) . '/site/sw.js');
}

function sw_fichiers(): array
{
    preg_match('/const FICHIERS = \[(.*?)\];/s', sw_source(), $m);
    preg_match_all("/'([^']+)'/", $m[1] ?? '', $f);
    return $f[1];
}

test('service worker : chaque fichier du lot 1 est dans sa liste, et seulement ceux-là', function () {
    $site = dirname(__DIR__, 2) . '/site';
    $presents = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS)) as $fichier) {
        $chemin = substr($fichier->getPathname(), strlen($site));
        if ($fichier->getFilename() === '.DS_Store') {
            continue;
        }
        foreach (JR_SW_HORS_CACHE as $exclu) {
            if (str_starts_with($chemin, $exclu)) {
                continue 2;
            }
        }
        $presents[] = $chemin;
    }
    $listes = array_values(array_filter(sw_fichiers(), fn(string $f): bool => $f !== '/'));
    sort($presents);
    sort($listes);
    egal($presents, $listes);
    vrai(in_array('/', sw_fichiers(), true), "'/' doit être en cache (ouverture sans réseau)");
});

test("service worker : sa VERSION est l'empreinte des fichiers en cache (changer un fichier oblige à la changer)", function () {
    $site = dirname(__DIR__, 2) . '/site';
    $empreinte = hash_init('sha256');
    foreach (sw_fichiers() as $f) {
        if ($f !== '/') {
            hash_update($empreinte, $f . "\n" . file_get_contents($site . $f));
        }
    }
    $attendue = 'jr-' . substr(hash_final($empreinte), 0, 10);
    preg_match("/const VERSION = '([^']+)';/", sw_source(), $m);
    egal($attendue, $m[1] ?? null, "dans site/sw.js, mettre const VERSION = '$attendue';");
});

test('sw : un push affiche toujours une notification, même sans contenu lisible', function () {
    $src = sw_source();
    vrai(str_contains($src, "self.addEventListener('push'"), 'écouteur push');
    vrai(str_contains($src, 'showNotification('), 'showNotification');
    vrai(str_contains($src, 'Jarvis Jr. a quelque chose pour toi.'), 'texte de repli');
    vrai(str_contains($src, 'e.waitUntil(afficher'), 'waitUntil sur l\'affichage');
});

test('sw : un toucher ramène l\'app sur l\'adresse de la notification, dans l\'app seulement', function () {
    $src = sw_source();
    vrai(str_contains($src, "self.addEventListener('notificationclick'"), 'écouteur notificationclick');
    vrai(str_contains($src, 'clients.openWindow('), 'ouverture si aucune fenêtre');
    vrai(str_contains($src, 'adresse_interne('), 'adresse filtrée');
});
