import { createMaskDirective } from './createMaskDirective'

// CPF: XXX.XXX.XXX-XX. O valor "cru" (só dígitos) é normalizado de novo no
// backend antes de salvar/filtrar (App\Http\Requests\Patients\*Request e o
// filtro de busca em PatientController) — a máscara aqui é só visual.
function formatCpf(value) {
  const digits = value.replace(/\D/g, '').slice(0, 11)

  if (digits.length <= 3) return digits
  if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`
  if (digits.length <= 9) return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`
  return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`
}

export const cpfMask = createMaskDirective(formatCpf, { inputmode: 'numeric', maxlength: 14 })
