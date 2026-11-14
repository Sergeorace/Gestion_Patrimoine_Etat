<script setup>
import { onMounted, ref, computed } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { api } from '../api';
import { authz, loadAuth, pageAutorisee } from '../authz';

const dash = ref(null), stats = ref(null), invs = ref([]);
const user = ref(null), err = ref('');

const fcfa = (n) => (Number(n) || 0).toLocaleString('fr-FR') + ' FCFA';

// ---------------------------------------------------------------------------
// Tableau de bord épuré : indicateurs clés + alertes, adaptée au rôle.
// ---------------------------------------------------------------------------
const role = computed(() => user.value?.roles?.[0] ?? 'admin');

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

// Alertes utiles à chaque rôle — aucun appel API interdit (403).
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

onMounted(async () => {
  await loadAuth();
  try {
    user.value = authz.user ?? await api.get('/auth/me');
  } catch { /* silencieux */ }
  try {
    const [d, s] = await Promise.all([
      api.get('/reports/dashboard'),
      api.get('/assets/stats'),
    ]);
    dash.value = d; stats.value = s;
  } catch (e) { err.value = e.message; }

  // Inventaires en cours : uniquement pour les rôles qui ont accès à l'écran.
  if (pageAutorisee('/inventaires')) {
    api.get('/inventories?per_page=100')
      .then(r => { invs.value = r.data ?? r ?? []; })
      .catch(() => {});
  }
});
</script>

<template>
  <AppLayout title="Tableau de bord">
    <p class="text-sm mb-5 -mt-2" style="color: var(--discret);">{{ ROLE_TITRE[role] ?? ROLE_TITRE.admin }}</p>
    <p v-if="err" class="text-sm mb-4" style="color: var(--rouge-700);">{{ err }}</p>

    <!-- Indicateurs clés -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
      <div class="carte p-5">
        <div class="kpi-label">Valeur totale</div>
        <div class="kpi-valeur mt-1" style="color: var(--vert-800);">{{ fcfa(dash?.patrimoine?.valeur_totale) }}</div>
      </div>
      <div class="carte p-5">
        <div class="kpi-label">VNC totale</div>
        <div class="kpi-valeur mt-1" style="color: var(--vert-800);">{{ fcfa(dash?.valorisation?.total_vnc) }}</div>
      </div>
      <div class="carte p-5">
        <div class="kpi-label">Biens recensés</div>
        <div class="kpi-valeur mt-1" style="color: var(--vert-800);">{{ dash?.patrimoine?.nombre_biens ?? stats?.total ?? 0 }}</div>
      </div>
    </section>

    <!-- Alertes (libellés et compteurs selon le rôle) -->
    <section v-if="alertes.length" class="carte p-5 max-w-2xl">
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
  </AppLayout>
</template>
