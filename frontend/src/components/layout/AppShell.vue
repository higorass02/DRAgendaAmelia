<script setup>
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Button } from '@/components/ui/button'
import { CalendarDays, Users, Stethoscope, BarChart3, LogOut, Menu, X } from '@lucide/vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const mobileOpen = ref(false)

const nav = [
  { name: 'appointments.kanban', label: 'Consultas', icon: CalendarDays, match: 'appointments' },
  { name: 'patients', label: 'Pacientes', icon: Users, match: 'patients' },
  { name: 'professionals', label: 'Profissionais', icon: Stethoscope, match: 'professionals' },
  { name: 'reports', label: 'Relatórios', icon: BarChart3, match: 'reports' },
]

function isActive(item) {
  return route.path.includes(item.match)
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-background">
    <header class="sticky top-0 z-40 border-b border-border bg-background">
      <div class="flex h-14 items-center gap-3 px-4">
        <button
          class="md:hidden text-muted-foreground"
          @click="mobileOpen = !mobileOpen"
          aria-label="Abrir menu"
        >
          <Menu v-if="!mobileOpen" class="size-5" />
          <X v-else class="size-5" />
        </button>
        <span class="font-semibold">Agenda</span>
        <div class="ml-auto flex items-center gap-3">
          <span class="hidden sm:inline text-sm text-muted-foreground">{{ auth.user?.name }}</span>
          <Button variant="ghost" size="sm" @click="handleLogout">
            <LogOut class="size-4" />
            <span class="hidden sm:inline">Sair</span>
          </Button>
        </div>
      </div>
    </header>

    <div class="flex">
      <aside
        class="fixed inset-y-14 left-0 z-30 w-56 -translate-x-full border-r border-border bg-background transition-transform md:static md:inset-auto md:translate-x-0"
        :class="{ 'translate-x-0': mobileOpen }"
      >
        <nav class="flex flex-col gap-1 p-3">
          <RouterLink
            v-for="item in nav"
            :key="item.name"
            :to="{ name: item.name }"
            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors"
            :class="
              isActive(item)
                ? 'bg-accent text-accent-foreground font-medium'
                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
            "
            @click="mobileOpen = false"
          >
            <component :is="item.icon" class="size-4" />
            {{ item.label }}
          </RouterLink>
        </nav>
      </aside>

      <div
        v-if="mobileOpen"
        class="fixed inset-0 z-20 bg-black/30 md:hidden"
        @click="mobileOpen = false"
      />

      <main class="min-w-0 flex-1 p-4 md:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
