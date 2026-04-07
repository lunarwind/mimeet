/**
 * mocks/notifications.ts
 * Sprint 4 通知測試資料
 */
export interface MockNotification {
  id: number
  type: 'new_message' | 'new_visitor' | 'new_follower' | 'date_invite' | 'date_accepted' | 'credit_changed' | 'subscription_expiry' | 'verification_result' | 'ticket_replied' | 'announcement'
  title: string
  body: string
  isRead: boolean
  createdAt: string
  actionUrl: string | null
}

const now = Date.now()

export const MOCK_NOTIFICATIONS: MockNotification[] = [
  { id: 1, type: 'new_message', title: '新訊息', body: '志明 傳了一則訊息給你', isRead: false, createdAt: new Date(now - 5 * 60000).toISOString(), actionUrl: '/app/messages/1' },
  { id: 2, type: 'new_visitor', title: '有人看了你', body: '淑芬 查看了你的個人資料', isRead: false, createdAt: new Date(now - 30 * 60000).toISOString(), actionUrl: '/app/profiles/26' },
  { id: 3, type: 'new_follower', title: '新收藏', body: '雅婷 收藏了你', isRead: false, createdAt: new Date(now - 2 * 3600000).toISOString(), actionUrl: '/app/profiles/27' },
  { id: 4, type: 'date_invite', title: '約會邀請', body: '心怡 向你發起見面邀請', isRead: false, createdAt: new Date(now - 5 * 3600000).toISOString(), actionUrl: '/app/dates' },
  { id: 5, type: 'credit_changed', title: '誠信分數', body: '完成身份驗證，誠信分數 +10', isRead: false, createdAt: new Date(now - 8 * 3600000).toISOString(), actionUrl: null },
  { id: 6, type: 'new_message', title: '新訊息', body: '佳穎 傳了一則訊息給你', isRead: true, createdAt: new Date(now - 12 * 3600000).toISOString(), actionUrl: '/app/messages/2' },
  { id: 7, type: 'date_accepted', title: '約會確認', body: '詩涵 接受了你的見面邀請', isRead: true, createdAt: new Date(now - 24 * 3600000).toISOString(), actionUrl: '/app/dates' },
  { id: 8, type: 'subscription_expiry', title: '訂閱提醒', body: '您的會員將於 3 天後到期', isRead: true, createdAt: new Date(now - 36 * 3600000).toISOString(), actionUrl: '/app/shop' },
  { id: 9, type: 'verification_result', title: '驗證結果', body: '您的進階驗證已通過', isRead: true, createdAt: new Date(now - 48 * 3600000).toISOString(), actionUrl: '/app/settings/verify' },
  { id: 10, type: 'ticket_replied', title: '回報回覆', body: '您的回報 #R2026040100001 已有回覆', isRead: true, createdAt: new Date(now - 72 * 3600000).toISOString(), actionUrl: '/app/reports' },
  { id: 11, type: 'announcement', title: '系統公告', body: '清明連假期間客服回覆可能較慢', isRead: true, createdAt: new Date(now - 96 * 3600000).toISOString(), actionUrl: null },
  { id: 12, type: 'new_visitor', title: '有人看了你', body: '宜蓁 查看了你的個人資料', isRead: true, createdAt: new Date(now - 120 * 3600000).toISOString(), actionUrl: '/app/profiles/31' },
]

export function mockFetchNotifications(page: number): {
  notifications: MockNotification[]
  hasMore: boolean
  unreadCount: number
} {
  const perPage = 10
  const start = (page - 1) * perPage
  const paged = MOCK_NOTIFICATIONS.slice(start, start + perPage)
  return {
    notifications: paged,
    hasMore: start + perPage < MOCK_NOTIFICATIONS.length,
    unreadCount: MOCK_NOTIFICATIONS.filter(n => !n.isRead).length,
  }
}
