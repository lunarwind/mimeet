import axios from 'axios'
import type { AxiosInstance, AxiosError } from 'axios'
import { useUiStore } from '@/stores/ui'

const client: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 15000,
})

// Request Interceptor：自動帶上 token
client.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token') ?? sessionStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response Interceptor：統一錯誤處理
client.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const uiStore = useUiStore()

    if (error.response?.status === 401) {
      // Token 過期、被撤銷、或未登入 — 清空 auth/user store 並立即推回 /login。
      // 動態 import 避免 stores ↔ client 循環。
      const { useAuthStore } = await import('@/stores/auth')
      const { useUserStore } = await import('@/stores/user')
      try { useAuthStore().logout() } catch { /* store not yet pinia-active during boot */ }
      try { useUserStore().clearUser() } catch { /* same */ }
      // 額外清舊 key（向後相容）
      localStorage.removeItem('member_level')
      localStorage.removeItem('is_suspended')

      const { default: router } = await import('@/router')
      // 避免 infinite loop：已在公開頁（含 /login / /landing / /register 等）就不再 push
      const currentPath = router.currentRoute.value.path
      const publicPrefixes = ['/login', '/register', '/forgot-password', '/reset-password', '/verify-email', '/']
      const isPublic = currentPath === '/' || publicPrefixes.some((p) => p !== '/' && currentPath.startsWith(p))
      if (!isPublic) {
        await router.push('/login')
      }
      // 標記為已處理，caller 的 catch 不必再顯示通用錯誤訊息
      ;(error as AxiosError & { _handled?: boolean })._handled = true
      return Promise.reject(error)
    }

    if (error.response?.status === 403) {
      // 帳號被停權：直接導去 /suspended，避免使用者看到通用「無權限」誤導訊息。
      // 動態 import 避免 client ↔ router 模組循環。
      if ((error.response.data as { code?: string } | undefined)?.code === 'ACCOUNT_SUSPENDED') {
        const { default: router } = await import('@/router')
        if (router.currentRoute.value.path !== '/suspended') {
          await router.push('/suspended')
        }
        // 標記為已處理，caller 看到此 flag 應 silent return（避免 LoginView 再 toast）
        ;(error as AxiosError & { _handled?: boolean })._handled = true
        return Promise.reject(error)
      }
      uiStore.showToast('您沒有權限執行此操作', 'error')
      return Promise.reject(error)
    }

    if (error.response?.status === 422) {
      // 表單驗證錯誤，讓各頁面自己處理
      return Promise.reject(error)
    }

    if (error.response?.status === 429) {
      uiStore.showToast('操作太頻繁，請稍後再試', 'warning')
      return Promise.reject(error)
    }

    if (error.response?.status && error.response.status >= 500) {
      uiStore.showToast('伺服器發生錯誤，請稍後再試', 'error')
      return Promise.reject(error)
    }

    return Promise.reject(error)
  },
)

export { client as apiClient }
export default client
