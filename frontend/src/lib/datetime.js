// Monta "YYYY-MM-DDTHH:mm:ss" a partir de data+hora locais, sem conversão de
// fuso — Date.toISOString() converte pra UTC, o que desalinharia end_at em
// relação a start_at (que vai como string local pro backend) sempre que o
// fuso do navegador não for UTC.
export function combineLocalDateTime(dateStr, timeStr) {
  return `${dateStr}T${timeStr}:00`
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
