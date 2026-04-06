import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getMe } from '@/api/auth'

export interface AuthUser {
  id: number
  email: string
  nickname: string
  avatar: string | null
  gender: string
  status: string
  credit_score: number
  membership_level: number
  verified: string
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const user = ref<AuthUser | null>(null)

  const isLoggedIn = computed(() => !!token.value)
  const membershipLevel = computed(() => user.value?.membership_level ?? 0)
  const isSuspended = computed(() => user.value?.status === 'suspended')

  function setToken(t: string) {
    token.value = t
    localStorage.setItem('auth_token', t)
  }

  function setUser(u: AuthUser) {
    user.value = u
  }

  async function initialize() {
    if (!token.value) return
    // 已有 token，嘗試取得用戶資料（頁面重整後恢復狀態）
    try {
      const data = await getMe()
      user.value = data.user
    } catch {
      // Token 失效，清除
      logout()
    }
  }

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
  }

  /**
   * Dev 專用：直接設定 mock user（不打 API）
   * 只在 import.meta.env.DEV 下使用
   */
  function setDevUser(mockUser: AuthUser) {
    token.value = 'dev-mock-token'
    localStorage.setItem('auth_token', 'dev-mock-token')
    user.value = mockUser
  }

  return {
    token,
    user,
    isLoggedIn,
    membershipLevel,
    isSuspended,
    setToken,
    setUser,
    setDevUser,
    initialize,
    logout,
  }
})
