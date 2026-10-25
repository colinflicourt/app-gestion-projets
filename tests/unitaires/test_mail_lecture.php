<?php
declare(strict_types=1);

test('mail lecture : « Re: » ajouté sauf s\'il y est déjà (casse et espaces ignorés), objet vide → « Re: »', function () {
    egal('Re: Bonjour', mail_objet_reponse('Bonjour'));
    egal('RE: Bonjour', mail_objet_reponse('RE: Bonjour'));
    egal('  re:x', mail_objet_reponse('  re:x'));
    egal('Re: Réponse', mail_objet_reponse('Réponse'));
    egal('Re: ', mail_objet_reponse(''));
    egal('Re: Fwd: Re', mail_objet_reponse('Fwd: Re'));
});

test('mail lecture : lien Répondre des 4 comptes, valeurs encodées', function () {
    $a = rawurlencode('alice+club@exemple.fr');
    $s = rawurlencode('Re: Café & co ?');
    egal('Re%3A%20Caf%C3%A9%20%26%20co%20%3F', $s);
    egal([
        'repondre_url' => "googlegmail://co?to=$a&subject=$s",
        'repondre_repli' => "https://mail.google.com/mail/?view=cm&to=$a&su=$s",
    ], mail_repondre('gmail', 'alice+club@exemple.fr', 'Café & co ?'));
    foreach (['icloud', 'colin'] as $compte) {
        egal(['repondre_url' => "mailto:$a?subject=$s", 'repondre_repli' => null], mail_repondre($compte, 'alice+club@exemple.fr', 'Café & co ?'), $compte);
    }
    egal(
        ['repondre_url' => "https://webmail.utc.fr/?_task=mail&_action=compose&_to=$a&_subject=$s", 'repondre_repli' => null],
        mail_repondre('utc', 'alice+club@exemple.fr', 'Café & co ?')
    );
});

test('mail lecture : un objet déjà en « RE: » n\'est pas doublé dans le lien', function () {
    egal('mailto:a%40b.fr?subject=RE%3A%20Bonjour', mail_repondre('icloud', 'a@b.fr', 'RE: Bonjour')['repondre_url']);
});

test('mail lecture : noms des boîtes pour « la suite est dans … »', function () {
    egal(['utc' => 'le webmail UTC', 'gmail' => 'Gmail', 'icloud' => 'iCloud Mail', 'colin' => 'le webmail OVH'], JR_MAILS_NOMS_BOITES);
    egal(JR_MAILS_COMPTES, array_keys(JR_MAILS_NOMS_BOITES));
});
