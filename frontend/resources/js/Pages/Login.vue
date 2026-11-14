<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { api, setToken } from '../api';

const email = ref(''), password = ref(''), err = ref(''), loading = ref(false);

async function submit() {
  loading.value = true; err.value = '';
  try {
    const r = await api.login(email.value, password.value);
    setToken(r.access_token);
    router.visit('/tableau-de-bord');
  } catch (e) {
    err.value = e.message === 'Failed to fetch' ? 'Service injoignable. Réessayez.' : e.message;
  } finally { loading.value = false; }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4" style="background: var(--vert-950);">
    <div class="w-full max-w-sm">
      <!-- Blason / identité -->
      <div class="text-center mb-8">
        <img :src="'/assets/armoiries.png'" alt="Armoiries de la République Togolaise"
             class="mx-auto mb-4" style="width: 96px; height: 120px; object-fit: contain;">
        <h1 class="text-white text-xl font-bold">Patrimoine de l'État</h1>
        <p class="text-xs mt-1 font-semibold" style="color: #fff;">République Togolaise</p>
        <p class="text-xs mt-1" style="color: rgba(255,255,255,.45);">Travail · Liberté · Patrie</p>
      </div>

      <!-- Carte de connexion -->
      <form @submit.prevent="submit" class="carte p-7">
        <h2 class="text-base font-bold mb-5">Se connecter</h2>
        <label class="block text-xs font-medium mb-1.5" style="color: var(--discret);">Adresse e-mail</label>
        <input v-model="email" type="email" required autocomplete="username"
               class="champ mb-4" placeholder="nom@structure.tg">
        <label class="block text-xs font-medium mb-1.5" style="color: var(--discret);">Mot de passe</label>
        <input v-model="password" type="password" required autocomplete="current-password"
               class="champ mb-2" placeholder="••••••••">
        <div class="text-right mb-4">
          <span class="text-xs cursor-pointer hover:underline" style="color: var(--vert-600);">Mot de passe oublié ?</span>
        </div>
        <p v-if="err" class="text-sm mb-3" style="color: var(--rouge-700);">{{ err }}</p>
        <button :disabled="loading" class="btn btn-primaire w-full justify-center py-2.5">
          {{ loading ? 'Connexion…' : 'Se connecter' }}
        </button>
      </form>

      <p class="text-center text-[11px] mt-6" style="color: rgba(255,255,255,.35);">
        Plateforme nationale de gestion du patrimoine de l'État togolais
      </p>
    </div>
  </div>
</template>
