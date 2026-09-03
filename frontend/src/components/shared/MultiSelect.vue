<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { onClickOutside } from '@vueuse/core'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Check, ChevronDown, Search } from '@lucide/vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  options: { type: Array, required: true }, // [{ id, label }]
  placeholder: { type: String, default: 'Selecionar...' },
  searchable: { type: Boolean, default: false },
  searchPlaceholder: { type: String, default: 'Buscar...' },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const query = ref('')
const root = ref(null)
const searchInput = ref(null)
onClickOutside(root, () => (open.value = false))

watch(open, (isOpen) => {
  if (!isOpen) {
    query.value = ''
    return
  }
  if (props.searchable) {
    // Input.vue não expõe focus() diretamente — o ref de um componente
    // <script setup> sem defineExpose só dá acesso a $el (o <input> real).
    nextTick(() => searchInput.value?.$el?.focus())
  }
})

function toggle(id) {
  const set = new Set(props.modelValue)
  if (set.has(id)) {
    set.delete(id)
  } else {
    set.add(id)
  }
  emit('update:modelValue', Array.from(set))
}

const filteredOptions = computed(() => {
  if (!props.searchable || !query.value.trim()) return props.options
  const needle = query.value.trim().toLowerCase()
  return props.options.filter((o) => o.label.toLowerCase().includes(needle))
})

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
      class="absolute z-50 mt-1 flex w-full flex-col overflow-hidden rounded-lg border border-border bg-popover shadow-lg"
    >
      <div v-if="searchable" class="relative border-b border-border p-1">
        <Search class="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
        <Input
          ref="searchInput"
          v-model="query"
          :placeholder="searchPlaceholder"
          class="h-7 border-0 pl-7 shadow-none focus-visible:ring-0"
          @keydown.stop
        />
      </div>

      <div class="max-h-56 overflow-y-auto p-1">
        <button
          v-for="opt in filteredOptions"
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
        <p v-if="filteredOptions.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
          Nenhuma opção encontrada.
        </p>
      </div>
    </div>
  </div>
</template>
