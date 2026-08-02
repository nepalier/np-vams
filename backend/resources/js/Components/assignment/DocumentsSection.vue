<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

const props = defineProps<{ assignmentId: string }>();

interface DocumentRow {
  id: string; category: string; document_type: string; document_number: string | null;
  verification_status: string; verification_remarks: string | null;
}

const documents = ref<DocumentRow[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const uploading = ref(false);
const updatingId = ref<string | null>(null);

const uploadForm = ref({ category: 'land', document_type: '', document_number: '' });
const showUploadForm = ref(false);
const selectedFile = ref<File | null>(null);

const LAND_DOC_TYPES = ['Lalpurja', 'Cadastral Map', 'Trace Map', 'Field Book', 'Registration Deed', 'Land Revenue Receipt', 'Four-Boundary Certificate', 'Mutation Document', 'Court Order'];
const BUILDING_DOC_TYPES = ['Building Permit', 'Approved Architectural Drawing', 'Structural Drawing', 'Completion Certificate', 'Municipal House-Tax Receipt'];
const IDENTITY_DOC_TYPES = ['Citizenship', 'Passport', 'Company Registration', 'PAN Certificate', 'VAT Certificate', 'Power of Attorney'];

const VERIFICATION_STATUSES = [
  'received', 'original_seen', 'copy_received', 'online_verified', 'authority_verified',
  'expired', 'incomplete', 'not_applicable', 'suspected_inconsistency', 'clarification_required', 'rejected',
];

const statusColor: Record<string, string> = {
  authority_verified: 'text-emerald-600', online_verified: 'text-emerald-600', original_seen: 'text-emerald-600',
  suspected_inconsistency: 'text-red-600', rejected: 'text-red-600', expired: 'text-red-600',
  clarification_required: 'text-amber-600', incomplete: 'text-amber-600',
};

function docTypesForCategory(category: string): string[] {
  if (category === 'land') return LAND_DOC_TYPES;
  if (category === 'building') return BUILDING_DOC_TYPES;
  return IDENTITY_DOC_TYPES;
}

async function load() {
  loading.value = true;
  try {
    const result = await apiFetch<{ data: DocumentRow[] }>(`/api/v1/assignments/${props.assignmentId}/documents`);
    documents.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load documents.';
  } finally {
    loading.value = false;
  }
}

function onFileSelected(event: Event) {
  selectedFile.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}

async function upload() {
  if (!selectedFile.value) return;

  uploading.value = true;
  error.value = null;

  const formData = new FormData();
  formData.append('file', selectedFile.value);
  formData.append('category', uploadForm.value.category);
  formData.append('document_type', uploadForm.value.document_type);
  if (uploadForm.value.document_number) formData.append('document_number', uploadForm.value.document_number);

  try {
    await apiFetch(`/api/v1/assignments/${props.assignmentId}/documents`, { method: 'POST', body: formData });
    uploadForm.value = { category: 'land', document_type: '', document_number: '' };
    selectedFile.value = null;
    showUploadForm.value = false;
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Upload failed.';
  } finally {
    uploading.value = false;
  }
}

async function updateStatus(doc: DocumentRow, status: string) {
  updatingId.value = doc.id;
  error.value = null;
  try {
    await apiFetch(`/api/v1/documents/${doc.id}/verification`, {
      method: 'PUT',
      body: JSON.stringify({ verification_status: status }),
    });
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to update status.';
  } finally {
    updatingId.value = null;
  }
}

onMounted(load);
</script>

<template>
  <div class="bg-white border rounded p-4">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">Documents</h2>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-2 mb-3">{{ error }}</div>

    <button class="text-sm text-brand-600 mb-3" @click="showUploadForm = !showUploadForm">
      {{ showUploadForm ? 'Cancel' : '+ Upload Document' }}
    </button>

    <div v-if="showUploadForm" class="border rounded p-3 mb-3 text-sm space-y-2">
      <div class="grid grid-cols-3 gap-2">
        <div>
          <label class="block text-xs text-gray-500">Category</label>
          <select v-model="uploadForm.category" class="border rounded px-2 py-1 w-full">
            <option value="land">Land</option>
            <option value="building">Building</option>
            <option value="identity_organizational">Identity / Organizational</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Document Type</label>
          <select v-model="uploadForm.document_type" class="border rounded px-2 py-1 w-full">
            <option value="" disabled>Select type</option>
            <option v-for="t in docTypesForCategory(uploadForm.category)" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Document Number</label>
          <input v-model="uploadForm.document_number" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
      </div>
      <input type="file" accept="application/pdf,image/*,.doc,.docx" @change="onFileSelected" class="text-xs" />
      <button
        :disabled="uploading || !selectedFile || !uploadForm.document_type"
        class="px-3 py-1 bg-brand-600 text-white rounded disabled:opacity-40"
        @click="upload"
      >{{ uploading ? 'Uploading…' : 'Upload' }}</button>
    </div>

    <p v-if="loading" class="text-sm text-gray-500">Loading…</p>
    <p v-else-if="documents.length === 0" class="text-sm text-gray-400">No documents uploaded yet.</p>

    <table v-else class="w-full text-sm">
      <thead class="text-gray-500 text-left">
        <tr><th class="py-1">Type</th><th class="py-1">Category</th><th class="py-1">Status</th></tr>
      </thead>
      <tbody>
        <tr v-for="doc in documents" :key="doc.id" class="border-t">
          <td class="py-1">{{ doc.document_type }}<span v-if="doc.document_number" class="text-gray-400"> ({{ doc.document_number }})</span></td>
          <td class="py-1 capitalize">{{ doc.category.replace('_', ' ') }}</td>
          <td class="py-1">
            <select
              :value="doc.verification_status"
              :disabled="updatingId === doc.id"
              :class="['border rounded px-1 py-0.5 text-xs', statusColor[doc.verification_status] ?? '']"
              @change="updateStatus(doc, ($event.target as HTMLSelectElement).value)"
            >
              <option v-for="s in VERIFICATION_STATUSES" :key="s" :value="s">{{ s.replace(/_/g, ' ') }}</option>
            </select>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
