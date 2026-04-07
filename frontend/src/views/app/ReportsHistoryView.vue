<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import { fetchReportHistory, type ReportRecord } from '@/api/reports'

const reports = ref<ReportRecord[]>([])
const isLoading = ref(false)

onMounted(async () => {
  isLoading.value = true
  try {
    reports.value = await fetchReportHistory()
  } catch {
    console.error('Failed to load report history')
  } finally {
    isLoading.value = false
  }
})

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('zh-TW')
}
</script>

<template>
  <AppLayout title="回報歷史" :show-back="true">
    <div class="history-page">
      <!-- Loading -->
      <div v-if="isLoading" class="history-loading">
        <div class="spinner" />
      </div>

      <!-- Empty -->
      <div v-else-if="reports.length === 0" class="history-empty">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
        </svg>
        <div class="history-empty__title">目前沒有回報紀錄</div>
      </div>

      <!-- List -->
      <div v-else class="history-list">
        <div
          v-for="report in reports"
          :key="report.id"
          class="history-item"
        >
          <div class="history-item__header">
            <span class="history-item__ticket">{{ report.ticketNumber }}</span>
            <span
              class="history-item__status"
              :class="{
                'history-item__status--pending': report.status === 1,
                'history-item__status--done': report.status === 2,
              }"
            >
              {{ report.statusLabel }}
            </span>
          </div>
          <div class="history-item__type">{{ report.typeLabel }}</div>
          <div class="history-item__title">{{ report.title }}</div>
          <div class="history-item__date">提交於 {{ formatDate(report.createdAt) }}</div>
          <div v-if="report.adminReply" class="history-item__reply">
            <span class="history-item__reply-label">管理員回覆：</span>
            {{ report.adminReply }}
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style>
.history-page { padding: 16px; }

.history-loading { display: flex; justify-content: center; padding: 48px 0; }
.spinner { width: 24px; height: 24px; border: 3px solid #E5E7EB; border-top-color: #F0294E; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.history-empty { text-align: center; padding: 64px 24px; }
.history-empty__title { font-size: 16px; font-weight: 600; color: #9CA3AF; margin-top: 16px; }

.history-list { display: flex; flex-direction: column; gap: 12px; }

.history-item { background: white; border-radius: 14px; border: 1px solid #F1F5F9; padding: 16px; }
.history-item__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.history-item__ticket { font-size: 12px; font-weight: 600; color: #F0294E; background: #FFF5F7; padding: 2px 8px; border-radius: 6px; }
.history-item__status { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 6px; }
.history-item__status--pending { background: #FEF3C7; color: #92400E; }
.history-item__status--done { background: #D1FAE5; color: #065F46; }
.history-item__type { font-size: 12px; color: #9CA3AF; }
.history-item__title { font-size: 15px; font-weight: 600; color: #111827; margin: 4px 0; }
.history-item__date { font-size: 12px; color: #9CA3AF; }
.history-item__reply { margin-top: 10px; padding: 10px; background: #F9FAFB; border-radius: 8px; font-size: 13px; color: #374151; line-height: 1.5; }
.history-item__reply-label { font-weight: 600; color: #111827; }
</style>
