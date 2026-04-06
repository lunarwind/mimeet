import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface Notification {
  id: number
  type: 'message' | 'visit' | 'favorite' | 'date' | 'system' | 'credit'
  title: string
  body: string
  isRead: boolean
  createdAt: string
  targetUrl: string | null
}

export const useNotificationStore = defineStore('notification', () => {
  const notifications = ref<Notification[]>([])
  const unreadCount = computed(() => notifications.value.filter((n) => !n.isRead).length)

  function setNotifications(list: Notification[]) {
    notifications.value = list
  }

  function markAsRead(notificationId: number) {
    const n = notifications.value.find((n) => n.id === notificationId)
    if (n) n.isRead = true
  }

  function markAllAsRead() {
    notifications.value.forEach((n) => (n.isRead = true))
  }

  function addNotification(notification: Notification) {
    notifications.value.unshift(notification)
  }

  return {
    notifications,
    unreadCount,
    setNotifications,
    markAsRead,
    markAllAsRead,
    addNotification,
  }
})
