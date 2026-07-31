<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

const props = defineProps<{ parcelId: string }>();

const form = ref({
  topography: '', flood_exposure: '', landslide_exposure: '', access_type: '',
  road_width_m: null as number | null, is_corner_plot: false,
  marketability_rating: null as number | null,
});
const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const suggestedFactors = ref<Record<string, number> | null>(null);

async function load() {
  loading.value = true;
  try {
    const result = await apiFetch<{ data: typeof form.value | null }>(`/api/v1/parcels/${props.parcelId}/characteristics`);
    if (result.data) {
      form.value = { ...form.value, ...result.data };
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load characteristics.';
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/parcels/${props.parcelId}/characteristics`, { method: 'PUT', body: JSON.stringify(form.value) });
    const preview = await apiFetch<{ data: Record<string, number> }>(`/api/v1/parcels/${props.parcelId}/suggested-adjustment-factors`);
    suggestedFactors.value = preview.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to save.';
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="border rounded p-3 mt-2 bg-gray-50 text-sm">
    <div v-if="loading" class="text-gray-500">Loading…</div>
    <template v-else>
      <div v-if="error" class="bg-red-50 text-red-700 rounded p-2 mb-2">{{ error }}</div>

      <div class="grid grid-cols-3 gap-2 mb-2">
        <div>
          <label class="block text-xs text-gray-500">Topography</label>
          <select v-model="form.topography" class="border rounded px-2 py-1 w-full">
            <option value="">—</option>
            <option value="flat">Flat</option>
            <option value="gentle_slope">Gentle Slope</option>
            <option value="steep_slope">Steep Slope</option>
            <option value="undulating">Undulating</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Flood Exposure</label>
          <select v-model="form.flood_exposure" class="border rounded px-2 py-1 w-full">
            <option value="">—</option>
            <option value="none">None</option>
            <option value="low">Low</option>
            <option value="moderate">Moderate</option>
            <option value="high">High</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Access Type</label>
          <select v-model="form.access_type" class="border rounded px-2 py-1 w-full">
            <option value="">—</option>
            <option value="motorable">Motorable</option>
            <option value="foot_trail">Foot Trail</option>
            <option value="no_direct_access">No Direct Access</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Road Width (m)</label>
          <input v-model.number="form.road_width_m" type="number" class="border rounded px-2 py-1 w-full" />
        </div>
        <div class="flex items-end gap-2">
          <label class="flex items-center gap-1 text-xs text-gray-600">
            <input v-model="form.is_corner_plot" type="checkbox" /> Corner Plot
          </label>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Marketability (1-5)</label>
          <input v-model.number="form.marketability_rating" type="number" min="1" max="5" class="border rounded px-2 py-1 w-full" />
        </div>
      </div>

      <button :disabled="saving" class="px-3 py-1 bg-brand-600 text-white rounded disabled:opacity-40" @click="save">
        {{ saving ? 'Saving…' : 'Save & Preview Adjustment Factors' }}
      </button>

      <div v-if="suggestedFactors" class="mt-2 text-xs text-gray-600">
        <span class="font-medium">Suggested market-comparison factors:</span>
        <span v-for="(v, k) in suggestedFactors" :key="k" class="ml-2">{{ k }}: {{ v }}</span>
        <span v-if="Object.keys(suggestedFactors).length === 0">None yet — fill in more fields above.</span>
      </div>
    </template>
  </div>
</template>
