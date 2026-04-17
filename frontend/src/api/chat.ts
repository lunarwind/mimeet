/**
 * api/chat.ts
 * 聊天相關 API — 對應 API-001 §4
 */
import client from './client'
import type { Conversation, Message } from '@/types/chat'

// ── 取得聊天列表 ──────────────────────────────────────────
export async function fetchConversations(): Promise<Conversation[]> {
  const res = await client.get('/chats')
  const raw = res.data?.data?.chats ?? []
  // Map backend snake_case → frontend camelCase
  return raw.map((c: any) => ({
    id: c.id,
    targetUser: {
      id: c.other_user?.id ?? 0,
      nickname: c.other_user?.nickname ?? '',
      avatarUrl: c.other_user?.avatar_url ?? c.other_user?.avatarUrl ?? null,
      isOnline: c.other_user?.online_status === 'online' || c.other_user?.isOnline || false,
      creditScore: c.other_user?.credit_score ?? c.other_user?.creditScore ?? 0,
    },
    lastMessage: typeof c.last_message === 'string'
      ? c.last_message
      : c.last_message?.content ?? '',
    lastMessageAt: c.last_message?.sent_at ?? c.updated_at ?? '',
    unreadCount: c.unread_count ?? 0,
  }))
}

// ── 取得聊天訊息 ──────────────────────────────────────────
export async function fetchMessages(conversationId: number): Promise<Message[]> {
  const res = await client.get<{ data: { messages: Message[] } }>(`/chats/${conversationId}/messages`)
  return res.data.data.messages
}

// ── 發送訊息 ──────────────────────────────────────────────
export async function sendMessage(conversationId: number, content: string): Promise<Message> {
  const res = await client.post<{ data: { message: Message } }>(`/chats/${conversationId}/messages`, {
    content, message_type: 'text',
  })
  return res.data.data.message
}

// ── 標記已讀 ──────────────────────────────────────────────
export async function markConversationRead(conversationId: number): Promise<void> {
  await client.patch(`/chats/${conversationId}/messages/read`)
}
