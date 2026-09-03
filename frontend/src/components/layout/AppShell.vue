<script setup>
import { ref, computed } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu'
import ChangePasswordDialog from '@/components/account/ChangePasswordDialog.vue'
import DeleteAccountDialog from '@/components/account/DeleteAccountDialog.vue'
import {
  CalendarDays,
  Users,
  Stethoscope,
  BarChart3,
  LogOut,
  Menu,
  X,
  UserCircle,
  KeyRound,
  ShieldCheck,
  Trash2,
  ChevronDown,
} from '@lucide/vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const mobileOpen = ref(false)
const changePasswordOpen = ref(false)
const deleteAccountOpen = ref(false)

const ROLE_LABELS = { admin: 'Administrador', staff: 'Equipe', patient: 'Paciente' }

const nav = computed(() => [
  { name: 'appointments.kanban', label: 'Consultas', icon: CalendarDays, match: 'appointments' },
  { name: 'patients', label: 'Pacientes', icon: Users, match: 'patients' },
  { name: 'professionals', label: 'Profissionais', icon: Stethoscope, match: 'professionals' },
  { name: 'reports', label: 'Relatórios', icon: BarChart3, match: 'reports' },
  ...(auth.isAdmin ? [{ name: 'users', label: 'Usuários', icon: ShieldCheck, match: 'users' }] : []),
])

function isActive(item) {
  return route.path.includes(item.match)
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

async function handleAccountDeleted() {
  auth.forceLogout()
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
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="ghost" size="sm" class="gap-2">
                <UserCircle class="size-5" />
                <span class="hidden flex-col items-start leading-tight sm:flex">
                  <span class="text-sm font-medium">{{ auth.user?.name }}</span>
                  <span class="text-xs text-muted-foreground">{{ ROLE_LABELS[auth.user?.role] ?? auth.user?.role }}</span>
                </span>
                <ChevronDown class="size-3.5 text-muted-foreground" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
              <DropdownMenuLabel>
                <p class="font-medium">{{ auth.user?.name }}</p>
                <p class="text-xs font-normal text-muted-foreground">{{ auth.user?.email }}</p>
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem v-if="auth.isAdmin" @click="router.push({ name: 'users' })">
                <ShieldCheck class="size-4" />
                Gerenciar usuários
              </DropdownMenuItem>
              <DropdownMenuItem @click="changePasswordOpen = true">
                <KeyRound class="size-4" />
                Trocar senha
              </DropdownMenuItem>
              <DropdownMenuItem class="text-destructive" @click="deleteAccountOpen = true">
                <Trash2 class="size-4" />
                Excluir minha conta
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem @click="handleLogout">
                <LogOut class="size-4" />
                Sair
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>

    <ChangePasswordDialog v-model:open="changePasswordOpen" />
    <DeleteAccountDialog v-model:open="deleteAccountOpen" @deleted="handleAccountDeleted" />

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
