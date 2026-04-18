<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'

interface Announcement {
  id: number
  title: string
  content: string
  type: 'info' | 'warning' | 'success'
}

const announcements = ref<Announcement[]>([])

onMounted(async () => {
  try {
    const dismissed: number[] = JSON.parse(localStorage.getItem('dismissed_announcements') || '[]')
    const res = await client.get('/announcements/active')
    const all = res.data?.data?.announcements ?? []
    announcements.value = all.filter((a: Announcement) => !dismissed.includes(a.id))
  } catch {
    // silent — announcements are non-critical
  }
})

function dismiss(id: number) {
  announcements.value = announcements.value.filter(a => a.id !== id)
  const dismissed: number[] = JSON.parse(localStorage.getItem('dismissed_announcements') || '[]')
  dismissed.push(id)
  localStorage.setItem('dismissed_announcements', JSON.stringify(dismissed))
}
</script>

<template>
  <div v-for="a in announcements" :key="a.id" class="ann-banner" :class="`ann-banner--${a.type}`">
    <span class="ann-banner__icon">{{ a.type === 'warning' ? '⚠️' : a.type === 'success' ? '🎉' : '📢' }}</span>
    <div class="ann-banner__body">
      <strong v-if="a.title" class="ann-banner__title">{{ a.title }}</strong>
      <span class="ann-banner__text">{{ a.content }}</span>
    </div>
    <button class="ann-banner__close" @click="dismiss(a.id)" aria-label="關閉">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
</template>

<style scoped>
.ann-banner { display:flex; align-items:center; gap:10px; padding:10px 16px; font-size:13px; line-height:1.4; }
.ann-banner--info    { background:#EFF6FF; color:#1E40AF; }
.ann-banner--warning { background:#FEF3C7; color:#92400E; }
.ann-banner--success { background:#F0FDF4; color:#166534; }
.ann-banner__icon { font-size:16px; flex-shrink:0; }
.ann-banner__body { flex:1; min-width:0; }
.ann-banner__title { margin-right:6px; }
.ann-banner__text { }
.ann-banner__close { background:none; border:none; padding:4px; cursor:pointer; color:inherit; opacity:0.6; flex-shrink:0; display:flex; }
.ann-banner__close:hover { opacity:1; }
</style>
