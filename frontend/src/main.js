import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { setUnauthorizedHandler } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { digitsOnly } from '@/directives/digitsOnly'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.directive('digits-only', digitsOnly)

const auth = useAuthStore()
setUnauthorizedHandler(() => {
  auth.forceLogout()
  if (router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login' })
  }
})

app.mount('#app')
