<script setup>
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppShell from '@/components/layout/AppShell.vue'
import { Toaster } from '@/components/ui/sonner'

const auth = useAuthStore()

// Token persiste no localStorage entre recarregamentos, mas o "user" (com o
// role, usado pra decidir o que mostrar no menu) é só de memória — sem
// isso, dar F5 deixa o nome/role em branco até o próximo login.
onMounted(() => {
  if (auth.isAuthenticated && !auth.user) {
    auth.fetchUser()
  }
})
</script>

<template>
  <Toaster
    position="top-center"
    rich-colors
    close-button
    :duration="6000"
    :toast-options="{ classes: { toast: 'font-medium', title: 'text-sm', description: 'text-sm' } }"
  />

  <AppShell v-if="useAuthStore().isAuthenticated">
    <RouterView />
  </AppShell>
  <RouterView v-else />
</template>
