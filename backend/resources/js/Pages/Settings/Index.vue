<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

interface SettingsForm {
  default_land_rate_government_weight_pct: number | null;
  default_land_rate_market_weight_pct: number | null;
  default_distress_value_pct: number | null;
  default_vehicle_scrap_pct: number | null;
  default_vehicle_depreciation_pct_per_annum: number | null;
  default_vehicle_other_cost_pct_per_annum: number | null;
  default_building_sanitary_fixture_pct: number | null;
  default_building_electrical_fixture_pct: number | null;
  default_building_depreciation_pct_per_annum: number | null;
}

const form = ref<SettingsForm>({
  default_land_rate_government_weight_pct: null, default_land_rate_market_weight_pct: null, default_distress_value_pct: null,
  default_vehicle_scrap_pct: null, default_vehicle_depreciation_pct_per_annum: null, default_vehicle_other_cost_pct_per_annum: null,
  default_building_sanitary_fixture_pct: null, default_building_electrical_fixture_pct: null, default_building_depreciation_pct_per_annum: null,
});

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const saved = ref(false);

async function load() {
  loading.value = true;
  try {
    const result = await apiFetch<{ data: SettingsForm }>('/api/v1/settings');
    form.value = { ...form.value, ...result.data };
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load settings.';
  } finally {
    loading.value = false;
  }
}

function syncLandGovernment(value: number | null) {
  form.value.default_land_rate_government_weight_pct = value;
  if (value !== null) form.value.default_land_rate_market_weight_pct = Math.round((100 - value) * 100) / 100;
}
function syncLandMarket(value: number | null) {
  form.value.default_land_rate_market_weight_pct = value;
  if (value !== null) form.value.default_land_rate_government_weight_pct = Math.round((100 - value) * 100) / 100;
}

async function save() {
  saving.value = true;
  error.value = null;
  saved.value = false;
  try {
    await apiFetch('/api/v1/settings', { method: 'PUT', body: JSON.stringify(form.value) });
    saved.value = true;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to save settings.';
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="text-lg font-semibold mb-1">Valuation Settings</h1>
    <p class="text-sm text-gray-500 mb-4">
      Organization-wide default percentages. These apply whenever a client (bank) doesn't have its own
      configured convention (set on the Clients screen), and can still be overridden for any single calculation.
      Leave a field blank to use the engine's own built-in default.
    </p>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>
    <div v-if="saved" class="bg-emerald-50 text-emerald-700 text-sm rounded p-3 mb-4">Settings saved.</div>
    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>

    <div v-else class="space-y-6">
      <div class="bg-white border rounded p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Weighted Land Rate</h2>
        <p class="text-xs text-gray-400 mb-3">Real bank documents have shown 30/70, 70/30, and 20/80 government/market splits -- there is no universal correct default.</p>
        <div class="grid grid-cols-3 gap-3 text-sm">
          <div>
            <label class="block text-xs text-gray-500">Government Weight %</label>
            <input
              :value="form.default_land_rate_government_weight_pct"
              @input="syncLandGovernment(($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value))"
              type="number" min="0" max="100" class="border rounded px-2 py-1 w-full"
            />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Market Weight % (auto-fills)</label>
            <input
              :value="form.default_land_rate_market_weight_pct"
              @input="syncLandMarket(($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value))"
              type="number" min="0" max="100" class="border rounded px-2 py-1 w-full"
            />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Distress Value % of FMV</label>
            <input v-model.number="form.default_distress_value_pct" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
        </div>
      </div>

      <div class="bg-white border rounded p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Vehicle / Machinery Valuation</h2>
        <div class="grid grid-cols-3 gap-3 text-sm">
          <div>
            <label class="block text-xs text-gray-500">Scrap Deduction %</label>
            <input v-model.number="form.default_vehicle_scrap_pct" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Depreciation %/annum</label>
            <input v-model.number="form.default_vehicle_depreciation_pct_per_annum" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Other Cost %/annum</label>
            <input v-model.number="form.default_vehicle_other_cost_pct_per_annum" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
        </div>
      </div>

      <div class="bg-white border rounded p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Building Cost Estimation</h2>
        <div class="grid grid-cols-3 gap-3 text-sm">
          <div>
            <label class="block text-xs text-gray-500">Sanitary Fixture %</label>
            <input v-model.number="form.default_building_sanitary_fixture_pct" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Electrical Fixture %</label>
            <input v-model.number="form.default_building_electrical_fixture_pct" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Depreciation %/annum</label>
            <input v-model.number="form.default_building_depreciation_pct_per_annum" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
        </div>
      </div>

      <button :disabled="saving" class="px-4 py-2 text-sm bg-brand-600 text-white rounded disabled:opacity-40" @click="save">
        {{ saving ? 'Saving…' : 'Save Settings' }}
      </button>
    </div>
  </div>
</template>
