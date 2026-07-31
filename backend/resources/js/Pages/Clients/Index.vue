<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

interface ClientRecord {
  id: string;
  name_en: string;
  name_ne: string | null;
  client_type: string;
  telephone: string | null;
  email: string | null;
  is_active: boolean;
  land_rate_government_weight_pct: number | null;
  land_rate_market_weight_pct: number | null;
  distress_value_pct: number | null;
}

const clients = ref<ClientRecord[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showForm = ref(false);
const submitting = ref(false);
const expandedSettingsId = ref<string | null>(null);
const settingsError = ref<string | null>(null);
const savingSettings = ref(false);

const CLIENT_TYPES = [
  'commercial_bank', 'development_bank', 'finance_company', 'microfinance',
  'cooperative', 'insurance', 'government_agency', 'corporate', 'individual', 'other',
];

const form = ref({
  name_en: '', name_ne: '', client_type: 'commercial_bank', telephone: '', email: '',
  land_rate_government_weight_pct: null as number | null,
  land_rate_market_weight_pct: null as number | null,
  distress_value_pct: null as number | null,
});

// Separate editable copy per client, keyed by id -- so opening one row's
// settings panel doesn't clobber another's in-progress edit.
const settingsForm = ref<Record<string, { land_rate_government_weight_pct: number | null; land_rate_market_weight_pct: number | null; distress_value_pct: number | null }>>({});

async function load() {
  loading.value = true;
  try {
    const result = await apiFetch<{ data: ClientRecord[] }>('/api/v1/clients?per_page=50');
    clients.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load clients.';
  } finally {
    loading.value = false;
  }
}

async function submit() {
  submitting.value = true;
  error.value = null;

  try {
    await apiFetch('/api/v1/clients', { method: 'POST', body: JSON.stringify(form.value) });
    form.value = {
      name_en: '', name_ne: '', client_type: 'commercial_bank', telephone: '', email: '',
      land_rate_government_weight_pct: null, land_rate_market_weight_pct: null, distress_value_pct: null,
    };
    showForm.value = false;
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to create client.';
  } finally {
    submitting.value = false;
  }
}

function toggleSettings(client: ClientRecord) {
  if (expandedSettingsId.value === client.id) {
    expandedSettingsId.value = null;
    return;
  }

  settingsForm.value[client.id] = {
    land_rate_government_weight_pct: client.land_rate_government_weight_pct,
    land_rate_market_weight_pct: client.land_rate_market_weight_pct,
    distress_value_pct: client.distress_value_pct,
  };
  expandedSettingsId.value = client.id;
  settingsError.value = null;
}

// Keeps government/market in sync automatically -- entering one fills
// the other so a valuer only ever has to type the number their bank's
// guideline actually states, not do the subtraction themselves.
function syncGovernmentWeight(clientId: string, value: number | null) {
  const s = settingsForm.value[clientId];
  s.land_rate_government_weight_pct = value;
  if (value !== null) s.land_rate_market_weight_pct = Math.round((100 - value) * 100) / 100;
}
function syncMarketWeight(clientId: string, value: number | null) {
  const s = settingsForm.value[clientId];
  s.land_rate_market_weight_pct = value;
  if (value !== null) s.land_rate_government_weight_pct = Math.round((100 - value) * 100) / 100;
}

async function saveSettings(clientId: string) {
  savingSettings.value = true;
  settingsError.value = null;

  try {
    await apiFetch(`/api/v1/clients/${clientId}`, {
      method: 'PUT',
      body: JSON.stringify(settingsForm.value[clientId]),
    });
    expandedSettingsId.value = null;
    await load();
  } catch (e) {
    settingsError.value = e instanceof Error ? e.message : 'Failed to save settings.';
  } finally {
    savingSettings.value = false;
  }
}

function conventionSummary(c: ClientRecord): string {
  if (c.land_rate_government_weight_pct === null || c.land_rate_market_weight_pct === null) {
    return 'Not set -- uses system default (30% Gov / 70% Market)';
  }
  return `${c.land_rate_government_weight_pct}% Gov / ${c.land_rate_market_weight_pct}% Market, Distress ${c.distress_value_pct ?? 80}% of FMV`;
}

onMounted(load);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-lg font-semibold">Clients</h1>
      <button class="px-3 py-1.5 text-sm bg-brand-600 text-white rounded" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : '+ New Client' }}
      </button>
    </div>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>

    <div v-if="showForm" class="bg-white border rounded p-4 mb-4">
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <label class="block text-xs text-gray-500">Name (English)</label>
          <input v-model="form.name_en" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Name (Nepali)</label>
          <input v-model="form.name_ne" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">Type</label>
          <select v-model="form.client_type" class="border rounded px-2 py-1 w-full">
            <option v-for="t in CLIENT_TYPES" :key="t" :value="t">{{ t.replace(/_/g, ' ') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">Telephone</label>
          <input v-model="form.telephone" type="text" class="border rounded px-2 py-1 w-full" />
        </div>
        <div class="col-span-2">
          <label class="block text-xs text-gray-500">Email</label>
          <input v-model="form.email" type="email" class="border rounded px-2 py-1 w-full" />
        </div>
      </div>

      <div class="mt-4 pt-3 border-t">
        <p class="text-xs font-medium text-gray-600 mb-1">Land Valuation Convention (optional -- set per this bank's own guideline)</p>
        <p class="text-xs text-gray-400 mb-2">Different banks use genuinely different splits (30/70, 70/30, 20/80 have all been seen in real reports). Leave blank to use the system default.</p>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs text-gray-500">Government Weight %</label>
            <input v-model.number="form.land_rate_government_weight_pct" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Market Weight %</label>
            <input v-model.number="form.land_rate_market_weight_pct" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Distress Value % of FMV</label>
            <input v-model.number="form.distress_value_pct" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
          </div>
        </div>
      </div>

      <button
        :disabled="submitting || !form.name_en"
        class="mt-3 px-4 py-1.5 text-sm bg-brand-600 text-white rounded disabled:opacity-40"
        @click="submit"
      >{{ submitting ? 'Creating…' : 'Create Client' }}</button>
    </div>

    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>
    <div v-else-if="clients.length === 0" class="text-gray-500 text-sm py-8 text-center border rounded">No clients yet.</div>
    <table v-else class="w-full text-sm bg-white border rounded overflow-hidden">
      <thead class="bg-gray-50 text-gray-600 text-left">
        <tr>
          <th class="px-4 py-2 font-medium">Name</th>
          <th class="px-4 py-2 font-medium">Type</th>
          <th class="px-4 py-2 font-medium">Telephone</th>
          <th class="px-4 py-2 font-medium">Valuation Convention</th>
          <th class="px-4 py-2 font-medium">Status</th>
          <th class="px-4 py-2 font-medium"></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="c in clients" :key="c.id">
          <tr class="border-t">
            <td class="px-4 py-2 font-medium">{{ c.name_en }}</td>
            <td class="px-4 py-2 capitalize">{{ c.client_type.replace(/_/g, ' ') }}</td>
            <td class="px-4 py-2">{{ c.telephone ?? '—' }}</td>
            <td class="px-4 py-2 text-xs text-gray-600">{{ conventionSummary(c) }}</td>
            <td class="px-4 py-2">
              <span :class="c.is_active ? 'text-emerald-600' : 'text-gray-400'">{{ c.is_active ? 'Active' : 'Inactive' }}</span>
            </td>
            <td class="px-4 py-2">
              <button class="text-xs text-brand-600" @click="toggleSettings(c)">
                {{ expandedSettingsId === c.id ? 'Cancel' : 'Edit Convention' }}
              </button>
            </td>
          </tr>
          <tr v-if="expandedSettingsId === c.id" class="border-t bg-gray-50">
            <td colspan="6" class="px-4 py-3">
              <div v-if="settingsError" class="bg-red-50 text-red-700 text-xs rounded p-2 mb-2">{{ settingsError }}</div>
              <div class="grid grid-cols-3 gap-3 text-sm mb-2">
                <div>
                  <label class="block text-xs text-gray-500">Government Weight %</label>
                  <input
                    :value="settingsForm[c.id]?.land_rate_government_weight_pct"
                    @input="syncGovernmentWeight(c.id, ($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value))"
                    type="number" min="0" max="100" class="border rounded px-2 py-1 w-full"
                  />
                </div>
                <div>
                  <label class="block text-xs text-gray-500">Market Weight % (auto-fills to 100 - Gov.)</label>
                  <input
                    :value="settingsForm[c.id]?.land_rate_market_weight_pct"
                    @input="syncMarketWeight(c.id, ($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value))"
                    type="number" min="0" max="100" class="border rounded px-2 py-1 w-full"
                  />
                </div>
                <div>
                  <label class="block text-xs text-gray-500">Distress Value % of FMV</label>
                  <input v-model.number="settingsForm[c.id].distress_value_pct" type="number" min="0" max="100" class="border rounded px-2 py-1 w-full" />
                </div>
              </div>
              <p class="text-xs text-gray-400 mb-2">This bank's specific guideline -- used automatically for every Weighted Land Rate calculation on assignments for this client, unless a valuer explicitly overrides it for one calculation.</p>
              <button
                :disabled="savingSettings"
                class="px-3 py-1 text-sm bg-brand-600 text-white rounded disabled:opacity-40"
                @click="saveSettings(c.id)"
              >{{ savingSettings ? 'Saving…' : 'Save Convention' }}</button>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
