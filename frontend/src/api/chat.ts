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
    isMuted: !!c.is_muted,
  }))
}

// ── Toggle 對話靜音（F22 Part A） ─────────────────────────
export async function toggleConversationMute(conversationId: number): Promise<boolean> {
  const res = await client.patch(`/chats/${conversationId}/mute`)
  return !!res.data?.data?.is_muted
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
  return raw.map(mapMessage(conversationId, false)).reverse()
}

// ── 發送訊息（文字） ─────────────────────────────────────
export async function sendMessage(conversationId: number, content: string, usePoints = false): Promise<Message> {
  const res = await client.post(`/chats/${conversationId}/messages`, {
    content,
    message_type: 'text',
    use_points: usePoints,
  })
  const m = res.data?.data?.message ?? {}
  return mapMessage(conversationId, true)(m)
}

// ── 發送訊息（圖片）— multipart ────────────────────────
export async function sendImageMessage(conversationId: number, imageFile: File): Promise<Message> {
  const form = new FormData()
  form.append('message_type', 'image')
  form.append('image', imageFile)
  const res = await client.post(`/chats/${conversationId}/messages`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  const m = res.data?.data?.message ?? {}
  return mapMessage(conversationId, true)(m)
}

// ── 回收訊息 ─────────────────────────────────────────────
export async function recallMessage(conversationId: number, messageId: number): Promise<void> {
  await client.delete(`/chats/${conversationId}/messages/${messageId}`)
}

// ── 聊天內關鍵字搜尋 ─────────────────────────────────────
export async function searchMessages(conversationId: number, keyword: string): Promise<Message[]> {
  const res = await client.get(`/chats/${conversationId}/messages/search`, {
    params: { keyword },
  })
  const raw = res.data?.data?.messages ?? []
  return raw.map(mapMessage(conversationId, false))
}

// ── snake_case → camelCase mapper（單一來源，避免漂移） ─
function mapMessage(conversationId: number, isOwnDefault: boolean) {
  return (m: any): Message => ({
    id: m.id,
    conversationId: m.conversation_id ?? conversationId,
    senderId: m.sender_id ?? 0,
    type: m.type ?? 'text',
    content: m.content ?? '',
    imageUrl: m.image_url ?? null,
    status: m.is_recalled ? 'recalled' : (m.is_read ? 'read' : 'sent'),
    isOwn: isOwnDefault,
    isRead: !!m.is_read,
    isRecalled: !!m.is_recalled,
    createdAt: m.sent_at ?? m.created_at ?? '',
  })
}

// ── 標記已讀 ──────────────────────────────────────────────
export async function markConversationRead(conversationId: number): Promise<void> {
  await client.patch(`/chats/${conversationId}/read`)
}

// ── 全部標記已讀 ──────────────────────────────────────────
export async function markAllConversationsRead(): Promise<void> {
  await client.patch('/chats/read-all')
}
