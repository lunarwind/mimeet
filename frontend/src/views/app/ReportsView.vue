<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import { createReport } from '@/api/reports'
import { useImageUpload } from '@/composables/useImageUpload'
import { useUiStore } from '@/stores/ui'

const router = useRouter()
const route = useRoute()
const uiStore = useUiStore()
const { isUploading, uploadReport, error: uploadError } = useImageUpload()

const REPORT_TYPES = [
  { value: 1, label: '騷擾或不當訊息', icon: '💬' },
  { value: 2, label: '假冒身份', icon: '🎭' },
  { value: 3, label: '詐騙行為', icon: '⚠️' },
  { value: 4, label: '不雅照片或內容', icon: '🚫' },
  { value: 5, label: '其他', icon: '📝' },
]

const selectedType = ref<number | null>(null)
const reportedUserId = ref<number | undefined>(
  route.query.userId ? Number(route.query.userId) : undefined
)
const content = ref('')
const images = ref<string[]>([])
const isSubmitting = ref(false)
const showSuccess = ref(false)
const ticketNumber = ref('')

const contentLength = computed(() => content.value.length)
const canSubmit = computed(() =>
  selectedType.value !== null && content.value.trim().length > 0 && !isSubmitting.value
)

// ── 截圖上傳 ──
const imageInput = ref<HTMLInputElement | null>(null)

function triggerImageUpload() {
  if (images.value.length >= 3) {
    uiStore.showToast('最多上傳 3 張截圖', 'warning')
    return
  }
  imageInput.value?.click()
}

async function handleImageChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const result = await uploadReport(file)
  if (result) {
    images.value.push(result.url)
  } else if (uploadError.value) {
    uiStore.showToast(uploadError.value, 'error')
  }
  // reset input
  if (imageInput.value) imageInput.value.value = ''
}

function removeImage(index: number) {
  images.value.splice(index, 1)
}

// ── 送出 ──
async function handleSubmit() {
  if (!canSubmit.value || selectedType.value === null) return
  isSubmitting.value = true
  try {
    const result = await createReport({
      type: selectedType.value,
      reportedUserId: reportedUserId.value,
      title: REPORT_TYPES.find(t => t.value === selectedType.value)?.label || '回報',
      content: content.value,
      images: images.value,
    })
    ticketNumber.value = result.ticketNumber
    showSuccess.value = true
  } catch {
    uiStore.showToast('送出失敗，請稍後再試', 'error')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <AppLayout title="回報問題">
    <template #topbar-right>
      <button
        class="history-link"
        @click="router.push('/app/reports/history')"
      >
        歷史紀錄
      </button>
    </template>

    <div class="report-page">
      <!-- 成功畫面 -->
      <div v-if="showSuccess" class="report-success">
        <div class="report-success__icon">✅</div>
        <div class="report-success__title">回報已送出</div>
        <div class="report-success__ticket">案號：{{ ticketNumber }}</div>
        <p class="report-success__desc">感謝您的回報，我們將於 3 個工作天內處理</p>
        <button class="btn-primary btn-full" @click="router.back()">返回</button>
      </div>

      <!-- 回報表單 -->
      <template v-else>
        <!-- 類型選擇 -->
        <section class="report-section">
          <h3 class="report-section__title">回報類型</h3>
          <div class="type-cards">
            <div
              v-for="type in REPORT_TYPES"
              :key="type.value"
              class="type-card"
              :class="{ 'type-card--active': selectedType === type.value }"
              @click="selectedType = type.value"
            >
              <span class="type-card__icon">{{ type.icon }}</span>
              <span class="type-card__label">{{ type.label }}</span>
            </div>
          </div>
        </section>

        <!-- 被回報對象 -->
        <section v-if="reportedUserId" class="report-section">
          <h3 class="report-section__title">被回報對象</h3>
          <div class="reported-user">用戶 ID: {{ reportedUserId }}</div>
        </section>

        <!-- 詳細描述 -->
        <section class="report-section">
          <h3 class="report-section__title">詳細描述</h3>
          <textarea
            v-model="content"
            class="report-textarea"
            maxlength="500"
            rows="5"
            placeholder="請詳細描述問題情況（必填）"
          />
          <span class="report-counter">{{ contentLength }} / 500</span>
        </section>

        <!-- 截圖上傳 -->
        <section class="report-section">
          <h3 class="report-section__title">截圖佐證（選填，最多 3 張）</h3>
          <div class="image-row">
            <div v-for="(img, i) in images" :key="i" class="image-thumb">
              <img :src="img" alt="截圖" />
              <button class="image-remove" @click="removeImage(i)">×</button>
            </div>
            <div
              v-if="images.length < 3"
              class="image-add"
              @click="triggerImageUpload"
            >
              <div v-if="isUploading" class="spinner-sm" />
              <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </div>
          </div>
          <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden-input" @change="handleImageChange" />
        </section>

        <!-- 送出 -->
        <button
          class="btn-primary btn-full btn-lg"
          :disabled="!canSubmit"
          @click="handleSubmit"
        >
          {{ isSubmitting ? '送出中...' : '送出回報' }}
        </button>
      </template>
    </div>
  </AppLayout>
</template>

<style>
.report-page { padding: 16px; }
.hidden-input { display: none; }

.history-link { padding: 6px 12px; border-radius: 8px; border: none; background: #FFF5F7; color: #F0294E; font-size: 13px; font-weight: 600; cursor: pointer; }

/* ── Success ── */
.report-success { text-align: center; padding: 48px 16px; }
.report-success__icon { font-size: 48px; margin-bottom: 12px; }
.report-success__title { font-size: 20px; font-weight: 700; color: #111827; }
.report-success__ticket { font-size: 14px; font-weight: 600; color: #F0294E; margin: 8px 0; background: #FFF5F7; display: inline-block; padding: 4px 12px; border-radius: 8px; }
.report-success__desc { font-size: 14px; color: #6B7280; margin: 8px 0 24px; }

/* ── Section ── */
.report-section { margin-bottom: 20px; }
.report-section__title { font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 10px; }

/* ── Type Cards ── */
.type-cards { display: flex; flex-direction: column; gap: 8px; }
.type-card { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px; border: 1.5px solid #E5E7EB; background: white; cursor: pointer; transition: border-color 0.15s; }
.type-card:active { transform: scale(0.99); }
.type-card--active { border-color: #F0294E; background: #FFF5F7; }
.type-card__icon { font-size: 20px; }
.type-card__label { font-size: 14px; font-weight: 500; color: #374151; }
.type-card--active .type-card__label { color: #F0294E; font-weight: 600; }

.reported-user { font-size: 14px; color: #6B7280; background: #F3F4F6; padding: 10px 16px; border-radius: 10px; }

/* ── Textarea ── */
.report-textarea { width: 100%; border: 1.5px solid #E5E7EB; border-radius: 10px; padding: 12px 16px; font-size: 15px; color: #111827; resize: none; font-family: inherit; }
.report-textarea:focus { outline: none; border-color: #F0294E; box-shadow: 0 0 0 3px rgba(240,41,78,0.12); }
.report-counter { display: block; text-align: right; font-size: 12px; color: #9CA3AF; margin-top: 4px; }

/* ── Images ── */
.image-row { display: flex; gap: 8px; flex-wrap: wrap; }
.image-thumb { width: 80px; height: 80px; border-radius: 10px; overflow: hidden; position: relative; }
.image-thumb img { width: 100%; height: 100%; object-fit: cover; }
.image-remove { position: absolute; top: 4px; right: 4px; width: 20px; height: 20px; border-radius: 50%; background: rgba(0,0,0,0.6); color: white; border: none; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.image-add { width: 80px; height: 80px; border-radius: 10px; border: 2px dashed #D1D5DB; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.image-add:hover { border-color: #F0294E; }
.spinner-sm { width: 20px; height: 20px; border: 2px solid #E5E7EB; border-top-color: #F0294E; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Buttons ── */
.btn-primary { padding: 12px; border-radius: 10px; border: none; background: #F0294E; color: white; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-primary:hover { background: #D01A3C; }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-full { width: 100%; }
.btn-lg { padding: 14px; font-size: 16px; }
</style>
