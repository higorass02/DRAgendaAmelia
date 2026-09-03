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

const props = defineProps({
  open: { type: Boolean, required: true },
  patient: { type: Object, default: null },
})

const emit = defineEmits(['update:open', 'saved'])

const form = ref(emptyForm())
const errors = ref({})
const saving = ref(false)

function emptyForm() {
  return { name: '', cpf: '', phone: '', email: '', birth_date: '' }
}

watch(
  () => [props.open, props.patient],
  () => {
    if (!props.open) return
    errors.value = {}
    form.value = props.patient
      ? {
          name: props.patient.name,
          cpf: props.patient.cpf,
          phone: props.patient.phone,
          email: props.patient.email ?? '',
          birth_date: props.patient.birth_date,
        }
      : emptyForm()
  },
  { immediate: true },
)

async function handleSubmit() {
  saving.value = true
  errors.value = {}
  try {
    if (props.patient) {
      await api.put(`/patients/${props.patient.id}`, form.value)
      toast.success('Paciente atualizado.')
    } else {
      await api.post('/patients', form.value)
      toast.success('Paciente cadastrado.')
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
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ patient ? 'Editar paciente' : 'Novo paciente' }}</DialogTitle>
        <DialogDescription>Dados usados para identificar e contatar o paciente.</DialogDescription>
      </DialogHeader>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div class="flex flex-col gap-1.5">
          <Label for="name">Nome</Label>
          <Input id="name" v-model="form.name" required />
          <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label for="cpf">CPF</Label>
          <Input id="cpf" v-model="form.cpf" v-cpf-mask autocomplete="off" placeholder="000.000.000-00" required />
          <p v-if="errors.cpf" class="text-sm text-destructive">{{ errors.cpf[0] }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label for="phone">Telefone</Label>
          <Input id="phone" v-model="form.phone" v-phone-mask placeholder="(11) 99999-8888" autocomplete="tel" required />
          <p v-if="errors.phone" class="text-sm text-destructive">{{ errors.phone[0] }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label for="email">E-mail (opcional)</Label>
          <Input id="email" v-model="form.email" type="email" />
          <p v-if="errors.email" class="text-sm text-destructive">{{ errors.email[0] }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label for="birth_date">Data de nascimento</Label>
          <Input id="birth_date" v-model="form.birth_date" type="date" required />
          <p v-if="errors.birth_date" class="text-sm text-destructive">{{ errors.birth_date[0] }}</p>
        </div>

        <DialogFooter>
          <Button type="submit" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar' }}</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
