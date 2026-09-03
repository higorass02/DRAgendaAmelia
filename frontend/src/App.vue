<script setup>
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppShell from '@/components/layout/AppShell.vue'
import { Toaster } from '@/components/ui/sonner'
</script>

<template>
  <Toaster position="top-right" rich-colors />

  <AppShell v-if="useAuthStore().isAuthenticated">
    <RouterView />
  </AppShell>
  <RouterView v-else />
</template>
