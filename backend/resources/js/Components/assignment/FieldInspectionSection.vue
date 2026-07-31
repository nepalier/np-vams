<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';
import StatusBadge from '../ui/StatusBadge.vue';

const props = defineProps<{ assignmentId: string; assignmentNumber: string }>();

interface SitePhoto { id: string; category: string; watermarked_path: string | null; }
interface SiteVisit {
  id: string; scheduled_at: string; checked_in_at: string | null; status: string;
  owner_representative_confirmed: boolean; field_checklist: string[] | null; field_notes: string | null;
  inspection_completed: boolean; photos: SitePhoto[];
}

const visits = ref<SiteVisit[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const submitting = ref(false);

const showScheduleForm = ref(false);
const scheduleDate = ref('');

const CHECKLIST_ITEMS = ['Land measured', 'Building measured', 'Access road photographed', 'Boundary confirmed', 'Owner/representative present'];
const checklistState = ref<Record<string, string[]>>({}); // visitId -> checked items
const notesState = ref<Record<string, string>>({});
const uploadingPhotoFor = ref<string | null>(null);
const photoCategory = ref('front_view');

async function load() {
  loading.value = true;
  try {
    const result = await apiFetch<{ data: SiteVisit[] }>(`/api/v1/assignments/${props.assignmentId}/site-visits`);
    visits.value = result.data;
    for (const v of result.data) {
      checklistState.value[v.id] = v.field_checklist ?? [];
      notesState.value[v.id] = v.field_notes ?? '';
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load site visits.';
  } finally {
    loading.value = false;
  }
}

async function scheduleVisit() {
  submitting.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/assignments/${props.assignmentId}/site-visits`, {
      method: 'POST',
      body: JSON.stringify({ scheduled_at: scheduleDate.value }),
    });
    scheduleDate.value = '';
    showScheduleForm.value = false;
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to schedule visit.';
  } finally {
    submitting.value = false;
  }
}

async function checkIn(visitId: string) {
  if (!navigator.geolocation) {
    error.value = 'GPS is not available in this browser.';
    return;
  }

  submitting.value = true;
  error.value = null;

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      try {
        await apiFetch(`/api/v1/site-visits/${visitId}/check-in`, {
          method: 'POST',
          body: JSON.stringify({
            check_in_latitude: position.coords.latitude,
            check_in_longitude: position.coords.longitude,
          }),
        });
        await load();
      } catch (e) {
        error.value = e instanceof Error ? e.message : 'Check-in failed.';
      } finally {
        submitting.value = false;
      }
    },
    () => {
      error.value = 'Could not get your location -- GPS permission may have been denied.';
      submitting.value = false;
    },
  );
}

function toggleChecklistItem(visitId: string, item: string) {
  const list = checklistState.value[visitId];
  const index = list.indexOf(item);
  if (index === -1) list.push(item); else list.splice(index, 1);
}

async function saveChecklist(visitId: string) {
  submitting.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/site-visits/${visitId}`, {
      method: 'PUT',
      body: JSON.stringify({
        owner_representative_confirmed: true,
        field_checklist: checklistState.value[visitId],
        field_notes: notesState.value[visitId],
      }),
    });
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to save checklist.';
  } finally {
    submitting.value = false;
  }
}

async function completeInspection(visitId: string) {
  submitting.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/site-visits/${visitId}/complete`, { method: 'POST' });
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to complete inspection -- check-in, owner confirmation, checklist, and GPS are all required first.';
  } finally {
    submitting.value = false;
  }
}

async function uploadPhoto(visitId: string, event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file) return;

  uploadingPhotoFor.value = visitId;
  error.value = null;

  const formData = new FormData();
  formData.append('photo', file);
  formData.append('category', photoCategory.value);
  formData.append('site_visit_id', visitId);
  formData.append('assignment_number', props.assignmentNumber);

  try {
    await apiFetch('/api/v1/site-photos', { method: 'POST', body: formData });
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Photo upload failed.';
  } finally {
    uploadingPhotoFor.value = null;
  }
}

onMounted(load);
</script>

<template>
  <div class="bg-white border rounded p-4">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">Field Inspection</h2>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-2 mb-3">{{ error }}</div>
    <div v-if="loading" class="text-sm text-gray-500">Loading…</div>

    <template v-else>
      <button class="text-sm text-brand-600 mb-3" @click="showScheduleForm = !showScheduleForm">
        {{ showScheduleForm ? 'Cancel' : '+ Schedule Site Visit' }}
      </button>

      <div v-if="showScheduleForm" class="border rounded p-3 mb-3 flex gap-2 items-end text-sm">
        <div>
          <label class="block text-xs text-gray-500">Date/Time</label>
          <input v-model="scheduleDate" type="datetime-local" class="border rounded px-2 py-1" />
        </div>
        <button :disabled="submitting || !scheduleDate" class="px-3 py-1.5 bg-brand-600 text-white rounded disabled:opacity-40" @click="scheduleVisit">
          Schedule
        </button>
      </div>

      <p v-if="visits.length === 0" class="text-sm text-gray-400">No site visits scheduled yet.</p>

      <div v-for="visit in visits" :key="visit.id" class="border-t py-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium">{{ new Date(visit.scheduled_at).toLocaleString() }}</span>
          <StatusBadge :status="visit.status" />
        </div>

        <div class="flex flex-wrap gap-2 mb-3 text-sm">
          <button v-if="!visit.checked_in_at" :disabled="submitting" class="px-3 py-1 border rounded hover:bg-brand-50 disabled:opacity-40" @click="checkIn(visit.id)">
            GPS Check-In
          </button>
          <span v-else class="text-emerald-600 text-xs">✓ Checked in</span>

          <button v-if="!visit.inspection_completed" :disabled="submitting" class="px-3 py-1 border rounded hover:bg-brand-50 disabled:opacity-40" @click="completeInspection(visit.id)">
            Complete Inspection
          </button>
          <span v-else class="text-emerald-600 text-xs">✓ Inspection completed</span>
        </div>

        <div v-if="!visit.inspection_completed" class="mb-3">
          <p class="text-xs text-gray-500 mb-1">Field Checklist</p>
          <div class="flex flex-wrap gap-3 text-sm mb-2">
            <label v-for="item in CHECKLIST_ITEMS" :key="item" class="flex items-center gap-1">
              <input type="checkbox" :checked="checklistState[visit.id]?.includes(item)" @change="toggleChecklistItem(visit.id, item)" />
              {{ item }}
            </label>
          </div>
          <textarea v-model="notesState[visit.id]" placeholder="Field notes" class="border rounded px-2 py-1 w-full text-sm mb-2" rows="2"></textarea>
          <button :disabled="submitting" class="px-3 py-1 text-sm border rounded hover:bg-brand-50 disabled:opacity-40" @click="saveChecklist(visit.id)">
            Save Checklist &amp; Notes
          </button>
        </div>

        <div>
          <p class="text-xs text-gray-500 mb-1">Photos ({{ visit.photos.length }})</p>
          <div class="flex items-center gap-2">
            <select v-model="photoCategory" class="border rounded px-2 py-1 text-xs">
              <option value="front_view">Front View</option>
              <option value="rear_view">Rear View</option>
              <option value="access_road">Access Road</option>
              <option value="boundary">Boundary</option>
              <option value="internal_room">Internal Room</option>
              <option value="structural_defect">Structural Defect</option>
              <option value="other">Other</option>
            </select>
            <input type="file" accept="image/*" :disabled="uploadingPhotoFor === visit.id" @change="uploadPhoto(visit.id, $event)" class="text-xs" />
            <span v-if="uploadingPhotoFor === visit.id" class="text-xs text-gray-400">Uploading &amp; watermarking…</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
