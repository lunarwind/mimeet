<script setup lang="ts">
import { ref, reactive, computed, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { register, verifyEmail, resendVerification } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import MiMeetLogo from '@/components/common/MiMeetLogo.vue'
import {
  validateEmail,
  validatePassword,
  validatePasswordConfirm,
  validatePhone,
  validateNickname,
  validateBirthDate,
  validateRequired,
} from '@/utils/validators'

const router = useRouter()
const authStore = useAuthStore()

// ── Step 狀態 ────────────────────────────────
const currentStep = ref(1)
const slideDir = ref<'forward' | 'back'>('forward')

function goStep(n: number) {
  slideDir.value = n > currentStep.value ? 'forward' : 'back'
  currentStep.value = n
}

// ── Step 1 表單 ──────────────────────────────
const step1 = reactive({
  gender: '' as 'male' | 'female' | '',
  nickname: '',
  birthYear: '',
  birthMonth: '',
  birthDay: '',
})
const step1Errors = reactive({ gender: '', nickname: '', birth: '' })

const years = Array.from({ length: 80 }, (_, i) => new Date().getFullYear() - 18 - i)
const months = Array.from({ length: 12 }, (_, i) => i + 1)
const days = computed(() => {
  if (!step1.birthYear || !step1.birthMonth) return Array.from({ length: 31 }, (_, i) => i + 1)
  return Array.from(
    { length: new Date(+step1.birthYear, +step1.birthMonth, 0).getDate() },
    (_, i) => i + 1
  )
})

function validateStep1(): boolean {
  let ok = true
  step1Errors.gender = step1Errors.nickname = step1Errors.birth = ''
  if (!step1.gender) { step1Errors.gender = '請選擇性別'; ok = false }
  step1Errors.nickname = validateNickname(step1.nickname)
  if (step1Errors.nickname) ok = false
  step1Errors.birth = validateBirthDate(step1.birthYear, step1.birthMonth, step1.birthDay)
  if (step1Errors.birth) ok = false
  return ok
}

// ── Step 2 表單 ──────────────────────────────
const step2 = reactive({
  email: '',
  password: '',
  passwordConfirm: '',
  phone: '',
  agreeTerms: false,
  agreeAge: false,
})
const step2Errors = reactive({
  email: '', password: '', passwordConfirm: '', phone: '', terms: '',
})
const showPass = ref(false)
const showPassConfirm = ref(false)
const isSubmitting = ref(false)
const registeredEmail = ref('')

function validateStep2(): boolean {
  let ok = true
  Object.keys(step2Errors).forEach(k => (step2Errors as any)[k] = '')
  step2Errors.email = validateEmail(step2.email)
  if (step2Errors.email) ok = false
  step2Errors.password = validatePassword(step2.password)
  if (step2Errors.password) ok = false
  step2Errors.passwordConfirm = validatePasswordConfirm(step2.passwordConfirm, step2.password)
  if (step2Errors.passwordConfirm) ok = false
  step2Errors.phone = validatePhone(step2.phone)
  if (step2Errors.phone) ok = false
  if (!step2.agreeTerms || !step2.agreeAge) { step2Errors.terms = '請勾選以上兩項才能繼續'; ok = false }
  return ok
}

async function submitStep2() {
  if (!validateStep2()) return
  isSubmitting.value = true
  try {
    const birthDate = `${step1.birthYear}-${String(step1.birthMonth).padStart(2,'0')}-${String(step1.birthDay).padStart(2,'0')}`
    const res = await register({
      email: step2.email,
      password: step2.password,
      nickname: step1.nickname.trim(),
      gender: step1.gender as 'male' | 'female',
      birth_date: birthDate,
    })
    // Store token and user from registration response
    const token = res.data?.data?.token ?? res.data?.token ?? ''
    if (token) authStore.setToken(token)
    const userData = res.data?.data?.user ?? res.data?.user
    if (userData) authStore.setUser(userData)
    registeredEmail.value = step2.email
    goStep(3)
  } catch (err: any) {
    const errors = err?.response?.data?.errors
    const details = err?.response?.data?.error?.details
    if (errors) {
      if (errors.email) step2Errors.email = errors.email[0]
      if (errors.password) step2Errors.password = errors.password[0]
      if (errors.phone) step2Errors.phone = errors.phone[0]
      if (errors.nickname) step2Errors.email = `暱稱衝突：${errors.nickname[0]}，請返回第 1 步修改`
      if (errors.birth_date) step2Errors.email = errors.birth_date[0]
    } else if (details) {
      details.forEach((d: any) => {
        if (d.field === 'email') step2Errors.email = d.message
        else if (d.field === 'phone') step2Errors.phone = d.message
        else if (d.field === 'password') step2Errors.password = d.message
        else if (d.field === 'nickname') step2Errors.email = `暱稱衝突：${d.message}，請返回第 1 步修改`
      })
    } else {
      step2Errors.email = err?.response?.data?.message ?? '註冊失敗，請稍後再試'
    }
  } finally {
    isSubmitting.value = false
  }
}

// ── Step 3 OTP ───────────────────────────────
const otpDigits = ref<string[]>(['', '', '', '', '', ''])
const otpRefs = ref<HTMLInputElement[]>([])
const otpError = ref('')
const isVerifying = ref(false)
const countdown = ref(0)
const isResending = ref(false)
let countdownTimer: ReturnType<typeof setInterval> | null = null

function startCountdown() {
  countdown.value = 60
  if (countdownTimer) clearInterval(countdownTimer)
  countdownTimer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0 && countdownTimer) {
      clearInterval(countdownTimer)
      countdownTimer = null
    }
  }, 1000)
}

watch(currentStep, (v) => { if (v === 3) startCountdown() })

function onOtpInput(idx: number, e: Event) {
  const val = (e.target as HTMLInputElement).value.replace(/\D/g, '')
  otpDigits.value[idx] = val.slice(-1)
  otpError.value = ''
  if (val && idx < 5) {
    nextTick(() => otpRefs.value[idx + 1]?.focus())
  }
  if (otpDigits.value.every(d => d)) verifyOtp()
}

function onOtpKeydown(idx: number, e: KeyboardEvent) {
  if (e.key === 'Backspace' && !otpDigits.value[idx] && idx > 0) {
    otpDigits.value[idx - 1] = ''
    nextTick(() => otpRefs.value[idx - 1]?.focus())
  }
}

function onOtpPaste(e: ClipboardEvent) {
  const text = e.clipboardData?.getData('text').replace(/\D/g, '').slice(0, 6) ?? ''
  if (text.length === 6) {
    otpDigits.value = text.split('')
    nextTick(() => { otpRefs.value[5]?.focus(); verifyOtp() })
    e.preventDefault()
  }
}

async function verifyOtp() {
  const code = otpDigits.value.join('')
  if (code.length < 6) return
  isVerifying.value = true
  otpError.value = ''
  try {
    await verifyEmail({ verification_code: code, email: registeredEmail.value })
    // Email verified → go to SMS verification (Step 4)
    if (step2.phone) {
      goStep(4)
      sendSmsCode()
    } else {
      router.push('/app/explore')
    }
  } catch {
    otpError.value = '驗證碼不正確或已過期'
    otpDigits.value = ['', '', '', '', '', '']
    nextTick(() => otpRefs.value[0]?.focus())
  } finally {
    isVerifying.value = false
  }
}

async function resendOtp() {
  if (countdown.value > 0 || isResending.value) return
  isResending.value = true
  try {
    await resendVerification(registeredEmail.value)
    startCountdown()
    otpError.value = ''
  } catch {
    otpError.value = '重新發送失敗，請稍後再試'
  } finally {
    isResending.value = false
  }
}

const otpFilled = computed(() => otpDigits.value.every(d => d !== ''))

// ── Step 4 SMS 驗證 ─────────────────────────────
const smsDigits = ref<string[]>(['', '', '', '', '', ''])
const smsRefs = ref<HTMLInputElement[]>([])
const smsError = ref('')
const isSmsVerifying = ref(false)
const smsCountdown = ref(0)
let smsTimer: ReturnType<typeof setInterval> | null = null

function startSmsCountdown() {
  smsCountdown.value = 60
  if (smsTimer) clearInterval(smsTimer)
  smsTimer = setInterval(() => {
    smsCountdown.value--
    if (smsCountdown.value <= 0 && smsTimer) { clearInterval(smsTimer); smsTimer = null }
  }, 1000)
}

async function sendSmsCode() {
  try {
    const client = (await import('@/api/client')).default
    await client.post('/auth/verify-phone/send', { phone: step2.phone })
    startSmsCountdown()
  } catch {
    smsError.value = '簡訊發送失敗，請稍後再試'
  }
}

function onSmsInput(idx: number, e: Event) {
  const val = (e.target as HTMLInputElement).value.replace(/\D/g, '')
  smsDigits.value[idx] = val.slice(-1)
  smsError.value = ''
  if (val && idx < 5) nextTick(() => smsRefs.value[idx + 1]?.focus())
  if (smsDigits.value.every(d => d)) verifySms()
}

function onSmsKeydown(idx: number, e: KeyboardEvent) {
  if (e.key === 'Backspace' && !smsDigits.value[idx] && idx > 0) {
    smsDigits.value[idx - 1] = ''
    nextTick(() => smsRefs.value[idx - 1]?.focus())
  }
}

async function verifySms() {
  const code = smsDigits.value.join('')
  if (code.length < 6) return
  isSmsVerifying.value = true
  smsError.value = ''
  try {
    const client = (await import('@/api/client')).default
    await client.post('/auth/verify-phone/confirm', { phone: step2.phone, code })
    router.push('/app/explore')
  } catch {
    smsError.value = '驗證碼不正確或已過期'
    smsDigits.value = ['', '', '', '', '', '']
    nextTick(() => smsRefs.value[0]?.focus())
  } finally {
    isSmsVerifying.value = false
  }
}

function goLogin() { router.push('/login') }
function goBack() { if (currentStep.value > 1) goStep(currentStep.value - 1) }
</script>

<template>
  <div class="reg-root">

    <!-- 背景裝飾 -->
    <div class="bg-glow bg-glow-1" />
    <div class="bg-glow bg-glow-2" />

    <!-- 頂部欄 -->
    <header class="reg-topbar">
      <button v-if="currentStep > 1 && currentStep < 3" class="back-btn" @click="goBack">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
      </button>
      <button v-else-if="currentStep === 1" class="back-btn" @click="goLogin">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
      </button>
      <div v-else class="back-btn-placeholder" />

      <MiMeetLogo size="md" clickable />

      <div class="back-btn-placeholder" />
    </header>

    <!-- 進度指示 -->
    <div class="progress-wrap">
      <div class="step-dots">
        <div v-for="s in 4" :key="s" class="step-dot" :class="{ active: s === currentStep, done: s < currentStep }" />
      </div>
      <span class="step-label">{{ currentStep }} / 4</span>
    </div>

    <!-- Step 容器 -->
    <div class="step-outer">
      <Transition :name="slideDir === 'forward' ? 'slide-left' : 'slide-right'" mode="out-in">

        <!-- ════ Step 1：基本資料 ════ -->
        <div v-if="currentStep === 1" key="step1" class="step-card">
          <h1 class="step-title">你是哪一位？</h1>
          <p class="step-sub">選擇你的身份，開始你的旅程</p>

          <!-- 性別選擇 -->
          <div class="gender-row">
            <!-- 甜爹 -->
            <button
              class="gender-card"
              :class="{ selected: step1.gender === 'male' }"
              @click="step1.gender = 'male'; step1Errors.gender = ''"
            >
              <div class="gender-icon-wrap">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path d="M20 7l-4-4m0 0h4m-4 0v4"/>
                  <circle cx="10" cy="14" r="6"/>
                </svg>
              </div>
              <div class="gender-label">甜爹</div>
              <div class="gender-sublabel">Male</div>
              <div v-if="step1.gender === 'male'" class="gender-check">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                  <path d="M20 6L9 17l-5-5"/>
                </svg>
              </div>
            </button>

            <!-- 甜心 -->
            <button
              class="gender-card"
              :class="{ selected: step1.gender === 'female' }"
              @click="step1.gender = 'female'; step1Errors.gender = ''"
            >
              <div class="gender-icon-wrap">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <circle cx="12" cy="8" r="6"/>
                  <path d="M12 14v6M9 17h6"/>
                </svg>
              </div>
              <div class="gender-label">甜心</div>
              <div class="gender-sublabel">Female</div>
              <div v-if="step1.gender === 'female'" class="gender-check">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                  <path d="M20 6L9 17l-5-5"/>
                </svg>
              </div>
            </button>
          </div>
          <Transition name="err">
            <p v-if="step1Errors.gender" class="field-error">{{ step1Errors.gender }}</p>
          </Transition>

          <!-- 暱稱 -->
          <div class="field-group">
            <label class="field-label">暱稱</label>
            <div class="input-wrap" :class="{ error: step1Errors.nickname }">
              <input
                v-model="step1.nickname"
                type="text"
                placeholder="2-20 個字"
                maxlength="20"
                class="field-input no-icon"
                @input="step1Errors.nickname = ''"
              />
              <span class="char-count">{{ step1.nickname.length }}/20</span>
            </div>
            <Transition name="err">
              <p v-if="step1Errors.nickname" class="field-error">{{ step1Errors.nickname }}</p>
            </Transition>
          </div>

          <!-- 生日 -->
          <div class="field-group">
            <label class="field-label">生日（需年滿 18 歲）</label>
            <div class="birth-row">
              <select v-model="step1.birthYear" class="birth-select" :class="{ error: step1Errors.birth }" @change="step1Errors.birth = ''">
                <option value="">年</option>
                <option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option>
              </select>
              <select v-model="step1.birthMonth" class="birth-select" :class="{ error: step1Errors.birth }" @change="step1Errors.birth = ''">
                <option value="">月</option>
                <option v-for="m in months" :key="m" :value="String(m)">{{ m }}</option>
              </select>
              <select v-model="step1.birthDay" class="birth-select" :class="{ error: step1Errors.birth }" @change="step1Errors.birth = ''">
                <option value="">日</option>
                <option v-for="d in days" :key="d" :value="String(d)">{{ d }}</option>
              </select>
            </div>
            <Transition name="err">
              <p v-if="step1Errors.birth" class="field-error">{{ step1Errors.birth }}</p>
            </Transition>
          </div>

          <button class="btn-next" @click="validateStep1() && goStep(2)">
            下一步
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </button>

          <p class="login-hint">已有帳號？<button class="inline-link" @click="goLogin">直接登入</button></p>
        </div>

        <!-- ════ Step 2：帳號資料 ════ -->
        <div v-else-if="currentStep === 2" key="step2" class="step-card">
          <h1 class="step-title">建立你的帳號</h1>
          <p class="step-sub">設定登入資訊</p>

          <!-- Email -->
          <div class="field-group">
            <label class="field-label">Email</label>
            <div class="input-wrap" :class="{ error: step2Errors.email }">
              <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/>
              </svg>
              <input v-model="step2.email" type="email" placeholder="your@email.com" autocomplete="email" class="field-input" @input="step2Errors.email = ''" />
            </div>
            <Transition name="err"><p v-if="step2Errors.email" class="field-error">{{ step2Errors.email }}</p></Transition>
          </div>

          <!-- 密碼 -->
          <div class="field-group">
            <label class="field-label">密碼（至少 8 位）</label>
            <div class="input-wrap" :class="{ error: step2Errors.password }">
              <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input v-model="step2.password" :type="showPass ? 'text' : 'password'" placeholder="請設定密碼" autocomplete="new-password" class="field-input" @input="step2Errors.password = ''" />
              <button class="eye-btn" type="button" @click="showPass = !showPass">
                <svg v-if="showPass" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
              </button>
            </div>
            <Transition name="err"><p v-if="step2Errors.password" class="field-error">{{ step2Errors.password }}</p></Transition>
          </div>

          <!-- 確認密碼 -->
          <div class="field-group">
            <label class="field-label">確認密碼</label>
            <div class="input-wrap" :class="{ error: step2Errors.passwordConfirm }">
              <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input v-model="step2.passwordConfirm" :type="showPassConfirm ? 'text' : 'password'" placeholder="再輸入一次密碼" autocomplete="new-password" class="field-input" @input="step2Errors.passwordConfirm = ''" />
              <button class="eye-btn" type="button" @click="showPassConfirm = !showPassConfirm">
                <svg v-if="showPassConfirm" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
              </button>
            </div>
            <Transition name="err"><p v-if="step2Errors.passwordConfirm" class="field-error">{{ step2Errors.passwordConfirm }}</p></Transition>
          </div>

          <!-- 手機 -->
          <div class="field-group">
            <label class="field-label">手機號碼</label>
            <div class="input-wrap" :class="{ error: step2Errors.phone }">
              <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.06 6.06l.9-.9a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
              </svg>
              <input v-model="step2.phone" type="tel" placeholder="09xxxxxxxx" maxlength="10" class="field-input" @input="step2Errors.phone = ''" />
            </div>
            <Transition name="err"><p v-if="step2Errors.phone" class="field-error">{{ step2Errors.phone }}</p></Transition>
          </div>

          <!-- 條款勾選 -->
          <div class="terms-block">
            <label class="check-row">
              <input type="checkbox" v-model="step2.agreeTerms" class="check-input" @change="step2Errors.terms = ''" />
              <span class="check-box" :class="{ checked: step2.agreeTerms }">
                <svg v-if="step2.agreeTerms" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5"><path d="M20 6L9 17l-5-5"/></svg>
              </span>
              <span class="check-label">
                我已閱讀並同意
                <a href="/privacy" target="_blank" class="terms-link">《隱私權政策》</a>
                及
                <a href="/terms" target="_blank" class="terms-link">《使用者條款》</a>
              </span>
            </label>
            <label class="check-row">
              <input type="checkbox" v-model="step2.agreeAge" class="check-input" @change="step2Errors.terms = ''" />
              <span class="check-box" :class="{ checked: step2.agreeAge }">
                <svg v-if="step2.agreeAge" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5"><path d="M20 6L9 17l-5-5"/></svg>
              </span>
              <span class="check-label">我確認年滿 18 歲</span>
            </label>
            <Transition name="err"><p v-if="step2Errors.terms" class="field-error">{{ step2Errors.terms }}</p></Transition>
          </div>

          <button class="btn-next" :class="{ loading: isSubmitting }" :disabled="isSubmitting" @click="submitStep2">
            <span v-if="!isSubmitting">完成註冊</span>
            <span v-else class="spinner-wrap">
              <svg class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
              處理中…
            </span>
          </button>
        </div>

        <!-- ════ Step 3：Email OTP ════ -->
        <div v-else-if="currentStep === 3" key="step3" class="step-card step3-card">
          <!-- 信封動畫 -->
          <div class="envelope-wrap">
            <div class="envelope-anim">
              <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="M2 7l10 7 10-7"/>
              </svg>
            </div>
          </div>

          <h1 class="step-title">驗證你的 Email</h1>
          <p class="step-sub">
            驗證碼已發至<br>
            <strong>{{ registeredEmail }}</strong>
          </p>

          <!-- OTP 輸入 -->
          <div class="otp-row" @paste="onOtpPaste">
            <input
              v-for="(_, idx) in otpDigits"
              :key="idx"
              :ref="el => { if (el) otpRefs[idx] = el as HTMLInputElement }"
              v-model="otpDigits[idx]"
              type="text"
              inputmode="numeric"
              maxlength="1"
              class="otp-input"
              :class="{ filled: otpDigits[idx], error: otpError }"
              @input="onOtpInput(idx, $event)"
              @keydown="onOtpKeydown(idx, $event)"
              @focus="($event.target as HTMLInputElement).select()"
            />
          </div>

          <Transition name="err">
            <p v-if="otpError" class="field-error otp-error">{{ otpError }}</p>
          </Transition>

          <!-- 驗證中 spinner -->
          <div v-if="isVerifying" class="verifying-hint">
            <svg class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            驗證中…
          </div>

          <!-- 重新發送 -->
          <div class="resend-row">
            <span v-if="countdown > 0" class="resend-countdown">{{ countdown }} 秒後可重新發送</span>
            <button v-else class="resend-btn" :disabled="isResending" @click="resendOtp">
              {{ isResending ? '發送中…' : '重新發送驗證碼' }}
            </button>
          </div>

          <p class="step3-hint">收不到信？請檢查垃圾郵件資料夾</p>
        </div>

        <!-- ═══ Step 4：SMS 手機驗證 ═══ -->
        <div v-if="currentStep === 4" class="step-card step3-card">
          <div class="envelope-icon">📱</div>
          <h2 class="step-title">手機驗證</h2>
          <p class="step-sub">驗證碼已發送至 {{ step2.phone }}</p>

          <div class="otp-row">
            <input
              v-for="(_, idx) in smsDigits"
              :key="'sms-' + idx"
              :ref="(el) => { if (el) smsRefs[idx] = el as HTMLInputElement }"
              type="text"
              inputmode="numeric"
              maxlength="1"
              class="otp-input"
              :class="{ filled: smsDigits[idx], error: smsError }"
              :value="smsDigits[idx]"
              @input="onSmsInput(idx, $event)"
              @keydown="onSmsKeydown(idx, $event)"
            />
          </div>

          <p v-if="smsError" class="field-error otp-error">{{ smsError }}</p>

          <button class="btn-primary" :disabled="isSmsVerifying || smsDigits.some(d => !d)" @click="verifySms">
            {{ isSmsVerifying ? '驗證中…' : '完成驗證' }}
          </button>

          <div class="resend-row">
            <span v-if="smsCountdown > 0" class="resend-countdown">{{ smsCountdown }} 秒後可重新發送</span>
            <button v-else class="resend-btn" @click="sendSmsCode">重新發送簡訊驗證碼</button>
          </div>

          <button class="link-btn" @click="router.push('/app/explore')">稍後再驗證</button>
        </div>

      </Transition>
    </div>

  </div>
</template>

<style scoped>
/* ── Variables ──────────────────────────────── */
.reg-root {
  --p: #F0294E; --pd: #D01A3C; --pl: #FFF5F7; --p50: #FFE4EA;
  --t1: #111827; --t2: #6B7280; --t3: #9CA3AF;
  --surf: #F9F9FB; --bdr: #E5E7EB; --err: #EF4444;
}

/* ── Root ───────────────────────────────────── */
.reg-root {
  min-height: 100svh;
  background: var(--surf);
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 20px 48px;
  font-family: 'Noto Sans TC', -apple-system, sans-serif;
  position: relative;
  overflow-x: hidden;
}
.bg-glow { position: fixed; border-radius: 50%; pointer-events: none; z-index: 0; }
.bg-glow-1 { top: -80px; right: -80px; width: 280px; height: 280px; background: #FFF0F3; }
.bg-glow-2 { bottom: -60px; left: -60px; width: 220px; height: 220px; background: #FFF8F5; }

/* ── Topbar ─────────────────────────────────── */
.reg-topbar {
  width: 100%; max-width: 440px;
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 0 8px;
  position: relative; z-index: 1;
}
.back-btn {
  width: 40px; height: 40px; border-radius: 12px;
  background: #fff; border: 1px solid var(--bdr);
  display: flex; align-items: center; justify-content: center;
  color: var(--t2); cursor: pointer; transition: all 0.2s;
}
.back-btn:hover { background: var(--surf); color: var(--t1); }
.back-btn-placeholder { width: 40px; }
.reg-logo {
  font-family: 'Noto Serif TC', serif;
  font-size: 20px; font-weight: 700; color: var(--p); letter-spacing: -0.5px;
}

/* ── Progress ───────────────────────────────── */
.progress-wrap {
  display: flex; align-items: center; gap: 10px;
  margin: 8px 0 16px; position: relative; z-index: 1;
}
.step-dots { display: flex; gap: 6px; }
.step-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--bdr); transition: all 0.3s;
}
.step-dot.active { background: var(--p); width: 24px; border-radius: 4px; }
.step-dot.done { background: var(--p50); }
.step-label { font-size: 12px; color: var(--t3); font-weight: 500; }

/* ── Step Outer ─────────────────────────────── */
.step-outer {
  width: 100%; max-width: 440px;
  position: relative; z-index: 1;
}

/* ── Step Card ──────────────────────────────── */
.step-card {
  background: #fff;
  border: 1px solid var(--bdr);
  border-radius: 20px;
  padding: 28px 24px 24px;
  display: flex; flex-direction: column; gap: 16px;
}
.step-title {
  font-family: 'Noto Serif TC', serif;
  font-size: 22px; font-weight: 700;
  color: var(--t1); letter-spacing: -0.5px; margin: 0;
}
.step-sub { font-size: 13px; color: var(--t2); margin: -8px 0 0; line-height: 1.6; }

/* ── Gender Cards ───────────────────────────── */
.gender-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.gender-card {
  position: relative;
  display: flex; flex-direction: column; align-items: center;
  gap: 6px; padding: 20px 12px;
  background: var(--surf); border: 2px solid var(--bdr);
  border-radius: 16px; cursor: pointer;
  font-family: inherit; transition: all 0.2s;
}
.gender-card:hover { border-color: var(--p50); background: var(--pl); }
.gender-card.selected { border-color: var(--p); background: var(--pl); }
.gender-icon-wrap {
  width: 52px; height: 52px; border-radius: 50%;
  background: #fff; display: flex; align-items: center; justify-content: center;
  color: var(--t2); border: 1px solid var(--bdr);
  transition: all 0.2s;
}
.gender-card.selected .gender-icon-wrap { color: var(--p); border-color: var(--p50); }
.gender-label { font-size: 15px; font-weight: 700; color: var(--t1); }
.gender-sublabel { font-size: 11px; color: var(--t3); }
.gender-check {
  position: absolute; top: 10px; right: 10px;
  width: 20px; height: 20px; border-radius: 50%;
  background: var(--p); display: flex; align-items: center; justify-content: center;
}

/* ── Fields ─────────────────────────────────── */
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-label { font-size: 13px; font-weight: 600; color: var(--t1); }
.input-wrap {
  position: relative; display: flex; align-items: center;
  border: 1.5px solid var(--bdr); border-radius: 12px;
  background: var(--surf); transition: border-color 0.2s, box-shadow 0.2s; overflow: hidden;
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
.field-input.no-icon { padding-left: 14px; }
.field-input::placeholder { color: var(--t3); }
.eye-btn {
  position: absolute; right: 12px; background: none; border: none;
  color: var(--t3); cursor: pointer; padding: 4px; display: flex; align-items: center;
}
.char-count {
  position: absolute; right: 12px; font-size: 11px; color: var(--t3); pointer-events: none;
}

/* ── Birth Row ──────────────────────────────── */
.birth-row { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 8px; }
.birth-select {
  height: 48px; padding: 0 10px;
  border: 1.5px solid var(--bdr); border-radius: 12px;
  background: var(--surf); color: var(--t1);
  font-size: 14px; font-family: inherit; outline: none;
  transition: border-color 0.2s; cursor: pointer; appearance: none;
}
.birth-select:focus { border-color: var(--p); }
.birth-select.error { border-color: var(--err); }

/* ── Terms ──────────────────────────────────── */
.terms-block { display: flex; flex-direction: column; gap: 10px; }
.check-row {
  display: flex; align-items: flex-start; gap: 10px; cursor: pointer;
}
.check-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  pointer-events: none;
}
.check-box {
  width: 20px;
  height: 20px;
  min-width: 20px;
  min-height: 20px;
  border-radius: 6px;
  border: 2px solid #D1D5DB;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  margin-top: 1px;
  flex-shrink: 0;
}
.check-box.checked {
  background: #F0294E;
  border-color: #F0294E;
}
.check-label { font-size: 13px; color: var(--t2); line-height: 1.55; }
.terms-link { color: var(--p); font-weight: 600; text-decoration: none; }
.terms-link:hover { text-decoration: underline; }

/* ── Field Error ────────────────────────────── */
.field-error { font-size: 12px; color: var(--err); margin: 0; }
.err-enter-active, .err-leave-active { transition: all 0.2s ease; }
.err-enter-from, .err-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Buttons ────────────────────────────────── */
.btn-next {
  width: 100%; height: 50px;
  background: var(--p); color: #fff; border: none;
  border-radius: 14px; font-size: 16px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 4px 14px rgba(240,41,78,0.28);
  transition: all 0.25s; margin-top: 4px;
}
.btn-next:hover:not(:disabled) { background: var(--pd); transform: translateY(-1px); }
.btn-next:disabled { opacity: 0.75; cursor: not-allowed; }
.spinner-wrap { display: flex; align-items: center; gap: 8px; }
.spinner { animation: spin 0.9s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ── Login hint ─────────────────────────────── */
.login-hint { text-align: center; font-size: 13px; color: var(--t2); margin: 0; }
.inline-link {
  background: none; border: none; color: var(--p);
  font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; padding: 0;
}

/* ── Step 3 Envelope ────────────────────────── */
.step3-card { align-items: center; text-align: center; }
.envelope-wrap { margin: 8px 0 4px; }
.envelope-anim {
  width: 80px; height: 80px; border-radius: 24px;
  background: var(--pl); border: 1px solid var(--p50);
  display: flex; align-items: center; justify-content: center;
  color: var(--p); animation: float 3s ease-in-out infinite;
}
@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}

/* ── OTP ────────────────────────────────────── */
.otp-row { display: flex; gap: 8px; justify-content: center; margin: 8px 0 4px; }
.otp-input {
  width: 44px; height: 54px; border-radius: 12px;
  border: 2px solid var(--bdr); background: var(--surf);
  text-align: center; font-size: 22px; font-weight: 700;
  color: var(--t1); font-family: 'Inter', sans-serif; outline: none;
  transition: all 0.2s; caret-color: var(--p);
}
.otp-input:focus { border-color: var(--p); background: #fff; box-shadow: 0 0 0 3px rgba(240,41,78,0.1); }
.otp-input.filled { border-color: var(--p50); background: var(--pl); }
.otp-input.error { border-color: var(--err); }
.otp-error { text-align: center; }
.verifying-hint {
  display: flex; align-items: center; justify-content: center;
  gap: 6px; font-size: 13px; color: var(--t2);
}

/* ── Resend ─────────────────────────────────── */
.resend-row { display: flex; justify-content: center; }
.resend-countdown { font-size: 13px; color: var(--t3); }
.resend-btn {
  background: none; border: none; color: var(--p);
  font-size: 13px; font-weight: 700; cursor: pointer;
  font-family: inherit; padding: 0;
  text-decoration: underline; text-underline-offset: 2px;
}
.resend-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.step3-hint { font-size: 12px; color: var(--t3); margin: 0; }

/* ── Slide Transition ───────────────────────── */
.slide-left-enter-active, .slide-left-leave-active,
.slide-right-enter-active, .slide-right-leave-active {
  transition: all 0.3s ease;
}
.slide-left-enter-from { opacity: 0; transform: translateX(40px); }
.slide-left-leave-to   { opacity: 0; transform: translateX(-40px); }
.slide-right-enter-from { opacity: 0; transform: translateX(-40px); }
.slide-right-leave-to   { opacity: 0; transform: translateX(40px); }

@media (max-width: 480px) {
  .step-card { padding: 24px 18px 20px; border-radius: 16px; }
  .otp-input { width: 38px; height: 48px; font-size: 20px; }
}

</style>
