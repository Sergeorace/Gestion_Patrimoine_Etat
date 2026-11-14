import { reactive } from 'vue';
import { api, getToken } from './api';

/**
 * État d'autorisation réactif, chargé une fois depuis /auth/me.
 * Source de vérité : le claim JWT "permissions" ("*" = administrateur).
 */
export const authz = reactive({
  user: null,
  perms: [],
  loaded: false,
});

export async function loadAuth() {
  if (authz.loaded) return authz.user;
  authz.loaded = true;
  if (! getToken()) return null;
  try {
    authz.user = await api.get('/auth/me');
    authz.perms = authz.user?.permissions ?? [];
  } catch { /* session expirée gérée par api.js */ }
  return authz.user;
}

export const estAdmin = () => authz.perms.includes('*');
export const peut = (p) => estAdmin() || authz.perms.includes(p);
export const peutUne = (...ps) => estAdmin() || ps.some((p) => authz.perms.includes(p));

// ---------------------------------------------------------------------------
// Matrice page -> permissions requises (lecture stricte).
// ---------------------------------------------------------------------------
export const PAGE_PERMS = {
  '/tableau-de-bord': [],
  '/biens': ['bien.lire'],
  '/biens/nouveau': ['bien.creer'],
  '/affectations': ['mouvement.lire', 'mouvement.creer', 'mouvement.valider'],
  '/mouvements/nouveau': ['mouvement.creer'],
  '/maintenance': ['maintenance.gerer'],
  '/inventaires': ['inventaire.gerer', 'inventaire.saisir', 'audit.lire'],
  '/rapports': ['rapport.lire'],
  '/admin': ['utilisateur.gerer'],
};

// Actions sensibles -> permission(s) requise(s).
export const ACTION_PERMS = {
  'bien.creer': ['bien.creer'],
  'mouvement.creer': ['mouvement.creer'],
  'maintenance.gerer': ['maintenance.gerer'],
  'inventaire.ecrire': ['inventaire.gerer'],
  'rapport.gerer': ['rapport.gerer'],
  'admin': ['utilisateur.gerer'],
};

export function peutAction(cle) {
  return peutUne(...(ACTION_PERMS[cle] ?? []));
}

export function pageAutorisee(path) {
  if (! getToken()) return false;
  // Correspondance la plus longue pour gérer /biens/nouveau et /biens/{id}
  const entries = Object.entries(PAGE_PERMS)
    .filter(([p]) => path === p || path.startsWith(p + '/'))
    .sort((x, y) => y[0].length - x[0].length);
  if (! entries.length) return estAdmin(); // page inconnue : admin seulement
  const [, perms] = entries[0];
  return perms.length === 0 || peutUne(...perms);
}
