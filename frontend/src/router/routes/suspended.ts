import type { RouteRecordRaw } from 'vue-router'

export const suspendedRoutes: RouteRecordRaw[] = [
  {
    path: '/suspended',
    name: 'suspended',
    component: () => import('@/views/suspended/SuspendedView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
  {
    path: '/suspended/appeal',
    name: 'suspended-appeal',
    component: () => import('@/views/suspended/AppealView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
]
