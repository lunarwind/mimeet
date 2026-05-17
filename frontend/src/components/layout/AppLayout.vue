<template>
  <div class="app-layout">
    <!-- TopBar -->
    <header class="top-bar">
      <div class="top-bar-left">
        <button v-if="showBack" @click="router.back()" class="back-btn">
          <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="m15 18-6-6 6-6" />
          </svg>
        </button>
        <span v-if="!showBack" class="top-bar-logo" @click="handleLogoClick">
          <span class="top-bar-logo__mi">Mi</span><span class="top-bar-logo__meet">Meet</span>
        </span>
        <span v-if="title !== 'MiMeet'" class="top-bar-title">{{ title }}</span>
      </div>
      <div class="top-bar-right">
        <slot name="topbar-right" />
      </div>
    </header>

    <!-- 主內容區 -->
    <main class="main-content">
      <slot />
    </main>

    <!-- 底部導覽 -->
    <BottomNav />
  </div>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUserChannel } from '@/composables/useChat'
import { destroyEcho } from '@/utils/echo'
import BottomNav from './BottomNav.vue'

const router = useRouter()
const auth = useAuthStore()
const { subscribe: subscribeUserChannel, unsubscribe: unsubscribeUserChannel } = useUserChannel()

watch(
  () => auth.isLoggedIn,
  (loggedIn) => {
    if (loggedIn) {
      subscribeUserChannel()
    } else {
      unsubscribeUserChannel()
      destroyEcho()
    }
  },
  { immediate: true },
)

const props = withDefaults(
  defineProps<{
    title?: string
    showBack?: boolean
  }>(),
  {
    title: 'MiMeet',
    showBack: false,
  },
)

function handleLogoClick() {
  if (auth.isLoggedIn && auth.user?.email_verified) {
    router.push('/app/explore')
  } else {
    router.push('/login')
  }
}
</script>

<style scoped>
.app-layout {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: #f9f9fb;
}

.top-bar {
  position: sticky;
  top: 0;
  height: 56px;
  background: white;
  border-bottom: 0.5px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  z-index: 40;
}

.top-bar-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.top-bar-logo {
  font-family: 'Noto Serif TC', serif;
  font-size: 20px;
  font-weight: 600;
  letter-spacing: -0.5px;
  cursor: pointer;
  line-height: 1;
}
.top-bar-logo__mi { color: #F0294E; }
.top-bar-logo__meet { color: #111827; }

.top-bar-title {
  font-size: 17px;
  font-weight: 500;
  color: #111827;
  font-family: 'Noto Sans TC', sans-serif;
}

.back-btn {
  background: none;
  border: none;
  padding: 4px;
  cursor: pointer;
  color: #374151;
  display: flex;
  align-items: center;
}

.top-bar-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.main-content {
  flex: 1;
  overflow-y: auto;
  padding-bottom: var(--app-bottom-inset);
}

/* ── Tablet (768px+) ──────────────────────────────────────── */
@media (min-width: 768px) {
  .top-bar { padding: 0 24px; max-width: 720px; margin: 0 auto; width: 100%; }
  .main-content { max-width: 720px; margin: 0 auto; width: 100%; }
}

/* ── Desktop (1024px+) ───────────────────────────────────── */
@media (min-width: 1024px) {
  .top-bar { max-width: 800px; }
  .main-content { max-width: 800px; }
}

/* ── Large desktop (1440px+) ─────────────────────────────── */
@media (min-width: 1440px) {
  .top-bar { max-width: 960px; }
  /* 1440px+ 用 floating pill BottomNav，視覺底距較大 */
  .main-content { max-width: 960px; padding-bottom: calc(var(--app-bottom-inset) + 16px); }
}

/* ── 4K / ultra-wide (1920px+) ───────────────────────────── */
@media (min-width: 1920px) {
  .top-bar { max-width: 1100px; }
  .main-content { max-width: 1100px; }
}
</style>
