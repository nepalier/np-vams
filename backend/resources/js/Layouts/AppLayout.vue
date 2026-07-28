<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { AuthUser } from '../types';

const page = usePage<{ auth?: { user: AuthUser } }>();
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-brand-700 text-white px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-6">
        <a href="/" class="font-semibold">NP-VAMS</a>
        <nav class="flex items-center gap-4 text-sm text-brand-50">
          <a href="/" class="hover:text-white">Dashboard</a>
          <a href="/assignments" class="hover:text-white">Assignments</a>
        </nav>
      </div>
      <div v-if="page.props.auth?.user" class="text-sm">
        {{ page.props.auth.user.name }}
      </div>
    </header>
    <main class="p-6">
      <slot />
    </main>
  </div>
</template>
