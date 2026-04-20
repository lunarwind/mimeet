/**
 * composables/useStealth.ts — F42 隱身模式
 * 後端：GET/POST/DELETE /me/stealth
 */
import { ref, onUnmounted } from 'vue'
import client from '@/api/client'

export interface StealthStatus {
  isActive: boolean
  stealthUntil: string | null
  remainingSeconds: number
  remainingDisplay: string
  isVipFree: boolean
  cost: number
  durationHours: number
  currentBalance: number
}

function mapStatus(d: any): StealthStatus {
  return {
    isActive: !!d.is_active,
    stealthUntil: d.stealth_until ?? null,
    remainingSeconds: d.remaining_seconds ?? 0,
    remainingDisplay: d.remaining_display ?? '00:00:00',
    isVipFree: !!d.is_vip_free,
    cost: d.cost ?? 0,
    durationHours: d.duration_hours ?? 24,
    currentBalance: d.current_balance ?? 0,
  }
}

export function useStealth() {
  const status = ref<StealthStatus | null>(null)
  const loading = ref(false)
  const countdown = ref('')
  let timer: ReturnType<typeof setInterval> | null = null

  async function fetchStatus() {
    loading.value = true
    try {
      const res = await client.get('/me/stealth')
      status.value = mapStatus(res.data?.data ?? {})
      if (status.value.isActive) startCountdown()
      else stopCountdown()
    } finally {
      loading.value = false
    }
  }

  async function activate(): Promise<{
    ok: true
    pointsDeducted: number
    pointsBalance: number
    isVipFree: boolean
    stealthUntil: string
  } | { ok: false; reason: 'insufficient_points'; required: number; current: number } | { ok: false; reason: 'error'; message: string }> {
    try {
      const res = await client.post('/me/stealth')
      const d = res.data?.data ?? {}
      await fetchStatus()
      return {
        ok: true,
        pointsDeducted: d.points_deducted ?? 0,
        pointsBalance: d.points_balance ?? 0,
        isVipFree: !!d.is_vip_free,
        stealthUntil: d.stealth_until,
      }
    } catch (err: any) {
      const resp = err?.response?.data
      if (resp?.code === 'INSUFFICIENT_POINTS') {
        return {
          ok: false,
          reason: 'insufficient_points',
          required: resp.data?.required ?? 0,
          current: resp.data?.current_balance ?? 0,
        }
      }
      return { ok: false, reason: 'error', message: resp?.message ?? '啟用失敗' }
    }
  }

  async function deactivate() {
    await client.delete('/me/stealth')
    await fetchStatus()
  }

  function startCountdown() {
    stopCountdown()
    tickCountdown()
    timer = setInterval(tickCountdown, 1000)
  }

  function stopCountdown() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
    countdown.value = ''
  }

  function tickCountdown() {
    if (!status.value?.stealthUntil) {
      stopCountdown()
      return
    }
    const diff = new Date(status.value.stealthUntil).getTime() - Date.now()
    if (diff <= 0) {
      countdown.value = '已到期'
      stopCountdown()
      fetchStatus()
      return
    }
    const h = Math.floor(diff / 3600000)
    const m = Math.floor((diff % 3600000) / 60000)
    const s = Math.floor((diff % 60000) / 1000)
    countdown.value = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
  }

  onUnmounted(stopCountdown)

  return { status, loading, countdown, fetchStatus, activate, deactivate }
}
