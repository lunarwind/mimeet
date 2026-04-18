<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BottomNav from '@/components/layout/BottomNav.vue'
import client from '@/api/client'

const router = useRouter()
const authStore = useAuthStore()
const isPaid = computed(() => (authStore.user?.membership_level ?? 0) >= 2)

interface Visitor {
  id: number
  nickname: string
  avatar_url: string | null
  credit_score: number
  visited_at: string
}

const visitors = ref<Visitor[]>([])
const isLoading = ref(true)

async function fetchVisitors() {
  isLoading.value = true
  try {
    const res = await client.get('/users/me/visitors', { params: { per_page: 50 } })
    visitors.value = res.data.data?.visitors ?? []
  } catch { /* ignore */ }
  isLoading.value = false
}

function goProfile(id: number) {
  if (!isPaid.value) return
  router.push(`/app/profiles/${id}`)
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 60) return `${mins} 分鐘前`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} 小時前`
  return `${Math.floor(hours / 24)} 天前`
}

onMounted(() => fetchVisitors())
</script>

<template>
  <div class="vis-page">
    <header class="vis-header">
      <button class="back-btn" @click="router.back()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      </button>
      <span class="page-title">誰來看我</span>
      <div style="width:40px" />
    </header>

    <!-- Loading -->
    <div v-if="isLoading" class="vis-loading">
      <div v-for="i in 5" :key="i" class="skeleton-card" />
    </div>

    <!-- Empty -->
    <div v-else-if="visitors.length === 0" class="vis-empty">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#E5E7EB" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
      <p>還沒有人查看你的資料</p>
      <span class="hint">完善個人資料可以增加曝光度</span>
    </div>

    <!-- List -->
    <div v-else class="vis-list">
      <!-- Upgrade banner for non-paid -->
      <div v-if="!isPaid" class="upgrade-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <span>升級付費會員即可查看完整訪客資料</span>
        <button @click="router.push('/app/shop')">升級</button>
      </div>

      <div v-for="v in visitors" :key="v.id" class="vis-card" :class="{ blurred: !isPaid }" @click="goProfile(v.id)">
        <div class="vis-avatar">
          <img v-if="v.avatar_url" :src="v.avatar_url" alt="" :class="{ 'blur-img': !isPaid }" />
          <div v-else class="vis-avatar-placeholder" :class="{ 'blur-img': !isPaid }">{{ v.nickname?.[0] ?? '?' }}</div>
        </div>
        <div class="vis-info">
          <div class="vis-name">{{ isPaid ? v.nickname : '***' }}</div>
          <div class="vis-time">{{ timeAgo(v.visited_at) }}</div>
        </div>
      </div>
    </div>
    <BottomNav />
  </div>
</template>

<style scoped>
.vis-page { min-height: 100svh; background: #F9F9FB; padding-bottom: calc(64px + env(safe-area-inset-bottom)); }
.vis-header { position: sticky; top: 0; z-index: 10; background: #fff; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; height: 52px; }
.back-btn { width: 40px; height: 40px; border-radius: 10px; background: transparent; border: none; display: flex; align-items: center; justify-content: center; color: #6B7280; cursor: pointer; }
.page-title { font-size: 16px; font-weight: 600; color: #111827; }
.vis-loading { padding: 16px; }
.skeleton-card { height: 64px; background: #F3F4F6; border-radius: 14px; margin-bottom: 8px; animation: shimmer 1.5s infinite; }
@keyframes shimmer { 0%,100% { opacity: 1 } 50% { opacity: 0.5 } }
.vis-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 80px 24px; color: #9CA3AF; gap: 8px; }
.vis-empty p { font-size: 14px; color: #6B7280; }
.hint { font-size: 12px; }
.upgrade-banner { display: flex; align-items: center; gap: 8px; background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 10px; padding: 10px 14px; margin: 12px 16px; font-size: 13px; color: #92400E; }
.upgrade-banner button { margin-left: auto; background: #F0294E; color: #fff; border: none; border-radius: 8px; padding: 6px 16px; font-size: 12px; font-weight: 600; cursor: pointer; }
.vis-list { padding: 0 16px 16px; }
.vis-card { display: flex; align-items: center; gap: 12px; background: #fff; border-radius: 14px; padding: 12px; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); cursor: pointer; }
.vis-card.blurred { cursor: default; }
.vis-avatar { width: 48px; height: 48px; border-radius: 50%; overflow: hidden; flex-shrink: 0; }
.vis-avatar img { width: 100%; height: 100%; object-fit: cover; }
.vis-avatar-placeholder { width: 100%; height: 100%; background: #F3F4F6; display: flex; align-items: center; justify-content: center; color: #9CA3AF; font-weight: 600; font-size: 18px; }
.blur-img { filter: blur(8px); }
.vis-info { flex: 1; }
.vis-name { font-size: 15px; font-weight: 600; color: #111827; }
.vis-time { font-size: 12px; color: #9CA3AF; margin-top: 2px; }
@media (min-width: 768px) { .vis-page { max-width: 680px; margin: 0 auto; } }
@media (min-width: 1024px) { .vis-page { max-width: 560px; } }
</style>
