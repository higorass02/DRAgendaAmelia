<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import api from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table'
import Pagination from '@/components/shared/Pagination.vue'
import SortableTableHead from '@/components/shared/SortableTableHead.vue'
import FilterDrawer from '@/components/shared/FilterDrawer.vue'
import MultiSelect from '@/components/shared/MultiSelect.vue'
import DateInputBR from '@/components/shared/DateInputBR.vue'
import { useSort } from '@/composables/useSort'
import { formatAuditChanges } from '@/lib/auditFormat'
import { ArrowRight } from '@lucide/vue'

const { sort, direction, toggleSort } = useSort('created_at')

const logs = ref([])
const meta = ref(null)
const loading = ref(true)
const filtersOpen = ref(false)
const page = ref(1)
const users = ref([])
const userOptions = computed(() => users.value.map((u) => ({ id: u.id, label: u.name })))

const ACTION_LABELS = {
  login: 'Login',
  logout: 'Logout',
  created: 'Criado',
  updated: 'Atualizado',
  deleted: 'Excluído',
  password_changed: 'Senha alterada',
  account_deleted: 'Conta excluída',
  scheduled: 'Agendada',
  confirmed: 'Confirmada',
  in_progress: 'Iniciada',
  completed: 'Concluída',
  cancelled: 'Cancelada',
  no_show: 'Não compareceu',
  rescheduled: 'Remarcada',
}
const ACTIONS = Object.keys(ACTION_LABELS)
const SUBJECT_TYPES = [
  { value: 'patient', label: 'Paciente' },
  { value: 'professional', label: 'Profissional' },
  { value: 'appointment', label: 'Consulta' },
  { value: 'user', label: 'Usuário' },
]

const ACTION_BADGE_VARIANT = {
  created: 'default',
  scheduled: 'default',
  confirmed: 'default',
  updated: 'secondary',
  rescheduled: 'secondary',
  in_progress: 'secondary',
  completed: 'default',
  deleted: 'destructive',
  cancelled: 'destructive',
  account_deleted: 'destructive',
  no_show: 'destructive',
  login: 'outline',
  logout: 'outline',
  password_changed: 'outline',
}

const filters = reactive({ actor_ids: [], action: 'all', subject_type: 'all', from: '', to: '' })

const activeFilterCount = computed(
  () =>
    filters.actor_ids.length +
    (filters.action !== 'all' ? 1 : 0) +
    (filters.subject_type !== 'all' ? 1 : 0) +
    (filters.from ? 1 : 0) +
    (filters.to ? 1 : 0)
)

async function load() {
  loading.value = true
  try {
    const params = { page: page.value, sort: sort.value, direction: direction.value }
    if (filters.actor_ids.length) params.actor_id = filters.actor_ids
    if (filters.action !== 'all') params.action = filters.action
    if (filters.subject_type !== 'all') params.subject_type = filters.subject_type
    if (filters.from) params.from = filters.from
    if (filters.to) params.to = filters.to

    const { data } = await api.get('/audit-logs', { params })
    logs.value = data.data
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
  filters.actor_ids = []
  filters.action = 'all'
  filters.subject_type = 'all'
  filters.from = ''
  filters.to = ''
}

function formatDateTime(iso) {
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(async () => {
  const { data } = await api.get('/users', { params: { per_page: 100, sort: 'name' } })
  users.value = data.data
  load()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Auditoria</h1>
      <FilterDrawer
        v-model:open="filtersOpen"
        :active-count="activeFilterCount"
        title="Filtrar auditoria"
        @clear="clearFilters"
      >
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">Usuário (ator)</Label>
          <MultiSelect
            v-model="filters.actor_ids"
            :options="userOptions"
            searchable
            placeholder="Todos os usuários"
            search-placeholder="Buscar por nome"
          />
        </div>
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">Ação</Label>
          <Select v-model="filters.action">
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todas</SelectItem>
              <SelectItem v-for="a in ACTIONS" :key="a" :value="a">{{ ACTION_LABELS[a] }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">Tipo de recurso</Label>
          <Select v-model="filters.subject_type">
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos</SelectItem>
              <SelectItem v-for="s in SUBJECT_TYPES" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">De</Label>
          <DateInputBR v-model="filters.from" />
        </div>
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">Até</Label>
          <DateInputBR v-model="filters.to" />
        </div>
      </FilterDrawer>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando...</p>
    <p v-else-if="logs.length === 0" class="text-sm text-muted-foreground">Nenhum registro encontrado.</p>

    <div v-else class="overflow-x-auto rounded-md border border-border">
      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead
              label="Quando"
              sort-key="created_at"
              :sort="sort"
              :direction="direction"
              @change="toggleSort"
            />
            <TableHead>Quem</TableHead>
            <SortableTableHead label="Ação" sort-key="action" :sort="sort" :direction="direction" @change="toggleSort" />
            <TableHead>Recurso</TableHead>
            <TableHead>Detalhes</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="log in logs" :key="log.id">
            <TableCell class="whitespace-nowrap text-sm">{{ formatDateTime(log.created_at) }}</TableCell>
            <TableCell class="font-medium">{{ log.actor?.name ?? '—' }}</TableCell>
            <TableCell>
              <Badge :variant="ACTION_BADGE_VARIANT[log.action] ?? 'secondary'">{{ log.action_label }}</Badge>
            </TableCell>
            <TableCell class="text-sm">
              <span v-if="log.subject_label">{{ log.subject_type_label }}: {{ log.subject_label }}</span>
              <span v-else class="text-muted-foreground">—</span>
            </TableCell>
            <TableCell class="max-w-80 text-xs">
              <span v-if="!log.changes" class="text-muted-foreground">—</span>
              <dl v-else class="flex flex-col gap-1">
                <div v-for="c in formatAuditChanges(log.changes)" :key="c.key">
                  <dt class="font-medium text-foreground">{{ c.label }}</dt>
                  <dd v-if="c.isDiff" class="flex items-center gap-1 truncate text-muted-foreground">
                    <span class="text-destructive line-through decoration-destructive/50">{{ c.from ?? '—' }}</span>
                    <ArrowRight class="size-3 shrink-0" />
                    <span class="font-medium text-foreground">{{ c.to ?? '—' }}</span>
                  </dd>
                  <dd v-else class="truncate text-muted-foreground">{{ c.value }}</dd>
                </div>
              </dl>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Pagination :meta="meta" @change="(p) => (page = p)" />
  </div>
</template>
