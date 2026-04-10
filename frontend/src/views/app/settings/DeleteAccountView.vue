<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()

const understood = ref(false)
const deleteText = ref('')
const showConfirmModal = ref(false)
const countdown = ref(5)
const isDeleting = ref(false)

let timer: ReturnType<typeof setInterval> | null = null

const deleteTextValid = computed(() => deleteText.value.trim() === 'DELETE')
const canProceed = computed(() => understood.value && deleteTextValid.value)
const canConfirm = computed(() => countdown.value === 0 && !isDeleting.value)

function openConfirmModal() {
  if (!canProceed.value) return
  showConfirmModal.value = true
  countdown.value = 5
  timer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      if (timer) clearInterval(timer)
    }
  }, 1000)
}

function closeConfirmModal() {
  showConfirmModal.value = false
  if (timer) clearInterval(timer)
}

async function confirmDelete() {
  if (!canConfirm.value) return
  isDeleting.value = true
  try {
    await (await import('@/api/client')).default.post('/me/delete-account', { password: 'confirmed' })
    authStore.logout()
    localStorage.removeItem('dev_identity_key')
    localStorage.removeItem('member_level')
    localStorage.removeItem('is_suspended')
    uiStore.showToast('帳號已刪除', 'info')
    router.push('/')
  } catch {
    uiStore.showToast('刪除失敗，請稍後再試', 'error')
  } finally {
    isDeleting.value = false
    showConfirmModal.value = false
  }
}

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

const DELETED_DATA = [
  '個人資料與頭像照片',
  '所有聊天記錄',
  '誠信分數與驗證紀錄',
  '收藏清單與訪客記錄',
  '訂閱與付費紀錄',
]
</script>

<template>
  <AppLayout title="刪除帳號" :show-back="true">
    <div class="delete-page">
      <!-- 警告區塊 -->
      <div class="danger-box">
        <div class="danger-box__header">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#991B1B" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span class="danger-box__title">此操作無法復原</span>
        </div>
        <p class="danger-box__desc">刪除帳號後，以下資料將永久消失：</p>
        <ul class="danger-box__list">
          <li v-for="item in DELETED_DATA" :key="item">{{ item }}</li>
        </ul>
      </div>

      <!-- 第一步：確認勾選 -->
      <label class="confirm-check">
        <input type="checkbox" v-model="understood" />
        <span>我了解刪除帳號後所有資料將永久消失</span>
      </label>

      <!-- 第二步：輸入 DELETE -->
      <div class="delete-input-section">
        <label class="delete-input-label">請輸入「DELETE」以確認刪除</label>
        <input
          v-model="deleteText"
          type="text"
          class="delete-input"
          :class="{ 'delete-input--valid': deleteTextValid }"
          placeholder="DELETE"
          autocomplete="off"
        />
      </div>

      <!-- 刪除按鈕 -->
      <button
        class="btn-danger btn-full"
        :disabled="!canProceed"
        @click="openConfirmModal"
      >
        刪除我的帳號
      </button>

      <!-- 確認 Modal -->
      <div v-if="showConfirmModal" class="modal-overlay" @click="closeConfirmModal">
        <div class="modal-card" @click.stop>
          <div class="modal-card__icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
          <h3 class="modal-card__title">最後確認</h3>
          <p class="modal-card__desc">您確定要刪除帳號嗎？此操作無法復原。</p>
          <div class="modal-card__actions">
            <button class="btn-secondary" @click="closeConfirmModal">取消</button>
            <button
              class="btn-danger-solid"
              :disabled="!canConfirm"
              @click="confirmDelete"
            >
              {{ isDeleting ? '刪除中...' : countdown > 0 ? `確認刪除（${countdown}）` : '確認刪除' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style>
.delete-page { padding: 16px; }

/* ── Danger Box ── */
.danger-box { background: #FEF2F2; border: 1.5px solid #FECACA; border-radius: 14px; padding: 16px; margin-bottom: 20px; }
.danger-box__header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.danger-box__title { font-size: 16px; font-weight: 700; color: #991B1B; }
.danger-box__desc { font-size: 14px; color: #991B1B; margin-bottom: 8px; }
.danger-box__list { padding-left: 20px; }
.danger-box__list li { font-size: 13px; color: #991B1B; margin-bottom: 4px; }

/* ── Confirm Check ── */
.confirm-check { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; color: #374151; margin-bottom: 20px; cursor: pointer; line-height: 1.4; }
.confirm-check input { accent-color: #F0294E; width: 18px; height: 18px; margin-top: 2px; flex-shrink: 0; }

/* ── Delete Input ── */
.delete-input-section { margin-bottom: 24px; }
.delete-input-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.delete-input { width: 100%; height: 48px; border-radius: 10px; border: 1.5px solid #E5E7EB; padding: 0 16px; font-size: 16px; color: #111827; font-family: 'Inter', monospace; letter-spacing: 2px; }
.delete-input:focus { outline: none; border-color: #EF4444; box-shadow: 0 0 0 3px rgba(239,68,68,0.12); }
.delete-input--valid { border-color: #EF4444; background: #FEF2F2; }

/* ── Buttons ── */
.btn-danger { padding: 14px; border-radius: 10px; border: 1.5px solid #FECACA; background: #FEF2F2; color: #991B1B; font-size: 16px; font-weight: 600; cursor: pointer; }
.btn-danger:disabled { opacity: 0.3; cursor: not-allowed; }
.btn-full { width: 100%; }

.btn-danger-solid { flex: 1; padding: 12px; border-radius: 10px; border: none; background: #EF4444; color: white; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-danger-solid:disabled { opacity: 0.4; cursor: not-allowed; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 24px; }
.modal-card { background: white; border-radius: 20px; padding: 24px; width: 100%; max-width: 360px; text-align: center; }
.modal-card__icon { margin-bottom: 12px; }
.modal-card__title { font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 8px; }
.modal-card__desc { font-size: 14px; color: #6B7280; margin-bottom: 20px; }
.modal-card__actions { display: flex; gap: 10px; }
.btn-secondary { flex: 1; padding: 12px; border-radius: 10px; border: 1.5px solid #E5E7EB; background: white; color: #374151; font-size: 15px; font-weight: 500; cursor: pointer; }
</style>
