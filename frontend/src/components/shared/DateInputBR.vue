<script setup>
import { ref, watch } from 'vue'
import { Input } from '@/components/ui/input'

// Campo de data em texto mascarado dd/mm/aaaa — não depende do <input
// type="date"> nativo (cujo formato de exibição segue o idioma do SO/
// navegador, fora do nosso controle). v-model continua em "YYYY-MM-DD"
// (ISO), então quem usa este componente não muda nada além da tag.
const props = defineProps({
  modelValue: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

function isoToDisplay(iso) {
  const [y, m, d] = (iso ?? '').split('-')
  if (!y || !m || !d) return ''
  return `${d}/${m}/${y}`
}

const display = ref(isoToDisplay(props.modelValue))

watch(
  () => props.modelValue,
  (iso) => {
    const expected = isoToDisplay(iso)
    if (expected !== display.value) display.value = expected
  },
)

function formatTyping(raw) {
  const digits = raw.replace(/\D/g, '').slice(0, 8)
  let day = digits.slice(0, 2)
  let month = digits.slice(2, 4)
  const year = digits.slice(4, 8)

  // Trava os limites óbvios enquanto digita (não deixa passar de 31/12) —
  // validade real de calendário (ex.: 31/02) fica pro backend.
  if (day.length === 2 && Number(day) > 31) day = '31'
  if (month.length === 2 && Number(month) > 12) month = '12'

  if (digits.length <= 2) return day
  if (digits.length <= 4) return `${day}/${month}`
  return `${day}/${month}/${year}`
}

function handleInput(raw) {
  const formatted = formatTyping(raw ?? '')
  display.value = formatted

  const digits = formatted.replace(/\D/g, '')
  if (digits.length === 8) {
    const day = digits.slice(0, 2)
    const month = digits.slice(2, 4)
    const year = digits.slice(4, 8)
    emit('update:modelValue', `${year}-${month}-${day}`)
  } else if (digits.length === 0) {
    emit('update:modelValue', '')
  }
  // Data incompleta (1-7 dígitos): não emite ainda, só atualiza o texto
  // exibido — espera terminar de digitar antes de virar um filtro válido.
}
</script>

<template>
  <Input
    :model-value="display"
    inputmode="numeric"
    placeholder="dd/mm/aaaa"
    maxlength="10"
    @update:model-value="handleInput"
  />
</template>
