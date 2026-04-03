<template>
  <div class="app-layout">
    <!-- TopBar -->
    <header class="top-bar">
      <div class="top-bar-left">
        <button v-if="showBack" @click="router.back()" class="back-btn">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="m15 18-6-6 6-6"/>
          </svg>
        </button>
        <span class="top-bar-title">{{ title }}</span>
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
import { useRouter } from 'vue-router'
import BottomNav from './BottomNav.vue'

const router = useRouter()

withDefaults(defineProps<{
  title?: string
  showBack?: boolean
}>(), {
  title: 'MiMeet',
  showBack: false,
})
</script>

<style scoped>
.app-layout {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: #F9F9FB;
}

.top-bar {
  position: sticky;
  top: 0;
  height: 56px;
  background: white;
  border-bottom: 0.5px solid #E5E7EB;
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
  padding-bottom: calc(64px + env(safe-area-inset-bottom));
}
</style>
