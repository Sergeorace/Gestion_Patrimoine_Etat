<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { api } from '../api';
import { loadAuth, peutAction } from '../authz';

const items = ref([]), biens = ref({}), err = ref('');
const showForm = ref(false), saving = ref(false), formErr = ref('');
const form = ref({ bien_id: '', type: 'preventive', description: '', date_prevue: '', cout_main_oeuvre: '', cout_pieces: 0 });

const TYPES = { preventive: 'Préventive', curative: 'Corrective', panne: 'Panne' };
const STATUTS = {
  declaree: ['Déclarée', 'et-or'],
  planifiee: ['Planifiée', 'et-bleu'],
  en_cours: ['En cours', 'et-or'],
  terminee: ['Terminée', 'et-vert'],
  annulee: ['Annulée', 'et-gris'],
};
const bien = (id) => biens.value[id];
const statut = (s) => STATUTS[s] ?? [s ?? '—', 'et-gris'];
const fcfa = (n) => (Number(n) || 0).toLocaleString('fr-FR');
const cout = (i) => Number(i.cout_main_oeuvre ?? 0) + Number(i.cout_pieces ?? 0);

async function load() {
  try {
    const [m, a] = await Promise.all([
      api.get('/maintenance/interventions?per_page=100'),
      api.get('/assets?per_page=100'),
    ]);
    items.value = m.data ?? m ?? [];
    for (const b of (a.data ?? a ?? [])) biens.value[b.id] = b;
  } catch (e) { err.value = e.message; }
}

async function save() {
  saving.value = true; formErr.value = '';
  try {
    await api.post('/maintenance/interventions', {
      bien_id: form.value.bien_id,
      type: form.value.type,
      description: form.value.description || null,
      date_prevue: form.value.date_prevue,
      cout_main_oeuvre: form.value.cout_main_oeuvre === '' ? null : Number(form.value.cout_main_oeuvre),
    });
    showForm.value = false;
    form.value = { bien_id: '', type: 'preventive', description: '', date_prevue: '', cout_main_oeuvre: '', cout_pieces: 0 };
    await load();
  } catch (e) { formErr.value = e.message; } finally { saving.value = false; }
}

onMounted(async () => { loadAuth(); await load(); });
</script>

<template>
  <AppLayout title="Maintenance">
    <div class="flex justify-between items-center mb-5">
      <p class="text-sm" style="color: var(--discret);">{{ items.length }} intervention{{ items.length > 1 ? 's' : '' }}</p>
      <button v-if="peutAction('maintenance.gerer')" class="btn btn-primaire" @click="showForm = !showForm">+ Planifier une intervention</button>
    </div>

    <!-- Formulaire de planification -->
    <form v-if="showForm" @submit.prevent="save" class="carte p-5 mb-5 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Bien</label>
        <select v-model="form.bien_id" required class="champ">
          <option value="" disabled>Sélectionner…</option>
          <option v-for="(b, id) in biens" :key="id" :value="id">{{ b.code_inventaire }} — {{ b.designation }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Type</label>
        <select v-model="form.type" class="champ">
          <option value="preventive">Préventive</option>
          <option value="curative">Corrective</option>
          <option value="panne">Panne</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Prévue le</label>
        <input v-model="form.date_prevue" type="date" required class="champ">
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Description</label>
        <input v-model="form.description" class="champ" placeholder="Objet de l'intervention…">
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Coût MO (FCFA)</label>
        <input v-model="form.cout_main_oeuvre" type="number" min="0" class="champ" placeholder="0">
      </div>
      <div class="md:col-span-3 flex items-center gap-3">
        <p v-if="formErr" class="text-sm" style="color: var(--rouge-700);">{{ formErr }}</p>
        <button :disabled="saving" class="btn btn-primaire ml-auto">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
      </div>
    </form>

    <p v-if="err" class="text-sm mb-3" style="color: var(--rouge-700);">{{ err }}</p>

    <div class="carte overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th class="th">Bien</th>
            <th class="th">Type</th>
            <th class="th">Prévue le</th>
            <th class="th text-right">Coût (FCFA)</th>
            <th class="th">Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in items" :key="i.id" class="tr-ligne">
            <td class="td">
              <div class="font-medium">{{ bien(i.bien_id)?.designation ?? '—' }}</div>
              <div class="lien-code" style="color: var(--discret);">{{ bien(i.bien_id)?.code_inventaire ?? '' }}</div>
            </td>
            <td class="td">{{ TYPES[i.type] ?? i.type }}</td>
            <td class="td" style="color: var(--discret);">{{ i.date_prevue ?? '—' }}</td>
            <td class="td text-right font-medium" style="font-variant-numeric: tabular-nums;">{{ fcfa(cout(i)) }}</td>
            <td class="td"><span class="etiquette" :class="statut(i.statut)[1]">{{ statut(i.statut)[0] }}</span></td>
          </tr>
          <tr v-if="!items.length">
            <td colspan="5" class="td text-center py-10" style="color: var(--discret);">Aucune intervention planifiée.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
