<script setup lang="ts">
import { ref, computed } from 'vue'
import TopBar from '@/components/layout/TopBar.vue'

// ── 狀態 ──────────────────────────────────────────────────
type ViewMode = 'form' | 'history' | 'success'
const viewMode = ref<ViewMode>('form')

// ── 回報表單 ──────────────────────────────────────────────
const REPORT_TYPES = [
  { value: 1, label: '一般檢舉', desc: '檢舉其他用戶違規行為' },
  { value: 2, label: '系統問題', desc: '回報網站功能問題' },
  { value: 3, label: '匿名聊天檢舉', desc: '針對匿名聊天室違規' },
]

const REASONS: Record<number, { value: number; label: string }[]> = {
  1: [
    { value: 1, label: '假照片 / 冒充他人' },
    { value: 2, label: '騷擾性訊息' },
    { value: 3, label: '詐騙行為' },
    { value: 4, label: '不當內容' },
    { value: 5, label: '其他違規' },
  ],
  2: [
    { value: 10, label: '頁面無法載入' },
    { value: 11, label: '功能異常' },
    { value: 12, label: '付款問題' },
    { value: 13, label: '其他問題' },
  ],
  3: [
    { value: 20, label: '不當言論' },
    { value: 21, label: '騷擾行為' },
    { value: 22, label: '洗版' },
  ],
}

const reportType = ref<number>(1)
const reportReason = ref('')
const reportTitle = ref('')
const reportContent = ref('')
const isSubmitting = ref(false)
const ticketNumber = ref('')

const currentReasons = computed(() => REASONS[reportType.value] ?? [])
const contentLength = computed(() => reportContent.value.length)
const canSubmit = computed(() =>
  reportType.value &&
  reportReason.value !== '' &&
  reportTitle.value.trim().length > 0 &&
  reportContent.value.trim().length >= 10
)

function onTypeChange() {
  reportReason.value = ''
}

async function handleSubmit() {
  if (!canSubmit.value || isSubmitting.value) return
  isSubmitting.value = true
  await new Promise(r => setTimeout(r, 800))
  ticketNumber.value = `R${new Date().toISOString().slice(0,10).replace(/-/g,'')}${String(Math.floor(Math.random() * 99999)).padStart(5, '0')}`
  viewMode.value = 'success'
  isSubmitting.value = false
}

function resetForm() {
  reportType.value = 1
  reportReason.value = ''
  reportTitle.value = ''
  reportContent.value = ''
  viewMode.value = 'form'
}

// ── 歷史紀錄 ──────────────────────────────────────────────
interface HistoryItem {
  id: number
  ticketNumber: string
  type: number
  typeLabel: string
  title: string
  status: number
  statusLabel: string
  createdAt: string
  adminReply: string | null
}

const historyItems = ref<HistoryItem[]>([
  { id: 1, ticketNumber: 'R2026040100001', type: 1, typeLabel: '一般檢舉', title: '對方傳送騷擾訊息', status: 3, statusLabel: '已處理', createdAt: '2026-03-28T10:30:00Z', adminReply: '經查證屬實，已對違規用戶進行處理' },
  { id: 2, ticketNumber: 'R2026040200002', type: 2, typeLabel: '系統問題', title: '訊息頁面無法載入', status: 2, statusLabel: '處理中', createdAt: '2026-04-02T14:00:00Z', adminReply: null },
])

function statusColor(status: number): string {
  if (status === 3) return '#10B981'
  if (status === 2) return '#F59E0B'
  return '#9CA3AF'
}
</script>

<template>
  <div class="reports-view">
    <TopBar title="問題回報" show-back>
      <template #right>
        <button
          class="mode-toggle"
          @click="viewMode = viewMode === 'history' ? 'form' : 'history'"
        >
          {{ viewMode === 'history' ? '新回報' : '歷史紀錄' }}
        </button>
      </template>
    </TopBar>

    <div class="reports-body">
      <!-- ── 回報表單 ─────────────────────────────────────── -->
      <template v-if="viewMode === 'form'">
        <!-- 回報類型 -->
        <div class="form-group">
          <label class="form-label">回報類型</label>
          <div class="type-chips">
            <button
              v-for="t in REPORT_TYPES"
              :key="t.value"
              class="type-chip"
              :class="{ 'type-chip--active': reportType === t.value }"
              @click="reportType = t.value; onTypeChange()"
            >
              {{ t.label }}
            </button>
          </div>
        </div>

        <!-- 原因 -->
        <div class="form-group">
          <label class="form-label">具體原因</label>
          <select v-model="reportReason" class="form-select">
            <option value="" disabled>請選擇</option>
            <option v-for="r in currentReasons" :key="r.value" :value="String(r.value)">
              {{ r.label }}
            </option>
          </select>
        </div>

        <!-- 標題 -->
        <div class="form-group">
          <label class="form-label">標題</label>
          <input
            v-model="reportTitle"
            type="text"
            class="form-input"
            placeholder="簡述問題"
            maxlength="50"
          />
        </div>

        <!-- 內容 -->
        <div class="form-group">
          <label class="form-label">詳細說明</label>
          <textarea
            v-model="reportContent"
            class="form-textarea"
            placeholder="請描述問題細節（至少 10 字）"
            maxlength="500"
            rows="5"
          />
          <span class="form-counter">{{ contentLength }} / 500</span>
        </div>

        <button
          class="submit-btn"
          :disabled="!canSubmit || isSubmitting"
          @click="handleSubmit"
        >
          {{ isSubmitting ? '送出中…' : '送出回報' }}
        </button>
      </template>

      <!-- ── 送出成功 ─────────────────────────────────────── -->
      <template v-if="viewMode === 'success'">
        <div class="success-card">
          <div class="success-card__icon">📋</div>
          <h2 class="success-card__title">回報已送出</h2>
          <p class="success-card__ticket">案號：{{ ticketNumber }}</p>
          <p class="success-card__text">我們將在 1-3 個工作天內回覆，請至「歷史紀錄」查看進度。</p>
          <button class="primary-btn" @click="resetForm">再次回報</button>
          <button class="ghost-btn" @click="viewMode = 'history'">查看歷史紀錄</button>
        </div>
      </template>

      <!-- ── 歷史紀錄 ─────────────────────────────────────── -->
      <template v-if="viewMode === 'history'">
        <div v-if="historyItems.length === 0" class="empty-state">
          <p>尚無回報紀錄</p>
        </div>

        <div
          v-for="item in historyItems"
          :key="item.id"
          class="history-card"
        >
          <div class="history-card__header">
            <span class="history-card__type">{{ item.typeLabel }}</span>
            <span class="history-card__status" :style="{ color: statusColor(item.status) }">
              {{ item.statusLabel }}
            </span>
          </div>
          <h3 class="history-card__title">{{ item.title }}</h3>
          <p class="history-card__ticket">{{ item.ticketNumber }}</p>
          <p class="history-card__date">
            {{ new Date(item.createdAt).toLocaleDateString('zh-TW', { month: 'short', day: 'numeric' }) }}
          </p>
          <div v-if="item.adminReply" class="history-card__reply">
            <span class="history-card__reply-label">管理員回覆：</span>
            {{ item.adminReply }}
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.reports-view {
  display: flex;
  flex-direction: column;
  flex: 1;
  background: #F9F9FB;
}

.reports-body {
  flex: 1;
  padding: 16px;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

/* ── Mode Toggle ─────────────────────────────────────────── */
.mode-toggle {
  background: none;
  border: none;
  font-size: 13px;
  font-weight: 600;
  color: #F0294E;
  cursor: pointer;
  padding: 4px 8px;
}

/* ── Form ────────────────────────────────────────────────── */
.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; }

.type-chips { display: flex; gap: 8px; }

.type-chip {
  height: 34px;
  padding: 0 14px;
  border-radius: 9999px;
  border: 1.5px solid #E5E7EB;
  background: #fff;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}

.type-chip--active {
  border-color: #F0294E;
  background: #FFF5F7;
  color: #F0294E;
}

.form-select {
  width: 100%;
  height: 48px;
  border: 1.5px solid #E5E7EB;
  border-radius: 10px;
  padding: 0 36px 0 16px;
  font-size: 15px;
  color: #111827;
  background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239CA3AF' stroke-width='2.5' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 12px center;
  outline: none;
  appearance: none;
  -webkit-appearance: none;
  box-sizing: border-box;
}

.form-select:focus { border-color: #F0294E; }

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

.form-textarea {
  width: 100%;
  border: 1.5px solid #E5E7EB;
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 15px;
  color: #111827;
  background: #fff;
  outline: none;
  resize: vertical;
  min-height: 120px;
  box-sizing: border-box;
  font-family: inherit;
}

.form-textarea:focus { border-color: #F0294E; box-shadow: 0 0 0 3px rgba(240,41,78,0.12); }

.form-counter {
  display: block;
  text-align: right;
  font-size: 12px;
  color: #9CA3AF;
  margin-top: 4px;
}

.submit-btn {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  border: none;
  background: #F0294E;
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.submit-btn:active { transform: scale(0.97); background: #D01A3C; }
.submit-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Success ─────────────────────────────────────────────── */
.success-card {
  text-align: center;
  padding: 40px 16px;
}

.success-card__icon { font-size: 48px; margin-bottom: 16px; }
.success-card__title { font-size: 20px; font-weight: 700; color: #111827; }
.success-card__ticket { font-size: 14px; color: #F0294E; font-weight: 600; margin-top: 8px; }
.success-card__text { font-size: 14px; color: #6B7280; margin: 8px 0 24px; line-height: 1.5; }

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
  margin-bottom: 12px;
}

.ghost-btn {
  width: 100%;
  height: 40px;
  background: none;
  border: none;
  color: #6B7280;
  font-size: 14px;
  cursor: pointer;
}

/* ── History ─────────────────────────────────────────────── */
.empty-state {
  text-align: center;
  padding: 48px 0;
  color: #9CA3AF;
  font-size: 14px;
}

.history-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #F1F5F9;
  padding: 16px;
  margin-bottom: 12px;
}

.history-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.history-card__type {
  font-size: 11px;
  font-weight: 600;
  color: #6B7280;
  background: #F3F4F6;
  padding: 2px 8px;
  border-radius: 6px;
}

.history-card__status {
  font-size: 12px;
  font-weight: 700;
}

.history-card__title {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.history-card__ticket {
  font-size: 12px;
  color: #9CA3AF;
  margin-top: 4px;
}

.history-card__date {
  font-size: 12px;
  color: #9CA3AF;
}

.history-card__reply {
  margin-top: 10px;
  padding: 10px 12px;
  background: #F0FDF4;
  border-radius: 8px;
  font-size: 13px;
  color: #065F46;
  line-height: 1.4;
}

.history-card__reply-label {
  font-weight: 600;
}
</style>
