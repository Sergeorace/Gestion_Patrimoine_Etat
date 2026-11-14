<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { api, apiBase } from '../api';

const d = ref(null), items = ref([]), err = ref(''), notice = ref('');
const busy = ref('');

const TYPES_RAPPORTS = [
  { type: 'inventaire_global', titre: 'État du patrimoine', desc: 'Inventaire complet des biens, leur état et leur localisation.' },
  { type: 'par_structure', titre: 'Biens par structure', desc: 'Répartition des biens par catégorie et par service.' },
  { type: 'valorisation', titre: 'Valeurs et VNC', desc: 'Valeur d\'acquisition et valeur nette comptable des biens.' },
  { type: 'mouvements', titre: 'Mouvements', desc: 'Historique des affectations, transferts et sorties.' },
  { type: 'maintenance', titre: 'Maintenance', desc: 'Interventions réalisées et à venir, avec les coûts.' },
];
const TYPES = Object.fromEntries(TYPES_RAPPORTS.map(t => [t.type, t.titre]));
const FORMATS = { csv: 'CSV', pdf: 'PDF', xlsx: 'Excel' };

async function load() {
  try {
    const [dash, r] = await Promise.all([api.get('/reports/dashboard'), api.get('/reports?per_page=30')]);
    d.value = dash; items.value = r.data ?? r ?? [];
  } catch (e) { err.value = e.message; }
}

async function generer(type, format) {
  busy.value = `${type}-${format}`; notice.value = '';
  try {
    const r = await api.post('/reports/generate', { type, format });
    if (r.telechargement) {
      notice.value = 'Rapport CSV généré — voir la liste ci-dessous.';
    } else {
      notice.value = r.message ?? 'Génération lancée.';
    }
    await load();
  } catch (e) { notice.value = e.message; } finally { busy.value = ''; }
}

const fcfa = (n) => (Number(n) || 0).toLocaleString('fr-FR');
onMounted(load);
</script>

<template>
  <AppLayout title="Rapports">
    <p v-if="err" class="text-sm mb-4" style="color: var(--rouge-700);">{{ err }}</p>
    <p v-if="notice" class="text-sm mb-4 px-4 py-2.5 rounded-lg" style="background: var(--vert-100); color: var(--vert-700);">{{ notice }}</p>

    <!-- Synthèse valeur -->
    <section class="carte p-6 mb-6 flex flex-wrap items-center justify-between gap-6">
      <div>
        <h2 class="text-sm font-bold">Valeurs et VNC</h2>
        <p class="text-xs mt-1" style="color: var(--discret);">Valeur d'acquisition et valeur nette comptable du patrimoine.</p>
      </div>
      <div class="flex gap-10 text-right">
        <div>
          <div class="kpi-label">Valeur d'acquisition</div>
          <div class="text-xl font-bold" style="color: var(--vert-800);">{{ fcfa(d?.patrimoine?.valeur_totale) }} <span class="text-xs font-normal" style="color: var(--discret);">FCFA</span></div>
        </div>
        <div>
          <div class="kpi-label">VNC</div>
          <div class="text-xl font-bold" style="color: var(--vert-800);">{{ fcfa(d?.valorisation?.total_vnc) }} <span class="text-xs font-normal" style="color: var(--discret);">FCFA</span></div>
        </div>
        <div>
          <div class="kpi-label">Amortissements</div>
          <div class="text-xl font-bold" style="color: var(--vert-800);">{{ fcfa(d?.valorisation?.total_amortissements) }} <span class="text-xs font-normal" style="color: var(--discret);">FCFA</span></div>
        </div>
      </div>
    </section>

    <!-- Rapports à générer -->
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
      <div v-for="t in TYPES_RAPPORTS" :key="t.type" class="carte p-5 flex flex-col">
        <h3 class="text-sm font-bold">{{ t.titre }}</h3>
        <p class="text-xs mt-1 mb-4 flex-1" style="color: var(--discret);">{{ t.desc }}</p>
        <div class="flex gap-2">
          <button v-for="(label, fmt) in FORMATS" :key="fmt"
                  :disabled="!!busy"
                  @click="generer(t.type, fmt)"
                  class="btn btn-secondaire !py-1.5 !px-3 !text-xs">
            {{ busy === `${t.type}-${fmt}` ? '…' : `Générer ${label}` }}
          </button>
        </div>
      </div>
    </section>

    <!-- Rapports générés -->
    <section class="carte overflow-x-auto">
      <div class="px-5 py-4 border-b" style="border-color: var(--ligne);">
        <h2 class="text-sm font-bold">Rapports générés</h2>
      </div>
      <table class="w-full">
        <thead>
          <tr>
            <th class="th">Type</th>
            <th class="th">Format</th>
            <th class="th">Généré le</th>
            <th class="th text-right">Téléchargement</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in items" :key="r.id" class="tr-ligne">
            <td class="td font-medium">{{ TYPES[r.type] ?? r.type }}</td>
            <td class="td" style="color: var(--discret);">{{ r.format?.toUpperCase() }}</td>
            <td class="td" style="color: var(--discret);">{{ r.genere_le ?? r.created_at ?? '—' }}</td>
            <td class="td text-right">
              <a :href="`${apiBase}/reports/${r.id}/download`" class="text-sm font-medium hover:underline" style="color: var(--vert-700);">CSV ↓</a>
            </td>
          </tr>
          <tr v-if="!items.length">
            <td colspan="4" class="td text-center py-10" style="color: var(--discret);">Aucun rapport généré.</td>
          </tr>
        </tbody>
      </table>
    </section>
  </AppLayout>
</template>
