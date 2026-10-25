<?php
declare(strict_types=1);

// Joue un flux avec de fausses fonctions d'émission : rend la liste des événements dans l'ordre
function jouer_flux(array $reponse): array
{
    $evenements = [];
    flux_executer(
        $reponse,
        function (int $statut, array $entetes) use (&$evenements): void {
            $evenements[] = ['entetes', $statut, $entetes];
        },
        function (string $octets) use (&$evenements): void {
            $evenements[] = ['octets', $octets];
        }
    );
    return $evenements;
}

function journal_du_test(): string
{
    $fichier = dossier_data() . '/erreurs.log';
    return is_file($fichier) ? (string) file_get_contents($fichier) : '';
}

test('réponse brute : exactement les en-têtes donnés, aucun en-tête de sécurité de l API', function () {
    $r = reponse_brute(200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store'], '<p>Bonjour</p>');
    egal(['statut', 'entetes', 'cookies', 'corps'], array_keys($r));
    egal(200, $r['statut']);
    egal(['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store'], $r['entetes']);
    egal([], $r['cookies']);
    egal('<p>Bonjour</p>', $r['corps']);
    foreach (array_keys(entetes_securite()) as $nom) {
        vrai(!isset($r['entetes'][$nom]), "$nom ne doit pas être ajouté");
    }
    egal(404, reponse_brute(404, [], '')['statut']);
    egal([], reponse_brute(404, [], '')['entetes']);
});

test('réponse en flux : la forme attendue par le routeur et emettre', function () {
    $producteur = fn(callable $commencer, callable $ecrire) => null;
    $r = reponse_flux($producteur, ['Content-Type' => 'text/plain; charset=utf-8']);
    egal(['statut', 'entetes', 'cookies', 'corps', 'flux'], array_keys($r));
    egal([], $r['cookies']);
    egal('', $r['corps']);
    egal(['Content-Type' => 'text/plain; charset=utf-8'], $r['entetes']);
    egal($producteur, $r['flux']);
});

test('flux : statut, en-têtes puis octets, dans l ordre', function () {
    $r = reponse_flux(function (callable $commencer, callable $ecrire): void {
        $commencer(200, ['Content-Type' => 'application/pdf']);
        $ecrire('abc');
        $ecrire('');
        $ecrire('déf');
    }, ['Content-Type' => 'text/plain']);
    egal([
        ['entetes', 200, ['Content-Type' => 'application/pdf']],
        ['octets', 'abc'],
        ['octets', ''],
        ['octets', 'déf'],
    ], jouer_flux($r));
    egal('', journal_du_test());
});

test('flux : une exception avant le départ donne le statut d erreur et les en-têtes d erreur', function () {
    $entetes_erreur = ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'];
    $r = reponse_flux(function (callable $commencer, callable $ecrire): void {
        throw new RuntimeException('panne');
    }, $entetes_erreur);
    $evenements = jouer_flux($r);
    egal(2, count($evenements));
    egal(['entetes', 502, $entetes_erreur], $evenements[0]);
    egal('octets', $evenements[1][0]);
    vrai($evenements[1][1] !== '' && strlen($evenements[1][1]) < 80, 'corps texte court');
    vrai(str_contains(journal_du_test(), '| flux | RuntimeException | panne'), 'ligne de journal attendue');
});

test('flux : codes d erreur de source attendus en 404 et 413, sans journal', function () {
    $entetes_erreur = ['Content-Type' => 'text/plain; charset=utf-8'];
    foreach (['absent' => 404, 'hors_copie' => 404, 'partie_inconnue' => 404, 'trop_lourde' => 413] as $code => $statut) {
        $r = reponse_flux(function (callable $commencer, callable $ecrire) use ($code): void {
            throw new ErreurSource($code, 'IMAP : test');
        }, $entetes_erreur);
        egal(['entetes', $statut, $entetes_erreur], jouer_flux($r)[0], $code);
        egal($statut, statut_erreur_source($code), $code);
    }
    egal('', journal_du_test());
});

test('flux : source en panne en 502 avec journal, ErreurApi avec son statut', function () {
    foreach (['refus', 'injoignable', 'delai', 'inconnu'] as $code) {
        $r = reponse_flux(function (callable $commencer, callable $ecrire) use ($code): void {
            throw new ErreurSource($code, 'IMAP : test');
        }, []);
        egal(502, jouer_flux($r)[0][1], $code);
        egal(502, statut_erreur_source($code), $code);
    }
    vrai(substr_count(journal_du_test(), 'ErreurSource') === 4, 'une ligne par panne de source');
    $r = reponse_flux(function (callable $commencer, callable $ecrire): void {
        throw new ErreurApi('mauvaise_partie', 400, 'Partie illisible.');
    }, ['X-Test' => '1']);
    egal(['entetes', 400, ['X-Test' => '1']], jouer_flux($r)[0]);
});

test('flux : une exception après le départ coupe la réponse et va au journal', function () {
    $r = reponse_flux(function (callable $commencer, callable $ecrire): void {
        $commencer(200, ['Content-Type' => 'application/pdf']);
        $ecrire('début');
        throw new ErreurSource('delai', 'IMAP : délai dépassé');
    }, ['Content-Type' => 'text/plain']);
    egal([['entetes', 200, ['Content-Type' => 'application/pdf']], ['octets', 'début']], jouer_flux($r));
    vrai(str_contains(journal_du_test(), '| flux | ErreurSource | IMAP : délai dépassé'), 'ligne de journal attendue');
    // Même un code « attendu » après le départ est journalisé : la réponse est tronquée
    $r = reponse_flux(function (callable $commencer, callable $ecrire): void {
        $commencer(200, []);
        throw new ErreurSource('trop_lourde', 'IMAP : trop lourde');
    }, []);
    egal([['entetes', 200, []]], jouer_flux($r));
    vrai(str_contains(journal_du_test(), 'trop lourde'), 'ligne de journal attendue');
});

test('flux : écrire avant de commencer est une erreur, jamais un octet sorti', function () {
    $r = reponse_flux(function (callable $commencer, callable $ecrire): void {
        $ecrire('trop tôt');
    }, ['Content-Type' => 'text/plain']);
    $evenements = jouer_flux($r);
    egal(['entetes', 502, ['Content-Type' => 'text/plain']], $evenements[0]);
    foreach ($evenements as $e) {
        vrai($e[0] !== 'octets' || $e[1] !== 'trop tôt', "l'octet ne doit pas sortir");
    }
});

test('flux : commencer deux fois est une erreur journalisée', function () {
    $r = reponse_flux(function (callable $commencer, callable $ecrire): void {
        $commencer(200, []);
        $commencer(200, []);
    }, []);
    egal([['entetes', 200, []]], jouer_flux($r));
    vrai(str_contains(journal_du_test(), 'LogicException'), 'ligne de journal attendue');
});
