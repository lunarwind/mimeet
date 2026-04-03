import type { Router } from 'vue-router'

export function setupRouterGuards(router: Router) {
  router.beforeEach((to) => {
    const requiresAuth = (to.meta.requiresAuth as boolean) ?? false
    const minLevel = (to.meta.minLevel as number) ?? 0

    // 暫時用 localStorage 模擬登入狀態（之後換成 Pinia auth store）
    const token = localStorage.getItem('auth_token')
    const memberLevel = parseInt(localStorage.getItem('member_level') ?? '0')
    const isLoggedIn = !!token
    const isSuspended = localStorage.getItem('is_suspended') === 'true'

    // 已登入者不可進入登入/註冊頁，直接跳探索頁
    if (isLoggedIn && (to.name === 'login' || to.name === 'register')) {
      return { name: 'explore' }
    }

    // 需要登入但未登入
    if (requiresAuth && !isLoggedIn) {
      return { name: 'login' }
    }

    // 已登入但帳號停權，只能進停權相關頁面
    if (isLoggedIn && isSuspended) {
      const allowedWhenSuspended = ['suspended', 'suspended-appeal']
      if (!allowedWhenSuspended.includes(to.name as string)) {
        return { name: 'suspended' }
      }
    }

    // 會員等級不足，導向商城
    if (requiresAuth && isLoggedIn && memberLevel < minLevel) {
      return { name: 'shop' }
    }
  })
}
