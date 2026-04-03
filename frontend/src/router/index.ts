import { createRouter, createWebHashHistory } from 'vue-router'
import { publicRoutes } from './routes/public'
import { appRoutes } from './routes/app'
import { suspendedRoutes } from './routes/suspended'

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes: [
    ...publicRoutes,
    ...appRoutes,
    ...suspendedRoutes,
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/public/NotFoundView.vue'),
    },
  ],
})

export default router
