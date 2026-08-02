<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
  latitude: number | null;
  longitude: number | null;
  boundaryPoints?: Array<{ lat: number; lng: number }> | null;
  propertyLabel?: string;
}>();

const mapContainer = ref<HTMLDivElement | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
let mapInstance: any = null;

// Leaflet loaded from CDN rather than an npm dependency -- keeps this
// component fully self-contained without touching package.json/
// package-lock.json, the same approach already used for the Swagger UI
// API docs page. Safe to call repeatedly; only injects once.
function loadLeaflet(): Promise<any> {
  return new Promise((resolve, reject) => {
    const w = window as any;
    if (w.L) {
      resolve(w.L);
      return;
    }

    const existingScript = document.querySelector('script[data-leaflet]');
    if (existingScript) {
      existingScript.addEventListener('load', () => resolve(w.L));
      existingScript.addEventListener('error', reject);
      return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css';
    document.head.appendChild(link);

    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js';
    script.setAttribute('data-leaflet', 'true');
    script.onload = () => resolve(w.L);
    script.onerror = reject;
    document.head.appendChild(script);
  });
}

async function initMap() {
  if (!mapContainer.value) return;

  loading.value = true;
  error.value = null;

  try {
    const L = await loadLeaflet();

    if (mapInstance) {
      mapInstance.remove();
      mapInstance = null;
    }

    const hasPoint = props.latitude !== null && props.longitude !== null;
    const hasBoundary = props.boundaryPoints && props.boundaryPoints.length >= 3;

    if (!hasPoint && !hasBoundary) {
      loading.value = false;
      return; // nothing to show -- the template renders a "no location recorded" message instead
    }

    const center: [number, number] = hasPoint
      ? [props.latitude as number, props.longitude as number]
      : [props.boundaryPoints![0].lat, props.boundaryPoints![0].lng];

    mapInstance = L.map(mapContainer.value).setView(center, 17);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(mapInstance);

    if (hasPoint) {
      L.marker([props.latitude as number, props.longitude as number])
        .addTo(mapInstance)
        .bindPopup(props.propertyLabel ?? 'Property location');
    }

    if (hasBoundary) {
      const latLngs = props.boundaryPoints!.map((p) => [p.lat, p.lng] as [number, number]);
      const polygon = L.polygon(latLngs, { color: '#185943', fillOpacity: 0.15 }).addTo(mapInstance);
      if (!hasPoint) mapInstance.fitBounds(polygon.getBounds());
    }
  } catch (e) {
    error.value = 'Failed to load the map (Leaflet could not be loaded from the CDN).';
  } finally {
    loading.value = false;
  }
}

watch(() => [props.latitude, props.longitude, props.boundaryPoints], initMap, { deep: true });
onMounted(initMap);
onUnmounted(() => {
  if (mapInstance) mapInstance.remove();
});
</script>

<template>
  <div>
    <div v-if="error" class="bg-red-50 text-red-700 text-xs rounded p-2 mb-2">{{ error }}</div>
    <div
      v-if="latitude === null && longitude === null && (!boundaryPoints || boundaryPoints.length < 3)"
      class="text-xs text-gray-400 border rounded p-4 text-center bg-gray-50"
    >
      No GPS coordinates or boundary recorded for this property yet.
    </div>
    <div v-else ref="mapContainer" style="height: 320px; border-radius: 6px; border: 1px solid #ddd;"></div>
  </div>
</template>
