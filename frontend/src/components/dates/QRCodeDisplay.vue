<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps<{
  expiresAt: string
  onRefresh: () => void
}>()

const countdown = ref('')
const isExpired = ref(false)
let timer: ReturnType<typeof setInterval> | undefined

function update() {
  const diff = new Date(props.expiresAt).getTime() - Date.now()
  if (diff <= 0) {
    isExpired.value = true
    countdown.value = '00:00'
    if (timer) clearInterval(timer)
    return
  }
  const m = Math.floor(diff / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  countdown.value = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

onMounted(() => { update(); timer = setInterval(update, 1000) })
onUnmounted(() => { if (timer) clearInterval(timer) })
</script>

<template>
  <div class="qr-display">
    <!-- Mock QR -->
    <div class="qr-display__code" :class="{ 'qr-display__code--expired': isExpired }">
      <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
        <!-- Mock QR pattern -->
        <rect width="120" height="120" rx="8" fill="#fff"/>
        <rect x="10" y="10" width="30" height="30" rx="4" fill="#111827"/>
        <rect x="80" y="10" width="30" height="30" rx="4" fill="#111827"/>
        <rect x="10" y="80" width="30" height="30" rx="4" fill="#111827"/>
        <rect x="15" y="15" width="20" height="20" rx="2" fill="#fff"/>
        <rect x="85" y="15" width="20" height="20" rx="2" fill="#fff"/>
        <rect x="15" y="85" width="20" height="20" rx="2" fill="#fff"/>
        <rect x="20" y="20" width="10" height="10" fill="#111827"/>
        <rect x="90" y="20" width="10" height="10" fill="#111827"/>
        <rect x="20" y="90" width="10" height="10" fill="#111827"/>
        <!-- Data blocks -->
        <rect x="50" y="10" width="8" height="8" fill="#111827"/>
        <rect x="62" y="10" width="8" height="8" fill="#111827"/>
        <rect x="50" y="22" width="8" height="8" fill="#111827"/>
        <rect x="50" y="50" width="20" height="20" rx="4" fill="#F0294E"/>
        <rect x="10" y="50" width="8" height="8" fill="#111827"/>
        <rect x="22" y="50" width="8" height="8" fill="#111827"/>
        <rect x="10" y="62" width="8" height="8" fill="#111827"/>
        <rect x="80" y="50" width="8" height="8" fill="#111827"/>
        <rect x="92" y="50" width="8" height="8" fill="#111827"/>
        <rect x="50" y="80" width="8" height="8" fill="#111827"/>
        <rect x="62" y="80" width="8" height="8" fill="#111827"/>
        <rect x="80" y="80" width="8" height="8" fill="#111827"/>
        <rect x="92" y="92" width="8" height="8" fill="#111827"/>
      </svg>
      <div v-if="isExpired" class="qr-display__overlay">
        <span>已過期</span>
      </div>
    </div>

    <!-- Timer / Expired -->
    <div v-if="!isExpired" class="qr-display__timer">
      <span class="qr-display__label">有效時間</span>
      <span class="qr-display__time">{{ countdown }}</span>
    </div>
    <div v-else class="qr-display__expired">
      <p>QR Code 已過期</p>
      <button class="qr-display__refresh" @click="onRefresh">重新產生</button>
    </div>
  </div>
</template>

<style scoped>
.qr-display { display:flex; flex-direction:column; align-items:center; gap:16px; padding:24px 0; }

.qr-display__code { position:relative; padding:12px; background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
.qr-display__code--expired { opacity:0.4; }
.qr-display__overlay { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.8); border-radius:12px; font-size:16px; font-weight:700; color:#EF4444; }

.qr-display__timer { text-align:center; }
.qr-display__label { display:block; font-size:12px; color:#6B7280; }
.qr-display__time { display:block; font-size:28px; font-weight:800; color:#111827; font-variant-numeric:tabular-nums; margin-top:4px; }

.qr-display__expired { text-align:center; }
.qr-display__expired p { font-size:14px; color:#EF4444; font-weight:600; margin:0 0 12px; }
.qr-display__refresh { height:40px; padding:0 24px; border-radius:9999px; border:none; background:#F0294E; color:#fff; font-size:14px; font-weight:600; cursor:pointer; }
.qr-display__refresh:active { transform:scale(0.95); }
</style>
