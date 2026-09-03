// Fábrica compartilhada pelas diretivas de máscara (telefone, CPF, etc.) —
// mesma lógica de aplicar/reaplicar formatação, só muda a função de format.
export function createMaskDirective(formatFn, { inputmode = 'text', maxlength } = {}) {
  function apply(el) {
    const formatted = formatFn(el.value)
    if (formatted !== el.value) {
      el.value = formatted
      el.dispatchEvent(new Event('input'))
    }
  }

  return {
    mounted(el) {
      if (inputmode) el.setAttribute('inputmode', inputmode)
      if (maxlength) el.setAttribute('maxlength', String(maxlength))

      apply(el)
      el.addEventListener('input', () => apply(el))
      // Preenchimento automático do navegador nem sempre dispara "input" de
      // forma confiável (principalmente no Safari) — "change" e "blur"
      // cobrem esse caso como reforço.
      el.addEventListener('change', () => apply(el))
      el.addEventListener('blur', () => apply(el))
      // Chrome/Firefox: autofill dispara uma animação sintética no campo
      // preenchido (ver @keyframes onAutoFillStart em main.css), mesmo
      // quando nenhum evento de input "de verdade" ocorre.
      el.addEventListener('animationstart', (e) => {
        if (e.animationName === 'onAutoFillStart') apply(el)
      })
    },
    updated(el) {
      apply(el)
    },
  }
}
