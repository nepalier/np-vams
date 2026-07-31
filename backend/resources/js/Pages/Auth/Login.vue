<script setup lang="ts">
import { reactive, ref } from 'vue';

const form = reactive({ email: '', password: '', mfa_code: '' });
const errors = ref<Record<string, string[]>>({});
const submitting = ref(false);

async function submit() {
  submitting.value = true;
  errors.value = {};

  try {
    const response = await fetch('/api/v1/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(form),
    });

    if (!response.ok) {
      const body = await response.json();
      errors.value = body.errors ?? {};
      return;
    }

    const { data } = await response.json();
    localStorage.setItem('npvams_token', data.token);
    window.location.href = data.user?.is_client_portal_user ? '/portal' : '/';
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="max-w-sm mx-auto mt-24 bg-white p-8 rounded-lg shadow">
    <h1 class="text-xl font-semibold mb-6 text-brand-700">NP-VAMS Sign In</h1>
    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input v-model="form.email" type="email" required class="w-full border rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Password</label>
        <input v-model="form.password" type="password" required class="w-full border rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">MFA Code (if enabled)</label>
        <input v-model="form.mfa_code" type="text" maxlength="6" class="w-full border rounded px-3 py-2" />
      </div>
      <button :disabled="submitting" type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded py-2">
        {{ submitting ? 'Signing in…' : 'Sign In' }}
      </button>
    </form>
  </div>
</template>
