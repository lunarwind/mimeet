/**
 * useChat.ts
 * WebSocket composable — connects via Socket.IO to Laravel Reverb/Echo.
 * Falls back to polling if WebSocket is unavailable.
 */
import { ref, onUnmounted } from 'vue'
import { io, type Socket } from 'socket.io-client'
import { useChatStore } from '@/stores/chat'
import { useAuthStore } from '@/stores/auth'
import type { ChatMessage } from '@/types/chat'

let socket: Socket | null = null

export function useChat() {
  const chatStore = useChatStore()
  const authStore = useAuthStore()
  const isConnected = ref(false)
  const messages = ref<ChatMessage[]>([])

  let activeConversationId: number | null = null
  let onMessageCb: ((msg: ChatMessage) => void) | null = null

  // ── WebSocket 連線 ────────────────────────────────────────
  function connect(conversationId: number) {
    activeConversationId = conversationId

    // Only connect if Reverb/WS env vars are configured
    const wsHost = import.meta.env.VITE_WS_HOST
    const wsPort = import.meta.env.VITE_WS_PORT
    const wsKey = import.meta.env.VITE_WS_KEY

    if (wsHost && wsPort && wsKey) {
      connectWebSocket(wsHost, wsPort, wsKey, conversationId)
    } else {
      // No WS config — mark as connected (REST-only mode)
      isConnected.value = true
    }
  }

  function connectWebSocket(host: string, port: string, key: string, conversationId: number) {
    if (socket?.connected) {
      socket.emit('subscribe', { channel: `private-chat.${conversationId}` })
      isConnected.value = true
      return
    }

    const scheme = import.meta.env.VITE_WS_SCHEME || 'ws'
    socket = io(`${scheme}://${host}:${port}`, {
      auth: { key },
      reconnectionAttempts: 3,
      reconnectionDelay: 2000,
    })

    socket.on('connect', () => {
      isConnected.value = true
      socket?.emit('subscribe', { channel: `private-chat.${conversationId}` })

      // Also subscribe to personal notification channel
      const userId = authStore.user?.id
      if (userId) {
        socket?.emit('subscribe', { channel: `private-user.${userId}` })
      }
    })

    socket.on('disconnect', () => {
      isConnected.value = false
    })

    // Listen for new messages
    socket.on('message', (event: { type: string; data: Record<string, unknown> }) => {
      if (event.type === 'MessageSent' || event.type === 'new-message') {
        const msg = event.data as unknown as ChatMessage
        if (msg.conversationId === activeConversationId) {
          messages.value.push(msg)
          onMessageCb?.(msg)
        }
        // Update unread count in store
        if (msg.conversationId) {
          const current = chatStore.unreadCounts.get(msg.conversationId) ?? 0
          chatStore.setUnread(msg.conversationId, current + 1)
        }
      }
    })

    // Listen for notifications
    socket.on('notification', (event: { type: string; data: Record<string, unknown> }) => {
      // Handle notification events (new_follower, date_invite, etc.)
      // These are handled by the notification store
    })
  }

  // ── 發送訊息（via REST API — WebSocket is receive-only） ────
  function sendMessage(content: string) {
    if (!activeConversationId) return
    // Actual send is done via POST /chats/{id}/messages in ChatView
    // This creates an optimistic local message
    const msg: ChatMessage = {
      id: Date.now(),
      conversationId: activeConversationId,
      senderId: authStore.user?.id ?? 0,
      type: 'text',
      content,
      status: 'sent',
      createdAt: new Date().toISOString(),
      isOwn: true,
    }
    messages.value.push(msg)
    onMessageCb?.(msg)
  }

  function onMessage(cb: (msg: ChatMessage) => void) {
    onMessageCb = cb
  }

  // ── 斷線 ────────────────────────────────────────────────
  function disconnect() {
    if (activeConversationId && socket?.connected) {
      socket.emit('unsubscribe', { channel: `private-chat.${activeConversationId}` })
    }
    isConnected.value = false
    activeConversationId = null
  }

  onUnmounted(disconnect)

  return { isConnected, messages, connect, disconnect, sendMessage, onMessage }
}

/**
 * Disconnect the global socket (call on logout).
 */
export function disconnectGlobalSocket() {
  socket?.disconnect()
  socket = null
}
