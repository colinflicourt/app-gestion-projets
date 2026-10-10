// Service worker de Jarvis Jr. (SPEC § 8.4) : l'app s'ouvre sans réseau. Tout ce qui est en cache est servi
// d'abord ; une nouvelle version (VERSION changée) est installée dès qu'elle est vue et sert à l'ouverture
// suivante. L'API n'est jamais mise en cache. VERSION est l'empreinte des fichiers de FICHIERS :
// php tests/run.php donne la bonne valeur.
const VERSION = 'jr-ed0a896e23';
const FICHIERS = [
  '/',
  '/index.html',
  '/manifest.webmanifest',
  '/css/app.css',
  '/js/app.js',
  '/js/api.js',
  '/js/etat.js',
  '/js/file.js',
  '/js/stockage.js',
  '/js/lib/cartes.js',
  '/js/lib/conduite.js',
  '/js/lib/dom.js',
  '/js/lib/phrases.js',
  '/js/lib/temps.js',
  '/js/lib/textes.js',
  '/js/lib/tirage.js',
  '/js/ecrans/agenda.js',
  '/js/ecrans/aujourdhui.js',
  '/js/ecrans/capture.js',
  '/js/ecrans/carte.js',
  '/js/ecrans/commun.js',
  '/js/ecrans/connexion.js',
  '/js/ecrans/moi.js',
  '/js/ecrans/taches.js',
  '/fonts/archivo.woff2',
  '/fonts/martian-mono.woff2',
  '/fonts/permanent-marker.woff2',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/apple-touch-icon.png',
  '/favicon-32.png',
];

// cache: 'reload' : OVH ajoute max-age=900 aux fichiers statiques, il faut la version du serveur
self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION)
    .then((cache) => cache.addAll(FICHIERS.map((f) => new Request(f, { cache: 'reload' }))))
    .then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys()
    .then((noms) => Promise.all(noms.filter((n) => n !== VERSION).map((n) => caches.delete(n))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) return;
  e.respondWith((async () => {
    const cache = await caches.open(VERSION);
    const trouve = await cache.match(e.request, { ignoreSearch: true });
    if (trouve) return trouve;
    try {
      return await fetch(e.request);
    } catch (erreur) {
      const page = e.request.mode === 'navigate' ? await cache.match('/') : undefined;
      if (page) return page;
      throw erreur;
    }
  })());
});
