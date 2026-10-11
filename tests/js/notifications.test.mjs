import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cleOctets } from '../../site/js/notifications.js';

test('clé VAPID base64url en octets : 65 octets, point non compressé (0x04)', () => {
  const o = cleOctets('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
  assert.equal(o.length, 65);
  assert.equal(o[0], 4);
  assert.equal(o[64], 0x0f);
});

test('base64url avec - et _ (sans remplissage) relu comme en base64', () => {
  assert.deepEqual([...cleOctets('-_8')], [0xfb, 0xff]);
});
