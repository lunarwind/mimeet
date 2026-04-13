<script setup lang="ts">
import { ref, nextTick, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { verifyDateQR, getCurrentPosition } from '@/api/dates'
import jsQR from 'jsqr'

const router = useRouter()
// ── 狀態 ──────────────────────────────────────────────────
type ViewState = 'camera' | 'manual' | 'denied' | 'gps-prompt' | 'verifying' | 'success' | 'error'
const viewState = ref<ViewState>('camera')
const manualCode = ref('')
const isVerifying = ref(false)
const verifyResult = ref<{ success: boolean; credit: number; gpsPassed: boolean; status: string } | null>(null)
const gpsStatus = ref<'idle' | 'fetching' | 'got' | 'denied'>('idle')
const pendingToken = ref('')  // QR code scanned but not yet verified
const errorMsg = ref('')
const scanStatus = ref('') // 即時掃描狀態提示
const videoRef = ref<HTMLVideoElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
let mediaStream: MediaStream | null = null
let scanRafId: number | null = null

// ── 相機 ──────────────────────────────────────────────────
async function startCamera() {
  try {
    scanStatus.value = '正在開啟相機…'
    mediaStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } },
    })

    // 先切到 camera 狀態，讓 v-if 渲染 <video>
    viewState.value = 'camera'
    await nextTick()

    // 現在 videoRef 已經存在於 DOM
    if (videoRef.value) {
      videoRef.value.srcObject = mediaStream
      videoRef.value.onloadedmetadata = () => {
        videoRef.value!.play()
        startScanning()
      }
    }
    scanStatus.value = '對準 QR Code…'
  } catch (e) {
    viewState.value = 'denied'
  }
}

function stopCamera() {
  stopScanning()
  mediaStream?.getTracks().forEach(t => t.stop())
  mediaStream = null
}

// ── QR 掃描迴圈 ──────────────────────────────────────────
function startScanning() {
  const video = videoRef.value
  const canvas = canvasRef.value
  if (!video || !canvas) return

  const ctx = canvas.getContext('2d', { willReadFrequently: true })
  if (!ctx) return

  function tick() {
    if (!video || video.readyState !== video.HAVE_ENOUGH_DATA) {
      scanRafId = requestAnimationFrame(tick)
      return
    }

    canvas!.width = video.videoWidth
    canvas!.height = video.videoHeight
    ctx!.drawImage(video, 0, 0, canvas!.width, canvas!.height)

    const imageData = ctx!.getImageData(0, 0, canvas!.width, canvas!.height)
    const qr = jsQR(imageData.data, imageData.width, imageData.height, {
      inversionAttempts: 'dontInvert',
    })

    if (qr && qr.data) {
      scanStatus.value = '掃描成功！'
      stopScanning()
      stopCamera()
      handleVerify(qr.data)
      return
    }

    scanRafId = requestAnimationFrame(tick)
  }

  scanRafId = requestAnimationFrame(tick)
}

function stopScanning() {
  if (scanRafId !== null) {
    cancelAnimationFrame(scanRafId)
    scanRafId = null
  }
}

// ── 掃碼成功 → 顯示 GPS 授權說明 ─────────────────────────
function handleVerify(code: string) {
  if (!code.trim()) return
  pendingToken.value = code
  viewState.value = 'gps-prompt'
}

// ── 用戶選擇「允許 GPS」→ 取得定位後送出驗證 ──────────────
async function submitWithGps() {
  isVerifying.value = true
  viewState.value = 'verifying'
  gpsStatus.value = 'fetching'
  try {
    const gps = await getCurrentPosition()
    gpsStatus.value = gps ? 'got' : 'denied'
    await doVerify(gps?.latitude ?? null, gps?.longitude ?? null)
  } catch {
    errorMsg.value = '驗證失敗，請確認 QR Code 正確'
    viewState.value = 'error'
  } finally {
    isVerifying.value = false
  }
}

// ── 用戶選擇「跳過 GPS」→ 不取得定位直接送出 ──────────────
async function submitWithoutGps() {
  isVerifying.value = true
  viewState.value = 'verifying'
  gpsStatus.value = 'denied'
  try {
    await doVerify(null, null)
  } catch {
    errorMsg.value = '驗證失敗，請確認 QR Code 正確'
    viewState.value = 'error'
  } finally {
    isVerifying.value = false
  }
}

// ── 實際送出驗證 API ─────────────────────────────────────
async function doVerify(lat: number | null, lng: number | null) {
  const res = await verifyDateQR(pendingToken.value, lat, lng)
  if (res.status === 'waiting') {
    verifyResult.value = { success: true, credit: 0, gpsPassed: false, status: 'waiting' }
  } else {
    verifyResult.value = {
      success: res.success, credit: res.creditScoreAwarded,
      gpsPassed: res.gpsPassed, status: res.status,
    }
  }
  viewState.value = 'success'
}

function handleManualSubmit() {
  handleVerify(manualCode.value)
}

function goBack() { router.back() }
function goDates() { router.push('/app/dates') }

onMounted(() => {
  startCamera()
})
onUnmounted(stopCamera)
</script>

<template>
  <div class="scan-view">
    <!-- TopBar -->
    <header class="scan-topbar">
      <button class="scan-topbar__back" @click="goBack">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <span class="scan-topbar__title">掃碼驗證</span>
    </header>

    <!-- 相機模式 -->
    <template v-if="viewState === 'camera'">
      <div class="camera-area">
        <video ref="videoRef" autoplay playsinline muted class="camera-video" />
        <canvas ref="canvasRef" class="camera-canvas" />
        <div class="camera-overlay">
          <div class="camera-frame">
            <span class="camera-corner camera-corner--tl" />
            <span class="camera-corner camera-corner--tr" />
            <span class="camera-corner camera-corner--bl" />
            <span class="camera-corner camera-corner--br" />
          </div>
          <p class="camera-hint">{{ scanStatus || '將 QR Code 對準框內' }}</p>
          <button class="camera-manual-btn" @click="stopCamera(); viewState = 'manual'">手動輸入代碼</button>
        </div>
      </div>
    </template>

    <!-- DEV 手動輸入 -->
    <template v-if="viewState === 'manual'">
      <div class="manual-area">
        <div class="manual-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#F0294E" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>
        </div>
        <p class="manual-title">手動輸入</p>
        <p class="manual-hint">請輸入約會 QR Code</p>
        <input v-model="manualCode" type="text" class="manual-input" placeholder="輸入 QR Code…" @keyup.enter="handleManualSubmit" />
        <button class="manual-btn" @click="handleManualSubmit" :disabled="!manualCode.trim()">驗證</button>
        <div class="manual-divider" />
        <button class="manual-btn manual-btn--camera" @click="startCamera">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          開啟相機掃碼
        </button>
      </div>
    </template>

    <!-- 相機授權拒絕 -->
    <template v-if="viewState === 'denied'">
      <div class="denied-area">
        <div class="denied-icon">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
            <line x1="1" y1="1" x2="23" y2="23" stroke="#EF4444" stroke-width="2"/>
          </svg>
        </div>
        <p class="denied-title">需要相機權限</p>
        <p class="denied-text">需要相機權限才能掃描 QR Code，請在瀏覽器設定中允許相機存取。</p>
        <button class="denied-btn" @click="startCamera">重試</button>
        <button class="denied-btn denied-btn--manual" @click="viewState = 'manual'">手動輸入代碼</button>
      </div>
    </template>

    <!-- GPS 授權說明（掃碼成功後、驗證前顯示） -->
    <template v-if="viewState === 'gps-prompt'">
      <div class="gps-prompt-area">
        <div class="gps-prompt-icon">📍</div>
        <h2 class="gps-prompt-title">開啟定位可獲得更高分數</h2>
        <div class="gps-prompt-card">
          <div class="gps-prompt-row">
            <span class="gps-prompt-badge gps-prompt-badge--high">+5 分</span>
            <span>允許 GPS 定位，且在約定地點 500m 內</span>
          </div>
          <div class="gps-prompt-row">
            <span class="gps-prompt-badge gps-prompt-badge--low">+2 分</span>
            <span>不提供 GPS 或距離超過 500m</span>
          </div>
        </div>
        <p class="gps-prompt-note">系統將在您按下按鈕後請求定位權限，您可以隨時拒絕。</p>
        <button class="gps-prompt-btn gps-prompt-btn--allow" @click="submitWithGps">
          📍 允許定位並驗證（推薦）
        </button>
        <button class="gps-prompt-btn gps-prompt-btn--skip" @click="submitWithoutGps">
          跳過定位，直接驗證
        </button>
      </div>
    </template>

    <!-- 驗證中 -->
    <template v-if="viewState === 'verifying'">
      <div class="result-area">
        <span class="spinner" />
        <p>{{ gpsStatus === 'fetching' ? '正在取得 GPS 定位…' : '驗證中…' }}</p>
      </div>
    </template>

    <!-- 成功 -->
    <template v-if="viewState === 'success'">
      <div class="result-area">
        <template v-if="verifyResult?.status === 'waiting'">
          <div class="result-icon">⏳</div>
          <h2 class="result-title">已掃碼，等待對方</h2>
          <p class="result-text">對方掃碼後驗證即完成</p>
          <p v-if="gpsStatus === 'got'" class="result-gps result-gps--ok">📍 GPS 定位成功</p>
          <p v-else class="result-gps result-gps--no">📍 GPS 未取得（驗證仍有效，但加分較少）</p>
        </template>
        <template v-else>
          <div class="result-icon result-icon--success">✅</div>
          <h2 class="result-title">約會驗證成功！</h2>
          <p class="result-text">誠信分數 +{{ verifyResult?.credit ?? 0 }}</p>
          <p v-if="verifyResult?.gpsPassed" class="result-gps result-gps--ok">📍 GPS 驗證通過（500m 內）</p>
          <p v-else class="result-gps result-gps--no">📍 GPS 未通過（距離過遠或未授權）</p>
        </template>
        <button class="result-btn" @click="goDates">返回約會列表</button>
      </div>
    </template>

    <!-- 失敗 -->
    <template v-if="viewState === 'error'">
      <div class="result-area">
        <div class="result-icon result-icon--error">❌</div>
        <h2 class="result-title">驗證失敗</h2>
        <p class="result-text">{{ errorMsg }}</p>
        <button class="result-btn" @click="viewState = 'camera'">重試</button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.scan-view { min-height:100dvh; background:#111827; color:#fff; display:flex; flex-direction:column; }

/* ── TopBar ──────────────────────────────────────────────── */
.scan-topbar { display:flex; align-items:center; gap:10px; height:56px; padding:0 16px; flex-shrink:0; }
.scan-topbar__back { background:none; border:none; padding:4px; cursor:pointer; display:flex; }
.scan-topbar__title { font-size:17px; font-weight:600; }

/* ── Camera ──────────────────────────────────────────────── */
.camera-area { flex:1; position:relative; overflow:hidden; }
.camera-video { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
.camera-canvas { display:none; }
.camera-overlay { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; z-index:1; background:radial-gradient(circle 140px, transparent 50%, rgba(0,0,0,0.5) 100%); }
.camera-frame { width:220px; height:220px; position:relative; }
.camera-corner { position:absolute; width:24px; height:24px; border-color:#F0294E; border-style:solid; }
.camera-corner--tl { top:0; left:0; border-width:3px 0 0 3px; border-radius:4px 0 0 0; }
.camera-corner--tr { top:0; right:0; border-width:3px 3px 0 0; border-radius:0 4px 0 0; }
.camera-corner--bl { bottom:0; left:0; border-width:0 0 3px 3px; border-radius:0 0 0 4px; }
.camera-corner--br { bottom:0; right:0; border-width:0 3px 3px 0; border-radius:0 0 4px 0; }
.camera-hint { margin-top:20px; font-size:14px; color:rgba(255,255,255,0.85); text-align:center; }
.camera-manual-btn { margin-top:24px; background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); color:#fff; height:36px; padding:0 20px; border-radius:9999px; font-size:13px; font-weight:500; cursor:pointer; }

/* ── Manual ──────────────────────────────────────────────── */
.manual-area { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:32px 20px; gap:12px; }
.manual-icon { margin-bottom:8px; }
.manual-title { font-size:18px; font-weight:700; }
.manual-hint { font-size:13px; color:#9CA3AF; text-align:center; }
.manual-input { width:100%; max-width:300px; height:48px; border:1.5px solid #374151; border-radius:10px; padding:0 16px; font-size:15px; color:#fff; background:#1F2937; outline:none; text-align:center; box-sizing:border-box; }
.manual-input:focus { border-color:#F0294E; }
.manual-btn { width:100%; max-width:300px; height:44px; border-radius:10px; border:none; background:#F0294E; color:#fff; font-size:15px; font-weight:600; cursor:pointer; }
.manual-btn:disabled { opacity:0.4; }
.manual-btn--mock { background:#374151; }
.manual-btn--camera { background:transparent; border:1.5px solid #F0294E; color:#F0294E; display:flex; align-items:center; justify-content:center; gap:6px; }
.manual-divider { width:100%; max-width:300px; height:1px; background:#374151; margin:8px 0; }

/* ── Denied ──────────────────────────────────────────────── */
.denied-area { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:32px 20px; gap:12px; text-align:center; }
.denied-icon { margin-bottom:8px; }
.denied-title { font-size:18px; font-weight:700; }
.denied-text { font-size:13px; color:#9CA3AF; max-width:280px; line-height:1.5; }
.denied-btn { width:100%; max-width:260px; height:44px; border-radius:10px; border:none; background:#F0294E; color:#fff; font-size:15px; font-weight:600; cursor:pointer; }
.denied-btn--manual { background:#374151; }

/* ── Result ──────────────────────────────────────────────── */
.result-area { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; padding:32px 20px; text-align:center; }
.result-icon { font-size:48px; margin-bottom:8px; }
.result-title { font-size:20px; font-weight:700; }
.result-text { font-size:14px; color:#9CA3AF; }
/* ── GPS Prompt ─────────────────────────────────────────── */
.gps-prompt-area { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:32px 20px; gap:16px; text-align:center; }
.gps-prompt-icon { font-size:48px; margin-bottom:4px; }
.gps-prompt-title { font-size:20px; font-weight:700; }
.gps-prompt-card { background:#1F2937; border-radius:12px; padding:16px; width:100%; max-width:300px; }
.gps-prompt-row { display:flex; align-items:center; gap:10px; padding:8px 0; font-size:14px; color:#D1D5DB; }
.gps-prompt-row + .gps-prompt-row { border-top:1px solid #374151; }
.gps-prompt-badge { display:inline-block; padding:2px 10px; border-radius:9999px; font-size:13px; font-weight:700; flex-shrink:0; }
.gps-prompt-badge--high { background:#065F46; color:#A7F3D0; }
.gps-prompt-badge--low { background:#78350F; color:#FDE68A; }
.gps-prompt-note { font-size:12px; color:#6B7280; max-width:280px; line-height:1.5; }
.gps-prompt-btn { width:100%; max-width:300px; height:48px; border-radius:10px; border:none; font-size:15px; font-weight:600; cursor:pointer; }
.gps-prompt-btn--allow { background:#F0294E; color:#fff; }
.gps-prompt-btn--skip { background:transparent; color:#9CA3AF; border:1.5px solid #374151; }

.result-gps { font-size:13px; margin-top:4px; }
.result-gps--ok { color:#10B981; }
.result-gps--no { color:#F59E0B; }
.result-btn { width:100%; max-width:260px; height:44px; border-radius:10px; border:none; background:#F0294E; color:#fff; font-size:15px; font-weight:600; cursor:pointer; }

.spinner { width:32px; height:32px; border-radius:50%; border:3px solid #374151; border-top-color:#F0294E; animation:spin 0.7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>
