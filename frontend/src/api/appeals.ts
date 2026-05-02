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

/**
 * 對應 API-001 §10.8 GET /api/v1/me/appeal/current
 * 回傳當前進行中（pending）或最近一筆已處理（resolved）的申訴；
 * 若用戶從未提交過申訴，回傳 null。
 */
export interface CurrentAppeal {
  ticket_no: string
  status: string
  submitted_at: string | null
  admin_reply: string | null
  replied_at: string | null
}

export async function getCurrentAppeal(): Promise<CurrentAppeal | null> {
  const res = await client.get<{
    success: boolean
    data: CurrentAppeal | null
  }>('/me/appeal/current')
  return res.data.data
}
