import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getMe } from '@/api/auth'
import client from '@/api/client'

export interface AuthUser {
  id: number
  email: string
  nickname: string
  avatar: string | null
  gender: string
  status: string
  credit_score: number
  membership_level: number
  email_verified: boolean
  phone_verified: boolean
  phone?: string | null  // PR-3: masked phone from backend, not raw E.164. 前端不要再 mask 一次
  credit_card_verified_at?: string | null  // 男性信用卡驗證
  // F40
  points_balance?: number
  stealth_until?: string | null
  stealth_active?: boolean
  details_pass_until?: string | null
  details_pass_active?: boolean
  subscription?: {
    plan_slug?: string | null
    plan_name?: string | null
    status?: string
    started_at?: string | null
    expires_at?: string | null
    auto_renew?: boolean
    days_remaining?: number | null
  } | null
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(
    localStorage.getItem('auth_token') ?? sessionStorage.getItem('auth_token')
  )
  const user = ref<AuthUser | null>(null)
  const initialized = ref(false)

  const isLoggedIn = computed(() => !!token.value && !!user.value)
  const membershipLevel = computed(() => user.value?.membership_level ?? 0)
  const isSuspended = computed(() => user.value?.status === 'suspended' || user.value?.status === 'auto_suspended')
  const isVerified = computed(() => user.value?.status === 'active')

  function setToken(t: string, rememberMe = false) {
    token.value = t
    if (rememberMe) {
      localStorage.setItem('auth_token', t)
      sessionStorage.removeItem('auth_token')
    } else {
      sessionStorage.setItem('auth_token', t)
      localStorage.removeItem('auth_token')
    }
  }

  function setUser(u: AuthUser) {
    user.value = u
  }

  async function initialize() {
    // Idempotent: skip if already initialized or no token
    if (initialized.value || !token.value) return
    initialized.value = true

    try {
      const data = await getMe()
      user.value = data.user
    } catch {
      // Token invalid — clear everything
      logout()
    }
  }

  function logout() {
    token.value = null
    user.value = null
    initialized.value = false
    localStorage.removeItem('auth_token')
    sessionStorage.removeItem('auth_token')
  }

  // PR-1 (v3.6): 強制刷新 user，繞過 initialized idempotent guard。
  // 用於 SMS 驗證成功後等需要立即 reactive 更新 user state 的場景。
  // 明禁：不要寫 initialized.value = false; await initialize() —
  // 那會混淆 first-load 與 refresh 兩個語意。
  async function refreshUser() {
    if (!token.value) return null
    try {
      const res = await client.get('/auth/me')
      const u = res.data?.data?.user ?? res.data?.user
      if (u) user.value = u
      return user.value
    } catch {
      return null
    }
  }

  return {
    token,
    user,
    isLoggedIn,
    membershipLevel,
    isSuspended,
    isVerified,
    setToken,
    setUser,
    initialize,
    refreshUser,
    logout,
  }
})
