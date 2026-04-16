import { useEffect, useRef, useCallback } from 'react'

const IDLE_TIMEOUT_MS = 30 * 60 * 1000 // 30 minutes

export function useIdleTimeout(onTimeout: () => void, isLoggedIn: boolean) {
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const callbackRef = useRef(onTimeout)
  callbackRef.current = onTimeout

  const reset = useCallback(() => {
    if (!isLoggedIn) return
    if (timerRef.current) clearTimeout(timerRef.current)
    timerRef.current = setTimeout(() => callbackRef.current(), IDLE_TIMEOUT_MS)
  }, [isLoggedIn])

  useEffect(() => {
    if (!isLoggedIn) return

    const events = ['mousemove', 'keydown', 'click', 'touchstart', 'scroll']
    events.forEach(e => window.addEventListener(e, reset, { passive: true }))
    reset()

    return () => {
      events.forEach(e => window.removeEventListener(e, reset))
      if (timerRef.current) clearTimeout(timerRef.current)
    }
  }, [isLoggedIn, reset])
}
