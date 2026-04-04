import axios from 'axios'
import type { AxiosInstance, AxiosError } from 'axios'
import { useUiStore } from '@/stores/ui'

const client: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 10000,
})

// Request Interceptor：自動帶上 token
client.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response Interceptor：統一錯誤處理
client.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    const uiStore = useUiStore()

    if (error.response?.status === 401) {
      // Token 過期或未登入
      localStorage.removeItem('auth_token')
      localStorage.removeItem('member_level')
      window.location.hash = '/login'
      return Promise.reject(error)
    }

    if (error.response?.status === 403) {
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
  }
)

import { setupMockAdapter } from '@/mocks/mockAdapter'
setupMockAdapter(client)

export default client
