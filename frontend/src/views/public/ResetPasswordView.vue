<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { resetPassword } from '@/api/auth'
import { validatePassword, validatePasswordConfirm, getPasswordStrength } from '@/utils/validators'
import MiMeetLogo from '@/components/common/MiMeetLogo.vue'

const router = useRouter()
const route = useRoute()

type PageState = 'form' | 'success' | 'invalid'
const state = ref<PageState>('form')
const isLoading = ref(false)

// 從 URL query 取得 token + email
const token = ref('')
const email = ref('')

onMounted(() => {
  token.value = route.query.token as string || ''
  email.value = route.query.email as string || ''
  if (!token.value || !email.value) {
    state.value = 'invalid'
  }
})

// 表單
const form = reactive({ password: '', passwordConfirm: '' })
const errors = reactive({ password: '', passwordConfirm: '' })
const showPass = ref(false)
const showPassConfirm = ref(false)

// 密碼強度
const pwStrength = computed(() => getPasswordStrength(form.password))
const strength = computed(() => pwStrength.value.score)
const strengthLabel = computed(() => pwStrength.value.label)
const strengthColor = computed(() => pwStrength.value.color)

function validate(): boolean {
  let ok = true
  errors.password = validatePassword(form.password)
  if (errors.password) ok = false
  errors.passwordConfirm = validatePasswordConfirm(form.passwordConfirm, form.password)
  if (errors.passwordConfirm) ok = false
  return ok
}

async function handleSubmit() {
  if (!validate()) return
  isLoading.value = true
  try {
    await resetPassword({
      token: token.value,
      email: email.value,
      password: form.password,
      password_confirmation: form.passwordConfirm,
    })
    state.value = 'success'
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { error?: { code?: string } } } }
    const code = e?.response?.data?.error?.code
    if (code === '1010' || e?.response?.status === 422) {
      state.value = 'invalid'
    } else {
      errors.password = '重設失敗，請稍後再試'
    }
  } finally {
    isLoading.value = false
  }
}

function goLogin() { router.push('/login') }
function goForgot() { router.push('/forgot-password') }
</script>

<template>
  <div class="rp-root">
    <div class="bg-glow bg-glow-1" />
    <div class="bg-glow bg-glow-2" />

    <!-- Topbar -->
    <header class="rp-topbar">
      <div class="placeholder" />
      <MiMeetLogo size="md" clickable />
      <div class="placeholder" />
    </header>

    <div class="rp-container">
      <Transition name="fade" mode="out-in">

        <!-- ── 狀態 A：重設表單 ── -->
        <div v-if="state === 'form'" key="form" class="rp-card">
          <div class="rp-icon-wrap">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="3" y="11" width="18" height="11" rx="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              <path d="M12 16v2" stroke-linecap="round"/>
            </svg>
          </div>

          <h1 class="rp-title">重設你的密碼</h1>
          <p class="rp-desc">請設定新密碼，至少 8 個字元</p>

          <!-- 新密碼 -->
          <div class="field-group">
            <label class="field-label">新密碼</label>
            <div class="input-wrap" :class="{ error: errors.password }">
              <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input
                v-model="form.password"
                :type="showPass ? 'text' : 'password'"
                placeholder="請輸入新密碼"
                autocomplete="new-password"
                class="field-input"
                @input="errors.password = ''"
              />
              <button class="eye-btn" type="button" @click="showPass = !showPass">
                <svg v-if="showPass" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
              </button>
            </div>

            <!-- 密碼強度指示條 -->
            <div v-if="form.password" class="strength-wrap">
              <div class="strength-bar">
                <div
                  class="strength-fill"
                  :style="{
                    width: `${(strength / 5) * 100}%`,
                    background: strengthColor,
                  }"
                />
              </div>
              <span class="strength-label" :style="{ color: strengthColor }">
                {{ strengthLabel }}
              </span>
            </div>

            <Transition name="err">
              <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
            </Transition>
          </div>

          <!-- 確認密碼 -->
          <div class="field-group">
            <label class="field-label">確認新密碼</label>
            <div class="input-wrap" :class="{ error: errors.passwordConfirm }">
              <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input
                v-model="form.passwordConfirm"
                :type="showPassConfirm ? 'text' : 'password'"
                placeholder="再輸入一次新密碼"
                autocomplete="new-password"
                class="field-input"
                @input="errors.passwordConfirm = ''"
                @keyup.enter="handleSubmit"
              />
              <button class="eye-btn" type="button" @click="showPassConfirm = !showPassConfirm">
                <svg v-if="showPassConfirm" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
              </button>
            </div>
            <Transition name="err">
              <p v-if="errors.passwordConfirm" class="field-error">{{ errors.passwordConfirm }}</p>
            </Transition>
          </div>

          <button
            class="btn-main"
            :class="{ loading: isLoading }"
            :disabled="isLoading"
            @click="handleSubmit"
          >
            <span v-if="!isLoading">確認重設密碼</span>
            <span v-else class="spinner-wrap">
              <svg class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
              處理中…
            </span>
          </button>
        </div>

        <!-- ── 狀態 B：重設成功 ── -->
        <div v-else-if="state === 'success'" key="success" class="rp-card success-card">
          <div class="success-icon">
            <svg class="check-svg" width="80" height="80" viewBox="0 0 80 80">
              <circle class="circle-bg" cx="40" cy="40" r="36" fill="none" stroke="#ECFDF5" stroke-width="6"/>
              <circle class="circle-anim" cx="40" cy="40" r="36" fill="none" stroke="#10B981" stroke-width="6"
                stroke-linecap="round" stroke-dasharray="226" stroke-dashoffset="226"/>
              <path class="check-anim" d="M26 40l10 10 18-18"
                fill="none" stroke="#10B981" stroke-width="5"
                stroke-linecap="round" stroke-linejoin="round"
                stroke-dasharray="40" stroke-dashoffset="40"/>
            </svg>
          </div>

          <h1 class="rp-title success-title">密碼重設成功！</h1>
          <p class="rp-desc">你的密碼已更新，請使用新密碼登入。</p>

          <button class="btn-main btn-green" @click="goLogin">
            前往登入
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </button>
        </div>

        <!-- ── 狀態 C：連結失效 ── -->
        <div v-else key="invalid" class="rp-card invalid-card">
          <div class="invalid-icon">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="12" cy="12" r="10"/>
              <path d="M12 8v4M12 16h.01" stroke-linecap="round"/>
            </svg>
          </div>

          <h1 class="rp-title">連結已失效</h1>
          <p class="rp-desc">此重設連結已失效或已被使用，請重新申請。</p>

          <button class="btn-main" @click="goForgot">
            重新申請重設連結
          </button>

          <button class="back-link" @click="goLogin">返回登入</button>
        </div>

      </Transition>
    </div>
  </div>
</template>

<style scoped>
.rp-root {
  --p: #F0294E; --pd: #D01A3C; --pl: #FFF5F7; --p50: #FFE4EA;
  --t1: #111827; --t2: #6B7280; --t3: #9CA3AF;
  --surf: #F9F9FB; --bdr: #E5E7EB; --err: #EF4444;
}
.rp-root {
  min-height: 100svh; background: var(--surf);
  display: flex; flex-direction: column; align-items: center;
  padding: 0 20px 48px;
  font-family: 'Noto Sans TC', -apple-system, sans-serif;
  position: relative; overflow: hidden;
}
.bg-glow { position: fixed; border-radius: 50%; pointer-events: none; z-index: 0; }
.bg-glow-1 { top: -80px; right: -80px; width: 260px; height: 260px; background: #FFF0F3; }
.bg-glow-2 { bottom: -60px; left: -60px; width: 200px; height: 200px; background: #F0FDF4; }

.rp-topbar {
  width: 100%; max-width: 440px;
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 0 12px; position: relative; z-index: 1;
}
.rp-logo { font-family: 'Noto Serif TC', serif; font-size: 20px; font-weight: 700; color: var(--p); }
.placeholder { width: 40px; }

.rp-container {
  width: 100%; max-width: 440px;
  flex: 1; display: flex; align-items: center;
  position: relative; z-index: 1;
}
.rp-card {
  width: 100%; background: #fff;
  border: 1px solid var(--bdr); border-radius: 20px;
  padding: 32px 24px 28px;
  display: flex; flex-direction: column; align-items: center;
  gap: 16px; text-align: center;
}
.rp-icon-wrap {
  width: 80px; height: 80px; border-radius: 24px;
  background: var(--pl); border: 1px solid var(--p50);
  display: flex; align-items: center; justify-content: center; color: var(--p);
}
.rp-title {
  font-family: 'Noto Serif TC', serif;
  font-size: 22px; font-weight: 700; color: var(--t1); margin: 0;
}
.rp-desc { font-size: 14px; color: var(--t2); line-height: 1.65; margin: -4px 0 0; }

/* Fields */
.field-group { width: 100%; display: flex; flex-direction: column; gap: 6px; text-align: left; }
.field-label { font-size: 13px; font-weight: 600; color: var(--t1); }
.input-wrap {
  position: relative; display: flex; align-items: center;
  border: 1.5px solid var(--bdr); border-radius: 12px; background: var(--surf);
  transition: border-color 0.2s, box-shadow 0.2s;
}
.input-wrap:focus-within {
  border-color: var(--p); box-shadow: 0 0 0 3px rgba(240,41,78,0.08); background: #fff;
}
.input-wrap.error { border-color: var(--err); box-shadow: 0 0 0 3px rgba(239,68,68,0.08); }
.input-icon { position: absolute; left: 14px; color: var(--t3); pointer-events: none; }
.field-input {
  width: 100%; height: 48px; padding: 0 44px;
  border: none; background: transparent; font-size: 15px;
  color: var(--t1); font-family: inherit; outline: none;
}
.field-input::placeholder { color: var(--t3); }
.eye-btn {
  position: absolute; right: 12px; background: none; border: none;
  color: var(--t3); cursor: pointer; padding: 4px; display: flex;
}
.field-error { font-size: 12px; color: var(--err); margin: 0; }
.err-enter-active, .err-leave-active { transition: all 0.2s; }
.err-enter-from, .err-leave-to { opacity: 0; transform: translateY(-4px); }

/* Strength */
.strength-wrap { display: flex; align-items: center; gap: 8px; }
.strength-bar {
  flex: 1; height: 4px; background: var(--bdr);
  border-radius: 2px; overflow: hidden;
}
.strength-fill {
  height: 100%; border-radius: 2px;
  transition: width 0.3s ease, background 0.3s ease;
}
.strength-label { font-size: 12px; font-weight: 600; min-width: 20px; }

/* Buttons */
.btn-main {
  width: 100%; height: 50px;
  background: var(--p); color: #fff; border: none;
  border-radius: 14px; font-size: 15px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 4px 14px rgba(240,41,78,0.28);
  transition: all 0.25s; margin-top: 4px;
}
.btn-main:hover:not(:disabled) { background: var(--pd); transform: translateY(-1px); }
.btn-main:disabled { opacity: 0.75; cursor: not-allowed; }
.btn-green { background: #10B981; box-shadow: 0 4px 14px rgba(16,185,129,0.28); }
.btn-green:hover:not(:disabled) { background: #10B981; }
.spinner-wrap { display: flex; align-items: center; gap: 8px; }
.spinner { animation: spin 0.9s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.back-link {
  background: none; border: none; color: var(--t3);
  font-size: 13px; cursor: pointer; font-family: inherit; padding: 0;
  text-decoration: underline; text-underline-offset: 2px;
}
.back-link:hover { color: var(--t2); }

/* Success */
.success-card { border-color: #A7F3D0; }
.success-title { color: #065F46; }
.circle-anim { animation: draw-circle 0.6s ease forwards 0.1s; }
.check-anim { animation: draw-check 0.4s ease forwards 0.65s; }
@keyframes draw-circle { to { stroke-dashoffset: 0; } }
@keyframes draw-check { to { stroke-dashoffset: 0; } }

/* Invalid */
.invalid-card { border-color: #FECACA; }
.invalid-icon {
  width: 80px; height: 80px; border-radius: 24px;
  background: #FEF2F2; border: 1px solid #FECACA;
  display: flex; align-items: center; justify-content: center; color: var(--err);
}

/* Transition */
.fade-enter-active, .fade-leave-active { transition: all 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(10px); }

@media (max-width: 480px) {
  .rp-card { padding: 28px 18px 24px; border-radius: 16px; }
}
</style>
