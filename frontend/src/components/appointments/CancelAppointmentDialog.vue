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
import { Textarea } from '@/components/ui/textarea'

const props = defineProps({
  open: { type: Boolean, required: true },
  appointment: { type: Object, default: null },
})

const emit = defineEmits(['update:open', 'saved'])

const reason = ref('')
const saving = ref(false)

watch(
  () => props.open,
  (open) => {
    if (open) reason.value = ''
  },
)

async function handleSubmit() {
  saving.value = true
  try {
    await api.post(`/appointments/${props.appointment.id}/cancel`, { reason: reason.value || null })
    toast.success('Consulta cancelada.')
    emit('saved')
    emit('update:open', false)
  } catch {
    // toast de erro já vem do interceptor
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Cancelar consulta</DialogTitle>
        <DialogDescription>
          O paciente será notificado. Essa ação não pode ser desfeita.
        </DialogDescription>
      </DialogHeader>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div class="flex flex-col gap-1.5">
          <Label for="reason">Motivo (opcional)</Label>
          <Textarea id="reason" v-model="reason" rows="3" />
        </div>

        <DialogFooter>
          <Button type="submit" variant="destructive" :disabled="saving">
            {{ saving ? 'Cancelando...' : 'Confirmar cancelamento' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
