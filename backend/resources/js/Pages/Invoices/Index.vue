<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../Composables/useApi';
import StatusBadge from '../../Components/ui/StatusBadge.vue';

interface InvoiceRow {
  id: string; invoice_number: string; client_name: string | null;
  issue_date: string | null; due_date: string | null;
  total_amount: string; outstanding_amount: string; status: string;
}
interface ClientOption { id: string; name_en: string; }

const invoices = ref<InvoiceRow[]>([]);
const clients = ref<ClientOption[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showForm = ref(false);
const submitting = ref(false);
const payingInvoiceId = ref<string | null>(null);
const paymentAmount = ref<number | null>(null);
const paymentMethod = ref('bank_transfer');

const form = ref({
  client_id: '',
  vat_pct: 13,
  tds_pct: 1.5,
  due_date: '',
  items: [{ description: '', quantity: 1, unit_rate: null as number | null }],
});

function addItem() {
  form.value.items.push({ description: '', quantity: 1, unit_rate: null });
}
function removeItem(i: number) {
  form.value.items.splice(i, 1);
}

async function loadAll() {
  loading.value = true;
  try {
    const [invoicesRes, clientsRes] = await Promise.all([
      apiFetch<{ data: InvoiceRow[] }>('/api/v1/invoices?per_page=50'),
      apiFetch<{ data: ClientOption[] }>('/api/v1/clients?per_page=100'),
    ]);
    invoices.value = invoicesRes.data;
    clients.value = clientsRes.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load invoices.';
  } finally {
    loading.value = false;
  }
}

async function submit() {
  submitting.value = true;
  error.value = null;
  try {
    await apiFetch('/api/v1/invoices', { method: 'POST', body: JSON.stringify(form.value) });
    form.value = { client_id: '', vat_pct: 13, tds_pct: 1.5, due_date: '', items: [{ description: '', quantity: 1, unit_rate: null }] };
    showForm.value = false;
    await loadAll();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to create invoice.';
  } finally {
    submitting.value = false;
  }
}

function startPayment(invoiceId: string) {
  payingInvoiceId.value = invoiceId;
  paymentAmount.value = null;
}

async function submitPayment(invoiceId: string) {
  if (!paymentAmount.value) return;
  submitting.value = true;
  error.value = null;
  try {
    await apiFetch(`/api/v1/invoices/${invoiceId}/payments`, {
      method: 'POST',
      body: JSON.stringify({ amount: paymentAmount.value, payment_method: paymentMethod.value }),
    });
    payingInvoiceId.value = null;
    await loadAll();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to record payment.';
  } finally {
    submitting.value = false;
  }
}

function formatCurrency(value: string | number): string {
  return new Intl.NumberFormat('en-NP', { style: 'currency', currency: 'NPR' }).format(Number(value));
}

onMounted(loadAll);
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-lg font-semibold">Invoices</h1>
      <button class="px-3 py-1.5 text-sm bg-brand-600 text-white rounded" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : '+ New Invoice' }}
      </button>
    </div>

    <div v-if="error" class="bg-red-50 text-red-700 text-sm rounded p-3 mb-4">{{ error }}</div>

    <div v-if="showForm" class="bg-white border rounded p-4 mb-4 text-sm">
      <div class="grid grid-cols-3 gap-3 mb-3">
        <div>
          <label class="block text-xs text-gray-500">Client</label>
          <select v-model="form.client_id" class="border rounded px-2 py-1 w-full">
            <option value="" disabled>Select a client</option>
            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name_en }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500">VAT %</label>
          <input v-model.number="form.vat_pct" type="number" class="border rounded px-2 py-1 w-full" />
        </div>
        <div>
          <label class="block text-xs text-gray-500">TDS %</label>
          <input v-model.number="form.tds_pct" type="number" class="border rounded px-2 py-1 w-full" />
        </div>
      </div>

      <label class="block text-xs text-gray-500 mb-1">Line Items</label>
      <div v-for="(item, i) in form.items" :key="i" class="flex gap-2 mb-2">
        <input v-model="item.description" type="text" placeholder="Description" class="border rounded px-2 py-1 flex-1" />
        <input v-model.number="item.quantity" type="number" placeholder="Qty" class="border rounded px-2 py-1 w-20" />
        <input v-model.number="item.unit_rate" type="number" placeholder="Rate" class="border rounded px-2 py-1 w-32" />
        <button v-if="form.items.length > 1" class="text-red-600 text-xs" @click="removeItem(i)">Remove</button>
      </div>
      <button class="text-xs text-brand-600 mb-3" @click="addItem">+ Add line item</button>

      <div>
        <button
          :disabled="submitting || !form.client_id"
          class="px-4 py-1.5 bg-brand-600 text-white rounded disabled:opacity-40"
          @click="submit"
        >{{ submitting ? 'Creating…' : 'Create Invoice' }}</button>
      </div>
    </div>

    <div v-if="loading" class="text-gray-500 text-sm">Loading…</div>
    <div v-else-if="invoices.length === 0" class="text-gray-500 text-sm py-8 text-center border rounded">No invoices yet.</div>
    <table v-else class="w-full text-sm bg-white border rounded overflow-hidden">
      <thead class="bg-gray-50 text-gray-600 text-left">
        <tr>
          <th class="px-4 py-2 font-medium">Invoice #</th>
          <th class="px-4 py-2 font-medium">Client</th>
          <th class="px-4 py-2 font-medium">Total</th>
          <th class="px-4 py-2 font-medium">Outstanding</th>
          <th class="px-4 py-2 font-medium">Status</th>
          <th class="px-4 py-2 font-medium">Action</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="inv in invoices" :key="inv.id" class="border-t">
          <td class="px-4 py-2 font-medium">{{ inv.invoice_number }}</td>
          <td class="px-4 py-2">{{ inv.client_name ?? '—' }}</td>
          <td class="px-4 py-2">{{ formatCurrency(inv.total_amount) }}</td>
          <td class="px-4 py-2">{{ formatCurrency(inv.outstanding_amount) }}</td>
          <td class="px-4 py-2"><StatusBadge :status="inv.status" /></td>
          <td class="px-4 py-2">
            <button
              v-if="Number(inv.outstanding_amount) > 0 && payingInvoiceId !== inv.id"
              class="text-xs text-brand-600"
              @click="startPayment(inv.id)"
            >Record Payment</button>
            <div v-if="payingInvoiceId === inv.id" class="flex gap-1 items-center">
              <input v-model.number="paymentAmount" type="number" placeholder="Amount" class="border rounded px-1 py-0.5 w-24 text-xs" />
              <select v-model="paymentMethod" class="border rounded px-1 py-0.5 text-xs">
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="online">Online</option>
              </select>
              <button class="text-xs text-brand-600" :disabled="submitting" @click="submitPayment(inv.id)">Save</button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
