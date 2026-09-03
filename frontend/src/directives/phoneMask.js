import { createMaskDirective } from './createMaskDirective'

// Telefone BR: (XX) XXXXX-XXXX (celular) ou (XX) XXXX-XXXX (fixo). O valor
// "cru" (só dígitos) é normalizado de novo no backend antes de salvar — a
// máscara aqui é só pra leitura/digitação ficar mais fácil.
function formatPhone(value) {
  const digits = value.replace(/\D/g, '').slice(0, 11)

  if (digits.length === 0) return ''
  if (digits.length <= 2) return `(${digits}`

  const ddd = digits.slice(0, 2)
  const rest = digits.slice(2)

  if (rest.length <= 4) return `(${ddd}) ${rest}`

  // 10 dígitos no total = fixo (4+4); 11 = celular (5+4).
  const splitAt = digits.length <= 10 ? 4 : 5
  return `(${ddd}) ${rest.slice(0, splitAt)}-${rest.slice(splitAt)}`
}

export const phoneMask = createMaskDirective(formatPhone, { inputmode: 'tel', maxlength: 15 })
