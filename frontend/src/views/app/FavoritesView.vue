<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import BottomNav from '@/components/layout/BottomNav.vue'
import client from '@/api/client'

const router = useRouter()

interface FavoriteUser {
  id: number
  nickname: string
  avatar_url: string | null
  credit_score: number
  last_active_at: string | null
  followed_at: string
}

const users = ref<FavoriteUser[]>([])
const isLoading = ref(true)
const page = ref(1)
const hasMore = ref(true)

async function fetchFavorites(loadMore = false) {
  if (loadMore) page.value++
  else { page.value = 1; users.value = [] }
  isLoading.value = true
  try {
    const res = await client.get('/users/me/following', { params: { page: page.value, per_page: 20 } })
    const items = res.data.data?.users ?? []
    if (loadMore) users.value.push(...items)
    else users.value = items
    hasMore.value = items.length >= 20
  } catch { /* ignore */ }
  isLoading.value = false
}

async function removeFavorite(userId: number) {
  try {
    await client.delete(`/users/${userId}/follow`)
    users.value = users.value.filter(u => u.id !== userId)
  } catch { /* ignore */ }
}

function goProfile(id: number) { router.push(`/app/profiles/${id}`) }

onMounted(() => fetchFavorites())
</script>

<template>
  <div class="fav-page">
    <header class="fav-header">
      <button class="back-btn" @click="router.back()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      </button>
      <span class="page-title">我的收藏</span>
      <div style="width:40px" />
    </header>

    <!-- Loading -->
    <div v-if="isLoading && users.length === 0" class="fav-loading">
      <div v-for="i in 5" :key="i" class="skeleton-card" />
    </div>

    <!-- Empty -->
    <div v-else-if="users.length === 0" class="fav-empty">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#E5E7EB" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      <p>你還沒有收藏任何人</p>
      <button class="cta-btn" @click="router.push('/app/explore')">去探索</button>
    </div>

    <!-- List -->
    <div v-else class="fav-list">
      <div v-for="user in users" :key="user.id" class="fav-card" @click="goProfile(user.id)">
        <div class="fav-avatar">
          <img v-if="user.avatar_url" :src="user.avatar_url" alt="" />
          <div v-else class="fav-avatar-placeholder">{{ user.nickname?.[0] ?? '?' }}</div>
        </div>
        <div class="fav-info">
          <div class="fav-name">{{ user.nickname }}</div>
          <div class="fav-meta">誠信 {{ user.credit_score }} · {{ user.last_active_at ? '最近上線' : '離線' }}</div>
        </div>
        <button class="unfav-btn" @click.stop="removeFavorite(user.id)" title="取消收藏">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="#F0294E" stroke="#F0294E" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </button>
      </div>

      <button v-if="hasMore" class="load-more" @click="fetchFavorites(true)" :disabled="isLoading">
        {{ isLoading ? '載入中...' : '載入更多' }}
      </button>
    </div>
    <BottomNav />
  </div>
</template>

<style scoped>
.fav-page { min-height: 100svh; background: #F9F9FB; padding-bottom: calc(64px + env(safe-area-inset-bottom)); }
.fav-header { position: sticky; top: 0; z-index: 10; background: #fff; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; height: 52px; }
.back-btn { width: 40px; height: 40px; border-radius: 10px; background: transparent; border: none; display: flex; align-items: center; justify-content: center; color: #6B7280; cursor: pointer; }
.page-title { font-size: 16px; font-weight: 600; color: #111827; }
.fav-loading { padding: 16px; }
.skeleton-card { height: 72px; background: #F3F4F6; border-radius: 14px; margin-bottom: 8px; animation: shimmer 1.5s infinite; }
@keyframes shimmer { 0%,100% { opacity: 1 } 50% { opacity: 0.5 } }
.fav-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 80px 24px; color: #9CA3AF; gap: 12px; }
.fav-empty p { font-size: 14px; }
.cta-btn { background: #F0294E; color: #fff; border: none; border-radius: 10px; padding: 10px 24px; font-size: 14px; font-weight: 600; cursor: pointer; }
.fav-list { padding: 12px 16px; }
.fav-card { display: flex; align-items: center; gap: 12px; background: #fff; border-radius: 14px; padding: 12px; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); cursor: pointer; }
.fav-avatar { width: 48px; height: 48px; border-radius: 50%; overflow: hidden; flex-shrink: 0; }
.fav-avatar img { width: 100%; height: 100%; object-fit: cover; }
.fav-avatar-placeholder { width: 100%; height: 100%; background: #F3F4F6; display: flex; align-items: center; justify-content: center; color: #9CA3AF; font-weight: 600; font-size: 18px; }
.fav-info { flex: 1; min-width: 0; }
.fav-name { font-size: 15px; font-weight: 600; color: #111827; }
.fav-meta { font-size: 12px; color: #9CA3AF; margin-top: 2px; }
.unfav-btn { width: 36px; height: 36px; border-radius: 50%; border: none; background: #FFF1F3; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.load-more { width: 100%; padding: 12px; background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; color: #6B7280; font-size: 14px; cursor: pointer; margin-top: 8px; }
@media (min-width: 768px) { .fav-page { max-width: 680px; margin: 0 auto; } }
@media (min-width: 1024px) { .fav-page { max-width: 560px; } }
</style>
