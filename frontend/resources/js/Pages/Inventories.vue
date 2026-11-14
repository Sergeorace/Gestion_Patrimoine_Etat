<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { api } from '../api';
import { loadAuth, peutAction } from '../authz';

const items = ref([]), totalBiens = ref(0), structures = ref({}), err = ref('');
const showForm = ref(false), saving = ref(false), formErr = ref('');
const form = ref({ libelle: '', structure_id: '', date_debut: '', date_fin: '' });
const constats = ref({});

const STATUTS = {
  planifiee: ['Planifiée', 'et-gris'],
  en_cours: ['En cours', 'et-bleu'],
  cloturee: ['Clôturée', 'et-vert'],
};
const statut = (s) => STATUTS[s] ?? [s ?? '—', 'et-gris'];
const periode = (c) => `${c.date_debut ?? '?'} → ${c.date_fin ?? '?'}`;
const controls = (c) => constats.value[c.id] != null ? `${constats.value[c.id]} / ${totalBiens.value || '—'}` : '—';

async function load() {
  try {
    const [inv, a, s] = await Promise.all([
      api.get('/inventories?per_page=50'),
      api.get('/assets?per_page=1'),
      api.get('/organisation/structures'),
    ]);
    items.value = inv.data ?? inv ?? [];
    totalBiens.value = a.total ?? (a.data?.length ?? 0);
    for (const x of (Array.isArray(s) ? s : (s.data ?? []))) structures.value[x.id] = x.libelle;
    // Nombre de constats par campagne
    await Promise.all(items.value.map(async (c) => {
      try {
        const r = await api.get(`/inventories/${c.id}/constats`);
        constats.value[c.id] = Array.isArray(r) ? r.length : (r.data?.length ?? r.total ?? 0);
      } catch { constats.value[c.id] = null; }
    }));
  } catch (e) { err.value = e.message; }
}

async function save() {
  saving.value = true; formErr.value = '';
  try {
    await api.post('/inventories', {
      libelle: form.value.libelle,
      structure_id: form.value.structure_id || null,
      date_debut: form.value.date_debut,
      date_fin: form.value.date_fin,
    });
    showForm.value = false;
    form.value = { libelle: '', structure_id: '', date_debut: '', date_fin: '' };
    await load();
  } catch (e) { formErr.value = e.message; } finally { saving.value = false; }
}

onMounted(async () => { loadAuth(); await load(); });
</script>

<template>
  <AppLayout title="Inventaires">
    <div class="flex justify-between items-center mb-5">
      <p class="text-sm" style="color: var(--discret);">{{ items.length }} campagne{{ items.length > 1 ? 's' : '' }}</p>
      <button v-if="peutAction('inventaire.ecrire')" class="btn btn-primaire" @click="showForm = !showForm">+ Nouvel inventaire</button>
    </div>

    <form v-if="showForm" @submit.prevent="save" class="carte p-5 mb-5 grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="md:col-span-2">
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Intitulé</label>
        <input v-model="form.libelle" required class="champ" placeholder="Ex. Inventaire annuel 2026">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Structure</label>
        <select v-model="form.structure_id" class="champ">
          <option value="">Toutes</option>
          <option v-for="(lib, id) in structures" :key="id" :value="id">{{ lib }}</option>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Du</label>
          <input v-model="form.date_debut" type="date" required class="champ">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Au</label>
          <input v-model="form.date_fin" type="date" required class="champ">
        </div>
      </div>
      <div class="md:col-span-4 flex items-center gap-3">
        <p v-if="formErr" class="text-sm" style="color: var(--rouge-700);">{{ formErr }}</p>
        <button :disabled="saving" class="btn btn-primaire ml-auto">{{ saving ? 'Enregistrement…' : 'Créer la campagne' }}</button>
      </div>
    </form>

    <p v-if="err" class="text-sm mb-3" style="color: var(--rouge-700);">{{ err }}</p>

    <div class="carte overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th class="th">Campagne</th>
            <th class="th">Période</th>
            <th class="th">Périmètre</th>
            <th class="th">Biens contrôlés</th>
            <th class="th">Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in items" :key="c.id" class="tr-ligne">
            <td class="td">
              <div class="font-medium">{{ c.libelle }}</div>
              <div class="lien-code" style="color: var(--discret);">{{ c.code }}</div>
            </td>
            <td class="td" style="color: var(--discret);">{{ periode(c) }}</td>
            <td class="td" style="color: var(--discret);">{{ c.structure_id ? (structures[c.structure_id] ?? '—') : 'National' }}</td>
            <td class="td" style="font-variant-numeric: tabular-nums;">{{ controls(c) }}</td>
            <td class="td"><span class="etiquette" :class="statut(c.statut)[1]">{{ statut(c.statut)[0] }}</span></td>
          </tr>
          <tr v-if="!items.length">
            <td colspan="5" class="td text-center py-10" style="color: var(--discret);">Aucune campagne d'inventaire.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
