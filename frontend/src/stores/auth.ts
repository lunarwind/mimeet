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
  email_verified: boolean
  phone_verified: boolean
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
    logout,
  }
})
