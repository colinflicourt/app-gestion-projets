// Notifications web sur l'iPhone (SPEC § 15.8) : abonnement sur un geste, renvoyé à chaque ouverture
// (iOS n'a pas pushsubscriptionchange), oublié à la déconnexion.
import { appeler } from './api.js';

// Clé publique VAPID (base64url) en octets, pour applicationServerKey
export function cleOctets(texte) {
  const b64 = texte.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(texte.length / 4) * 4, '=');
  return Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
}

const disponibles = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

// L'inscription du service worker, ou null s'il n'est pas prêt en 3 s
const inscription = () => Promise.race([navigator.serviceWorker.ready, new Promise((r) => setTimeout(() => r(null), 3000))]);

async function abonnementActuel() {
  if (!disponibles() || Notification.permission !== 'granted') return null;
  const i = await inscription();
  return i ? i.pushManager.getSubscription() : null;
}

const envoyer = (abonnement) => appeler('POST', 'abonnements', { corps: { abonnement: abonnement.toJSON() } });

// L'état connu sans attendre : la permission du navigateur, avant de lire l'abonnement
export function etatRapide() {
  if (!disponibles()) return 'indisponibles';
  if (Notification.permission === 'denied') return 'coupees';
  return Notification.permission === 'granted' ? 'actives' : 'inactives';
}

export async function etatNotifications() {
  if (!disponibles()) return 'indisponibles';
  if (Notification.permission === 'denied') return 'coupees';
  return (await abonnementActuel()) ? 'actives' : 'inactives';
}

// Sur un geste : la permission est demandée avant toute autre attente (iOS l'exige)
export async function activer(cle) {
  const permission = await Notification.requestPermission();
  if (permission !== 'granted') return permission === 'denied' ? 'coupees' : 'inactives';
  const i = await inscription();
  if (!i) return 'indisponibles';
  const abonnement = (await i.pushManager.getSubscription())
    ?? (await i.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: cleOctets(cle) }));
  await envoyer(abonnement);
  return 'actives';
}

export async function renvoyer() {
  try {
    const abonnement = await abonnementActuel();
    if (abonnement) await envoyer(abonnement);
  } catch {
    // réessayé à la prochaine ouverture
  }
}

export async function oublier() {
  try {
    const abonnement = await abonnementActuel();
    if (!abonnement) return;
    try {
      await appeler('POST', 'abonnements/supprimer', { corps: { endpoint: abonnement.endpoint } });
    } finally {
      await abonnement.unsubscribe();
    }
  } catch {
    // un abonnement resté au serveur répondra 410 au prochain envoi et sera supprimé
  }
}

export const envoyerTest = () => appeler('POST', 'notifications/test', { corps: {} });
