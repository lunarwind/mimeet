/**
 * useInfiniteScroll.ts
 * 通用泛型無限滾動 composable
 */
import { ref, computed, onUnmounted, watch, type Ref } from 'vue'

export interface InfiniteScrollFetchResult<T> {
  data: T[]
  hasMore: boolean
}

export interface UseInfiniteScrollOptions<T> {
  fetchFn: (page: number) => Promise<InfiniteScrollFetchResult<T>>
  /** Intersection Observer rootMargin（預設 200px） */
  rootMargin?: string
}

export function useInfiniteScroll<T>(options: UseInfiniteScrollOptions<T>) {
  const items = ref<T[]>([]) as Ref<T[]>
  const currentPage = ref(0)
  const isLoading = ref(false)
  const isLoadingMore = ref(false)
  const hasMore = ref(true)
  const error = ref<string | null>(null)
  const sentinelRef = ref<HTMLElement | null>(null)

  const isEmpty = computed(() => items.value.length === 0 && !isLoading.value)

  let observer: IntersectionObserver | null = null

  // ── 載入第一頁 ──────────────────────────────────────────
  async function fetchFirst() {
    isLoading.value = true
    error.value = null
    currentPage.value = 1
    try {
      const result = await options.fetchFn(1)
      items.value = result.data
      hasMore.value = result.hasMore
    } catch (e) {
      error.value = '載入失敗，請稍後再試'
      console.error('[useInfiniteScroll] fetchFirst error:', e)
    } finally {
      isLoading.value = false
    }
  }

  // ── 載入更多 ────────────────────────────────────────────
  async function fetchMore() {
    if (!hasMore.value || isLoadingMore.value || isLoading.value) return
    isLoadingMore.value = true
    const nextPage = currentPage.value + 1
    try {
      const result = await options.fetchFn(nextPage)
      items.value.push(...result.data)
      currentPage.value = nextPage
      hasMore.value = result.hasMore
    } catch (e) {
      console.error('[useInfiniteScroll] fetchMore error:', e)
    } finally {
      isLoadingMore.value = false
    }
  }

  // ── 重設 ────────────────────────────────────────────────
  function reset() {
    items.value = []
    hasMore.value = true
    fetchFirst()
  }

  // ── Intersection Observer ───────────────────────────────
  function setupObserver() {
    cleanupObserver()
    observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasMore.value && !isLoadingMore.value) {
          fetchMore()
        }
      },
      { rootMargin: options.rootMargin ?? '200px' },
    )
    if (sentinelRef.value) {
      observer.observe(sentinelRef.value)
    }
  }

  function cleanupObserver() {
    if (observer) {
      observer.disconnect()
      observer = null
    }
  }

  // sentinel DOM 出現後自動掛載 observer
  watch(sentinelRef, (el) => {
    if (el) setupObserver()
    else cleanupObserver()
  })

  onUnmounted(cleanupObserver)

  return {
    items,
    isLoading,
    isLoadingMore,
    hasMore,
    isEmpty,
    error,
    sentinelRef,
    fetchFirst,
    fetchMore,
    reset,
  }
}
