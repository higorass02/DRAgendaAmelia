<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/lib/api'
import AppointmentsNav from '@/components/appointments/AppointmentsNav.vue'
import StatusBadge from '@/components/appointments/StatusBadge.vue'
import ScheduleAppointmentDialog from '@/components/appointments/ScheduleAppointmentDialog.vue'
import { Label } from '@/components/ui/label'
import DateInputBR from '@/components/shared/DateInputBR.vue'
import { formatLocalDate } from '@/lib/datetime'

const appointments = ref([])
const loading = ref(true)
const scheduleOpen = ref(false)
const date = ref(formatLocalDate(new Date()))

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/appointments', {
      params: { from: date.value, to: date.value },
    })
    appointments.value = data.data
  } finally {
    loading.value = false
  }
}

const byProfessional = computed(() => {
  const groups = new Map()
  for (const appointment of appointments.value) {
    const key = appointment.professional.id
    if (!groups.has(key)) {
      groups.set(key, { professional: appointment.professional, items: [] })
    }
    groups.get(key).items.push(appointment)
  }
  return [...groups.values()].sort((a, b) => a.professional.name.localeCompare(b.professional.name))
})

function formatTime(iso) {
  return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

watch(date, load)
onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-4">
    <AppointmentsNav @schedule="scheduleOpen = true" />

    <div class="flex items-end gap-2">
      <div class="flex flex-col gap-1.5">
        <Label for="agenda_date">Dia</Label>
        <DateInputBR v-model="date" class="w-48" />
      </div>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando...</p>
    <p v-else-if="byProfessional.length === 0" class="text-sm text-muted-foreground">
      Nenhuma consulta nesse dia.
    </p>

    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="group in byProfessional"
        :key="group.professional.id"
        class="rounded-lg border border-border p-4"
      >
        <h2 class="font-semibold">{{ group.professional.name }}</h2>
        <p class="mb-3 text-sm text-muted-foreground">{{ group.professional.specialty }}</p>

        <ul class="flex flex-col gap-2">
          <li
            v-for="appointment in group.items"
            :key="appointment.id"
            class="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-3 py-2"
          >
            <div class="min-w-0">
              <p class="text-sm font-medium">{{ formatTime(appointment.start_at) }}</p>
              <p class="truncate text-sm text-muted-foreground">{{ appointment.patient.name }}</p>
            </div>
            <StatusBadge :status="appointment.status.value" />
          </li>
        </ul>
      </div>
    </div>

    <ScheduleAppointmentDialog v-model:open="scheduleOpen" @saved="load" />
  </div>
</template>
