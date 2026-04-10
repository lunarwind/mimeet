/**
 * api/dates.ts
 * 約會相關 API — 對應 API-001 §5
 */
import client from './client'
import type { DateInvitation } from '@/types/chat'

export async function fetchDates(): Promise<DateInvitation[]> {
  const res = await client.get<{ data: { invitations: DateInvitation[] } }>('/date-invitations')
  return res.data.data.invitations
}

export async function respondToDate(id: number, response: 'accepted' | 'rejected'): Promise<void> {
  await client.patch(`/date-invitations/${id}/response`, { data: { response } })
}

export interface VerifyResult {
  success: boolean
  creditScoreAwarded: number
  gpsPassed: boolean
  status: 'completed' | 'waiting'
}

export async function verifyDateQR(
  token: string,
  latitude?: number | null,
  longitude?: number | null,
): Promise<VerifyResult> {
  const body: Record<string, unknown> = { token }
  if (latitude != null) body.latitude = latitude
  if (longitude != null) body.longitude = longitude

  const res = await client.post<{ data: { status: string; score_awarded?: number; gps_passed?: boolean } }>(
    '/dates/verify',
    body,
  )
  const d = res.data.data
  return {
    success: true,
    creditScoreAwarded: d.score_awarded ?? 0,
    gpsPassed: d.gps_passed ?? false,
    status: d.status as 'completed' | 'waiting',
  }
}

/**
 * Get current GPS position (returns null if denied/unavailable)
 */
export function getCurrentPosition(): Promise<{ latitude: number; longitude: number } | null> {
  return new Promise((resolve) => {
    if (!navigator.geolocation) {
      resolve(null)
      return
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 },
    )
  })
}
