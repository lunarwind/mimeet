import { apiClient } from './client'

export interface VerificationRequestResponse {
  verification_id: number
  random_code: string
  expires_at: string
  remaining_seconds: number
}

export interface VerificationStatusResponse {
  status: 'none' | 'pending_code' | 'pending_review' | 'approved' | 'rejected' | 'expired'
  submitted_at?: string
  reviewed_at?: string
  reject_reason?: string
}

export async function requestVerificationCode(): Promise<VerificationRequestResponse> {
  const res = await apiClient.post('/me/verification-photo/request')
  return res.data.data
}

export async function uploadVerificationPhoto(photoUrl: string, randomCode: string) {
  const res = await apiClient.post('/me/verification-photo/upload', {
    photo_url: photoUrl,
    random_code: randomCode,
  })
  return res.data.data
}

export async function getVerificationStatus(): Promise<VerificationStatusResponse> {
  const res = await apiClient.get('/me/verification-photo/status')
  return res.data.data
}

// ── 信用卡驗證（男性進階驗證）──────────────────────────────

export interface CreditCardVerificationInitResponse {
  payment_id: number
  aio_url: string
  params: Record<string, string | number>
}

export interface CreditCardVerificationStatus {
  verified: boolean
  verified_at: string | null
  latest: { status: string; created_at: string } | null
}

export async function initiateCreditCardVerification(): Promise<CreditCardVerificationInitResponse> {
  const res = await apiClient.post('/verification/credit-card/initiate')
  return res.data.data
}

export async function getCreditCardVerificationStatus(): Promise<CreditCardVerificationStatus> {
  const res = await apiClient.get('/verification/credit-card/status')
  return res.data.data
}
