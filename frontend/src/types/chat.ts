export type MessageType = 'text' | 'image'
export type MessageStatus = 'sending' | 'sent' | 'read' | 'failed'

export interface Message {
  id: number
  conversationId: number
  senderId: number
  type: MessageType
  content: string
  status: MessageStatus
  createdAt: string
  isOwn: boolean
}

export interface Conversation {
  id: number
  targetUser: {
    id: number
    nickname: string
    avatarUrl: string | null
    isOnline: boolean
    creditScore: number
  }
  lastMessage: string
  lastMessageAt: string
  unreadCount: number
}

export interface DateInvitation {
  id: number
  inviterId: number
  inviteeId: number
  status: 'pending' | 'accepted' | 'rejected' | 'verified' | 'expired'
  scheduledAt: string
  location: string | null
  qrToken: string | null
  expiresAt: string | null
  createdAt: string
}
