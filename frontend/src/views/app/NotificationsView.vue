<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import TopBar from '@/components/layout/TopBar.vue'
import { useInfiniteScroll } from '@/composables/useInfiniteScroll'
import { useNotificationStore } from '@/stores/notification'
import {
  fetchNotifications,
  markNotificationRead,
  markAllNotificationsRead,
  type NotificationItem,
} from '@/api/notifications'

const router = useRouter()
const notificationStore = useNotificationStore()

const localUnreadCount = ref(0)

const {
  items: notifications,
  isLoading,
  isLoadingMore,
  hasMore,
  isEmpty,
  sentinelRef,
  fetchFirst,
} = useInfiniteScroll<NotificationItem>({
  fetchFn: async (page) => {
    const res = await fetchNotifications(page)
    localUnreadCount.value = res.unreadCount
    return { data: res.notifications, hasMore: res.hasMore }
  },
})

fetchFirst()

async function handleTap(item: NotificationItem) {
  if (!item.isRead) {
    item.isRead = true
    localUnreadCount.value = Math.max(0, localUnreadCount.value - 1)
    notificationStore.markAsRead(item.id)
    markNotificationRead(item.id)
  }
  if (item.actionUrl) {
    router.push(item.actionUrl)
  }
}

async function handleMarkAllRead() {
  notifications.value.forEach(n => { n.isRead = true })
  localUnreadCount.value = 0
  notificationStore.markAllAsRead()
  await markAllNotificationsRead()
}

// ── 通知類型 icon 對應 ──────────────────────────────────────
const TYPE_ICONS: Record<string, { emoji: string; bg: string }> = {
  new_message:         { emoji: '💬', bg: '#EFF6FF' },
  new_visitor:         { emoji: '👀', bg: '#F0FDF4' },
  new_follower:        { emoji: '❤️', bg: '#FEF2F2' },
  date_invite:         { emoji: '📅', bg: '#FFFBEB' },
  date_accepted:       { emoji: '✅', bg: '#F0FDF4' },
  credit_changed:      { emoji: '⭐', bg: '#FFFBEB' },
  subscription_expiry: { emoji: '⚠️', bg: '#FEF3C7' },
  verification_result: { emoji: '🛡️', bg: '#EFF6FF' },
  ticket_replied:      { emoji: '📋', bg: '#F5F3FF' },
  announcement:        { emoji: '📢', bg: '#F9FAFB' },
}

function getIcon(type: string) {
  return TYPE_ICONS[type] ?? { emoji: '🔔', bg: '#F9FAFB' }
}

function timeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return '剛剛'
  if (mins < 60) return `${mins} 分鐘前`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} 小時前`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days} 天前`
  return new Date(iso).toLocaleDateString('zh-TW', { month: 'short', day: 'numeric' })
}
</script>

<template>
  <div class="notify-view">
    <TopBar title="通知">
      <template #right>
        <button
          v-if="localUnreadCount > 0"
          class="mark-all-btn"
          @click="handleMarkAllRead"
        >
          全部已讀
        </button>
      </template>
    </TopBar>

    <div class="notify-body">
      <!-- Loading -->
      <div v-if="isLoading" class="notify-loading">
        <div v-for="n in 6" :key="n" class="skeleton-item">
          <div class="skeleton-icon" />
          <div class="skeleton-text">
            <div class="skeleton-line skeleton-line--title" />
            <div class="skeleton-line skeleton-line--body" />
          </div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="isEmpty" class="notify-empty">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <p class="notify-empty__text">暫時沒有通知</p>
      </div>

      <!-- List -->
      <template v-else>
        <div
          v-for="item in notifications"
          :key="item.id"
          class="notify-item"
          :class="{ 'notify-item--unread': !item.isRead }"
          @click="handleTap(item)"
        >
          <div class="notify-item__icon" :style="{ background: getIcon(item.type).bg }">
            {{ getIcon(item.type).emoji }}
          </div>
          <div class="notify-item__content">
            <p class="notify-item__body">{{ item.body }}</p>
            <span class="notify-item__time">{{ timeAgo(item.createdAt) }}</span>
          </div>
          <span v-if="!item.isRead" class="notify-item__dot" />
        </div>

        <div ref="sentinelRef" class="sentinel" />
        <div v-if="isLoadingMore" class="loading-more"><span class="spinner" /></div>
        <p v-if="!hasMore && notifications.length > 0" class="end-hint">已顯示全部通知</p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.notify-view {
  display: flex;
  flex-direction: column;
  flex: 1;
  background: #F9F9FB;
}

.notify-body { flex: 1; }

/* ── Mark All ────────────────────────────────────────────── */
.mark-all-btn {
  background: none;
  border: none;
  font-size: 13px;
  font-weight: 600;
  color: #F0294E;
  cursor: pointer;
  padding: 4px 8px;
}

/* ── Loading Skeleton ────────────────────────────────────── */
.notify-loading { padding: 0 16px; }

.skeleton-item {
  display: flex;
  gap: 12px;
  padding: 16px 0;
  border-bottom: 0.5px solid #F1F5F9;
}

.skeleton-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  flex-shrink: 0;
}

.skeleton-text { flex: 1; display: flex; flex-direction: column; gap: 8px; justify-content: center; }

.skeleton-line {
  height: 12px;
  border-radius: 6px;
  background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

.skeleton-line--title { width: 70%; }
.skeleton-line--body { width: 45%; }

@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* ── Empty ───────────────────────────────────────────────── */
.notify-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 64px 0;
}

.notify-empty__text { font-size: 14px; color: #9CA3AF; }

/* ── Item ────────────────────────────────────────────────── */
.notify-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  cursor: pointer;
  border-bottom: 0.5px solid #F3F4F6;
  transition: background 0.15s;
}

.notify-item:active { background: #F3F4F6; }
.notify-item--unread { background: #FEFCE8; }

.notify-item__icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}

.notify-item__content { flex: 1; min-width: 0; }

.notify-item__body {
  font-size: 14px;
  color: #111827;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notify-item--unread .notify-item__body { font-weight: 600; }

.notify-item__time {
  font-size: 12px;
  color: #9CA3AF;
  margin-top: 2px;
  display: block;
}

.notify-item__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #F0294E;
  flex-shrink: 0;
}

/* ── Footer ──────────────────────────────────────────────── */
.sentinel { height: 1px; }
.loading-more { display: flex; justify-content: center; padding: 16px 0; }
.spinner { width: 24px; height: 24px; border-radius: 50%; border: 2.5px solid #E5E7EB; border-top-color: #F0294E; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.end-hint { text-align: center; font-size: 12px; color: #CBD5E1; padding: 16px 0; }
</style>
