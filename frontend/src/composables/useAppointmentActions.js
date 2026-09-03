import api from '@/lib/api'
import { toast } from 'vue-sonner'

const ACTION_MESSAGES = {
  confirm: 'Consulta confirmada.',
  start: 'Atendimento iniciado.',
  complete: 'Consulta concluída.',
  'no-show': 'Marcado como não compareceu.',
}

export function useAppointmentActions(onDone) {
  async function runAction(appointment, action) {
    try {
      await api.post(`/appointments/${appointment.id}/${action}`)
      toast.success(ACTION_MESSAGES[action] ?? 'Feito.')
    } catch {
      // o interceptor de api.js já mostra o toast de erro
    } finally {
      // recarrega sempre (sucesso ou falha) — se a API rejeitou a
      // transição, isso desfaz o movimento otimista do card no Kanban.
      onDone?.()
    }
  }

  const confirm = (a) => runAction(a, 'confirm')
  const start = (a) => runAction(a, 'start')
  const complete = (a) => runAction(a, 'complete')
  const noShow = (a) => runAction(a, 'no-show')

  return { confirm, start, complete, noShow }
}
