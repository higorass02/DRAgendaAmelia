<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import api from '@/lib/api'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import FilterDrawer from '@/components/shared/FilterDrawer.vue'
import MultiSelect from '@/components/shared/MultiSelect.vue'
import DateInputBR from '@/components/shared/DateInputBR.vue'
import { formatMinutes } from '@/lib/duration'
import { formatLocalDate } from '@/lib/datetime'

const report = ref(null)
const loading = ref(true)
const professionals = ref([])
const patients = ref([])
const filtersOpen = ref(false)

const now = new Date()
// Padrão: mês atual inteiro (dia 1 ao último dia do mês) — não "até hoje"
// nem os últimos 30 dias corridos. Dia 0 do mês seguinte = último dia deste.
const startOfMonth = formatLocalDate(new Date(now.getFullYear(), now.getMonth(), 1))
const endOfMonth = formatLocalDate(new Date(now.getFullYear(), now.getMonth() + 1, 0))

const filters = reactive({
  from: startOfMonth,
  to: endOfMonth,
  professional_ids: [],
  patient_ids: [],
})

const activeFilterCount = computed(
  () =>
    (filters.from !== startOfMonth ? 1 : 0) +
    (filters.to !== endOfMonth ? 1 : 0) +
    (filters.professional_ids.length > 0 ? 1 : 0) +
    (filters.patient_ids.length > 0 ? 1 : 0),
)

const professionalOptions = computed(() => professionals.value.map((p) => ({ id: p.id, label: p.name })))
const patientOptions = computed(() => patients.value.map((p) => ({ id: p.id, label: p.name })))

async function load() {
  loading.value = true
  try {
    const params = { from: filters.from, to: filters.to }
    if (filters.professional_ids.length) params.professional_id = filters.professional_ids
    if (filters.patient_ids.length) params.patient_id = filters.patient_ids

    const { data } = await api.get('/reports', { params })
    report.value = data
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filters.from = ''
  filters.to = ''
  filters.professional_ids = []
  filters.patient_ids = []
}

function formatPercent(value) {
  return `${value}%`
}

watch(filters, load)

onMounted(async () => {
  const [{ data: professionalData }, { data: patientData }] = await Promise.all([
    api.get('/professionals', { params: { per_page: 100 } }),
    api.get('/patients', { params: { per_page: 100 } }),
  ])
  professionals.value = professionalData.data
  patients.value = patientData.data
  load()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Relatórios</h1>
      <FilterDrawer
        v-model:open="filtersOpen"
        :active-count="activeFilterCount"
        title="Filtrar relatórios"
        @clear="clearFilters"
      >
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">De</Label>
          <DateInputBR v-model="filters.from" />
        </div>
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">Até</Label>
          <DateInputBR v-model="filters.to" />
        </div>
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">Profissionais</Label>
          <MultiSelect
            v-model="filters.professional_ids"
            :options="professionalOptions"
            searchable
            placeholder="Todos os profissionais"
            search-placeholder="Buscar por nome"
          />
        </div>
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">Pacientes</Label>
          <MultiSelect
            v-model="filters.patient_ids"
            :options="patientOptions"
            searchable
            placeholder="Todos os pacientes"
            search-placeholder="Buscar por nome"
          />
        </div>
      </FilterDrawer>
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
                  {{ p.occupancy_rate }}% ({{ formatMinutes(p.occupied_minutes) }} / {{ formatMinutes(p.capacity_minutes) }})
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
