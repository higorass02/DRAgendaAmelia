<script setup>
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Pencil } from '@lucide/vue'
import { formatCpf, formatPhone, formatDate } from '@/lib/format'

const props = defineProps({
  open: { type: Boolean, required: true },
  patient: { type: Object, default: null },
})

const emit = defineEmits(['update:open', 'edit'])

function edit() {
  emit('update:open', false)
  emit('edit', props.patient)
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
    <DialogContent v-if="patient">
      <DialogHeader>
        <DialogTitle>{{ patient.name }}</DialogTitle>
        <DialogDescription>Dados do paciente.</DialogDescription>
      </DialogHeader>

      <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
        <div class="col-span-2">
          <dt class="text-xs text-muted-foreground">CPF</dt>
          <dd class="font-medium">{{ formatCpf(patient.cpf) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-muted-foreground">Telefone</dt>
          <dd class="font-medium">{{ formatPhone(patient.phone) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-muted-foreground">Nascimento</dt>
          <dd class="font-medium">{{ formatDate(patient.birth_date) }}</dd>
        </div>
        <div class="col-span-2">
          <dt class="text-xs text-muted-foreground">E-mail</dt>
          <dd class="font-medium">{{ patient.email ?? '—' }}</dd>
        </div>
      </dl>

      <DialogFooter>
        <Button variant="outline" @click="edit">
          <Pencil class="size-4" />
          Editar
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
