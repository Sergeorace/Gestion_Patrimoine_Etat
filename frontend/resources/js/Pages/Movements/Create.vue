<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { api } from '../../api';
import { loadAuth, peutAction } from '../../authz';

const biens = ref([]), structures = ref([]);
const f = ref({ type: 'affectation', bien_id: '', structure_dest_id: '', motif: '', motif_sortie_id: '', date_effet: '' });
const err = ref(''), loading = ref(false);
const TYPES = { affectation: 'Affectation', transfert: 'Transfert', entree: 'Entrée', sortie: 'Sortie' };
const MOTIFS_SORTIE = [
  ['reforme', 'Réforme'], ['cession', 'Cession'], ['destruction', 'Destruction'], ['perte', 'Perte'],
];

async function submit() {
  loading.value = true; err.value = '';
  try {
    const payload = { ...f.value };
    if (!payload.date_effet) delete payload.date_effet;
    if (!payload.motif_sortie_id) delete payload.motif_sortie_id;
    await api.post('/movements', payload);
    router.visit('/affectations');
  } catch (e) { err.value = e.message; } finally { loading.value = false; }
}

onMounted(async () => {
  await loadAuth();
  if (! peutAction('mouvement.creer')) { router.visit('/affectations'); return; }
  try {
    const [b, s] = await Promise.all([api.get('/assets?per_page=100'), api.get('/organisation/structures')]);
    biens.value = b.data ?? b ?? [];
    structures.value = Array.isArray(s) ? s : (s.data ?? []);
  } catch (e) { err.value = e.message; }
});
</script>

<template>
  <AppLayout title="Nouvelle affectation">
    <form @submit.prevent="submit" class="carte p-6 max-w-2xl space-y-5">
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Type de mouvement *</label>
        <div class="flex gap-2 flex-wrap">
          <button v-for="(lib, k) in TYPES" :key="k" type="button" @click="f.type = k"
                  class="etiquette cursor-pointer !px-3 !py-1.5 !text-sm"
                  :class="f.type === k ? 'et-vert' : 'et-gris'">{{ lib }}</button>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Bien *</label>
        <select v-model="f.bien_id" required class="champ">
          <option value="" disabled>Sélectionner un bien…</option>
          <option v-for="b in biens" :key="b.id" :value="b.id">{{ b.code_inventaire }} — {{ b.designation }}</option>
        </select>
      </div>
      <div v-if="f.type !== 'entree'">
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Structure de destination *</label>
        <select v-model="f.structure_dest_id" required class="champ">
          <option value="" disabled>Sélectionner…</option>
          <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.libelle }}</option>
        </select>
      </div>
      <div v-if="f.type === 'sortie'">
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Motif de sortie</label>
        <select v-model="f.motif_sortie_id" class="champ">
          <option value="">—</option>
          <option v-for="[v, lib] in MOTIFS_SORTIE" :key="v" :value="v">{{ lib }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Date d'effet</label>
        <input v-model="f.date_effet" type="date" class="champ">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Motif / commentaire</label>
        <textarea v-model="f.motif" rows="3" class="champ" placeholder="Objet de la demande…"></textarea>
      </div>
      <div class="flex items-center gap-3">
        <p v-if="err" class="text-sm" style="color: var(--rouge-700);">{{ err }}</p>
        <button :disabled="loading" class="btn btn-primaire ml-auto">{{ loading ? 'Envoi…' : 'Soumettre pour validation' }}</button>
      </div>
    </form>
  </AppLayout>
</template>
