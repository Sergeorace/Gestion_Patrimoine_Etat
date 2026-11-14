<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { api } from '../api';
import { onMounted, ref } from 'vue';

const users = ref([]), roles = ref([]), structures = ref({}), err = ref('');
const showForm = ref(false), saving = ref(false), formErr = ref(''), created = ref('');
const form = ref({ nom: '', prenom: '', email: '', structure_id: '', role_ids: [] });

const statut = (s) => s === 'actif' ? ['Actif', 'et-vert'] : ['Suspendu', 'et-rouge'];
const roleNames = (u) => (u.roles ?? []).map(r => r.libelle ?? r.code ?? r.nom).join(', ') || '—';

async function load() {
  try {
    const [u, r, s] = await Promise.all([
      api.get('/users?per_page=100'),
      api.get('/roles'),
      api.get('/organisation/structures'),
    ]);
    users.value = u.data ?? u ?? [];
    roles.value = Array.isArray(r) ? r : (r.data ?? []);
    for (const x of (Array.isArray(s) ? s : (s.data ?? []))) structures.value[x.id] = x.libelle;
  } catch (e) { err.value = e.message; }
}

function toggleRole(id) {
  const i = form.value.role_ids.indexOf(id);
  i >= 0 ? form.value.role_ids.splice(i, 1) : form.value.role_ids.push(id);
}

async function save() {
  saving.value = true; formErr.value = ''; created.value = '';
  try {
    const r = await api.post('/users', {
      nom: form.value.nom, prenom: form.value.prenom, email: form.value.email,
      structure_id: form.value.structure_id || null,
      role_ids: form.value.role_ids,
    });
    created.value = r.temporary_password ? `Compte créé — mot de passe temporaire : ${r.temporary_password}` : 'Compte créé.';
    showForm.value = false;
    form.value = { nom: '', prenom: '', email: '', structure_id: '', role_ids: [] };
    await load();
  } catch (e) { formErr.value = e.message; } finally { saving.value = false; }
}

onMounted(load);
</script>

<template>
  <AppLayout title="Administration">
    <div class="flex justify-between items-center mb-5">
      <p class="text-sm" style="color: var(--discret);">{{ users.length }} utilisateur{{ users.length > 1 ? 's' : '' }}</p>
      <button class="btn btn-primaire" @click="showForm = !showForm">+ Nouvel utilisateur</button>
    </div>

    <form v-if="showForm" @submit.prevent="save" class="carte p-5 mb-5 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div><label class="block text-xs font-medium mb-1" style="color: var(--discret);">Nom</label>
        <input v-model="form.nom" required class="champ"></div>
      <div><label class="block text-xs font-medium mb-1" style="color: var(--discret);">Prénom</label>
        <input v-model="form.prenom" required class="champ"></div>
      <div><label class="block text-xs font-medium mb-1" style="color: var(--discret);">E-mail</label>
        <input v-model="form.email" type="email" required class="champ"></div>
      <div><label class="block text-xs font-medium mb-1" style="color: var(--discret);">Service</label>
        <select v-model="form.structure_id" class="champ">
          <option value="">—</option>
          <option v-for="(lib, id) in structures" :key="id" :value="id">{{ lib }}</option>
        </select></div>
      <div class="md:col-span-2">
        <label class="block text-xs font-medium mb-1" style="color: var(--discret);">Rôle(s)</label>
        <div class="flex flex-wrap gap-2">
          <button v-for="r in roles" :key="r.id" type="button" @click="toggleRole(r.id)"
                  class="etiquette cursor-pointer"
                  :class="form.role_ids.includes(r.id) ? 'et-vert' : 'et-gris'">
            {{ r.libelle ?? r.code ?? r.nom }}
          </button>
        </div>
      </div>
      <div class="md:col-span-3 flex items-center gap-3">
        <p v-if="formErr" class="text-sm" style="color: var(--rouge-700);">{{ formErr }}</p>
        <p v-if="created" class="text-sm" style="color: var(--vert-700);">{{ created }}</p>
        <button :disabled="saving" class="btn btn-primaire ml-auto">{{ saving ? 'Enregistrement…' : 'Créer le compte' }}</button>
      </div>
    </form>

    <p v-if="err" class="text-sm mb-3" style="color: var(--rouge-700);">{{ err }}</p>

    <div class="carte overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr>
            <th class="th">Nom</th>
            <th class="th">E-mail</th>
            <th class="th">Rôle</th>
            <th class="th">Service</th>
            <th class="th">Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id" class="tr-ligne">
            <td class="td font-medium">{{ u.prenom }} {{ u.nom }}</td>
            <td class="td" style="color: var(--discret);">{{ u.email }}</td>
            <td class="td">{{ roleNames(u) }}</td>
            <td class="td" style="color: var(--discret);">{{ structures[u.structure_id] ?? '—' }}</td>
            <td class="td"><span class="etiquette" :class="statut(u.statut)[1]">{{ statut(u.statut)[0] }}</span></td>
          </tr>
          <tr v-if="!users.length">
            <td colspan="5" class="td text-center py-10" style="color: var(--discret);">Aucun utilisateur.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
