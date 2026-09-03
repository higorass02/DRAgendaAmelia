<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import api from '@/lib/api'
import AppointmentsNav from '@/components/appointments/AppointmentsNav.vue'
import StatusBadge from '@/components/appointments/StatusBadge.vue'
import ScheduleAppointmentDialog from '@/components/appointments/ScheduleAppointmentDialog.vue'
import CancelAppointmentDialog from '@/components/appointments/CancelAppointmentDialog.vue'
import RescheduleAppointmentDialog from '@/components/appointments/RescheduleAppointmentDialog.vue'
import AppointmentDetailDialog from '@/components/appointments/AppointmentDetailDialog.vue'
import Pagination from '@/components/shared/Pagination.vue'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from '@/components/ui/dropdown-menu'
import { useAppointmentActions } from '@/composables/useAppointmentActions'
import { STATUS_ORDER, statusMeta } from '@/lib/appointmentStatus'
import { MoreVertical, X } from '@lucide/vue'

const appointments = ref([])
const meta = ref(null)
const loading = ref(true)
const page = ref(1)
const scheduleOpen = ref(false)
const cancelOpen = ref(false)
const rescheduleOpen = ref(false)
const detailOpen = ref(false)
const selectedAppointment = ref(null)
const selectedAppointmentId = ref(null)

const filters = reactive({
  status: 'all',
  patient_name: '',
  professional_name: '',
  from: '',
  to: '',
})

const { confirm, start, complete, noShow } = useAppointmentActions(() => load())

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (filters.status !== 'all') params.status = filters.status
    if (filters.patient_name) params.patient_name = filters.patient_name
    if (filters.professional_name) params.professional_name = filters.professional_name
    if (filters.from) params.from = filters.from
    if (filters.to) params.to = filters.to

    const { data } = await api.get('/appointments', { params })
    appointments.value = data.data
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
  filters.status = 'all'
  filters.patient_name = ''
  filters.professional_name = ''
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

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-4">
    <AppointmentsNav @schedule="scheduleOpen = true" />

    <div class="grid grid-cols-2 gap-3 rounded-md border border-border p-3 sm:grid-cols-3 lg:grid-cols-6">
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Status</Label>
        <Select v-model="filters.status">
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos os status</SelectItem>
            <SelectItem v-for="s in STATUS_ORDER" :key="s" :value="s">{{ statusMeta(s).label }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Paciente</Label>
        <Input v-model="filters.patient_name" placeholder="Buscar por nome" />
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Profissional</Label>
        <Input v-model="filters.professional_name" placeholder="Buscar por nome" />
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">De</Label>
        <Input v-model="filters.from" type="date" />
      </div>
      <div class="flex flex-col gap-1">
        <Label class="text-xs text-muted-foreground">Até</Label>
        <Input v-model="filters.to" type="date" />
      </div>
      <Button variant="ghost" size="sm" class="self-end justify-self-start" @click="clearFilters">
        <X class="size-4" />
        Limpar filtros
      </Button>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando...</p>
    <p v-else-if="appointments.length === 0" class="text-sm text-muted-foreground">
      Nenhuma consulta encontrada.
    </p>

    <div v-else class="overflow-x-auto rounded-md border border-border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Paciente</TableHead>
            <TableHead>Profissional</TableHead>
            <TableHead>Data/Hora</TableHead>
            <TableHead>Status</TableHead>
            <TableHead class="w-10" />
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="appointment in appointments"
            :key="appointment.id"
            class="cursor-pointer"
            @click="openDetail(appointment)"
          >
            <TableCell class="font-medium">{{ appointment.patient.name }}</TableCell>
            <TableCell>{{ appointment.professional.name }}</TableCell>
            <TableCell>{{ formatDateTime(appointment.start_at) }}</TableCell>
            <TableCell><StatusBadge :status="appointment.status.value" /></TableCell>
            <TableCell @click.stop>
              <DropdownMenu v-if="['scheduled', 'confirmed', 'in_progress'].includes(appointment.status.value)">
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="icon"><MoreVertical class="size-4" /></Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                  <DropdownMenuItem v-if="appointment.status.value === 'scheduled'" @click="confirm(appointment)">
                    Confirmar
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="appointment.status.value === 'confirmed'" @click="start(appointment)">
                    Iniciar atendimento
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="appointment.status.value === 'in_progress'" @click="complete(appointment)">
                    Concluir
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="appointment.status.value === 'confirmed'" @click="openReschedule(appointment)">
                    Remarcar
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="appointment.status.value === 'confirmed'" @click="noShow(appointment)">
                    Não compareceu
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    v-if="['scheduled', 'confirmed'].includes(appointment.status.value)"
                    class="text-destructive"
                    @click="openCancel(appointment)"
                  >
                    Cancelar
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Pagination :meta="meta" @change="(p) => (page = p)" />

    <ScheduleAppointmentDialog v-model:open="scheduleOpen" @saved="load" />
    <CancelAppointmentDialog v-model:open="cancelOpen" :appointment="selectedAppointment" @saved="load" />
    <RescheduleAppointmentDialog
      v-model:open="rescheduleOpen"
      :appointment="selectedAppointment"
      @saved="load"
    />
    <AppointmentDetailDialog v-model:open="detailOpen" :appointment-id="selectedAppointmentId" />
  </div>
</template>
