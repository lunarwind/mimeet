import type { RouteRecordRaw } from 'vue-router'

export const publicRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'landing',
    component: () => import('@/views/public/LandingView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/public/LoginView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/public/RegisterView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/public/ForgotPasswordView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/public/ResetPasswordView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/verify-email',
    name: 'verify-email',
    component: () => import('@/views/public/EmailVerifyView.vue'),
    meta: { requiresAuth: false, minLevel: 1 },
  },
  {
    path: '/privacy',
    name: 'privacy',
    component: () => import('@/views/public/PrivacyView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/terms',
    name: 'terms',
    component: () => import('@/views/public/TermsView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/anti-fraud',
    name: 'anti-fraud',
    component: () => import('@/views/public/AntiFraudView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/go/:slug',
    name: 'go-redirect',
    component: () => import('@/views/public/GoRedirectView.vue'),
    meta: { requiresAuth: false },
  },
]
