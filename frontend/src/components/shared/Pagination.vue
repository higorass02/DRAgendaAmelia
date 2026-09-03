<script setup>
import { Button } from '@/components/ui/button'
import { ChevronLeft, ChevronRight } from '@lucide/vue'

const props = defineProps({
  meta: { type: Object, default: null },
})

const emit = defineEmits(['change'])
</script>

<template>
  <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between gap-4 text-sm">
    <p class="text-muted-foreground">
      {{ meta.total }} {{ meta.total === 1 ? 'resultado' : 'resultados' }} — página
      {{ meta.current_page }} de {{ meta.last_page }}
    </p>
    <div class="flex items-center gap-2">
      <Button
        variant="outline"
        size="sm"
        :disabled="meta.current_page <= 1"
        @click="emit('change', meta.current_page - 1)"
      >
        <ChevronLeft class="size-4" />
        Anterior
      </Button>
      <Button
        variant="outline"
        size="sm"
        :disabled="meta.current_page >= meta.last_page"
        @click="emit('change', meta.current_page + 1)"
      >
        Próxima
        <ChevronRight class="size-4" />
      </Button>
    </div>
  </div>
</template>
