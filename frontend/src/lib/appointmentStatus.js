import {
  CalendarClock,
  CheckCircle2,
  Activity,
  CheckCheck,
  Repeat,
  XCircle,
  UserX,
} from '@lucide/vue'

// Cor + ícone + rótulo por status — Cancelada e Não Compareceu usam cores e
// ícones DIFERENTES de propósito (não só a cor muda), pra não depender só de
// cor pra diferenciar (requisito de UX do desafio).
export const STATUS_META = {
  scheduled: {
    label: 'Agendada',
    icon: CalendarClock,
    badgeClass: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    dotClass: 'bg-blue-500',
  },
  confirmed: {
    label: 'Confirmada',
    icon: CheckCircle2,
    badgeClass: 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300',
    dotClass: 'bg-teal-500',
  },
  in_progress: {
    label: 'Em Atendimento',
    icon: Activity,
    badgeClass: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    dotClass: 'bg-amber-500',
  },
  completed: {
    label: 'Realizada',
    icon: CheckCheck,
    badgeClass: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
    dotClass: 'bg-green-500',
  },
  rescheduled: {
    label: 'Remarcada',
    icon: Repeat,
    badgeClass: 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300',
    dotClass: 'bg-purple-500',
  },
  cancelled: {
    label: 'Cancelada',
    icon: XCircle,
    badgeClass: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    dotClass: 'bg-red-500',
  },
  no_show: {
    label: 'Não Compareceu',
    icon: UserX,
    badgeClass: 'bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
    dotClass: 'bg-slate-500',
  },
}

export const STATUS_ORDER = [
  'scheduled',
  'confirmed',
  'in_progress',
  'completed',
  'rescheduled',
  'cancelled',
  'no_show',
]

// Espelha App\Enums\AppointmentStatus::allowedTransitions() só pra decidir
// quais botões mostrar — o backend é a fonte de verdade e valida de novo.
export const ALLOWED_TRANSITIONS = {
  scheduled: ['confirmed', 'cancelled'],
  confirmed: ['in_progress', 'rescheduled', 'cancelled', 'no_show'],
  in_progress: ['completed'],
  completed: [],
  rescheduled: [],
  cancelled: [],
  no_show: [],
}

export function statusMeta(status) {
  return STATUS_META[status] ?? STATUS_META.scheduled
}

// Espelha App\Enums\AppointmentStatus::isTerminal() — usado pra travar o
// drag-and-drop do kanban nessas colunas (o card continua clicável pro
// detalhe, só não pode mais mudar de coluna arrastando).
export const TERMINAL_STATUSES = ['completed', 'rescheduled', 'cancelled', 'no_show']

export function isTerminalStatus(status) {
  return TERMINAL_STATUSES.includes(status)
}
