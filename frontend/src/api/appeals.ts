/**
 * api/appeals.ts
 * 停權申訴 API
 * 對應 API-001 §10.8 POST /api/v1/me/appeal (multipart/form-data)
 */
import client from './client'

export interface AppealPayload {
  reason: string
  images?: File[]
}

export interface AppealResponse {
  ticket_no: string
  message: string
}

export async function submitAppeal(payload: AppealPayload): Promise<AppealResponse> {
  const form = new FormData()
  form.append('reason', payload.reason)
  if (payload.images) {
    payload.images.forEach((file) => form.append('images[]', file))
  }

  const res = await client.post<{
    success: boolean
    data: AppealResponse
  }>('/me/appeal', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return res.data.data
}
