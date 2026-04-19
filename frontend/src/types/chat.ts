export type MessageType = 'text' | 'image'
export type MessageStatus = 'sending' | 'sent' | 'read' | 'failed' | 'recalled'

export interface ChatMessage {
  id: number
  conversationId: number
  senderId: number
  type: MessageType
  content: string
  imageUrl?: string | null
  status: MessageStatus
  createdAt: string
  isOwn: boolean
  isRead?: boolean
  isRecalled?: boolean
}

/** @deprecated use ChatMessage */
export type Message = ChatMessage

export interface ChatListItem {
  id: number
  targetUser: {
    id: number
    nickname: string
    avatarUrl: string | null
    isOnline: boolean
  }
  lastMessage: string
  lastMessageAt: string
  unreadCount: number
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
  inviterNickname: string
  inviteeNickname: string
  inviterAvatar: string | null
  inviteeAvatar: string | null
  status: 'pending' | 'accepted' | 'rejected' | 'verified' | 'expired'
  scheduledAt: string
  location: string | null
  qrToken: string | null
  expiresAt: string | null
  creditScoreChange: number | null
  createdAt: string
}
