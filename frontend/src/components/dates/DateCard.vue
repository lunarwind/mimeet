<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import type { DateInvitation } from '@/types/chat'
import QRCodeDisplay from './QRCodeDisplay.vue'

const props = defineProps<{ date: DateInvitation; myId: number }>()
const emit = defineEmits<{
  accept: [id: number]
  reject: [id: number]
  scan: [id: number]
}>()

const countdown = ref('')
const showQR = ref(false)
let timer: ReturnType<typeof setInterval> | undefined

const otherNickname = computed(() =>
  props.date.inviterId === props.myId ? props.date.inviteeNickname : props.date.inviterNickname
)
const otherAvatar = computed(() =>
  props.date.inviterId === props.myId ? props.date.inviteeAvatar : props.date.inviterAvatar
)

const statusLabel = computed(() => {
  switch (props.date.status) {
    case 'pending': return '待接受'
    case 'accepted': return '進行中'
    case 'verified': return '已完成'
    case 'rejected': return '已拒絕'
    case 'expired': return '已過期'
    default: return ''
  }
})

const isWithin2Hours = computed(() => {
  if (props.date.status !== 'accepted') return false
  const diff = new Date(props.date.scheduledAt).getTime() - Date.now()
  return diff > 0 && diff < 2 * 3600000
})

function formatScheduled(iso: string): string {
  const d = new Date(iso)
  return d.toLocaleDateString('zh-TW', { month: 'short', day: 'numeric' }) + ' ' +
    d.toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' })
}

function updateCountdown() {
  const diff = new Date(props.date.scheduledAt).getTime() - Date.now()
  if (diff <= 0) { countdown.value = '即將開始'; return }
  const h = Math.floor(diff / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  countdown.value = h > 0 ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}` : `${m}:${String(s).padStart(2, '0')}`
}

onMounted(() => {
  if (props.date.status === 'accepted') {
    updateCountdown()
    timer = setInterval(updateCountdown, 1000)
  }
})
onUnmounted(() => { if (timer) clearInterval(timer) })
</script>

<template>
  <div class="date-card">
    <!-- 頂部 -->
    <div class="date-card__top">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>
      <span class="date-card__status">{{ statusLabel }}</span>
    </div>

    <!-- 中間 -->
    <div class="date-card__middle">
      <img v-if="otherAvatar" :src="otherAvatar" class="date-card__avatar" alt="" />
      <div>
        <p class="date-card__nickname">{{ otherNickname }}</p>
        <p class="date-card__time">{{ formatScheduled(date.scheduledAt) }}</p>
        <p v-if="date.location" class="date-card__location">📍 {{ date.location }}</p>
      </div>
    </div>

    <!-- 底部 -->
    <div class="date-card__bottom">
      <!-- 待接受 -->
      <template v-if="date.status === 'pending'">
        <button class="date-btn date-btn--accept" @click="emit('accept', date.id)">接受</button>
        <button class="date-btn date-btn--reject" @click="emit('reject', date.id)">拒絕</button>
      </template>

      <!-- 進行中 + 2小時內 -->
      <template v-else-if="isWithin2Hours">
        <span class="date-card__countdown">⏱ {{ countdown }}</span>
        <button class="date-btn date-btn--qr" @click="showQR = !showQR">{{ showQR ? '收起 QR' : '顯示 QR' }}</button>
        <button class="date-btn date-btn--scan" @click="emit('scan', date.id)">掃碼驗證</button>
      </template>

      <!-- 進行中 但還不到2小時 -->
      <template v-else-if="date.status === 'accepted'">
        <span class="date-card__countdown">⏱ {{ countdown }}</span>
      </template>

      <!-- 已完成 -->
      <template v-else-if="date.status === 'verified' && date.creditScoreChange">
        <span class="date-card__credit">誠信分數 {{ date.creditScoreChange > 0 ? '+' : '' }}{{ date.creditScoreChange }}</span>
      </template>
    </div>

    <!-- QR Code 展開區 -->
    <div v-if="showQR && date.expiresAt" class="date-card__qr">
      <QRCodeDisplay
        :expires-at="date.expiresAt"
        :on-refresh="() => { /* mock refresh */ }"
      />
    </div>
  </div>
</template>

<style scoped>
.date-card { background:linear-gradient(135deg, #F0294E, #C0203E); color:#fff; border-radius:20px; padding:18px; margin-bottom:12px; }

.date-card__top { display:flex; align-items:center; gap:8px; margin-bottom:14px; }
.date-card__status { font-size:12px; font-weight:600; background:rgba(255,255,255,0.2); padding:2px 10px; border-radius:9999px; }

.date-card__middle { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
.date-card__avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.3); flex-shrink:0; }
.date-card__nickname { font-size:16px; font-weight:700; }
.date-card__time { font-size:13px; opacity:0.85; margin-top:2px; }
.date-card__location { font-size:12px; opacity:0.75; margin-top:2px; }

.date-card__bottom { display:flex; align-items:center; gap:8px; }
.date-card__countdown { font-size:14px; font-weight:700; font-variant-numeric:tabular-nums; flex:1; }
.date-card__credit { font-size:14px; font-weight:700; background:rgba(255,255,255,0.2); padding:4px 12px; border-radius:8px; }

.date-btn { height:36px; padding:0 16px; border-radius:9999px; border:none; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.15s; }
.date-btn:active { transform:scale(0.95); }
.date-btn--accept { background:#fff; color:#F0294E; }
.date-btn--reject { background:rgba(255,255,255,0.2); color:#fff; }
.date-btn--qr { background:rgba(255,255,255,0.2); color:#fff; }
.date-btn--scan { background:#fff; color:#F0294E; }

.date-card__qr { margin-top:14px; background:rgba(255,255,255,0.95); border-radius:14px; padding:4px; }
</style>
