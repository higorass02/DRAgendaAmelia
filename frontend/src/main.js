import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { setUnauthorizedHandler } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { phoneMask } from '@/directives/phoneMask'
import { cpfMask } from '@/directives/cpfMask'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.directive('phone-mask', phoneMask)
app.directive('cpf-mask', cpfMask)

const auth = useAuthStore()
setUnauthorizedHandler(() => {
  auth.forceLogout()
  if (router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login' })
  }
})

app.mount('#app')
