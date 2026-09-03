<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import api from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table'
import Pagination from '@/components/shared/Pagination.vue'
import PatientFormDialog from '@/components/patients/PatientFormDialog.vue'
import { Plus, Pencil, X } from '@lucide/vue'

const patients = ref([])
const meta = ref(null)
const loading = ref(true)
const dialogOpen = ref(false)
const editingPatient = ref(null)
const page = ref(1)

const filters = reactive({
  name: '',
  cpf: '',
  phone: '',
  email: '',
  birth_date_from: '',
  birth_date_to: '',
})

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
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

function formatCpf(cpf) {
  return cpf?.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') ?? cpf
}

function formatDate(date) {
  return new Date(date + 'T00:00:00').toLocaleDateString('pt-BR')
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Pacientes</h1>
      <Button @click="openCreate">
        <Plus class="size-4" />
        Novo paciente
      </Button>
    </div>

    <div class="grid grid-cols-2 gap-3 rounded-md border border-border p-3 sm:grid-cols-3 lg:grid-cols-6">
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Nome</Label>
        <Input v-model="filters.name" placeholder="Buscar por nome" />
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">CPF</Label>
        <Input v-model="filters.cpf" placeholder="Buscar por CPF" />
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Telefone</Label>
        <Input v-model="filters.phone" placeholder="Buscar por telefone" />
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">E-mail</Label>
        <Input v-model="filters.email" placeholder="Buscar por e-mail" />
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Nascido de</Label>
        <Input v-model="filters.birth_date_from" type="date" />
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Nascido até</Label>
        <Input v-model="filters.birth_date_to" type="date" />
      </div>
      <Button variant="ghost" size="sm" class="col-span-2 justify-self-start sm:col-span-1" @click="clearFilters">
        <X class="size-4" />
        Limpar filtros
      </Button>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando...</p>
    <p v-else-if="patients.length === 0" class="text-sm text-muted-foreground">
      Nenhum paciente encontrado.
    </p>

    <div v-else class="overflow-x-auto rounded-md border border-border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Nome</TableHead>
            <TableHead>CPF</TableHead>
            <TableHead>Telefone</TableHead>
            <TableHead>E-mail</TableHead>
            <TableHead>Nascimento</TableHead>
            <TableHead class="w-10" />
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="patient in patients" :key="patient.id">
            <TableCell class="font-medium">{{ patient.name }}</TableCell>
            <TableCell>{{ formatCpf(patient.cpf) }}</TableCell>
            <TableCell>{{ patient.phone }}</TableCell>
            <TableCell>{{ patient.email ?? '—' }}</TableCell>
            <TableCell>{{ formatDate(patient.birth_date) }}</TableCell>
            <TableCell>
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
  </div>
</template>
