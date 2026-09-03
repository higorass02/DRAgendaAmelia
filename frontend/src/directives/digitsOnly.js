// Restringe um <input> (telefone, CEP, etc.) a dígitos — bloqueia tecla não
// numérica e sanitiza colagem, sem depender de type="number" (que quebra
// zero à esquerda e formatação).
export const digitsOnly = {
  mounted(el) {
    el.setAttribute('inputmode', 'numeric')

    el.addEventListener('input', () => {
      const cleaned = el.value.replace(/\D/g, '')
      if (cleaned !== el.value) {
        el.value = cleaned
        el.dispatchEvent(new Event('input'))
      }
    })
  },
}
