<template>
  <div :class="['avatar', `avatar-${size}`]">
    <img
      v-if="src"
      :src="src"
      :alt="alt"
      class="avatar-img"
      @error="onError"
    />
    <div v-else class="avatar-fallback">
      {{ initials }}
    </div>
    <span v-if="showOnline" :class="['online-dot', isOnline ? 'online' : 'offline']" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

const props = withDefaults(defineProps<{
  src?: string | null
  alt?: string
  nickname?: string
  size?: 'sm' | 'md' | 'lg' | 'xl'
  showOnline?: boolean
  isOnline?: boolean
}>(), {
  size: 'md',
  showOnline: false,
  isOnline: false,
})

const imgError = ref(false)

const initials = computed(() => {
  if (!props.nickname) return '?'
  return props.nickname.charAt(0).toUpperCase()
})

function onError() {
  imgError.value = true
}
</script>

<style scoped>
.avatar {
  position: relative;
  border-radius: 50%;
  flex-shrink: 0;
  overflow: hidden;
}

.avatar-sm  { width: 32px; height: 32px; }
.avatar-md  { width: 48px; height: 48px; }
.avatar-lg  { width: 56px; height: 56px; }
.avatar-xl  { width: 80px; height: 80px; }

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-fallback {
  width: 100%;
  height: 100%;
  background: #F4C0D1;
  color: #72243E;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
  font-family: 'Noto Sans TC', sans-serif;
}

.online-dot {
  position: absolute;
  bottom: 1px;
  right: 1px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  border: 1.5px solid white;
}

.online  { background: #10B981; }
.offline { background: #D1D5DB; }
</style>
