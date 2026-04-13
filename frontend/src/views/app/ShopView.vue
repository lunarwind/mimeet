<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import { usePayment } from '@/composables/usePayment'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import type { SubscriptionPlan } from '@/types/payment'

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const {
  plans,
  currentSubscription,
  isPaid,
  isExpiringSoon,
  daysRemaining,
  isLoading,
  fetchPlans,
  fetchCurrentSubscription,
  createOrder,
  toggleAutoRenew,
} = usePayment()

const showConfirmModal = ref(false)
const selectedPlan = ref<SubscriptionPlan | null>(null)
const autoRenewChecked = ref(true)

onMounted(async () => {
  await Promise.all([fetchPlans(), fetchCurrentSubscription()])
})

function selectPlan(plan: SubscriptionPlan) {
  selectedPlan.value = plan
  autoRenewChecked.value = true
  showConfirmModal.value = true
}

async function confirmPurchase() {
  if (!selectedPlan.value) return
  const result = await createOrder(selectedPlan.value.type)
  showConfirmModal.value = false
  if (result) {
    window.location.href = result.orderUrl
  } else {
    uiStore.showToast('建立訂單失敗', 'error')
  }
}

async function handleAutoRenewToggle() {
  if (!currentSubscription.value) return
  const newVal = !currentSubscription.value.autoRenew
  const ok = await toggleAutoRenew(newVal)
  if (ok) {
    uiStore.showToast(newVal ? '已開啟自動續訂' : '已關閉自動續訂', 'success')
  }
}

function formatPrice(n: number) {
  return n.toLocaleString('zh-TW')
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('zh-TW')
}

const PAID_FEATURES = [
  { icon: '💬', label: '無限聊天', desc: '不限次數傳送訊息' },
  { icon: '✅', label: '已讀回執', desc: '知道對方是否已讀' },
  { icon: '📱', label: 'QR 約會驗證', desc: '見面掃碼，誠信加分' },
  { icon: '📝', label: '動態發布', desc: '分享生活動態吸引關注' },
]
</script>

<template>
  <AppLayout title="會員商城">
    <div class="shop-page">
      <!-- 已付費會員區塊 -->
      <section v-if="isPaid && currentSubscription" class="my-member">
        <div class="my-member__header">
          <span class="my-member__badge">付費會員</span>
          <span v-if="isExpiringSoon" class="my-member__expiry-warn">即將到期</span>
        </div>
        <div class="my-member__plan">{{ currentSubscription.planName }}</div>
        <div class="my-member__info">
          <span>到期日：{{ formatDate(currentSubscription.expiresAt) }}</span>
          <span>剩餘 {{ daysRemaining }} 天</span>
        </div>
        <div class="my-member__row">
          <span class="my-member__label">自動續訂</span>
          <button
            class="toggle-btn"
            :class="{ 'toggle-btn--on': currentSubscription.autoRenew }"
            @click="handleAutoRenewToggle"
          >
            <span class="toggle-btn__dot" />
          </button>
        </div>
        <button class="manage-btn" @click="router.push('/app/settings/subscription')">
          前往訂閱管理
        </button>
      </section>

      <!-- 新手體驗入口 -->
      <section v-if="!isPaid" class="trial-entry" @click="router.push('/app/shop/trial')">
        <div class="trial-entry__left">
          <span class="trial-entry__tag">限時體驗</span>
          <span class="trial-entry__title">NT$199 體驗 30 天</span>
          <span class="trial-entry__desc">全功能解鎖，每人限購一次</span>
        </div>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#F0294E" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
      </section>

      <!-- 方案卡片 -->
      <section class="plans">
        <h2 class="section-title">選擇方案</h2>
        <div class="plans-grid">
          <div
            v-for="plan in plans"
            :key="plan.id"
            class="plan-card"
            :class="{ 'plan-card--popular': plan.isPopular }"
            @click="selectPlan(plan)"
          >
            <span v-if="plan.isPopular" class="plan-card__pop-tag">最多人選擇</span>
            <div class="plan-card__name">{{ plan.name }}</div>
            <div class="plan-card__price">
              <span class="plan-card__currency">NT$</span>
              <span class="plan-card__amount">{{ formatPrice(plan.price) }}</span>
            </div>
            <div v-if="plan.originalPrice" class="plan-card__original">
              原價 NT${{ formatPrice(plan.originalPrice) }}
            </div>
            <div class="plan-card__duration">{{ plan.durationDays }} 天</div>
            <ul class="plan-card__features">
              <li v-for="f in plan.features" :key="f">{{ f }}</li>
            </ul>
          </div>
        </div>
      </section>

      <!-- 付費功能說明 -->
      <section class="features-section">
        <h2 class="section-title">付費限定功能</h2>
        <div class="features-list">
          <div v-for="feat in PAID_FEATURES" :key="feat.label" class="feature-item">
            <span class="feature-item__icon">{{ feat.icon }}</span>
            <div>
              <div class="feature-item__label">{{ feat.label }}</div>
              <div class="feature-item__desc">{{ feat.desc }}</div>
            </div>
          </div>
        </div>
      </section>

      <!-- 確認 Modal -->
      <div v-if="showConfirmModal" class="modal-overlay" @click="showConfirmModal = false">
        <div class="modal-card" @click.stop>
          <h3 class="modal-card__title">確認購買</h3>
          <template v-if="selectedPlan">
            <div class="modal-card__plan">{{ selectedPlan.name }}</div>
            <div class="modal-card__amount">NT${{ formatPrice(selectedPlan.price) }}</div>
            <label class="modal-card__check">
              <input type="checkbox" v-model="autoRenewChecked" />
              <span>到期後自動續訂</span>
            </label>
          </template>
          <div class="modal-card__actions">
            <button class="btn-secondary" @click="showConfirmModal = false">取消</button>
            <button class="btn-primary" :disabled="isLoading" @click="confirmPurchase">
              {{ isLoading ? '處理中...' : '確認付款' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style>
.shop-page { padding: 16px; }

/* ── 我的會員 ── */
.my-member { background: linear-gradient(135deg, #F0294E, #A80F2C); border-radius: 14px; padding: 20px; color: white; margin-bottom: 16px; }
.my-member__header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.my-member__badge { background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
.my-member__expiry-warn { background: #F59E0B; color: #92400E; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
.my-member__plan { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
.my-member__info { font-size: 13px; opacity: 0.85; display: flex; gap: 12px; margin-bottom: 12px; }
.my-member__row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.my-member__label { font-size: 14px; }
.manage-btn { width: 100%; padding: 10px; border-radius: 10px; border: 1.5px solid rgba(255,255,255,0.4); background: transparent; color: white; font-size: 14px; font-weight: 600; cursor: pointer; }

/* ── Toggle ── */
.toggle-btn { width: 44px; height: 24px; border-radius: 12px; border: none; background: rgba(255,255,255,0.3); position: relative; cursor: pointer; transition: background 0.2s; padding: 0; }
.toggle-btn--on { background: #22C55E; }
.toggle-btn__dot { position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: white; transition: transform 0.2s; }
.toggle-btn--on .toggle-btn__dot { transform: translateX(20px); }

/* ── 體驗入口 ── */
.trial-entry { display: flex; align-items: center; justify-content: space-between; background: #FFF5F7; border: 1.5px solid #FFE4EA; border-radius: 14px; padding: 16px; margin-bottom: 20px; cursor: pointer; }
.trial-entry__left { display: flex; flex-direction: column; gap: 2px; }
.trial-entry__tag { font-size: 11px; font-weight: 700; color: #F0294E; text-transform: uppercase; letter-spacing: 0.5px; }
.trial-entry__title { font-size: 16px; font-weight: 700; color: #111827; }
.trial-entry__desc { font-size: 12px; color: #6B7280; }

/* ── 方案卡片 ── */
.section-title { font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 12px; }
.plans { margin-bottom: 24px; }
.plans-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
.plan-card { background: white; border: 1.5px solid #E5E7EB; border-radius: 14px; padding: 16px; cursor: pointer; position: relative; transition: border-color 0.15s, box-shadow 0.15s; text-align: center; }
.plan-card:active { transform: scale(0.98); }
.plan-card--popular { border-color: #F0294E; box-shadow: 0 0 0 1px #F0294E; }
.plan-card__pop-tag { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: #F0294E; color: white; font-size: 10px; font-weight: 700; padding: 2px 10px; border-radius: 9999px; white-space: nowrap; }
.plan-card__name { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px; }
.plan-card__price { display: flex; align-items: baseline; justify-content: center; gap: 2px; }
.plan-card__currency { font-size: 14px; color: #F0294E; font-weight: 600; }
.plan-card__amount { font-size: 28px; font-weight: 800; color: #F0294E; font-variant-numeric: tabular-nums; }
.plan-card__original { font-size: 12px; color: #9CA3AF; text-decoration: line-through; margin-top: 2px; }
.plan-card__duration { font-size: 12px; color: #6B7280; margin: 4px 0 10px; }
.plan-card__features { list-style: none; padding: 0; text-align: left; }
.plan-card__features li { font-size: 12px; color: #6B7280; padding: 2px 0; }
.plan-card__features li::before { content: '✓ '; color: #22C55E; font-weight: 700; }

/* ── 功能說明 ── */
.features-section { margin-bottom: 24px; }
.features-list { display: flex; flex-direction: column; gap: 12px; }
.feature-item { display: flex; align-items: center; gap: 12px; background: white; border-radius: 12px; padding: 14px 16px; border: 1px solid #F1F5F9; }
.feature-item__icon { font-size: 24px; flex-shrink: 0; }
.feature-item__label { font-size: 14px; font-weight: 600; color: #111827; }
.feature-item__desc { font-size: 12px; color: #6B7280; }

/* ── Modal ── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 24px; }
.modal-card { background: white; border-radius: 20px; padding: 24px; width: 100%; max-width: 360px; text-align: center; }
.modal-card__title { font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 16px; }
.modal-card__plan { font-size: 16px; font-weight: 600; color: #374151; }
.modal-card__amount { font-size: 28px; font-weight: 800; color: #F0294E; margin: 8px 0 16px; }
.modal-card__check { display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; color: #374151; margin-bottom: 20px; cursor: pointer; }
.modal-card__check input { accent-color: #F0294E; width: 18px; height: 18px; }
.modal-card__actions { display: flex; gap: 10px; }

/* ── Buttons ── */
.btn-primary { flex: 1; padding: 12px; border-radius: 10px; border: none; background: #F0294E; color: white; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-primary:hover { background: #D01A3C; }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-secondary { flex: 1; padding: 12px; border-radius: 10px; border: 1.5px solid #E5E7EB; background: white; color: #374151; font-size: 15px; font-weight: 500; cursor: pointer; }
</style>
