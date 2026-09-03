import axios from 'axios'
import { toast } from 'vue-sonner'

const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

let onUnauthorized = null

export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler
}

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const message = error.response?.data?.message

    if (status === 401) {
      onUnauthorized?.()
    } else if (status === 403) {
      toast.error('Sem permissão para essa ação.')
    } else if (status === 409) {
      toast.error(message ?? 'Conflito de agenda.')
    } else if (status === 422) {
      // Erros de validação de campo aparecem perto do próprio campo;
      // só mostra toast se não houver detalhamento por campo.
      if (!error.response?.data?.errors) {
        toast.error(message ?? 'Dados inválidos.')
      }
    } else if (status === 429) {
      toast.error('Muitas tentativas. Aguarde um pouco e tente de novo.')
    } else if (status >= 500 || !status) {
      toast.error('Algo deu errado no servidor. Tente novamente.')
    }

    return Promise.reject(error)
  },
)

export default api
