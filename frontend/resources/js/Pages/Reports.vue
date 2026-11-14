<script setup>
import { computed, onMounted, ref } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { api, apiBase, getToken } from '../api';
import { loadAuth, peutAction } from '../authz';

const d = ref(null), items = ref([]), err = ref(''), notice = ref('');
const busy = ref('');

// ---- Filtres communs ----
const filtres = ref({
  region: '', prefecture: '', commune: '',
  domaine: '', secteur: '', service: '', etat: '', du: '', au: '',
});
const sites = ref([]), structures = ref([]);

const DOMAINES = [
  ['domaine_public_immobilier', 'Domaine public immobilier'],
  ['domaine_prive_immobilier', 'Domaine privé immobilier'],
  ['education', 'Éducation'],
  ['sante', 'Santé'],
  ['infrastructures', 'Infrastructures'],
  ['secteurs_productifs', 'Secteurs productifs'],
  ['biens_meubles', 'Biens meubles'],
  ['patrimoine_culturel', 'Patrimoine culturel'],
  ['participations', 'Participations'],
];
const ETATS = [
  ['en_service', 'En service'],
  ['en_maintenance', 'En maintenance'],
  ['reforme', 'Réformé'],
];

const regions = computed(() => [...new Set(sites.value.map(s => s.region).filter(Boolean))].sort());
const prefectures = computed(() => [...new Set(sites.value.filter(s => !filtres.value.region || s.region === filtres.value.region).map(s => s.prefecture).filter(Boolean))].sort());
const communes = computed(() => [...new Set(sites.value.filter(s => (!filtres.value.region || s.region === filtres.value.region) && (!filtres.value.prefecture || s.prefecture === filtres.value.prefecture)).map(s => s.commune).filter(Boolean))].sort());

// Secteurs connus (renseignes sur les biens)
const SECTEURS = [
  'Administration centrale', 'Transport et logistique', "Systèmes d'information",
  'Mobilier de bureau', 'Équipements pédagogiques', 'Santé', 'Infrastructures',
  'Secteurs productifs', 'Patrimoine culturel',
];

function reset() {
  filtres.value = { region: '', prefecture: '', commune: '', domaine: '', secteur: '', service: '', etat: '', du: '', au: '' };
}

function queryString() {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries(filtres.value)) if (v) p.set(k, v);
  return p.toString();
}

const RAPPORTS = [
  { type: 'patrimoine', titre: 'État du patrimoine', desc: 'Liste des biens par domaine, secteur et service, regroupés par région.' },
  { type: 'valeurs-vnc', titre: 'Valeurs et VNC', desc: 'Valeur d\'acquisition, amortissement cumulé et valeur nette comptable.' },
  { type: 'maintenances', titre: 'Maintenances', desc: 'Historique et coûts des interventions de maintenance.' },
  { type: 'inventaires', titre: 'Inventaires', desc: 'Écarts constatés par campagne d\'inventaire.' },
];

async function exporter(type, format) {
  busy.value = `${type}-${format}`; notice.value = '';
  try {
    const qs = queryString();
    const res = await fetch(`${apiBase}/reports/export/${type}?format=${format}${qs ? '&' + qs : ''}`, {
      headers: { Authorization: `Bearer ${getToken()}` },
    });
    if (! res.ok) {
      const j = await res.json().catch(() => ({}));
      throw new Error(j.error?.message || j.message || `Erreur ${res.status}`);
    }
    const blob = await res.blob();
    if (blob.size < 1000) throw new Error('Aucun résultat pour ces filtres.');
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `rapport-${type}-${new Date().toISOString().slice(0, 10)}.${format === 'pdf' ? 'pdf' : 'xlsx'}`;
    a.click();
    URL.revokeObjectURL(url);
    await load();
  } catch (e) { notice.value = e.message; }
  finally { busy.value = ''; }
}

const TYPES_RAPPORTS = [
  { type: 'inventaire_global', titre: 'État du patrimoine (CSV)', desc: 'Inventaire complet des biens — export CSV historique.' },
  { type: 'par_structure', titre: 'Biens par structure', desc: 'Répartition des biens par catégorie et par service.' },
  { type: 'valorisation', titre: 'Valeurs et VNC (CSV)', desc: 'Valeur d\'acquisition et VNC — export CSV.' },
  { type: 'mouvements', titre: 'Mouvements', desc: 'Historique des affectations, transferts et sorties.' },
  { type: 'maintenance', titre: 'Maintenance', desc: 'Interventions réalisées et à venir, avec les coûts.' },
];
const TYPES = Object.fromEntries(TYPES_RAPPORTS.map(t => [t.type, t.titre]));
const FORMATS = { csv: 'CSV' };

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

async function telecharger(r) {
  busy.value = `dl-${r.id}`; notice.value = '';
  try {
    const res = await fetch(`${apiBase}/reports/${r.id}/download`, {
      headers: { Authorization: `Bearer ${getToken()}`, Accept: 'text/csv' },
    });
    if (! res.ok) throw new Error(`Erreur ${res.status}`);
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `rapport-${r.type}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) { notice.value = 'Téléchargement impossible : ' + e.message; }
  finally { busy.value = ''; }
}

const fcfa = (n) => (Number(n) || 0).toLocaleString('fr-FR');
const peutGenerer = computed(() => peutAction('rapport.gerer'));

onMounted(async () => {
  loadAuth();
  await load();
  try {
    const [s, st] = await Promise.all([api.get('/organisation/sites?per_page=500'), api.get('/organisation/structures?per_page=500')]);
    sites.value = Array.isArray(s) ? s : (s.data ?? []);
    structures.value = Array.isArray(st) ? st : (st.data ?? []);
  } catch { /* filtres geo optionnels */ }
});
</script>

<template>
  <AppLayout title="Rapports">
    <p v-if="err" class="text-sm mb-4" style="color: var(--rouge-700);">{{ err }}</p>
    <p v-if="notice" class="text-sm mb-4 px-4 py-2.5 rounded-lg" style="background: var(--vert-100); color: var(--vert-700);">{{ notice }}</p>

    <!-- Filtres communs -->
    <section class="carte p-5 mb-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-bold">Filtres</h2>
        <button class="text-xs font-medium hover:underline" style="color: var(--vert-700);" @click="reset">Réinitialiser</button>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-5 gap-3">
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Région</label>
          <select v-model="filtres.region" class="champ !py-1.5 !text-sm" @change="filtres.prefecture=''; filtres.commune=''">
            <option value="">Toutes</option>
            <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Préfecture</label>
          <select v-model="filtres.prefecture" class="champ !py-1.5 !text-sm" @change="filtres.commune=''">
            <option value="">Toutes</option>
            <option v-for="p in prefectures" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Commune</label>
          <select v-model="filtres.commune" class="champ !py-1.5 !text-sm">
            <option value="">Toutes</option>
            <option v-for="c in communes" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Domaine</label>
          <select v-model="filtres.domaine" class="champ !py-1.5 !text-sm">
            <option value="">Tous</option>
            <option v-for="[v, l] in DOMAINES" :key="v" :value="v">{{ l }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Secteur</label>
          <select v-model="filtres.secteur" class="champ !py-1.5 !text-sm">
            <option value="">Tous</option>
            <option v-for="s in SECTEURS" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Service / ministère</label>
          <select v-model="filtres.service" class="champ !py-1.5 !text-sm">
            <option value="">Tous</option>
            <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.libelle }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">État du bien</label>
          <select v-model="filtres.etat" class="champ !py-1.5 !text-sm">
            <option value="">Tous</option>
            <option v-for="[v, l] in ETATS" :key="v" :value="v">{{ l }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Du</label>
          <input v-model="filtres.du" type="date" class="champ !py-1.5 !text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Au</label>
          <input v-model="filtres.au" type="date" class="champ !py-1.5 !text-sm">
        </div>
      </div>
    </section>

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

    <!-- Rapports PDF / Excel -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
      <div v-for="t in RAPPORTS" :key="t.type" class="carte p-5 flex flex-col">
        <h3 class="text-sm font-bold">{{ t.titre }}</h3>
        <p class="text-xs mt-1 mb-4 flex-1" style="color: var(--discret);">{{ t.desc }}</p>
        <div v-if="peutGenerer" class="flex gap-2">
          <button :disabled="!!busy" @click="exporter(t.type, 'pdf')"
                  class="btn btn-primaire !py-1.5 !px-3 !text-xs flex-1">
            {{ busy === `${t.type}-pdf` ? 'Génération…' : 'Exporter en PDF' }}
          </button>
          <button :disabled="!!busy" @click="exporter(t.type, 'xlsx')"
                  class="btn btn-secondaire !py-1.5 !px-3 !text-xs flex-1">
            {{ busy === `${t.type}-xlsx` ? 'Génération…' : 'Exporter en Excel' }}
          </button>
        </div>
        <p v-else class="text-xs" style="color: var(--discret);">Génération réservée aux gestionnaires — voir les rapports ci-dessous.</p>
      </div>
    </section>

    <!-- Exports CSV (historique) -->
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
      <div v-for="t in TYPES_RAPPORTS" :key="t.type" class="carte p-5 flex flex-col">
        <h3 class="text-sm font-bold">{{ t.titre }}</h3>
        <p class="text-xs mt-1 mb-4 flex-1" style="color: var(--discret);">{{ t.desc }}</p>
        <div v-if="peutGenerer" class="flex gap-2">
          <button v-for="(label, fmt) in FORMATS" :key="fmt"
                  :disabled="!!busy"
                  @click="generer(t.type, fmt)"
                  class="btn btn-secondaire !py-1.5 !px-3 !text-xs">
            {{ busy === `${t.type}-${fmt}` ? '…' : `Générer ${label}` }}
          </button>
        </div>
        <p v-else class="text-xs" style="color: var(--discret);">Consultation seule.</p>
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
            <td class="td font-medium">{{ TYPES[r.type?.replace(/^export_/, '')] ?? r.type }}</td>
            <td class="td" style="color: var(--discret);">{{ r.format?.toUpperCase() }}</td>
            <td class="td" style="color: var(--discret);">{{ r.genere_le ?? r.created_at ?? '—' }}</td>
            <td class="td text-right">
              <button @click="telecharger(r)" :disabled="busy === `dl-${r.id}`"
                      class="text-sm font-medium hover:underline disabled:opacity-50"
                      style="color: var(--vert-700);">
                {{ busy === `dl-${r.id}` ? '…' : 'CSV ↓' }}
              </button>
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
