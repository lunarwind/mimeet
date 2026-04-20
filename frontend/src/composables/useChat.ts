/**
 * useChat.ts — Laravel Reverb WebSocket via laravel-echo
 */
import { ref, onUnmounted } from 'vue'
import { getEcho } from '@/utils/echo'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import type { ChatMessage } from '@/types/chat'

export interface MessageSentPayload {
  id: number
  uuid?: string
  conversation_id: number
  sender_id: number
  type: 'text' | 'image'
  content: string
  image_url?: string | null
  is_read?: boolean
  sent_at?: string
  created_at?: string
}

export interface MessageReadPayload {
  conversation_id: number
  reader_id: number
  read_at: string
}

export interface MessageRecalledPayload {
  message_id: number
  conversation_id: number
  recalled_at: string
}

export function useChat() {
  const isConnected = ref(false)
  const authStore = useAuthStore()
  const chatStore = useChatStore()

  let activeConversationId: number | null = null
  let onMessageCb: ((msg: ChatMessage) => void) | null = null
  let onReadCb: ((payload: MessageReadPayload) => void) | null = null
  let onRecallCb: ((payload: MessageRecalledPayload) => void) | null = null

  function connect(conversationId: number) {
    if (activeConversationId === conversationId) return
    if (activeConversationId !== null) disconnect()

    const echo = getEcho()
    activeConversationId = conversationId
    const meId = authStore.user?.id ?? 0

    echo.private(`chat.${conversationId}`)
      .listen('.MessageSent', (payload: MessageSentPayload) => {
        if (payload.sender_id === meId) return
        const msg: ChatMessage = {
          id: payload.id,
          conversationId: payload.conversation_id,
          senderId: payload.sender_id,
          type: payload.type,
          content: payload.content,
          imageUrl: payload.image_url ?? null,
          status: 'sent',
          createdAt: payload.sent_at ?? payload.created_at ?? new Date().toISOString(),
          isOwn: false,
          isRead: false,
          isRecalled: false,
        }
        onMessageCb?.(msg)
        chatStore.updateLastMessage(
          conversationId,
          payload.content || (payload.type === 'image' ? '[圖片]' : ''),
        )
      })
      .listen('.MessageRead', (payload: MessageReadPayload) => {
        onReadCb?.(payload)
      })
      .listen('.MessageRecalled', (payload: MessageRecalledPayload) => {
        onRecallCb?.(payload)
      })

    isConnected.value = true
  }

  function disconnect() {
    if (activeConversationId === null) return
    try {
      getEcho().leave(`chat.${activeConversationId}`)
    } catch { /* noop */ }
    activeConversationId = null
    isConnected.value = false
  }

  function onMessage(cb: (msg: ChatMessage) => void) {
    onMessageCb = cb
  }

  function onRead(cb: (payload: MessageReadPayload) => void) {
    onReadCb = cb
  }

  function onRecall(cb: (payload: MessageRecalledPayload) => void) {
    onRecallCb = cb
  }

  onUnmounted(disconnect)

  return {
    isConnected,
    connect,
    disconnect,
    onMessage,
    onRead,
    onRecall,
  }
}

/**
 * 全域訂閱個人通知頻道 user.{userId}
 * 在 AppLayout 登入後呼叫，登出時 unsubscribe。
 */
export function useUserChannel() {
  const authStore = useAuthStore()
  const chatStore = useChatStore()
  let subscribedUserId: number | null = null

  function subscribe() {
    const uid = authStore.user?.id
    if (!uid || subscribedUserId === uid) return

    if (subscribedUserId !== null) unsubscribe()

    const echo = getEcho()
    echo.private(`user.${uid}`)
      .listen('.NotificationReceived', (payload: { type?: string; conversation_id?: number }) => {
        if (payload.type === 'new_message' && payload.conversation_id) {
          chatStore.incrementUnread(payload.conversation_id)
        }
      })

    subscribedUserId = uid
  }

  function unsubscribe() {
    if (subscribedUserId === null) return
    try {
      getEcho().leave(`user.${subscribedUserId}`)
    } catch { /* noop */ }
    subscribedUserId = null
  }

  return { subscribe, unsubscribe }
}
