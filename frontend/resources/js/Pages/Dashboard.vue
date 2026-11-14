<script setup>
import { onMounted, ref, computed } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { api } from '../api';

const dash = ref(null), stats = ref(null), categories = ref([]);
const maint = ref([]), invs = ref([]), err = ref('');

const fcfa = (n) => (Number(n) || 0).toLocaleString('fr-FR') + ' FCFA';

const catName = (id) => categories.value.find(c => c.id === id)?.libelle ?? 'Autre';

const cats = computed(() => {
  const rows = stats.value?.par_categorie ?? [];
  const max = Math.max(1, ...rows.map(r => Number(r.n)));
  return rows.map(r => ({ ...r, libelle: catName(r.categorie_id), pct: Math.round((Number(r.n) / max) * 100) }));
});

const alertes = computed(() => [
  { label: 'Maintenances à planifier', n: maint.value.filter(i => ['planifiee', 'declaree'].includes(i.statut)).length, href: '/maintenance', ton: 'et-or' },
  { label: 'Inventaires en cours', n: invs.value.filter(c => c.statut === 'en_cours').length, href: '/inventaires', ton: 'et-bleu' },
  { label: 'Biens sans affectation', n: Number(stats.value?.par_statut?.disponible ?? 0), href: '/biens', ton: 'et-gris' },
]);

onMounted(async () => {
  try {
    const [d, s, c, m, iv] = await Promise.all([
      api.get('/reports/dashboard'),
      api.get('/assets/stats'),
      api.get('/organisation/categories'),
      api.get('/maintenance/interventions?per_page=100'),
      api.get('/inventories?per_page=100'),
    ]);
    dash.value = d; stats.value = s;
    categories.value = Array.isArray(c) ? c : (c.data ?? []);
    maint.value = m.data ?? m ?? [];
    invs.value = iv.data ?? iv ?? [];
  } catch (e) { err.value = e.message; }
});
</script>

<template>
  <AppLayout title="Tableau de bord">
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- Alertes -->
      <section class="carte p-5">
        <h2 class="text-sm font-bold mb-4">Alertes</h2>
        <ul class="divide-y" style="--tw-divide-opacity:1; border-color: #efede3;">
          <li v-for="a in alertes" :key="a.label">
            <a :href="a.href" class="flex items-center justify-between py-3 group">
              <span class="flex items-center gap-2.5 text-sm">
                <span class="etiquette" :class="a.ton">{{ a.n }}</span>
                {{ a.label }}
              </span>
              <svg class="w-4 h-4 opacity-0 group-hover:opacity-60 transition-opacity" style="color: var(--discret);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </a>
          </li>
        </ul>
      </section>

      <!-- Biens par catégorie -->
      <section class="carte p-5 lg:col-span-2">
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
    </div>
  </AppLayout>
</template>
