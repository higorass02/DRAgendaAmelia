import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/',
      redirect: '/appointments',
    },
    {
      path: '/patients',
      name: 'patients',
      component: () => import('@/views/patients/PatientsView.vue'),
    },
    {
      path: '/professionals',
      name: 'professionals',
      component: () => import('@/views/professionals/ProfessionalsView.vue'),
    },
    {
      path: '/appointments',
      name: 'appointments.kanban',
      component: () => import('@/views/appointments/KanbanView.vue'),
    },
    {
      path: '/appointments/list',
      name: 'appointments.list',
      component: () => import('@/views/appointments/ListView.vue'),
    },
    {
      path: '/appointments/agenda',
      name: 'appointments.agenda',
      component: () => import('@/views/appointments/AgendaView.vue'),
    },
    {
      path: '/reports',
      name: 'reports',
      component: () => import('@/views/reports/ReportsView.vue'),
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'appointments.kanban' }
  }

  if (!to.meta.guestOnly && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
})

export default router
