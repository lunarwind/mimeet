<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import TopBar from '@/components/layout/TopBar.vue'

const router = useRouter()
const authStore = useAuthStore()

// ── 狀態 ──────────────────────────────────────────────────
type ViewState = 'confirm' | 'pending' | 'cancelled'
const viewState = ref<ViewState>('confirm')
const password = ref('')
const reason = ref('')
const isSubmitting = ref(false)
const error = ref<string | null>(null)
const scheduledDate = ref<string | null>(null)

const REASONS = [
  { value: 'no_longer_needed', label: '不再需要此服務' },
  { value: 'privacy_concern', label: '隱私考量' },
  { value: 'bad_experience', label: '使用體驗不佳' },
  { value: 'found_partner', label: '已找到對象' },
  { value: 'other', label: '其他原因' },
]

const canSubmit = computed(() => password.value.length >= 8 && reason.value)

async function handleDelete() {
  if (!canSubmit.value || isSubmitting.value) return
  isSubmitting.value = true
  error.value = null

  // Mock: 模擬 API 呼叫
  await new Promise(r => setTimeout(r, 800))

  if (password.value === 'wrong') {
    error.value = '密碼錯誤，請重新輸入'
    isSubmitting.value = false
    return
  }

  const deleteDate = new Date(Date.now() + 7 * 86400000)
  scheduledDate.value = deleteDate.toLocaleDateString('zh-TW', {
    year: 'numeric', month: 'long', day: 'numeric',
  })
  viewState.value = 'pending'
  isSubmitting.value = false
}

async function handleCancelDeletion() {
  isSubmitting.value = true
  await new Promise(r => setTimeout(r, 500))
  viewState.value = 'cancelled'
  isSubmitting.value = false
}

function goBack() {
  router.push({ name: 'settings' })
}
</script>

<template>
  <div class="delete-view">
    <TopBar title="刪除帳號" show-back />

    <div class="delete-body">
      <!-- 確認刪除表單 -->
      <template v-if="viewState === 'confirm'">
        <div class="warning-banner">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
          <div>
            <p class="warning-banner__title">注意：此操作不可逆</p>
            <p class="warning-banner__text">
              提交後將進入 <strong>7 天冷靜期</strong>，期間重新登入可取消。
              冷靜期結束後帳號及所有資料將永久刪除。
            </p>
          </div>
        </div>

        <!-- 刪除原因 -->
        <div class="form-group">
          <label class="form-label">刪除原因</label>
          <div class="reason-list">
            <label
              v-for="r in REASONS"
              :key="r.value"
              class="reason-option"
              :class="{ 'reason-option--active': reason === r.value }"
            >
              <input type="radio" v-model="reason" :value="r.value" class="sr-only" />
              <span class="reason-option__radio" :class="{ 'reason-option__radio--checked': reason === r.value }" />
              <span>{{ r.label }}</span>
            </label>
          </div>
        </div>

        <!-- 密碼確認 -->
        <div class="form-group">
          <label class="form-label">請輸入密碼確認</label>
          <input
            v-model="password"
            type="password"
            class="form-input"
            placeholder="請輸入目前密碼"
            autocomplete="current-password"
          />
          <p v-if="error" class="form-error">{{ error }}</p>
        </div>

        <button
          class="delete-btn"
          :disabled="!canSubmit || isSubmitting"
          @click="handleDelete"
        >
          {{ isSubmitting ? '處理中…' : '確認刪除帳號' }}
        </button>
      </template>

      <!-- 冷靜期等待中 -->
      <template v-if="viewState === 'pending'">
        <div class="result-card">
          <div class="result-card__icon result-card__icon--warning">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <h2 class="result-card__title">帳號刪除申請已送出</h2>
          <p class="result-card__text">
            您的帳號將於 <strong>{{ scheduledDate }}</strong> 永久刪除。<br />
            在此之前，您可以隨時取消。
          </p>
          <button
            class="cancel-btn"
            :disabled="isSubmitting"
            @click="handleCancelDeletion"
          >
            {{ isSubmitting ? '處理中…' : '取消刪除申請' }}
          </button>
          <button class="ghost-btn" @click="goBack">返回設定</button>
        </div>
      </template>

      <!-- 已取消 -->
      <template v-if="viewState === 'cancelled'">
        <div class="result-card">
          <div class="result-card__icon result-card__icon--success">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
          </div>
          <h2 class="result-card__title">刪除申請已取消</h2>
          <p class="result-card__text">您的帳號已恢復正常，感謝您繼續使用 MiMeet。</p>
          <button class="primary-btn" @click="goBack">返回設定</button>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.delete-view {
  display: flex;
  flex-direction: column;
  flex: 1;
  background: #F9F9FB;
}

.delete-body {
  flex: 1;
  padding: 16px;
}

/* ── Warning Banner ──────────────────────────────────────── */
.warning-banner {
  display: flex;
  gap: 12px;
  background: #FEF2F2;
  border: 1px solid #FECACA;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 24px;
}

.warning-banner svg { flex-shrink: 0; margin-top: 2px; }
.warning-banner__title { font-size: 14px; font-weight: 700; color: #991B1B; }
.warning-banner__text { font-size: 13px; color: #991B1B; margin-top: 4px; line-height: 1.5; }

/* ── Form ────────────────────────────────────────────────── */
.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; }

.form-input {
  width: 100%;
  height: 48px;
  border: 1.5px solid #E5E7EB;
  border-radius: 10px;
  padding: 0 16px;
  font-size: 15px;
  color: #111827;
  background: #fff;
  outline: none;
  box-sizing: border-box;
}

.form-input:focus { border-color: #F0294E; box-shadow: 0 0 0 3px rgba(240,41,78,0.12); }
.form-error { font-size: 13px; color: #EF4444; margin-top: 6px; }

/* ── Reason Radio ────────────────────────────────────────── */
.reason-list { display: flex; flex-direction: column; gap: 8px; }

.reason-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background: #fff;
  border: 1.5px solid #E5E7EB;
  border-radius: 10px;
  cursor: pointer;
  font-size: 14px;
  color: #374151;
  transition: all 0.15s;
}

.reason-option--active { border-color: #F0294E; background: #FFF5F7; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }

.reason-option__radio {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid #D1D5DB;
  flex-shrink: 0;
  position: relative;
}

.reason-option__radio--checked {
  border-color: #F0294E;
}

.reason-option__radio--checked::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #F0294E;
}

/* ── Buttons ─────────────────────────────────────────────── */
.delete-btn {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  border: none;
  background: #DC2626;
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.delete-btn:active { transform: scale(0.97); background: #B91C1C; }
.delete-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.cancel-btn {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  border: 1.5px solid #F0294E;
  background: #fff;
  color: #F0294E;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  margin-bottom: 12px;
}

.cancel-btn:active { background: #FFF5F7; }
.cancel-btn:disabled { opacity: 0.5; }

.primary-btn {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  border: none;
  background: #F0294E;
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
}

.primary-btn:active { transform: scale(0.97); }

.ghost-btn {
  width: 100%;
  height: 40px;
  background: none;
  border: none;
  color: #6B7280;
  font-size: 14px;
  cursor: pointer;
}

/* ── Result Card ─────────────────────────────────────────── */
.result-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 48px 16px;
}

.result-card__icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}

.result-card__icon--warning { background: #FEF3C7; color: #F59E0B; }
.result-card__icon--success { background: #D1FAE5; color: #10B981; }

.result-card__title {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 8px;
}

.result-card__text {
  font-size: 14px;
  color: #6B7280;
  line-height: 1.6;
  margin-bottom: 32px;
}
</style>
