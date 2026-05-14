<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import { usePayment } from '@/composables/usePayment'
import { useUiStore } from '@/stores/ui'

const router = useRouter()
const uiStore = useUiStore()
const {
  currentSubscription,
  isPaid,
  isExpiringSoon,
  daysRemaining,
  isLoading,
  fetchCurrentSubscription,
  cancelSubscription,
  toggleAutoRenew,
} = usePayment()

const showCancelModal = ref(false)
const cancelReason = ref('')
const cancelSuccess = ref(false)

onMounted(() => { fetchCurrentSubscription() })

async function handleAutoRenewToggle() {
  if (!currentSubscription.value) return
  const newVal = !currentSubscription.value.autoRenew
  const ok = await toggleAutoRenew(newVal)
  if (ok) {
    uiStore.showToast(newVal ? '已開啟自動續訂' : '已關閉自動續訂', 'success')
  }
}

async function handleCancel() {
  if (!cancelReason.value.trim()) {
    uiStore.showToast('請填寫取消原因', 'warning')
    return
  }
  const ok = await cancelSubscription(cancelReason.value)
  if (ok) {
    showCancelModal.value = false
    cancelSuccess.value = true
  } else {
    uiStore.showToast('取消失敗，請稍後再試', 'error')
  }
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('zh-TW')
}
</script>

<template>
  <AppLayout title="訂閱管理" :show-back="true">
    <div class="sub-page">
      <!-- 有訂閱 -->
      <template v-if="isPaid && currentSubscription">
        <div class="sub-card">
          <div class="sub-card__header">
            <span class="sub-card__badge">目前方案</span>
            <span v-if="isExpiringSoon" class="sub-card__warn">即將到期</span>
          </div>
          <div class="sub-card__plan">{{ currentSubscription.planName }}</div>
          <div class="sub-card__detail">
            <span>到期日：{{ formatDate(currentSubscription.expiresAt) }}</span>
            <span>剩餘 {{ daysRemaining }} 天</span>
          </div>
        </div>

        <div class="sub-setting" v-if="!currentSubscription.isTrial">
          <div class="sub-setting__row">
            <div>
              <span class="sub-setting__label">自動續訂</span>
              <span class="sub-setting__desc">到期後自動扣款續訂</span>
            </div>
            <button
              class="toggle-sm"
              :class="{ 'toggle-sm--on': currentSubscription.autoRenew }"
              @click="handleAutoRenewToggle"
            >
              <span class="toggle-sm__dot" />
            </button>
          </div>
        </div>

        <!-- 取消訂閱成功 -->
        <div v-if="cancelSuccess" class="cancel-success">
          <div class="cancel-success__icon">✅</div>
          <div class="cancel-success__title">取消申請已送出</div>
          <p class="cancel-success__desc">服務將持續至到期日 {{ formatDate(currentSubscription.expiresAt) }}，到期後不自動續費。</p>
        </div>

        <button
          v-else
          class="cancel-btn"
          @click="showCancelModal = true"
        >
          取消訂閱
        </button>
      </template>

      <!-- 無訂閱 -->
      <template v-else>
        <div class="empty-sub">
          <div class="empty-sub__icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20"/></svg>
          </div>
          <div class="empty-sub__title">目前沒有有效訂閱</div>
          <p class="empty-sub__desc">升級付費會員解鎖全部功能</p>
          <button class="btn-primary btn-full" @click="router.push('/app/shop')">前往會員商城</button>
        </div>
      </template>

      <!-- 取消 Modal -->
      <div v-if="showCancelModal" class="modal-overlay" @click="showCancelModal = false">
        <div class="modal-card" @click.stop>
          <h3 class="modal-card__title">確定要取消訂閱嗎？</h3>
          <p class="modal-card__desc">取消後服務將持續至到期日，不會立即失效。</p>
          <textarea
            v-model="cancelReason"
            class="modal-card__textarea"
            placeholder="請告訴我們取消原因（必填）"
            rows="3"
          />
          <div class="modal-card__actions">
            <button class="btn-secondary" @click="showCancelModal = false">返回</button>
            <button class="btn-danger" :disabled="isLoading || !cancelReason.trim()" @click="handleCancel">
              {{ isLoading ? '處理中...' : '確認取消' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style>
.sub-page { padding: 16px; }

.sub-card { background: linear-gradient(135deg, #F0294E, #A80F2C); border-radius: 14px; padding: 20px; color: white; margin-bottom: 16px; }
.sub-card__header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.sub-card__badge { background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
.sub-card__warn { background: #F59E0B; color: #92400E; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
.sub-card__plan { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
.sub-card__detail { font-size: 13px; opacity: 0.85; display: flex; gap: 12px; }

.sub-setting { background: white; border-radius: 14px; border: 1px solid #F1F5F9; padding: 16px; margin-bottom: 16px; }
.sub-setting__row { display: flex; align-items: center; justify-content: space-between; }
.sub-setting__label { font-size: 14px; font-weight: 600; color: #111827; display: block; }
.sub-setting__desc { font-size: 12px; color: #9CA3AF; }

.toggle-sm { width: 40px; height: 22px; border-radius: 11px; border: none; background: #E5E7EB; position: relative; cursor: pointer; transition: background 0.2s; padding: 0; }
.toggle-sm--on { background: #22C55E; }
.toggle-sm__dot { position: absolute; top: 2px; left: 2px; width: 18px; height: 18px; border-radius: 50%; background: white; transition: transform 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
.toggle-sm--on .toggle-sm__dot { transform: translateX(18px); }

.cancel-btn { width: 100%; padding: 12px; border-radius: 10px; border: 1.5px solid #F59E0B; background: #FFFBEB; color: #92400E; font-size: 15px; font-weight: 600; cursor: pointer; }
.cancel-btn:hover { background: #FEF3C7; }

.cancel-success { text-align: center; padding: 24px 0; }
.cancel-success__icon { font-size: 36px; margin-bottom: 8px; }
.cancel-success__title { font-size: 16px; font-weight: 700; color: #111827; }
.cancel-success__desc { font-size: 14px; color: #6B7280; margin-top: 4px; }

.empty-sub { text-align: center; padding: 48px 16px; }
.empty-sub__icon { margin-bottom: 16px; }
.empty-sub__title { font-size: 18px; font-weight: 700; color: #111827; }
.empty-sub__desc { font-size: 14px; color: #6B7280; margin: 4px 0 24px; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 24px; }
.modal-card { background: white; border-radius: 20px; padding: 24px; width: 100%; max-width: 360px; }
.modal-card__title { font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 8px; }
.modal-card__desc { font-size: 14px; color: #6B7280; margin-bottom: 16px; }
.modal-card__textarea { width: 100%; border: 1.5px solid #E5E7EB; border-radius: 10px; padding: 12px; font-size: 14px; resize: none; font-family: inherit; margin-bottom: 16px; }
.modal-card__textarea:focus { outline: none; border-color: #F0294E; }
.modal-card__actions { display: flex; gap: 10px; }
.btn-primary { flex: 1; padding: 12px; border-radius: 10px; border: none; background: #F0294E; color: white; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-secondary { flex: 1; padding: 12px; border-radius: 10px; border: 1.5px solid #E5E7EB; background: white; color: #374151; font-size: 15px; font-weight: 500; cursor: pointer; }
.btn-danger { flex: 1; padding: 12px; border-radius: 10px; border: none; background: #EF4444; color: white; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-danger:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-full { width: 100%; }
</style>
