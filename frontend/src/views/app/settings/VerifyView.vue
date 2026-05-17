<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { sendPhoneCode, verifyPhoneCode } from '@/api/auth'
import client from '@/api/client'
import { requestVerificationCode, uploadVerificationPhoto, getVerificationStatus, initiateCreditCardVerification } from '@/api/verification'
import type { VerificationStatusResponse } from '@/api/verification'
import { redirectToECPay } from '@/utils/paymentRedirect'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

// ── 狀態 ──────────────────────────────────────────────────
type Step = 'overview' | 'sms-send' | 'sms-verify' | 'advanced-guide' | 'advanced-photo' | 'advanced-pending'

const currentStep = ref<Step>('overview')
const isLoading = ref(false)

// ── 手機驗證 ──────────────────────────────────────────────
// PR-3:不再有 user-controllable phone state。用 authStore.user.phone(已 masked)。
const smsCode = ref('')
const smsSending = ref(false)
const smsVerifying = ref(false)
const smsError = ref<string | null>(null)
const smsCooldown = ref(0)
let cooldownTimer: ReturnType<typeof setInterval> | undefined

const phoneVerified = computed(() => authStore.user?.phone_verified ?? false)
const advancedVerified = computed(() =>
  (authStore.user?.membership_level ?? 0) >= 2 || !!authStore.user?.credit_card_verified_at
)

function startSmsCooldown() {
  smsCooldown.value = 60
  cooldownTimer = setInterval(() => {
    smsCooldown.value--
    if (smsCooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}

async function sendSmsCode() {
  if (!authStore.user?.phone || smsSending.value || smsCooldown.value > 0) return
  smsSending.value = true
  smsError.value = null
  try {
    // PR-3:不傳 phone,後端固定用 auth user.phone
    await sendPhoneCode({})
    startSmsCooldown()
    currentStep.value = 'sms-verify'
  } catch {
    smsError.value = '發送失敗，請稍後再試'
  } finally {
    smsSending.value = false
  }
}

async function verifySmsCode() {
  if (smsCode.value.length !== 6 || smsVerifying.value) return
  smsVerifying.value = true
  smsError.value = null
  try {
    // PR-3:不傳 phone
    await verifyPhoneCode({ code: smsCode.value })
    await authStore.refreshUser()
    currentStep.value = 'overview'
  } catch {
    smsError.value = '驗證碼錯誤或已過期'
  } finally {
    smsVerifying.value = false
  }
}

// ── 進階驗證（Lv1.5 女性 / Lv2 男性）──────────────────────
const photoCode = ref('')
const photoCodeExpiry = ref('')
const photoUrl = ref('')
const advancedSubmitting = ref(false)
const advancedError = ref<string | null>(null)
const isUploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const cameraInput = ref<HTMLInputElement | null>(null)
const verificationStatus = ref<VerificationStatusResponse | null>(null)
const verificationRejectReason = ref<string | null>(null)

const isFemale = computed(() => authStore.user?.gender === 'female')
const lv15Verified = computed(() => (authStore.user?.membership_level ?? 0) >= 1.5)
// 男性信用卡驗證
const creditCardLoading = ref(false)
const creditCardError = ref<string | null>(null)
const creditCardResult = ref<'success' | 'failed' | null>(null)
const creditCardVerified = computed(() => !!authStore.user?.credit_card_verified_at)
// 男性 Lv0（手機未驗證）→ 顯示但 disable，提示先完成手機驗證
const maleNeedsPhoneFirst = computed(() =>
  !isFemale.value && !phoneVerified.value
)

// Check existing verification status on mount
onMounted(async () => {
  if (isFemale.value && !lv15Verified.value) {
    try {
      const status = await getVerificationStatus()
      verificationStatus.value = status
      if (status.status === 'pending_review') {
        currentStep.value = 'advanced-pending'
      } else if (status.status === 'rejected') {
        verificationRejectReason.value = status.reject_reason ?? null
      }
    } catch { /* ignore */ }
  }

  // Handle ECPay return for credit card verification
  const ccResult = route.query.credit_card as string | undefined
  if (ccResult === 'success' || ccResult === 'failed') {
    creditCardResult.value = ccResult
    currentStep.value = 'advanced-guide'
    if (ccResult === 'success') {
      // Refresh user data to reflect new membership_level and credit_card_verified_at
      try { await authStore.initialize() } catch { /* ignore */ }
    }
    // Clean up URL query params
    router.replace({ query: {} })
  }
})

async function startAdvancedVerification() {
  creditCardResult.value = null
  creditCardError.value = null
  if (isFemale.value) {
    // Re-check status to avoid stale UI from multi-tab / multi-device
    try {
      const status = await getVerificationStatus()
      verificationStatus.value = status
      if (status.status === 'pending_review') {
        currentStep.value = 'advanced-pending'
        return
      }
    } catch { /* fallback to request */ }

    currentStep.value = 'advanced-guide'
    try {
      const data = await requestVerificationCode()
      photoCode.value = data.random_code
      photoCodeExpiry.value = data.expires_at
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { error?: { code?: string; message?: string } } } })?.response?.data?.error
      if (msg?.code === 'VERIFICATION_PENDING_REVIEW') {
        verificationStatus.value = {
          status: 'pending_review',
          submitted_at: new Date().toISOString(),
        }
        currentStep.value = 'advanced-pending'
      } else {
        advancedError.value = msg?.message ?? '無法取得驗證碼'
      }
    }
  } else {
    currentStep.value = 'advanced-guide'
  }
}

async function initiateCreditCard() {
  if (creditCardLoading.value) return
  creditCardLoading.value = true
  creditCardError.value = null
  try {
    const data = await initiateCreditCardVerification()
    redirectToECPay(data.aio_url, data.params)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: { message?: string } } } }
    creditCardError.value = e?.response?.data?.error?.message ?? '無法發起驗證，請稍後再試'
  } finally {
    creditCardLoading.value = false
  }
}

async function submitAdvancedPhoto() {
  if (!photoUrl.value || advancedSubmitting.value) return
  advancedSubmitting.value = true
  advancedError.value = null
  try {
    await uploadVerificationPhoto(photoUrl.value, photoCode.value)
    // Sync state so the overview button stays hidden after returning
    verificationStatus.value = {
      status: 'pending_review',
      submitted_at: new Date().toISOString(),
    }
    verificationRejectReason.value = null
    currentStep.value = 'advanced-pending'
  } catch (err: unknown) {
    const msg = (err as { response?: { data?: { error?: { code?: string; message?: string } } } })?.response?.data?.error
    if (msg?.code === 'VERIFICATION_EXPIRED') {
      advancedError.value = '驗證碼已過期，請重新申請'
    } else if (msg?.code === 'VERIFICATION_PENDING_REVIEW') {
      // Already submitted in another tab/device — sync state and switch view
      verificationStatus.value = {
        status: 'pending_review',
        submitted_at: new Date().toISOString(),
      }
      currentStep.value = 'advanced-pending'
    } else {
      advancedError.value = msg?.message ?? '提交失敗，請重試'
    }
  } finally {
    advancedSubmitting.value = false
  }
}

function triggerFileUpload() { fileInput.value?.click() }
function triggerCamera() { cameraInput.value?.click() }

async function handleFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  ;(e.target as HTMLInputElement).value = ''

  isUploading.value = true
  advancedError.value = null
  try {
    const formData = new FormData()
    formData.append('photo', file)
    const res = await client.post('/users/me/photos', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    photoUrl.value = res.data?.data?.photo?.url ?? res.data?.data?.url ?? ''
    if (!photoUrl.value) throw new Error('No URL returned')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    const msg = e?.response?.data?.message ?? '照片上傳失敗，請重試'
    advancedError.value = msg
  } finally {
    isUploading.value = false
  }
}

function goBack() {
  if (currentStep.value === 'overview') {
    router.back()
  } else {
    currentStep.value = 'overview'
  }
}

onBeforeUnmount(() => {
  if (cooldownTimer) { clearInterval(cooldownTimer); cooldownTimer = undefined }
})

// ── PR-1 (v3.6) 問題回報 modal ──────────────────────────────
const reportModalOpen = ref(false)
const reportDescription = ref('')
const reportSubmitting = ref(false)
const reportError = ref<string | null>(null)
const reportSuccessMsg = ref<string | null>(null)
const reportSentThisSession = ref(false)
function openReportModal() {
  if (reportSentThisSession.value) return
  reportDescription.value = ''
  reportError.value = null
  reportModalOpen.value = true
}

function closeReportModal() {
  reportModalOpen.value = false
}

async function submitReport() {
  const desc = reportDescription.value.trim()
  if (desc.length < 10) {
    reportError.value = '問題描述至少需 10 字'
    return
  }
  if (desc.length > 1000) {
    reportError.value = '問題描述不可超過 1000 字'
    return
  }
  reportSubmitting.value = true
  reportError.value = null
  try {
    const res = await client.post('/reports', { type: 'system_issue', description: desc })
    const ticket = res.data?.data?.report
    const ticketId = ticket?.uuid ?? ticket?.id ?? ''
    reportSuccessMsg.value = `已收到您的回報（票號 #${ticketId}），客服將儘快處理。期間您可以繼續嘗試或登出。`
    reportSentThisSession.value = true
    reportModalOpen.value = false
    setTimeout(() => { reportSuccessMsg.value = null }, 8000)
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { error?: { message?: string }; message?: string } } }
    if (e?.response?.status === 422) {
      reportError.value = e?.response?.data?.error?.message ?? e?.response?.data?.message ?? '描述格式不正確'
    } else if (e?.response?.status === 429) {
      reportError.value = e?.response?.data?.error?.message ?? '您 24 小時內已回報過，若仍有問題請聯繫客服'
    } else {
      reportError.value = '送出失敗，請稍後再試'
    }
  } finally {
    reportSubmitting.value = false
  }
}

// 登出（BottomNav 對 Lv0 隱藏，此頁需要明確登出入口避免 user 被困）
async function handleLogout() {
  try { await client.post('/auth/logout') } catch { /* ignore */ }
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="verify-view">
    <!-- TopBar -->
    <header class="verify-topbar">
      <button class="verify-topbar__back" @click="goBack" aria-label="返回">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </button>
      <h1 class="verify-topbar__title">身份驗證</h1>
      <div class="verify-topbar__placeholder" />
    </header>

    <!-- Overview -->
    <div v-if="currentStep === 'overview'" class="verify-content">
      <p class="verify-desc">完成驗證以解鎖更多功能並提升誠信分數</p>
      <!-- 回報成功 inline message (8s 自動消失) -->
      <div v-if="reportSuccessMsg" class="verify-inline-success">
        ✓ {{ reportSuccessMsg }}
      </div>

      <!-- 手機驗證 -->
      <div class="verify-card" :class="{ 'verify-card--done': phoneVerified }">
        <div class="verify-card__icon">
          <svg v-if="phoneVerified" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>
          </svg>
        </div>
        <div class="verify-card__info">
          <h3 class="verify-card__title">手機驗證</h3>
          <p class="verify-card__sub">
            {{ phoneVerified ? '已完成驗證' : '驗證您的台灣手機號碼' }}
          </p>
        </div>
        <button
          v-if="!phoneVerified"
          class="verify-card__btn"
          @click="currentStep = 'sms-send'"
        >
          驗證
        </button>
        <span v-else class="verify-card__check">✓</span>
      </div>

      <!-- 進階驗證（Lv1.5 女性 / Lv2 男性）-->
      <div class="verify-card" :class="{ 'verify-card--done': lv15Verified || advancedVerified }">
        <div class="verify-card__icon">
          <svg v-if="lv15Verified || advancedVerified" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>
        <div class="verify-card__info">
          <h3 class="verify-card__title">{{ isFemale ? '真人驗證 (Lv1.5)' : '進階驗證' }}</h3>
          <p class="verify-card__sub">
            <template v-if="lv15Verified || advancedVerified">已完成驗證</template>
            <template v-else-if="verificationStatus?.status === 'pending_review'">審核中，請耐心等待</template>
            <template v-else-if="verificationStatus?.status === 'rejected'">驗證未通過，可重新申請</template>
            <template v-else>{{ isFemale ? '上傳含隨機碼的自拍照' : '信用卡小額驗證' }}</template>
          </p>
        </div>
        <button
          v-if="!lv15Verified && !advancedVerified && verificationStatus?.status !== 'pending_review'"
          class="verify-card__btn"
          @click="startAdvancedVerification"
        >
          {{ verificationStatus?.status === 'rejected' ? '重新驗證' : '驗證' }}
        </button>
        <Tag v-else-if="verificationStatus?.status === 'pending_review'" class="verify-card__tag" style="background:#FEF3C7;color:#92400E;border:none;font-size:12px;">審核中</Tag>
        <span v-else class="verify-card__check">✓</span>
      </div>

      <!-- Rejection reason if applicable -->
      <div v-if="verificationStatus?.status === 'rejected' && verificationRejectReason" class="verify-reject-notice">
        未通過原因：{{ verificationRejectReason }}
      </div>
    </div>

    <!-- SMS Send (PR-3: 顯示帳號既有手機號,user 不能改) -->
    <div v-else-if="currentStep === 'sms-send'" class="verify-content">
      <div class="verify-step">
        <h2 class="verify-step__title">確認手機號碼</h2>
        <p class="verify-step__desc">我們將發送驗證碼至您帳號上的手機</p>
        <div class="verify-input-wrap">
          <div class="verify-readonly-phone">{{ authStore.user?.phone || '(未設定)' }}</div>
        </div>
        <p v-if="phoneVerified && authStore.user?.phone" class="verify-step__desc" style="font-size: 12px; margin-top: -8px;">
          <button type="button" class="verify-link" @click="router.push('/app/settings/phone-change')">
            不是這個號碼?變更手機
          </button>
        </p>
        <p v-if="smsError" class="verify-error">{{ smsError }}</p>
        <button
          class="verify-submit"
          :disabled="!authStore.user?.phone || smsSending || smsCooldown > 0"
          @click="sendSmsCode"
        >
          {{ smsSending ? '發送中…' : (smsCooldown > 0 ? `${smsCooldown} 秒後可重發` : '發送驗證碼') }}
        </button>
      </div>
    </div>

    <!-- SMS Verify -->
    <div v-else-if="currentStep === 'sms-verify'" class="verify-content">
      <div class="verify-step">
        <h2 class="verify-step__title">輸入驗證碼</h2>
        <p class="verify-step__desc">驗證碼已發送至 {{ authStore.user?.phone }}</p>
        <div class="verify-input-wrap">
          <input
            v-model="smsCode"
            type="text"
            class="verify-input verify-input--code"
            placeholder="000000"
            maxlength="6"
            inputmode="numeric"
            autocomplete="one-time-code"
          />
        </div>
        <p v-if="smsError" class="verify-error">{{ smsError }}</p>
        <button
          class="verify-submit"
          :disabled="smsCode.length !== 6 || smsVerifying"
          @click="verifySmsCode"
        >
          {{ smsVerifying ? '驗證中…' : '確認驗證' }}
        </button>
        <button
          class="verify-resend"
          :disabled="smsCooldown > 0"
          @click="sendSmsCode"
        >
          {{ smsCooldown > 0 ? `${smsCooldown} 秒後可重新發送` : '重新發送驗證碼' }}
        </button>

        <!-- PR-1 (v3.6) 問題回報入口 + 登出 -->
        <div class="verify-fallback-links">
          <p class="verify-fallback-hint">
            收不到驗證碼？或系統有問題嗎？
            <button
              class="verify-link"
              :disabled="reportSentThisSession"
              @click="openReportModal"
            >
              {{ reportSentThisSession ? '已回報' : '回報問題' }}
            </button>
          </p>
          <button class="verify-link verify-link--muted" @click="handleLogout">稍後再試，先登出</button>
        </div>
      </div>
    </div>

    <!-- PR-1 (v3.6) 問題回報 Modal -->
    <div v-if="reportModalOpen" class="report-modal-overlay" @click.self="closeReportModal">
      <div class="report-modal">
        <h3 class="report-modal__title">回報驗證問題</h3>
        <p class="report-modal__hint">ⓘ 我們將協助您處理 SMS 驗證問題</p>

        <div class="report-modal__field">
          <label class="report-modal__label">手機號碼</label>
          <div class="report-modal__readonly">{{ authStore.user?.phone || '(未提供)' }}</div>
        </div>

        <div class="report-modal__field">
          <label class="report-modal__label">問題描述（10-1000 字）</label>
          <textarea
            v-model="reportDescription"
            class="report-modal__textarea"
            rows="5"
            maxlength="1000"
            placeholder="請描述您遇到的狀況，例如「等了 10 分鐘還沒收到驗證碼」「驗證碼一直顯示錯誤」等"
            :disabled="reportSubmitting"
          />
          <div class="report-modal__char-count">{{ reportDescription.length }}/1000</div>
        </div>

        <p v-if="reportError" class="report-modal__error">{{ reportError }}</p>

        <div class="report-modal__actions">
          <button
            class="report-modal__btn report-modal__btn--cancel"
            :disabled="reportSubmitting"
            @click="closeReportModal"
          >
            取消
          </button>
          <button
            class="report-modal__btn report-modal__btn--submit"
            :disabled="reportSubmitting || reportDescription.trim().length < 10"
            @click="submitReport"
          >
            {{ reportSubmitting ? '送出中…' : '送出' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Advanced Guide -->
    <div v-else-if="currentStep === 'advanced-guide'" class="verify-content">
      <div class="verify-step">
        <h2 class="verify-step__title">{{ isFemale ? '真人驗證' : '信用卡驗證' }}</h2>

        <!-- 女性：照片驗證 -->
        <template v-if="isFemale">
          <p class="verify-step__desc">請手持證件並拍攝自拍照，照片中需清楚顯示以下驗證碼</p>
          <div class="verify-code-display">
            <span class="verify-code-display__code">{{ photoCode }}</span>
            <span class="verify-code-display__hint">驗證碼（10 分鐘內有效）</span>
          </div>
          <ul class="verify-rules">
            <li>請確保光線充足，五官清晰可見</li>
            <li>手持有效身份證件</li>
            <li>照片中需包含上方驗證碼</li>
          </ul>
          <button
            class="verify-submit"
            @click="currentStep = 'advanced-photo'"
          >
            我已準備好，開始拍攝
          </button>
        </template>

        <!-- 男性：信用卡驗證 -->
        <template v-else>
          <p class="verify-step__badge">🔒 僅限男性會員</p>

          <!-- Lv0：手機未驗證時顯示前置提醒 -->
          <div v-if="maleNeedsPhoneFirst" class="verify-result verify-result--info">
            <span>⚠️ 請先完成手機驗證（Lv1），才可進行信用卡驗證</span>
          </div>

          <!-- 驗證成功後返回的提示 -->
          <div v-else-if="creditCardResult === 'success'" class="verify-result verify-result--success">
            <span>✅ 信用卡驗證成功！誠信分數已 +15</span>
          </div>
          <div v-else-if="creditCardResult === 'failed'" class="verify-result verify-result--failed">
            <span>❌ 付款未完成，請重試</span>
          </div>

          <p class="verify-step__desc">透過信用卡小額驗證（NT$100）確認您的真實身份</p>
          <ul class="verify-rules">
            <li>💰 預授權 NT$100（非實際扣款）</li>
            <li>⏰ 驗證完成後 3-5 個工作日內自動退還</li>
            <li>🇹🇼 僅支援台灣發行之信用卡 / 簽帳卡（男性專屬驗證項目）</li>
            <li>⭐ 驗證完成後誠信分數 +15</li>
          </ul>
          <p v-if="creditCardError" class="verify-error">{{ creditCardError }}</p>
          <button
            class="verify-submit"
            :disabled="creditCardLoading || creditCardVerified || maleNeedsPhoneFirst"
            @click="initiateCreditCard"
          >
            <template v-if="creditCardLoading">處理中…</template>
            <template v-else-if="creditCardVerified">已完成驗證</template>
            <template v-else-if="maleNeedsPhoneFirst">請先完成手機驗證</template>
            <template v-else>前往付款驗證</template>
          </button>
        </template>

        <p v-if="advancedError" class="verify-error">{{ advancedError }}</p>
      </div>
    </div>

    <!-- Advanced Photo Upload (Female) -->
    <div v-else-if="currentStep === 'advanced-photo'" class="verify-content">
      <div class="verify-step">
        <h2 class="verify-step__title">上傳驗證照片</h2>
        <p class="verify-step__desc">請拍攝手持證件及驗證碼 {{ photoCode }} 的自拍照</p>
        <!-- Hidden file inputs -->
        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden-input" @change="handleFileChange" />
        <input ref="cameraInput" type="file" accept="image/*" capture="user" class="hidden-input" @change="handleFileChange" />

        <!-- Preview or upload area -->
        <div v-if="photoUrl" class="verify-upload-preview">
          <img :src="photoUrl" alt="驗證照片預覽" class="verify-upload-preview__img" />
          <button class="verify-upload-preview__remove" @click="photoUrl = ''">重新選擇</button>
        </div>
        <div v-else class="verify-upload-actions">
          <button class="upload-btn upload-btn--gallery" :disabled="isUploading" @click="triggerFileUpload">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            {{ isUploading ? '上傳中…' : '選擇照片' }}
          </button>
          <button class="upload-btn upload-btn--camera" :disabled="isUploading" @click="triggerCamera">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            {{ isUploading ? '上傳中…' : '拍照上傳' }}
          </button>
        </div>
        <p v-if="advancedError" class="verify-error">{{ advancedError }}</p>
        <button
          class="verify-submit"
          :disabled="!photoUrl || advancedSubmitting"
          @click="submitAdvancedPhoto"
        >
          {{ advancedSubmitting ? '提交中…' : '提交驗證' }}
        </button>
        <p class="verify-note">審核通常在 24 小時內完成</p>
      </div>
    </div>

    <!-- Pending Review -->
    <div v-else-if="currentStep === 'advanced-pending'" class="verify-content">
      <div class="verify-step" style="text-align: center;">
        <div class="verify-pending-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
        </div>
        <h2 class="verify-step__title">照片已送出</h2>
        <p class="verify-step__desc">您的驗證照片正在審核中，通常在 24 小時內完成。審核通過後您將自動升級為 Lv1.5 驗證會員，誠信分數 +15。</p>
        <button class="verify-submit" style="background: #64748B;" @click="currentStep = 'overview'">
          返回
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.verify-view {
  background: #F9F9FB;
  min-height: 100dvh;
  padding-bottom: var(--app-bottom-inset);
}

/* ── TopBar ────────────────────────────────────────────────── */
.verify-topbar {
  display: flex;
  align-items: center;
  height: 56px;
  padding: 0 12px;
  background: #fff;
  border-bottom: 0.5px solid #E8ECF0;
  position: sticky;
  top: 0;
  z-index: 10;
}

.verify-topbar__back {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: #334155;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.verify-topbar__title {
  flex: 1;
  text-align: center;
  font-size: 16px;
  font-weight: 600;
  color: #0F172A;
}

.verify-topbar__placeholder {
  width: 40px;
}

/* ── Content ───────────────────────────────────────────────── */
.verify-content {
  padding: 20px 16px;
}

.verify-desc {
  font-size: 14px;
  color: #64748B;
  margin-bottom: 20px;
}

/* ── Verify Card ───────────────────────────────────────────── */
.verify-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #fff;
  border-radius: 14px;
  border: 1px solid #F1F5F9;
  margin-bottom: 10px;
}

.verify-card--done {
  border-color: #D1FAE5;
  background: #F0FDF4;
}

.verify-card__icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: #F8FAFC;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.verify-card--done .verify-card__icon {
  background: #DCFCE7;
}

.verify-card__info {
  flex: 1;
  min-width: 0;
}

.verify-card__title {
  font-size: 15px;
  font-weight: 600;
  color: #0F172A;
}

.verify-card__sub {
  font-size: 12px;
  color: #94A3B8;
  margin-top: 2px;
}

.verify-card__btn {
  height: 34px;
  padding: 0 16px;
  border-radius: 8px;
  border: none;
  background: #F0294E;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.15s;
}

.verify-card__btn:active {
  background: #D01A3C;
}

.verify-card__check {
  color: #22C55E;
  font-size: 18px;
  font-weight: 700;
  flex-shrink: 0;
}

/* ── Step Form ─────────────────────────────────────────────── */
.verify-step {
  max-width: 400px;
  margin: 0 auto;
}

.verify-step__title {
  font-size: 20px;
  font-weight: 700;
  color: #0F172A;
  margin-bottom: 8px;
}

.verify-step__desc {
  font-size: 14px;
  color: #64748B;
  line-height: 1.6;
  margin-bottom: 24px;
}

.verify-input-wrap {
  margin-bottom: 16px;
}

.verify-input {
  width: 100%;
  height: 52px;
  padding: 0 16px;
  border-radius: 10px;
  border: 1.5px solid #E2E8F0;
  font-size: 16px;
  color: #0F172A;
  background: #fff;
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
}

.verify-input:focus {
  border-color: #F0294E;
  box-shadow: 0 0 0 3px rgba(240,41,78,0.12);
}

.verify-input--code {
  text-align: center;
  font-size: 24px;
  font-weight: 700;
  letter-spacing: 8px;
}

/* PR-3: 顯示帳號既有手機(readonly) */
.verify-readonly-phone {
  width: 100%;
  height: 52px;
  padding: 0 16px;
  border-radius: 10px;
  background: #F1F5F9;
  border: 1.5px solid #E2E8F0;
  display: flex;
  align-items: center;
  font-size: 18px;
  font-weight: 600;
  color: #0F172A;
  letter-spacing: 1px;
}

.verify-error {
  color: #EF4444;
  font-size: 13px;
  margin-bottom: 12px;
}

.verify-submit {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  border: none;
  background: #F0294E;
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
  margin-bottom: 12px;
}

.verify-submit:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.verify-submit:not(:disabled):active {
  background: #D01A3C;
}

.verify-resend {
  width: 100%;
  height: 40px;
  border: none;
  background: transparent;
  color: #F0294E;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.verify-resend:disabled {
  color: #94A3B8;
  cursor: not-allowed;
}

/* ── Code Display ──────────────────────────────────────────── */
.verify-code-display {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20px;
  background: #FFF0F3;
  border-radius: 14px;
  border: 1.5px dashed #FECDD3;
  margin-bottom: 20px;
}

.verify-code-display__code {
  font-size: 36px;
  font-weight: 800;
  color: #F0294E;
  letter-spacing: 6px;
  font-family: 'Inter', monospace;
}

.verify-code-display__hint {
  font-size: 12px;
  color: #F0294E;
  margin-top: 6px;
  opacity: 0.7;
}

/* ── Rules ─────────────────────────────────────────────────── */
.verify-rules {
  list-style: none;
  padding: 0;
  margin: 0 0 24px;
}

.verify-rules li {
  font-size: 13px;
  color: #475569;
  line-height: 1.8;
  padding-left: 18px;
  position: relative;
}

.verify-rules li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 9px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #CBD5E1;
}

/* ── Upload ────────────────────────────────────────────────── */
.hidden-input { display: none; }

.verify-upload-actions {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.upload-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: opacity 0.15s;
  flex: 1;
  justify-content: center;
}
.upload-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.upload-btn--gallery {
  background: #F3F4F6;
  color: #374151;
  border: 1.5px solid #E5E7EB;
}
.upload-btn--gallery:hover:not(:disabled) { background: #E5E7EB; }

.upload-btn--camera {
  background: #F0294E;
  color: white;
}
.upload-btn--camera:hover:not(:disabled) { background: #D01A3C; }

.verify-upload-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}
.verify-upload-preview__img {
  max-width: 100%;
  max-height: 260px;
  border-radius: 12px;
  object-fit: contain;
  border: 1px solid #E2E8F0;
}
.verify-upload-preview__remove {
  background: none;
  border: none;
  color: #F0294E;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.verify-note {
  text-align: center;
  font-size: 12px;
  color: #94A3B8;
}

.verify-reject-notice {
  margin: -4px 16px 10px;
  padding: 8px 12px;
  background: #FEF2F2;
  border-radius: 8px;
  font-size: 12px;
  color: #991B1B;
}

.verify-pending-icon {
  margin-bottom: 16px;
}

.verify-card__tag {
  flex-shrink: 0;
  border-radius: 6px;
}

/* ── PR-1 (v3.6) 問題回報入口 + 登出 ─────────────────────── */
.verify-fallback-links {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 0.5px solid #E2E8F0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}

.verify-fallback-hint {
  font-size: 12px;
  color: #64748B;
  margin: 0;
  text-align: center;
}

.verify-link {
  background: none;
  border: none;
  color: #F0294E;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline;
  padding: 4px;
}

.verify-link:disabled {
  color: #94A3B8;
  cursor: not-allowed;
  text-decoration: none;
}

.verify-link--muted {
  color: #64748B;
  font-weight: 500;
}

.verify-inline-success {
  margin-bottom: 16px;
  padding: 12px 14px;
  background: #F0FDF4;
  border: 1px solid #BBF7D0;
  border-radius: 10px;
  color: #166534;
  font-size: 13px;
  line-height: 1.6;
}

/* ── PR-1 (v3.6) 回報 Modal ───────────────────────────── */
.report-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 16px;
}

.report-modal {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 16px;
  padding: 24px 20px;
}

.report-modal__title {
  font-size: 18px;
  font-weight: 700;
  color: #0F172A;
  margin: 0 0 8px;
}

.report-modal__hint {
  font-size: 12px;
  color: #64748B;
  background: #F1F5F9;
  border-radius: 8px;
  padding: 8px 12px;
  margin: 0 0 16px;
}

.report-modal__field {
  margin-bottom: 14px;
}

.report-modal__label {
  display: block;
  font-size: 12px;
  color: #475569;
  font-weight: 600;
  margin-bottom: 6px;
}

.report-modal__readonly {
  height: 40px;
  padding: 0 12px;
  display: flex;
  align-items: center;
  background: #F1F5F9;
  border-radius: 8px;
  font-size: 14px;
  color: #475569;
}

.report-modal__textarea {
  width: 100%;
  border: 1.5px solid #E2E8F0;
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 14px;
  color: #0F172A;
  font-family: inherit;
  outline: none;
  resize: vertical;
  box-sizing: border-box;
}

.report-modal__textarea:focus {
  border-color: #F0294E;
  box-shadow: 0 0 0 3px rgba(240, 41, 78, 0.1);
}

.report-modal__char-count {
  text-align: right;
  font-size: 11px;
  color: #94A3B8;
  margin-top: 4px;
}

.report-modal__error {
  margin: 0 0 12px;
  font-size: 12px;
  color: #EF4444;
}

.report-modal__actions {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}

.report-modal__btn {
  flex: 1;
  height: 40px;
  border-radius: 10px;
  border: none;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.report-modal__btn--cancel {
  background: #F1F5F9;
  color: #475569;
}

.report-modal__btn--submit {
  background: #F0294E;
  color: #fff;
}

.report-modal__btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* ── Tablet/Desktop: center content ──────────────────────── */
@media (min-width: 768px) {
  .verify-view { max-width: 560px; margin: 0 auto; }
}
</style>
