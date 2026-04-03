<template>
  <div :class="['loading-wrap', { fullscreen }]">
    <div class="spinner" :style="{ width: size + 'px', height: size + 'px' }" />
    <p v-if="text" class="loading-text">{{ text }}</p>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  fullscreen?: boolean
  size?: number
  text?: string
}>(), {
  fullscreen: false,
  size: 32,
})
</script>

<style scoped>
.loading-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 24px;
}

.loading-wrap.fullscreen {
  position: fixed;
  inset: 0;
  background: rgba(255, 255, 255, 0.8);
  z-index: 100;
}

.spinner {
  border: 3px solid #F3F4F6;
  border-top-color: #F0294E;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.loading-text {
  font-size: 14px;
  color: #6B7280;
  font-family: 'Noto Sans TC', sans-serif;
}
</style>
