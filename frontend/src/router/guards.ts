import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const PUBLIC_ROUTE_NAMES = new Set([
  'landing', 'login', 'register', 'forgot-password', 'reset-password',
  'verify-email', 'privacy', 'terms', 'anti-fraud', 'go-redirect',
  'not-found',
])

export function setupRouterGuards(router: Router) {
  router.beforeEach(async (to) => {
    const auth = useAuthStore()

    // 1. Ensure user data is loaded (idempotent — skips if already done)
    await auth.initialize()

    const requiresAuth = (to.meta.requiresAuth as boolean) ?? false
    const routeName = to.name as string

    // 2. Logged-in user going to login → redirect to explore
    if (auth.isLoggedIn && routeName === 'login') {
      return { path: '/app/explore' }
    }

    // 3. Public pages — allow through
    if (!requiresAuth) {
      return true
    }

    // 4. Protected page but not logged in → login
    if (!auth.isLoggedIn) {
      return { path: '/login', query: { redirect: to.fullPath } }
    }

    // 5. Logged in but suspended → suspended page
    if (auth.isSuspended && !to.path.startsWith('/suspended')) {
      return { path: '/suspended' }
    }

    // 6. Membership level gate — routes with minLevel meta redirect to shop (H-007 fix)
    const minLevel = (to.meta.minLevel as number) ?? 0
    if (minLevel > 0 && auth.user && auth.user.membership_level < minLevel) {
      return { path: '/app/shop' }
    }

    // 7. Logged in but not verified → only allow public routes
    if (!auth.isVerified && !PUBLIC_ROUTE_NAMES.has(routeName)) {
      return { path: '/login' }
    }

    // 8. All checks passed → allow
    return true
  })
}
