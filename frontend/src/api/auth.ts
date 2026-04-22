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
  nickname: string
  gender: 'male' | 'female'
  birth_date: string
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

export interface SendPhoneCodePayload {
  phone: string
}

export interface VerifyPhoneCodePayload {
  phone: string
  code: string
}

export function sendPhoneCode(payload: SendPhoneCodePayload) {
  return client.post('/auth/verify-phone/send', payload).then(r => r.data)
}

export function verifyPhoneCode(payload: VerifyPhoneCodePayload) {
  return client.post('/auth/verify-phone/confirm', payload).then(r => r.data)
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export function changePassword(payload: ChangePasswordPayload) {
  return client.post('/me/change-password', payload).then(r => r.data)
}
