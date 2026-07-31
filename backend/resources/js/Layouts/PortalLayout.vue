<script setup lang="ts">
function logout() {
  localStorage.removeItem('npvams_token');
  window.location.href = '/login';
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-brand-800 text-white px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-6">
        <span class="font-semibold">NP-VAMS Client Portal</span>
        <nav class="flex items-center gap-4 text-sm text-brand-100">
          <a href="/portal" class="hover:text-white">Dashboard</a>
          <a href="/portal/assignments" class="hover:text-white">My Cases</a>
          <a href="/portal/invoices" class="hover:text-white">Invoices</a>
        </nav>
      </div>
      <button class="text-sm text-brand-100 hover:text-white" @click="logout">Log Out</button>
    </header>
    <main class="p-6 max-w-5xl mx-auto">
      <slot />
    </main>
  </div>
</template>
