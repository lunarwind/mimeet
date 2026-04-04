import axios from 'axios'
import type { AxiosInstance } from 'axios'

const MOCK_DELAY = () => Math.random() * 600 + 200 // 200-800ms 隨機延遲

export function setupMockAdapter(client: AxiosInstance) {
  client.interceptors.request.use(async (config) => {
    if (import.meta.env.VITE_USE_MOCK !== 'true') return config

    const url = config.url ?? ''
    const method = (config.method ?? 'get').toLowerCase()

    // 動態載入對應的 mock handler
    try {
      const handler = await getMockHandler(url, method, config.data)
      if (handler) {
        await new Promise(resolve => setTimeout(resolve, MOCK_DELAY()))
        return Promise.reject({
          isMock: true,
          data: handler,
          status: 200,
        })
      }
    } catch (e: any) {
      if (e.isMock) {
        return Promise.reject(e)
      }
    }

    return config
  })

  client.interceptors.response.use(
    (response) => response,
    (error) => {
      if (error.isMock) {
        return Promise.resolve({
          data: error.data,
          status: error.status ?? 200,
          headers: {},
          config: {},
          request: {},
        })
      }
      return Promise.reject(error)
    }
  )
}

async function getMockHandler(url: string, method: string, data?: any) {
  // Auth
  if (url.includes('/auth/login') && method === 'post') {
    const { mockLogin } = await import('./handlers/auth')
    return mockLogin(data)
  }
  if (url.includes('/auth/register') && method === 'post') {
    const { mockRegister } = await import('./handlers/auth')
    return mockRegister(data)
  }
  if (url.includes('/auth/me') && method === 'get') {
    const { mockMe } = await import('./handlers/auth')
    return mockMe()
  }

  // Users
  if (url.includes('/users') && method === 'get') {
    const { mockGetUsers } = await import('./handlers/users')
    return mockGetUsers()
  }

  return null
}
