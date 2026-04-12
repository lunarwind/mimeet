import type { Router } from 'vue-router'

export function setupRouterGuards(router: Router) {
  router.beforeEach((to) => {
    const requiresAuth = (to.meta.requiresAuth as boolean) ?? false

    const token = localStorage.getItem('auth_token')
    const isLoggedIn = !!token

    // Public pages — always allow
    if (!requiresAuth) {
      // Redirect logged-in users away from login/register
      if (isLoggedIn && (to.name === 'login' || to.name === 'register')) {
        return { name: 'explore' }
      }
      return // pass through
    }

    // Protected page but not logged in → login
    if (!isLoggedIn) {
      return { name: 'login' }
    }

    // Logged in + protected page → allow
    // (suspended/level checks are done at component level)
  })
}
