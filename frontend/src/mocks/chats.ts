/**
 * mocks/chats.ts
 * Sprint 4 聊天 Mock 資料
 */
export interface MockConversation {
  id: number
  targetUser: {
    id: number
    nickname: string
    avatar: string
    isOnline: boolean
  }
  lastMessage: string
  lastMessageType: 'text' | 'image'
  lastMessageAt: string
  unreadCount: number
}

const now = Date.now()

export const MOCK_CONVERSATIONS: MockConversation[] = [
  {
    id: 1,
    targetUser: { id: 1, nickname: '志明', avatar: 'https://i.pravatar.cc/150?img=1', isOnline: true },
    lastMessage: '晚上要一起吃飯嗎？',
    lastMessageType: 'text',
    lastMessageAt: new Date(now - 5 * 60000).toISOString(),
    unreadCount: 3,
  },
  {
    id: 2,
    targetUser: { id: 26, nickname: '淑芬', avatar: 'https://i.pravatar.cc/150?img=26', isOnline: true },
    lastMessage: '好的，那明天見！',
    lastMessageType: 'text',
    lastMessageAt: new Date(now - 30 * 60000).toISOString(),
    unreadCount: 1,
  },
  {
    id: 3,
    targetUser: { id: 3, nickname: '建宏', avatar: 'https://i.pravatar.cc/150?img=3', isOnline: false },
    lastMessage: '最近有空嗎？想聊聊',
    lastMessageType: 'text',
    lastMessageAt: new Date(now - 3 * 3600000).toISOString(),
    unreadCount: 0,
  },
  {
    id: 4,
    targetUser: { id: 27, nickname: '雅婷', avatar: 'https://i.pravatar.cc/150?img=27', isOnline: false },
    lastMessage: '[圖片]',
    lastMessageType: 'image',
    lastMessageAt: new Date(now - 8 * 3600000).toISOString(),
    unreadCount: 0,
  },
  {
    id: 5,
    targetUser: { id: 4, nickname: '家豪', avatar: 'https://i.pravatar.cc/150?img=4', isOnline: true },
    lastMessage: '週末有一個活動你想去嗎？',
    lastMessageType: 'text',
    lastMessageAt: new Date(now - 24 * 3600000).toISOString(),
    unreadCount: 0,
  },
  {
    id: 6,
    targetUser: { id: 28, nickname: '心怡', avatar: 'https://i.pravatar.cc/150?img=28', isOnline: false },
    lastMessage: '謝謝你的推薦，那家店真的很好吃！',
    lastMessageType: 'text',
    lastMessageAt: new Date(now - 48 * 3600000).toISOString(),
    unreadCount: 0,
  },
]

export interface MockMessage {
  id: number
  senderId: number
  content: string
  messageType: 'text' | 'image'
  sentAt: string
  isRead: boolean
}

export function mockFetchConversations(): MockConversation[] {
  return MOCK_CONVERSATIONS
}

export function mockFetchMessages(conversationId: number, myUserId: number): MockMessage[] {
  const conv = MOCK_CONVERSATIONS.find(c => c.id === conversationId)
  if (!conv) return []

  const otherId = conv.targetUser.id
  const base = Date.now() - 2 * 3600000

  return [
    { id: 1, senderId: otherId, content: '嗨！你好呀 😊', messageType: 'text', sentAt: new Date(base).toISOString(), isRead: true },
    { id: 2, senderId: myUserId, content: '你好！很高興認識你', messageType: 'text', sentAt: new Date(base + 60000).toISOString(), isRead: true },
    { id: 3, senderId: otherId, content: '你平常都在哪裡活動呢？', messageType: 'text', sentAt: new Date(base + 120000).toISOString(), isRead: true },
    { id: 4, senderId: myUserId, content: '大部分時間在台北，偶爾去新竹', messageType: 'text', sentAt: new Date(base + 180000).toISOString(), isRead: true },
    { id: 5, senderId: otherId, content: '哇，我也在台北！', messageType: 'text', sentAt: new Date(base + 300000).toISOString(), isRead: true },
    { id: 6, senderId: myUserId, content: '真巧！有空可以約出來走走', messageType: 'text', sentAt: new Date(base + 420000).toISOString(), isRead: true },
    { id: 7, senderId: otherId, content: conv.lastMessage, messageType: 'text', sentAt: conv.lastMessageAt, isRead: conv.unreadCount === 0 },
  ]
}
