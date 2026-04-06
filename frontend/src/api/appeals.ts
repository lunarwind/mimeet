/**
 * api/appeals.ts
 * 停權申訴 API
 * 對應 API-001 §10.8 POST /api/v1/me/appeal
 */
import client from './client'

const USE_MOCK = import.meta.env.DEV

export interface AppealPayload {
  reason: string
  evidence?: string
  images?: string[]
}

export interface AppealResponse {
  ticket_number: string
  message: string
}

export async function submitAppeal(payload: AppealPayload): Promise<AppealResponse> {
  if (USE_MOCK) {
    await new Promise(r => setTimeout(r, 800 + Math.random() * 500))
    const now = new Date()
    const date = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}`
    const rand = String(Math.floor(10000 + Math.random() * 90000))
    return {
      ticket_number: `APPEAL-${date}-${rand}`,
      message: '申訴已送出，我們將在 3-5 個工作天內回覆。',
    }
  }

  const res = await client.post<{
    success: boolean
    data: AppealResponse
  }>('/me/appeal', { reason: payload.reason })
  return res.data.data
}
