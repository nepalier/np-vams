<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

interface Option { id: string | number; name_en: string; }
interface PropertyOption { id: string; name_en: string; }

const clients = ref<Option[]>([]);
const purposes = ref<Option[]>([]);
const properties = ref<PropertyOption[]>([]);

const loadingOptions = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);

const form = ref({
  client_id: '',
  valuation_purpose_id: '',
  assignment_date: new Date().toISOString().slice(0, 10),
  requested_completion_date: '',
  priority: 'normal',
  requested_loan_amount: null as number | null,
  contact_person: '',
  client_remarks: '',
  property_ids: [] as string[],
});

async function loadOptions() {
  loadingOptions.value = true;
  try {
    const [clientsRes, purposesRes, propertiesRes] = await Promise.all([
      apiFetch<{ data: Array<{ id: string; name_en: string }> }>('/api/v1/clients?per_page=100'),
      apiFetch<{ data: Option[] }>('/api/v1/master-data/valuation-purposes'),
      apiFetch<{ data: Array<{ id: string; property_name: string | null; property_code: string }> }>('/api/v1/properties?per_page=100'),
    ]);
    clients.value = clientsRes.data;
    purposes.value = purposesRes.data;
    properties.value = propertiesRes.data.map((p) => ({ id: p.id, name_en: p.property_name ?? p.property_code }));
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load form options.';
  } finally {
    loadingOptions.value = false;
  }
}

function togglePropertySelection(id: string) {
  const index = form.value.property_ids.indexOf(id);
  if (index === -1) {
    form.value.property_ids.push(id);
  } else {
    form.value.property_ids.splice(index, 1);
  }
}

async function submit() {
  submitting.value = true;
  error.value = null;

  try {
    const result = await apiFetch<{ data: { id: string } }>('/api/v1/assignments', {
      method: 'POST',
      body: JSON.stringify(form.value),
    });
    window.location.href = `/assignments/${result.data.id}`;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to create assignment.';
    submitting.value = false;
  }
}

onMounted(loadOptions);
</script>

<template>
  <div>
    <a href="/assignments" class="text-sm text-brand-600 hover:underline">← Back to assignments</a>
    <h1 class="text-lg font-semibold mt-4 mb-4">New Assignment</h1>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>
    <div v-if="loadingOptions" class="text-gray-500 text-sm">Loading form…</div>

    <div v-else class="bg-white border rounded p-4 space-y-4 max-w-2xl">
      <div v-if="clients.length === 0" class="bg-amber-50 text-amber-700 text-sm rounded p-3">
        No clients exist yet. <a href="/clients" class="underline">Create one first</a>.
      </div>
      <div v-if="properties.length === 0" class="bg-amber-50 text-amber-700 text-sm rounded p-3">
        No properties exist yet. <a href="/properties" class="underline">Create one first</a>.
      </div>

      <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Client</label>
          <select v-model="form.client_id" class="border rounded px-2 py-1.5 w-full">
            <option value="" disabled>Select a client</option>
            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name_en }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Valuation Purpose</label>
          <select v-model="form.valuation_purpose_id" class="border rounded px-2 py-1.5 w-full">
            <option value="" disabled>Select a purpose</option>
            <option v-for="p in purposes" :key="p.id" :value="p.id">{{ p.name_en }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Assignment Date</label>
          <input v-model="form.assignment_date" type="date" class="border rounded px-2 py-1.5 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Requested Completion</label>
          <input v-model="form.requested_completion_date" type="date" class="border rounded px-2 py-1.5 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Priority</label>
          <select v-model="form.priority" class="border rounded px-2 py-1.5 w-full">
            <option value="low">Low</option>
            <option value="normal">Normal</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Requested Loan Amount</label>
          <input v-model.number="form.requested_loan_amount" type="number" class="border rounded px-2 py-1.5 w-full" />
        </div>
        <div class="col-span-2">
          <label class="block text-xs text-gray-500 mb-1">Contact Person</label>
          <input v-model="form.contact_person" type="text" class="border rounded px-2 py-1.5 w-full" />
        </div>
      </div>

      <div>
        <label class="block text-xs text-gray-500 mb-2">Properties</label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="p in properties"
            :key="p.id"
            type="button"
            :class="[
              'px-3 py-1 text-sm rounded border',
              form.property_ids.includes(p.id) ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700',
            ]"
            @click="togglePropertySelection(p.id)"
          >{{ p.name_en }}</button>
        </div>
      </div>

      <div>
        <label class="block text-xs text-gray-500 mb-1">Client Remarks</label>
        <textarea v-model="form.client_remarks" class="border rounded px-2 py-1.5 w-full" rows="2"></textarea>
      </div>

      <button
        :disabled="submitting || !form.client_id || !form.valuation_purpose_id || form.property_ids.length === 0"
        class="px-4 py-2 text-sm bg-brand-600 text-white rounded disabled:opacity-40"
        @click="submit"
      >{{ submitting ? 'Creating…' : 'Create Assignment' }}</button>
    </div>
  </div>
</template>
