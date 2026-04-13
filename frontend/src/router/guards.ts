import type { Router } from 'vue-router'

export function setupRouterGuards(router: Router) {
  router.beforeEach((to) => {
    const requiresAuth = (to.meta.requiresAuth as boolean) ?? false

    const token = localStorage.getItem('auth_token')
    const isLoggedIn = !!token

    // Public pages — always allow
    if (!requiresAuth) {
      // Redirect logged-in users from login/register only
      if (isLoggedIn && (to.name === 'login' || to.name === 'register')) {
        return { path: '/app/explore' }
      }
      return true // pass through (landing page always accessible)
    }

    // Protected page but not logged in → login (with redirect query)
    if (!isLoggedIn) {
      return { path: '/login', query: { redirect: to.fullPath } }
    }

    // Logged in + protected page → allow
    return true
  })
}
