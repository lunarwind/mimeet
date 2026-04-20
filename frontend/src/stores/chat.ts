import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Conversation } from '@/types/chat'

export const useChatStore = defineStore('chat', () => {
  const conversations = ref<Conversation[]>([])
  const totalUnread = computed(() => conversations.value.reduce((sum, c) => sum + c.unreadCount, 0))
  const unreadBadge = computed(() => totalUnread.value > 99 ? '99+' : totalUnread.value > 0 ? String(totalUnread.value) : '')

  function sortByLastMessage() {
    conversations.value.sort((a, b) => {
      const ta = a.lastMessageAt ? new Date(a.lastMessageAt).getTime() : 0
      const tb = b.lastMessageAt ? new Date(b.lastMessageAt).getTime() : 0
      return tb - ta
    })
  }

  function setConversations(list: Conversation[]) {
    conversations.value = list
    sortByLastMessage()
  }

  async function initFromApi() {
    const { fetchConversations } = await import('@/api/chat')
    const data = await fetchConversations()
    setConversations(data)
  }

  function removeConversation(conversationId: number) {
    conversations.value = conversations.value.filter((c) => c.id !== conversationId)
  }

  function setMuted(conversationId: number, isMuted: boolean) {
    const conv = conversations.value.find((c) => c.id === conversationId)
    if (conv) conv.isMuted = isMuted
  }

  function markAsRead(conversationId: number) {
    const conv = conversations.value.find((c) => c.id === conversationId)
    if (conv) conv.unreadCount = 0
  }

  function markAllAsRead() {
    conversations.value.forEach((c) => { c.unreadCount = 0 })
  }

  function incrementUnread(conversationId: number) {
    const conv = conversations.value.find((c) => c.id === conversationId)
    if (conv) {
      conv.unreadCount++
      conv.lastMessageAt = new Date().toISOString()
      sortByLastMessage()
    }
  }

  function updateLastMessage(conversationId: number, content: string) {
    const conv = conversations.value.find((c) => c.id === conversationId)
    if (conv) {
      conv.lastMessage = content
      conv.lastMessageAt = new Date().toISOString()
      sortByLastMessage()
    }
  }

  return {
    conversations,
    totalUnread,
    unreadBadge,
    setConversations,
    initFromApi,
    removeConversation,
    setMuted,
    markAsRead,
    markAllAsRead,
    incrementUnread,
    updateLastMessage,
  }
})
