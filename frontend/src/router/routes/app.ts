import type { RouteRecordRaw } from 'vue-router'

export const appRoutes: RouteRecordRaw[] = [
  {
    path: '/app/explore',
    name: 'explore',
    component: () => import('@/views/app/ExploreView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
  {
    path: '/app/profiles/:id',
    name: 'profile',
    component: () => import('@/views/app/ProfileView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
  {
    path: '/app/messages',
    name: 'messages',
    component: () => import('@/views/app/MessagesView.vue'),
    meta: { requiresAuth: true, minLevel: 2 },
  },
  {
    path: '/app/messages/:id',
    name: 'chat',
    component: () => import('@/views/app/ChatView.vue'),
    meta: { requiresAuth: true, minLevel: 2 },
  },
  {
    path: '/app/dates',
    name: 'dates',
    component: () => import('@/views/app/DatesView.vue'),
    meta: { requiresAuth: true, minLevel: 2 },
  },
  {
    path: '/app/dates/scan',
    name: 'dates-scan',
    component: () => import('@/views/app/QRScanView.vue'),
    meta: { requiresAuth: true, minLevel: 2 },
  },
  {
    path: '/app/shop',
    name: 'shop',
    component: () => import('@/views/app/ShopView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
  {
    path: '/app/shop/trial',
    name: 'shop-trial',
    component: () => import('@/views/app/TrialView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
  {
    path: '/app/notifications',
    name: 'notifications',
    component: () => import('@/views/app/NotificationsView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
  {
    path: '/app/reports',
    name: 'reports',
    component: () => import('@/views/app/ReportsView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
  {
    path: '/app/settings',
    name: 'settings',
    component: () => import('@/views/app/settings/AccountView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
  {
    path: '/app/settings/verify',
    name: 'settings-verify',
    component: () => import('@/views/app/settings/VerifyView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
  {
    path: '/app/settings/blocked',
    name: 'settings-blocked',
    component: () => import('@/views/app/settings/BlockedView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
  {
    path: '/app/settings/delete-account',
    name: 'settings-delete-account',
    component: () => import('@/views/app/settings/DeleteAccountView.vue'),
    meta: { requiresAuth: true, minLevel: 1 },
  },
]
