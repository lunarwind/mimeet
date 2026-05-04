<script setup lang="ts">
import { ref, watchEffect, onMounted, onUnmounted } from 'vue'
import QRCode from 'qrcode'

const props = defineProps<{
  qrToken: string
  expiresAt: string
  onRefresh?: () => void
}>()

const countdown = ref('')
const isExpired = ref(false)
const dataUrl = ref<string>('')
const copyStatus = ref<'idle' | 'copied'>('idle')
let timer: ReturnType<typeof setInterval> | undefined
let copyResetTimer: ReturnType<typeof setTimeout> | undefined

// PR-QR Step 4: 真實 QR 渲染（取代 mock SVG）
//   errorCorrectionLevel: 'H'  → 30% 容錯，手機對手機掃最穩
//   margin: 2                  → 標準最小 quiet zone
//   width: 240                 → 內部 240×240，CSS 120×120 = retina 2x
watchEffect(async () => {
  const token = props.qrToken
  if (!token) {
    dataUrl.value = ''
    return
  }
  try {
    dataUrl.value = await QRCode.toDataURL(token, {
      errorCorrectionLevel: 'H',
      margin: 2,
      width: 240,
    })
  } catch (e) {
    console.error('[QRCodeDisplay] toDataURL failed', e)
    dataUrl.value = ''
  }
})

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

// PR-QR Step 4: 文字代碼複製（含 fallback for insecure context / 老瀏覽器）
async function copyToken() {
  if (!props.qrToken) return
  let ok = false
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(props.qrToken)
      ok = true
    } else {
      throw new Error('clipboard API unavailable')
    }
  } catch {
    // Fallback：hidden textarea + execCommand
    try {
      const textarea = document.createElement('textarea')
      textarea.value = props.qrToken
      textarea.style.position = 'fixed'
      textarea.style.opacity = '0'
      document.body.appendChild(textarea)
      textarea.select()
      ok = document.execCommand('copy')
      document.body.removeChild(textarea)
    } catch (e) {
      console.error('[QRCodeDisplay] copy fallback failed', e)
    }
  }
  if (ok) {
    copyStatus.value = 'copied'
    if (copyResetTimer) clearTimeout(copyResetTimer)
    copyResetTimer = setTimeout(() => { copyStatus.value = 'idle' }, 2000)
  }
}

onMounted(() => { update(); timer = setInterval(update, 1000) })
onUnmounted(() => {
  if (timer) clearInterval(timer)
  if (copyResetTimer) clearTimeout(copyResetTimer)
})
</script>

<template>
  <div class="qr-display">
    <!-- 真實 QR (取代 mock SVG) -->
    <div class="qr-display__code" :class="{ 'qr-display__code--expired': isExpired }">
      <img
        v-if="dataUrl"
        :src="dataUrl"
        alt="約會驗證 QR Code"
        class="qr-display__img"
      />
      <div v-else class="qr-display__placeholder">
        <span>等待中…</span>
      </div>
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
      <button v-if="onRefresh" class="qr-display__refresh" @click="onRefresh">重新產生</button>
    </div>

    <!-- PR-QR Step 4: 文字代碼區塊（手動輸入 fallback / 對方無法掃碼時用）-->
    <div v-if="qrToken && !isExpired" class="qr-display__token-block">
      <p class="qr-display__token-hint">對方可掃描上方 QR Code，或手動輸入此代碼：</p>
      <div class="qr-display__token-text">{{ qrToken }}</div>
      <button
        class="qr-display__copy-btn"
        :class="{ 'qr-display__copy-btn--copied': copyStatus === 'copied' }"
        @click="copyToken"
      >
        {{ copyStatus === 'copied' ? '✓ 已複製' : '複製代碼' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.qr-display { display:flex; flex-direction:column; align-items:center; gap:16px; padding:24px 0; }

.qr-display__code { position:relative; padding:12px; background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
.qr-display__code--expired { opacity:0.4; }

.qr-display__img { width:120px; height:120px; display:block; }
.qr-display__placeholder { width:120px; height:120px; display:flex; align-items:center; justify-content:center; background:#F3F4F6; border-radius:6px; font-size:12px; color:#6B7280; }

.qr-display__overlay { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.8); border-radius:12px; font-size:16px; font-weight:700; color:#EF4444; }

.qr-display__timer { text-align:center; }
.qr-display__label { display:block; font-size:12px; color:#6B7280; }
.qr-display__time { display:block; font-size:28px; font-weight:800; color:#111827; font-variant-numeric:tabular-nums; margin-top:4px; }

.qr-display__expired { text-align:center; }
.qr-display__expired p { font-size:14px; color:#EF4444; font-weight:600; margin:0 0 12px; }
.qr-display__refresh { height:40px; padding:0 24px; border-radius:9999px; border:none; background:#F0294E; color:#fff; font-size:14px; font-weight:600; cursor:pointer; }
.qr-display__refresh:active { transform:scale(0.95); }

/* PR-QR Step 4: 文字代碼區塊 */
.qr-display__token-block { width:100%; max-width:300px; display:flex; flex-direction:column; align-items:center; gap:8px; padding:0 12px; }
.qr-display__token-hint { font-size:12px; color:#6B7280; text-align:center; margin:0; line-height:1.5; }
.qr-display__token-text { width:100%; font-family:'Courier New', Consolas, monospace; font-size:11px; color:#111827; background:#F3F4F6; border-radius:8px; padding:10px 12px; word-break:break-all; line-height:1.4; user-select:all; }
.qr-display__copy-btn { height:32px; padding:0 16px; border-radius:9999px; border:1.5px solid #F0294E; background:transparent; color:#F0294E; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.15s; }
.qr-display__copy-btn:active { transform:scale(0.95); }
.qr-display__copy-btn--copied { background:#10B981; border-color:#10B981; color:#fff; }
</style>
