<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { verifyEmail, resendVerification } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

// ── 狀態 ─────────────────────────────────────
type ViewState = 'waiting' | 'success' | 'error'
const state = ref<ViewState>('waiting')
const errorMsg = ref('')
const countdown = ref(0)
const isResending = ref(false)

// 從路由 query 取得 email（RegisterView 完成後帶過來）
const email = ref((route.query.email as string) || authStore.user?.email || '')

// ── 倒數計時 ──────────────────────────────────
let countdownTimer: ReturnType<typeof setInterval> | null = null

function startCountdown(secs = 60) {
  countdown.value = secs
  if (countdownTimer) clearInterval(countdownTimer)
  countdownTimer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0 && countdownTimer) {
      clearInterval(countdownTimer)
      countdownTimer = null
    }
  }, 1000)
}

onMounted(() => {
  startCountdown()
  // 若 URL 帶有 token（Email 連結點擊跳回），自動驗證
  const token = route.query.token as string
  if (token && email.value) {
    autoVerifyByToken(token)
  }
})

onUnmounted(() => {
  if (countdownTimer) clearInterval(countdownTimer)
})

// ── 自動 Token 驗證（Email 連結點擊） ────────
async function autoVerifyByToken(token: string) {
  try {
    const res = await verifyEmail({ verification_code: token, email: email.value })
    if (res?.data?.token || res?.data?.data?.token) {
      authStore.setToken(res.data?.token ?? res.data?.data?.token ?? '')
      authStore.setUser(res.data?.user ?? res.data?.data?.user)
    }
    state.value = 'success'
    // 3 秒後自動跳轉
    setTimeout(() => router.push('/app/explore'), 3000)
  } catch {
    state.value = 'error'
    errorMsg.value = '驗證連結已失效，請重新發送驗證信'
  }
}

// ── 重新發送 ──────────────────────────────────
async function resend() {
  if (countdown.value > 0 || isResending.value || !email.value) return
  isResending.value = true
  errorMsg.value = ''
  try {
    await resendVerification(email.value)
    startCountdown()
    state.value = 'waiting'
  } catch {
    errorMsg.value = '發送失敗，請稍後再試'
  } finally {
    isResending.value = false
  }
}

// ── 手動跳轉 ──────────────────────────────────
function goExplore() {
  router.push('/app/explore')
}

function goLogin() {
  router.push('/login')
}
</script>

<template>
  <div class="verify-root">

    <!-- 背景裝飾 -->
    <div class="bg-glow bg-glow-1" />
    <div class="bg-glow bg-glow-2" />

    <!-- Topbar -->
    <header class="verify-topbar">
      <span class="verify-logo">MiMeet</span>
    </header>

    <!-- 主內容 -->
    <div class="verify-container">

      <!-- ── 狀態 A：等待驗證 ── -->
      <Transition name="fade" mode="out-in">
        <div v-if="state === 'waiting'" key="waiting" class="verify-card">

          <!-- 信封動畫 -->
          <div class="icon-wrap envelope-float">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
              <rect x="2" y="4" width="20" height="16" rx="2"/>
              <path d="M2 7l10 7 10-7"/>
            </svg>
          </div>

          <h1 class="verify-title">請驗證你的 Email</h1>

          <p class="verify-desc">
            我們已將驗證信發送至<br>
            <strong class="email-highlight">{{ email || '你的信箱' }}</strong>
          </p>

          <p class="verify-hint">
            點擊信件中的驗證連結即可完成驗證，<br>連結有效期為 60 分鐘。
          </p>

          <!-- 重新發送 -->
          <div class="resend-block">
            <span v-if="countdown > 0" class="resend-countdown">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 6v6l4 2"/>
              </svg>
              {{ countdown }} 秒後可重新發送
            </span>
            <button
              v-else
              class="resend-btn"
              :disabled="isResending"
              @click="resend"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
              </svg>
              {{ isResending ? '發送中…' : '重新發送驗證信' }}
            </button>
          </div>

          <Transition name="err">
            <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>
          </Transition>

          <!-- 修改 Email 連結 -->
          <button class="change-email-btn" @click="goLogin">
            使用不同帳號登入
          </button>

          <p class="spam-hint">收不到信？請檢查垃圾郵件資料夾</p>

        </div>

        <!-- ── 狀態 B：驗證成功 ── -->
        <div v-else-if="state === 'success'" key="success" class="verify-card success-card">

          <!-- 打勾圓圈動畫 -->
          <div class="success-icon-wrap">
            <svg class="success-circle" width="80" height="80" viewBox="0 0 80 80">
              <circle
                class="circle-bg"
                cx="40" cy="40" r="36"
                fill="none" stroke="#ECFDF5" stroke-width="6"
              />
              <circle
                class="circle-anim"
                cx="40" cy="40" r="36"
                fill="none" stroke="#10B981" stroke-width="6"
                stroke-linecap="round"
                stroke-dasharray="226"
                stroke-dashoffset="226"
              />
              <path
                class="check-anim"
                d="M26 40l10 10 18-18"
                fill="none" stroke="#10B981" stroke-width="5"
                stroke-linecap="round" stroke-linejoin="round"
                stroke-dasharray="40"
                stroke-dashoffset="40"
              />
            </svg>
          </div>

          <h1 class="verify-title success-title">Email 已驗證！</h1>
          <p class="verify-desc success-desc">你的帳號已成功驗證，即將跳轉至探索頁面…</p>

          <div class="auto-redirect-hint">
            <div class="redirect-bar">
              <div class="redirect-fill" />
            </div>
            <span class="redirect-text">3 秒後自動跳轉</span>
          </div>

          <button class="btn-explore" @click="goExplore">
            立即開始使用
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </button>

        </div>

        <!-- ── 狀態 C：驗證失敗 ── -->
        <div v-else key="error" class="verify-card error-card">

          <div class="error-icon-wrap">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="12" cy="12" r="10"/>
              <path d="M15 9l-6 6M9 9l6 6"/>
            </svg>
          </div>

          <h1 class="verify-title">驗證連結已失效</h1>
          <p class="verify-desc">{{ errorMsg }}</p>

          <button class="resend-btn resend-btn-large" @click="resend" :disabled="isResending || countdown > 0">
            {{ countdown > 0 ? `${countdown} 秒後可重新發送` : isResending ? '發送中…' : '重新發送驗證信' }}
          </button>

          <button class="change-email-btn" @click="goLogin">返回登入頁</button>

        </div>
      </Transition>

    </div>

  </div>
</template>

<style scoped>
/* ── Variables ──────────────────────────────── */
.verify-root {
  --p: #F0294E; --pd: #D01A3C; --pl: #FFF5F7; --p50: #FFE4EA;
  --t1: #111827; --t2: #6B7280; --t3: #9CA3AF;
  --surf: #F9F9FB; --bdr: #E5E7EB; --err: #EF4444;
  --green: #10B981;
}

/* ── Root ───────────────────────────────────── */
.verify-root {
  min-height: 100svh;
  background: var(--surf);
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 20px 48px;
  font-family: 'Noto Sans TC', -apple-system, sans-serif;
  position: relative;
  overflow: hidden;
}
.bg-glow { position: fixed; border-radius: 50%; pointer-events: none; z-index: 0; }
.bg-glow-1 { top: -80px; right: -80px; width: 280px; height: 280px; background: #FFF0F3; }
.bg-glow-2 { bottom: -60px; left: -60px; width: 220px; height: 220px; background: #F0FDF4; }

/* ── Topbar ─────────────────────────────────── */
.verify-topbar {
  width: 100%; max-width: 440px;
  padding: 20px 0 12px;
  display: flex; justify-content: center;
  position: relative; z-index: 1;
}
.verify-logo {
  font-family: 'Noto Serif TC', serif;
  font-size: 22px; font-weight: 700;
  color: var(--p); letter-spacing: -0.5px;
}

/* ── Container ──────────────────────────────── */
.verify-container {
  width: 100%; max-width: 440px;
  flex: 1; display: flex; align-items: center;
  position: relative; z-index: 1;
}

/* ── Card ───────────────────────────────────── */
.verify-card {
  width: 100%;
  background: #fff;
  border: 1px solid var(--bdr);
  border-radius: 20px;
  padding: 36px 28px 28px;
  display: flex; flex-direction: column;
  align-items: center; gap: 16px;
  text-align: center;
}

/* ── Envelope Icon ──────────────────────────── */
.icon-wrap {
  width: 88px; height: 88px; border-radius: 28px;
  background: var(--pl); border: 1px solid var(--p50);
  display: flex; align-items: center; justify-content: center;
  color: var(--p);
}
.envelope-float {
  animation: float 3s ease-in-out infinite;
}
@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

/* ── Typography ─────────────────────────────── */
.verify-title {
  font-family: 'Noto Serif TC', serif;
  font-size: 22px; font-weight: 700;
  color: var(--t1); letter-spacing: -0.5px; margin: 0;
}
.verify-desc {
  font-size: 14px; color: var(--t2);
  line-height: 1.65; margin: -4px 0 0;
}
.email-highlight {
  color: var(--t1); font-weight: 600;
  word-break: break-all;
}
.verify-hint {
  font-size: 13px; color: var(--t3);
  line-height: 1.6; margin: -4px 0 0;
}

/* ── Resend ─────────────────────────────────── */
.resend-block {
  display: flex; justify-content: center;
  margin: 4px 0;
}
.resend-countdown {
  display: flex; align-items: center; gap: 6px;
  font-size: 13px; color: var(--t3);
}
.resend-btn {
  display: inline-flex; align-items: center; gap: 7px;
  background: var(--pl); border: 1px solid var(--p50);
  color: var(--p); padding: 10px 20px;
  border-radius: 24px; font-size: 13px; font-weight: 600;
  cursor: pointer; font-family: inherit;
  transition: all 0.2s;
}
.resend-btn:hover:not(:disabled) {
  background: var(--p50);
}
.resend-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.resend-btn-large {
  width: 100%; justify-content: center;
  padding: 13px 20px; font-size: 14px; border-radius: 14px;
}

/* ── Error msg ──────────────────────────────── */
.error-msg { font-size: 12px; color: var(--err); margin: -4px 0 0; }
.err-enter-active, .err-leave-active { transition: all 0.2s; }
.err-enter-from, .err-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Change email ───────────────────────────── */
.change-email-btn {
  background: none; border: none;
  color: var(--t3); font-size: 13px;
  cursor: pointer; font-family: inherit; padding: 0;
  text-decoration: underline; text-underline-offset: 2px;
  transition: color 0.2s;
}
.change-email-btn:hover { color: var(--t2); }
.spam-hint { font-size: 12px; color: var(--t3); margin: -4px 0 0; }

/* ── Success Card ───────────────────────────── */
.success-card { border-color: #A7F3D0; }
.success-icon-wrap { margin: 4px 0; }

/* SVG circle animation */
.circle-anim {
  animation: draw-circle 0.6s ease forwards 0.1s;
}
.check-anim {
  animation: draw-check 0.4s ease forwards 0.65s;
}
@keyframes draw-circle {
  to { stroke-dashoffset: 0; }
}
@keyframes draw-check {
  to { stroke-dashoffset: 0; }
}

.success-title { color: #065F46; }
.success-desc { color: #6EE7B7; color: #047857; }

/* Auto redirect bar */
.auto-redirect-hint {
  display: flex; flex-direction: column;
  align-items: center; gap: 6px; width: 100%;
}
.redirect-bar {
  width: 100%; height: 3px;
  background: #D1FAE5; border-radius: 2px; overflow: hidden;
}
.redirect-fill {
  height: 100%; background: var(--green);
  border-radius: 2px;
  animation: fill-bar 3s linear forwards;
}
@keyframes fill-bar {
  from { width: 0%; }
  to { width: 100%; }
}
.redirect-text { font-size: 12px; color: #6EE7B7; color: #059669; }

/* Explore button */
.btn-explore {
  width: 100%; height: 50px;
  background: var(--green); color: #fff; border: none;
  border-radius: 14px; font-size: 15px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: all 0.25s;
  box-shadow: 0 4px 14px rgba(16,185,129,0.28);
}
.btn-explore:hover { background: #059669; transform: translateY(-1px); }

/* ── Error Card ─────────────────────────────── */
.error-card { border-color: #FECACA; }
.error-icon-wrap {
  width: 88px; height: 88px; border-radius: 28px;
  background: #FEF2F2; border: 1px solid #FECACA;
  display: flex; align-items: center; justify-content: center;
  color: var(--err);
}

/* ── Fade Transition ────────────────────────── */
.fade-enter-active, .fade-leave-active { transition: all 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(12px); }

/* ── RWD ────────────────────────────────────── */
@media (max-width: 480px) {
  .verify-card { padding: 28px 20px 24px; border-radius: 16px; }
}
</style>
