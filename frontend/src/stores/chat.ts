import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface Conversation {
  id: number
  targetUser: {
    id: number
    nickname: string
    avatarUrl: string | null
    isOnline: boolean
  }
  lastMessage: string
  lastMessageAt: string
  unreadCount: number
}

export const useChatStore = defineStore('chat', () => {
  const conversations = ref<Conversation[]>([])
  const totalUnread = computed(() => conversations.value.reduce((sum, c) => sum + c.unreadCount, 0))

  function setConversations(list: Conversation[]) {
    conversations.value = list
  }

  function markAsRead(conversationId: number) {
    const conv = conversations.value.find((c) => c.id === conversationId)
    if (conv) conv.unreadCount = 0
  }

  function incrementUnread(conversationId: number) {
    const conv = conversations.value.find((c) => c.id === conversationId)
    if (conv) conv.unreadCount++
  }

  return {
    conversations,
    totalUnread,
    setConversations,
    markAsRead,
    incrementUnread,
  }
})
