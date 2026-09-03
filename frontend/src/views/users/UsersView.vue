<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import api from '@/lib/api'
import { toast } from 'vue-sonner'
import { useAuthStore } from '@/stores/auth'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table'
import Pagination from '@/components/shared/Pagination.vue'
import SortableTableHead from '@/components/shared/SortableTableHead.vue'
import FilterDrawer from '@/components/shared/FilterDrawer.vue'
import UserFormDialog from '@/components/users/UserFormDialog.vue'
import { useSort } from '@/composables/useSort'
import { Plus, Pencil, Trash2 } from '@lucide/vue'

const auth = useAuthStore()
const { sort, direction, toggleSort } = useSort('name')

const users = ref([])
const meta = ref(null)
const loading = ref(true)
const dialogOpen = ref(false)
const filtersOpen = ref(false)
const editingUser = ref(null)
const page = ref(1)

const filters = reactive({ name: '', email: '', role: 'all' })

const activeFilterCount = computed(
  () => Object.entries(filters).filter(([key, value]) => value && !(key === 'role' && value === 'all')).length,
)

const ROLE_LABELS = { admin: 'Administrador', staff: 'Equipe', patient: 'Paciente' }
const ROLE_BADGE_VARIANT = { admin: 'default', staff: 'secondary', patient: 'outline' }

async function load() {
  loading.value = true
  try {
    const params = { page: page.value, sort: sort.value, direction: direction.value }
    if (filters.name) params.name = filters.name
    if (filters.email) params.email = filters.email
    if (filters.role !== 'all') params.role = filters.role

    const { data } = await api.get('/users', { params })
    users.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

const debouncedSearch = useDebounceFn(() => {
  page.value = 1
  load()
}, 400)

watch(filters, debouncedSearch)
watch(page, load)
watch([sort, direction], () => {
  page.value = 1
  load()
})

function clearFilters() {
  filters.name = ''
  filters.email = ''
  filters.role = 'all'
}

function openCreate() {
  editingUser.value = null
  dialogOpen.value = true
}

function openEdit(user) {
  editingUser.value = user
  dialogOpen.value = true
}

async function remove(user) {
  if (user.id === auth.user?.id) {
    toast.error('Use "Excluir minha conta" no seu perfil para excluir a própria conta.')
    return
  }
  if (!confirm(`Excluir o usuário "${user.name}"? Essa ação não pode ser desfeita.`)) return

  try {
    await api.delete(`/users/${user.id}`)
    toast.success('Usuário excluído.')
    load()
  } catch {
    // toast de erro já vem do interceptor
  }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Usuários</h1>
      <div class="flex items-center gap-2">
        <FilterDrawer
          v-model:open="filtersOpen"
          :active-count="activeFilterCount"
          title="Filtrar usuários"
          @clear="clearFilters"
        >
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">Nome</Label>
            <Input v-model="filters.name" placeholder="Buscar por nome" />
          </div>
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">E-mail</Label>
            <Input v-model="filters.email" placeholder="Buscar por e-mail" />
          </div>
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">Papel</Label>
            <Select v-model="filters.role">
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                <SelectItem value="admin">Administrador</SelectItem>
                <SelectItem value="staff">Equipe</SelectItem>
                <SelectItem value="patient">Paciente</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </FilterDrawer>
        <Button @click="openCreate">
          <Plus class="size-4" />
          Novo usuário
        </Button>
      </div>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando...</p>
    <p v-else-if="users.length === 0" class="text-sm text-muted-foreground">Nenhum usuário encontrado.</p>

    <div v-else class="overflow-x-auto rounded-md border border-border">
      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Nome" sort-key="name" :sort="sort" :direction="direction" @change="toggleSort" />
            <SortableTableHead label="E-mail" sort-key="email" :sort="sort" :direction="direction" @change="toggleSort" />
            <SortableTableHead label="Papel" sort-key="role" :sort="sort" :direction="direction" @change="toggleSort" />
            <TableHead class="w-20" />
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="user in users" :key="user.id">
            <TableCell class="font-medium">{{ user.name }}</TableCell>
            <TableCell>{{ user.email }}</TableCell>
            <TableCell>
              <Badge :variant="ROLE_BADGE_VARIANT[user.role] ?? 'secondary'">
                {{ ROLE_LABELS[user.role] ?? user.role }}
              </Badge>
            </TableCell>
            <TableCell>
              <div class="flex items-center gap-1">
                <Button variant="ghost" size="icon" @click="openEdit(user)">
                  <Pencil class="size-4" />
                </Button>
                <Button variant="ghost" size="icon" class="text-destructive" @click="remove(user)">
                  <Trash2 class="size-4" />
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Pagination :meta="meta" @change="(p) => (page = p)" />

    <UserFormDialog v-model:open="dialogOpen" :user="editingUser" @saved="load" />
  </div>
</template>
