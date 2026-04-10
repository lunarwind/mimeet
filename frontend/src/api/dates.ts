/**
 * api/dates.ts
 * 約會相關 API — 對應 API-001 §5
 */
import client from './client'
import type { DateInvitation } from '@/types/chat'

const USE_MOCK = import.meta.env.VITE_USE_MOCK === 'true'

function delay(ms: number) { return new Promise(r => setTimeout(r, ms)) }

export async function fetchDates(): Promise<DateInvitation[]> {
  if (USE_MOCK) {
    const { mockFetchDates } = await import('@/mocks/dates')
    await delay(300 + Math.random() * 300)
    return mockFetchDates()
  }
  const res = await client.get<{ data: { invitations: DateInvitation[] } }>('/date-invitations')
  return res.data.data.invitations
}

export async function respondToDate(id: number, response: 'accepted' | 'rejected'): Promise<void> {
  if (USE_MOCK) { await delay(300); return }
  await client.patch(`/date-invitations/${id}/response`, { data: { response } })
}

export async function verifyDateQR(qrCode: string): Promise<{ success: boolean; creditScoreAwarded: number }> {
  if (USE_MOCK) {
    await delay(500)
    return { success: true, creditScoreAwarded: 5 }
  }
  const res = await client.post<{ data: { verification: { credit_score_awarded: number } } }>('/date-invitations/verify', { data: { qr_code: qrCode } })
  return { success: true, creditScoreAwarded: res.data.data.verification.credit_score_awarded }
}
