<script setup lang="ts">
import { ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';
import type { ValuationCalculation } from '../../types';

const props = defineProps<{ assignmentId: string }>();

const activeTab = ref<'market_comparison' | 'cost_approach'>('market_comparison');
const submitting = ref(false);
const error = ref<string | null>(null);
const lastResult = ref<ValuationCalculation | null>(null);

// -- Market comparison form state --
interface ComparableRow {
  base_rate: number | null;
  time_pct: number;
  location_pct: number;
  road_width_pct: number;
  corner_plot_pct: number;
}
const comparables = ref<ComparableRow[]>([{ base_rate: null, time_pct: 0, location_pct: 0, road_width_pct: 0, corner_plot_pct: 0 }]);

function addComparable() {
  comparables.value.push({ base_rate: null, time_pct: 0, location_pct: 0, road_width_pct: 0, corner_plot_pct: 0 });
}
function removeComparable(index: number) {
  comparables.value.splice(index, 1);
}

async function submitMarketComparison() {
  submitting.value = true;
  error.value = null;

  try {
    const payload = {
      comparables: comparables.value.map((c) => ({
        base_rate: c.base_rate,
        factors: {
          time: 1 + c.time_pct / 100,
          location: 1 + c.location_pct / 100,
          road_width: 1 + c.road_width_pct / 100,
          corner_plot: 1 + c.corner_plot_pct / 100,
        },
      })),
    };

    const result = await apiFetch<{ data: ValuationCalculation }>(
      `/api/v1/assignments/${props.assignmentId}/calculations/market-comparison`,
      { method: 'POST', body: JSON.stringify(payload) },
    );
    lastResult.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Calculation failed.';
  } finally {
    submitting.value = false;
  }
}

// -- Cost approach form state --
const costForm = ref({
  built_up_area_sqm: null as number | null,
  base_construction_rate: null as number | null,
  depreciation_method: 'straight_line',
  age_years: null as number | null,
  economic_life_years: null as number | null,
  physical_depreciation_pct: null as number | null,
});

async function submitCostApproach() {
  submitting.value = true;
  error.value = null;

  try {
    const result = await apiFetch<{ data: ValuationCalculation }>(
      `/api/v1/assignments/${props.assignmentId}/calculations/cost-approach`,
      { method: 'POST', body: JSON.stringify(costForm.value) },
    );
    lastResult.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Calculation failed.';
  } finally {
    submitting.value = false;
  }
}

function formatCurrency(value: string | number): string {
  return new Intl.NumberFormat('en-NP', { style: 'currency', currency: 'NPR' }).format(Number(value));
}
</script>

<template>
  <div class="bg-white border rounded p-4">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">Valuation Calculations</h2>

    <div class="flex gap-2 mb-4 text-sm">
      <button
        :class="['px-3 py-1 rounded', activeTab === 'market_comparison' ? 'bg-brand-600 text-white' : 'bg-gray-100']"
        @click="activeTab = 'market_comparison'"
      >Market Comparison</button>
      <button
        :class="['px-3 py-1 rounded', activeTab === 'cost_approach' ? 'bg-brand-600 text-white' : 'bg-gray-100']"
        @click="activeTab = 'cost_approach'"
      >Cost Approach</button>
    </div>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-2 mb-3">{{ error }}</div>

    <!-- Market Comparison -->
    <div v-if="activeTab === 'market_comparison'" class="space-y-3">
      <div v-for="(row, i) in comparables" :key="i" class="border rounded p-3">
        <div class="flex justify-between items-center mb-2">
          <span class="text-xs font-medium text-gray-500">Comparable #{{ i + 1 }}</span>
          <button v-if="comparables.length > 1" class="text-xs text-red-600" @click="removeComparable(i)">Remove</button>
        </div>
        <div class="grid grid-cols-5 gap-2 text-sm">
          <div>
            <label class="block text-xs text-gray-500">Base Rate (Rs/sqm)</label>
            <input v-model.number="row.base_rate" type="number" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Time %</label>
            <input v-model.number="row.time_pct" type="number" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Location %</label>
            <input v-model.number="row.location_pct" type="number" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Road Width %</label>
            <input v-model.number="row.road_width_pct" type="number" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Corner Plot %</label>
            <input v-model.number="row.corner_plot_pct" type="number" class="border rounded px-2 py-1 w-full" />
          </div>
        </div>
      </div>

      <div class="flex gap-2">
        <button class="text-sm text-brand-600" @click="addComparable">+ Add comparable</button>
      </div>

      <button
        :disabled="submitting"
        class="px-4 py-1.5 text-sm bg-brand-600 text-white rounded disabled:opacity-40"
        @click="submitMarketComparison"
      >{{ submitting ? 'Calculating…' : 'Calculate' }}</button>
    </div>

    <!-- Cost Approach -->
    <div v-else class="space-y-3">
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <label class="block text-xs text-gray-500">Built-up Area (sqm)</label>
          <input v-model.number="costForm.built_up_area_sqm" type="number" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Base Construction Rate (Rs/sqm)</label>
          <input v-model.number="costForm.base_construction_rate" type="number" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Depreciation Method</label>
          <select v-model="costForm.depreciation_method" class="border rounded px-2 py-1 w-full">
            <option value="straight_line">Straight Line</option>
            <option value="age_life">Age-Life</option>
            <option value="observed_condition">Observed Condition</option>
            <option value="custom_professional">Custom Professional</option>
          </select>
        </div>
        <template v-if="['straight_line', 'age_life'].includes(costForm.depreciation_method)">
          <div>
            <label class="block text-xs text-gray-500">Age (years)</label>
            <input v-model.number="costForm.age_years" type="number" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Economic Life (years)</label>
            <input v-model.number="costForm.economic_life_years" type="number" class="border rounded px-2 py-1 w-full" />
          </div>
        </template>
        <div v-else>
          <label class="block text-xs text-gray-500">Physical Depreciation %</label>
          <input v-model.number="costForm.physical_depreciation_pct" type="number" class="border rounded px-2 py-1 w-full" />
        </div>
      </div>

      <button
        :disabled="submitting"
        class="px-4 py-1.5 text-sm bg-brand-600 text-white rounded disabled:opacity-40"
        @click="submitCostApproach"
      >{{ submitting ? 'Calculating…' : 'Calculate' }}</button>
    </div>

    <!-- Result -->
    <div v-if="lastResult" class="mt-4 border-t pt-3">
      <p class="text-sm font-medium text-gray-700">
        {{ lastResult.method === 'market_comparison' ? 'Suggested Adopted Rate' : 'Depreciated Value' }}:
        <span class="text-brand-700 font-semibold">{{ formatCurrency(lastResult.computed_value) }}</span>
      </p>
      <details class="mt-2 text-xs text-gray-500">
        <summary class="cursor-pointer">View calculation details</summary>
        <pre class="mt-2 bg-gray-50 p-2 rounded overflow-auto">{{ JSON.stringify(lastResult.computed_details, null, 2) }}</pre>
      </details>
    </div>
  </div>
</template>
