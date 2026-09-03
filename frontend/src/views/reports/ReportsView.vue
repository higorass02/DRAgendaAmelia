<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import api from '@/lib/api'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { X } from '@lucide/vue'

const report = ref(null)
const loading = ref(true)
const professionals = ref([])
const patients = ref([])

const today = new Date().toISOString().slice(0, 10)
const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10)

const filters = reactive({
  from: thirtyDaysAgo,
  to: today,
  professional_id: 'all',
  patient_id: 'all',
})

async function load() {
  loading.value = true
  try {
    const params = { from: filters.from, to: filters.to }
    if (filters.professional_id !== 'all') params.professional_id = filters.professional_id
    if (filters.patient_id !== 'all') params.patient_id = filters.patient_id

    const { data } = await api.get('/reports', { params })
    report.value = data
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filters.from = thirtyDaysAgo
  filters.to = today
  filters.professional_id = 'all'
  filters.patient_id = 'all'
}

function formatPercent(value) {
  return `${value}%`
}

watch(filters, load)

onMounted(async () => {
  const [{ data: professionalData }, { data: patientData }] = await Promise.all([
    api.get('/professionals'),
    api.get('/patients'),
  ])
  professionals.value = professionalData.data
  patients.value = patientData.data
  load()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-xl font-semibold">Relatórios</h1>

    <div class="grid grid-cols-2 gap-3 rounded-md border border-border p-3 sm:grid-cols-3 lg:grid-cols-5">
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">De</Label>
        <Input v-model="filters.from" type="date" />
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Até</Label>
        <Input v-model="filters.to" type="date" />
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Profissional</Label>
        <Select v-model="filters.professional_id">
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos</SelectItem>
            <SelectItem v-for="p in professionals" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Paciente</Label>
        <Select v-model="filters.patient_id">
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos</SelectItem>
            <SelectItem v-for="p in patients" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <Button variant="ghost" size="sm" class="self-end justify-self-start" @click="clearFilters">
        <X class="size-4" />
        Limpar filtros
      </Button>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando...</p>

    <template v-else-if="report">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <Card>
          <CardHeader>
            <CardDescription>Taxa de não comparecimento</CardDescription>
            <CardTitle class="text-3xl">{{ formatPercent(report.no_show_rate) }}</CardTitle>
          </CardHeader>
        </Card>

        <Card>
          <CardHeader>
            <CardDescription>Taxa de remarcação</CardDescription>
            <CardTitle class="text-3xl">{{ formatPercent(report.reschedule_rate) }}</CardTitle>
          </CardHeader>
        </Card>

        <Card>
          <CardHeader>
            <CardDescription>Cancelamentos de última hora</CardDescription>
            <CardTitle class="text-3xl">{{ formatPercent(report.cancellations.last_minute_rate) }}</CardTitle>
          </CardHeader>
          <CardContent class="text-sm text-muted-foreground">
            {{ report.cancellations.last_minute }} de {{ report.cancellations.total }} cancelamentos
            tiveram menos de 24h de antecedência.
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Ocupação por profissional</CardTitle>
          <CardDescription>
            Minutos ocupados vs. capacidade disponível no período selecionado.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="report.occupancy_by_professional.length === 0" class="text-sm text-muted-foreground">
            Nenhum profissional encontrado para esse filtro.
          </div>
          <div v-else class="flex flex-col gap-3">
            <div
              v-for="p in report.occupancy_by_professional"
              :key="p.professional_id"
              class="flex flex-col gap-1"
            >
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium">{{ p.name }}</span>
                <span class="text-muted-foreground">
                  {{ p.occupancy_rate }}% ({{ p.occupied_minutes }}min / {{ p.capacity_minutes }}min)
                </span>
              </div>
              <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full rounded-full bg-primary"
                  :style="{ width: Math.min(p.occupancy_rate, 100) + '%' }"
                />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
