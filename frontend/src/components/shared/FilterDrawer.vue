<script setup>
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter } from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Menu, X } from '@lucide/vue'

const props = defineProps({
  open: { type: Boolean, required: true },
  activeCount: { type: Number, default: 0 },
  title: { type: String, default: 'Filtros' },
})

const emit = defineEmits(['update:open', 'clear'])
</script>

<template>
  <Button variant="outline" size="sm" class="relative" @click="emit('update:open', true)">
    <Menu class="size-4" />
    Filtros
    <Badge v-if="activeCount > 0" variant="secondary" class="ml-1 px-1.5">{{ activeCount }}</Badge>
  </Button>

  <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
    <SheetContent>
      <SheetHeader>
        <SheetTitle>{{ title }}</SheetTitle>
        <SheetDescription>Ajuste os filtros e a lista atualiza automaticamente.</SheetDescription>
      </SheetHeader>

      <div class="flex flex-1 flex-col gap-4 overflow-y-auto">
        <slot />
      </div>

      <SheetFooter class="!mx-0 !mb-0 !rounded-none border-t-0 bg-transparent p-0 pt-2">
        <Button variant="ghost" size="sm" class="w-full sm:w-auto" @click="emit('clear')">
          <X class="size-4" />
          Limpar filtros
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
