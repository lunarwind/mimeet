/**
 * useChat.ts
 * Socket.IO 連線骨架 — DEV 環境使用 Mock 模式
 */
import { ref, onUnmounted } from 'vue'
import { useChatStore } from '@/stores/chat'
import type { ChatMessage } from '@/types/chat'

export function useChat() {
  const chatStore = useChatStore()
  const isConnected = ref(false)
  const messages = ref<ChatMessage[]>([])

  let mockTimer: ReturnType<typeof setInterval> | null = null
  let retryCount = 0
  let activeConversationId: number | null = null
  let onMessageCb: ((msg: ChatMessage) => void) | null = null

  // ── 連線 ────────────────────────────────────────────────
  function connect(conversationId: number) {
    activeConversationId = conversationId

    if (import.meta.env.VITE_USE_MOCK === 'true') {
      // Mock 模式：模擬連線，定時發送假訊息
      isConnected.value = true
      startMockInterval()
      return
    }

    // 真實 Socket.IO（Phase 2 實作）
    tryConnect()
  }

  function tryConnect() {
    retryCount++
    // NOTE: real Socket.IO connection (Phase 2)
    // socket = io(...)
    // socket.on('connect', () => { isConnected.value = true; retryCount = 0 })
    // socket.on('disconnect', () => { isConnected.value = false; if (retryCount < 3) tryConnect() })
    // socket.on('message', handleIncoming)
  }

  // ── Mock 自動回覆 ──────────────────────────────────────
  function startMockInterval() {
    stopMockInterval()
    mockTimer = setInterval(async () => {
      if (!activeConversationId) return
      const { randomReply } = await import('@/mocks/chats')
      const msg: ChatMessage = {
        id: Date.now(),
        conversationId: activeConversationId,
        senderId: -1,
        type: 'text',
        content: randomReply(),
        status: 'sent',
        createdAt: new Date().toISOString(),
        isOwn: false,
      }
      messages.value.push(msg)
      chatStore.incrementUnread(activeConversationId!)
      onMessageCb?.(msg)
    }, 3000 + Math.random() * 5000)
  }

  function stopMockInterval() {
    if (mockTimer) { clearInterval(mockTimer); mockTimer = null }
  }

  // ── 發送訊息 ────────────────────────────────────────────
  function sendMessage(content: string) {
    if (!activeConversationId) return
    const msg: ChatMessage = {
      id: Date.now(),
      conversationId: activeConversationId,
      senderId: 0,
      type: 'text',
      content,
      status: 'sent',
      createdAt: new Date().toISOString(),
      isOwn: true,
    }
    messages.value.push(msg)
    onMessageCb?.(msg)
  }

  // ── callback ────────────────────────────────────────────
  function onMessage(cb: (msg: ChatMessage) => void) {
    onMessageCb = cb
  }

  // ── 斷線 ────────────────────────────────────────────────
  function disconnect() {
    stopMockInterval()
    isConnected.value = false
    activeConversationId = null
    retryCount = 0
  }

  onUnmounted(disconnect)

  return { isConnected, messages, connect, disconnect, sendMessage, onMessage }
}
