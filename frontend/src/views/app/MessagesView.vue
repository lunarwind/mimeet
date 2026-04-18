<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useChatStore } from '@/stores/chat'
import { fetchConversations, markAllConversationsRead } from '@/api/chat'
import AppLayout from '@/components/layout/AppLayout.vue'
import type { Conversation } from '@/types/chat'

const router = useRouter()
const chatStore = useChatStore()

const conversations = ref<Conversation[]>([])
const isLoading = ref(true)
const searchQuery = ref('')

// ── Swipe 左滑 ──────────────────────────────────────────
const swipedId = ref<number | null>(null)
let touchStartX = 0

function onTouchStart(e: TouchEvent) { touchStartX = e.touches[0]?.clientX ?? 0 }
function onTouchEnd(e: TouchEvent, id: number) {
  const diff = touchStartX - (e.changedTouches[0]?.clientX ?? 0)
  swipedId.value = diff > 60 ? id : null
}
function closeSwiped() { swipedId.value = null }

// ── 搜尋過濾 ──────────────────────────────────────────────
const filteredConversations = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return conversations.value
  return conversations.value.filter(c =>
    c.targetUser.nickname.toLowerCase().includes(q) ||
    c.lastMessage.toLowerCase().includes(q)
  )
})

// ── 載入資料 ──────────────────────────────────────────────
onMounted(async () => {
  const data = await fetchConversations()
  conversations.value = data
  chatStore.setConversations(data)
  isLoading.value = false
})

// ── 操作 ──────────────────────────────────────────────────
function goToChat(id: number) {
  router.push(`/app/messages/${id}`)
}

function handleBlock(id: number) {
  conversations.value = conversations.value.filter(c => c.id !== id)
  swipedId.value = null
}

function handleDelete(id: number) {
  conversations.value = conversations.value.filter(c => c.id !== id)
  swipedId.value = null
}

async function handleMarkAllRead() {
  try {
    await markAllConversationsRead()
    chatStore.markAllAsRead()
    conversations.value.forEach(c => { c.unreadCount = 0 })
  } catch { /* ignore */ }
}

function timeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return '剛剛'
  if (mins < 60) return `${mins}分`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}時`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}天`
  return new Date(iso).toLocaleDateString('zh-TW', { month: 'numeric', day: 'numeric' })
}
</script>

<template>
  <AppLayout title="訊息">
    <template #topbar-right>
      <button
        v-if="chatStore.totalUnread > 0"
        class="mark-all-read-btn"
        @click="handleMarkAllRead"
      >
        全部已讀
      </button>
    </template>

    <!-- 搜尋欄 -->
    <div class="msg-search">
      <div class="msg-search__inner">
        <svg class="msg-search__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="searchQuery" type="search" class="msg-search__input" placeholder="搜尋暱稱或訊息…" />
      </div>
    </div>

    <!-- Loading -->
    <template v-if="isLoading">
      <div v-for="n in 5" :key="n" class="chat-skeleton">
        <div class="chat-skeleton__avatar" />
        <div class="chat-skeleton__text">
          <div class="chat-skeleton__line chat-skeleton__line--name" />
          <div class="chat-skeleton__line chat-skeleton__line--msg" />
        </div>
      </div>
    </template>

    <!-- 空狀態 -->
    <div v-else-if="filteredConversations.length === 0 && !searchQuery" class="msg-empty">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <p class="msg-empty__text">還沒有任何對話，去探索吧！</p>
      <button class="msg-empty__btn" @click="router.push('/app/explore')">去探索</button>
    </div>

    <!-- 搜尋無結果 -->
    <div v-else-if="filteredConversations.length === 0 && searchQuery" class="msg-empty">
      <p class="msg-empty__text">找不到「{{ searchQuery }}」</p>
    </div>

    <!-- 對話列表 -->
    <div v-else class="chat-list" @click="closeSwiped">
      <div
        v-for="conv in filteredConversations"
        :key="conv.id"
        class="chat-card-wrap"
      >
        <div
          class="chat-card"
          :class="{ 'chat-card--unread': conv.unreadCount > 0, 'chat-card--swiped': swipedId === conv.id }"
          @click="goToChat(conv.id)"
          @touchstart="onTouchStart"
          @touchend="onTouchEnd($event, conv.id)"
        >
          <div class="chat-card__avatar-wrap">
            <img :src="conv.targetUser.avatarUrl ?? '/default-avatar.svg'" :alt="conv.targetUser.nickname" class="chat-card__avatar" />
            <span v-if="conv.targetUser.isOnline" class="chat-card__online" />
          </div>
          <div class="chat-card__body">
            <div class="chat-card__row">
              <span class="chat-card__name" :class="{ 'chat-card__name--bold': conv.unreadCount > 0 }">{{ conv.targetUser.nickname }}</span>
              <span class="chat-card__time">{{ timeAgo(conv.lastMessageAt) }}</span>
            </div>
            <div class="chat-card__row">
              <span class="chat-card__msg">{{ conv.lastMessage }}</span>
              <span v-if="conv.unreadCount > 0" class="chat-card__badge">{{ conv.unreadCount > 99 ? '99+' : conv.unreadCount }}</span>
            </div>
          </div>
        </div>
        <!-- 左滑操作 -->
        <div v-if="swipedId === conv.id" class="swipe-actions">
          <button class="swipe-btn swipe-btn--block" @click.stop="handleBlock(conv.id)">封鎖</button>
          <button class="swipe-btn swipe-btn--delete" @click.stop="handleDelete(conv.id)">刪除</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* ── TopBar ──────────────────────────────────────────────── */
.mark-all-read-btn { background:none; border:none; color:#F0294E; font-size:13px; font-weight:600; cursor:pointer; padding:4px 8px; }

/* ── Search ──────────────────────────────────────────────── */
.msg-search { padding:8px 16px; background:#fff; border-bottom:0.5px solid #F1F5F9; }
.msg-search__inner { display:flex; align-items:center; gap:8px; height:40px; background:#F1F5F9; border-radius:9999px; padding:0 14px; }
.msg-search__icon { flex-shrink:0; color:#94A3B8; }
.msg-search__input { flex:1; border:none; background:transparent; font-size:14px; color:#111827; outline:none; min-width:0; }
.msg-search__input::placeholder { color:#94A3B8; }
.msg-search__input::-webkit-search-cancel-button { display:none; }

/* ── Skeleton ────────────────────────────────────────────── */
.chat-skeleton { display:flex; align-items:center; gap:12px; padding:14px 16px; border-bottom:0.5px solid #F3F4F6; }
.chat-skeleton__avatar { width:48px; height:48px; border-radius:50%; background:linear-gradient(90deg,#F1F5F9 25%,#E2E8F0 50%,#F1F5F9 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; flex-shrink:0; }
.chat-skeleton__text { flex:1; display:flex; flex-direction:column; gap:8px; }
.chat-skeleton__line { height:12px; border-radius:6px; background:linear-gradient(90deg,#F1F5F9 25%,#E2E8F0 50%,#F1F5F9 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; }
.chat-skeleton__line--name { width:35%; }
.chat-skeleton__line--msg { width:65%; }
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

/* ── Empty ───────────────────────────────────────────────── */
.msg-empty { display:flex; flex-direction:column; align-items:center; gap:12px; padding:64px 20px; text-align:center; }
.msg-empty__text { font-size:14px; color:#9CA3AF; }
.msg-empty__btn { height:36px; padding:0 20px; border-radius:9999px; border:none; background:#F0294E; color:#fff; font-size:13px; font-weight:600; cursor:pointer; }

/* ── Chat Card ───────────────────────────────────────────── */
.chat-list { background:#fff; }
.chat-card-wrap { position:relative; overflow:hidden; }
.chat-card { display:flex; align-items:center; gap:12px; padding:14px 16px; height:72px; box-sizing:border-box; cursor:pointer; border-bottom:0.5px solid #F3F4F6; transition:transform 0.2s ease,background 0.15s; background:#fff; }
.chat-card--unread { background:#F9FAFB; }
.chat-card--swiped { transform:translateX(-140px); }
.chat-card:active { background:#F3F4F6; }

.chat-card__avatar-wrap { position:relative; flex-shrink:0; }
.chat-card__avatar { width:48px; height:48px; border-radius:50%; object-fit:cover; background:#F1F5F9; }
.chat-card__online { position:absolute; bottom:1px; right:1px; width:10px; height:10px; border-radius:50%; background:#22C55E; border:2px solid #fff; }

.chat-card__body { flex:1; min-width:0; }
.chat-card__row { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.chat-card__row + .chat-card__row { margin-top:4px; }
.chat-card__name { font-size:15px; font-weight:400; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.chat-card__name--bold { font-weight:700; }
.chat-card__time { font-size:12px; color:#9CA3AF; flex-shrink:0; }
.chat-card__msg { font-size:13px; color:#6B7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; }
.chat-card__badge { flex-shrink:0; min-width:18px; height:18px; border-radius:9999px; background:#F0294E; color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; padding:0 4px; }

/* ── Swipe Actions ───────────────────────────────────────── */
.swipe-actions { position:absolute; right:0; top:0; bottom:0; display:flex; }
.swipe-btn { width:70px; border:none; color:#fff; font-size:12px; font-weight:600; cursor:pointer; }
.swipe-btn--block { background:#F59E0B; }
.swipe-btn--delete { background:#EF4444; }
</style>
