<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../api';
import { authz, loadAuth, pageAutorisee } from '../authz';

const props = defineProps({ title: { type: String, default: '' } });
const page = usePage();
const menuOuvert = ref(false);

const nav = [
  { label: 'Tableau de bord', href: '/tableau-de-bord' },
  { label: 'Biens', href: '/biens' },
  { label: 'Affectations', href: '/affectations' },
  { label: 'Maintenance', href: '/maintenance' },
  { label: 'Inventaires', href: '/inventaires' },
  { label: 'Rapports', href: '/rapports' },
  { label: 'Administration', href: '/admin' },
];

const user = computed(() => authz.user);
const roles = computed(() => authz.user?.roles ?? []);

onMounted(loadAuth);

// Garde de navigation : toute page hors du périmètre du rôle
// renvoie vers le tableau de bord.
watch(() => page.url, async (url) => {
  await loadAuth();
  menuOuvert.value = false;
  const path = url.split('?')[0];
  if (path !== '/tableau-de-bord' && ! pageAutorisee(path)) {
    router.visit('/tableau-de-bord');
  }
}, { immediate: true });

const navFiltre = computed(() => nav.filter((n) => pageAutorisee(n.href)));
const actif = (href) => page.url === href || (href !== '/tableau-de-bord' && page.url.startsWith(href));

function logout() {
  api.post('/auth/logout').catch(() => {});
  localStorage.removeItem('jwt');
  window.location.href = '/connexion';
}
</script>

<template>
  <div class="min-h-screen flex">
    <!-- ===================== Sidebar ===================== -->
    <div v-if="menuOuvert" class="fixed inset-0 z-30 bg-black/40 md:hidden" @click="menuOuvert = false"></div>
    <aside class="sidebar w-64 shrink-0 flex-col fixed inset-y-0 z-40 transition-transform duration-200"
           :class="menuOuvert ? 'translate-x-0 flex' : '-translate-x-full hidden md:flex md:translate-x-0'"
           style="background: var(--vert-950);">
      <div class="sidebar-logo">
        <img :src="'/assets/armoiries.png'" alt="Armoiries de la République Togolaise">
        <span>Patrimoine<br>de l'État</span>
      </div>

      <nav class="flex-1 px-4 py-5 space-y-2 overflow-y-auto">
        <Link v-for="n in navFiltre" :key="n.href" :href="n.href"
              :class="['nav-item', { actif: actif(n.href) }]">
          {{ n.label }}
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
    <div class="app-fond main flex-1 md:ml-64 flex flex-col min-w-0">
      <!-- Filigrane institutionnel : armoirie en filigrane derriere le contenu -->
      <img class="watermark" :src="'/assets/armoiries.png'" alt="" aria-hidden="true">

      <div class="relative z-10 flex flex-col flex-1 min-h-0">
      <div class="topbar flex items-center gap-3">
        <button class="md:hidden btn-secondaire !px-2.5 !py-1.5" @click="menuOuvert = !menuOuvert" aria-label="Menu">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
          </svg>
        </button>
        <div>
          <div class="font-bold" style="color: var(--encre);">République Togolaise</div>
          <div>Travail · Liberté · Patrie</div>
        </div>
      </div>
      <header class="pt-4 pb-4 flex items-center justify-between gap-4">
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
      <main class="flex-1"><slot /></main>
      </div>
    </div>
  </div>
</template>
