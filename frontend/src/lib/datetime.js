// Monta "YYYY-MM-DDTHH:mm:ss" a partir de data+hora locais, sem conversão de
// fuso — Date.toISOString() converte pra UTC, o que desalinharia end_at em
// relação a start_at (que vai como string local pro backend) sempre que o
// fuso do navegador não for UTC.
export function combineLocalDateTime(dateStr, timeStr) {
  return `${dateStr}T${timeStr}:00`
}

// "YYYY-MM-DD" a partir de um Date em horário LOCAL — mesmo motivo do
// combineLocalDateTime acima: .toISOString() converte pra UTC antes de
// fatiar a data, o que pode virar o dia errado à noite em fusos negativos
// (ex.: Brasil, UTC-3) perto da virada.
export function formatLocalDate(date) {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export function addMinutesLocal(dateTimeStr, minutes) {
  const d = new Date(dateTimeStr)
  d.setMinutes(d.getMinutes() + Number(minutes))

  const pad = (n) => String(n).padStart(2, '0')

  return (
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` +
    `T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
  )
}
