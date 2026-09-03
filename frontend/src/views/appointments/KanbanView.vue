<script setup>
import { reactive, ref, computed, onMounted, watch } from 'vue'
import draggable from 'vuedraggable'
import { toast } from 'vue-sonner'
import api from '@/lib/api'
import AppointmentsNav from '@/components/appointments/AppointmentsNav.vue'
import AppointmentCard from '@/components/appointments/AppointmentCard.vue'
import ScheduleAppointmentDialog from '@/components/appointments/ScheduleAppointmentDialog.vue'
import CancelAppointmentDialog from '@/components/appointments/CancelAppointmentDialog.vue'
import RescheduleAppointmentDialog from '@/components/appointments/RescheduleAppointmentDialog.vue'
import AppointmentDetailDialog from '@/components/appointments/AppointmentDetailDialog.vue'
import FilterDrawer from '@/components/shared/FilterDrawer.vue'
import DateInputBR from '@/components/shared/DateInputBR.vue'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { useAppointmentActions } from '@/composables/useAppointmentActions'
import { STATUS_ORDER, ALLOWED_TRANSITIONS, statusMeta, isTerminalStatus } from '@/lib/appointmentStatus'

const loading = ref(true)
const scheduleOpen = ref(false)
const cancelOpen = ref(false)
const rescheduleOpen = ref(false)
const detailOpen = ref(false)
const filtersOpen = ref(false)
const selectedAppointment = ref(null)
const selectedAppointmentId = ref(null)

const filters = reactive({ patient_name: '', professional_name: '', from: '', to: '' })
const activeFilterCount = computed(() => Object.values(filters).filter(Boolean).length)

// vuedraggable precisa de um array de verdade (mutável) por coluna — não dá
// pra usar um computed derivado direto, senão ele não consegue mover itens
// entre colunas.
const columnItems = reactive(Object.fromEntries(STATUS_ORDER.map((s) => [s, []])))

async function load() {
  loading.value = true
  try {
    const params = { per_page: 100 }
    if (filters.patient_name) params.patient_name = filters.patient_name
    if (filters.professional_name) params.professional_name = filters.professional_name
    if (filters.from) params.from = filters.from
    if (filters.to) params.to = filters.to

    const { data } = await api.get('/appointments', { params })
    for (const status of STATUS_ORDER) columnItems[status] = []
    for (const appointment of data.data) columnItems[appointment.status.value].push(appointment)
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filters.patient_name = ''
  filters.professional_name = ''
  filters.from = ''
  filters.to = ''
}

watch(filters, load)

const { confirm, start, complete, noShow } = useAppointmentActions(load)

const DROP_ACTION = { confirmed: confirm, in_progress: start, completed: complete, no_show: noShow }

function openCancel(appointment) {
  selectedAppointment.value = appointment
  cancelOpen.value = true
}

function openReschedule(appointment) {
  selectedAppointment.value = appointment
  rescheduleOpen.value = true
}

function openDetail(appointment) {
  selectedAppointmentId.value = appointment.id
  detailOpen.value = true
}

async function handleColumnChange(event, targetStatus) {
  if (!event.added) return

  const appointment = event.added.element
  const sourceStatus = appointment.status.value

  if (sourceStatus === targetStatus) return

  if (!ALLOWED_TRANSITIONS[sourceStatus]?.includes(targetStatus)) {
    toast.error(
      `Não é possível mover de "${statusMeta(sourceStatus).label}" para "${statusMeta(targetStatus).label}".`,
    )
    load()
    return
  }

  if (targetStatus === 'cancelled') {
    openCancel(appointment)
    load()
    return
  }
  if (targetStatus === 'rescheduled') {
    openReschedule(appointment)
    load()
    return
  }

  DROP_ACTION[targetStatus]?.(appointment)
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-4">
    <AppointmentsNav @schedule="scheduleOpen = true" />

    <div class="flex justify-end">
      <FilterDrawer
        v-model:open="filtersOpen"
        :active-count="activeFilterCount"
        title="Filtrar consultas"
        @clear="clearFilters"
      >
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">Paciente</Label>
          <Input v-model="filters.patient_name" placeholder="Buscar por nome" />
        </div>
        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">Profissional</Label>
          <Input v-model="filters.professional_name" placeholder="Buscar por nome" />
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

    <div v-else class="flex gap-4 overflow-x-auto pb-2">
      <div
        v-for="status in STATUS_ORDER"
        :key="status"
        class="flex w-72 shrink-0 flex-col gap-3 rounded-lg bg-muted/40 p-3"
      >
        <div class="flex items-center gap-2 px-1">
          <span class="size-2 rounded-full" :class="statusMeta(status).dotClass" />
          <h2 class="text-sm font-semibold">{{ statusMeta(status).label }}</h2>
          <span class="ml-auto text-xs text-muted-foreground">{{ columnItems[status].length }}</span>
        </div>

        <draggable
          :list="columnItems[status]"
          :group="{ name: 'appointments', pull: !isTerminalStatus(status), put: true }"
          :sort="!isTerminalStatus(status)"
          item-key="id"
          class="flex min-h-16 flex-col gap-3"
          ghost-class="opacity-40"
          @change="(e) => handleColumnChange(e, status)"
        >
          <template #item="{ element }">
            <AppointmentCard
              :appointment="element"
              @changed="load"
              @cancel="openCancel"
              @reschedule="openReschedule"
              @view="openDetail"
            />
          </template>
        </draggable>

        <p v-if="columnItems[status].length === 0" class="px-1 text-xs text-muted-foreground">
          Nenhuma consulta.
        </p>
      </div>
    </div>

    <ScheduleAppointmentDialog v-model:open="scheduleOpen" @saved="load" />
    <CancelAppointmentDialog
      v-model:open="cancelOpen"
      :appointment="selectedAppointment"
      @saved="load"
    />
    <RescheduleAppointmentDialog
      v-model:open="rescheduleOpen"
      :appointment="selectedAppointment"
      @saved="load"
    />
    <AppointmentDetailDialog v-model:open="detailOpen" :appointment-id="selectedAppointmentId" />
  </div>
</template>
