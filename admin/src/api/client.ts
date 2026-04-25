import axios from 'axios'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
  withCredentials: false,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 15000,
})

// Request interceptor: attach Bearer token from sessionStorage
apiClient.interceptors.request.use((config) => {
  const authToken = sessionStorage.getItem('admin_token')
  if (authToken) {
    config.headers.Authorization = `Bearer ${authToken}`
  }
  return config
})

// Response interceptor: handle 401/403
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      sessionStorage.removeItem('admin_token')
      sessionStorage.removeItem('admin_user')
      localStorage.removeItem('admin_token')
      localStorage.removeItem('admin_user')
      window.location.hash = '#/login'
    }
    if (error.response?.status === 403) {
      // 403 Permission denied — handled by UI layer
    }
    return Promise.reject(error)
  },
)

export default apiClient
