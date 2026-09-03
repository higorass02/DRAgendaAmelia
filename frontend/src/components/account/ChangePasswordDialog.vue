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

const props = defineProps({
  open: { type: Boolean, required: true },
})

const emit = defineEmits(['update:open'])

const currentPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const errors = ref({})
const saving = ref(false)

watch(
  () => props.open,
  (open) => {
    if (!open) return
    currentPassword.value = ''
    password.value = ''
    passwordConfirmation.value = ''
    errors.value = {}
  },
)

async function handleSubmit() {
  saving.value = true
  errors.value = {}
  try {
    await api.put('/me/password', {
      current_password: currentPassword.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    toast.success('Senha alterada.')
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
        <DialogTitle>Trocar senha</DialogTitle>
        <DialogDescription>Informe a senha atual e a nova senha.</DialogDescription>
      </DialogHeader>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div class="flex flex-col gap-1.5">
          <Label for="current_password">Senha atual</Label>
          <Input id="current_password" v-model="currentPassword" type="password" required />
          <p v-if="errors.current_password" class="text-sm text-destructive">
            {{ errors.current_password[0] }}
          </p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label for="new_password">Nova senha</Label>
          <Input id="new_password" v-model="password" type="password" required />
          <p v-if="errors.password" class="text-sm text-destructive">{{ errors.password[0] }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <Label for="new_password_confirmation">Confirmar nova senha</Label>
          <Input id="new_password_confirmation" v-model="passwordConfirmation" type="password" required />
        </div>

        <DialogFooter>
          <Button type="submit" :disabled="saving">{{ saving ? 'Salvando...' : 'Trocar senha' }}</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
