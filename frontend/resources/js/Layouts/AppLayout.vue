<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { api, getToken } from '../api';

const props = defineProps({ title: { type: String, default: '' } });
const page = usePage();

const nav = [
  ['Tableau de bord', '/tableau-de-bord'],
  ['Biens', '/biens'],
  ['Affectations', '/affectations'],
  ['Maintenance', '/maintenance'],
  ['Inventaires', '/inventaires'],
  ['Rapports', '/rapports'],
  ['Administration', '/admin'],
];

const user = ref(null);
const roles = ref([]);
onMounted(async () => {
  if (! getToken()) return;
  try {
    user.value = await api.get('/auth/me');
    roles.value = user.value?.roles ?? [];
  } catch { /* session expirée gérée par api.js */ }
});

// Navigation adaptée au rôle : l'administration reste réservée aux admins.
const navFiltre = computed(() => {
  const estAdmin = roles.value.includes('admin');
  return nav.filter(([label]) => estAdmin || label !== 'Administration');
});

function logout() {
  api.post('/auth/logout').catch(() => {});
  localStorage.removeItem('jwt');
  window.location.href = '/connexion';
}
const actif = (href) => page.url === href || (href !== '/tableau-de-bord' && page.url.startsWith(href));
</script>

<template>
  <div class="min-h-screen flex">
    <!-- ===================== Sidebar ===================== -->
    <aside class="w-64 shrink-0 flex flex-col fixed inset-y-0" style="background: var(--vert-950);">
      <div class="px-5 pt-6 pb-5" style="border-bottom: 1px solid rgba(255,255,255,.09);">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
               style="background: var(--or); color: var(--vert-950); font-weight: 800; font-size: .95rem;">PE</div>
          <div>
            <div class="text-white font-bold leading-tight text-[15px]">Patrimoine de l'État</div>
            <div class="text-[10px] uppercase tracking-widest" style="color: var(--or);">République Togolaise</div>
          </div>
        </div>
        <div class="mt-3 text-[10.5px] leading-snug" style="color: rgba(255,255,255,.45);">
          Travail · Liberté · Patrie
        </div>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <Link v-for="[label, href] in navFiltre" :key="href" :href="href"
              :class="['nav-item', { actif: actif(href) }]">
          {{ label }}
        </Link>
      </nav>

      <div class="px-4 py-4" style="border-top: 1px solid rgba(255,255,255,.09);">
        <button @click="logout" class="nav-item w-full" style="color: rgba(255,255,255,.55);">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
          </svg>
          Déconnexion
        </button>
      </div>
    </aside>

    <!-- ===================== Contenu ===================== -->
    <div class="app-fond flex-1 ml-64 flex flex-col min-w-0 relative">
      <div class="relative z-10 flex flex-col flex-1 min-h-0">
      <header class="px-8 pt-6 pb-4 flex items-center justify-between gap-4">
        <h1 class="text-xl font-bold tracking-tight">{{ title }}</h1>
        <div v-if="user" class="flex items-center gap-3">
          <div class="text-right">
            <div class="text-sm font-semibold">{{ user.prenom }} {{ user.nom }}</div>
            <div class="text-xs" style="color: var(--discret);">{{ (user.roles ?? []).join(', ') || 'Utilisateur' }}</div>
          </div>
          <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold text-white"
               style="background: var(--vert-700);">
            {{ (user.prenom?.[0] ?? '') + (user.nom?.[0] ?? '') }}
          </div>
        </div>
      </header>
      <main class="flex-1 px-8 pb-10"><slot /></main>
      </div>
    </div>
  </div>
</template>
