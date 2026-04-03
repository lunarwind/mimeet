<template>
  <Teleport to="body">
    <Transition name="toast">
      <div v-if="toast.visible" :class="['toast', `toast-${toast.type}`]">
        <svg v-if="toast.type === 'success'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M20 6 9 17l-5-5"/>
        </svg>
        <svg v-else-if="toast.type === 'error'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>
        </svg>
        <svg v-else-if="toast.type === 'warning'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
          <path d="M12 9v4M12 17h.01"/>
        </svg>
        <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
        </svg>
        <span class="toast-message">{{ toast.message }}</span>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { useUiStore } from '@/stores/ui'
import { storeToRefs } from 'pinia'

const uiStore = useUiStore()
const { toast } = storeToRefs(uiStore)
</script>

<style scoped>
.toast {
  position: fixed;
  top: 64px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 99px;
  font-size: 14px;
  font-family: 'Noto Sans TC', sans-serif;
  font-weight: 500;
  z-index: 300;
  white-space: nowrap;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.toast-success { background: #065F46; color: white; }
.toast-error   { background: #991B1B; color: white; }
.toast-warning { background: #92400E; color: white; }
.toast-info    { background: #1E40AF; color: white; }

.toast-message { font-size: 14px; }

.toast-enter-active,
.toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(-12px);
}
</style>
