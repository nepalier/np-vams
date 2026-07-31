<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import StatusBadge from '../../Components/ui/StatusBadge.vue';

defineOptions({ layout: PortalLayout });

interface PortalInvoice {
  id: string; invoice_number: string; total_amount: string; outstanding_amount: string; status: string; issue_date: string | null;
}

const invoices = ref<PortalInvoice[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

function formatCurrency(value: string): string {
  return new Intl.NumberFormat('en-NP', { style: 'currency', currency: 'NPR' }).format(Number(value));
}

async function load() {
  try {
    const result = await apiFetch<{ data: PortalInvoice[] }>('/api/v1/portal/invoices?per_page=50');
    invoices.value = result.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load invoices.';
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div>
    <h1 class="text-lg font-semibold mb-4">Invoices</h1>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>
    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>
    <div v-else-if="invoices.length === 0" class="text-gray-500 text-sm py-8 text-center border rounded bg-white">
      No invoices yet.
    </div>
    <table v-else class="w-full text-sm bg-white border rounded overflow-hidden">
      <thead class="bg-gray-50 text-gray-600 text-left">
        <tr>
          <th class="px-4 py-2 font-medium">Invoice #</th>
          <th class="px-4 py-2 font-medium">Date</th>
          <th class="px-4 py-2 font-medium">Total</th>
          <th class="px-4 py-2 font-medium">Outstanding</th>
          <th class="px-4 py-2 font-medium">Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="inv in invoices" :key="inv.id" class="border-t">
          <td class="px-4 py-2 font-medium">{{ inv.invoice_number }}</td>
          <td class="px-4 py-2">{{ inv.issue_date ?? '—' }}</td>
          <td class="px-4 py-2">{{ formatCurrency(inv.total_amount) }}</td>
          <td class="px-4 py-2">{{ formatCurrency(inv.outstanding_amount) }}</td>
          <td class="px-4 py-2"><StatusBadge :status="inv.status" /></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
