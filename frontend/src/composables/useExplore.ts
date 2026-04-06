/**
 * useExplore.ts
 * 探索頁 composable — 基於 useInfiniteScroll 泛型實作
 */
import { watch, type Ref, type ComputedRef } from 'vue'
import { useInfiniteScroll } from './useInfiniteScroll'
import { searchUsers } from '@/api/users'
import type { ExploreUser, ExploreFilter } from '@/types/explore'

const PAGE_SIZE = 20

export function useExplore(filters: Ref<ExploreFilter> | ComputedRef<ExploreFilter>) {
  const scroll = useInfiniteScroll<ExploreUser>({
    fetchFn: async (page) => {
      const result = await searchUsers(buildParams(page))
      return {
        data: result.users,
        hasMore: result.pagination.current_page < result.pagination.total_pages,
      }
    },
  })

  // 將 ExploreFilter 轉為 API 查詢參數
  function buildParams(page: number) {
    const f = filters.value
    return {
      page,
      per_page: PAGE_SIZE,
      sort: 'credit_score',
      sort_direction: 'desc',
      ...(f.search      ? { nickname: f.search }         : {}),
      ...(f.ageMin      ? { age_min: f.ageMin }           : {}),
      ...(f.ageMax      ? { age_max: f.ageMax }           : {}),
      ...(f.gender && f.gender !== 'all' ? { gender: f.gender } : {}),
      ...(f.city        ? { location: f.city }            : {}),
      ...(f.cities?.length ? { location: f.cities[0] }   : {}),
      ...(f.creditScoreRange ? buildCreditParams(f.creditScoreRange) : {}),
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
    scroll.reset()
  }, { immediate: true })

  return {
    users: scroll.items,
    isLoading: scroll.isLoading,
    isLoadingMore: scroll.isLoadingMore,
    hasMore: scroll.hasMore,
    isEmpty: scroll.isEmpty,
    error: scroll.error,
    sentinelRef: scroll.sentinelRef,
    fetchMore: scroll.fetchMore,
    reset: scroll.reset,
  }
}
