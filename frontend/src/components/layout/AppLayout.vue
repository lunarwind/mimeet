<template>
  <div class="app-layout">
    <!-- 主內容區：由子路由填充 -->
    <main class="app-content">
      <router-view />
    </main>

    <!-- 底部導覽 -->
    <BottomNav v-if="!route.meta.hideBottomNav" />
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router'
import BottomNav from './BottomNav.vue'

const route = useRoute()
</script>

<style scoped>
.app-layout {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: #F9F9FB;
}

.app-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding-bottom: calc(64px + env(safe-area-inset-bottom));
}

/* 當 BottomNav 隱藏時（如 ChatView），app-content 為最後一個元素 */
.app-content:last-child {
  padding-bottom: 0;
}
</style>
