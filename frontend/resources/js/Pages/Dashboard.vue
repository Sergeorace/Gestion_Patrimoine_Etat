<script setup>
import { onMounted, ref, computed } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { api } from '../api';
import { authz, loadAuth, pageAutorisee, peutUne } from '../authz';

const dash = ref(null), stats = ref(null), categories = ref([]);
const maint = ref([]), invs = ref([]), mouvements = ref([]), user = ref(null), err = ref('');

const fcfa = (n) => (Number(n) || 0).toLocaleString('fr-FR') + ' FCFA';
const catName = (id) => categories.value.find(c => c.id === id)?.libelle ?? 'Autre';

// ---------------------------------------------------------------------------
// Personnalisation par rôle : chaque profil voit les blocs utiles à sa mission.
// ---------------------------------------------------------------------------
const role = computed(() => user.value?.roles?.[0] ?? 'admin');

// Blocs disponibles : kpis, valorisation, alertes, categories, mouvements,
// interventions, inventaires
const BLOCS_PAR_ROLE = {
  admin:        ['kpis', 'valorisation', 'alertes', 'categories', 'mouvements', 'interventions', 'inventaires'],
  gestionnaire: ['kpis', 'alertes', 'categories', 'mouvements', 'interventions'],
  responsable:  ['kpis', 'alertes', 'categories', 'mouvements'],
  inventoriste: ['kpis', 'alertes', 'categories', 'inventaires'],
  comptable:    ['kpis', 'valorisation', 'categories'],
  maintenance:  ['kpis', 'alertes', 'interventions'],
  auditeur:     ['kpis', 'valorisation', 'inventaires', 'mouvements'],
  decideur:     ['kpis', 'valorisation', 'alertes'],
};
// Permission(s) exigée(s) par chaque bloc — un rôle ne voit jamais un bloc
// dont l'API sous-jacente lui serait interdite (403).
const PERM_BLOC = {
  kpis: [],
  valorisation: ['valorisation.lire'],
  alertes: [],
  categories: ['bien.lire'],
  mouvements: ['mouvement.lire', 'mouvement.creer', 'mouvement.valider'],
  interventions: ['maintenance.gerer'],
  inventaires: ['inventaire.gerer', 'inventaire.saisir', 'audit.lire'],
};
const blocs = computed(() => new Set(
  (BLOCS_PAR_ROLE[role.value] ?? BLOCS_PAR_ROLE.admin)
    .filter((b) => PERM_BLOC[b].length === 0 || peutUne(...PERM_BLOC[b]))
));
const a = (b) => blocs.value.has(b);

const ROLE_TITRE = {
  admin: 'Vue consolidée de l\'ensemble du patrimoine.',
  gestionnaire: 'Suivi opérationnel du parc immobilier et mobilier.',
  responsable: 'Les biens et affectations de votre périmètre.',
  inventoriste: 'Campagnes d\'inventaire et contrôle des biens.',
  comptable: 'Valorisation et situation comptable du patrimoine.',
  maintenance: 'Interventions et maintenance des biens.',
  auditeur: 'Contrôle, inventaires et traçabilité des mouvements.',
  decideur: 'Synthèse stratégique pour la décision.',
};

// Alertes adaptées au rôle — chaque compteur n'appelle que des API autorisées.
const ALERTES_BASE = [
  { cle: 'maintenance', label: 'Maintenances à planifier', n: computed(() => Number(dash.value?.maintenance?.a_venir_30j ?? 0)), href: '/maintenance', ton: 'et-or', page: '/maintenance' },
  { cle: 'inventaires', label: 'Inventaires en cours', n: computed(() => invs.value.filter(c => c.statut === 'en_cours').length), href: '/inventaires', ton: 'et-bleu', page: '/inventaires' },
  { cle: 'biens', label: 'Biens sans affectation', n: computed(() => Number(stats.value?.par_statut?.disponible ?? 0)), href: '/biens', ton: 'et-gris', page: '/biens' },
];
const ALERTES_PAR_ROLE = {
  maintenance: ['maintenance'],
  inventoriste: ['inventaires', 'biens'],
  auditeur: ['inventaires'],
  decideur: ['maintenance'],
};
const alertes = computed(() => {
  const cles = ALERTES_PAR_ROLE[role.value] ?? ALERTES_BASE.map(x => x.cle);
  return ALERTES_BASE
    .filter(x => cles.includes(x.cle) && pageAutorisee(x.page))
    .map(x => ({ ...x, n: x.n.value }));
});

const cats = computed(() => {
  const rows = stats.value?.par_categorie ?? [];
  const max = Math.max(1, ...rows.map(r => Number(r.n)));
  return rows.map(r => ({ ...r, libelle: catName(r.categorie_id), pct: Math.round((Number(r.n) / max) * 100) }));
});

const INTERV_TYPES = { preventive: 'Préventive', curative: 'Corrective', panne: 'Panne' };
const INTERV_STATUTS = { declaree: 'Déclarée', planifiee: 'Planifiée', en_cours: 'En cours', terminee: 'Terminée' };
const MVT_STATUTS = { brouillon: 'Brouillon', validee: 'Validée', rejetee: 'Rejetée', appliquee: 'Appliquée' };
const INV_STATUTS = { planifiee: 'Planifiée', en_cours: 'En cours', cloturee: 'Clôturée' };

onMounted(async () => {
  await loadAuth();
  // Garde de rôle : le tableau de bord est le seul écran universel.
  try {
    user.value = authz.user ?? await api.get('/auth/me');
  } catch { /* silencieux */ }
  try {
    const [d, s, c] = await Promise.all([
      api.get('/reports/dashboard'),
      api.get('/assets/stats'),
      api.get('/organisation/categories'),
    ]);
    dash.value = d; stats.value = s;
    categories.value = Array.isArray(c) ? c : (c.data ?? []);
  } catch (e) { err.value = e.message; }

  // Données des blocs secondaires — uniquement si la page correspondante
  // est dans le périmètre du rôle (évite les 403).
  const jobs = [];
  if (a('interventions')) jobs.push(api.get('/maintenance/interventions?per_page=100').then(r => { maint.value = r.data ?? r ?? []; }).catch(() => {}));
  if (pageAutorisee('/inventaires')) jobs.push(api.get('/inventories?per_page=100').then(r => { invs.value = r.data ?? r ?? []; }).catch(() => {}));
  if (a('mouvements')) jobs.push(api.get('/movements?per_page=8').then(r => { mouvements.value = r.data ?? r ?? []; }).catch(() => {}));
  await Promise.all(jobs);
});
</script>

<template>
  <AppLayout title="Tableau de bord">
    <p class="text-sm mb-5 -mt-2" style="color: var(--discret);">{{ ROLE_TITRE[role] ?? ROLE_TITRE.admin }}</p>
    <p v-if="err" class="text-sm mb-4" style="color: var(--rouge-700);">{{ err }}</p>

    <!-- Indicateurs clés (adaptés : le comptable voit la valorisation en avant) -->
    <section v-if="a('kpis')" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
      <div class="carte p-5">
        <div class="kpi-label">Valeur totale</div>
        <div class="kpi-valeur mt-1" style="color: var(--vert-800);">{{ fcfa(dash?.patrimoine?.valeur_totale) }}</div>
      </div>
      <div class="carte p-5">
        <div class="kpi-label">VNC totale</div>
        <div class="kpi-valeur mt-1" :style="{ color: a('valorisation') ? 'var(--or)' : 'var(--vert-800)' }">{{ fcfa(dash?.valorisation?.total_vnc) }}</div>
      </div>
      <div class="carte p-5">
        <div class="kpi-label">Biens recensés</div>
        <div class="kpi-valeur mt-1" style="color: var(--vert-800);">{{ dash?.patrimoine?.nombre_biens ?? stats?.total ?? 0 }}</div>
      </div>
    </section>

    <!-- Valorisation (comptable, auditeur, décideur, admin) -->
    <section v-if="a('valorisation')" class="carte p-5 mb-8">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-bold">Valorisation du patrimoine</h2>
        <a href="/rapports" class="text-xs font-medium hover:underline" style="color: var(--vert-700);">Voir les rapports →</a>
      </div>
      <div class="grid grid-cols-3 gap-6">
        <div>
          <div class="kpi-label">Acquisition</div>
          <div class="text-lg font-bold" style="font-variant-numeric: tabular-nums;">{{ fcfa(dash?.valorisation?.total_acquisition ?? dash?.patrimoine?.valeur_totale) }}</div>
        </div>
        <div>
          <div class="kpi-label">Amortissements</div>
          <div class="text-lg font-bold" style="font-variant-numeric: tabular-nums;">{{ fcfa(dash?.valorisation?.total_amortissements) }}</div>
        </div>
        <div>
          <div class="kpi-label">VNC</div>
          <div class="text-lg font-bold" style="color: var(--or); font-variant-numeric: tabular-nums;">{{ fcfa(dash?.valorisation?.total_vnc) }}</div>
        </div>
      </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- Alertes (libellés et compteurs selon le rôle) -->
      <section v-if="a('alertes')" class="carte p-5">
        <h2 class="text-sm font-bold mb-4">Alertes</h2>
        <ul class="divide-y" style="border-color: #efede3;">
          <li v-for="x in alertes" :key="x.label">
            <a :href="x.href" class="flex items-center justify-between py-3 group">
              <span class="flex items-center gap-2.5 text-sm">
                <span class="etiquette" :class="x.ton">{{ x.n }}</span>
                {{ x.label }}
              </span>
              <svg class="w-4 h-4 opacity-0 group-hover:opacity-60 transition-opacity" style="color: var(--discret);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </a>
          </li>
        </ul>
      </section>

      <!-- Biens par catégorie -->
      <section v-if="a('categories')" class="carte p-5" :class="{ 'lg:col-span-2': !a('alertes') }">
        <h2 class="text-sm font-bold mb-1">Biens par catégorie</h2>
        <p class="text-xs mb-5" style="color: var(--discret);">Répartition du parc par catégorie de nomenclature</p>
        <div v-if="cats.length" class="space-y-4">
          <div v-for="c in cats" :key="c.categorie_id">
            <div class="flex justify-between text-sm mb-1.5">
              <span class="font-medium">{{ c.libelle }}</span>
              <span style="color: var(--discret);">{{ c.n }} bien{{ c.n > 1 ? 's' : '' }} · {{ (Number(c.valeur) || 0).toLocaleString('fr-FR') }} FCFA</span>
            </div>
            <div class="h-2 rounded-full overflow-hidden" style="background: #efede3;">
              <div class="h-full rounded-full" :style="{ width: c.pct + '%', background: 'var(--vert-600)' }"></div>
            </div>
          </div>
        </div>
        <p v-else class="text-sm py-8 text-center" style="color: var(--discret);">Aucun bien recensé pour le moment.</p>
      </section>

      <!-- Interventions (maintenance, gestionnaire, admin) -->
      <section v-if="a('interventions')" class="carte overflow-hidden">
        <div class="px-5 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--ligne);">
          <h2 class="text-sm font-bold">Interventions</h2>
          <a href="/maintenance" class="text-xs font-medium hover:underline" style="color: var(--vert-700);">Tout voir →</a>
        </div>
        <table class="w-full">
          <tbody>
            <tr v-for="i in maint.slice(0, 5)" :key="i.id" class="tr-ligne">
              <td class="td">
                <div class="text-sm font-medium">{{ INTERV_TYPES[i.type] ?? i.type }}</div>
                <div class="text-xs" style="color: var(--discret);">{{ i.date_prevue ?? '—' }}</div>
              </td>
              <td class="td text-right"><span class="etiquette" :class="{ declaree: 'et-or', planifiee: 'et-bleu', en_cours: 'et-or', terminee: 'et-vert' }[i.statut] ?? 'et-gris'">{{ INTERV_STATUTS[i.statut] ?? i.statut }}</span></td>
            </tr>
            <tr v-if="!maint.length"><td class="td text-center py-8" style="color: var(--discret);">Aucune intervention.</td></tr>
          </tbody>
        </table>
      </section>

      <!-- Campagnes d'inventaire (inventoriste, auditeur, admin) -->
      <section v-if="a('inventaires')" class="carte overflow-hidden">
        <div class="px-5 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--ligne);">
          <h2 class="text-sm font-bold">Inventaires</h2>
          <a href="/inventaires" class="text-xs font-medium hover:underline" style="color: var(--vert-700);">Tout voir →</a>
        </div>
        <table class="w-full">
          <tbody>
            <tr v-for="c in invs.slice(0, 5)" :key="c.id" class="tr-ligne">
              <td class="td">
                <div class="text-sm font-medium">{{ c.libelle }}</div>
                <div class="text-xs" style="color: var(--discret);">{{ c.date_debut }} → {{ c.date_fin }}</div>
              </td>
              <td class="td text-right"><span class="etiquette" :class="{ en_cours: 'et-bleu', cloturee: 'et-vert', planifiee: 'et-gris' }[c.statut] ?? 'et-gris'">{{ INV_STATUTS[c.statut] ?? c.statut }}</span></td>
            </tr>
            <tr v-if="!invs.length"><td class="td text-center py-8" style="color: var(--discret);">Aucune campagne.</td></tr>
          </tbody>
        </table>
      </section>

      <!-- Mouvements récents (gestionnaire, responsable, auditeur, admin) -->
      <section v-if="a('mouvements')" class="carte overflow-hidden lg:col-span-2">
        <div class="px-5 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--ligne);">
          <h2 class="text-sm font-bold">Mouvements récents</h2>
          <a href="/affectations" class="text-xs font-medium hover:underline" style="color: var(--vert-700);">Tout voir →</a>
        </div>
        <table class="w-full">
          <thead><tr><th class="th">Référence</th><th class="th">Type</th><th class="th">Date</th><th class="th">Statut</th></tr></thead>
          <tbody>
            <tr v-for="m in mouvements" :key="m.id" class="tr-ligne">
              <td class="td lien-code font-semibold" style="color: var(--vert-700);">{{ m.reference }}</td>
              <td class="td">{{ m.type }}</td>
              <td class="td" style="color: var(--discret);">{{ m.date_demande ?? '—' }}</td>
              <td class="td"><span class="etiquette et-gris">{{ MVT_STATUTS[m.statut] ?? m.statut }}</span></td>
            </tr>
            <tr v-if="!mouvements.length"><td colspan="4" class="td text-center py-8" style="color: var(--discret);">Aucun mouvement.</td></tr>
          </tbody>
        </table>
      </section>
    </div>
  </AppLayout>
</template>
