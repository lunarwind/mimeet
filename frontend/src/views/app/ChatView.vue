<script setup lang="ts">
import { ref, computed, onMounted, nextTick, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import { useChat } from '@/composables/useChat'
import {
  fetchMessages,
  markConversationRead,
  fetchConversationInfo,
  sendMessage as apiSendMessage,
  sendImageMessage,
  recallMessage as apiRecallMessage,
  searchMessages as apiSearchMessages,
} from '@/api/chat'
import MessageBubble from '@/components/chat/MessageBubble.vue'
import ChatInput from '@/components/chat/ChatInput.vue'
import type { ChatMessage } from '@/types/chat'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()
const { connect, disconnect, onMessage } = useChat()

const conversationId = computed(() => Number(route.params.id))
const isLoading = ref(true)
const localMessages = ref<ChatMessage[]>([])
const messagesEndRef = ref<HTMLElement | null>(null)

// ── 對方資訊 ──────────────────────────────────────────────
const otherUser = ref<{
  id: number; nickname: string; avatarUrl: string | null;
  onlineStatus: string | null; lastActiveLabel: string | null; creditScore: number
}>({ id: 0, nickname: '', avatarUrl: null, onlineStatus: null, lastActiveLabel: null, creditScore: 0 })

// ── 圖片預覽 ──────────────────────────────────────────────
const previewImageUrl = ref<string | null>(null)

// ── 搜尋 ──────────────────────────────────────────────────
const searchOpen = ref(false)
const searchKeyword = ref('')
const searchResults = ref<ChatMessage[]>([])
const searchLoading = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | undefined

function toggleSearch() {
  searchOpen.value = !searchOpen.value
  if (!searchOpen.value) {
    searchKeyword.value = ''
    searchResults.value = []
  }
}

watch(searchKeyword, (q) => {
  clearTimeout(searchTimer)
  if (!q.trim()) {
    searchResults.value = []
    return
  }
  searchLoading.value = true
  searchTimer = setTimeout(async () => {
    try {
      searchResults.value = await apiSearchMessages(conversationId.value, q.trim())
    } catch { searchResults.value = [] }
    finally { searchLoading.value = false }
  }, 300)
})

function jumpToMessage(id: number) {
  searchOpen.value = false
  searchResults.value = []
  searchKeyword.value = ''
  nextTick(() => {
    const el = document.querySelector(`[data-msg-id="${id}"]`) as HTMLElement | null
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    el?.classList.add('msg-highlight')
    setTimeout(() => el?.classList.remove('msg-highlight'), 1600)
  })
}

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
  const [userData, data] = await Promise.all([
    fetchConversationInfo(conversationId.value).catch(() => null),
    fetchMessages(conversationId.value),
  ])
  if (userData) otherUser.value = userData
  // 標註 isOwn
  const meId = authStore.user?.id ?? 0
  localMessages.value = data.map(m => ({ ...m, isOwn: m.senderId === meId }))
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

// ── 文字發送 ──────────────────────────────────────────────
const isSending = ref(false)

// F40-b 逆區間訊息
const showReverseModal = ref(false)
const showPointsInsufficientModal = ref(false)
const reverseInfo = ref<{ pointCost: number; currentBalance: number; canAfford: boolean } | null>(null)
const pendingContent = ref('')
const pendingTempId = ref<number | null>(null)

async function handleSend(content: string, usePoints = false) {
  if (isSending.value) return
  isSending.value = true

  // 若不是補送（usePoints 模式），push optimistic 訊息；否則沿用之前的 tempId
  let tempId: number
  if (!usePoints) {
    tempId = Date.now()
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
  } else {
    tempId = pendingTempId.value ?? Date.now()
  }

  try {
    const sent = await apiSendMessage(conversationId.value, content, usePoints)
    const idx = localMessages.value.findIndex(m => m.id === tempId)
    if (idx !== -1) {
      localMessages.value[idx] = { ...sent, isOwn: true, status: 'sent' }
    }
    chatStore.updateLastMessage(conversationId.value, content)
    pendingContent.value = ''
    pendingTempId.value = null
  } catch (err: any) {
    const resp = err.response?.data
    const status = err.response?.status

    // F40-b：分數不足，可用點數突破
    if (status === 403 && resp?.data?.can_use_points) {
      pendingContent.value = content
      pendingTempId.value = tempId
      reverseInfo.value = {
        pointCost: resp.data.point_cost,
        currentBalance: resp.data.current_balance,
        canAfford: resp.data.can_afford,
      }
      // 把 optimistic 訊息暫標 failed 以便辨識
      const idx = localMessages.value.findIndex(m => m.id === tempId)
      const t1 = idx !== -1 ? localMessages.value[idx] : undefined
      if (t1) t1.status = 'failed'
      showReverseModal.value = true
      return
    }

    // F40-b：use_points=true 送出時餘額不足
    if (status === 422 && resp?.code === 'INSUFFICIENT_POINTS') {
      const idx = localMessages.value.findIndex(m => m.id === tempId)
      const t2 = idx !== -1 ? localMessages.value[idx] : undefined
      if (t2) t2.status = 'failed'
      reverseInfo.value = {
        pointCost: resp.data?.required ?? 0,
        currentBalance: resp.data?.current_balance ?? 0,
        canAfford: false,
      }
      showPointsInsufficientModal.value = true
      return
    }

    const idx = localMessages.value.findIndex(m => m.id === tempId)
    const target = idx !== -1 ? localMessages.value[idx] : undefined
    if (target) target.status = 'failed'
    const msg = resp?.error?.message ?? resp?.message ?? '發送失敗'
    const { useUiStore } = await import('@/stores/ui')
    useUiStore().showToast(msg, 'error')
  } finally {
    isSending.value = false
  }
}

async function confirmReverseMessage() {
  if (!reverseInfo.value?.canAfford) {
    showReverseModal.value = false
    showPointsInsufficientModal.value = true
    return
  }
  showReverseModal.value = false
  await handleSend(pendingContent.value, true)
}

function cancelReverseMessage() {
  showReverseModal.value = false
  // 移除 failed 訊息
  if (pendingTempId.value !== null) {
    localMessages.value = localMessages.value.filter(m => m.id !== pendingTempId.value)
    pendingTempId.value = null
  }
  pendingContent.value = ''
}

function goTopUpFromChat() {
  showPointsInsufficientModal.value = false
  showReverseModal.value = false
  if (pendingTempId.value !== null) {
    localMessages.value = localMessages.value.filter(m => m.id !== pendingTempId.value)
    pendingTempId.value = null
  }
  router.push('/app/shop?tab=points')
}

// ── 圖片發送 ──────────────────────────────────────────────
async function handleSendImage(file: File) {
  if (isSending.value) return
  isSending.value = true

  const tempId = Date.now()
  const previewUrl = URL.createObjectURL(file)
  const tempMsg: ChatMessage = {
    id: tempId,
    conversationId: conversationId.value,
    senderId: authStore.user?.id ?? 0,
    type: 'image',
    content: previewUrl,
    imageUrl: previewUrl,
    status: 'sending',
    createdAt: new Date().toISOString(),
    isOwn: true,
  }
  localMessages.value.push(tempMsg)
  scrollToBottom()

  try {
    const sent = await sendImageMessage(conversationId.value, file)
    const idx = localMessages.value.findIndex(m => m.id === tempId)
    if (idx !== -1) {
      localMessages.value[idx] = { ...sent, isOwn: true, status: 'sent' }
    }
    chatStore.updateLastMessage(conversationId.value, '[圖片]')
    URL.revokeObjectURL(previewUrl)
  } catch (err: any) {
    const idx = localMessages.value.findIndex(m => m.id === tempId)
    const target = idx !== -1 ? localMessages.value[idx] : undefined
    if (target) target.status = 'failed'
    const msg = err.response?.data?.error?.message ?? err.response?.data?.message ?? '圖片發送失敗'
    const { useUiStore } = await import('@/stores/ui')
    useUiStore().showToast(msg, 'error')
  } finally {
    isSending.value = false
  }
}

// ── 訊息回收 ──────────────────────────────────────────────
async function handleRecall(messageId: number) {
  try {
    await apiRecallMessage(conversationId.value, messageId)
    const idx = localMessages.value.findIndex(m => m.id === messageId)
    const target = idx !== -1 ? localMessages.value[idx] : undefined
    if (target) {
      localMessages.value[idx] = {
        ...target,
        isRecalled: true,
        content: '',
        imageUrl: null,
        status: 'recalled',
      }
    }
  } catch (err: any) {
    const msg = err.response?.data?.error?.message ?? '無法回收訊息'
    const { useUiStore } = await import('@/stores/ui')
    useUiStore().showToast(msg, 'error')
  }
}

function openImage(url: string) {
  previewImageUrl.value = url
}
function closePreview() {
  previewImageUrl.value = null
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
      <button class="chat-topbar__icon" @click="toggleSearch" :aria-label="searchOpen ? '關閉搜尋' : '搜尋對話'">
        <svg v-if="!searchOpen" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <button class="chat-topbar__icon" @click="goProfile" aria-label="查看資訊">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      </button>
    </header>

    <!-- 搜尋面板 -->
    <div v-if="searchOpen" class="chat-search">
      <input
        v-model="searchKeyword"
        class="chat-search__input"
        type="search"
        placeholder="在對話中搜尋關鍵字…"
        autofocus
      />
      <div v-if="searchLoading" class="chat-search__loading">搜尋中…</div>
      <div v-else-if="searchResults.length" class="chat-search__results">
        <button
          v-for="r in searchResults"
          :key="r.id"
          class="chat-search__item"
          @click="jumpToMessage(r.id)"
        >
          <span class="chat-search__snippet">{{ r.content }}</span>
          <span class="chat-search__time">{{ new Date(r.createdAt).toLocaleString('zh-TW', { month:'numeric', day:'numeric', hour:'2-digit', minute:'2-digit' }) }}</span>
        </button>
      </div>
      <div v-else-if="searchKeyword.trim()" class="chat-search__empty">找不到「{{ searchKeyword }}」</div>
    </div>

    <!-- 訊息區 -->
    <div class="chat-messages">
      <div v-if="isLoading" class="chat-loading"><span class="spinner" /></div>
      <template v-else>
        <template v-for="(msg, i) in localMessages" :key="msg.id">
          <div v-if="dateSeparator(msg, localMessages[i - 1])" class="date-sep">
            <span>{{ dateSeparator(msg, localMessages[i - 1]) }}</span>
          </div>
          <div :data-msg-id="msg.id" class="msg-anchor">
            <MessageBubble
              :message="msg"
              :is-self="msg.isOwn"
              @recall="handleRecall"
              @image-click="openImage"
            />
          </div>
        </template>
        <div ref="messagesEndRef" class="scroll-anchor" />
      </template>
    </div>

    <!-- 輸入 -->
    <ChatInput @send="handleSend" @send-image="handleSendImage" />

    <!-- 圖片預覽（點擊訊息圖片放大） -->
    <div v-if="previewImageUrl" class="img-preview" @click="closePreview">
      <img :src="previewImageUrl" class="img-preview__img" alt="" />
      <button class="img-preview__close" aria-label="關閉">✕</button>
    </div>

    <!-- F40-b 逆區間訊息確認 Modal -->
    <div v-if="showReverseModal" class="rv-modal-overlay" @click="cancelReverseMessage">
      <div class="rv-modal-card" @click.stop>
        <h3 class="rv-modal-card__title">💬 誠信分數不足</h3>
        <p class="rv-modal-card__desc">
          對方的誠信等級較高，你目前無法直接發訊。<br>
          花點數可以突破限制，將這則訊息送出。
        </p>
        <div class="rv-modal-card__meta">
          <div>消費：<strong>{{ reverseInfo?.pointCost }} 點</strong></div>
          <div>目前餘額：{{ reverseInfo?.currentBalance }} 點</div>
          <div v-if="reverseInfo?.canAfford">消費後餘額：{{ (reverseInfo.currentBalance - reverseInfo.pointCost) }} 點</div>
          <div v-else style="color:#EF4444;">⚠️ 餘額不足</div>
        </div>
        <div class="rv-modal-card__actions">
          <button class="rv-btn rv-btn--secondary" @click="cancelReverseMessage">取消</button>
          <button class="rv-btn rv-btn--primary" :disabled="!reverseInfo?.canAfford" @click="confirmReverseMessage">
            確認發送（{{ reverseInfo?.pointCost }} 點）
          </button>
        </div>
      </div>
    </div>

    <!-- F40-b 點數不足 Modal -->
    <div v-if="showPointsInsufficientModal" class="rv-modal-overlay" @click="showPointsInsufficientModal = false">
      <div class="rv-modal-card" @click.stop>
        <h3 class="rv-modal-card__title">點數不足</h3>
        <div class="rv-modal-card__meta">
          <div>需要：<strong>{{ reverseInfo?.pointCost }} 點</strong></div>
          <div>目前：<strong>{{ reverseInfo?.currentBalance }} 點</strong></div>
        </div>
        <div class="rv-modal-card__actions">
          <button class="rv-btn rv-btn--secondary" @click="showPointsInsufficientModal = false; cancelReverseMessage()">取消</button>
          <button class="rv-btn rv-btn--primary" @click="goTopUpFromChat">前往儲值</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chat-view { display:flex; flex-direction:column; height:calc(100dvh - 64px - env(safe-area-inset-bottom)); background:#fff; }

.chat-topbar { display:flex; align-items:center; gap:10px; height:56px; padding:0 12px; background:#fff; border-bottom:0.5px solid #E5E7EB; flex-shrink:0; }
.chat-topbar__back { background:none; border:none; padding:4px; cursor:pointer; color:#374151; display:flex; }
.chat-topbar__logo { font-family:'Noto Serif TC',serif; font-size:18px; font-weight:600; letter-spacing:-0.5px; cursor:pointer; line-height:1; flex-shrink:0; }
.chat-topbar__user { display:flex; align-items:center; gap:10px; flex:1; min-width:0; cursor:pointer; }
.chat-topbar__avatar { width:36px; height:36px; border-radius:50%; object-fit:cover; flex-shrink:0; }
.chat-topbar__name { display:block; font-size:15px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.chat-topbar__status { display:block; font-size:11px; color:#9CA3AF; font-weight:500; }
.chat-topbar__status--on { color:#22C55E; }
.chat-topbar__icon { background:none; border:none; padding:4px; cursor:pointer; color:#9CA3AF; display:flex; }
.chat-topbar__icon:hover { color:#F0294E; }

.chat-search { position:relative; background:#fff; border-bottom:0.5px solid #E5E7EB; padding:10px 16px; flex-shrink:0; }
.chat-search__input { width:100%; height:40px; border:1.5px solid #E5E7EB; border-radius:9999px; padding:0 16px; font-size:14px; outline:none; }
.chat-search__input:focus { border-color:#F0294E; }
.chat-search__loading,.chat-search__empty { padding:16px 4px; font-size:13px; color:#9CA3AF; text-align:center; }
.chat-search__results { max-height:240px; overflow-y:auto; margin-top:8px; border:1px solid #F1F5F9; border-radius:10px; }
.chat-search__item { display:flex; justify-content:space-between; align-items:center; gap:12px; width:100%; padding:10px 14px; background:#fff; border:none; border-bottom:1px solid #F1F5F9; font-size:13px; color:#111827; cursor:pointer; text-align:left; }
.chat-search__item:hover { background:#F9FAFB; }
.chat-search__item:last-child { border-bottom:none; }
.chat-search__snippet { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.chat-search__time { flex-shrink:0; font-size:11px; color:#9CA3AF; }

.chat-messages { flex:1; overflow-y:auto; padding:12px 16px; display:flex; flex-direction:column; gap:4px; -webkit-overflow-scrolling:touch; }
.chat-loading { display:flex; justify-content:center; padding:48px 0; }
.spinner { width:24px; height:24px; border-radius:50%; border:2.5px solid #E5E7EB; border-top-color:#F0294E; animation:spin 0.7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

.scroll-anchor { height:1px; flex-shrink:0; }
.msg-anchor { display:flex; flex-direction:column; }
:deep(.msg-highlight) .bubble { animation:msg-flash 1.5s ease; }
@keyframes msg-flash { 0%,100% { box-shadow:0 0 0 0 rgba(240,41,78,0); } 40% { box-shadow:0 0 0 4px rgba(240,41,78,0.35); } }

.date-sep { display:flex; justify-content:center; padding:12px 0 8px; }
.date-sep span { font-size:11px; color:#9CA3AF; background:#F1F5F9; padding:2px 10px; border-radius:9999px; }

/* ── F40-b 逆區間訊息 Modal ────────────────────── */
.rv-modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:400; display:flex; align-items:center; justify-content:center; padding:20px; }
.rv-modal-card { width:100%; max-width:380px; background:#fff; border-radius:16px; padding:24px; box-shadow:0 20px 40px rgba(0,0,0,0.15); }
.rv-modal-card__title { font-size:17px; font-weight:700; color:#111827; margin:0 0 8px; }
.rv-modal-card__desc { font-size:13px; color:#6B7280; line-height:1.6; margin:0 0 14px; }
.rv-modal-card__meta { font-size:14px; color:#374151; line-height:1.9; margin-bottom:16px; padding:12px; background:#F9FAFB; border-radius:10px; }
.rv-modal-card__meta strong { color:#F0294E; }
.rv-modal-card__actions { display:flex; gap:10px; }
.rv-btn { flex:1; height:44px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; }
.rv-btn--secondary { background:#F3F4F6; color:#6B7280; border:1px solid #E5E7EB; }
.rv-btn--primary { background:#F0294E; color:#fff; }
.rv-btn--primary:disabled { opacity:0.5; cursor:not-allowed; }

.img-preview { position:fixed; inset:0; background:rgba(0,0,0,0.88); z-index:9999; display:flex; align-items:center; justify-content:center; cursor:zoom-out; padding:16px; }
.img-preview__img { max-width:100%; max-height:100%; border-radius:8px; }
.img-preview__close { position:absolute; top:20px; right:20px; width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.12); border:none; color:#fff; font-size:18px; cursor:pointer; }

@media (min-width: 768px) {
  .chat-topbar { padding:0 24px; }
  .chat-topbar__avatar { width:40px; height:40px; }
  .chat-topbar__name { font-size:16px; }
  .chat-messages { padding:16px 24px; gap:6px; }
}
@media (min-width: 1024px) {
  .chat-topbar { padding:0 32px; }
  .chat-messages { padding:20px 48px; gap:6px; }
}
</style>
