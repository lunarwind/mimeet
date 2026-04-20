import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher

let echoInstance: Echo<'reverb'> | null = null

function getAuthToken(): string {
  return localStorage.getItem('auth_token') || ''
}

export function getEcho(): Echo<'reverb'> {
  if (echoInstance) return echoInstance

  const host = import.meta.env.VITE_REVERB_HOST || 'api.mimeet.online'
  const scheme = import.meta.env.VITE_REVERB_SCHEME || 'https'
  const port = Number(import.meta.env.VITE_REVERB_PORT) || (scheme === 'https' ? 443 : 8080)
  const apiBase = import.meta.env.VITE_API_BASE_URL || 'https://api.mimeet.online/api/v1'

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'mimeet-key-2026',
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${apiBase}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${getAuthToken()}`,
        Accept: 'application/json',
      },
    },
  })

  return echoInstance
}

export function destroyEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }
}

export function updateEchoAuth(token: string): void {
  if (!echoInstance) return
  const connector = echoInstance.connector as unknown as {
    pusher: { config: { auth: { headers: Record<string, string> } } }
  }
  connector.pusher.config.auth.headers.Authorization = `Bearer ${token}`
}
