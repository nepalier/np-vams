<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';
import StatusBadge from '../ui/StatusBadge.vue';
import type { Report } from '../../types';

const props = defineProps<{ assignmentId: string; assignmentStatus: string }>();

const report = ref<Report | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const submitting = ref(false);
const showReasonFor = ref<'cancel' | 'supersede' | null>(null);
const reason = ref('');
const selectedTemplate = ref<'default' | 'bank_standard_np'>('bank_standard_np');

async function load() {
  loading.value = true;
  try {
    const result = await apiFetch<{ data: Report | null }>(`/api/v1/assignments/${props.assignmentId}/report`);
    report.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load report status.';
  } finally {
    loading.value = false;
  }
}

async function runAction(path: string, body: Record<string, unknown> = {}) {
  submitting.value = true;
  error.value = null;

  try {
    const result = await apiFetch<{ data: Report }>(`/api/v1/assignments/${props.assignmentId}/report/${path}`, {
      method: 'POST',
      body: JSON.stringify(body),
    });
    report.value = result.data;
    showReasonFor.value = null;
    reason.value = '';
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Action failed.';
  } finally {
    submitting.value = false;
  }
}

function confirmWithReason(action: 'cancel' | 'supersede') {
  if (showReasonFor.value !== action) {
    showReasonFor.value = action;
    return;
  }
  runAction(action, { reason: reason.value });
}

onMounted(load);
</script>

<template>
  <div class="bg-white border rounded p-4">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">Report</h2>

    <div v-if="loading" class="text-sm text-gray-500">Loading…</div>
    <div v-else>
      <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-2 mb-3">{{ error }}</div>

      <div v-if="!report" class="text-sm text-gray-500 mb-3">
        No report generated yet. Requires a valuation reconciliation to exist first.
      </div>
      <div v-else class="flex items-center gap-3 mb-3 text-sm">
        <StatusBadge :status="report.status" />
        <span v-if="report.report_number" class="text-gray-600">{{ report.report_number }}</span>
        <span v-if="report.current_version" class="text-gray-400 text-xs">
          v{{ report.current_version.version_number }} ({{ report.current_version.format }})
        </span>
      </div>

      <div class="mb-2 text-sm">
        <label class="text-xs text-gray-500 mr-2">Report Template</label>
        <select v-model="selectedTemplate" class="border rounded px-2 py-1 text-xs">
          <option value="bank_standard_np">Bank Standard (Nepal)</option>
          <option value="default">Default</option>
        </select>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          :disabled="submitting"
          class="px-3 py-1.5 text-sm border rounded hover:bg-brand-50 disabled:opacity-40"
          @click="runAction('generate-draft', { template: selectedTemplate })"
        >{{ report ? 'Regenerate Draft' : 'Generate Draft' }}</button>

        <button
          v-if="report && assignmentStatus === 'approved'"
          :disabled="submitting"
          class="px-3 py-1.5 text-sm border rounded hover:bg-brand-50 disabled:opacity-40"
          @click="runAction('sign')"
        >Sign Report</button>

        <button
          v-if="report && assignmentStatus === 'digitally_signed'"
          :disabled="submitting"
          class="px-3 py-1.5 text-sm border rounded hover:bg-brand-50 disabled:opacity-40"
          @click="runAction('issue')"
        >Issue Report</button>

        <button
          v-if="report && report.status === 'issued'"
          :disabled="submitting"
          class="px-3 py-1.5 text-sm border border-red-300 text-red-700 rounded hover:bg-red-50 disabled:opacity-40"
          @click="confirmWithReason('cancel')"
        >Cancel Report</button>

        <button
          v-if="report && report.status === 'issued'"
          :disabled="submitting"
          class="px-3 py-1.5 text-sm border border-amber-300 text-amber-700 rounded hover:bg-amber-50 disabled:opacity-40"
          @click="confirmWithReason('supersede')"
        >Supersede Report</button>
      </div>

      <div v-if="showReasonFor" class="mt-2 flex gap-2">
        <input v-model="reason" type="text" placeholder="Reason (required)" class="border rounded px-2 py-1 text-sm flex-1" />
        <button
          :disabled="submitting || !reason"
          class="px-3 py-1 text-sm bg-brand-600 text-white rounded disabled:opacity-40"
          @click="runAction(showReasonFor, { reason })"
        >Confirm</button>
      </div>
    </div>
  </div>
</template>
