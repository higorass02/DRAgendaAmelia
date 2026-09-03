<script setup>
import { ref, computed } from 'vue'
import { onClickOutside } from '@vueuse/core'
import { Button } from '@/components/ui/button'
import { Check, ChevronDown } from '@lucide/vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  options: { type: Array, required: true }, // [{ id, label }]
  placeholder: { type: String, default: 'Selecionar...' },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const root = ref(null)
onClickOutside(root, () => (open.value = false))

function toggle(id) {
  const set = new Set(props.modelValue)
  if (set.has(id)) {
    set.delete(id)
  } else {
    set.add(id)
  }
  emit('update:modelValue', Array.from(set))
}

const triggerLabel = computed(() => {
  if (props.modelValue.length === 0) return props.placeholder
  if (props.modelValue.length === 1) {
    return props.options.find((o) => o.id === props.modelValue[0])?.label ?? props.placeholder
  }
  return `${props.modelValue.length} selecionados`
})
</script>

<template>
  <div ref="root" class="relative">
    <Button
      type="button"
      variant="outline"
      class="w-full justify-between font-normal"
      @click="open = !open"
    >
      <span class="truncate">{{ triggerLabel }}</span>
      <ChevronDown class="size-4 opacity-50" />
    </Button>

    <div
      v-if="open"
      class="absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-border bg-popover p-1 shadow-lg"
    >
      <button
        v-for="opt in options"
        :key="opt.id"
        type="button"
        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent"
        @click="toggle(opt.id)"
      >
        <span
          class="flex size-4 shrink-0 items-center justify-center rounded border border-input"
          :class="{ 'border-primary bg-primary text-primary-foreground': modelValue.includes(opt.id) }"
        >
          <Check v-if="modelValue.includes(opt.id)" class="size-3" />
        </span>
        <span class="truncate">{{ opt.label }}</span>
      </button>
      <p v-if="options.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">Nenhuma opção.</p>
    </div>
  </div>
</template>
