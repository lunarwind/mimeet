<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import TopBar from '@/components/layout/TopBar.vue'
import type { MockMessage } from '@/mocks/chats'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()

const conversationId = computed(() => Number(route.params.id))
const messages = ref<MockMessage[]>([])
const isLoading = ref(true)
const inputText = ref('')
const messagesEndRef = ref<HTMLElement | null>(null)
const isSending = ref(false)

// ── 對方用戶資訊 ──────────────────────────────────────────
const otherUser = computed(() => {
  const conv = chatStore.conversations.find(c => c.id === conversationId.value)
  return conv?.targetUser ?? { id: 0, nickname: '用戶', avatar: 'https://i.pravatar.cc/150?img=0', isOnline: false }
})

const myId = computed(() => authStore.user?.id ?? 99)

onMounted(async () => {
  const { mockFetchMessages } = await import('@/mocks/chats')
  await new Promise(r => setTimeout(r, 300))
  messages.value = mockFetchMessages(conversationId.value, myId.value)
  chatStore.markAsRead(conversationId.value)
  isLoading.value = false
  await nextTick()
  scrollToBottom()
})

function scrollToBottom() {
  messagesEndRef.value?.scrollIntoView({ behavior: 'smooth' })
}

async function handleSend() {
  const text = inputText.value.trim()
  if (!text || isSending.value) return

  isSending.value = true
  const newMsg: MockMessage = {
    id: Date.now(),
    senderId: myId.value,
    content: text,
    messageType: 'text',
    sentAt: new Date().toISOString(),
    isRead: false,
  }

  messages.value.push(newMsg)
  inputText.value = ''
  await nextTick()
  scrollToBottom()

  // Mock: 模擬對方回覆
  setTimeout(async () => {
    const reply: MockMessage = {
      id: Date.now() + 1,
      senderId: otherUser.value.id,
      content: randomReply(),
      messageType: 'text',
      sentAt: new Date().toISOString(),
      isRead: false,
    }
    messages.value.push(reply)
    await nextTick()
    scrollToBottom()
  }, 1500 + Math.random() * 2000)

  isSending.value = false
}

function randomReply(): string {
  const replies = [
    '哈哈，真有趣 😄',
    '好啊！',
    '聽起來不錯耶',
    '改天再聊～',
    '你覺得呢？',
    '太棒了 🎉',
    '我也是這麼想的',
  ]
  return replies[Math.floor(Math.random() * replies.length)]
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' })
}

function goToProfile() {
  router.push(`/app/profiles/${otherUser.value.id}`)
}
</script>

<template>
  <div class="chat-view">
    <!-- TopBar -->
    <TopBar show-back>
      <template #left>
        <button class="chat-topbar__back" @click="router.back()" aria-label="返回">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m15 18-6-6 6-6" />
          </svg>
        </button>
        <div class="chat-topbar__user" @click="goToProfile">
          <img :src="otherUser.avatar" :alt="otherUser.nickname" class="chat-topbar__avatar" />
          <div>
            <span class="chat-topbar__name">{{ otherUser.nickname }}</span>
            <span v-if="otherUser.isOnline" class="chat-topbar__status">在線</span>
          </div>
        </div>
      </template>
    </TopBar>

    <!-- Messages Area -->
    <div class="chat-messages">
      <div v-if="isLoading" class="chat-loading"><span class="spinner" /></div>

      <template v-else>
        <div
          v-for="msg in messages"
          :key="msg.id"
          class="msg-row"
          :class="{ 'msg-row--mine': msg.senderId === myId }"
        >
          <img
            v-if="msg.senderId !== myId"
            :src="otherUser.avatar"
            class="msg-row__avatar"
            alt=""
          />
          <div class="msg-bubble" :class="msg.senderId === myId ? 'msg-bubble--mine' : 'msg-bubble--other'">
            <p class="msg-bubble__text">{{ msg.content }}</p>
            <span class="msg-bubble__time">
              {{ formatTime(msg.sentAt) }}
              <template v-if="msg.senderId === myId">
                {{ msg.isRead ? ' ✓✓' : ' ✓' }}
              </template>
            </span>
          </div>
        </div>
        <div ref="messagesEndRef" />
      </template>
    </div>

    <!-- Input Area -->
    <div class="chat-input">
      <input
        v-model="inputText"
        type="text"
        class="chat-input__field"
        placeholder="輸入訊息…"
        @keydown.enter.prevent="handleSend"
      />
      <button class="chat-input__send" :disabled="!inputText.trim()" @click="handleSend">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.chat-view {
  display: flex;
  flex-direction: column;
  height: 100dvh;
  background: #F9F9FB;
}

/* ── TopBar Override ─────────────────────────────────────── */
.chat-topbar__back {
  background: none;
  border: none;
  padding: 4px;
  margin: -4px;
  cursor: pointer;
  color: #374151;
  display: flex;
  align-items: center;
}

.chat-topbar__user {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  margin-left: 4px;
}

.chat-topbar__avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
}

.chat-topbar__name {
  display: block;
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.chat-topbar__status {
  display: block;
  font-size: 11px;
  color: #22C55E;
  font-weight: 500;
}

/* ── Messages ────────────────────────────────────────────── */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.chat-loading {
  display: flex;
  justify-content: center;
  padding: 48px 0;
}

.spinner {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2.5px solid #E5E7EB;
  border-top-color: #F0294E;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* ── Message Row ─────────────────────────────────────────── */
.msg-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  max-width: 80%;
}

.msg-row--mine {
  align-self: flex-end;
  flex-direction: row-reverse;
}

.msg-row__avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

/* ── Bubble ──────────────────────────────────────────────── */
.msg-bubble {
  padding: 10px 14px;
  border-radius: 16px;
  max-width: 100%;
  word-break: break-word;
}

.msg-bubble--other {
  background: #fff;
  border: 1px solid #F1F5F9;
  border-bottom-left-radius: 4px;
}

.msg-bubble--mine {
  background: #F0294E;
  color: #fff;
  border-bottom-right-radius: 4px;
}

.msg-bubble__text {
  font-size: 14px;
  line-height: 1.5;
}

.msg-bubble__time {
  display: block;
  font-size: 10px;
  margin-top: 4px;
  text-align: right;
}

.msg-bubble--other .msg-bubble__time { color: #9CA3AF; }
.msg-bubble--mine .msg-bubble__time { color: rgba(255,255,255,0.7); }

/* ── Input ───────────────────────────────────────────────── */
.chat-input {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: #fff;
  border-top: 1px solid #F1F5F9;
  padding-bottom: max(10px, env(safe-area-inset-bottom));
}

.chat-input__field {
  flex: 1;
  height: 40px;
  border: 1.5px solid #E5E7EB;
  border-radius: 20px;
  padding: 0 16px;
  font-size: 14px;
  color: #111827;
  background: #F9FAFB;
  outline: none;
}

.chat-input__field:focus {
  border-color: #F0294E;
  background: #fff;
}

.chat-input__send {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: #F0294E;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.15s;
}

.chat-input__send:active { transform: scale(0.93); }
.chat-input__send:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
