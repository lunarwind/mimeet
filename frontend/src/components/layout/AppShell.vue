<template>
  <div class="app-shell">
    <router-view />
    <BottomNav v-if="showNav" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BottomNav from './BottomNav.vue'

const route = useRoute()

// Hide BottomNav on full-screen pages (chat conversation, QR scan)
const showNav = computed(() => route.name !== 'chat' && route.name !== 'qr-scan')
</script>

<style scoped>
.app-shell {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: #f9f9fb;
}
</style>
