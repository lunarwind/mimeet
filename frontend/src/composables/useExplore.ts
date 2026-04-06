/**
 * useExplore.ts
 * 探索頁無限滾動 Composable
 * 依據 API-001 §3.2.1 GET /api/v1/users/search
 */
import { ref, computed, watch, type Ref, type ComputedRef } from 'vue'
import { searchUsers } from '@/api/users'
import type { ExploreUser, ExploreFilter } from '@/types/explore'

const PAGE_SIZE = 20

export function useExplore(filters: Ref<ExploreFilter> | ComputedRef<ExploreFilter>) {
  const users        = ref<ExploreUser[]>([])
  const currentPage  = ref(1)
  const isLoading    = ref(false)
  const isLoadingMore = ref(false)
  const hasMore      = ref(true)
  const error        = ref<string | null>(null)

  const isEmpty = computed(() => users.value.length === 0 && !isLoading.value)

  // 第一頁載入
  async function fetchFirst() {
    isLoading.value = true
    error.value = null
    currentPage.value = 1
    try {
      const result = await searchUsers(buildParams(1))
      users.value = result.users
      hasMore.value = result.pagination.current_page < result.pagination.total_pages
    } catch (e) {
      error.value = '載入失敗，請稍後再試'
      console.error('[useExplore] fetchFirst error:', e)
    } finally {
      isLoading.value = false
    }
  }

  // 載入更多（無限滾動）
  async function fetchMore() {
    if (!hasMore.value || isLoadingMore.value || isLoading.value) return
    isLoadingMore.value = true
    const nextPage = currentPage.value + 1
    try {
      const result = await searchUsers(buildParams(nextPage))
      users.value.push(...result.users)
      currentPage.value = nextPage
      hasMore.value = nextPage < result.pagination.total_pages
    } catch (e) {
      console.error('[useExplore] fetchMore error:', e)
    } finally {
      isLoadingMore.value = false
    }
  }

  // 重設並重新載入（篩選條件改變時）
  function reset() {
    users.value = []
    hasMore.value = true
    fetchFirst()
  }

  // 將 ExploreFilter 轉為 API 查詢參數
  function buildParams(page: number) {
    const f = filters.value
    return {
      page,
      per_page: PAGE_SIZE,
      sort: 'credit_score',
      sort_direction: 'desc',
      // 搜尋
      ...(f.search      ? { nickname: f.search }         : {}),
      // 年齡
      ...(f.ageMin      ? { age_min: f.ageMin }           : {}),
      ...(f.ageMax      ? { age_max: f.ageMax }           : {}),
      // 性別
      ...(f.gender && f.gender !== 'all' ? { gender: f.gender } : {}),
      // 地區（快速標籤或多選城市）
      ...(f.city        ? { location: f.city }            : {}),
      ...(f.cities?.length ? { location: f.cities[0] }   : {}), // 暫用第一個城市；後端若支援多選再調整
      // 誠信分數
      ...(f.creditScoreRange ? buildCreditParams(f.creditScoreRange) : {}),
      // 最後上線
      ...(f.lastOnline && f.lastOnline !== 'all' ? { last_online: f.lastOnline } : {}),
    }
  }

  function buildCreditParams(range: string) {
    const map: Record<string, { credit_score_min: number; credit_score_max?: number }> = {
      '0-30':   { credit_score_min: 0,  credit_score_max: 30  },
      '31-60':  { credit_score_min: 31, credit_score_max: 60  },
      '61-90':  { credit_score_min: 61, credit_score_max: 90  },
      '91-120': { credit_score_min: 91 },
    }
    return map[range] ?? {}
  }

  // 篩選條件改變時自動重設
  watch(filters, () => {
    reset()
  }, { immediate: true })

  return {
    users,
    isLoading,
    isLoadingMore,
    hasMore,
    isEmpty,
    error,
    fetchMore,
    reset,
  }
}
