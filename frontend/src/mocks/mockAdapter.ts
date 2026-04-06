import type { AxiosInstance } from 'axios'

const MOCK_DELAY = () => Math.random() * 600 + 200

export function setupMockAdapter(client: AxiosInstance) {
  client.interceptors.request.use(async (config) => {
    if (import.meta.env.VITE_USE_MOCK !== 'true') return config

    const url = config.url ?? ''
    const method = (config.method ?? 'get').toLowerCase()

    try {
      const handler = await getMockHandler(url, method, config.data)
      if (handler) {
        await new Promise((resolve) => setTimeout(resolve, MOCK_DELAY()))
        return Promise.reject({
          isMock: true,
          data: handler,
          status: 200,
        })
      }
    } catch (e: unknown) {
      if ((e as { isMock?: boolean }).isMock) {
        return Promise.reject(e)
      }
    }

    return config
  })

  client.interceptors.response.use(
    (response) => response,
    (error: unknown) => {
      if ((error as { isMock?: boolean }).isMock) {
        return Promise.resolve({
          data: (error as { data: unknown }).data,
          status: (error as { status?: number }).status ?? 200,
          headers: {},
          config: {},
          request: {},
        })
      }
      return Promise.reject(error)
    },
  )
}

async function getMockHandler(url: string, method: string, data?: Record<string, unknown>) {
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
  if (url.includes('/users') && method === 'get') {
    const { mockGetUsers } = await import('./handlers/users')
    return mockGetUsers()
  }
  return null
}
