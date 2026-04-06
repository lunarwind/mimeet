/**
 * types/explore.ts
 * 探索頁相關型別
 */

/** API 回傳的探索用戶資料（對應 API-001 §3.2.1 search response） */
export interface ExploreUser {
  id: number
  nickname: string
  age: number
  location: string
  avatar: string | null

  // 誠信分數（後台用，前台只顯示等級）
  creditScore: number

  // 線上狀態
  isOnline: boolean
  lastActiveAt: string | null

  // 驗證狀態
  emailVerified: boolean
  phoneVerified: boolean
  advancedVerified: boolean

  // 會員等級
  membershipLevel: number

  // 收藏狀態（需登入後才有）
  isFavorited: boolean
}

/** 探索篩選條件 */
export interface ExploreFilter {
  search?: string        // 暱稱搜尋
  gender?: 'all' | 'male' | 'female'
  ageMin?: number
  ageMax?: number
  city?: string          // 快速地區標籤（單選）
  cities?: string[]      // 進階篩選地區（多選）
  creditScoreRange?: string // '0-30' | '31-60' | '61-90' | '91-120'
  lastOnline?: string    // 'today' | '3days' | '7days' | 'all'
}

/** API 分頁資訊 */
export interface Pagination {
  current_page: number
  per_page: number
  total: number
  total_pages: number
}

/** searchUsers API 回傳格式 */
export interface SearchUsersResponse {
  users: ExploreUser[]
  pagination: Pagination
}
