<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

interface Party {
  id: string; party_kind: string; name_en: string; citizenship_number: string | null;
  mobile: string | null; ownership_type?: string | null; ownership_percentage?: number | null;
}

const activeTab = ref<'borrowers' | 'owners'>('borrowers');
const borrowers = ref<Party[]>([]);
const owners = ref<Party[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showForm = ref(false);
const submitting = ref(false);

const PARTY_KINDS = ['individual', 'company', 'institutional', 'trust', 'guthi'];

const form = ref({
  party_kind: 'individual', name_en: '', citizenship_number: '', mobile: '', email: '',
  ownership_type: 'single', ownership_percentage: 100,
});

async function load() {
  loading.value = true;
  try {
    const [borrowersRes, ownersRes] = await Promise.all([
      apiFetch<{ data: Party[] }>('/api/v1/borrowers?per_page=100'),
      apiFetch<{ data: Party[] }>('/api/v1/property-owners?per_page=100'),
    ]);
    borrowers.value = borrowersRes.data;
    owners.value = ownersRes.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load parties.';
  } finally {
    loading.value = false;
  }
}

async function submit() {
  submitting.value = true;
  error.value = null;
  const endpoint = activeTab.value === 'borrowers' ? '/api/v1/borrowers' : '/api/v1/property-owners';

  try {
    await apiFetch(endpoint, { method: 'POST', body: JSON.stringify(form.value) });
    form.value = { party_kind: 'individual', name_en: '', citizenship_number: '', mobile: '', email: '', ownership_type: 'single', ownership_percentage: 100 };
    showForm.value = false;
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to create.';
  } finally {
    submitting.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-lg font-semibold">Borrowers &amp; Property Owners</h1>
      <button class="px-3 py-1.5 text-sm bg-brand-600 text-white rounded" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : '+ New' }}
      </button>
    </div>

    <div class="flex gap-2 mb-4 text-sm">
      <button :class="['px-3 py-1 rounded', activeTab === 'borrowers' ? 'bg-brand-600 text-white' : 'bg-gray-100']" @click="activeTab = 'borrowers'">
        Borrowers ({{ borrowers.length }})
      </button>
      <button :class="['px-3 py-1 rounded', activeTab === 'owners' ? 'bg-brand-600 text-white' : 'bg-gray-100']" @click="activeTab = 'owners'">
        Property Owners ({{ owners.length }})
      </button>
    </div>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>

    <div v-if="showForm" class="bg-white border rounded p-4 mb-4 text-sm">
      <p class="text-xs text-gray-500 mb-2">Creating a new {{ activeTab === 'borrowers' ? 'borrower' : 'property owner' }}</p>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs text-gray-500">Party Kind</label>
          <select v-model="form.party_kind" class="border rounded px-2 py-1 w-full">
            <option v-for="k in PARTY_KINDS" :key="k" :value="k">{{ k }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Full Name</label>
          <input v-model="form.name_en" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Citizenship No.</label>
          <input v-model="form.citizenship_number" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Mobile</label>
          <input v-model="form.mobile" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Email</label>
          <input v-model="form.email" type="email" class="border rounded px-2 py-1 w-full" />
        </div>
        <template v-if="activeTab === 'owners'">
          <div>
            <label class="block text-xs text-gray-500">Ownership %</label>
            <input v-model.number="form.ownership_percentage" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
        </template>
      </div>
      <button
        :disabled="submitting || !form.name_en"
        class="mt-3 px-4 py-1.5 bg-brand-600 text-white rounded disabled:opacity-40"
        @click="submit"
      >{{ submitting ? 'Creating…' : 'Create' }}</button>
    </div>

    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>
    <table v-else class="w-full text-sm bg-white border rounded overflow-hidden">
      <thead class="bg-gray-50 text-gray-600 text-left">
        <tr>
          <th class="px-4 py-2 font-medium">Name</th>
          <th class="px-4 py-2 font-medium">Kind</th>
          <th class="px-4 py-2 font-medium">Citizenship No.</th>
          <th class="px-4 py-2 font-medium">Mobile</th>
          <th v-if="activeTab === 'owners'" class="px-4 py-2 font-medium">Ownership %</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in (activeTab === 'borrowers' ? borrowers : owners)" :key="p.id" class="border-t">
          <td class="px-4 py-2 font-medium">{{ p.name_en }}</td>
          <td class="px-4 py-2 capitalize">{{ p.party_kind }}</td>
          <td class="px-4 py-2">{{ p.citizenship_number ?? '—' }}</td>
          <td class="px-4 py-2">{{ p.mobile ?? '—' }}</td>
          <td v-if="activeTab === 'owners'" class="px-4 py-2">{{ p.ownership_percentage ?? '—' }}</td>
        </tr>
      </tbody>
    </table>
    <p v-if="!loading && (activeTab === 'borrowers' ? borrowers : owners).length === 0" class="text-sm text-gray-400 text-center py-8 border rounded mt-2">
      None recorded yet.
    </p>
  </div>
</template>
