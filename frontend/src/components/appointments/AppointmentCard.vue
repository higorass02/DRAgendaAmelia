<script setup>
import StatusBadge from '@/components/appointments/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from '@/components/ui/dropdown-menu'
import { useAppointmentActions } from '@/composables/useAppointmentActions'
import { MoreVertical } from '@lucide/vue'

const props = defineProps({
  appointment: { type: Object, required: true },
})

const emit = defineEmits(['changed', 'cancel', 'reschedule', 'view'])

const { confirm, start, complete, noShow } = useAppointmentActions(() => emit('changed'))

const PRIMARY_ACTION = {
  scheduled: { run: confirm, label: 'Confirmar' },
  confirmed: { run: start, label: 'Iniciar' },
  in_progress: { run: complete, label: 'Concluir' },
}

function formatDateTime(iso) {
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div
    class="flex cursor-pointer flex-col gap-2 rounded-lg border border-border bg-background p-3 shadow-sm transition-shadow hover:shadow-md"
    @click="$emit('view', appointment)"
  >
    <div class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <p class="truncate font-medium">{{ appointment.patient.name }}</p>
        <p class="truncate text-sm text-muted-foreground">{{ appointment.professional.name }}</p>
      </div>
      <StatusBadge :status="appointment.status.value" />
    </div>

    <p class="text-sm text-muted-foreground">{{ formatDateTime(appointment.start_at) }}</p>

    <div v-if="appointment.status.value === 'confirmed'" class="flex items-center gap-2" @click.stop>
      <Button size="sm" @click="PRIMARY_ACTION.confirmed.run(appointment)">
        {{ PRIMARY_ACTION.confirmed.label }}
      </Button>
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button variant="ghost" size="icon"><MoreVertical class="size-4" /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent>
          <DropdownMenuItem @click="$emit('reschedule', appointment)">Remarcar</DropdownMenuItem>
          <DropdownMenuItem @click="noShow(appointment)">Não compareceu</DropdownMenuItem>
          <DropdownMenuItem class="text-destructive" @click="$emit('cancel', appointment)">
            Cancelar
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>

    <div v-else-if="appointment.status.value === 'scheduled'" class="flex items-center gap-2" @click.stop>
      <Button size="sm" @click="PRIMARY_ACTION.scheduled.run(appointment)">
        {{ PRIMARY_ACTION.scheduled.label }}
      </Button>
      <Button size="sm" variant="ghost" class="text-destructive" @click="$emit('cancel', appointment)">
        Cancelar
      </Button>
    </div>

    <div v-else-if="appointment.status.value === 'in_progress'" @click.stop>
      <Button size="sm" @click="PRIMARY_ACTION.in_progress.run(appointment)">
        {{ PRIMARY_ACTION.in_progress.label }}
      </Button>
    </div>
  </div>
</template>
