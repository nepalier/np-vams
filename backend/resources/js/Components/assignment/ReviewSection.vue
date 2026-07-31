<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

const props = defineProps<{ assignmentId: string }>();

interface Comment {
  id: string; section: string | null; comment: string; severity: string;
  is_resolved: boolean; created_by_user_id: string; created_at: string;
}
interface Decision {
  id: string; stage: string; decision: string; remarks: string | null; decided_at: string;
}

const comments = ref<Comment[]>([]);
const decisions = ref<Decision[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const submitting = ref(false);

const commentForm = ref({ section: '', comment: '', severity: 'information' });
const showCommentForm = ref(false);

const reviewDecision = ref('recommend_approval');
const reviewRemarks = ref('');
const approvalDecision = ref('approve');
const approvalRemarks = ref('');

async function load() {
  loading.value = true;
  try {
    const result = await apiFetch<{ data: { comments: Comment[]; decisions: Decision[] } }>(
      `/api/v1/assignments/${props.assignmentId}/review`,
    );
    comments.value = result.data.comments;
    decisions.value = result.data.decisions;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load review history.';
  } finally {
    loading.value = false;
  }
}

async function submitComment() {
  submitting.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/assignments/${props.assignmentId}/review/comments`, {
      method: 'POST',
      body: JSON.stringify(commentForm.value),
    });
    commentForm.value = { section: '', comment: '', severity: 'information' };
    showCommentForm.value = false;
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to add comment.';
  } finally {
    submitting.value = false;
  }
}

async function submitReviewDecision() {
  submitting.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/assignments/${props.assignmentId}/review/decision`, {
      method: 'POST',
      body: JSON.stringify({ decision: reviewDecision.value, remarks: reviewRemarks.value || undefined }),
    });
    reviewRemarks.value = '';
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Review decision failed.';
  } finally {
    submitting.value = false;
  }
}

async function submitApprovalDecision() {
  submitting.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/assignments/${props.assignmentId}/approval/decision`, {
      method: 'POST',
      body: JSON.stringify({ decision: approvalDecision.value, remarks: approvalRemarks.value || undefined }),
    });
    approvalRemarks.value = '';
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Approval decision failed. This often means segregation-of-duties rules blocked it (the same person cannot review/approve their own work).';
  } finally {
    submitting.value = false;
  }
}

const severityColor: Record<string, string> = {
  information: 'text-gray-500', warning: 'text-amber-600', high_risk: 'text-orange-600', blocking_error: 'text-red-600',
};

onMounted(load);
</script>

<template>
  <div class="bg-white border rounded p-4">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">Review &amp; Approval</h2>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-2 mb-3">{{ error }}</div>
    <div v-if="loading" class="text-sm text-gray-500">Loading…</div>

    <template v-else>
      <!-- Add comment -->
      <div class="mb-4">
        <button class="text-sm text-brand-600" @click="showCommentForm = !showCommentForm">
          {{ showCommentForm ? 'Cancel' : '+ Add Comment' }}
        </button>
        <div v-if="showCommentForm" class="mt-2 border rounded p-3 space-y-2 text-sm">
          <input v-model="commentForm.section" type="text" placeholder="Section (optional, e.g. land_valuation)" class="border rounded px-2 py-1 w-full" />
          <textarea v-model="commentForm.comment" placeholder="Comment" class="border rounded px-2 py-1 w-full" rows="2"></textarea>
          <select v-model="commentForm.severity" class="border rounded px-2 py-1 w-full">
            <option value="information">Information</option>
            <option value="warning">Warning</option>
            <option value="high_risk">High Risk</option>
            <option value="blocking_error">Blocking Error</option>
          </select>
          <button :disabled="submitting || !commentForm.comment" class="px-3 py-1 bg-brand-600 text-white rounded disabled:opacity-40" @click="submitComment">
            Save Comment
          </button>
        </div>
      </div>

      <!-- Comment list -->
      <div class="mb-4">
        <p v-if="comments.length === 0" class="text-sm text-gray-400">No comments yet.</p>
        <div v-for="c in comments" :key="c.id" class="border-t py-2 text-sm">
          <span :class="['font-medium', severityColor[c.severity]]">[{{ c.severity.replace('_', ' ') }}]</span>
          <span v-if="c.section" class="text-gray-400"> ({{ c.section }})</span>
          <p class="text-gray-700">{{ c.comment }}</p>
        </div>
      </div>

      <!-- Decisions -->
      <div class="grid grid-cols-2 gap-4 border-t pt-4">
        <div>
          <h3 class="text-xs font-semibold text-gray-500 mb-2">Technical Review Decision</h3>
          <select v-model="reviewDecision" class="border rounded px-2 py-1 w-full text-sm mb-2">
            <option value="accept">Accept</option>
            <option value="reject">Reject</option>
            <option value="recommend_approval">Recommend Approval</option>
          </select>
          <input v-model="reviewRemarks" type="text" placeholder="Remarks" class="border rounded px-2 py-1 w-full text-sm mb-2" />
          <button :disabled="submitting" class="px-3 py-1 text-sm border rounded hover:bg-brand-50 disabled:opacity-40" @click="submitReviewDecision">
            Submit Review Decision
          </button>
        </div>
        <div>
          <h3 class="text-xs font-semibold text-gray-500 mb-2">Final Approval Decision</h3>
          <select v-model="approvalDecision" class="border rounded px-2 py-1 w-full text-sm mb-2">
            <option value="approve">Approve</option>
            <option value="return_for_correction">Return for Correction</option>
            <option value="cancel">Cancel</option>
          </select>
          <input v-model="approvalRemarks" type="text" placeholder="Remarks" class="border rounded px-2 py-1 w-full text-sm mb-2" />
          <button :disabled="submitting" class="px-3 py-1 text-sm border rounded hover:bg-brand-50 disabled:opacity-40" @click="submitApprovalDecision">
            Submit Approval Decision
          </button>
        </div>
      </div>

      <!-- Decision history -->
      <div v-if="decisions.length > 0" class="mt-4 border-t pt-3">
        <h3 class="text-xs font-semibold text-gray-500 mb-2">Decision History</h3>
        <div v-for="d in decisions" :key="d.id" class="text-sm text-gray-600 py-1">
          <span class="font-medium">{{ d.stage.replace('_', ' ') }}</span>: {{ d.decision.replace(/_/g, ' ') }}
          <span v-if="d.remarks" class="text-gray-400"> — {{ d.remarks }}</span>
        </div>
      </div>
    </template>
  </div>
</template>
