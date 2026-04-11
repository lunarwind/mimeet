/**
 * api/appeals.ts
 * 停權申訴 API
 * 對應 API-001 §10.8 POST /api/v1/me/appeal
 */
import client from './client'

<<<<<<< HEAD
const USE_MOCK = import.meta.env.VITE_USE_MOCK === 'true'

=======
>>>>>>> develop
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
  const res = await client.post<{
    success: boolean
    data: AppealResponse
  }>('/me/appeal', { reason: payload.reason })
  return res.data.data
}
