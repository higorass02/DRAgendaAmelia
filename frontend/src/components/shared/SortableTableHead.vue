<script setup>
import { TableHead } from '@/components/ui/table'
import { ChevronUp, ChevronDown, ChevronsUpDown } from '@lucide/vue'

const props = defineProps({
  label: { type: String, required: true },
  sortKey: { type: String, required: true },
  sort: { type: String, default: '' },
  direction: { type: String, default: 'asc' },
  class: { type: String, default: '' },
})

defineEmits(['change'])
</script>

<template>
  <TableHead :class="props.class">
    <button
      type="button"
      class="inline-flex items-center gap-1 text-foreground hover:text-primary"
      @click="$emit('change', sortKey)"
    >
      {{ label }}
      <ChevronUp v-if="sort === sortKey && direction === 'asc'" class="size-3.5" />
      <ChevronDown v-else-if="sort === sortKey && direction === 'desc'" class="size-3.5" />
      <ChevronsUpDown v-else class="size-3.5 opacity-30" />
    </button>
  </TableHead>
</template>
