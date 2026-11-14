<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { api } from '../../api';
import { Link } from '@inertiajs/vue3';

const items = ref([]), total = ref(0), page = ref(1), q = ref(''), err = ref('');
const categories = ref([]), structures = ref([]);

const ETATS = {
  disponible: ['Disponible', 'et-gris'],
  affecte: ['Affecté', 'et-vert'],
  en_maintenance: ['En maintenance', 'et-or'],
  sorti: ['Sorti', 'et-rouge'],
};
const TYPES = { immobilier: 'Immobilier', vehicule: 'Véhicule', informatique: 'Informatique', mobilier: 'Mobilier', autre: 'Autre' };

const catName = (id) => categories.value.find(c => c.id === id)?.libelle ?? '—';
const structName = (id) => structures.value.find(s => s.id === id)?.libelle ?? null;
const etat = (s) => ETATS[s] ?? [s ?? '—', 'et-gris'];
const fcfa = (n) => (Number(n) || 0).toLocaleString('fr-FR');

async function load(p = 1) {
  try {
    const r = await api.get(`/assets?search=${encodeURIComponent(q.value)}&page=${p}&per_page=20`);
    items.value = r.data ?? r; total.value = r.total ?? 0; page.value = p;
  } catch (e) { err.value = e.message; }
}
onMounted(async () => {
  load();
  try {
    const [c, s] = await Promise.all([api.get('/organisation/categories'), api.get('/organisation/structures')]);
    categories.value = Array.isArray(c) ? c : (c.data ?? []);
    structures.value = Array.isArray(s) ? s : (s.data ?? []);
  } catch { /* référentiels optionnels */ }
});
</script>

<template>
  <AppLayout title="Biens">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
      <p class="text-sm" style="color: var(--discret);">{{ total }} bien{{ total > 1 ? 's' : '' }} recensé{{ total > 1 ? 's' : '' }}</p>
      <div class="flex items-center gap-2">
        <form @submit.prevent="load(1)" class="flex gap-2">
          <input v-model="q" placeholder="Rechercher (code, désignation)…" class="champ w-72">
          <button class="btn btn-secondaire">Rechercher</button>
        </form>
        <Link href="/biens/nouveau" class="btn btn-primaire">+ Nouveau bien</Link>
      </div>
    </div>

    <p v-if="err" class="text-sm mb-3" style="color: var(--rouge-700);">{{ err }}</p>

    <div class="carte overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th class="th">Code</th>
            <th class="th">Désignation</th>
            <th class="th">Catégorie</th>
            <th class="th">Structure</th>
            <th class="th text-right">Valeur (FCFA)</th>
            <th class="th">État</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in items" :key="b.id" class="tr-ligne">
            <td class="td"><Link :href="`/biens/${b.id}`" class="lien-code font-semibold" style="color: var(--vert-700);">{{ b.code_inventaire }}</Link></td>
            <td class="td font-medium">{{ b.designation }}</td>
            <td class="td" style="color: var(--discret);">{{ TYPES[b.type_specifique] ?? catName(b.categorie_id) }}</td>
            <td class="td" style="color: var(--discret);">{{ structName(b.structure_id) ?? '—' }}</td>
            <td class="td text-right font-medium" style="font-variant-numeric: tabular-nums;">{{ fcfa(b.valeur_acquisition) }}</td>
            <td class="td"><span class="etiquette" :class="etat(b.statut)[1]">{{ etat(b.statut)[0] }}</span></td>
          </tr>
          <tr v-if="!items.length">
            <td colspan="6" class="td text-center py-10" style="color: var(--discret);">Aucun bien recensé.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex items-center gap-3 text-sm">
      <button :disabled="page <= 1" @click="load(page - 1)" class="btn btn-secondaire !py-1.5 !px-3">Précédent</button>
      <span style="color: var(--discret);">Page {{ page }}</span>
      <button @click="load(page + 1)" class="btn btn-secondaire !py-1.5 !px-3">Suivant</button>
    </div>
  </AppLayout>
</template>
