export type Gender = 'male' | 'female'
export type UserStatus = 'active' | 'suspended' | 'deleted'
export type MembershipLevel = 0 | 1 | 1.5 | 2 | 3

export interface User {
  id: number
  uuid: string
  nickname: string
  gender: Gender
  membershipLevel: MembershipLevel
  isPaid: boolean
  creditScore: number
  status: UserStatus
  avatarUrl: string | null
}

export interface UserProfile extends User {
  age: number
  region: string
  bio: string | null
  height: number | null
  weight: number | null
  occupation: string | null
  income: string | null
  photos: string[]
  isOnline: boolean
  lastActiveAt: string
  isBlocked: boolean
  isFavorited: boolean
}

export interface UserCard {
  id: number
  uuid: string
  nickname: string
  gender: Gender
  age: number
  region: string
  creditScore: number
  avatarUrl: string | null
  isOnline: boolean
  membershipLevel: MembershipLevel
  lastActiveAt: string
}

// 誠信分數等級
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

/** 個人資料更新 payload */
export interface UpdateProfilePayload {
  nickname?: string
  introduction?: string
  location?: string
  height?: number
  weight?: number
  education?: string
  job?: string
}
