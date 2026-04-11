export type AdminRole = 'super_admin' | 'admin' | 'cs'

export interface AdminUser {
  id: number
  name: string
  email: string
  role: AdminRole
}

export interface PaginatedResponse<T> {
  success: boolean
  data: T
  pagination: {
    current_page: number
    per_page: number
    total: number
    total_pages: number
  }
}

export interface ApiResponse<T> {
  success: boolean
  code?: number
  message?: string
  data: T
}

export interface MemberListItem {
  uid: number
  nickname: string
  gender: 'male' | 'female'
  age: number
  avatar: string
  credit_score: number
  level: number
  level_label: string
  location: string
  email: string
  phone_last4: string
  status: 'active' | 'suspended' | 'pending_deletion'
  email_verified: boolean
  phone_verified: boolean
  advanced_verified: boolean
  last_login_at: string
  registered_at: string
  profile_views: number
}

export interface MemberDetail extends MemberListItem {
  introduction: string
  height: number
  weight: number
  job: string
  education: string
  birth_date: string
  photos: { id: number; url: string; is_avatar: boolean; order: number }[]
  membership_level: number
  verification_status: {
    email_verified: boolean
    phone_verified: boolean
    verified: boolean
    credit_card_verified: boolean
  }
}

export interface ScoreRecord {
  id: number
  delta: number
  reason: string
  operator: string
  created_at: string
}

export interface SubscriptionRecord {
  id: number
  plan: string
  amount: number
  status: 'active' | 'expired' | 'cancelled'
  started_at: string
  expires_at: string
}

export interface Ticket {
  id: number
  ticket_number: string
  type: 1 | 2 | 3
  type_label: string
  title: string
  content: string
  reporter: { uid: number; nickname: string; avatar: string }
  reported_user: { uid: number; nickname: string; avatar: string } | null
  status: 1 | 2 | 3
  status_label: string
  admin_reply: string | null
  images: string[]
  created_at: string
  updated_at: string
}

export interface PaymentRecord {
  id: number
  order_number: string
  user: { uid: number; nickname: string }
  payment_type: string
  plan: string
  amount: number
  amount_paid: number
  payment_method: string
  status: 'paid' | 'failed' | 'refunded' | 'pending'
  paid_at: string
  ecpay_trade_no?: string
  ecpay_invoice_no?: string | null
}

export type CreditLevel = 'top' | 'good' | 'normal' | 'low'

export function getCreditLevel(score: number): CreditLevel {
  if (score >= 91) return 'top'
  if (score >= 61) return 'good'
  if (score >= 31) return 'normal'
  return 'low'
}

export const CreditLevelLabel: Record<CreditLevel, string> = {
  top: '頂級',
  good: '優質',
  normal: '普通',
  low: '受限',
}

export const CreditLevelColor: Record<CreditLevel, string> = {
  top: '#92400E',
  good: '#065F46',
  normal: '#1E40AF',
  low: '#991B1B',
}

export const CreditLevelBg: Record<CreditLevel, string> = {
  top: '#FFFBEB',
  good: '#ECFDF5',
  normal: '#EFF6FF',
  low: '#FEF2F2',
}
