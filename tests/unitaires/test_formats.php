<?php
declare(strict_types=1);

test("formats : heures et dates comme Junior les dit, à l'heure de Paris", function () {
    egal('8 h', heure_lisible(utc_test('2026-10-14 06:00:00')));
    egal('8 h 30', heure_lisible(utc_test('2026-10-26 07:30:00')));  // heure d'hiver : UTC+1
    egal('19 h 15', heure_lisible(new DateTimeImmutable('2026-10-21T19:15:00+02:00')));
    egal('0 h 05', heure_lisible(utc_test('2026-10-13 22:05:00')));
    egal('Mercredi 14 octobre', date_longue(utc_test('2026-10-14 06:00:00')));
    egal('Dimanche 1er novembre', date_longue(utc_test('2026-10-31 23:30:00')));  // déjà le 1er à Paris
    egal('Mercredi', jour_nom(new DateTimeImmutable('2026-10-21T19:15:00+02:00')));
});

test("formats : pluriel, coupe, et jours entre deux dates (changement d'heure compris)", function () {
    egal(['1 tâche', '3 tâches'], [pluriel(1, 'tâche'), pluriel(3, 'tâche')]);
    egal('abc', couper('abc', 3));
    egal('ab…', couper('abcd', 3));
    egal(120, mb_strlen(couper(str_repeat('é', 300), 120)));
    egal(7, jours_entre('2026-10-14', '2026-10-21'));
    egal(2, jours_entre('2026-10-24', '2026-10-26'));
    egal(0, jours_entre('2026-10-25', '2026-10-25'));
});
