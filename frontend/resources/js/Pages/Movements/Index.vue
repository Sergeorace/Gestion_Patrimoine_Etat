<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { api } from '../../api';
import { Link } from '@inertiajs/vue3';

const items = ref([]), err = ref('');
const biens = ref({}), structures = ref({});

const STATUTS = {
  brouillon: ['Brouillon', 'et-gris'],
  en_attente: ['En attente', 'et-or'],
  validee: ['Validée', 'et-vert'],
  appliquee: ['Appliquée', 'et-vert'],
  rejetee: ['Rejetée', 'et-rouge'],
  annulee: ['Annulée', 'et-gris'],
};
const TYPES = { entree: 'Entrée', affectation: 'Affectation', transfert: 'Transfert', sortie: 'Sortie' };

const bien = (id) => biens.value[id];
const struct = (id) => structures.value[id];
const statut = (s) => STATUTS[s] ?? [s ?? '—', 'et-gris'];

onMounted(async () => {
  try {
    const [m, a, s] = await Promise.all([
      api.get('/movements?per_page=50'),
      api.get('/assets?per_page=100'),
      api.get('/organisation/structures'),
    ]);
    items.value = m.data ?? m ?? [];
    for (const b of (a.data ?? a ?? [])) biens.value[b.id] = b;
    for (const x of (Array.isArray(s) ? s : (s.data ?? []))) structures.value[x.id] = x.libelle;
  } catch (e) { err.value = e.message; }
});
</script>

<template>
  <AppLayout title="Affectations">
    <div class="flex justify-between items-center mb-5">
      <p class="text-sm" style="color: var(--discret);">{{ items.length }} mouvement{{ items.length > 1 ? 's' : '' }} enregistré{{ items.length > 1 ? 's' : '' }}</p>
      <Link href="/mouvements/nouveau" class="btn btn-primaire">+ Nouvelle affectation</Link>
    </div>

    <p v-if="err" class="text-sm mb-3" style="color: var(--rouge-700);">{{ err }}</p>

    <div class="carte overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th class="th">Référence</th>
            <th class="th">Bien</th>
            <th class="th">Type</th>
            <th class="th">Service – Ministère</th>
            <th class="th">Date</th>
            <th class="th">Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in items" :key="m.id" class="tr-ligne">
            <td class="td lien-code font-semibold" style="color: var(--vert-700);">{{ m.reference }}</td>
            <td class="td">
              <div class="font-medium">{{ bien(m.bien_id)?.designation ?? '—' }}</div>
              <div class="lien-code" style="color: var(--discret);">{{ bien(m.bien_id)?.code_inventaire ?? '' }}</div>
            </td>
            <td class="td">{{ TYPES[m.type] ?? m.type }}</td>
            <td class="td" style="color: var(--discret);">{{ struct(m.structure_dest_id) ?? '—' }}</td>
            <td class="td" style="color: var(--discret);">{{ m.date_effet ?? m.date_demande ?? '—' }}</td>
            <td class="td"><span class="etiquette" :class="statut(m.statut)[1]">{{ statut(m.statut)[0] }}</span></td>
          </tr>
          <tr v-if="!items.length">
            <td colspan="6" class="td text-center py-10" style="color: var(--discret);">Aucune affectation enregistrée.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
