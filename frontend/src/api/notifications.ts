/**
 * api/notifications.ts
 * 通知相關 API — 對應 API-001 §10.7
 */
import client from './client'
import type { MockNotification } from '@/mocks/notifications'

const USE_MOCK = import.meta.env.DEV

export interface NotificationItem {
  id: number
  type: string
  title: string
  body: string
  isRead: boolean
  createdAt: string
  actionUrl: string | null
}

function delay(ms: number) {
  return new Promise(r => setTimeout(r, ms))
}

export async function fetchNotifications(page: number): Promise<{
  notifications: NotificationItem[]
  hasMore: boolean
  unreadCount: number
}> {
  if (USE_MOCK) {
    const { mockFetchNotifications } = await import('@/mocks/notifications')
    await delay(300 + Math.random() * 300)
    const res = mockFetchNotifications(page)
    return {
      notifications: res.notifications.map(toItem),
      hasMore: res.hasMore,
      unreadCount: res.unreadCount,
    }
  }

  const res = await client.get<{
    success: boolean
    data: {
      unread_count: number
      notifications: {
        id: number; type: string; title: string; body: string
        is_read: boolean; created_at: string; action_url: string | null
      }[]
    }
    pagination: { total_pages: number; current_page: number }
  }>('/me/notifications', { params: { page, per_page: 10 } })

  return {
    notifications: res.data.data.notifications.map(n => ({
      id: n.id, type: n.type, title: n.title, body: n.body,
      isRead: n.is_read, createdAt: n.created_at, actionUrl: n.action_url,
    })),
    hasMore: res.data.pagination.current_page < res.data.pagination.total_pages,
    unreadCount: res.data.data.unread_count,
  }
}

export async function markNotificationRead(id: number): Promise<void> {
  if (USE_MOCK) { await delay(100); return }
  await client.patch(`/me/notifications/${id}/read`)
}

export async function markAllNotificationsRead(): Promise<void> {
  if (USE_MOCK) { await delay(200); return }
  await client.post('/me/notifications/read-all')
}

function toItem(n: MockNotification): NotificationItem {
  return {
    id: n.id, type: n.type, title: n.title, body: n.body,
    isRead: n.isRead, createdAt: n.createdAt, actionUrl: n.actionUrl,
  }
}
