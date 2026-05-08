import client from './client'

export interface LoginPayload {
  email: string
  password: string
  device_info?: {
    type: string
    name: string
    os: string
  }
}

export interface LoginResponse {
  success: boolean
  data: {
    user: {
      id: number
      email: string
      nickname: string
      avatar: string | null
      gender: string
      status: string
      credit_score: number
      membership_level: number
      email_verified: boolean
      phone_verified: boolean
      phone?: string | null  // PR-3: masked phone from backend, not raw E.164
    }
    token: string
  }
}

export function login(payload: LoginPayload): Promise<LoginResponse['data']> {
  return client.post('/auth/login', payload).then(r => r.data.data)
}

export function logout(): Promise<void> {
  return client.post('/auth/logout')
}

export function getMe() {
  return client.get('/auth/me').then(r => r.data.data)
}

export function forgotPassword(email: string) {
  return client.post('/auth/forgot-password', { email })
}

export function resetPassword(payload: {
  token: string
  email: string
  password: string
  password_confirmation: string
}) {
  return client.post('/auth/reset-password', payload)
}

export interface RegisterPayload {
  email: string
  password: string
  password_confirmation: string  // 對應後端 confirmed rule
  nickname: string
  gender: 'male' | 'female'
  birth_date: string
  phone?: string                 // optional（後端 nullable）
  terms_accepted: boolean        // 接受真實 form 狀態，caller 負責傳正確值
  privacy_accepted: boolean
  anti_fraud_read: boolean
}

export function register(payload: RegisterPayload) {
  return client.post('/auth/register', payload)
}

export function verifyEmail(payload: { verification_code: string; email: string }) {
  return client.post('/auth/verify-email', payload).then(r => r.data)
}

export function resendVerification(email: string) {
  return client.post('/auth/resend-verification', { email })
}

// PR-3: phone 不再由 client 控制(漏洞修復)。固定使用 auth user.phone。
export type SendPhoneCodePayload = Record<string, never>
export interface VerifyPhoneCodePayload {
  code: string
}

export function sendPhoneCode(payload: SendPhoneCodePayload = {}) {
  return client.post('/auth/verify-phone/send', payload).then(r => r.data)
}

export function verifyPhoneCode(payload: VerifyPhoneCodePayload) {
  return client.post('/auth/verify-phone/confirm', payload).then(r => r.data)
}

// PR-3: 換號流程
export interface InitiatePhoneChangePayload {
  new_phone: string
}
export interface VerifyOldPhonePayload {
  old_otp: string
}
export interface VerifyNewPhonePayload {
  new_otp: string
}

export function initiatePhoneChange(payload: InitiatePhoneChangePayload) {
  return client.post('/auth/phone-change/initiate', payload).then(r => r.data)
}

export function verifyOldPhone(payload: VerifyOldPhonePayload) {
  return client.post('/auth/phone-change/verify-old', payload).then(r => r.data)
}

export function verifyNewPhone(payload: VerifyNewPhonePayload) {
  return client.post('/auth/phone-change/verify-new', payload).then(r => r.data)
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export function changePassword(payload: ChangePasswordPayload) {
  return client.post('/me/change-password', payload).then(r => r.data)
}
