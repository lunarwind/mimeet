<template>
  <div class="app-shell">
    <AnnouncementBanner />
    <router-view />
    <BottomNav v-if="showNav" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BottomNav from './BottomNav.vue'
import AnnouncementBanner from '@/components/common/AnnouncementBanner.vue'

const route = useRoute()

// Hide BottomNav on full-screen pages (chat conversation, QR scan)
const showNav = computed(() => route.name !== 'qr-scan')
</script>

<style scoped>
.app-shell {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: #f9f9fb;
}
</style>
