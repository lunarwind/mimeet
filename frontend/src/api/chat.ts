/**
 * api/chat.ts
 * 聊天相關 API — 對應 API-001 §4
 */
import client from './client'
import type { Conversation, Message } from '@/types/chat'

<<<<<<< HEAD
const USE_MOCK = import.meta.env.VITE_USE_MOCK === 'true'

function delay(ms: number) {
  return new Promise(r => setTimeout(r, ms))
}

=======
>>>>>>> develop
// ── 取得聊天列表 ──────────────────────────────────────────
export async function fetchConversations(): Promise<Conversation[]> {
  const res = await client.get<{ data: { chats: Conversation[] } }>('/chats')
  return res.data.data.chats
}

// ── 取得聊天訊息 ──────────────────────────────────────────
export async function fetchMessages(conversationId: number): Promise<Message[]> {
  const res = await client.get<{ data: { messages: Message[] } }>(`/chats/${conversationId}/messages`)
  return res.data.data.messages
}

// ── 發送訊息 ──────────────────────────────────────────────
export async function sendMessage(conversationId: number, content: string): Promise<Message> {
  const res = await client.post<{ data: { message: Message } }>(`/chats/${conversationId}/messages`, {
    data: { content, message_type: 'text' },
  })
  return res.data.data.message
}

// ── 標記已讀 ──────────────────────────────────────────────
export async function markConversationRead(conversationId: number): Promise<void> {
  await client.patch(`/chats/${conversationId}/messages/read`)
}
