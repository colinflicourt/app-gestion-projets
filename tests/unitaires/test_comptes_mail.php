<?php
declare(strict_types=1);

test('comptes mail : les 4 comptes sont toujours présents, identifiants pré-remplis, rien d enregistré', function () {
    base_de_test();
    $c = comptes_mail_lire();
    egal(['utc', 'gmail', 'icloud', 'colin'], array_keys($c));
    egal(['identifiant' => 'colin.flicourt@gmail.com', 'enregistre' => false, 'dernier_succes' => null, 'derniere_erreur' => null], $c['gmail']);
    egal(['identifiant' => 'cflicour', 'enregistre' => false, 'dernier_succes' => null, 'derniere_erreur' => null], $c['utc']);
    egal('colin@qyris.fr', $c['colin']['identifiant']);
    egal(null, compte_mail_acces('gmail'));
});

test('comptes mail : mot de passe chiffré en base, relu par compte_mail_acces, absent de comptes_mail_lire', function () {
    base_de_test();
    $clair = 'mot de passe "à\\risque"';
    compte_mail_enregistrer('gmail', 'moi@gmail.com', $clair);
    $brut = (string) db()->query("SELECT mot_de_passe FROM comptes_mail WHERE compte = 'gmail'")->fetchColumn();
    vrai($brut !== $clair && !str_contains($brut, 'risque'), 'mot de passe en clair en base');
    egal(['identifiant' => 'moi@gmail.com', 'mot_de_passe' => $clair], compte_mail_acces('gmail'));
    $lu = comptes_mail_lire();
    egal(true, $lu['gmail']['enregistre']);
    egal('moi@gmail.com', $lu['gmail']['identifiant']);
    vrai(!str_contains(json_encode($lu), 'risque'), 'mot de passe dans la lecture');
    foreach ($lu as $compte) {
        vrai(!array_key_exists('mot_de_passe', $compte), 'clé mot_de_passe');
    }
    egal(false, $lu['icloud']['enregistre']);
});

test('comptes mail : réenregistrer remplace le mot de passe et efface l erreur', function () {
    base_de_test();
    fixer_heure('2026-10-23 12:00:00');
    compte_mail_enregistrer('icloud', 'a@icloud.com', 'un');
    compte_mail_noter('icloud', 'refus');
    egal('refus', comptes_mail_lire()['icloud']['derniere_erreur']);
    compte_mail_enregistrer('icloud', 'a@icloud.com', 'deux');
    egal('deux', compte_mail_acces('icloud')['mot_de_passe']);
    egal(null, comptes_mail_lire()['icloud']['derniere_erreur']);
    egal(1, (int) db()->query('SELECT COUNT(*) FROM comptes_mail')->fetchColumn());
});

test('comptes mail : noter une erreur puis un succès', function () {
    base_de_test();
    compte_mail_enregistrer('colin', 'colin@qyris.fr', 'x');
    fixer_heure('2026-10-23 12:30:00');
    compte_mail_noter('colin', 'delai');
    $c = comptes_mail_lire()['colin'];
    egal(['delai', null], [$c['derniere_erreur'], $c['dernier_succes']]);
    fixer_heure('2026-10-23 12:45:05');
    compte_mail_noter('colin', null);
    $c = comptes_mail_lire()['colin'];
    egal([null, '2026-10-23T12:45:05Z'], [$c['derniere_erreur'], $c['dernier_succes']]);
});

test('comptes mail : supprimer efface le compte et sa copie, pas les autres', function () {
    base_de_test();
    fixer_heure('2026-10-23 12:00:00');
    compte_mail_enregistrer('gmail', 'a@gmail.com', 'x');
    compte_mail_enregistrer('icloud', 'a@icloud.com', 'y');
    copie_ecrire('mail:gmail', ['uidvalidity' => 1, 'mails' => []], maintenant());
    copie_ecrire('mail:icloud', ['uidvalidity' => 1, 'mails' => []], maintenant());
    compte_mail_supprimer('gmail');
    egal(false, comptes_mail_lire()['gmail']['enregistre']);
    egal(null, copie_lire('mail:gmail'));
    vrai(copie_lire('mail:icloud') !== null, 'copie icloud conservée');
    egal(true, comptes_mail_lire()['icloud']['enregistre']);
});

test('comptes mail : compte inconnu refusé', function () {
    base_de_test();
    leve(InvalidArgumentException::class, fn() => compte_mail_enregistrer('yahoo', 'a', 'b'));
    leve(InvalidArgumentException::class, fn() => compte_mail_acces('yahoo'));
    leve(InvalidArgumentException::class, fn() => compte_mail_supprimer('yahoo'));
});

test('corrections d expéditeur : minuscules, espaces retirés, auto efface, choix invalide refusé', function () {
    base_de_test();
    fixer_heure('2026-10-23 12:00:00');
    correction_ecrire('  News@Exemple.FR ', 'pub');
    egal(['news@exemple.fr' => 'pub'], corrections_lire());
    correction_ecrire('news@exemple.fr', 'afficher');
    egal(['news@exemple.fr' => 'afficher'], corrections_lire());
    leve(InvalidArgumentException::class, fn() => correction_ecrire('a@b.fr', 'lu'));
    leve(InvalidArgumentException::class, fn() => correction_ecrire('  ', 'pub'));
    correction_ecrire('NEWS@exemple.fr', 'auto');
    egal([], corrections_lire());
    correction_ecrire('absent@exemple.fr', 'auto');
});

test('comptes mail : une ligne d un compte retiré (contact) restée en base est ignorée, et le compte est refusé', function () {
    base_de_test();
    compte_mail_enregistrer('colin', 'colin@qyris.fr', 'secret-colin');
    $r = db()->prepare('INSERT INTO comptes_mail (compte, identifiant, mot_de_passe, modifie_le) VALUES (?, ?, ?, UTC_TIMESTAMP())');
    $r->execute(['contact', 'contact@qyris.fr', 'x']);
    egal(['utc', 'gmail', 'icloud', 'colin'], array_keys(comptes_mail_lire()));
    egal(['utc', 'gmail', 'icloud', 'colin'], array_keys(corps(appeler('GET', 'mails'))['comptes']));
    egal(['utc', 'gmail', 'icloud', 'colin'], array_keys(mails_copies_datees()));
    egal(400, appeler('POST', 'comptes-mail', ['compte' => 'contact', 'identifiant' => 'contact@qyris.fr', 'mot_de_passe' => 'y'])['statut']);
});
