<script setup>
import { ref, watch } from 'vue'
import api from '@/lib/api'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import StatusBadge from '@/components/appointments/StatusBadge.vue'
import { Repeat } from '@lucide/vue'

const props = defineProps({
  open: { type: Boolean, required: true },
  appointmentId: { type: [Number, String], default: null },
})

const emit = defineEmits(['update:open'])

const appointment = ref(null)
const loading = ref(false)

watch(
  () => [props.open, props.appointmentId],
  async ([open, id]) => {
    if (!open || !id) {
      appointment.value = null
      return
    }
    loading.value = true
    try {
      const { data } = await api.get(`/appointments/${id}`)
      appointment.value = data.data
    } finally {
      loading.value = false
    }
  },
)

function formatDateTime(iso) {
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
    <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Detalhes da consulta</DialogTitle>
        <DialogDescription>Dados completos e histórico de mudanças de status.</DialogDescription>
      </DialogHeader>

      <p v-if="loading" class="text-sm text-muted-foreground">Carregando...</p>

      <div v-else-if="appointment" class="flex flex-col gap-5">
        <div class="flex items-center justify-between">
          <StatusBadge :status="appointment.status.value" />
          <span class="text-sm text-muted-foreground">#{{ appointment.id }}</span>
        </div>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
          <div class="col-span-2">
            <dt class="text-muted-foreground">Paciente</dt>
            <dd class="font-medium">{{ appointment.patient.name }}</dd>
          </div>
          <div class="col-span-2">
            <dt class="text-muted-foreground">Profissional</dt>
            <dd class="font-medium">
              {{ appointment.professional.name }} — {{ appointment.professional.specialty }}
            </dd>
          </div>
          <div>
            <dt class="text-muted-foreground">Início</dt>
            <dd>{{ formatDateTime(appointment.start_at) }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">Fim</dt>
            <dd>{{ formatDateTime(appointment.end_at) }}</dd>
          </div>
          <div class="col-span-2">
            <dt class="text-muted-foreground">Agendada por</dt>
            <dd>{{ appointment.created_by.name }}</dd>
          </div>
        </dl>

        <div
          v-if="appointment.rescheduled_from_id || appointment.rescheduled_to_id"
          class="flex items-center gap-2 rounded-md bg-purple-50 px-3 py-2 text-sm text-purple-800 dark:bg-purple-950 dark:text-purple-300"
        >
          <Repeat class="size-4 shrink-0" />
          <span v-if="appointment.rescheduled_from_id">
            Remarcada a partir da consulta #{{ appointment.rescheduled_from_id }}
          </span>
          <span v-else>Remarcada para a consulta #{{ appointment.rescheduled_to_id }}</span>
        </div>

        <div>
          <h3 class="mb-2 text-sm font-semibold">Histórico</h3>
          <ol class="flex flex-col gap-3 border-l border-border pl-4">
            <li v-for="h in appointment.status_history" :key="h.id" class="relative">
              <span class="absolute -left-[21px] top-1 size-2 rounded-full bg-primary" />
              <div class="flex items-center gap-2">
                <StatusBadge :status="h.to_status.value" />
                <span class="text-xs text-muted-foreground">{{ formatDateTime(h.changed_at) }}</span>
              </div>
              <p class="mt-1 text-sm text-muted-foreground">
                por {{ h.changed_by.name }}
                <template v-if="h.reason">— "{{ h.reason }}"</template>
              </p>
            </li>
          </ol>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
