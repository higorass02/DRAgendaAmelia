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
import { Badge } from '@/components/ui/badge'
import { Pencil } from '@lucide/vue'

const WEEKDAY_LABELS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']

const props = defineProps({
  open: { type: Boolean, required: true },
  professional: { type: Object, default: null },
})

const emit = defineEmits(['update:open', 'edit'])

function edit() {
  emit('update:open', false)
  emit('edit', props.professional)
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
    <DialogContent v-if="professional">
      <DialogHeader>
        <DialogTitle>{{ professional.name }}</DialogTitle>
        <DialogDescription>
          <Badge variant="secondary">{{ professional.specialty }}</Badge>
        </DialogDescription>
      </DialogHeader>

      <div class="flex flex-col gap-1.5">
        <p class="text-xs text-muted-foreground">Disponibilidade</p>
        <ul class="flex flex-col gap-1 text-sm">
          <li v-for="a in professional.availabilities" :key="a.id" class="flex justify-between">
            <span class="font-medium">{{ WEEKDAY_LABELS[a.weekday] }}</span>
            <span class="text-muted-foreground">{{ a.start_time }}–{{ a.end_time }}</span>
          </li>
        </ul>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="edit">
          <Pencil class="size-4" />
          Editar
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
