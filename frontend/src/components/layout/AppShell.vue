<template>
  <div class="app-shell">
    <AnnouncementBanner />
    <UnverifiedPhoneBanner />
    <router-view />
    <BottomNav v-if="showNav" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BottomNav from './BottomNav.vue'
import AnnouncementBanner from '@/components/common/AnnouncementBanner.vue'
import UnverifiedPhoneBanner from '@/components/UnverifiedPhoneBanner.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()

// Hide BottomNav on:
//   - Full-screen pages (chat conversation, QR scan)
//   - Lv0 users (PR-1 D10): tabs would all be guard-blocked → looks "stuck"
const showNav = computed(() =>
  route.name !== 'qr-scan' && (authStore.user?.membership_level ?? 0) >= 1
)
</script>

<style scoped>
.app-shell {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: #f9f9fb;
}
</style>
