<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useChatStore } from '@/stores/chat'
import TopBar from '@/components/layout/TopBar.vue'
import type { MockConversation } from '@/mocks/chats'

const router = useRouter()
const chatStore = useChatStore()

const conversations = ref<MockConversation[]>([])
const isLoading = ref(true)

onMounted(async () => {
  // Mock fetch
  const { mockFetchConversations } = await import('@/mocks/chats')
  await new Promise(r => setTimeout(r, 400))
  conversations.value = mockFetchConversations()

  // 同步到 store（for BottomNav badge）
  chatStore.setConversations(
    conversations.value.map(c => ({
      id: c.id,
      targetUser: c.targetUser,
      lastMessage: c.lastMessage,
      lastMessageAt: c.lastMessageAt,
      unreadCount: c.unreadCount,
    }))
  )

  isLoading.value = false
})

function goToChat(conversationId: number) {
  router.push(`/app/messages/${conversationId}`)
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
  return new Date(iso).toLocaleDateString('zh-TW', { month: 'short', day: 'numeric' })
}
</script>

<template>
  <div class="messages-view">
    <TopBar title="訊息" />

    <div class="messages-body">
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

      <!-- Empty -->
      <div v-else-if="conversations.length === 0" class="messages-empty">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <p class="messages-empty__text">還沒有任何對話</p>
        <button class="messages-empty__btn" @click="router.push('/app/explore')">去探索</button>
      </div>

      <!-- Conversation List -->
      <template v-else>
        <div
          v-for="conv in conversations"
          :key="conv.id"
          class="chat-card"
          @click="goToChat(conv.id)"
        >
          <div class="chat-card__avatar-wrap">
            <img :src="conv.targetUser.avatar" :alt="conv.targetUser.nickname" class="chat-card__avatar" />
            <span v-if="conv.targetUser.isOnline" class="chat-card__online-dot" />
          </div>
          <div class="chat-card__body">
            <div class="chat-card__row">
              <span class="chat-card__name">{{ conv.targetUser.nickname }}</span>
              <span class="chat-card__time">{{ timeAgo(conv.lastMessageAt) }}</span>
            </div>
            <div class="chat-card__row">
              <span class="chat-card__msg" :class="{ 'chat-card__msg--unread': conv.unreadCount > 0 }">
                {{ conv.lastMessage }}
              </span>
              <span v-if="conv.unreadCount > 0" class="chat-card__badge">
                {{ conv.unreadCount > 99 ? '99+' : conv.unreadCount }}
              </span>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.messages-view {
  display: flex;
  flex-direction: column;
  flex: 1;
  background: #fff;
}

.messages-body { flex: 1; }

/* ── Skeleton ────────────────────────────────────────────── */
.chat-skeleton {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 0.5px solid #F3F4F6;
}

.chat-skeleton__avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  flex-shrink: 0;
}

.chat-skeleton__text { flex: 1; display: flex; flex-direction: column; gap: 8px; }

.chat-skeleton__line {
  height: 12px;
  border-radius: 6px;
  background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

.chat-skeleton__line--name { width: 40%; }
.chat-skeleton__line--msg { width: 70%; }

@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* ── Empty ───────────────────────────────────────────────── */
.messages-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 64px 0;
}

.messages-empty__text { font-size: 14px; color: #9CA3AF; }

.messages-empty__btn {
  height: 36px;
  padding: 0 20px;
  border-radius: 9999px;
  border: none;
  background: #F0294E;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

/* ── Chat Card ───────────────────────────────────────────── */
.chat-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  cursor: pointer;
  transition: background 0.15s;
  border-bottom: 0.5px solid #F3F4F6;
}

.chat-card:active { background: #F9FAFB; }

.chat-card__avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.chat-card__avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
  background: #F1F5F9;
}

.chat-card__online-dot {
  position: absolute;
  bottom: 1px;
  right: 1px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #22C55E;
  border: 2px solid #fff;
}

.chat-card__body { flex: 1; min-width: 0; }

.chat-card__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.chat-card__row + .chat-card__row { margin-top: 4px; }

.chat-card__name {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-card__time {
  font-size: 12px;
  color: #9CA3AF;
  flex-shrink: 0;
}

.chat-card__msg {
  font-size: 13px;
  color: #6B7280;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
}

.chat-card__msg--unread {
  font-weight: 600;
  color: #111827;
}

.chat-card__badge {
  flex-shrink: 0;
  min-width: 18px;
  height: 18px;
  border-radius: 9999px;
  background: #F0294E;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
}
</style>
