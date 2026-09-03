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

const emit = defineEmits(['update:open', 'deleted'])

const password = ref('')
const errors = ref({})
const saving = ref(false)

watch(
  () => props.open,
  (open) => {
    if (!open) return
    password.value = ''
    errors.value = {}
  },
)

async function handleSubmit() {
  saving.value = true
  errors.value = {}
  try {
    await api.delete('/me', { data: { password: password.value } })
    toast.success('Conta excluída.')
    emit('update:open', false)
    emit('deleted')
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
        <DialogTitle>Excluir minha conta</DialogTitle>
        <DialogDescription>
          Essa ação não pode ser desfeita. Todos os seus tokens de acesso serão revogados.
        </DialogDescription>
      </DialogHeader>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div class="flex flex-col gap-1.5">
          <Label for="delete_password">Confirme sua senha</Label>
          <Input id="delete_password" v-model="password" type="password" required />
          <p v-if="errors.password" class="text-sm text-destructive">{{ errors.password[0] }}</p>
        </div>

        <DialogFooter>
          <Button type="submit" variant="destructive" :disabled="saving">
            {{ saving ? 'Excluindo...' : 'Excluir minha conta' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
