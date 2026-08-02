<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

interface Comparable {
  id: string; location: string; unit_rate: string; reliability_grade: string;
  transaction_date: string | null; district_id: number | null; latitude: number | null; longitude: number | null;
}
interface District { id: number; name_en: string; }

const comparables = ref<Comparable[]>([]);
const districts = ref<District[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showForm = ref(false);
const submitting = ref(false);

const filterDistrictId = ref<number | ''>('');
const filterGrade = ref('');

const GRADES = [
  { value: 'A', label: 'A — Verified registered transaction' },
  { value: 'B', label: 'B — Verified institutional transaction' },
  { value: 'C', label: 'C — Multiple confirmed quotations' },
  { value: 'D', label: 'D — Single quotation' },
  { value: 'E', label: 'E — Unverified asking price' },
];

const form = ref({
  location: '', district_id: '' as number | '', latitude: null as number | null, longitude: null as number | null,
  unit_rate: null as number | null, reliability_grade: 'C', transaction_date: '', data_source: '',
});

function gradeColor(grade: string): string {
  return { A: 'text-emerald-600', B: 'text-emerald-600', C: 'text-amber-600', D: 'text-amber-600', E: 'text-red-600' }[grade] ?? '';
}

function formatCurrency(value: string): string {
  return new Intl.NumberFormat('en-NP', { style: 'currency', currency: 'NPR' }).format(Number(value));
}

async function load() {
  loading.value = true;
  try {
    const params = new URLSearchParams({ per_page: '50' });
    if (filterDistrictId.value) params.set('district_id', String(filterDistrictId.value));
    if (filterGrade.value) params.set('reliability_grade', filterGrade.value);

    const [comparablesRes, districtsRes] = await Promise.all([
      apiFetch<{ data: Comparable[] }>(`/api/v1/comparable-properties?${params}`),
      districts.value.length ? Promise.resolve({ data: districts.value }) : apiFetch<{ data: District[] }>('/api/v1/master-data/districts'),
    ]);
    comparables.value = comparablesRes.data;
    districts.value = districtsRes.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load comparables.';
  } finally {
    loading.value = false;
  }
}

async function submit() {
  submitting.value = true;
  error.value = null;
  try {
    await apiFetch('/api/v1/comparable-properties', { method: 'POST', body: JSON.stringify(form.value) });
    form.value = { location: '', district_id: '', latitude: null, longitude: null, unit_rate: null, reliability_grade: 'C', transaction_date: '', data_source: '' };
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
      <h1 class="text-lg font-semibold">Comparable Properties</h1>
      <button class="px-3 py-1.5 text-sm bg-brand-600 text-white rounded" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : '+ New Comparable' }}
      </button>
    </div>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>

    <div v-if="showForm" class="bg-white border rounded p-4 mb-4 text-sm">
      <div class="grid grid-cols-3 gap-3 mb-2">
        <div class="col-span-2">
          <label class="block text-xs text-gray-500">Location</label>
          <input v-model="form.location" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">District</label>
          <select v-model="form.district_id" class="border rounded px-2 py-1 w-full">
            <option value="">—</option>
            <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name_en }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Latitude</label>
          <input v-model.number="form.latitude" type="number" step="0.000001" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Longitude</label>
          <input v-model.number="form.longitude" type="number" step="0.000001" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Unit Rate (Rs)</label>
          <input v-model.number="form.unit_rate" type="number" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Reliability Grade</label>
          <select v-model="form.reliability_grade" class="border rounded px-2 py-1 w-full">
            <option v-for="g in GRADES" :key="g.value" :value="g.value">{{ g.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Transaction Date</label>
          <input v-model="form.transaction_date" type="date" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Data Source</label>
          <input v-model="form.data_source" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
      </div>
      <p class="text-xs text-gray-400 mb-2">Recording GPS coordinates lets this comparable be found automatically by the "nearby" search from an assignment's Market Comparison tab.</p>
      <button
        :disabled="submitting || !form.location || !form.unit_rate"
        class="px-4 py-1.5 bg-brand-600 text-white rounded disabled:opacity-40"
        @click="submit"
      >{{ submitting ? 'Creating…' : 'Create Comparable' }}</button>
    </div>

    <div class="flex gap-3 mb-4 text-sm">
      <select v-model="filterDistrictId" class="border rounded px-2 py-1" @change="load">
        <option value="">All districts</option>
        <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name_en }}</option>
      </select>
      <select v-model="filterGrade" class="border rounded px-2 py-1" @change="load">
        <option value="">All grades</option>
        <option v-for="g in GRADES" :key="g.value" :value="g.value">{{ g.value }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>
    <div v-else-if="comparables.length === 0" class="text-gray-500 text-sm py-8 text-center border rounded bg-white">
      No comparable properties recorded yet.
    </div>
    <table v-else class="w-full text-sm bg-white border rounded overflow-hidden">
      <thead class="bg-gray-50 text-gray-600 text-left">
        <tr>
          <th class="px-4 py-2 font-medium">Location</th>
          <th class="px-4 py-2 font-medium">Unit Rate</th>
          <th class="px-4 py-2 font-medium">Grade</th>
          <th class="px-4 py-2 font-medium">Transaction Date</th>
          <th class="px-4 py-2 font-medium">GPS</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in comparables" :key="c.id" class="border-t">
          <td class="px-4 py-2">{{ c.location }}</td>
          <td class="px-4 py-2">{{ formatCurrency(c.unit_rate) }}</td>
          <td class="px-4 py-2"><span :class="['font-medium', gradeColor(c.reliability_grade)]">{{ c.reliability_grade }}</span></td>
          <td class="px-4 py-2">{{ c.transaction_date ?? '—' }}</td>
          <td class="px-4 py-2">{{ c.latitude && c.longitude ? '✓' : '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
