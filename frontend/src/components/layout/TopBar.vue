<template>
  <header class="top-bar" :class="{ 'top-bar--transparent': transparent }">
    <div class="top-bar__left">
      <slot name="left">
        <button v-if="showBack" class="top-bar__back" @click="router.back()" aria-label="返回">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m15 18-6-6 6-6" />
          </svg>
        </button>
      </slot>
      <h1 v-if="title" class="top-bar__title">{{ title }}</h1>
    </div>
    <div class="top-bar__right">
      <slot name="right" />
    </div>
  </header>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

withDefaults(
  defineProps<{
    title?: string
    showBack?: boolean
    transparent?: boolean
  }>(),
  {
    title: '',
    showBack: false,
    transparent: false,
  },
)
</script>

<style scoped>
.top-bar {
  position: sticky;
  top: 0;
  height: 56px;
  background: #fff;
  border-bottom: 0.5px solid #E5E7EB;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  z-index: 40;
  flex-shrink: 0;
}

.top-bar--transparent {
  background: transparent;
  border-bottom: none;
}

.top-bar__left {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.top-bar__title {
  font-size: 17px;
  font-weight: 600;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.top-bar__back {
  background: none;
  border: none;
  padding: 4px;
  margin: -4px;
  cursor: pointer;
  color: #374151;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.top-bar__back:active {
  opacity: 0.6;
}

.top-bar__right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
</style>
