import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Conversation } from '@/types/chat'

export const useChatStore = defineStore('chat', () => {
  const conversations = ref<Conversation[]>([])
  const totalUnread = computed(() => conversations.value.reduce((sum, c) => sum + c.unreadCount, 0))
  const unreadBadge = computed(() => totalUnread.value > 99 ? '99+' : totalUnread.value > 0 ? String(totalUnread.value) : '')

  function setConversations(list: Conversation[]) {
    conversations.value = list
  }

  /** 從 API 初始化（載入聊天列表時呼叫） */
  async function initFromApi() {
    const { fetchConversations } = await import('@/api/chat')
    const data = await fetchConversations()
    conversations.value = data
  }

  function markAsRead(conversationId: number) {
    const conv = conversations.value.find((c) => c.id === conversationId)
    if (conv) conv.unreadCount = 0
  }

  function incrementUnread(conversationId: number) {
    const conv = conversations.value.find((c) => c.id === conversationId)
    if (conv) conv.unreadCount++
  }

  function updateLastMessage(conversationId: number, content: string) {
    const conv = conversations.value.find((c) => c.id === conversationId)
    if (conv) {
      conv.lastMessage = content
      conv.lastMessageAt = new Date().toISOString()
    }
  }

  return {
    conversations,
    totalUnread,
    unreadBadge,
    setConversations,
    initFromApi,
    markAsRead,
    incrementUnread,
    updateLastMessage,
  }
})
