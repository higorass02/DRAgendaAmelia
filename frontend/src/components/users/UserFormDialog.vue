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

const ROLES = [
  { value: 'admin', label: 'Administrador' },
  { value: 'staff', label: 'Equipe' },
  { value: 'patient', label: 'Paciente' },
]

const props = defineProps({
  open: { type: Boolean, required: true },
  user: { type: Object, default: null },
})

const emit = defineEmits(['update:open', 'saved'])

const form = ref(emptyForm())
const errors = ref({})
const saving = ref(false)

function emptyForm() {
  return { name: '', email: '', password: '', password_confirmation: '', role: 'staff' }
}

watch(
  () => [props.open, props.user],
  () => {
    if (!props.open) return
    errors.value = {}
    form.value = props.user
      ? { name: props.user.name, email: props.user.email, password: '', password_confirmation: '', role: props.user.role }
      : emptyForm()
  },
  { immediate: true },
)

async function handleSubmit() {
  saving.value = true
  errors.value = {}
  try {
    if (props.user) {
      const payload = { name: form.value.name, email: form.value.email, role: form.value.role }
      if (form.value.password) {
        payload.password = form.value.password
        payload.password_confirmation = form.value.password_confirmation
      }
      await api.put(`/users/${props.user.id}`, payload)
      toast.success('Usuário atualizado.')
    } else {
      await api.post('/users', form.value)
      toast.success('Usuário criado.')
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
        <DialogTitle>{{ user ? 'Editar usuário' : 'Novo usuário' }}</DialogTitle>
        <DialogDescription>Defina os dados de acesso e o papel do usuário.</DialogDescription>
      </DialogHeader>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div class="flex flex-col gap-1.5">
          <Label for="user_name">Nome</Label>
          <Input id="user_name" v-model="form.name" required />
          <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label for="user_email">E-mail</Label>
          <Input id="user_email" v-model="form.email" type="email" required />
          <p v-if="errors.email" class="text-sm text-destructive">{{ errors.email[0] }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label>Papel</Label>
          <Select v-model="form.role">
            <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="r in ROLES" :key="r.value" :value="r.value">{{ r.label }}</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.role" class="text-sm text-destructive">{{ errors.role[0] }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label for="user_password">{{ user ? 'Nova senha (opcional)' : 'Senha' }}</Label>
          <Input id="user_password" v-model="form.password" type="password" :required="!user" />
          <p v-if="errors.password" class="text-sm text-destructive">{{ errors.password[0] }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label for="user_password_confirmation">Confirmar senha</Label>
          <Input
            id="user_password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            :required="!user || !!form.password"
          />
        </div>

        <DialogFooter>
          <Button type="submit" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar' }}</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
