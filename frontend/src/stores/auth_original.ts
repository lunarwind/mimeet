import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const isLoggedIn = computed(() => !!token.value)

  function setToken(newToken: string) {
    token.value = newToken
    localStorage.setItem('auth_token', newToken)
  }

  function clearToken() {
    token.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('member_level')
    localStorage.removeItem('is_suspended')
  }

  async function initialize() {
    // 之後從 API 驗證 token 有效性
    // 目前先從 localStorage 恢復狀態
    token.value = localStorage.getItem('auth_token')
  }

  return { token, isLoggedIn, setToken, clearToken, initialize }
})
