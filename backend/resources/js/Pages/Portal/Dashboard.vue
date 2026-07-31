<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';
import PortalLayout from '../../Layouts/PortalLayout.vue';

defineOptions({ layout: PortalLayout });

interface PortalDashboard {
  total_requests: number;
  pending_cases: number;
  reports_received: number;
  average_turnaround_days: number | null;
  revaluation_due_count: number;
  branch_wise_case_count: Record<string, number>;
  property_value_distribution: Record<string, number>;
}

const summary = ref<PortalDashboard | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

async function load() {
  const token = localStorage.getItem('npvams_token');
  if (!token) {
    window.location.href = '/login';
    return;
  }

  try {
    const result = await apiFetch<{ data: PortalDashboard }>('/api/v1/portal/dashboard');
    summary.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load dashboard.';
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div>
    <h1 class="text-lg font-semibold mb-4">Your Dashboard</h1>

    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>
    <div v-else-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3">{{ error }}</div>

    <div v-else class="space-y-6">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Total Requests</p>
          <p class="text-2xl font-semibold text-brand-700">{{ summary?.total_requests ?? '—' }}</p>
        </div>
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Pending Cases</p>
          <p class="text-2xl font-semibold text-amber-600">{{ summary?.pending_cases ?? '—' }}</p>
        </div>
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Reports Received</p>
          <p class="text-2xl font-semibold text-emerald-600">{{ summary?.reports_received ?? '—' }}</p>
        </div>
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Revaluation Due</p>
          <p class="text-2xl font-semibold text-gray-700">{{ summary?.revaluation_due_count ?? '—' }}</p>
        </div>
      </div>

      <div v-if="summary && Object.keys(summary.branch_wise_case_count).length" class="bg-white border rounded p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Cases by Branch</h2>
        <ul class="text-sm space-y-1">
          <li v-for="(count, branch) in summary.branch_wise_case_count" :key="branch" class="flex justify-between">
            <span class="text-gray-500">{{ branch }}</span>
            <span class="font-medium">{{ count }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
