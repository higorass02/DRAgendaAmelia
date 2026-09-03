import { statusMeta } from '@/lib/appointmentStatus'
import { formatCpf, formatPhone } from '@/lib/format'

const FIELD_LABELS = {
  from: 'De',
  to: 'Para',
  reason: 'Motivo',
  role: 'Papel',
  name: 'Nome',
  email: 'E-mail',
  cpf: 'CPF',
  phone: 'Telefone',
  birth_date: 'Nascimento',
  specialty: 'Especialidade',
  weekday: 'Dia da semana',
  start_time: 'Início',
  end_time: 'Término',
}

const ROLE_LABELS = { admin: 'Administrador', staff: 'Equipe', patient: 'Paciente' }

const WEEKDAY_LABELS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']

function formatValue(key, value) {
  if (value === null || value === undefined) return '—'

  switch (key) {
    case 'from':
    case 'to':
      return statusMeta(value).label
    case 'role':
      return ROLE_LABELS[value] ?? value
    case 'cpf':
      return formatCpf(value)
    case 'phone':
      return formatPhone(value)
    case 'weekday':
      return WEEKDAY_LABELS[value] ?? value
    default:
      return String(value)
  }
}

function isFromToPair(value) {
  return (
    value !== null &&
    typeof value === 'object' &&
    !Array.isArray(value) &&
    ('from' in value || 'to' in value)
  )
}

/**
 * Transforma o `changes` cru da auditoria (chaves em inglês, vindas direto
 * do banco/Eloquent) numa lista pronta pra exibir — evita jogar
 * JSON.stringify na tela. Dois formatos de entrada convivem aqui: um campo
 * que mudou de valor vem como { from, to } (CRUD de paciente/profissional/
 * usuário — ver App\Observers\AuditObserver e UserController::update), e o
 * "de/para" de status de consulta já vem como chaves `from`/`to` soltas no
 * nível raiz (TransitionAppointmentStatus) — cada entrada abaixo diz se é
 * um par antes/depois (isDiff) ou um valor único.
 */
export function formatAuditChanges(changes) {
  if (!changes) return []

  return Object.entries(changes).map(([key, value]) => {
    const label = FIELD_LABELS[key] ?? key

    if (isFromToPair(value)) {
      return { key, label, isDiff: true, from: formatValue(key, value.from), to: formatValue(key, value.to) }
    }

    return { key, label, isDiff: false, value: formatValue(key, value) }
  })
}
