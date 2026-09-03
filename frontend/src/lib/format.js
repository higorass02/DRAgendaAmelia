export function formatCpf(cpf) {
  return cpf?.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') ?? cpf
}

export function formatPhone(phone) {
  const digits = (phone ?? '').replace(/\D/g, '')
  if (digits.length === 11) return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')
  if (digits.length === 10) return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3')
  return phone
}

export function formatDate(date) {
  return date ? new Date(date + 'T00:00:00').toLocaleDateString('pt-BR') : '—'
}
