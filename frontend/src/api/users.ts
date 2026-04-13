/**
 * api/users.ts
 * 用戶相關 API
 * 對應 API-001 §3.1 / §3.2.1
 */
import client from './client'
import type { ExploreUser, SearchUsersResponse } from '@/types/explore'

// ── 用戶個人資料型別 ──────────────────────────────────────
export interface UserProfileData {
  id: number
  nickname: string
  age: number
  gender: 'male' | 'female'
  location: string
  avatar: string | null
  credit_score: number
  membership_level: number
  introduction: string | null
  height: number | null
  weight: number | null
  job: string | null
  education: string | null
  photos: { id: number; url: string; is_avatar: boolean; order: number }[]
  verification_status: {
    email_verified: boolean
    phone_verified: boolean
    verified: boolean
    credit_card_verified: boolean
  }
  online_status: 'online' | 'offline'
  last_active_at: string | null
  is_favorited: boolean
  is_blocked: boolean
  stats: {
    profile_views: number
    messages_received: number
    likes_received: number
  }
  created_at: string
}

// ── 搜尋用戶（探索頁） ────────────────────────────────────
export async function searchUsers(params: Record<string, unknown>): Promise<SearchUsersResponse> {
  const res = await client.get<{
    success: boolean
    data: {
      users: RawApiUser[]
      pagination: {
        current_page: number
        per_page: number
        total: number
        total_pages: number
      }
    }
  }>('/users/search', { params })

  return {
    users: res.data.data.users.map(transformUser),
    pagination: res.data.data.pagination,
  }
}

// ── 取得用戶個人資料 ──────────────────────────────────────
export async function fetchUserProfile(userId: number): Promise<UserProfileData> {
  const res = await client.get<{
    success: boolean
    data: { user: UserProfileData }
  }>(`/users/${userId}`, { params: { include: 'photos,stats' } })
  return res.data.data.user
}

// ── 收藏 / 取消收藏 ────────────────────────────────────────
export async function favoriteUser(userId: number): Promise<void> {
  await client.post(`/users/${userId}/follow`)
}

export async function unfavoriteUser(userId: number): Promise<void> {
  await client.delete(`/users/${userId}/follow`)
}

// ── 封鎖 / 解除封鎖 ────────────────────────────────────────
export async function blockUser(userId: number): Promise<void> {
  await client.post(`/users/${userId}/block`)
}

export async function unblockUser(userId: number): Promise<void> {
  await client.delete(`/users/${userId}/block`)
}

// ── API 原始型別（Snake Case） ────────────────────────────
interface RawApiUser {
  id: number
  nickname: string
  age: number
  location: string
  avatar: string | null
  credit_score: number
  online_status: 'online' | 'offline'
  last_active_at: string | null
  email_verified?: boolean
  phone_verified?: boolean
  advanced_verified?: boolean
  membership_level?: number
  is_favorited?: boolean
}

// ── 轉換函式（snake_case → camelCase） ───────────────────
function transformUser(raw: RawApiUser): ExploreUser {
  return {
    id:              raw.id,
    nickname:        raw.nickname,
    age:             raw.age,
    location:        raw.location,
    avatar:          raw.avatar,
    creditScore:     raw.credit_score,
    isOnline:        raw.online_status === 'online',
    lastActiveAt:    raw.last_active_at,
    emailVerified:   raw.email_verified ?? false,
    phoneVerified:   raw.phone_verified ?? false,
    advancedVerified: raw.advanced_verified ?? false,
    membershipLevel: raw.membership_level ?? 1,
    isFavorited:     raw.is_favorited ?? false,
  }
}
