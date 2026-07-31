<script setup lang="ts">
import { ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

const props = defineProps<{ buildingId: string }>();

const STRUCTURAL_ITEMS = ['foundation', 'columns', 'beams', 'slabs', 'walls', 'cracks', 'settlement', 'dampness', 'roof'];

const ratings = ref<Record<string, number>>(Object.fromEntries(STRUCTURAL_ITEMS.map((i) => [i, 3])));
const saving = ref(false);
const error = ref<string | null>(null);
const suggestion = ref<{ physical_depreciation_pct: number | null } | null>(null);

async function save() {
  saving.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/buildings/${props.buildingId}/condition-assessments`, {
      method: 'POST',
      body: JSON.stringify({
        items: STRUCTURAL_ITEMS.map((item_type) => ({ item_type, condition_rating: ratings.value[item_type] })),
      }),
    });
    const preview = await apiFetch<{ data: { physical_depreciation_pct: number | null } }>(
      `/api/v1/buildings/${props.buildingId}/suggested-depreciation`,
    );
    suggestion.value = preview.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to save assessment.';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="border rounded p-3 mt-2 bg-gray-50 text-sm">
    <div v-if="error" class="bg-red-50 text-red-700 rounded p-2 mb-2">{{ error }}</div>

    <p class="text-xs text-gray-500 mb-2">Rate each item 1 (excellent) to 5 (critical):</p>
    <div class="grid grid-cols-3 gap-2 mb-3">
      <div v-for="item in STRUCTURAL_ITEMS" :key="item">
        <label class="block text-xs text-gray-500 capitalize">{{ item }}</label>
        <input v-model.number="ratings[item]" type="number" min="1" max="5" class="border rounded px-2 py-1 w-full" />
      </div>
    </div>

    <button :disabled="saving" class="px-3 py-1 bg-brand-600 text-white rounded disabled:opacity-40" @click="save">
      {{ saving ? 'Saving…' : 'Save Assessment & Preview Depreciation' }}
    </button>

    <div v-if="suggestion" class="mt-2 text-xs text-gray-600">
      <span class="font-medium">Suggested physical depreciation:</span>
      {{ suggestion.physical_depreciation_pct !== null ? `${suggestion.physical_depreciation_pct}%` : 'Not enough data' }}
    </div>
  </div>
</template>
