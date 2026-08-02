<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

interface ProfessionalProfile {
  nec_registration_number: string | null;
  professional_license_number: string | null;
  registration_validity_date: string | null;
  license_expiry_date: string | null;
}

interface AdminProfileRow {
  user: { name: string } | null;
  nec_registration_number: string | null;
  license_expiry_date: string | null;
  registration_validity_date: string | null;
}

const form = ref<ProfessionalProfile>({
  nec_registration_number: '', professional_license_number: '',
  registration_validity_date: '', license_expiry_date: '',
});
const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const saved = ref(false);

const adminView = ref<AdminProfileRow[] | null>(null);
const showAdminView = ref(false);

async function load() {
  loading.value = true;
  try {
    const result = await apiFetch<{ data: ProfessionalProfile | null }>('/api/v1/professional-profile');
    if (result.data) form.value = { ...form.value, ...result.data };
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load your professional profile.';
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  error.value = null;
  saved.value = false;
  try {
    await apiFetch('/api/v1/professional-profile', { method: 'PUT', body: JSON.stringify(form.value) });
    saved.value = true;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to save.';
  } finally {
    saving.value = false;
  }
}

async function loadAdminView() {
  showAdminView.value = !showAdminView.value;
  if (!showAdminView.value || adminView.value !== null) return;

  try {
    const result = await apiFetch<{ data: AdminProfileRow[] }>('/api/v1/professional-profiles');
    adminView.value = result.data;
  } catch (e) {
    // Not every user has permission for this view -- fail silently into an empty list rather than showing an alarming error for a non-admin.
    adminView.value = [];
  }
}

function isExpiringSoon(dateStr: string | null): boolean {
  if (!dateStr) return false;
  const days = (new Date(dateStr).getTime() - Date.now()) / (1000 * 60 * 60 * 24);
  return days >= 0 && days <= 30;
}

onMounted(load);
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-lg font-semibold mb-1">My Professional Profile</h1>
    <p class="text-sm text-gray-500 mb-4">NEC registration and license details -- you'll be alerted automatically as either approaches expiry.</p>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>
    <div v-if="saved" class="bg-emerald-50 text-emerald-700 text-sm rounded p-3 mb-4">Saved.</div>
    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>

    <div v-else class="bg-white border rounded p-4 space-y-3 text-sm">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-gray-500">NEC Registration Number</label>
          <input v-model="form.nec_registration_number" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Professional License Number</label>
          <input v-model="form.professional_license_number" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Registration Validity Date</label>
          <input v-model="form.registration_validity_date" type="date" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">License Expiry Date</label>
          <input v-model="form.license_expiry_date" type="date" class="border rounded px-2 py-1 w-full" />
        </div>
      </div>
      <button :disabled="saving" class="px-4 py-1.5 bg-brand-600 text-white rounded disabled:opacity-40" @click="save">
        {{ saving ? 'Saving…' : 'Save' }}
      </button>
    </div>

    <div class="mt-6">
      <button class="text-sm text-brand-600" @click="loadAdminView">
        {{ showAdminView ? 'Hide' : 'Show' }} Firm-wide Compliance Overview
      </button>

      <div v-if="showAdminView" class="mt-3 bg-white border rounded overflow-hidden">
        <table v-if="adminView && adminView.length > 0" class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-600 text-left">
            <tr><th class="px-4 py-2 font-medium">Name</th><th class="px-4 py-2 font-medium">NEC Reg. No.</th><th class="px-4 py-2 font-medium">Expiry</th></tr>
          </thead>
          <tbody>
            <tr v-for="(p, i) in adminView" :key="i" class="border-t">
              <td class="px-4 py-2">{{ p.user?.name ?? '—' }}</td>
              <td class="px-4 py-2">{{ p.nec_registration_number ?? '—' }}</td>
              <td class="px-4 py-2">
                <span :class="isExpiringSoon(p.license_expiry_date ?? p.registration_validity_date) ? 'text-red-600 font-medium' : ''">
                  {{ p.license_expiry_date ?? p.registration_validity_date ?? '—' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-sm text-gray-400 p-4">No profiles recorded yet, or you don't have permission to view this.</p>
      </div>
    </div>
  </div>
</template>
