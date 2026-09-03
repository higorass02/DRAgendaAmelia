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
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { combineLocalDateTime, addMinutesLocal } from '@/lib/datetime'

const props = defineProps({
  open: { type: Boolean, required: true },
})

const emit = defineEmits(['update:open', 'saved'])

const patients = ref([])
const professionals = ref([])
const patientId = ref('')
const professionalId = ref('')
const date = ref('')
const startTime = ref('')
const durationMinutes = ref(30)
const errors = ref({})
const generalError = ref('')
const saving = ref(false)

watch(
  () => props.open,
  async (open) => {
    if (!open) return
    errors.value = {}
    generalError.value = ''
    patientId.value = ''
    professionalId.value = ''
    date.value = ''
    startTime.value = ''
    durationMinutes.value = 30

    const [{ data: patientData }, { data: professionalData }] = await Promise.all([
      api.get('/patients'),
      api.get('/professionals'),
    ])
    patients.value = patientData.data
    professionals.value = professionalData.data
  },
)

async function handleSubmit() {
  saving.value = true
  errors.value = {}
  generalError.value = ''

  const startAt = combineLocalDateTime(date.value, startTime.value)
  const endAt = addMinutesLocal(startAt, durationMinutes.value)

  try {
    await api.post('/appointments', {
      patient_id: Number(patientId.value),
      professional_id: Number(professionalId.value),
      start_at: startAt,
      end_at: endAt,
    })
    toast.success('Consulta agendada.')
    emit('saved')
    emit('update:open', false)
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors ?? {}
    } else if (e.response?.status === 409 || e.response?.data?.message) {
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
        <DialogTitle>Agendar consulta</DialogTitle>
        <DialogDescription>Escolha o paciente, o profissional e o horário.</DialogDescription>
      </DialogHeader>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div class="flex flex-col gap-1.5">
          <Label>Paciente</Label>
          <Select v-model="patientId">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Selecione o paciente" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="p in patients" :key="p.id" :value="String(p.id)">
                {{ p.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.patient_id" class="text-sm text-destructive">{{ errors.patient_id[0] }}</p>
        </div>

        <div class="flex flex-col gap-1.5">
          <Label>Profissional</Label>
          <Select v-model="professionalId">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Selecione o profissional" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="p in professionals" :key="p.id" :value="String(p.id)">
                {{ p.name }} — {{ p.specialty }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.professional_id" class="text-sm text-destructive">
            {{ errors.professional_id[0] }}
          </p>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <div class="col-span-1 flex flex-col gap-1.5">
            <Label for="date">Data</Label>
            <Input id="date" v-model="date" type="date" required />
          </div>
          <div class="col-span-1 flex flex-col gap-1.5">
            <Label for="start_time">Início</Label>
            <Input id="start_time" v-model="startTime" type="time" required />
          </div>
          <div class="col-span-1 flex flex-col gap-1.5">
            <Label for="duration">Duração (min)</Label>
            <Input id="duration" v-model="durationMinutes" type="number" min="5" step="5" required />
          </div>
        </div>
        <p v-if="errors.start_at" class="text-sm text-destructive">{{ errors.start_at[0] }}</p>
        <p v-if="errors.end_at" class="text-sm text-destructive">{{ errors.end_at[0] }}</p>
        <p v-if="generalError" class="text-sm text-destructive">{{ generalError }}</p>

        <DialogFooter>
          <Button type="submit" :disabled="saving">{{ saving ? 'Agendando...' : 'Agendar' }}</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
