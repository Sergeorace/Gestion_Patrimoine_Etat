<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { api } from '../../api';

const page = usePage();
const id = page.props.assetId;
const bien = ref(null), historique = ref([]), categories = ref([]), err = ref('');

const ETATS = {
  disponible: ['Disponible', 'et-gris'],
  affecte: ['Affecté', 'et-vert'],
  en_maintenance: ['En maintenance', 'et-or'],
  sorti: ['Sorti', 'et-rouge'],
};
const ETAT_PHYSIQUE = { bon: 'Bon', moyen: 'Moyen', degrade: 'Dégradé', hors_service: 'Hors service' };
const STATUTS_MVT = { brouillon: 'Brouillon', validee: 'Validée', rejetee: 'Rejetée', appliquee: 'Appliquée' };

const catName = computed(() => categories.value.find(c => c.id === bien.value?.categorie_id)?.libelle ?? '—');

onMounted(async () => {
  try {
    const [b, m, c] = await Promise.all([
      api.get(`/assets/${id}`),
      api.get('/movements?per_page=100'),
      api.get('/organisation/categories'),
    ]);
    bien.value = b;
    const all = m.data ?? m ?? [];
    historique.value = all.filter(x => x.bien_id === id);
    categories.value = Array.isArray(c) ? c : (c.data ?? []);
  } catch (e) { err.value = e.message; }
});
</script>

<template>
  <AppLayout :title="bien?.designation ?? 'Fiche bien'">
    <p v-if="err" class="text-sm mb-4" style="color: var(--rouge-700);">{{ err }}</p>
    <div v-if="bien" class="max-w-3xl space-y-6">
      <div class="carte p-6">
        <div class="flex items-start justify-between gap-4 mb-5">
          <div>
            <h2 class="text-lg font-bold">{{ bien.designation }}</h2>
            <p class="lien-code mt-1" style="color: var(--discret);">{{ bien.code_inventaire }}</p>
          </div>
          <span class="etiquette" :class="(ETATS[bien.statut] ?? ['—', 'et-gris'])[1]">
            {{ (ETATS[bien.statut] ?? [bien.statut, 'et-gris'])[0] }}
          </span>
        </div>
        <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
          <div><dt class="kpi-label mb-1">Catégorie</dt><dd class="font-medium">{{ catName }}</dd></div>
          <div><dt class="kpi-label mb-1">Type</dt><dd class="font-medium">{{ bien.type_specifique ?? '—' }}</dd></div>
          <div><dt class="kpi-label mb-1">État physique</dt><dd class="font-medium">{{ ETAT_PHYSIQUE[bien.etat] ?? bien.etat ?? '—' }}</dd></div>
          <div><dt class="kpi-label mb-1">Valeur d'acquisition</dt><dd class="font-medium" style="font-variant-numeric: tabular-nums;">{{ (Number(bien.valeur_acquisition) || 0).toLocaleString('fr-FR') }} FCFA</dd></div>
          <div><dt class="kpi-label mb-1">Date d'acquisition</dt><dd class="font-medium">{{ bien.date_acquisition ?? '—' }}</dd></div>
          <div><dt class="kpi-label mb-1">Mode</dt><dd class="font-medium">{{ bien.mode_acquisition ?? '—' }}</dd></div>
        </dl>
        <p v-if="bien.description" class="text-sm mt-5 pt-4" style="color: var(--discret); border-top: 1px solid var(--ligne);">{{ bien.description }}</p>
      </div>

      <div class="carte overflow-x-auto">
        <div class="px-5 py-4 border-b" style="border-color: var(--ligne);">
          <h2 class="text-sm font-bold">Historique des mouvements</h2>
        </div>
        <table class="w-full">
          <thead>
            <tr><th class="th">Référence</th><th class="th">Type</th><th class="th">Date</th><th class="th">Statut</th></tr>
          </thead>
          <tbody>
            <tr v-for="m in historique" :key="m.id" class="tr-ligne">
              <td class="td lien-code font-semibold" style="color: var(--vert-700);">{{ m.reference }}</td>
              <td class="td">{{ m.type }}</td>
              <td class="td" style="color: var(--discret);">{{ m.date_demande ?? '—' }}</td>
              <td class="td"><span class="etiquette et-gris">{{ STATUTS_MVT[m.statut] ?? m.statut }}</span></td>
            </tr>
            <tr v-if="!historique.length">
              <td colspan="4" class="td text-center py-8" style="color: var(--discret);">Aucun mouvement enregistré.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
