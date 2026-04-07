/**
 * mocks/chats.ts
 * Sprint 4 聊天 Mock 資料
 */
import type { Conversation, Message } from '@/types/chat'

const now = Date.now()

const CONVERSATIONS: Conversation[] = [
  {
    id: 1,
    targetUser: { id: 1, nickname: '志明', avatarUrl: 'https://i.pravatar.cc/150?img=1', isOnline: true, creditScore: 88 },
    lastMessage: '晚上要一起吃飯嗎？',
    lastMessageAt: new Date(now - 5 * 60000).toISOString(),
    unreadCount: 3,
  },
  {
    id: 2,
    targetUser: { id: 26, nickname: '淑芬', avatarUrl: 'https://i.pravatar.cc/150?img=26', isOnline: true, creditScore: 92 },
    lastMessage: '好的，那明天見！',
    lastMessageAt: new Date(now - 30 * 60000).toISOString(),
    unreadCount: 1,
  },
  {
    id: 3,
    targetUser: { id: 3, nickname: '建宏', avatarUrl: 'https://i.pravatar.cc/150?img=3', isOnline: false, creditScore: 75 },
    lastMessage: '最近有空嗎？想聊聊',
    lastMessageAt: new Date(now - 3 * 3600000).toISOString(),
    unreadCount: 0,
  },
  {
    id: 4,
    targetUser: { id: 27, nickname: '雅婷', avatarUrl: 'https://i.pravatar.cc/150?img=27', isOnline: false, creditScore: 68 },
    lastMessage: '那家咖啡廳真的很不錯！',
    lastMessageAt: new Date(now - 8 * 3600000).toISOString(),
    unreadCount: 0,
  },
  {
    id: 5,
    targetUser: { id: 4, nickname: '家豪', avatarUrl: 'https://i.pravatar.cc/150?img=4', isOnline: true, creditScore: 95 },
    lastMessage: '週末有一個活動你想去嗎？',
    lastMessageAt: new Date(now - 24 * 3600000).toISOString(),
    unreadCount: 0,
  },
  {
    id: 6,
    targetUser: { id: 28, nickname: '心怡', avatarUrl: 'https://i.pravatar.cc/150?img=28', isOnline: false, creditScore: 82 },
    lastMessage: '謝謝你的推薦！',
    lastMessageAt: new Date(now - 48 * 3600000).toISOString(),
    unreadCount: 0,
  },
]

function buildMessages(convId: number): Message[] {
  const conv = CONVERSATIONS.find(c => c.id === convId)
  if (!conv) return []
  const otherId = conv.targetUser.id
  const base = now - 2 * 3600000
  return [
    { id: 1, conversationId: convId, senderId: otherId, type: 'text', content: '嗨！你好呀 😊', status: 'read', createdAt: new Date(base).toISOString(), isOwn: false },
    { id: 2, conversationId: convId, senderId: 0, type: 'text', content: '你好！很高興認識你', status: 'read', createdAt: new Date(base + 60000).toISOString(), isOwn: true },
    { id: 3, conversationId: convId, senderId: otherId, type: 'text', content: '你平常都在哪裡活動呢？', status: 'read', createdAt: new Date(base + 120000).toISOString(), isOwn: false },
    { id: 4, conversationId: convId, senderId: 0, type: 'text', content: '大部分時間在台北，偶爾去新竹', status: 'read', createdAt: new Date(base + 180000).toISOString(), isOwn: true },
    { id: 5, conversationId: convId, senderId: otherId, type: 'text', content: '哇，我也在台北！', status: 'read', createdAt: new Date(base + 300000).toISOString(), isOwn: false },
    { id: 6, conversationId: convId, senderId: 0, type: 'text', content: '真巧！有空可以約出來走走', status: 'read', createdAt: new Date(base + 420000).toISOString(), isOwn: true },
    { id: 7, conversationId: convId, senderId: otherId, type: 'text', content: conv.lastMessage, status: conv.unreadCount > 0 ? 'sent' : 'read', createdAt: conv.lastMessageAt, isOwn: false },
  ]
}

export function mockConversations(): Conversation[] {
  return [...CONVERSATIONS].sort((a, b) => new Date(b.lastMessageAt).getTime() - new Date(a.lastMessageAt).getTime())
}

export function mockMessages(conversationId: number): Message[] {
  return buildMessages(conversationId)
}

// ── Mock 自動回覆用 ──────────────────────────────────────
const AUTO_REPLIES = [
  '哈哈，真有趣 😄', '好啊！', '聽起來不錯耶', '改天再聊～',
  '你覺得呢？', '太棒了 🎉', '我也是這麼想的', '好期待！',
  '讓我想想...', '沒問題 👌',
]

export function randomReply(): string {
  return AUTO_REPLIES[Math.floor(Math.random() * AUTO_REPLIES.length)]
}
