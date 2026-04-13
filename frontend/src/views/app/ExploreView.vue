<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import BottomNav from '@/components/layout/BottomNav.vue'
import UserCard from '@/components/explore/UserCard.vue'
import FilterBottomSheet from '@/components/explore/FilterBottomSheet.vue'
import ExploreEmptyState from '@/components/explore/ExploreEmptyState.vue'
import { useExplore } from '@/composables/useExplore'
import type { ExploreFilter } from '@/types/explore'

const router = useRouter()

// ── 快速篩選標籤 ─────────────────────────────────────────
const QUICK_TAGS = ['全部', '台北', '台中', '高雄', '桃園', '其他縣市'] as const
type QuickTag = typeof QUICK_TAGS[number]

const activeTag = ref<QuickTag>('全部')
const searchQuery = ref('')
const showFilterSheet = ref(false)
const activeFilters = ref<ExploreFilter>({})

// 是否有進階篩選條件（用於漏斗紅點）
const hasActiveFilters = computed(() => {
  const f = activeFilters.value
  return !!(
    f.ageMin !== undefined ||
    f.ageMax !== undefined ||
    f.gender ||
    f.creditScoreRange ||
    f.cities?.length ||
    f.lastOnline
  )
})

// 統整所有篩選條件傳給 composable
const mergedFilters = computed<ExploreFilter>(() => ({
  ...activeFilters.value,
  search: searchQuery.value || undefined,
  city: activeTag.value !== '全部' ? activeTag.value : undefined,
}))

// ── 無限滾動 composable ───────────────────────────────────
const {
  users,
  isLoading,
  isLoadingMore,
  hasMore,
  isEmpty,
  sentinelRef,
  fetchMore,
  reset,
} = useExplore(mergedFilters)

// 搜尋 debounce
let searchTimer: ReturnType<typeof setTimeout>
watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => reset(), 400)
})

// 標籤切換立即重新載入
watch(activeTag, () => reset())

// ── 篩選 Bottom Sheet 操作 ────────────────────────────────
function onApplyFilter(filters: ExploreFilter) {
  activeFilters.value = filters
  showFilterSheet.value = false
  reset()
}

function onResetFilter() {
  activeFilters.value = {}
  showFilterSheet.value = false
  reset()
}

// ── 點擊卡片導航 ──────────────────────────────────────────
function goToProfile(userId: number) {
  router.push(`/app/profiles/${userId}`)
}

// ── 清除搜尋 ──────────────────────────────────────────────
function clearSearch() {
  searchQuery.value = ''
}
</script>

<template>
  <div class="explore-view">
    <!-- TopBar -->
    <header class="explore-topbar">
      <h1 class="explore-topbar__title">探索</h1>
      <button
        class="explore-topbar__filter-btn"
        :class="{ 'explore-topbar__filter-btn--active': hasActiveFilters }"
        @click="showFilterSheet = true"
        aria-label="開啟篩選"
      >
        <!-- 漏斗 icon -->
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        <!-- 紅點：有篩選條件時顯示 -->
        <span v-if="hasActiveFilters" class="explore-topbar__filter-dot" aria-hidden="true" />
      </button>
    </header>

    <!-- 搜尋框 -->
    <div class="explore-search">
      <div class="explore-search__inner">
        <svg class="explore-search__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input
          v-model="searchQuery"
          type="search"
          class="explore-search__input"
          placeholder="搜尋暱稱…"
          autocomplete="off"
        />
        <button
          v-if="searchQuery"
          class="explore-search__clear"
          @click="clearSearch"
          aria-label="清除搜尋"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- 快速篩選標籤列 -->
    <div class="explore-tags" role="tablist" aria-label="地區快速篩選">
      <button
        v-for="tag in QUICK_TAGS"
        :key="tag"
        role="tab"
        :aria-selected="activeTag === tag"
        class="explore-tags__chip"
        :class="{ 'explore-tags__chip--active': activeTag === tag }"
        @click="activeTag = tag"
      >
        {{ tag }}
      </button>
    </div>

    <!-- 主內容區 -->
    <main class="explore-content">
      <!-- 初始 Loading 骨架 -->
      <template v-if="isLoading && users.length === 0">
        <div v-for="n in 8" :key="n" class="explore-skeleton" aria-hidden="true">
          <div class="explore-skeleton__avatar" />
          <div class="explore-skeleton__body">
            <div class="explore-skeleton__line explore-skeleton__line--name" />
            <div class="explore-skeleton__line explore-skeleton__line--sub" />
            <div class="explore-skeleton__line explore-skeleton__line--badges" />
          </div>
          <div class="explore-skeleton__right" />
        </div>
      </template>

      <!-- 空狀態 -->
      <ExploreEmptyState
        v-else-if="isEmpty && !isLoading"
        @adjust-filter="showFilterSheet = true"
      />

      <!-- 用戶卡片列表 -->
      <template v-else>
        <UserCard
          v-for="user in users"
          :key="user.id"
          :user="user"
          @click="goToProfile(user.id)"
        />

        <!-- 無限滾動 Sentinel -->
        <div ref="sentinelRef" class="explore-sentinel" aria-hidden="true" />

        <!-- 載入更多 spinner -->
        <div v-if="isLoadingMore" class="explore-loading-more" aria-live="polite" aria-label="載入更多用戶">
          <span class="explore-loading-more__spinner" />
          <span class="explore-loading-more__text">載入更多…</span>
        </div>

        <!-- 已載入全部 -->
        <p v-if="!hasMore && users.length > 0 && !isLoadingMore" class="explore-end-hint">
          已顯示全部 {{ users.length }} 位用戶
        </p>
      </template>
    </main>

    <!-- 篩選 Bottom Sheet -->
    <FilterBottomSheet
      v-if="showFilterSheet"
      :current-filters="activeFilters"
      @apply="onApplyFilter"
      @reset="onResetFilter"
      @close="showFilterSheet = false"
    />

    <BottomNav />
  </div>
</template>

<style scoped>
/* ──────────────────────────────────────────────────────────
   Variables（繼承全域，這裡只補頁面局部的）
   ────────────────────────────────────────────────────────── */
.explore-view {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
  padding-bottom: calc(64px + env(safe-area-inset-bottom));
  background: #F8F9FB;
}

/* ── TopBar ─────────────────────────────────────────────── */
.explore-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 56px;
  padding: 0 16px;
  background: #fff;
  border-bottom: 0.5px solid #E8ECF0;
  position: sticky;
  top: 0;
  z-index: 10;
}

.explore-topbar__title {
  font-size: 20px;
  font-weight: 700;
  color: #0F172A;
  letter-spacing: -0.3px;
}

.explore-topbar__filter-btn {
  position: relative;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  border: none;
  background: #F1F5F9;
  color: #475569;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.explore-topbar__filter-btn:active {
  transform: scale(0.93);
}

.explore-topbar__filter-btn--active {
  background: #FFF0F3;
  color: #F0294E;
}

.explore-topbar__filter-dot {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #F0294E;
  border: 1.5px solid #fff;
}

/* ── 搜尋框 ─────────────────────────────────────────────── */
.explore-search {
  padding: 10px 16px 0;
  background: #fff;
}

.explore-search__inner {
  position: relative;
  display: flex;
  align-items: center;
  background: #F1F5F9;
  border-radius: 9999px;
  height: 44px;
  padding: 0 16px;
  gap: 8px;
}

.explore-search__icon {
  flex-shrink: 0;
  color: #94A3B8;
}

.explore-search__input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 15px;
  color: #0F172A;
  outline: none;
  min-width: 0;
}

.explore-search__input::placeholder {
  color: #94A3B8;
}

/* 移除 search input 瀏覽器原生 x 按鈕 */
.explore-search__input::-webkit-search-cancel-button { display: none; }

.explore-search__clear {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: none;
  background: #CBD5E1;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  transition: background 0.15s;
}

.explore-search__clear:active {
  background: #94A3B8;
}

/* ── 快速篩選標籤列 ──────────────────────────────────────── */
.explore-tags {
  display: flex;
  gap: 8px;
  padding: 10px 16px 12px;
  overflow-x: auto;
  background: #fff;
  border-bottom: 0.5px solid #E8ECF0;
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
  /* 隱藏捲軸 */
  scrollbar-width: none;
}

.explore-tags::-webkit-scrollbar { display: none; }

.explore-tags__chip {
  flex-shrink: 0;
  height: 32px;
  padding: 0 14px;
  border-radius: 9999px;
  border: none;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, color 0.15s, transform 0.1s;
  background: #F1F5F9;
  color: #475569;
  white-space: nowrap;
}

.explore-tags__chip--active {
  background: #F0294E;
  color: #fff;
}

.explore-tags__chip:active {
  transform: scale(0.95);
}

/* ── 主內容 ─────────────────────────────────────────────── */
.explore-content {
  flex: 1;
  padding: 8px 16px 16px;
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* ── 骨架屏 ─────────────────────────────────────────────── */
.explore-skeleton {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 0;
  border-bottom: 0.5px solid #F1F5F9;
}

.explore-skeleton__avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  flex-shrink: 0;
}

.explore-skeleton__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.explore-skeleton__line {
  height: 12px;
  border-radius: 6px;
  background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

.explore-skeleton__line--name { width: 55%; }
.explore-skeleton__line--sub  { width: 40%; }
.explore-skeleton__line--badges { width: 65%; height: 18px; }

.explore-skeleton__right {
  width: 40px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-end;
}

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── 無限滾動 ─────────────────────────────────────────────── */
.explore-sentinel {
  height: 1px;
}

.explore-loading-more {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 20px 0;
  color: #94A3B8;
  font-size: 13px;
}

.explore-loading-more__spinner {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid #E2E8F0;
  border-top-color: #F0294E;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.explore-end-hint {
  text-align: center;
  padding: 20px 0;
  font-size: 12px;
  color: #CBD5E1;
  letter-spacing: 0.3px;
}
</style>
