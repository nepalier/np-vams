<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

interface ClientRecord {
  id: string;
  name_en: string;
  name_ne: string | null;
  client_type: string;
  telephone: string | null;
  email: string | null;
  is_active: boolean;
}

const clients = ref<ClientRecord[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showForm = ref(false);
const submitting = ref(false);

const CLIENT_TYPES = [
  'commercial_bank', 'development_bank', 'finance_company', 'microfinance',
  'cooperative', 'insurance', 'government_agency', 'corporate', 'individual', 'other',
];

const form = ref({ name_en: '', name_ne: '', client_type: 'commercial_bank', telephone: '', email: '' });

async function load() {
  loading.value = true;
  try {
    const result = await apiFetch<{ data: ClientRecord[] }>('/api/v1/clients?per_page=50');
    clients.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load clients.';
  } finally {
    loading.value = false;
  }
}

async function submit() {
  submitting.value = true;
  error.value = null;

  try {
    await apiFetch('/api/v1/clients', { method: 'POST', body: JSON.stringify(form.value) });
    form.value = { name_en: '', name_ne: '', client_type: 'commercial_bank', telephone: '', email: '' };
    showForm.value = false;
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to create client.';
  } finally {
    submitting.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-lg font-semibold">Clients</h1>
      <button class="px-3 py-1.5 text-sm bg-brand-600 text-white rounded" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : '+ New Client' }}
      </button>
    </div>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>

    <div v-if="showForm" class="bg-white border rounded p-4 mb-4">
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <label class="block text-xs text-gray-500">Name (English)</label>
          <input v-model="form.name_en" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Name (Nepali)</label>
          <input v-model="form.name_ne" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Type</label>
          <select v-model="form.client_type" class="border rounded px-2 py-1 w-full">
            <option v-for="t in CLIENT_TYPES" :key="t" :value="t">{{ t.replace(/_/g, ' ') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Telephone</label>
          <input v-model="form.telephone" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div class="col-span-2">
          <label class="block text-xs text-gray-500">Email</label>
          <input v-model="form.email" type="email" class="border rounded px-2 py-1 w-full" />
        </div>
      </div>
      <button
        :disabled="submitting || !form.name_en"
        class="mt-3 px-4 py-1.5 text-sm bg-brand-600 text-white rounded disabled:opacity-40"
        @click="submit"
      >{{ submitting ? 'Creating…' : 'Create Client' }}</button>
    </div>

    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>
    <div v-else-if="clients.length === 0" class="text-gray-500 text-sm py-8 text-center border rounded">No clients yet.</div>
    <table v-else class="w-full text-sm bg-white border rounded overflow-hidden">
      <thead class="bg-gray-50 text-gray-600 text-left">
        <tr>
          <th class="px-4 py-2 font-medium">Name</th>
          <th class="px-4 py-2 font-medium">Type</th>
          <th class="px-4 py-2 font-medium">Telephone</th>
          <th class="px-4 py-2 font-medium">Email</th>
          <th class="px-4 py-2 font-medium">Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in clients" :key="c.id" class="border-t">
          <td class="px-4 py-2 font-medium">{{ c.name_en }}</td>
          <td class="px-4 py-2 capitalize">{{ c.client_type.replace(/_/g, ' ') }}</td>
          <td class="px-4 py-2">{{ c.telephone ?? '—' }}</td>
          <td class="px-4 py-2">{{ c.email ?? '—' }}</td>
          <td class="px-4 py-2">
            <span :class="c.is_active ? 'text-emerald-600' : 'text-gray-400'">{{ c.is_active ? 'Active' : 'Inactive' }}</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
