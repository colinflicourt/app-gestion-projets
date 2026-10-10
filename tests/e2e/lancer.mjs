// Tests d'écran de Jarvis Jr. : node tests/e2e/lancer.mjs [morceau du nom d'un test]
import { readdirSync } from 'node:fs';
import { lancer } from './cadre.mjs';

const dossier = new URL('.', import.meta.url);
for (const nom of readdirSync(dossier).filter((f) => /^test_.*\.mjs$/.test(f)).sort()) {
  await import(new URL(nom, dossier));
}
process.exitCode = await lancer(process.argv[2] ?? '');
