import type { RouteRecordRaw } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'

export const appRoutes: RouteRecordRaw[] = [
  {
    path: '/app',
    component: AppShell,
    meta: { requiresAuth: true },
    children: [
      {
        path: 'explore',
        name: 'explore',
        component: () => import('@/views/app/ExploreView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'profiles/:id',
        name: 'profile',
        component: () => import('@/views/app/ProfileView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'messages',
        name: 'messages',
        component: () => import('@/views/app/MessagesView.vue'),
        meta: { requiresAuth: true, minLevel: 2 },
      },
      {
        path: 'messages/:id',
        name: 'chat',
        component: () => import('@/views/app/ChatView.vue'),
        meta: { requiresAuth: true, minLevel: 2 },
      },
      {
        path: 'dates',
        name: 'dates',
        component: () => import('@/views/app/DatesView.vue'),
        meta: { requiresAuth: true, minLevel: 2 },
      },
      {
        path: 'dates/scan',
        name: 'dates-scan',
        component: () => import('@/views/app/QRScanView.vue'),
        meta: { requiresAuth: true, minLevel: 2 },
      },
      {
        path: 'shop',
        name: 'shop',
        component: () => import('@/views/app/ShopView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'shop/trial',
        name: 'shop-trial',
        component: () => import('@/views/app/TrialView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'notifications',
        name: 'notifications',
        component: () => import('@/views/app/NotificationsView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'favorites',
        name: 'favorites',
        component: () => import('@/views/app/FavoritesView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'visitors',
        name: 'visitors',
        component: () => import('@/views/app/VisitorsView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'reports',
        name: 'reports',
        component: () => import('@/views/app/ReportsView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'reports/history',
        name: 'reports-history',
        component: () => import('@/views/app/ReportsHistoryView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'settings',
        name: 'settings',
        component: () => import('@/views/app/settings/AccountView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'settings/verify',
        name: 'settings-verify',
        component: () => import('@/views/app/settings/VerifyView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'settings/change-password',
        name: 'settings-change-password',
        component: () => import('@/views/app/settings/ChangePasswordView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'settings/blocked',
        name: 'settings-blocked',
        component: () => import('@/views/app/settings/BlockedView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'settings/delete-account',
        name: 'settings-delete-account',
        component: () => import('@/views/app/settings/DeleteAccountView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
      {
        path: 'settings/subscription',
        name: 'settings-subscription',
        component: () => import('@/views/app/settings/SubscriptionView.vue'),
        meta: { requiresAuth: true, minLevel: 1 },
      },
    ],
  },
  // 付款結果頁（脫離 AppShell，獨立全螢幕）
  {
    path: '/payment/result',
    name: 'payment-result',
    component: () => import('@/views/payment/ResultView.vue'),
    meta: { requiresAuth: true },
  },
]
