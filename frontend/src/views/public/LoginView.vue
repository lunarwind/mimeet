<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { login } from '@/api/auth'
import { validateEmail, validatePassword } from '@/utils/validators'

const router = useRouter()
const authStore = useAuthStore()

// ── 表單狀態 ──────────────────────────────────
const form = reactive({
  email: '',
  password: '',
})

const errors = reactive({
  email: '',
  password: '',
})

const showPassword = ref(false)
const isLoading = ref(false)
const toast = reactive({
  show: false,
  message: '',
  type: 'error' as 'error' | 'success',
})

// ── Toast ────────────────────────────────────
let toastTimer: ReturnType<typeof setTimeout> | null = null

function showToast(message: string, type: 'error' | 'success' = 'error') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.message = message
  toast.type = type
  toast.show = true
  toastTimer = setTimeout(() => { toast.show = false }, 3500)
}

// ── 驗證 ─────────────────────────────────────
function validateForm(): boolean {
  let valid = true
  errors.email = validateEmail(form.email)
  if (errors.email) valid = false

  errors.password = form.password ? '' : '此欄位為必填'
  if (errors.password) valid = false

  return valid
}

// ── 登入 ─────────────────────────────────────
async function handleLogin() {
  if (!validateForm()) return
  isLoading.value = true

  try {
    const res = await login({
      email: form.email,
      password: form.password,
      device_info: { type: 'web', name: navigator.userAgent, os: '' },
    })

    authStore.setToken(res.token ?? '')
    authStore.setUser(res.user)

    // 停權帳號跳轉
    if (res.user.status === 'suspended') {
      router.push('/suspended')
      return
    }

    router.push('/app/explore')
  } catch (err: any) {
    const code = err?.response?.data?.error?.code || err?.response?.status

    if (code === 429 || err?.response?.data?.error?.type === 'too_many_attempts') {
      showToast('請稍後再試，您已嘗試過多次')
    } else if (err?.response?.data?.user?.status === 'suspended') {
      router.push('/suspended')
    } else {
      showToast('Email 或密碼不正確')
    }
  } finally {
    isLoading.value = false
  }
}

// ── 跳轉 ─────────────────────────────────────
function goRegister() { router.push('/register') }
function goForgot() { router.push('/forgot-password') }
function goLanding() { router.push('/') }
</script>

<template>
  <div class="login-root">

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast.show" class="toast" :class="toast.type">
        <svg v-if="toast.type === 'error'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="12" cy="12" r="10"/>
          <path d="M15 9l-6 6M9 9l6 6"/>
        </svg>
        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M20 6L9 17l-5-5"/>
        </svg>
        {{ toast.message }}
      </div>
    </Transition>

    <!-- 頂部 back 連結 -->
    <button class="back-btn" @click="goLanding">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
      </svg>
    </button>

    <!-- 主要內容 -->
    <div class="login-container">

      <!-- Logo + 標題 -->
      <div class="login-header">
        <div class="logo" @click="goLanding">MiMeet</div>
        <h1 class="login-title">歡迎回來</h1>
        <p class="login-subtitle">登入你的 MiMeet 帳號</p>
      </div>

      <!-- 表單 -->
      <div class="login-form">

        <!-- Email -->
        <div class="field-group">
          <label class="field-label">Email</label>
          <div class="input-wrap" :class="{ error: errors.email, focused: false }">
            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="2" y="4" width="20" height="16" rx="2"/>
              <path d="M2 7l10 7 10-7"/>
            </svg>
            <input
              v-model="form.email"
              type="email"
              placeholder="請輸入 Email"
              autocomplete="email"
              class="field-input"
              :class="{ 'has-error': errors.email }"
              @input="errors.email = ''"
              @keyup.enter="handleLogin"
            />
          </div>
          <Transition name="err">
            <p v-if="errors.email" class="field-error">{{ errors.email }}</p>
          </Transition>
        </div>

        <!-- 密碼 -->
        <div class="field-group">
          <div class="field-label-row">
            <label class="field-label">密碼</label>
            <button class="forgot-link" @click="goForgot">忘記密碼？</button>
          </div>
          <div class="input-wrap" :class="{ error: errors.password }">
            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="11" width="18" height="11" rx="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="請輸入密碼"
              autocomplete="current-password"
              class="field-input"
              :class="{ 'has-error': errors.password }"
              @input="errors.password = ''"
              @keyup.enter="handleLogin"
            />
            <button class="eye-btn" @click="showPassword = !showPassword" type="button">
              <!-- 眼睛開 -->
              <svg v-if="showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <!-- 眼睛關 -->
              <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/>
              </svg>
            </button>
          </div>
          <Transition name="err">
            <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
          </Transition>
        </div>

        <!-- 登入按鈕 -->
        <button
          class="btn-login"
          :class="{ loading: isLoading }"
          :disabled="isLoading"
          @click="handleLogin"
        >
          <span v-if="!isLoading">登入</span>
          <span v-else class="spinner-wrap">
            <svg class="spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            登入中…
          </span>
        </button>

        <!-- 分隔線 -->
        <div class="divider">
          <span class="divider-line" />
          <span class="divider-text">或</span>
          <span class="divider-line" />
        </div>

        <!-- 前往註冊 -->
        <div class="register-hint">
          還沒有帳號？
          <button class="register-link" @click="goRegister">立即免費加入</button>
        </div>

      </div>
    </div>

    <!-- 底部裝飾 -->
    <div class="login-footer">
      <a href="#" class="footer-link">隱私權政策</a>
      <span class="footer-dot">·</span>
      <a href="#" class="footer-link">使用者條款</a>
    </div>

  </div>
</template>

<style scoped>
/* ── Variables（定義在元件根元素，避免 scoped :root 失效）── */
.login-root {
  --p:     #F0294E;
  --pd:    #D01A3C;
  --pl:    #FFF5F7;
  --p50:   #FFE4EA;
  --t1:    #111827;
  --t2:    #6B7280;
  --t3:    #9CA3AF;
  --surf:  #F9F9FB;
  --bdr:   #E5E7EB;
  --err:   #EF4444;
}

/* ── Root ───────────────────────────────────── */
.login-root {
  min-height: 100svh;
  background: var(--surf);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px 20px 40px;
  font-family: 'Noto Sans TC', -apple-system, BlinkMacSystemFont, sans-serif;
  position: relative;
}

/* Background subtle glow */
.login-root::before {
  content: '';
  position: fixed;
  top: -80px;
  right: -80px;
  width: 320px;
  height: 320px;
  border-radius: 50%;
  background: #FFF0F3;
  z-index: 0;
  pointer-events: none;
}
.login-root::after {
  content: '';
  position: fixed;
  bottom: -60px;
  left: -60px;
  width: 240px;
  height: 240px;
  border-radius: 50%;
  background: #FFF8F5;
  z-index: 0;
  pointer-events: none;
}

/* ── Back button ────────────────────────────── */
.back-btn {
  position: fixed;
  top: 20px;
  left: 20px;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid var(--bdr);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--t2);
  cursor: pointer;
  transition: all 0.2s;
  z-index: 10;
}
.back-btn:hover {
  background: var(--surf);
  color: var(--t1);
}

/* ── Toast ──────────────────────────────────── */
.toast {
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 24px;
  font-size: 13px;
  font-weight: 500;
  z-index: 200;
  white-space: nowrap;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.toast.error {
  background: #FEF2F2;
  color: #991B1B;
  border: 1px solid #FECACA;
}
.toast.success {
  background: #ECFDF5;
  color: #065F46;
  border: 1px solid #A7F3D0;
}
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(-12px); }

/* ── Container ──────────────────────────────── */
.login-container {
  width: 100%;
  max-width: 400px;
  position: relative;
  z-index: 1;
}

/* ── Header ─────────────────────────────────── */
.login-header {
  text-align: center;
  margin-bottom: 32px;
}
.logo {
  font-family: 'Noto Serif TC', serif;
  font-size: 28px;
  font-weight: 700;
  color: var(--p);
  letter-spacing: -0.5px;
  cursor: pointer;
  display: inline-block;
  margin-bottom: 20px;
}
.login-title {
  font-family: 'Noto Serif TC', serif;
  font-size: 26px;
  font-weight: 700;
  color: var(--t1);
  letter-spacing: -0.5px;
  margin: 0 0 8px;
}
.login-subtitle {
  font-size: 14px;
  color: var(--t2);
  margin: 0;
}

/* ── Form Card ──────────────────────────────── */
.login-form {
  background: #fff;
  border: 1px solid var(--bdr);
  border-radius: 20px;
  padding: 28px 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* ── Field Group ────────────────────────────── */
.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.field-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.field-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--t1);
}
.forgot-link {
  background: none;
  border: none;
  font-size: 12px;
  color: var(--p);
  cursor: pointer;
  font-family: inherit;
  padding: 0;
  font-weight: 500;
}
.forgot-link:hover { text-decoration: underline; }

/* ── Input Wrap ─────────────────────────────── */
.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  border: 1.5px solid var(--bdr);
  border-radius: 12px;
  background: var(--surf);
  transition: border-color 0.2s, box-shadow 0.2s;
  overflow: hidden;
}
.input-wrap:focus-within {
  border-color: var(--p);
  box-shadow: 0 0 0 3px rgba(240,41,78,0.08);
  background: #fff;
}
.input-wrap.error {
  border-color: var(--err);
  box-shadow: 0 0 0 3px rgba(239,68,68,0.08);
}
.input-icon {
  position: absolute;
  left: 14px;
  color: var(--t3);
  pointer-events: none;
  flex-shrink: 0;
}
.field-input {
  width: 100%;
  height: 48px;
  padding: 0 44px 0 44px;
  border: none;
  background: transparent;
  font-size: 15px;
  color: var(--t1);
  font-family: inherit;
  outline: none;
}
.field-input::placeholder { color: var(--t3); }
.field-input.has-error { color: var(--err); }

.eye-btn {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: var(--t3);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  transition: color 0.2s;
}
.eye-btn:hover { color: var(--t2); }

/* ── Field Error ────────────────────────────── */
.field-error {
  font-size: 12px;
  color: var(--err);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 4px;
}
.err-enter-active, .err-leave-active { transition: all 0.2s ease; }
.err-enter-from, .err-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Login Button ───────────────────────────── */
.btn-login {
  width: 100%;
  height: 50px;
  background: var(--p);
  color: #fff;
  border: none;
  border-radius: 14px;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.25s;
  box-shadow: 0 4px 14px rgba(240,41,78,0.28);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 4px;
}
.btn-login:hover:not(:disabled) {
  background: var(--pd);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(240,41,78,0.35);
}
.btn-login:active:not(:disabled) {
  transform: translateY(0);
}
.btn-login:disabled {
  opacity: 0.75;
  cursor: not-allowed;
  transform: none;
}

/* spinner */
.spinner-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}
.spinner {
  animation: spin 0.9s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* ── Divider ────────────────────────────────── */
.divider {
  display: flex;
  align-items: center;
  gap: 10px;
}
.divider-line {
  flex: 1;
  height: 1px;
  background: var(--bdr);
}
.divider-text {
  font-size: 12px;
  color: var(--t3);
  flex-shrink: 0;
}

/* ── Register Hint ──────────────────────────── */
.register-hint {
  text-align: center;
  font-size: 14px;
  color: var(--t2);
}
.register-link {
  background: none;
  border: none;
  color: var(--p);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  padding: 0;
  transition: opacity 0.2s;
}
.register-link:hover { opacity: 0.8; text-decoration: underline; }

/* ── Footer ─────────────────────────────────── */
.login-footer {
  margin-top: 32px;
  display: flex;
  gap: 8px;
  align-items: center;
  position: relative;
  z-index: 1;
}
.footer-link {
  font-size: 12px;
  color: var(--t3);
  text-decoration: none;
  transition: color 0.2s;
}
.footer-link:hover { color: var(--t2); }
.footer-dot {
  font-size: 12px;
  color: var(--t3);
}

/* ── RWD ────────────────────────────────────── */
@media (max-width: 480px) {
  .login-form {
    padding: 24px 20px;
    border-radius: 16px;
  }
}
</style>
