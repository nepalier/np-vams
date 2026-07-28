<script setup lang="ts">
defineProps<{ status: string }>();

const colorMap: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  submitted: 'bg-blue-100 text-blue-700',
  cancelled: 'bg-red-100 text-red-700',
  correction_requested: 'bg-amber-100 text-amber-700',
  approved: 'bg-emerald-100 text-emerald-700',
  report_issued: 'bg-emerald-100 text-emerald-700',
  awaiting_approval: 'bg-amber-100 text-amber-700',
  under_technical_review: 'bg-amber-100 text-amber-700',
};

function colorFor(status: string): string {
  return colorMap[status] ?? 'bg-brand-50 text-brand-700';
}

function labelFor(status: string): string {
  return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
</script>

<template>
  <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-medium', colorFor(status)]">
    {{ labelFor(status) }}
  </span>
</template>
