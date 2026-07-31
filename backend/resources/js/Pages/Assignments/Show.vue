<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';
import StatusBadge from '../../Components/ui/StatusBadge.vue';
import ValuationSection from '../../Components/assignment/ValuationSection.vue';
import FieldInspectionSection from '../../Components/assignment/FieldInspectionSection.vue';
import ReviewSection from '../../Components/assignment/ReviewSection.vue';
import ReportSection from '../../Components/assignment/ReportSection.vue';
import type { Assignment } from '../../types';

const props = defineProps<{ id: string }>();

const assignment = ref<Assignment | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const transitioning = ref(false);
const showRemarksFor = ref<string | null>(null);
const remarks = ref('');

async function load() {
  loading.value = true;
  error.value = null;

  try {
    assignment.value = (await apiFetch<{ data: Assignment }>(`/api/v1/assignments/${props.id}`)).data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load assignment.';
  } finally {
    loading.value = false;
  }
}

async function transition(toStatus: string) {
  transitioning.value = true;
  error.value = null;

  try {
    const result = await apiFetch<{ data: Assignment }>(`/api/v1/assignments/${props.id}/workflow/transition`, {
      method: 'POST',
      body: JSON.stringify({ to_status: toStatus, remarks: remarks.value || undefined }),
    });
    assignment.value = result.data;
    showRemarksFor.value = null;
    remarks.value = '';
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Transition failed.';
  } finally {
    transitioning.value = false;
  }
}

// Statuses whose workflow edge requires remarks -- kept in sync with
// WorkflowSeeder's requires_remarks flags for the edges a user is likely
// to trigger from the UI. If the server rejects a transition for missing
// remarks that this list doesn't cover, the error message from the API
// still surfaces via `error`, so nothing is silently swallowed either way.
const REMARKS_REQUIRED_TARGETS = ['correction_requested', 'cancelled', 'superseded', 'archived'];

function handleTransitionClick(toStatus: string) {
  if (REMARKS_REQUIRED_TARGETS.includes(toStatus) && showRemarksFor.value !== toStatus) {
    showRemarksFor.value = toStatus;
    return;
  }
  transition(toStatus);
}

const formattedFee = computed(() => {
  if (!assignment.value) return '';
  return new Intl.NumberFormat('en-NP', { style: 'currency', currency: 'NPR' }).format(Number(assignment.value.total_fee));
});

onMounted(load);
</script>

<template>
  <div>
    <a href="/assignments" class="text-sm text-brand-600 hover:underline">← Back to assignments</a>

    <div v-if="loading" class="text-gray-500 text-sm mt-4">Loading…</div>
    <div v-else-if="error && !assignment" class="bg-red-50 text-red-700 text-sm rounded p-3 mt-4">{{ error }}</div>

    <template v-else-if="assignment">
      <div class="flex items-center justify-between mt-4 mb-6">
        <div>
          <h1 class="text-xl font-semibold">{{ assignment.assignment_number }}</h1>
          <p class="text-gray-500 text-sm">{{ assignment.client_name ?? 'Unknown client' }} — {{ assignment.valuation_purpose_name ?? '—' }}</p>
        </div>
        <StatusBadge :status="assignment.status" />
      </div>

      <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>

      <div class="grid grid-cols-2 gap-6 mb-6">
        <div class="bg-white border rounded p-4">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Details</h2>
          <dl class="text-sm space-y-2">
            <div class="flex justify-between"><dt class="text-gray-500">Assignment date</dt><dd>{{ assignment.assignment_date ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Requested completion</dt><dd>{{ assignment.requested_completion_date ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Priority</dt><dd class="capitalize">{{ assignment.priority }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Total fee</dt><dd>{{ formattedFee }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Payment status</dt><dd class="capitalize">{{ assignment.payment_status.replace('_', ' ') }}</dd></div>
          </dl>
        </div>

        <div class="bg-white border rounded p-4">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Assignees</h2>
          <dl class="text-sm space-y-2">
            <div class="flex justify-between"><dt class="text-gray-500">Valuer</dt><dd>{{ assignment.assigned_valuer_name ?? 'Unassigned' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Approver</dt><dd>{{ assignment.assigned_approver_name ?? 'Unassigned' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Properties</dt><dd>{{ assignment.properties?.length ?? 0 }}</dd></div>
          </dl>
        </div>
      </div>

      <div class="bg-white border rounded p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Workflow</h2>

        <p v-if="assignment.available_transitions.length === 0" class="text-sm text-gray-500">
          No further transitions available from this status.
        </p>

        <div v-else class="flex flex-wrap gap-2">
          <div v-for="target in assignment.available_transitions" :key="target">
            <button
              :disabled="transitioning"
              class="px-3 py-1.5 text-sm border rounded hover:bg-brand-50 hover:border-brand-300 disabled:opacity-40"
              @click="handleTransitionClick(target)"
            >
              Move to: {{ target.replace(/_/g, ' ') }}
            </button>

            <div v-if="showRemarksFor === target" class="mt-2 flex gap-2">
              <input
                v-model="remarks"
                type="text"
                placeholder="Remarks (required for this transition)"
                class="border rounded px-2 py-1 text-sm flex-1"
              />
              <button
                :disabled="transitioning || !remarks"
                class="px-3 py-1 text-sm bg-brand-600 text-white rounded disabled:opacity-40"
                @click="transition(target)"
              >
                Confirm
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 mt-6">
        <FieldInspectionSection :assignment-id="assignment.id" :assignment-number="assignment.assignment_number" />
        <ValuationSection :assignment-id="assignment.id" />
        <ReviewSection :assignment-id="assignment.id" />
        <ReportSection :assignment-id="assignment.id" :assignment-status="assignment.status" />
      </div>
    </template>
  </div>
</template>
