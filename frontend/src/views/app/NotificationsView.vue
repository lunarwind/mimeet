<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import { useUiStore } from '@/stores/ui'
import client from '@/api/client'

const router = useRouter()
const uiStore = useUiStore()

interface Notification {
  id: number
  type: string
  title: string
  body: string
  actionUrl: string
  isRead: boolean
  createdAt: string
  createdAtHuman: string
}

const notifications = ref<Notification[]>([])
const isLoading = ref(false)

const unreadCount = computed(() => notifications.value.filter(n => !n.isRead).length)

const ICON_MAP: Record<string, string> = {
  new_message: '💬',
  new_visitor: '👀',
  new_follower: '❤️',
  date_invite: '📅',
  date_accepted: '📅',
  date_verified: '✅',
  credit_changed: '📊',
  subscription_expiry: '⚠️',
  verification_result: '🔒',
  ticket_replied: '📋',
}

onMounted(async () => {
  isLoading.value = true
  try {
    const res = await client.get('/notifications')
    const items = res.data?.data?.notifications ?? []
    notifications.value = items.map((n: Record<string, unknown>) => ({
      id: n.id, type: n.type, title: n.title, body: n.body,
      isRead: !!n.is_read, createdAt: n.created_at as string,
      createdAtHuman: n.created_at ? new Date(n.created_at as string).toLocaleString('zh-TW') : '',
    }))
  } catch {
    notifications.value = []
  }
  isLoading.value = false
})

function handleClick(notif: Notification) {
  notif.isRead = true
  router.push(notif.actionUrl)
}

function markAllRead() {
  notifications.value.forEach(n => { n.isRead = true })
  uiStore.showToast('已全部標記為已讀', 'success')
}
</script>

<template>
  <AppLayout title="通知">
    <template #topbar-right>
      <button
        v-if="unreadCount > 0"
        class="read-all-btn"
        @click="markAllRead"
      >
        全部已讀
      </button>
    </template>

    <div class="notif-page">
      <!-- Loading -->
      <div v-if="isLoading" class="notif-loading">
        <div class="spinner" />
      </div>

      <!-- Empty -->
      <div v-else-if="notifications.length === 0" class="notif-empty">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.2">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <div class="notif-empty__title">目前沒有任何通知</div>
      </div>

      <!-- List -->
      <div v-else class="notif-list">
        <div
          v-for="notif in notifications"
          :key="notif.id"
          class="notif-item"
          :class="{ 'notif-item--unread': !notif.isRead }"
          @click="handleClick(notif)"
        >
          <div v-if="!notif.isRead" class="notif-item__indicator" />
          <span class="notif-item__icon">{{ ICON_MAP[notif.type] || '📢' }}</span>
          <div class="notif-item__content">
            <div class="notif-item__title">{{ notif.title }}</div>
            <div class="notif-item__body">{{ notif.body }}</div>
            <div class="notif-item__time">{{ notif.createdAtHuman }}</div>
          </div>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style>
.notif-page { padding: 0; }

.read-all-btn { padding: 6px 12px; border-radius: 8px; border: none; background: #FFF5F7; color: #F0294E; font-size: 13px; font-weight: 600; cursor: pointer; }
.read-all-btn:hover { background: #FFE4EA; }

.notif-loading { display: flex; justify-content: center; padding: 48px 0; }
.spinner { width: 24px; height: 24px; border: 3px solid #E5E7EB; border-top-color: #F0294E; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.notif-empty { text-align: center; padding: 64px 24px; }
.notif-empty__title { font-size: 16px; font-weight: 600; color: #9CA3AF; margin-top: 16px; }

.notif-list { background: white; }

.notif-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 0.5px solid #F1F5F9; cursor: pointer; position: relative; transition: background 0.15s; }
.notif-item:active { background: #F9FAFB; }
.notif-item--unread { background: #FFFBFE; }
.notif-item__indicator { position: absolute; left: 0; top: 14px; bottom: 14px; width: 3px; background: #F0294E; border-radius: 0 2px 2px 0; }
.notif-item__icon { font-size: 24px; flex-shrink: 0; width: 36px; text-align: center; }
.notif-item__content { flex: 1; min-width: 0; }
.notif-item__title { font-size: 14px; font-weight: 600; color: #111827; }
.notif-item__body { font-size: 13px; color: #6B7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.notif-item__time { font-size: 11px; color: #9CA3AF; margin-top: 2px; }
</style>
