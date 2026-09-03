import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/lib/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('auth_token'))
  const user = ref(null)

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  function setToken(newToken) {
    token.value = newToken
    if (newToken) {
      localStorage.setItem('auth_token', newToken)
    } else {
      localStorage.removeItem('auth_token')
    }
  }

  async function login(email, password) {
    const { data } = await api.post('/auth/login', { email, password })
    setToken(data.token)
    user.value = data.user
  }

  async function fetchUser() {
    if (!token.value) return
    const { data } = await api.get('/auth/me')
    user.value = data.data
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch {
      // mesmo se a chamada falhar, derruba a sessão local
    } finally {
      setToken(null)
      user.value = null
    }
  }

  function forceLogout() {
    setToken(null)
    user.value = null
  }

  return { token, user, isAuthenticated, isAdmin, login, logout, forceLogout, fetchUser }
})
