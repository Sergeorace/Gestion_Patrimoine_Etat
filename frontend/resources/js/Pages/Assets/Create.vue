<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { api } from '../../api';
import { loadAuth, peutAction } from '../../authz';

const categories = ref([]), structures = ref([]), biens = ref([]);
const f = ref({
  designation: '', categorie_id: '', type_specifique: 'mobilier',
  structure_id: '', site_id: '', date_acquisition: '', mode_acquisition: 'achat',
  date_mise_en_service: '', reference_decision: '',
  valeur_acquisition: 0, description: '',
  nature: 'corporelle', compte_comptable: '', immobilisation: true,
});
const err = ref(''), loading = ref(false);

const TYPES = { immobilier: 'Immobilier', vehicule: 'Véhicule', informatique: 'Informatique', mobilier: 'Mobilier', autre: 'Autre' };
const MODES = { achat: 'Achat', don: 'Don', transfert: 'Transfert', construction: 'Construction', recuperation: 'Récupération' };
const NATURES = { incorporelle: 'Incorporelle', corporelle: 'Corporelle', financiere: 'Financière' };
// Comptes OHADA (classe 2 - actif immobilise)
const COMPTES_OHADA = [
  ['21', 'Incorporelles'], ['22', 'Terrains'], ['231', 'Constructions'],
  ['232', 'Installations techniques'], ['244', 'Matériel et outillage'],
  ['245', 'Matériel de transport'], ['246', 'Matériel informatique'],
  ['248', 'Autres immobilisations corporelles'], ['26', 'Participations'], ['27', 'Prêts'],
];

async function submit() {
  loading.value = true; err.value = '';
  try {
    const payload = { ...f.value, valeur_acquisition: Number(f.value.valeur_acquisition) || 0 };
    const r = await api.post('/assets', payload);
    router.visit(`/biens/${r.id ?? r.data?.id}`);
  } catch (e) { err.value = e.message; } finally { loading.value = false; }
}

onMounted(async () => {
  await loadAuth();
  if (! peutAction('bien.creer')) { router.visit('/biens'); return; }
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
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Mise en service</label>
        <input v-model="f.date_mise_en_service" type="date" class="champ" title="Début des dotations aux amortissements">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Réf. décision / marché</label>
        <input v-model="f.reference_decision" class="champ" placeholder="N° arrêté, marché, bon de commande">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Valeur d'acquisition (FCFA) *</label>
        <input v-model.number="f.valeur_acquisition" type="number" min="0" required class="champ">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Nature comptable</label>
        <select v-model="f.nature" class="champ">
          <option v-for="(lib, k) in NATURES" :key="k" :value="k">{{ lib }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Compte OHADA</label>
        <select v-model="f.compte_comptable" class="champ">
          <option value="">—</option>
          <option v-for="[c, l] in COMPTES_OHADA" :key="c" :value="c">{{ c }} — {{ l }}</option>
        </select>
      </div>
      <div class="flex items-end pb-1">
        <label class="flex items-center gap-2 text-sm cursor-pointer">
          <input v-model="f.immobilisation" type="checkbox" class="accent-[#176045]">
          Immobilisation (sinon charge / consommation)
        </label>
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
