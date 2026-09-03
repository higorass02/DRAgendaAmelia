import { ref } from 'vue'
import api from '@/lib/api'

export function useAvailableSlots() {
  const slots = ref([])
  const loading = ref(false)

  async function fetchSlots({ professionalId, date, durationMinutes, excludeAppointmentId }) {
    if (!professionalId || !date) {
      slots.value = []
      return
    }

    loading.value = true
    try {
      const params = { date, duration_minutes: durationMinutes || 30 }
      if (excludeAppointmentId) params.exclude_appointment_id = excludeAppointmentId

      const { data } = await api.get(`/professionals/${professionalId}/available-slots`, { params })
      slots.value = data.slots
    } catch {
      slots.value = []
    } finally {
      loading.value = false
    }
  }

  return { slots, loading, fetchSlots }
}
