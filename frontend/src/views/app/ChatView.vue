<script setup lang="ts">
import { ref, computed, onMounted, nextTick, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import { useChat } from '@/composables/useChat'
import { fetchMessages, markConversationRead, fetchConversationInfo, sendMessage as apiSendMessage } from '@/api/chat'
import MessageBubble from '@/components/chat/MessageBubble.vue'
import ChatInput from '@/components/chat/ChatInput.vue'
import type { ChatMessage } from '@/types/chat'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()
const { isConnected, connect, disconnect, sendMessage: wsSend, onMessage } = useChat()

const conversationId = computed(() => Number(route.params.id))
const isLoading = ref(true)
const localMessages = ref<ChatMessage[]>([])
const messagesEndRef = ref<HTMLElement | null>(null)

// ── 對方資訊 ──────────────────────────────────────────────
const otherUser = ref<{
  id: number; nickname: string; avatarUrl: string | null;
  onlineStatus: string | null; lastActiveLabel: string | null; creditScore: number
}>({ id: 0, nickname: '', avatarUrl: null, onlineStatus: null, lastActiveLabel: null, creditScore: 0 })

// ── 日期分隔 ──────────────────────────────────────────────
function dateSeparator(msg: ChatMessage, prev: ChatMessage | undefined): string | null {
  if (!msg.createdAt) return null
  const d = new Date(msg.createdAt)
  if (isNaN(d.getTime())) return null
  const pd = prev?.createdAt ? new Date(prev.createdAt) : null
  if (pd && !isNaN(pd.getTime()) && d.toDateString() === pd.toDateString()) return null
  const today = new Date()
  const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1)
  if (d.toDateString() === today.toDateString()) return '今天'
  if (d.toDateString() === yesterday.toDateString()) return '昨天'
  return d.toLocaleDateString('zh-TW', { month: 'long', day: 'numeric' })
}

// ── 滾動到底 ──────────────────────────────────────────────
async function scrollToBottom() {
  await nextTick()
  messagesEndRef.value?.scrollIntoView({ behavior: 'smooth' })
}

// ── 載入 ──────────────────────────────────────────────────
onMounted(async () => {
  // Fetch other user info and messages in parallel
  const [userData, data] = await Promise.all([
    fetchConversationInfo(conversationId.value).catch(() => null),
    fetchMessages(conversationId.value),
  ])
  if (userData) otherUser.value = userData
  localMessages.value = data
  chatStore.markAsRead(conversationId.value)
  markConversationRead(conversationId.value)
  isLoading.value = false

  await scrollToBottom()

  connect(conversationId.value)
  onMessage((msg) => {
    localMessages.value.push(msg)
    scrollToBottom()
  })
})

onUnmounted(() => disconnect())

// ── 發送 ──────────────────────────────────────────────────
const isSending = ref(false)

async function handleSend(content: string) {
  if (isSending.value) return
  isSending.value = true

  // Optimistic: show immediately
  const tempId = Date.now()
  const tempMsg: ChatMessage = {
    id: tempId,
    conversationId: conversationId.value,
    senderId: authStore.user?.id ?? 0,
    type: 'text',
    content,
    status: 'sending',
    createdAt: new Date().toISOString(),
    isOwn: true,
  }
  localMessages.value.push(tempMsg)
  scrollToBottom()

  try {
    const sent = await apiSendMessage(conversationId.value, content)
    // Replace temp message with server response
    const idx = localMessages.value.findIndex(m => m.id === tempId)
    if (idx !== -1) {
      localMessages.value[idx] = { ...sent, isOwn: true, status: 'sent' }
    }
    chatStore.updateLastMessage(conversationId.value, content)
  } catch (err: any) {
    // Mark as failed
    const idx = localMessages.value.findIndex(m => m.id === tempId)
    const failedMsg = idx !== -1 ? localMessages.value[idx] : undefined
    if (failedMsg) failedMsg.status = 'failed'

    const msg = err.response?.data?.error?.message ?? err.response?.data?.message ?? '發送失敗'
    const { useUiStore } = await import('@/stores/ui')
    useUiStore().showToast(msg, 'error')
  } finally {
    isSending.value = false
  }
}

function goBack() { router.back() }
function goProfile() { router.push(`/app/profiles/${otherUser.value.id}`) }
</script>

<template>
  <div class="chat-view">
    <!-- TopBar -->
    <header class="chat-topbar">
      <button class="chat-topbar__back" @click="goBack" aria-label="返回">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <span class="chat-topbar__logo" @click="$router.push('/app/explore')"><span style="color:#F0294E">Mi</span><span style="color:#111827">Meet</span></span>
      <div class="chat-topbar__user" @click="goProfile">
        <img :src="otherUser.avatarUrl ?? '/default-avatar.svg'" class="chat-topbar__avatar" alt="" />
        <div>
          <span class="chat-topbar__name">{{ otherUser.nickname || '用戶' }}</span>
          <span v-if="otherUser.lastActiveLabel" class="chat-topbar__status" :class="{ 'chat-topbar__status--on': otherUser.onlineStatus === 'online', 'chat-topbar__status--off': otherUser.onlineStatus !== 'online' }">{{ otherUser.lastActiveLabel }}</span>
        </div>
      </div>
      <button class="chat-topbar__info" @click="goProfile" aria-label="查看資訊">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      </button>
    </header>

    <!-- 訊息區 -->
    <div class="chat-messages">
      <div v-if="isLoading" class="chat-loading"><span class="spinner" /></div>
      <template v-else>
        <template v-for="(msg, i) in localMessages" :key="msg.id">
          <div v-if="dateSeparator(msg, localMessages[i - 1])" class="date-sep">
            <span>{{ dateSeparator(msg, localMessages[i - 1]) }}</span>
          </div>
          <MessageBubble :message="msg" :is-self="msg.isOwn" />
        </template>
        <div ref="messagesEndRef" class="scroll-anchor" />
      </template>
    </div>

    <!-- 輸入 -->
    <ChatInput @send="handleSend" />
  </div>
</template>

<style scoped>
/* ── Base (mobile-first) ─────────────────────────────────── */
.chat-view { display:flex; flex-direction:column; height:calc(100dvh - 64px - env(safe-area-inset-bottom)); background:#fff; }

/* ── TopBar ──────────────────────────────────────────────── */
.chat-topbar { display:flex; align-items:center; gap:10px; height:56px; padding:0 12px; background:#fff; border-bottom:0.5px solid #E5E7EB; flex-shrink:0; }
.chat-topbar__back { background:none; border:none; padding:4px; cursor:pointer; color:#374151; display:flex; }
.chat-topbar__logo { font-family:'Noto Serif TC',serif; font-size:18px; font-weight:600; letter-spacing:-0.5px; cursor:pointer; line-height:1; display:none; }
@media (min-width: 768px) { .chat-topbar__logo { display:inline; } }
.chat-topbar__user { display:flex; align-items:center; gap:10px; flex:1; min-width:0; cursor:pointer; }
.chat-topbar__avatar { width:36px; height:36px; border-radius:50%; object-fit:cover; flex-shrink:0; }
.chat-topbar__name { display:block; font-size:15px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.chat-topbar__status { display:block; font-size:11px; color:#9CA3AF; font-weight:500; }
.chat-topbar__status--on { color:#22C55E; }
.chat-topbar__info { background:none; border:none; padding:4px; cursor:pointer; color:#9CA3AF; display:flex; }

/* ── Messages ────────────────────────────────────────────── */
.chat-messages { flex:1; overflow-y:auto; padding:12px 16px; display:flex; flex-direction:column; gap:4px; -webkit-overflow-scrolling:touch; }
.chat-loading { display:flex; justify-content:center; padding:48px 0; }
.spinner { width:24px; height:24px; border-radius:50%; border:2.5px solid #E5E7EB; border-top-color:#F0294E; animation:spin 0.7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

.scroll-anchor { height:1px; flex-shrink:0; }

/* ── Date Separator ──────────────────────────────────────── */
.date-sep { display:flex; justify-content:center; padding:12px 0 8px; }
.date-sep span { font-size:11px; color:#9CA3AF; background:#F1F5F9; padding:2px 10px; border-radius:9999px; }

/* ── Tablet (768px+) ─────────────────────────────────────── */
@media (min-width: 768px) {
  .chat-topbar { padding:0 24px; }
  .chat-topbar__avatar { width:40px; height:40px; }
  .chat-topbar__name { font-size:16px; }
  .chat-messages { padding:16px 24px; gap:6px; }
}

/* ── Desktop (1024px+) ────────────────────────────────────── */
@media (min-width: 1024px) {
  .chat-view { max-width:960px; margin:0 auto; border-left:0.5px solid #E5E7EB; border-right:0.5px solid #E5E7EB; }
  .chat-topbar { padding:0 24px; }
  .chat-messages { padding:20px 32px; gap:6px; }
}

/* ── Large desktop (1440px+) ─────────────────────────────── */
@media (min-width: 1440px) {
  .chat-view { max-width:1100px; height:calc(100dvh - 100px); margin-top:8px; border-radius:16px; border:0.5px solid #E5E7EB; box-shadow:0 2px 16px rgba(0,0,0,0.06); }
}

/* ── 4K (1920px+) ────────────────────────────────────────── */
@media (min-width: 1920px) {
  .chat-view { max-width:1300px; }
}
</style>
