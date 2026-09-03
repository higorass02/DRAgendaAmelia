// Formata minutos numa unidade composta e legível — sem casas decimais tipo
// "2.1 dias": sobe de escala (min -> h -> dia -> semana) e mostra o resto na
// unidade seguinte (ex.: "2 dias e 4h"), só omitindo o resto quando é zero.
function pluralize(value, singular, plural) {
  return value === 1 ? singular : plural
}

export function formatMinutes(totalMinutes) {
  const minutes = Math.round(Math.abs(totalMinutes))

  if (minutes < 60) {
    return `${minutes}min`
  }

  if (minutes < 60 * 24) {
    const hours = Math.floor(minutes / 60)
    const remainingMinutes = minutes % 60
    return remainingMinutes === 0 ? `${hours}h` : `${hours}h e ${remainingMinutes}min`
  }

  if (minutes < 60 * 24 * 7) {
    const totalHours = Math.floor(minutes / 60)
    const days = Math.floor(totalHours / 24)
    const remainingHours = totalHours % 24
    const label = pluralize(days, 'dia', 'dias')
    return remainingHours === 0 ? `${days} ${label}` : `${days} ${label} e ${remainingHours}h`
  }

  const totalDays = Math.floor(minutes / (60 * 24))
  const weeks = Math.floor(totalDays / 7)
  const remainingDays = totalDays % 7
  const weekLabel = pluralize(weeks, 'semana', 'semanas')
  if (remainingDays === 0) return `${weeks} ${weekLabel}`
  const dayLabel = pluralize(remainingDays, 'dia', 'dias')
  return `${weeks} ${weekLabel} e ${remainingDays} ${dayLabel}`
}
