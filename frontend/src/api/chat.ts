/**
 * api/chat.ts
 * 聊天相關 API — 對應 API-001 §4
 */
import client from './client'
import type { Conversation, Message } from '@/types/chat'

// ── 建立/取得對話 ─────────────────────────────────────────
export async function getOrCreateConversation(targetUserId: number): Promise<number> {
  const res = await client.post('/chats', { user_id: targetUserId })
  return res.data?.data?.conversation?.id ?? 0
}

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

// ── 取得對話對方資訊 ──────────────────────────────────────
export async function fetchConversationInfo(conversationId: number) {
  const res = await client.get(`/chats/${conversationId}/info`)
  const u = res.data?.data?.user ?? {}
  return {
    id: u.id as number,
    nickname: u.nickname as string ?? '',
    avatarUrl: (u.avatar_url ?? null) as string | null,
    onlineStatus: (u.online_status ?? null) as string | null,
    lastActiveLabel: (u.last_active_label ?? null) as string | null,
    creditScore: (u.credit_score ?? 0) as number,
  }
}

// ── 取得聊天訊息 ──────────────────────────────────────────
export async function fetchMessages(conversationId: number): Promise<Message[]> {
  const res = await client.get(`/chats/${conversationId}/messages`)
  const raw = res.data?.data?.messages ?? []
  return raw.map((m: any) => ({
    id: m.id,
    conversationId: m.conversation_id ?? conversationId,
    senderId: m.sender_id ?? 0,
    type: m.type ?? 'text',
    content: m.content ?? '',
    status: m.is_read ? 'read' : 'sent',
    isOwn: false, // set by ChatView based on current user
    createdAt: m.sent_at ?? m.created_at ?? '',
  }))
}

// ── 發送訊息 ──────────────────────────────────────────────
export async function sendMessage(conversationId: number, content: string): Promise<Message> {
  const res = await client.post(`/chats/${conversationId}/messages`, { content })
  const m = res.data?.data?.message ?? {}
  return {
    id: m.id,
    conversationId,
    senderId: m.sender_id ?? 0,
    type: m.type ?? 'text',
    content: m.content ?? content,
    status: 'sent',
    createdAt: m.sent_at ?? m.created_at ?? new Date().toISOString(),
    isOwn: true,
  }
}

// ── 標記已讀 ──────────────────────────────────────────────
export async function markConversationRead(conversationId: number): Promise<void> {
  await client.patch(`/chats/${conversationId}/read`)
}

// ── 全部標記已讀 ──────────────────────────────────────────
export async function markAllConversationsRead(): Promise<void> {
  await client.patch('/chats/read-all')
}
