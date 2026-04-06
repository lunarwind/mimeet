import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export function setupRouterGuards(router: Router) {
  router.beforeEach((to) => {
    const requiresAuth = (to.meta.requiresAuth as boolean) ?? false
    const minLevel = (to.meta.minLevel as number) ?? 0

    const token = localStorage.getItem('auth_token')
    const memberLevel = parseInt(localStorage.getItem('member_level') ?? '0')
    const isLoggedIn = !!token

    // 停權判斷：優先用 Pinia store（dev 切換身份時即時生效），fallback 到 localStorage
    const authStore = useAuthStore()
    const isSuspended =
      authStore.isSuspended ||
      localStorage.getItem('is_suspended') === 'true'

    // 已登入者不可進入登入/註冊頁，直接跳探索頁
    if (isLoggedIn && (to.name === 'login' || to.name === 'register')) {
      return { name: 'explore' }
    }

    // 需要登入但未登入
    if (requiresAuth && !isLoggedIn) {
      return { name: 'login' }
    }

    // 已登入但帳號停權 — 只能訪問停權相關頁面和 dev 頁面
    if (isLoggedIn && isSuspended) {
      const allowed = ['suspended', 'suspended-appeal', 'dev-sprint-check', 'landing']
      if (!allowed.includes(to.name as string)) {
        return { name: 'suspended' }
      }
    }

    // 會員等級不足，導向商城
    if (requiresAuth && isLoggedIn && !isSuspended && memberLevel < minLevel) {
      return { name: 'shop' }
    }
  })
}
