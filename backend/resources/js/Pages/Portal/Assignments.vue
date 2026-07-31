<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import StatusBadge from '../../Components/ui/StatusBadge.vue';
import type { Assignment, PaginatedResponse } from '../../types';

defineOptions({ layout: PortalLayout });

const assignments = ref<Assignment[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

async function load() {
  try {
    const result = await apiFetch<PaginatedResponse<Assignment>>('/api/v1/portal/assignments?per_page=50');
    assignments.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load your cases.';
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div>
    <h1 class="text-lg font-semibold mb-4">My Cases</h1>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>
    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>
    <div v-else-if="assignments.length === 0" class="text-gray-500 text-sm py-8 text-center border rounded bg-white">
      No cases submitted yet.
    </div>
    <table v-else class="w-full text-sm bg-white border rounded overflow-hidden">
      <thead class="bg-gray-50 text-gray-600 text-left">
        <tr>
          <th class="px-4 py-2 font-medium">Assignment #</th>
          <th class="px-4 py-2 font-medium">Purpose</th>
          <th class="px-4 py-2 font-medium">Status</th>
          <th class="px-4 py-2 font-medium">Date</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="a in assignments" :key="a.id" class="border-t">
          <td class="px-4 py-2 font-medium">{{ a.assignment_number }}</td>
          <td class="px-4 py-2">{{ a.valuation_purpose_name ?? '—' }}</td>
          <td class="px-4 py-2"><StatusBadge :status="a.status" /></td>
          <td class="px-4 py-2">{{ a.assignment_date ?? '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
