<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { forgotPassword } from '@/api/auth'
import { validateEmail } from '@/utils/validators'

const router = useRouter()

type PageState = 'form' | 'sent'
const state = ref<PageState>('form')
const isLoading = ref(false)
const email = ref('')
const emailError = ref('')
const sentEmail = ref('')

function validate(): boolean {
  emailError.value = validateEmail(email.value)
  return !emailError.value
}

async function handleSubmit() {
  if (!validate()) return
  isLoading.value = true
  try {
    await forgotPassword(email.value.trim())
    sentEmail.value = email.value.trim()
    state.value = 'sent'
  } catch {
    // API 規格：無論 Email 是否存在均回傳相同訊息（防枚舉攻擊）
    // 所以不論成功失敗都顯示已送出畫面
    sentEmail.value = email.value.trim()
    state.value = 'sent'
  } finally {
    isLoading.value = false
  }
}

function goLogin() { router.push('/login') }
function goBack() { router.push('/login') }
function tryAgain() {
  email.value = ''
  emailError.value = ''
  state.value = 'form'
}
</script>

<template>
  <div class="fp-root">
    <div class="bg-glow bg-glow-1" />
    <div class="bg-glow bg-glow-2" />

    <!-- Topbar -->
    <header class="fp-topbar">
      <button class="back-btn" @click="goBack">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
      </button>
      <span class="fp-logo">MiMeet</span>
      <div class="placeholder" />
    </header>

    <div class="fp-container">
      <Transition name="fade" mode="out-in">

        <!-- ── 狀態 A：輸入 Email ── -->
        <div v-if="state === 'form'" key="form" class="fp-card">
          <div class="fp-icon-wrap">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="3" y="11" width="18" height="11" rx="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              <circle cx="12" cy="16" r="1" fill="currentColor"/>
            </svg>
          </div>

          <h1 class="fp-title">忘記密碼？</h1>
          <p class="fp-desc">輸入你的註冊 Email，我們將寄送密碼重設連結</p>

          <div class="field-group">
            <label class="field-label">Email</label>
            <div class="input-wrap" :class="{ error: emailError }">
              <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="M2 7l10 7 10-7"/>
              </svg>
              <input
                v-model="email"
                type="email"
                placeholder="your@email.com"
                autocomplete="email"
                class="field-input"
                @input="emailError = ''"
                @keyup.enter="handleSubmit"
              />
            </div>
            <Transition name="err">
              <p v-if="emailError" class="field-error">{{ emailError }}</p>
            </Transition>
          </div>

          <button
            class="btn-submit"
            :class="{ loading: isLoading }"
            :disabled="isLoading"
            @click="handleSubmit"
          >
            <span v-if="!isLoading">發送重設連結</span>
            <span v-else class="spinner-wrap">
              <svg class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
              發送中…
            </span>
          </button>

          <button class="back-link" @click="goLogin">返回登入</button>
        </div>

        <!-- ── 狀態 B：已送出 ── -->
        <div v-else key="sent" class="fp-card sent-card">
          <div class="sent-icon-wrap">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
              <path d="M22 2L11 13"/>
              <path d="M22 2L15 22 11 13 2 9l20-7z"/>
            </svg>
          </div>

          <h1 class="fp-title">連結已寄出！</h1>
          <p class="fp-desc">
            若 <strong class="email-hl">{{ sentEmail }}</strong> 已在 MiMeet 註冊，<br>
            密碼重設連結已發送至該信箱，有效期 <strong>60 分鐘</strong>。
          </p>

          <div class="tips-block">
            <div class="tip-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 12l2 2 4-4"/>
                <circle cx="12" cy="12" r="10"/>
              </svg>
              收不到信？請檢查垃圾郵件資料夾
            </div>
            <div class="tip-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 6v6l4 2"/>
              </svg>
              連結 60 分鐘後失效，請盡快點擊
            </div>
          </div>

          <button class="btn-submit btn-login" @click="goLogin">
            返回登入頁
          </button>

          <button class="back-link" @click="tryAgain">
            重新輸入 Email
          </button>
        </div>

      </Transition>
    </div>
  </div>
</template>

<style>
:root {
  --p: #F0294E; --pd: #D01A3C; --pl: #FFF5F7; --p50: #FFE4EA;
  --t1: #111827; --t2: #6B7280; --t3: #9CA3AF;
  --surf: #F9F9FB; --bdr: #E5E7EB; --err: #EF4444;
}
.fp-root {
  min-height: 100svh; background: var(--surf);
  display: flex; flex-direction: column; align-items: center;
  padding: 0 20px 48px;
  font-family: 'Noto Sans TC', -apple-system, sans-serif;
  position: relative; overflow: hidden;
}
.bg-glow { position: fixed; border-radius: 50%; pointer-events: none; z-index: 0; }
.bg-glow-1 { top: -80px; right: -80px; width: 260px; height: 260px; background: #FFF0F3; }
.bg-glow-2 { bottom: -60px; left: -60px; width: 200px; height: 200px; background: #FFF8F5; }

/* Topbar */
.fp-topbar {
  width: 100%; max-width: 440px;
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 0 12px; position: relative; z-index: 1;
}
.back-btn {
  width: 40px; height: 40px; border-radius: 12px;
  background: #fff; border: 1px solid var(--bdr);
  display: flex; align-items: center; justify-content: center;
  color: var(--t2); cursor: pointer; transition: all 0.2s;
}
.back-btn:hover { background: var(--surf); color: var(--t1); }
.fp-logo { font-family: 'Noto Serif TC', serif; font-size: 20px; font-weight: 700; color: var(--p); }
.placeholder { width: 40px; }

/* Container */
.fp-container {
  width: 100%; max-width: 440px;
  flex: 1; display: flex; align-items: center;
  position: relative; z-index: 1;
}

/* Card */
.fp-card {
  width: 100%; background: #fff;
  border: 1px solid var(--bdr); border-radius: 20px;
  padding: 32px 24px 28px;
  display: flex; flex-direction: column; align-items: center;
  gap: 16px; text-align: center;
}

/* Icon */
.fp-icon-wrap {
  width: 80px; height: 80px; border-radius: 24px;
  background: var(--pl); border: 1px solid var(--p50);
  display: flex; align-items: center; justify-content: center;
  color: var(--p);
}
.sent-icon-wrap {
  width: 80px; height: 80px; border-radius: 24px;
  background: #EFF6FF; border: 1px solid #BFDBFE;
  display: flex; align-items: center; justify-content: center;
  color: #3B82F6;
  animation: pop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
}
@keyframes pop {
  from { transform: scale(0.7); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.fp-title {
  font-family: 'Noto Serif TC', serif;
  font-size: 22px; font-weight: 700;
  color: var(--t1); letter-spacing: -0.5px; margin: 0;
}
.fp-desc {
  font-size: 14px; color: var(--t2);
  line-height: 1.65; margin: -4px 0 0;
}
.email-hl { color: var(--t1); font-weight: 600; word-break: break-all; }

/* Field */
.field-group { width: 100%; display: flex; flex-direction: column; gap: 6px; text-align: left; }
.field-label { font-size: 13px; font-weight: 600; color: var(--t1); }
.input-wrap {
  position: relative; display: flex; align-items: center;
  border: 1.5px solid var(--bdr); border-radius: 12px;
  background: var(--surf); transition: border-color 0.2s, box-shadow 0.2s;
}
.input-wrap:focus-within {
  border-color: var(--p); box-shadow: 0 0 0 3px rgba(240,41,78,0.08); background: #fff;
}
.input-wrap.error { border-color: var(--err); box-shadow: 0 0 0 3px rgba(239,68,68,0.08); }
.input-icon { position: absolute; left: 14px; color: var(--t3); pointer-events: none; }
.field-input {
  width: 100%; height: 48px; padding: 0 16px 0 44px;
  border: none; background: transparent; font-size: 15px;
  color: var(--t1); font-family: inherit; outline: none;
}
.field-input::placeholder { color: var(--t3); }
.field-error { font-size: 12px; color: var(--err); margin: 0; }
.err-enter-active, .err-leave-active { transition: all 0.2s; }
.err-enter-from, .err-leave-to { opacity: 0; transform: translateY(-4px); }

/* Buttons */
.btn-submit {
  width: 100%; height: 50px;
  background: var(--p); color: #fff; border: none;
  border-radius: 14px; font-size: 15px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 4px 14px rgba(240,41,78,0.28);
  transition: all 0.25s; margin-top: 4px;
}
.btn-submit:hover:not(:disabled) { background: var(--pd); transform: translateY(-1px); }
.btn-submit:disabled { opacity: 0.75; cursor: not-allowed; }
.btn-login { background: var(--t1); box-shadow: 0 4px 14px rgba(0,0,0,0.15); }
.btn-login:hover:not(:disabled) { background: #1F2937; }
.spinner-wrap { display: flex; align-items: center; gap: 8px; }
.spinner { animation: spin 0.9s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.back-link {
  background: none; border: none; color: var(--t3);
  font-size: 13px; cursor: pointer; font-family: inherit; padding: 0;
  text-decoration: underline; text-underline-offset: 2px; transition: color 0.2s;
}
.back-link:hover { color: var(--t2); }

/* Tips */
.tips-block {
  width: 100%; background: var(--surf); border-radius: 12px;
  padding: 14px 16px; display: flex; flex-direction: column; gap: 10px;
}
.tip-item {
  display: flex; align-items: center; gap: 8px;
  font-size: 13px; color: var(--t2); text-align: left;
}
.tip-item svg { color: var(--t3); flex-shrink: 0; }

/* Sent card accent */
.sent-card { border-color: #BFDBFE; }

/* Transition */
.fade-enter-active, .fade-leave-active { transition: all 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(10px); }

@media (max-width: 480px) {
  .fp-card { padding: 28px 18px 24px; border-radius: 16px; }
}
</style>
