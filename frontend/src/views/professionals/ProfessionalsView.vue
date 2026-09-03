<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import api from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import Pagination from '@/components/shared/Pagination.vue'
import SortableTableHead from '@/components/shared/SortableTableHead.vue'
import FilterDrawer from '@/components/shared/FilterDrawer.vue'
import ProfessionalFormDialog from '@/components/professionals/ProfessionalFormDialog.vue'
import ProfessionalDetailDialog from '@/components/professionals/ProfessionalDetailDialog.vue'
import { useSort } from '@/composables/useSort'
import { Plus, Pencil } from '@lucide/vue'

const WEEKDAY_LABELS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

const { sort, direction, toggleSort } = useSort('name')

const professionals = ref([])
const meta = ref(null)
const loading = ref(true)
const dialogOpen = ref(false)
const detailOpen = ref(false)
const filtersOpen = ref(false)
const editingProfessional = ref(null)
const selectedProfessional = ref(null)
const page = ref(1)

const filters = reactive({ name: '', specialty: '' })

const activeFilterCount = computed(() => Object.values(filters).filter(Boolean).length)

async function load() {
  loading.value = true
  try {
    const params = { page: page.value, sort: sort.value, direction: direction.value }
    for (const [key, value] of Object.entries(filters)) {
      if (value) params[key] = value
    }
    const { data } = await api.get('/professionals', { params })
    professionals.value = data.data
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
  filters.specialty = ''
}

function openCreate() {
  editingProfessional.value = null
  dialogOpen.value = true
}

function openEdit(professional) {
  editingProfessional.value = professional
  dialogOpen.value = true
}

function openDetail(professional) {
  selectedProfessional.value = professional
  detailOpen.value = true
}

function availabilitySummary(availabilities) {
  return availabilities
    .map((a) => `${WEEKDAY_LABELS[a.weekday]} ${a.start_time}-${a.end_time}`)
    .join(', ')
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Profissionais</h1>
      <div class="flex items-center gap-2">
        <FilterDrawer
          v-model:open="filtersOpen"
          :active-count="activeFilterCount"
          title="Filtrar profissionais"
          @clear="clearFilters"
        >
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">Nome</Label>
            <Input v-model="filters.name" placeholder="Buscar por nome" />
          </div>
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">Especialidade</Label>
            <Input v-model="filters.specialty" placeholder="Buscar por especialidade" />
          </div>
        </FilterDrawer>
        <Button @click="openCreate">
          <Plus class="size-4" />
          Novo profissional
        </Button>
      </div>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando...</p>
    <p v-else-if="professionals.length === 0" class="text-sm text-muted-foreground">
      Nenhum profissional encontrado.
    </p>

    <div v-else class="overflow-x-auto rounded-md border border-border">
      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Nome" sort-key="name" :sort="sort" :direction="direction" @change="toggleSort" />
            <SortableTableHead
              label="Especialidade"
              sort-key="specialty"
              :sort="sort"
              :direction="direction"
              @change="toggleSort"
            />
            <TableHead>Disponibilidade</TableHead>
            <TableHead class="w-10" />
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="professional in professionals"
            :key="professional.id"
            class="cursor-pointer"
            @click="openDetail(professional)"
          >
            <TableCell class="font-medium">{{ professional.name }}</TableCell>
            <TableCell><Badge variant="secondary">{{ professional.specialty }}</Badge></TableCell>
            <TableCell class="text-sm text-muted-foreground">
              {{ availabilitySummary(professional.availabilities) }}
            </TableCell>
            <TableCell @click.stop>
              <Button variant="ghost" size="icon" @click="openEdit(professional)">
                <Pencil class="size-4" />
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Pagination :meta="meta" @change="(p) => (page = p)" />

    <ProfessionalFormDialog
      v-model:open="dialogOpen"
      :professional="editingProfessional"
      @saved="load"
    />
    <ProfessionalDetailDialog
      v-model:open="detailOpen"
      :professional="selectedProfessional"
      @edit="openEdit"
    />
  </div>
</template>
