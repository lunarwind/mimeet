<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  initiatePhoneChange,
  verifyOldPhone,
  verifyNewPhone,
} from '@/api/auth'

type Step = 'input_new' | 'old_otp' | 'new_otp' | 'completed'

const router = useRouter()
const authStore = useAuthStore()

const currentStep = ref<Step>('input_new')
const newPhone = ref('')
const oldOtp = ref('')
const newOtp = ref('')
const newPhoneFromBackend = ref('')

const submitting = ref(false)
const errorMsg = ref<string | null>(null)
const cooldownOld = ref(0)
const cooldownNew = ref(0)
let cooldownOldTimer: ReturnType<typeof setInterval> | undefined
let cooldownNewTimer: ReturnType<typeof setInterval> | undefined

const newPhoneValid = computed(() => /^09\d{8}$/.test(newPhone.value))

// PR-3 component-level guard:未驗證 user 不能進此頁
onMounted(() => {
  if (!authStore.user?.phone_verified || !authStore.user?.phone) {
    router.replace('/app/settings/verify')
  }
})

onBeforeUnmount(() => {
  if (cooldownOldTimer) clearInterval(cooldownOldTimer)
  if (cooldownNewTimer) clearInterval(cooldownNewTimer)
})

function startCooldownOld() {
  cooldownOld.value = 60
  cooldownOldTimer = setInterval(() => {
    cooldownOld.value--
    if (cooldownOld.value <= 0 && cooldownOldTimer) {
      clearInterval(cooldownOldTimer)
      cooldownOldTimer = undefined
    }
  }, 1000)
}

function startCooldownNew() {
  cooldownNew.value = 60
  cooldownNewTimer = setInterval(() => {
    cooldownNew.value--
    if (cooldownNew.value <= 0 && cooldownNewTimer) {
      clearInterval(cooldownNewTimer)
      cooldownNewTimer = undefined
    }
  }, 1000)
}

function extractError(err: unknown): string {
  const data = (err as { response?: { data?: any } })?.response?.data
  return (
    data?.errors?.new_phone?.[0] ??
    data?.errors?.phone?.[0] ??
    data?.error?.details?.[0]?.message ??
    data?.error?.message ??
    '操作失敗，請稍後再試'
  )
}

async function submitInitiate() {
  if (!newPhoneValid.value || submitting.value) return
  submitting.value = true
  errorMsg.value = null
  try {
    const res = await initiatePhoneChange({ new_phone: newPhone.value })
    newPhoneFromBackend.value = res?.data?.new_phone ?? newPhone.value
    startCooldownOld()
    currentStep.value = 'old_otp'
  } catch (err) {
    errorMsg.value = extractError(err)
  } finally {
    submitting.value = false
  }
}

async function submitVerifyOld() {
  if (oldOtp.value.length !== 6 || submitting.value) return
  submitting.value = true
  errorMsg.value = null
  try {
    await verifyOldPhone({ old_otp: oldOtp.value })
    startCooldownNew()
    currentStep.value = 'new_otp'
  } catch (err) {
    errorMsg.value = extractError(err)
  } finally {
    submitting.value = false
  }
}

async function submitVerifyNew() {
  if (newOtp.value.length !== 6 || submitting.value) return
  submitting.value = true
  errorMsg.value = null
  try {
    await verifyNewPhone({ new_otp: newOtp.value })
    await authStore.refreshUser()
    currentStep.value = 'completed'
  } catch (err) {
    errorMsg.value = extractError(err)
  } finally {
    submitting.value = false
  }
}

async function resendOldOtp() {
  if (cooldownOld.value > 0) return
  await submitInitiate()
}

function goBack() {
  if (currentStep.value === 'completed' || currentStep.value === 'input_new') {
    router.push('/app/settings')
  } else {
    currentStep.value = 'input_new'
    errorMsg.value = null
  }
}
</script>

<template>
  <div class="phone-change-view">
    <header class="topbar">
      <button class="back-btn" @click="goBack">← 返回</button>
      <h1 class="title">變更手機號碼</h1>
      <div class="placeholder"></div>
    </header>

    <div class="content">
      <!-- Step 1: 輸入新號 -->
      <div v-if="currentStep === 'input_new'" class="step-card">
        <h2 class="step-title">輸入新手機號碼</h2>
        <p class="step-desc">
          目前手機:<strong>{{ authStore.user?.phone }}</strong>
        </p>
        <div class="field-group">
          <label class="field-label">新手機號碼</label>
          <input
            v-model="newPhone"
            type="tel"
            class="field-input"
            placeholder="09xxxxxxxx"
            maxlength="10"
            autocomplete="tel"
          />
        </div>
        <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>
        <button
          class="primary-btn"
          :disabled="!newPhoneValid || submitting"
          @click="submitInitiate"
        >
          {{ submitting ? '處理中…' : '繼續(發送驗證碼到舊號)' }}
        </button>
        <p class="hint">
          下一步會發驗證碼到您目前的手機 {{ authStore.user?.phone }},確認您本人後再驗證新號。
        </p>
      </div>

      <!-- Step 2: 驗證舊號 -->
      <div v-else-if="currentStep === 'old_otp'" class="step-card">
        <h2 class="step-title">驗證目前手機</h2>
        <p class="step-desc">
          驗證碼已發送至:<strong>{{ authStore.user?.phone }}</strong>
        </p>
        <div class="field-group">
          <input
            v-model="oldOtp"
            type="text"
            class="field-input otp-input"
            placeholder="000000"
            maxlength="6"
            inputmode="numeric"
            autocomplete="one-time-code"
          />
        </div>
        <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>
        <button
          class="primary-btn"
          :disabled="oldOtp.length !== 6 || submitting"
          @click="submitVerifyOld"
        >
          {{ submitting ? '驗證中…' : '驗證' }}
        </button>
        <button
          v-if="cooldownOld === 0"
          class="text-btn"
          @click="resendOldOtp"
        >
          重新發送驗證碼
        </button>
        <p v-else class="hint">{{ cooldownOld }} 秒後可重新發送</p>
      </div>

      <!-- Step 3: 驗證新號 -->
      <div v-else-if="currentStep === 'new_otp'" class="step-card">
        <h2 class="step-title">驗證新手機</h2>
        <p class="step-desc">
          驗證碼已發送至:<strong>{{ newPhoneFromBackend }}</strong>
        </p>
        <div class="field-group">
          <input
            v-model="newOtp"
            type="text"
            class="field-input otp-input"
            placeholder="000000"
            maxlength="6"
            inputmode="numeric"
            autocomplete="one-time-code"
          />
        </div>
        <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>
        <button
          class="primary-btn"
          :disabled="newOtp.length !== 6 || submitting"
          @click="submitVerifyNew"
        >
          {{ submitting ? '驗證中…' : '完成換號' }}
        </button>
        <p v-if="cooldownNew > 0" class="hint">{{ cooldownNew }} 秒後可重新發送</p>
      </div>

      <!-- Step 4: 完成 -->
      <div v-else-if="currentStep === 'completed'" class="step-card success">
        <div class="success-icon">✓</div>
        <h2 class="step-title">手機已成功變更</h2>
        <p class="step-desc">
          您的新手機:<strong>{{ authStore.user?.phone }}</strong>
        </p>
        <button class="primary-btn" @click="router.push('/app/settings')">
          返回設定
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.phone-change-view {
  background: #F9F9FB;
  min-height: 100dvh;
  padding-bottom: var(--app-bottom-inset);
}

.topbar {
  display: flex;
  align-items: center;
  height: 56px;
  padding: 0 16px;
  background: #fff;
  border-bottom: 0.5px solid #E8ECF0;
  position: sticky;
  top: 0;
  z-index: 10;
}

.back-btn {
  background: none;
  border: none;
  color: #334155;
  font-size: 14px;
  cursor: pointer;
  padding: 8px;
}

.title {
  flex: 1;
  text-align: center;
  font-size: 16px;
  font-weight: 600;
  color: #0F172A;
  margin: 0;
}

.placeholder {
  width: 40px;
}

.content {
  padding: 24px 16px;
  max-width: 480px;
  margin: 0 auto;
}

.step-card {
  background: #fff;
  border-radius: 16px;
  padding: 24px 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.step-title {
  font-size: 20px;
  font-weight: 700;
  color: #0F172A;
  margin: 0 0 8px;
}

.step-desc {
  font-size: 14px;
  color: #475569;
  margin: 0 0 20px;
  line-height: 1.6;
}

.step-desc strong {
  color: #0F172A;
  font-weight: 700;
}

.field-group {
  margin-bottom: 16px;
}

.field-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  margin-bottom: 6px;
}

.field-input {
  width: 100%;
  height: 52px;
  padding: 0 16px;
  border: 1.5px solid #E2E8F0;
  border-radius: 12px;
  font-size: 16px;
  color: #0F172A;
  background: #fff;
  outline: none;
  box-sizing: border-box;
}

.field-input:focus {
  border-color: #F0294E;
  box-shadow: 0 0 0 3px rgba(240, 41, 78, 0.12);
}

.otp-input {
  text-align: center;
  font-size: 24px;
  font-weight: 700;
  letter-spacing: 8px;
}

.error-msg {
  font-size: 13px;
  color: #EF4444;
  margin: 0 0 12px;
}

.primary-btn {
  width: 100%;
  height: 48px;
  border-radius: 12px;
  border: none;
  background: #F0294E;
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
}

.primary-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.text-btn {
  width: 100%;
  margin-top: 12px;
  background: none;
  border: none;
  color: #F0294E;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 8px;
}

.hint {
  margin-top: 12px;
  font-size: 12px;
  color: #94A3B8;
  text-align: center;
}

.success {
  text-align: center;
}

.success-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 16px;
  border-radius: 50%;
  background: #DCFCE7;
  color: #22C55E;
  font-size: 32px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
