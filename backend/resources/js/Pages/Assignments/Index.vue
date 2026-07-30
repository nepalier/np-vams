<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { apiFetch } from '../../Composables/useApi';
import StatusBadge from '../../Components/ui/StatusBadge.vue';
import type { Assignment, PaginatedResponse } from '../../types';

const assignments = ref<Assignment[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const statusFilter = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);

const STATUS_OPTIONS = [
  '', 'draft', 'submitted', 'assignment_accepted', 'documents_pending', 'valuer_assigned',
  'field_inspection_in_progress', 'under_valuation', 'under_technical_review',
  'correction_requested', 'awaiting_approval', 'approved', 'digitally_signed',
  'report_issued', 'cancelled', 'superseded',
];

async function load() {
  loading.value = true;
  error.value = null;

  try {
    const params = new URLSearchParams({ page: String(page.value), per_page: '20' });
    if (statusFilter.value) params.set('status', statusFilter.value);

    const response = await apiFetch<PaginatedResponse<Assignment>>(`/api/v1/assignments?${params}`);
    assignments.value = response.data;
    lastPage.value = response.meta.last_page;
    total.value = response.meta.total;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load assignments.';
  } finally {
    loading.value = false;
  }
}

watch(statusFilter, () => { page.value = 1; load(); });
watch(page, load);
onMounted(load);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <h1 class="text-lg font-semibold">Assignments</h1>
        <span class="text-sm text-gray-500" v-if="!loading">{{ total }} total</span>
      </div>
      <a href="/assignments/create" class="px-3 py-1.5 text-sm bg-brand-600 text-white rounded">+ New Assignment</a>
    </div>

    <div class="mb-4 flex items-center gap-3">
      <label class="text-sm text-gray-600">Status</label>
      <select v-model="statusFilter" class="border rounded px-2 py-1 text-sm">
        <option v-for="s in STATUS_OPTIONS" :key="s" :value="s">
          {{ s ? s.replace(/_/g, ' ') : 'All statuses' }}
        </option>
      </select>
    </div>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>
    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>

    <div v-else-if="assignments.length === 0" class="text-gray-500 text-sm py-8 text-center border rounded">
      No assignments found.
    </div>

    <table v-else class="w-full text-sm bg-white border rounded overflow-hidden">
      <thead class="bg-gray-50 text-gray-600 text-left">
        <tr>
          <th class="px-4 py-2 font-medium">Assignment #</th>
          <th class="px-4 py-2 font-medium">Client</th>
          <th class="px-4 py-2 font-medium">Purpose</th>
          <th class="px-4 py-2 font-medium">Status</th>
          <th class="px-4 py-2 font-medium">Priority</th>
          <th class="px-4 py-2 font-medium">Date</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="assignment in assignments"
          :key="assignment.id"
          class="border-t hover:bg-gray-50"
        >
          <td class="px-4 py-2">
            <a :href="`/assignments/${assignment.id}`" class="text-brand-600 font-medium hover:underline">
              {{ assignment.assignment_number }}
            </a>
          </td>
          <td class="px-4 py-2">{{ assignment.client_name ?? '—' }}</td>
          <td class="px-4 py-2">{{ assignment.valuation_purpose_name ?? '—' }}</td>
          <td class="px-4 py-2"><StatusBadge :status="assignment.status" /></td>
          <td class="px-4 py-2 capitalize">{{ assignment.priority }}</td>
          <td class="px-4 py-2">{{ assignment.assignment_date ?? '—' }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="!loading && lastPage > 1" class="flex items-center justify-center gap-3 mt-4 text-sm">
      <button :disabled="page <= 1" class="px-3 py-1 border rounded disabled:opacity-40" @click="page--">Previous</button>
      <span class="text-gray-600">Page {{ page }} of {{ lastPage }}</span>
      <button :disabled="page >= lastPage" class="px-3 py-1 border rounded disabled:opacity-40" @click="page++">Next</button>
    </div>
  </div>
</template>
