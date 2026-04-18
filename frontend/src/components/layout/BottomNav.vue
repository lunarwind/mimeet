<template>
  <nav class="bottom-nav">
    <RouterLink to="/app/explore" class="nav-item" active-class="active">
      <div class="nav-icon">
        <svg
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <circle cx="11" cy="11" r="8" />
          <path d="m21 21-4.35-4.35" />
        </svg>
      </div>
      <span class="nav-label">探索</span>
    </RouterLink>

    <RouterLink to="/app/messages" class="nav-item" active-class="active">
      <div class="nav-icon relative">
        <svg
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
        <span v-if="totalUnread > 0" class="badge">
          {{ totalUnread > 99 ? '99+' : totalUnread }}
        </span>
      </div>
      <span class="nav-label">訊息</span>
    </RouterLink>

    <RouterLink to="/app/dates" class="nav-item" active-class="active">
      <div class="nav-icon">
        <svg
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
      </div>
      <span class="nav-label">約會</span>
    </RouterLink>

    <RouterLink to="/app/notifications" class="nav-item" active-class="active">
      <div class="nav-icon relative">
        <svg
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>
        <span v-if="unreadCount > 0" class="badge">
          {{ unreadCount > 99 ? '99+' : unreadCount }}
        </span>
      </div>
      <span class="nav-label">通知</span>
    </RouterLink>

    <RouterLink to="/app/settings" class="nav-item" active-class="active">
      <div class="nav-icon">
        <svg
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
          <circle cx="12" cy="7" r="4" />
        </svg>
      </div>
      <span class="nav-label">我的</span>
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { useChatStore } from '@/stores/chat'
import { useNotificationStore } from '@/stores/notification'
import { storeToRefs } from 'pinia'

const chatStore = useChatStore()
const notificationStore = useNotificationStore()
const { totalUnread } = storeToRefs(chatStore)
const { unreadCount } = storeToRefs(notificationStore)
</script>

<style scoped>
.bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 64px;
  background: white;
  border-top: 0.5px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: space-around;
  padding-bottom: env(safe-area-inset-bottom);
  z-index: 50;
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  color: #9ca3af;
  text-decoration: none;
  flex: 1 1 0;
  min-width: 60px;
  padding: 8px 0;
  white-space: nowrap;
}

.nav-item.active {
  color: #f0294e;
}

.nav-icon {
  position: relative;
  width: 24px;
  height: 24px;
}

.nav-label {
  font-size: 10px;
  font-family: 'Noto Sans TC', sans-serif;
}

.badge {
  position: absolute;
  top: -4px;
  right: -8px;
  background: #f0294e;
  color: white;
  font-size: 10px;
  font-weight: 600;
  min-width: 16px;
  height: 16px;
  border-radius: 99px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
}

/* ── Desktop (1024px+): constrained width ────────────────── */
@media (min-width: 1024px) {
  .bottom-nav {
    width: 100%;
    max-width: 800px;
    left: 50%;
    right: auto;
    transform: translateX(-50%);
    padding: 0 16px;
    border-top: none;
    border: 0.5px solid #e5e7eb;
    border-bottom: none;
    border-radius: 16px 16px 0 0;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.06);
  }
  .nav-item {
    min-width: 80px;
    padding: 10px 8px;
    border-radius: 8px;
    transition: background-color 0.2s;
  }
  .nav-item:hover { background-color: #f9fafb; }
  .nav-label { font-size: 11px; }
}

/* ── Large desktop (1440px+): floating pill ──────────────── */
@media (min-width: 1440px) {
  .bottom-nav {
    bottom: 16px;
    left: 50%;
    right: auto;
    transform: translateX(-50%);
    width: auto;
    min-width: 560px;
    max-width: 720px;
    height: 68px;
    padding: 0 12px;
    border-radius: 20px;
    border: none;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1), 0 0 0 0.5px rgba(0, 0, 0, 0.06);
  }
  .nav-item {
    min-width: 96px;
    padding: 10px 16px;
  }
  .nav-label { font-size: 12px; font-weight: 500; }
}
</style>
