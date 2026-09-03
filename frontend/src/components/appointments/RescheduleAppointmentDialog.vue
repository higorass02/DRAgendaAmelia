<script setup>
import { ref, watch } from 'vue'
import api from '@/lib/api'
import { toast } from 'vue-sonner'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { combineLocalDateTime, addMinutesLocal } from '@/lib/datetime'

const props = defineProps({
  open: { type: Boolean, required: true },
  appointment: { type: Object, default: null },
})

const emit = defineEmits(['update:open', 'saved'])

const date = ref('')
const startTime = ref('')
const durationMinutes = ref(30)
const reason = ref('')
const errors = ref({})
const generalError = ref('')
const saving = ref(false)

watch(
  () => props.open,
  (open) => {
    if (!open) return
    errors.value = {}
    generalError.value = ''
    date.value = ''
    startTime.value = ''
    durationMinutes.value = 30
    reason.value = ''
  },
)

async function handleSubmit() {
  saving.value = true
  errors.value = {}
  generalError.value = ''

  const startAt = combineLocalDateTime(date.value, startTime.value)
  const endAt = addMinutesLocal(startAt, durationMinutes.value)

  try {
    await api.post(`/appointments/${props.appointment.id}/reschedule`, {
      start_at: startAt,
      end_at: endAt,
      reason: reason.value || null,
    })
    toast.success('Consulta remarcada.')
    emit('saved')
    emit('update:open', false)
  } catch (e) {
    if (e.response?.status === 422 && e.response.data.errors) {
      errors.value = e.response.data.errors
    } else if (e.response?.data?.message) {
      generalError.value = e.response.data.message
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Remarcar consulta</DialogTitle>
        <DialogDescription>
          Cria uma nova consulta no horário escolhido e vincula à original.
        </DialogDescription>
      </DialogHeader>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-3 gap-2">
          <div class="col-span-1 flex flex-col gap-1.5">
            <Label for="r_date">Data</Label>
            <Input id="r_date" v-model="date" type="date" required />
          </div>
          <div class="col-span-1 flex flex-col gap-1.5">
            <Label for="r_start_time">Início</Label>
            <Input id="r_start_time" v-model="startTime" type="time" required />
          </div>
          <div class="col-span-1 flex flex-col gap-1.5">
            <Label for="r_duration">Duração (min)</Label>
            <Input id="r_duration" v-model="durationMinutes" type="number" min="5" step="5" required />
          </div>
        </div>
        <p v-if="errors.start_at" class="text-sm text-destructive">{{ errors.start_at[0] }}</p>
        <p v-if="errors.end_at" class="text-sm text-destructive">{{ errors.end_at[0] }}</p>

        <div class="flex flex-col gap-1.5">
          <Label for="r_reason">Motivo (opcional)</Label>
          <Textarea id="r_reason" v-model="reason" rows="3" />
        </div>

        <p
          v-if="generalError"
          class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive"
        >
          {{ generalError }}
        </p>

        <DialogFooter>
          <Button type="submit" :disabled="saving">{{ saving ? 'Remarcando...' : 'Remarcar' }}</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
