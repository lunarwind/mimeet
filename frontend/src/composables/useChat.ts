/**
 * useChat.ts
 * Socket.IO 連線骨架
 */
import { ref, onUnmounted } from 'vue'
import { useChatStore } from '@/stores/chat'
import type { ChatMessage } from '@/types/chat'

export function useChat() {
  const chatStore = useChatStore()
  const isConnected = ref(false)
  const messages = ref<ChatMessage[]>([])

  let retryCount = 0
  let activeConversationId: number | null = null
  let onMessageCb: ((msg: ChatMessage) => void) | null = null

  // ── 連線 ────────────────────────────────────────────────
  function connect(conversationId: number) {
    activeConversationId = conversationId
    tryConnect()
  }

  function tryConnect() {
    retryCount++
    // TODO: real Socket.IO connection
    // socket = io(...)
    // socket.on('connect', () => { isConnected.value = true; retryCount = 0 })
    // socket.on('disconnect', () => { isConnected.value = false; if (retryCount < 3) tryConnect() })
    // socket.on('message', handleIncoming)
    isConnected.value = true
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
    isConnected.value = false
    activeConversationId = null
    retryCount = 0
  }

  onUnmounted(disconnect)

  return { isConnected, messages, connect, disconnect, sendMessage, onMessage }
}
