<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

interface FirmDashboard {
  new_assignments_last_30_days: number;
  pending_documents: number;
  upcoming_site_visits: number;
  reports_under_review: number;
  overdue_assignments: number;
  reports_issued_last_30_days: number;
  average_turnaround_days: number | null;
  revenue_last_30_days: number;
  receivables_outstanding: number;
  valuer_workload: Record<string, number>;
  client_wise_assignment_count: Record<string, number>;
  district_wise_assignment_count: Record<string, number>;
  revaluation_due_count: number;
  rejection_rate_pct: number | null;
}

const loading = ref(true);
const error = ref<string | null>(null);
const summary = ref<FirmDashboard | null>(null);

async function load() {
  const token = localStorage.getItem('npvams_token');
  if (!token) {
    window.location.href = '/login';
    return;
  }

  try {
    const result = await apiFetch<{ data: FirmDashboard }>('/api/v1/dashboards/firm');
    summary.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load dashboard.';
  } finally {
    loading.value = false;
  }
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('en-NP', { style: 'currency', currency: 'NPR', maximumFractionDigits: 0 }).format(value);
}

onMounted(load);
</script>

<template>
  <div>
    <h1 class="text-lg font-semibold mb-4">Dashboard</h1>

    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>
    <div v-else-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3">{{ error }}</div>

    <template v-else-if="summary">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">New Assignments (30d)</p>
          <p class="text-2xl font-semibold text-brand-700">{{ summary.new_assignments_last_30_days }}</p>
        </div>
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Reports Under Review</p>
          <p class="text-2xl font-semibold text-amber-600">{{ summary.reports_under_review }}</p>
        </div>
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Overdue Assignments</p>
          <p class="text-2xl font-semibold text-red-600">{{ summary.overdue_assignments }}</p>
        </div>
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Reports Issued (30d)</p>
          <p class="text-2xl font-semibold text-emerald-600">{{ summary.reports_issued_last_30_days }}</p>
        </div>
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Revenue (30d)</p>
          <p class="text-2xl font-semibold text-brand-700">{{ formatCurrency(summary.revenue_last_30_days) }}</p>
        </div>
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Receivables Outstanding</p>
          <p class="text-2xl font-semibold text-gray-700">{{ formatCurrency(summary.receivables_outstanding) }}</p>
        </div>
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Avg. Turnaround</p>
          <p class="text-2xl font-semibold text-gray-700">
            {{ summary.average_turnaround_days !== null ? `${summary.average_turnaround_days}d` : '—' }}
          </p>
        </div>
        <div class="bg-white border rounded p-4">
          <p class="text-xs text-gray-500">Revaluations Due</p>
          <p class="text-2xl font-semibold text-gray-700">{{ summary.revaluation_due_count }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white border rounded p-4">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Valuer Workload</h2>
          <ul v-if="Object.keys(summary.valuer_workload).length" class="text-sm space-y-1">
            <li v-for="(count, valuerId) in summary.valuer_workload" :key="valuerId" class="flex justify-between">
              <span class="text-gray-500">{{ valuerId }}</span>
              <span class="font-medium">{{ count }}</span>
            </li>
          </ul>
          <p v-else class="text-sm text-gray-400">No active assignments with a valuer assigned.</p>
        </div>

        <div class="bg-white border rounded p-4">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">District-wise Assignments</h2>
          <ul v-if="Object.keys(summary.district_wise_assignment_count).length" class="text-sm space-y-1">
            <li v-for="(count, district) in summary.district_wise_assignment_count" :key="district" class="flex justify-between">
              <span class="text-gray-500">{{ district }}</span>
              <span class="font-medium">{{ count }}</span>
            </li>
          </ul>
          <p v-else class="text-sm text-gray-400">No property district data yet.</p>
        </div>
      </div>
    </template>
  </div>
</template>
