// Service worker de Jarvis Jr. (SPEC § 8.4) : l'app s'ouvre sans réseau. Tout ce qui est en cache est servi
// d'abord ; une nouvelle version (VERSION changée) est installée dès qu'elle est vue et sert à l'ouverture
// suivante. L'API n'est jamais mise en cache. VERSION est l'empreinte des fichiers de FICHIERS :
// php tests/run.php donne la bonne valeur.
const VERSION = 'jr-ae48aceeb2';
const FICHIERS = [
  '/',
  '/index.html',
  '/manifest.webmanifest',
  '/css/app.css',
  '/js/app.js',
  '/js/api.js',
  '/js/etat.js',
  '/js/file.js',
  '/js/notifications.js',
  '/js/stockage.js',
  '/js/lib/cartes.js',
  '/js/lib/conduite.js',
  '/js/lib/dom.js',
  '/js/lib/heures-rappel.js',
  '/js/lib/mails.js',
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
  '/js/ecrans/mails.js',
  '/js/ecrans/moi.js',
  '/js/ecrans/reglages.js',
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

// Notifications (SPEC § 15.8). iOS coupe l'abonnement si un push n'affiche rien : on affiche toujours.
const REPLI = { titre: 'Jarvis Jr.', texte: 'Jarvis Jr. a quelque chose pour toi.', url: '/#/aujourdhui', tag: 'jr' };

function lire_message(e) {
  try {
    const m = e.data ? e.data.json() : null;
    return m && typeof m.titre === 'string' ? { ...REPLI, ...m } : REPLI;
  } catch {
    return REPLI;
  }
}

// Seulement une adresse de l'app : une notification ne doit jamais ouvrir un autre site
function adresse_interne(url) {
  try {
    const u = new URL(url, self.location.origin);
    return u.origin === self.location.origin ? u.href : new URL(REPLI.url, self.location.origin).href;
  } catch {
    return new URL(REPLI.url, self.location.origin).href;
  }
}

function afficher(m) {
  return self.registration.showNotification(m.titre, {
    body: m.texte,
    tag: m.tag,
    data: { url: adresse_interne(m.url) },
    icon: '/icons/icon-192.png',
  });
}

self.addEventListener('push', (e) => {
  e.waitUntil(afficher(lire_message(e)));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || adresse_interne(REPLI.url);
  e.waitUntil((async () => {
    const fenetres = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const f of fenetres) {
      if (new URL(f.url).origin === self.location.origin) {
        await f.focus();
        return f.navigate(url);
      }
    }
    return self.clients.openWindow(url);
  })());
});
