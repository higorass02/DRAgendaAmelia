<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import api from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table'
import Pagination from '@/components/shared/Pagination.vue'
import SortableTableHead from '@/components/shared/SortableTableHead.vue'
import FilterDrawer from '@/components/shared/FilterDrawer.vue'
import DateInputBR from '@/components/shared/DateInputBR.vue'
import PatientFormDialog from '@/components/patients/PatientFormDialog.vue'
import PatientDetailDialog from '@/components/patients/PatientDetailDialog.vue'
import { useSort } from '@/composables/useSort'
import { formatCpf, formatPhone, formatDate } from '@/lib/format'
import { Plus, Pencil } from '@lucide/vue'

const { sort, direction, toggleSort } = useSort('name')

const patients = ref([])
const meta = ref(null)
const loading = ref(true)
const dialogOpen = ref(false)
const detailOpen = ref(false)
const filtersOpen = ref(false)
const editingPatient = ref(null)
const selectedPatient = ref(null)
const page = ref(1)

const filters = reactive({
  name: '',
  cpf: '',
  phone: '',
  email: '',
  birth_date_from: '',
  birth_date_to: '',
})

const activeFilterCount = computed(() => Object.values(filters).filter(Boolean).length)

async function load() {
  loading.value = true
  try {
    const params = { page: page.value, sort: sort.value, direction: direction.value }
    for (const [key, value] of Object.entries(filters)) {
      if (value) params[key] = value
    }
    const { data } = await api.get('/patients', { params })
    patients.value = data.data
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
  Object.keys(filters).forEach((key) => (filters[key] = ''))
}

function openCreate() {
  editingPatient.value = null
  dialogOpen.value = true
}

function openEdit(patient) {
  editingPatient.value = patient
  dialogOpen.value = true
}

function openDetail(patient) {
  selectedPatient.value = patient
  detailOpen.value = true
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Pacientes</h1>
      <div class="flex items-center gap-2">
        <FilterDrawer
          v-model:open="filtersOpen"
          :active-count="activeFilterCount"
          title="Filtrar pacientes"
          @clear="clearFilters"
        >
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">Nome</Label>
            <Input v-model="filters.name" placeholder="Buscar por nome" />
          </div>
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">CPF</Label>
            <Input v-model="filters.cpf" v-cpf-mask placeholder="Buscar por CPF" />
          </div>
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">Telefone</Label>
            <Input v-model="filters.phone" v-phone-mask placeholder="Buscar por telefone" />
          </div>
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">E-mail</Label>
            <Input v-model="filters.email" placeholder="Buscar por e-mail" />
          </div>
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">Nascido de</Label>
            <DateInputBR v-model="filters.birth_date_from" />
          </div>
          <div class="flex flex-col gap-1.5">
            <Label class="text-xs text-muted-foreground">Nascido até</Label>
            <DateInputBR v-model="filters.birth_date_to" />
          </div>
        </FilterDrawer>
        <Button @click="openCreate">
          <Plus class="size-4" />
          Novo paciente
        </Button>
      </div>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando...</p>
    <p v-else-if="patients.length === 0" class="text-sm text-muted-foreground">
      Nenhum paciente encontrado.
    </p>

    <div v-else class="overflow-x-auto rounded-md border border-border">
      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Nome" sort-key="name" :sort="sort" :direction="direction" @change="toggleSort" />
            <SortableTableHead label="CPF" sort-key="cpf" :sort="sort" :direction="direction" @change="toggleSort" />
            <TableHead>Telefone</TableHead>
            <SortableTableHead label="E-mail" sort-key="email" :sort="sort" :direction="direction" @change="toggleSort" />
            <SortableTableHead
              label="Nascimento"
              sort-key="birth_date"
              :sort="sort"
              :direction="direction"
              @change="toggleSort"
            />
            <TableHead class="w-10" />
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="patient in patients"
            :key="patient.id"
            class="cursor-pointer"
            @click="openDetail(patient)"
          >
            <TableCell class="font-medium">{{ patient.name }}</TableCell>
            <TableCell>{{ formatCpf(patient.cpf) }}</TableCell>
            <TableCell>{{ formatPhone(patient.phone) }}</TableCell>
            <TableCell>{{ patient.email ?? '—' }}</TableCell>
            <TableCell>{{ formatDate(patient.birth_date) }}</TableCell>
            <TableCell @click.stop>
              <Button variant="ghost" size="icon" @click="openEdit(patient)">
                <Pencil class="size-4" />
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Pagination :meta="meta" @change="(p) => (page = p)" />

    <PatientFormDialog
      v-model:open="dialogOpen"
      :patient="editingPatient"
      @saved="load"
    />
    <PatientDetailDialog
      v-model:open="detailOpen"
      :patient="selectedPatient"
      @edit="openEdit"
    />
  </div>
</template>
