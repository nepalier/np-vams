<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';

const props = defineProps<{ id: string }>();

interface Parcel { id: string; kitta_number: string; area_considered_sqm: number | null; }
interface Floor { id: string; floor_name: string; floor_number: number; covered_area_sqm: number | null; }
interface BuildingRecord { id: string; building_name: string | null; number_of_floors: number; structural_system: string | null; floors: Floor[]; }
interface PropertyDetail {
  id: string; property_code: string; property_name: string | null; address: string | null;
  district_name: string | null; latitude: number | null; longitude: number | null;
}

const property = ref<PropertyDetail | null>(null);
const parcels = ref<Parcel[]>([]);
const buildings = ref<BuildingRecord[]>([]);
const areaUnits = ref<Array<{ id: number; name_en: string }>>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const showParcelForm = ref(false);
const parcelForm = ref({ kitta_number: '', area_lalpurja: null as number | null, area_lalpurja_unit_id: '', area_considered_sqm: null as number | null });
const submittingParcel = ref(false);

const showBuildingForm = ref(false);
const buildingForm = ref({ building_name: '', number_of_floors: 1, structural_system: 'rcc_frame', current_use: '' });
const submittingBuilding = ref(false);

async function loadAll() {
  loading.value = true;
  try {
    const [propRes, parcelsRes, buildingsRes, unitsRes] = await Promise.all([
      apiFetch<{ data: PropertyDetail }>(`/api/v1/properties/${props.id}`),
      apiFetch<{ data: Parcel[] }>(`/api/v1/properties/${props.id}/parcels`),
      apiFetch<{ data: BuildingRecord[] }>(`/api/v1/properties/${props.id}/buildings`),
      apiFetch<{ data: Array<{ id: number; name_en: string }> }>('/api/v1/master-data/area-units'),
    ]);
    property.value = propRes.data;
    parcels.value = parcelsRes.data;
    buildings.value = buildingsRes.data;
    areaUnits.value = unitsRes.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load property.';
  } finally {
    loading.value = false;
  }
}

async function submitParcel() {
  submittingParcel.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/properties/${props.id}/parcels`, { method: 'POST', body: JSON.stringify(parcelForm.value) });
    parcelForm.value = { kitta_number: '', area_lalpurja: null, area_lalpurja_unit_id: '', area_considered_sqm: null };
    showParcelForm.value = false;
    await loadAll();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to create parcel.';
  } finally {
    submittingParcel.value = false;
  }
}

async function submitBuilding() {
  submittingBuilding.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/properties/${props.id}/buildings`, { method: 'POST', body: JSON.stringify(buildingForm.value) });
    buildingForm.value = { building_name: '', number_of_floors: 1, structural_system: 'rcc_frame', current_use: '' };
    showBuildingForm.value = false;
    await loadAll();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to create building.';
  } finally {
    submittingBuilding.value = false;
  }
}

onMounted(loadAll);
</script>

<template>
  <div>
    <a href="/properties" class="text-sm text-brand-600 hover:underline">← Back to properties</a>

    <div v-if="loading" class="text-gray-500 text-sm mt-4">Loading…</div>
    <div v-else-if="error && !property" class="bg-red-50 text-red-700 text-sm rounded p-3 mt-4">{{ error }}</div>

    <template v-else-if="property">
      <h1 class="text-xl font-semibold mt-4 mb-1">{{ property.property_name ?? property.property_code }}</h1>
      <p class="text-gray-500 text-sm mb-6">{{ property.property_code }} · {{ property.address ?? 'No address recorded' }} · {{ property.district_name ?? '—' }}</p>

      <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>

      <!-- Parcels -->
      <div class="bg-white border rounded p-4 mb-6">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-semibold text-gray-700">Land Parcels</h2>
          <button class="text-sm text-brand-600" @click="showParcelForm = !showParcelForm">{{ showParcelForm ? 'Cancel' : '+ Add Parcel' }}</button>
        </div>

        <div v-if="showParcelForm" class="border rounded p-3 mb-3 grid grid-cols-2 gap-2 text-sm">
          <div>
            <label class="block text-xs text-gray-500">Kitta Number</label>
            <input v-model="parcelForm.kitta_number" type="text" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Area Considered (sqm, optional if using unit conversion below)</label>
            <input v-model.number="parcelForm.area_considered_sqm" type="number" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Lalpurja Area</label>
            <input v-model.number="parcelForm.area_lalpurja" type="number" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Unit</label>
            <select v-model="parcelForm.area_lalpurja_unit_id" class="border rounded px-2 py-1 w-full">
              <option value="">—</option>
              <option v-for="u in areaUnits" :key="u.id" :value="u.id">{{ u.name_en }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <button :disabled="submittingParcel || !parcelForm.kitta_number" class="px-3 py-1.5 bg-brand-600 text-white rounded disabled:opacity-40" @click="submitParcel">
              {{ submittingParcel ? 'Saving…' : 'Save Parcel' }}
            </button>
          </div>
        </div>

        <p v-if="parcels.length === 0" class="text-sm text-gray-400">No parcels recorded yet.</p>
        <table v-else class="w-full text-sm">
          <thead class="text-gray-500 text-left"><tr><th class="py-1">Kitta #</th><th class="py-1">Area (sqm)</th></tr></thead>
          <tbody>
            <tr v-for="p in parcels" :key="p.id" class="border-t"><td class="py-1">{{ p.kitta_number }}</td><td class="py-1">{{ p.area_considered_sqm ?? '—' }}</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Buildings -->
      <div class="bg-white border rounded p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-semibold text-gray-700">Buildings</h2>
          <button class="text-sm text-brand-600" @click="showBuildingForm = !showBuildingForm">{{ showBuildingForm ? 'Cancel' : '+ Add Building' }}</button>
        </div>

        <div v-if="showBuildingForm" class="border rounded p-3 mb-3 grid grid-cols-2 gap-2 text-sm">
          <div>
            <label class="block text-xs text-gray-500">Building Name</label>
            <input v-model="buildingForm.building_name" type="text" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Number of Floors</label>
            <input v-model.number="buildingForm.number_of_floors" type="number" min="1" class="border rounded px-2 py-1 w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Structural System</label>
            <select v-model="buildingForm.structural_system" class="border rounded px-2 py-1 w-full">
              <option value="rcc_frame">RCC Frame</option>
              <option value="load_bearing_masonry">Load Bearing Masonry</option>
              <option value="steel">Steel</option>
              <option value="timber">Timber</option>
              <option value="mixed">Mixed</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500">Current Use</label>
            <input v-model="buildingForm.current_use" type="text" class="border rounded px-2 py-1 w-full" />
          </div>
          <div class="col-span-2">
            <button :disabled="submittingBuilding" class="px-3 py-1.5 bg-brand-600 text-white rounded disabled:opacity-40" @click="submitBuilding">
              {{ submittingBuilding ? 'Saving…' : 'Save Building' }}
            </button>
          </div>
        </div>

        <p v-if="buildings.length === 0" class="text-sm text-gray-400">No buildings recorded yet.</p>
        <div v-else class="space-y-2">
          <div v-for="b in buildings" :key="b.id" class="border-t pt-2 text-sm">
            <span class="font-medium">{{ b.building_name ?? 'Unnamed building' }}</span>
            <span class="text-gray-500"> · {{ b.number_of_floors }} floor(s) · {{ b.structural_system ?? '—' }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
