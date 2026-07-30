<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

interface PropertyRecord {
  id: string;
  property_code: string;
  property_name: string | null;
  address: string | null;
  district_name: string | null;
}

const properties = ref<PropertyRecord[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showForm = ref(false);
const submitting = ref(false);

const form = ref({ property_name: '', address: '', area_classification: 'urban' });

async function load() {
  loading.value = true;
  try {
    const result = await apiFetch<{ data: PropertyRecord[] }>('/api/v1/properties?per_page=50');
    properties.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load properties.';
  } finally {
    loading.value = false;
  }
}

async function submit() {
  submitting.value = true;
  error.value = null;

  try {
    await apiFetch('/api/v1/properties', { method: 'POST', body: JSON.stringify(form.value) });
    form.value = { property_name: '', address: '', area_classification: 'urban' };
    showForm.value = false;
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to create property.';
  } finally {
    submitting.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-lg font-semibold">Properties</h1>
      <button class="px-3 py-1.5 text-sm bg-brand-600 text-white rounded" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : '+ New Property' }}
      </button>
    </div>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>

    <div v-if="showForm" class="bg-white border rounded p-4 mb-4">
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <label class="block text-xs text-gray-500">Property Name</label>
          <input v-model="form.property_name" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Area Classification</label>
          <select v-model="form.area_classification" class="border rounded px-2 py-1 w-full">
            <option value="urban">Urban</option>
            <option value="semi_urban">Semi-Urban</option>
            <option value="rural">Rural</option>
          </select>
        </div>
        <div class="col-span-2">
          <label class="block text-xs text-gray-500">Address</label>
          <input v-model="form.address" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
      </div>
      <p class="text-xs text-gray-400 mt-2">Property code is generated automatically. Add parcels/buildings/GPS coordinates after creation.</p>
      <button
        :disabled="submitting"
        class="mt-3 px-4 py-1.5 text-sm bg-brand-600 text-white rounded disabled:opacity-40"
        @click="submit"
      >{{ submitting ? 'Creating…' : 'Create Property' }}</button>
    </div>

    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>
    <div v-else-if="properties.length === 0" class="text-gray-500 text-sm py-8 text-center border rounded">No properties yet.</div>
    <table v-else class="w-full text-sm bg-white border rounded overflow-hidden">
      <thead class="bg-gray-50 text-gray-600 text-left">
        <tr>
          <th class="px-4 py-2 font-medium">Code</th>
          <th class="px-4 py-2 font-medium">Name</th>
          <th class="px-4 py-2 font-medium">Address</th>
          <th class="px-4 py-2 font-medium">District</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in properties" :key="p.id" class="border-t">
          <td class="px-4 py-2 font-medium text-brand-700">{{ p.property_code }}</td>
          <td class="px-4 py-2">{{ p.property_name ?? '—' }}</td>
          <td class="px-4 py-2">{{ p.address ?? '—' }}</td>
          <td class="px-4 py-2">{{ p.district_name ?? '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
