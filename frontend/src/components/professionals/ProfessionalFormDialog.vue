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
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select'
import { Plus, Trash2 } from '@lucide/vue'

const props = defineProps({
  open: { type: Boolean, required: true },
  professional: { type: Object, default: null },
})

const emit = defineEmits(['update:open', 'saved'])

const WEEKDAYS = [
  { value: '0', label: 'Domingo' },
  { value: '1', label: 'Segunda' },
  { value: '2', label: 'Terça' },
  { value: '3', label: 'Quarta' },
  { value: '4', label: 'Quinta' },
  { value: '5', label: 'Sexta' },
  { value: '6', label: 'Sábado' },
]

const name = ref('')
const specialty = ref('')
const availabilities = ref([])
const errors = ref({})
const saving = ref(false)

watch(
  () => [props.open, props.professional],
  () => {
    if (!props.open) return
    errors.value = {}
    if (props.professional) {
      name.value = props.professional.name
      specialty.value = props.professional.specialty
      availabilities.value = props.professional.availabilities.map((a) => ({
        weekday: String(a.weekday),
        start_time: a.start_time,
        end_time: a.end_time,
      }))
    } else {
      name.value = ''
      specialty.value = ''
      availabilities.value = [{ weekday: '1', start_time: '08:00', end_time: '18:00' }]
    }
  },
  { immediate: true },
)

function addAvailability() {
  availabilities.value.push({ weekday: '1', start_time: '08:00', end_time: '18:00' })
}

function removeAvailability(index) {
  availabilities.value.splice(index, 1)
}

async function handleSubmit() {
  saving.value = true
  errors.value = {}
  const payload = {
    name: name.value,
    specialty: specialty.value,
    availabilities: availabilities.value.map((a) => ({
      ...a,
      weekday: Number(a.weekday),
    })),
  }
  try {
    if (props.professional) {
      await api.put(`/professionals/${props.professional.id}`, payload)
      toast.success('Profissional atualizado.')
    } else {
      await api.post('/professionals', payload)
      toast.success('Profissional cadastrado.')
    }
    emit('saved')
    emit('update:open', false)
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors ?? {}
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
    <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>{{ professional ? 'Editar profissional' : 'Novo profissional' }}</DialogTitle>
        <DialogDescription>Nome, especialidade e a janela de horários de atendimento.</DialogDescription>
      </DialogHeader>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div class="flex flex-col gap-1.5">
          <Label for="name">Nome</Label>
          <Input id="name" v-model="name" required />
          <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label for="specialty">Especialidade</Label>
          <Input id="specialty" v-model="specialty" required />
          <p v-if="errors.specialty" class="text-sm text-destructive">{{ errors.specialty[0] }}</p>
        </div>

        <div class="flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <Label>Disponibilidade</Label>
            <Button type="button" variant="outline" size="sm" @click="addAvailability">
              <Plus class="size-4" />
              Adicionar horário
            </Button>
          </div>
          <p v-if="errors.availabilities" class="text-sm text-destructive">
            {{ errors.availabilities[0] }}
          </p>

          <div
            v-for="(a, index) in availabilities"
            :key="index"
            class="flex items-center gap-2 rounded-md border border-border p-2"
          >
            <Select v-model="a.weekday">
              <SelectTrigger class="w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="d in WEEKDAYS" :key="d.value" :value="d.value">
                  {{ d.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <Input v-model="a.start_time" type="time" class="w-28" required />
            <span class="text-muted-foreground text-sm">até</span>
            <Input v-model="a.end_time" type="time" class="w-28" required />
            <Button
              type="button"
              variant="ghost"
              size="icon"
              class="ml-auto"
              @click="removeAvailability(index)"
            >
              <Trash2 class="size-4" />
            </Button>
          </div>
        </div>

        <DialogFooter>
          <Button type="submit" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar' }}</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
