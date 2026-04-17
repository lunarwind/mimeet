<template>
  <div class="app-shell">
    <router-view />
    <BottomNav />
  </div>
</template>

<script setup lang="ts">
import BottomNav from './BottomNav.vue'
</script>

<style scoped>
.app-shell {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: #f9f9fb;
}
</style>
