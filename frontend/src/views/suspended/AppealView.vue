<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { submitAppeal } from '@/api/appeals'

const router = useRouter()

// ── 狀態 ──────────────────────────────────────────────────
type Step = 'form' | 'submitting' | 'success'
const step = ref<Step>('form')

const reason = ref('')
const evidence = ref('')
const ticketNumber = ref('')
const submitError = ref<string | null>(null)

const REASON_MAX = 500
const EVIDENCE_MAX = 300

const reasonCount = computed(() => reason.value.length)
const evidenceCount = computed(() => evidence.value.length)
const canSubmit = computed(() =>
  reason.value.trim().length > 0 &&
  reason.value.length <= REASON_MAX &&
  evidence.value.length <= EVIDENCE_MAX
)

// ── 送出 ──────────────────────────────────────────────────
async function handleSubmit() {
  if (!canSubmit.value) return
  step.value = 'submitting'
  submitError.value = null
  try {
    const res = await submitAppeal({
      reason: reason.value.trim(),
      evidence: evidence.value.trim() || undefined,
    })
    ticketNumber.value = res.ticket_number
    step.value = 'success'
  } catch {
    submitError.value = '送出失敗，請稍後再試'
    step.value = 'form'
  }
}

function goBack() {
  router.push('/suspended')
}
</script>

<template>
  <div class="appeal-view">
    <!-- TopBar -->
    <header class="appeal-topbar">
      <button class="appeal-topbar__back" @click="goBack" aria-label="返回">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </button>
      <h1 class="appeal-topbar__title">申訴</h1>
      <div class="appeal-topbar__placeholder" />
    </header>

    <!-- Form -->
    <div v-if="step === 'form' || step === 'submitting'" class="appeal-content">
      <div class="appeal-notice">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        <p>提交後將由專人審核，處理時間約 3-5 個工作天。同一停權期間僅能提交一次申訴。</p>
      </div>

      <!-- 申訴原因 -->
      <div class="appeal-field">
        <label class="appeal-field__label">
          申訴原因
          <span class="appeal-field__required">*</span>
        </label>
        <textarea
          v-model="reason"
          class="appeal-field__textarea"
          :class="{ 'appeal-field__textarea--error': reasonCount > REASON_MAX }"
          placeholder="請詳細說明您認為停權決定有誤的原因…"
          rows="6"
          :maxlength="REASON_MAX + 50"
        />
        <div class="appeal-field__footer">
          <span v-if="reasonCount > REASON_MAX" class="appeal-field__error">超過字數上限</span>
          <span class="appeal-field__count" :class="{ 'appeal-field__count--over': reasonCount > REASON_MAX }">
            {{ reasonCount }} / {{ REASON_MAX }}
          </span>
        </div>
      </div>

      <!-- 佐證說明 -->
      <div class="appeal-field">
        <label class="appeal-field__label">佐證說明（選填）</label>
        <textarea
          v-model="evidence"
          class="appeal-field__textarea"
          :class="{ 'appeal-field__textarea--error': evidenceCount > EVIDENCE_MAX }"
          placeholder="如有其他佐證資料或補充說明，請在此填寫…"
          rows="4"
          :maxlength="EVIDENCE_MAX + 50"
        />
        <div class="appeal-field__footer">
          <span v-if="evidenceCount > EVIDENCE_MAX" class="appeal-field__error">超過字數上限</span>
          <span class="appeal-field__count" :class="{ 'appeal-field__count--over': evidenceCount > EVIDENCE_MAX }">
            {{ evidenceCount }} / {{ EVIDENCE_MAX }}
          </span>
        </div>
      </div>

      <!-- 上傳圖片 -->
      <div class="appeal-field">
        <label class="appeal-field__label">佐證圖片（選填，最多 3 張）</label>
        <div class="appeal-upload">
          <div class="appeal-upload__slot">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span>上傳</span>
          </div>
        </div>
      </div>

      <!-- 錯誤訊息 -->
      <p v-if="submitError" class="appeal-error">{{ submitError }}</p>

      <!-- 送出按鈕 -->
      <button
        class="appeal-submit"
        :disabled="!canSubmit || step === 'submitting'"
        @click="handleSubmit"
      >
        <span v-if="step === 'submitting'" class="appeal-submit__spinner" />
        {{ step === 'submitting' ? '送出中…' : '送出申訴' }}
      </button>
    </div>

    <!-- Success -->
    <div v-else-if="step === 'success'" class="appeal-success">
      <div class="appeal-success__icon">
        <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
          <circle cx="24" cy="24" r="22" fill="#D1FAE5" stroke="#22C55E" stroke-width="2"/>
          <path d="M14 24l7 7 13-13" stroke="#22C55E" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="appeal-success__check"/>
        </svg>
      </div>
      <h2 class="appeal-success__title">申訴已送出</h2>
      <p class="appeal-success__desc">我們將在 3-5 個工作天內回覆</p>
      <div class="appeal-success__ticket">
        <span class="appeal-success__ticket-label">案號</span>
        <span class="appeal-success__ticket-number">{{ ticketNumber }}</span>
      </div>
      <button class="appeal-success__btn" @click="goBack">返回</button>
    </div>
  </div>
</template>

<style scoped>
.appeal-view {
  background: #F9F9FB;
  min-height: 100dvh;
}

/* ── TopBar ────────────────────────────────────────────────── */
.appeal-topbar {
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

.appeal-topbar__back {
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

.appeal-topbar__title {
  flex: 1;
  text-align: center;
  font-size: 16px;
  font-weight: 600;
  color: #0F172A;
}

.appeal-topbar__placeholder { width: 40px; }

/* ── Content ───────────────────────────────────────────────── */
.appeal-content {
  padding: 20px 16px 40px;
  max-width: 480px;
  margin: 0 auto;
}

/* ── Notice ────────────────────────────────────────────────── */
.appeal-notice {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  background: #EFF6FF;
  border: 1px solid #BFDBFE;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 24px;
}

.appeal-notice svg { flex-shrink: 0; margin-top: 1px; }

.appeal-notice p {
  font-size: 12px;
  color: #1E40AF;
  line-height: 1.6;
  margin: 0;
}

/* ── Field ─────────────────────────────────────────────────── */
.appeal-field {
  margin-bottom: 20px;
}

.appeal-field__label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #1E293B;
  margin-bottom: 8px;
}

.appeal-field__required {
  color: #EF4444;
  margin-left: 2px;
}

.appeal-field__textarea {
  width: 100%;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1.5px solid #E2E8F0;
  font-size: 14px;
  color: #0F172A;
  background: #fff;
  outline: none;
  resize: vertical;
  line-height: 1.6;
  font-family: inherit;
  box-sizing: border-box;
  transition: border-color 0.15s;
}

.appeal-field__textarea:focus {
  border-color: #F0294E;
  box-shadow: 0 0 0 3px rgba(240,41,78,0.12);
}

.appeal-field__textarea--error {
  border-color: #EF4444;
}

.appeal-field__footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
}

.appeal-field__count {
  font-size: 12px;
  color: #94A3B8;
  font-variant-numeric: tabular-nums;
}

.appeal-field__count--over {
  color: #EF4444;
  font-weight: 600;
}

.appeal-field__error {
  font-size: 12px;
  color: #EF4444;
}

/* ── Upload ────────────────────────────────────────────────── */
.appeal-upload {
  display: flex;
  gap: 8px;
}

.appeal-upload__slot {
  width: 80px;
  height: 80px;
  border: 2px dashed #E2E8F0;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  cursor: pointer;
  transition: border-color 0.15s;
  font-size: 11px;
  color: #94A3B8;
}

.appeal-upload__slot:hover {
  border-color: #F0294E;
  color: #F0294E;
}

/* ── Error ─────────────────────────────────────────────────── */
.appeal-error {
  color: #EF4444;
  font-size: 13px;
  margin-bottom: 12px;
}

/* ── Submit ────────────────────────────────────────────────── */
.appeal-submit {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  border: none;
  background: #F0294E;
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background 0.15s;
}

.appeal-submit:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.appeal-submit:not(:disabled):active {
  background: #D01A3C;
}

.appeal-submit__spinner {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* ── Success ───────────────────────────────────────────────── */
.appeal-success {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80px 24px;
  text-align: center;
}

.appeal-success__icon {
  margin-bottom: 20px;
}

.appeal-success__check {
  stroke-dasharray: 40;
  stroke-dashoffset: 40;
  animation: draw-check 0.5s 0.3s ease forwards;
}

@keyframes draw-check {
  to { stroke-dashoffset: 0; }
}

.appeal-success__title {
  font-size: 20px;
  font-weight: 700;
  color: #0F172A;
  margin-bottom: 6px;
}

.appeal-success__desc {
  font-size: 14px;
  color: #64748B;
  margin-bottom: 24px;
}

.appeal-success__ticket {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 10px;
  padding: 14px 24px;
  margin-bottom: 28px;
}

.appeal-success__ticket-label {
  font-size: 11px;
  color: #94A3B8;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.appeal-success__ticket-number {
  font-size: 16px;
  font-weight: 700;
  color: #0F172A;
  font-family: 'Inter', monospace;
  letter-spacing: 0.5px;
}

.appeal-success__btn {
  width: 200px;
  height: 44px;
  border-radius: 10px;
  border: 1.5px solid #E2E8F0;
  background: #fff;
  font-size: 14px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
}

.appeal-success__btn:active {
  background: #F8FAFC;
}
</style>
