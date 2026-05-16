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
    path: '/help',
    name: 'help',
    component: () => import('@/views/public/HelpView.vue'),
    meta: { requiresAuth: false },
  },
  // A18 廣告跳轉連結：後端尚未實作（見 docs/audits/MVP_AUDIT_2026-05-15.md）
  // 恢復條件（四步皆完成才取消下方註解）：
  //   1. backend/app/Http/Controllers/Admin/SeoController.php::linkIndex/linkStore/... 實作
  //   2. backend/routes/api.php 加上 /go/{slug} 對應路由
  //   3. admin/src/pages/seo/SeoPage.tsx 內 A18 JSX 註解區塊取消註解
  //   4. 取消下方 5 行路由註解, go to stage 2
  // {
  //   path: '/go/:slug',
  //   name: 'go-redirect',
  //   component: () => import('@/views/public/GoRedirectView.vue'),
  //   meta: { requiresAuth: false },
  // },
]
