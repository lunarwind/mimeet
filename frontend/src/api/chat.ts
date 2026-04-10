/**
 * api/chat.ts
 * 聊天相關 API — 對應 API-001 §4
 */
import client from './client'
import type { Conversation, Message } from '@/types/chat'

const USE_MOCK = import.meta.env.VITE_USE_MOCK === 'true'

function delay(ms: number) {
  return new Promise(r => setTimeout(r, ms))
}

// ── 取得聊天列表 ──────────────────────────────────────────
export async function fetchConversations(): Promise<Conversation[]> {
  if (USE_MOCK) {
    const { mockConversations } = await import('@/mocks/chats')
    await delay(300 + Math.random() * 300)
    return mockConversations()
  }
  const res = await client.get<{ data: { chats: any[] } }>('/chats')
  return res.data.data.chats
}

// ── 取得聊天訊息 ──────────────────────────────────────────
export async function fetchMessages(conversationId: number): Promise<Message[]> {
  if (USE_MOCK) {
    const { mockMessages } = await import('@/mocks/chats')
    await delay(200 + Math.random() * 200)
    return mockMessages(conversationId)
  }
  const res = await client.get<{ data: { messages: any[] } }>(`/chats/${conversationId}/messages`)
  return res.data.data.messages
}

// ── 發送訊息 ──────────────────────────────────────────────
export async function sendMessage(conversationId: number, content: string): Promise<Message> {
  if (USE_MOCK) {
    await delay(100)
    return {
      id: Date.now(),
      conversationId,
      senderId: 0, // will be replaced by caller
      type: 'text',
      content,
      status: 'sent',
      createdAt: new Date().toISOString(),
      isOwn: true,
    }
  }
  const res = await client.post<{ data: { message: Message } }>(`/chats/${conversationId}/messages`, {
    data: { content, message_type: 'text' },
  })
  return res.data.data.message
}

// ── 標記已讀 ──────────────────────────────────────────────
export async function markConversationRead(conversationId: number): Promise<void> {
  if (USE_MOCK) { await delay(50); return }
  await client.patch(`/chats/${conversationId}/messages/read`)
}
