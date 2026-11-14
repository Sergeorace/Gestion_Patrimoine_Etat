<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { api } from '../../api';

const categories = ref([]), structures = ref([]), biens = ref([]);
const f = ref({
  designation: '', categorie_id: '', type_specifique: 'mobilier',
  structure_id: '', site_id: '', date_acquisition: '', mode_acquisition: 'achat',
  valeur_acquisition: 0, description: '',
});
const err = ref(''), loading = ref(false);

const TYPES = { immobilier: 'Immobilier', vehicule: 'Véhicule', informatique: 'Informatique', mobilier: 'Mobilier', autre: 'Autre' };
const MODES = { achat: 'Achat', don: 'Don', transfert: 'Transfert', construction: 'Construction', recuperation: 'Récupération' };

async function submit() {
  loading.value = true; err.value = '';
  try {
    const payload = { ...f.value, valeur_acquisition: Number(f.value.valeur_acquisition) || 0 };
    const r = await api.post('/assets', payload);
    router.visit(`/biens/${r.id ?? r.data?.id}`);
  } catch (e) { err.value = e.message; } finally { loading.value = false; }
}

onMounted(async () => {
  try {
    const [c, s, b] = await Promise.all([
      api.get('/organisation/categories'),
      api.get('/organisation/structures'),
      api.get('/assets?per_page=1'),
    ]);
    categories.value = Array.isArray(c) ? c : (c.data ?? []);
    structures.value = Array.isArray(s) ? s : (s.data ?? []);
  } catch { /* référentiels */ }
});
</script>

<template>
  <AppLayout title="Nouveau bien">
    <form @submit.prevent="submit" class="carte p-6 max-w-3xl grid grid-cols-1 md:grid-cols-2 gap-5">
      <div class="md:col-span-2">
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Désignation *</label>
        <input v-model="f.designation" required class="champ" placeholder="Ex. Ordinateur portable Dell Latitude">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Catégorie *</label>
        <select v-model="f.categorie_id" required class="champ">
          <option value="" disabled>Sélectionner…</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.libelle }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Type *</label>
        <select v-model="f.type_specifique" class="champ">
          <option v-for="(lib, k) in TYPES" :key="k" :value="k">{{ lib }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Structure</label>
        <select v-model="f.structure_id" class="champ">
          <option value="">—</option>
          <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.libelle }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Mode d'acquisition *</label>
        <select v-model="f.mode_acquisition" class="champ">
          <option v-for="(lib, k) in MODES" :key="k" :value="k">{{ lib }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Date d'acquisition *</label>
        <input v-model="f.date_acquisition" type="date" required class="champ">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Valeur d'acquisition (FCFA) *</label>
        <input v-model.number="f.valeur_acquisition" type="number" min="0" required class="champ">
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Description</label>
        <textarea v-model="f.description" rows="2" class="champ" placeholder="Caractéristiques, marque, modèle…"></textarea>
      </div>
      <div class="md:col-span-2 flex items-center gap-3">
        <p v-if="err" class="text-sm" style="color: var(--rouge-700);">{{ err }}</p>
        <button :disabled="loading" class="btn btn-primaire ml-auto">{{ loading ? 'Enregistrement…' : 'Enregistrer le bien' }}</button>
      </div>
    </form>
  </AppLayout>
</template>
