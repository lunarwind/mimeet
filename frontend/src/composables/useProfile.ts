/**
 * useProfile.ts
 * 用戶資料 composable（含 60 秒快取）
 */
import { ref } from 'vue'
import {
  fetchUserProfile,
  favoriteUser,
  unfavoriteUser,
  type UserProfileData,
} from '@/api/users'

// ── 60 秒快取 ─────────────────────────────────────────────
const cache = new Map<number, { data: UserProfileData; ts: number }>()
const CACHE_TTL = 60_000

function getCached(userId: number): UserProfileData | null {
  const entry = cache.get(userId)
  if (!entry) return null
  if (Date.now() - entry.ts > CACHE_TTL) {
    cache.delete(userId)
    return null
  }
  return entry.data
}

function setCache(userId: number, data: UserProfileData) {
  cache.set(userId, { data, ts: Date.now() })
}

// ── Composable ────────────────────────────────────────────
export function useProfile() {
  const profile = ref<UserProfileData | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchProfile(userId: number) {
    // 先查快取
    const cached = getCached(userId)
    if (cached) {
      profile.value = cached
      return cached
    }

    isLoading.value = true
    error.value = null
    try {
      const data = await fetchUserProfile(userId)
      profile.value = data
      setCache(userId, data)
      return data
    } catch (e) {
      error.value = '無法載入用戶資料'
      // Error handled via error ref
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function toggleFavorite(userId: number) {
    if (!profile.value) return
    try {
      if (profile.value.is_favorited) {
        await unfavoriteUser(userId)
        profile.value.is_favorited = false
      } else {
        await favoriteUser(userId)
        profile.value.is_favorited = true
      }
      // 更新快取
      if (profile.value) setCache(userId, profile.value)
    } catch (e) {
      // Silently fail — UI state not changed on error
    }
  }

  /** 清除指定用戶的快取 */
  function invalidateCache(userId: number) {
    cache.delete(userId)
  }

  return {
    profile,
    isLoading,
    error,
    fetchProfile,
    toggleFavorite,
    invalidateCache,
  }
}
