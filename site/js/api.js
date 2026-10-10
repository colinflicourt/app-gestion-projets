// Appels à l'API de jr.qyris.fr : /api/…, cookie de session, réponses { ok, … } ou { ok: false, erreur }.

export class ErreurApi extends Error {
  constructor(statut, code, message) {
    super(message);
    this.statut = statut;
    this.code = code;
  }
}

export class ErreurReseau extends Error {}

export async function appeler(methode, route, { corps = null, parametres = null, delai = 15000 } = {}) {
  const adresse = `/api/${route}${parametres ? `?${new URLSearchParams(parametres)}` : ''}`;
  const controle = new AbortController();
  const minuteur = setTimeout(() => controle.abort(), delai);
  let reponse;
  try {
    reponse = await fetch(adresse, {
      method: methode,
      credentials: 'same-origin',
      cache: 'no-store',
      signal: controle.signal,
      headers: corps === null ? {} : { 'Content-Type': 'application/json' },
      body: corps === null ? undefined : JSON.stringify(corps),
    });
  } catch {
    throw new ErreurReseau('Pas de réseau');
  } finally {
    clearTimeout(minuteur);
  }
  let json = null;
  try {
    json = await reponse.json();
  } catch {
    // Corps illisible. Après un 2xx (réponse coupée en route, page HTML d'un portail) : comme une réponse
    // perdue, l'écriture reste en file et repart avec le même id_client. Sinon : erreur du serveur.
    if (reponse.ok) throw new ErreurReseau('Réponse illisible');
  }
  if (!reponse.ok || json?.ok !== true) {
    throw new ErreurApi(reponse.status, json?.erreur?.code ?? 'erreur_serveur', json?.erreur?.message ?? 'Erreur du serveur. Réessaie dans un instant.');
  }
  return json;
}
