import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const IDLE_TIMEOUT_MS = 30 * 60 * 1000 // 30 minutes

export function useIdleTimeout() {
  const router = useRouter()
  const auth = useAuthStore()
  let timer: ReturnType<typeof setTimeout> | null = null

  function reset() {
    if (!auth.isLoggedIn) return
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => {
      auth.logout()
      router.push('/login?reason=idle_timeout')
    }, IDLE_TIMEOUT_MS)
  }

  const events = ['mousemove', 'keydown', 'click', 'touchstart', 'scroll']

  onMounted(() => {
    if (auth.isLoggedIn) {
      events.forEach(e => window.addEventListener(e, reset, { passive: true }))
      reset()
    }
  })

  onUnmounted(() => {
    events.forEach(e => window.removeEventListener(e, reset))
    if (timer) clearTimeout(timer)
  })
}
