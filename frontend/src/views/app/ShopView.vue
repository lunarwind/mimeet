<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import { usePayment } from '@/composables/usePayment'
import { usePoints } from '@/composables/usePoints'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import client from '@/api/client'
import type { SubscriptionPlan } from '@/types/payment'
import type { PointPackage } from '@/types/points'

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
  toggleAutoRenew,
} = usePayment()

const showConfirmModal = ref(false)
const selectedPlan = ref<SubscriptionPlan | null>(null)
const autoRenewChecked = ref(true)
const selectedPaymentMethod = ref<'credit_card' | 'atm' | 'cvs'>('credit_card')
const isSubmitting = ref(false)

// F40 — Tab + points
const activeTab = ref<'subscription' | 'points'>('subscription')
const {
  packages: pointPackages,
  balance: pointBalance,
  history: pointHistory,
  fetchPackages: fetchPointPackages,
  fetchBalance: fetchPointBalance,
  fetchHistory: fetchPointHistory,
  purchasePackage: purchasePointPackage,
} = usePoints()
const showHistoryModal = ref(false)
const selectedPointPackage = ref<PointPackage | null>(null)
const showPointConfirm = ref(false)

function openPointHistory() {
  showHistoryModal.value = true
  fetchPointHistory()
}
function selectPointPackage(p: PointPackage) {
  selectedPointPackage.value = p
  showPointConfirm.value = true
}
async function confirmPointPurchase() {
  if (!selectedPointPackage.value || isSubmitting.value) return
  isSubmitting.value = true
  try {
    const res = await purchasePointPackage(selectedPointPackage.value.slug)
    showPointConfirm.value = false
    window.location.href = res.paymentUrl
  } catch (err: unknown) {
    showPointConfirm.value = false
    const e = err as { response?: { data?: { error?: { message?: string } } } }
    uiStore.showToast(e?.response?.data?.error?.message ?? '建立點數訂單失敗', 'error')
  } finally {
    isSubmitting.value = false
  }
}

function formatTxnType(t: string): string {
  const map: Record<string, string> = {
    purchase: '購買', consume: '消費', refund: '退款',
    admin_gift: '管理員贈送', admin_deduct: '管理員扣除',
  }
  return map[t] ?? t
}
function formatFeature(f: string | null): string {
  if (!f) return ''
  const map: Record<string, string> = {
    stealth: '🕶 隱身', reverse_msg: '💬 突破訊息', super_like: '⭐ 超級讚', broadcast: '📢 廣播',
  }
  return map[f] ?? f
}

const PAYMENT_METHODS = [
  { value: 'credit_card' as const, label: '信用卡', icon: '💳', desc: '綠界金流（Visa / Master / JCB）' },
]

onMounted(async () => {
  await Promise.all([fetchPlans(), fetchCurrentSubscription(), fetchPointPackages(), fetchPointBalance()])

  // Detect payment return (hash mode: params are after #/app/shop?)
  const hashParams = new URLSearchParams(window.location.hash.split('?')[1] ?? '')
  const paymentStatus = hashParams.get('payment')
  const tabParam = hashParams.get('tab')
  if (tabParam === 'points') activeTab.value = 'points'

  if (paymentStatus === 'success') {
    const msg = tabParam === 'points' ? '點數已入帳！' : '付款成功！訂閱已啟用'
    uiStore.showToast(msg, 'success')
    // Re-fetch user to update membership_level / points_balance
    try {
      const { getMe } = await import('@/api/auth')
      const data = await getMe()
      authStore.setUser(data.user)
    } catch { /* ignore */ }
    await Promise.all([fetchCurrentSubscription(), fetchPointBalance()])
    window.history.replaceState({}, '', window.location.pathname + window.location.hash.split('?')[0])
  } else if (paymentStatus === 'complete') {
    uiStore.showToast('付款處理中，請稍候...', 'info')
    await fetchCurrentSubscription()
    window.history.replaceState({}, '', window.location.pathname + window.location.hash.split('?')[0])
  }
})

function selectPlan(plan: SubscriptionPlan) {
  selectedPlan.value = plan
  autoRenewChecked.value = true
  selectedPaymentMethod.value = 'credit_card'
  showConfirmModal.value = true
}

async function confirmPurchase() {
  if (!selectedPlan.value || isSubmitting.value) return
  isSubmitting.value = true

  try {
    const plan = selectedPlan.value
    const slug = plan.slug ?? plan.type ?? plan.id

    const res = await client.post('/subscriptions/orders', {
      plan_id: slug,
      payment_method: selectedPaymentMethod.value,
    })

    showConfirmModal.value = false

    const data = res.data?.data ?? {}
    const paymentUrl = data.payment_url ?? null

    if (!paymentUrl) {
      uiStore.showToast('無法取得付款連結，請稍後再試', 'error')
      return
    }

    // External URL — use window.location.href (not Vue Router)
    window.location.href = paymentUrl
  } catch (err: unknown) {
    showConfirmModal.value = false
    const e = err as { response?: { data?: { message?: string } } }
    const msg = e?.response?.data?.message ?? '建立訂單失敗，請稍後再試'
    uiStore.showToast(msg, 'error')
  } finally {
    isSubmitting.value = false
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

function formatDate(iso: string | null | undefined) {
  if (!iso) return '未知'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return '未知'
  return d.toLocaleDateString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit' })
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
      <!-- Tab 切換 -->
      <div class="shop-tabs">
        <button class="shop-tab" :class="{ 'shop-tab--active': activeTab === 'subscription' }" @click="activeTab = 'subscription'">訂閱方案</button>
        <button class="shop-tab" :class="{ 'shop-tab--active': activeTab === 'points' }" @click="activeTab = 'points'">點數商城</button>
      </div>

      <!-- ==== 點數商城 Tab ==================================================== -->
      <template v-if="activeTab === 'points'">
        <section class="points-balance-card">
          <div>
            <span class="points-balance-card__label">💎 我的點數</span>
            <span class="points-balance-card__value">{{ pointBalance }} 點</span>
          </div>
          <button class="points-balance-card__history-btn" @click="openPointHistory">查看交易紀錄</button>
        </section>

        <section class="plans">
          <h2 class="section-title">選購點數</h2>
          <div class="points-grid">
            <div
              v-for="p in pointPackages"
              :key="p.slug"
              class="point-card"
              :class="{ 'point-card--popular': p.slug === 'pack_150', 'point-card--best': p.slug === 'pack_1200' }"
              @click="selectPointPackage(p)"
            >
              <span v-if="p.slug === 'pack_150'" class="point-card__tag">最受歡迎</span>
              <span v-else-if="p.slug === 'pack_1200'" class="point-card__tag point-card__tag--best">最划算</span>
              <div class="point-card__name">{{ p.name }}</div>
              <div class="point-card__points">
                <span class="point-card__base">{{ p.points }} 點</span>
                <span v-if="p.bonusPoints > 0" class="point-card__bonus">+{{ p.bonusPoints }}</span>
              </div>
              <div class="point-card__price">NT${{ formatPrice(p.price) }}</div>
              <div class="point-card__cost">$ {{ p.costPerPoint.toFixed(1) }} / 點</div>
              <button class="point-card__btn">購買</button>
            </div>
          </div>
          <p class="points-note">💡 點數可用於：隱身模式、超級讚、廣播訊息、突破訊息限制</p>
        </section>
      </template>

      <!-- ==== 訂閱方案 Tab ==================================================== -->
      <template v-else>
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
      </template>
      <!-- /訂閱方案 Tab -->

      <!-- 點數購買確認 Modal -->
      <div v-if="showPointConfirm" class="modal-overlay" @click="showPointConfirm = false">
        <div class="modal-card" @click.stop>
          <h3 class="modal-card__title">確認購買點數</h3>
          <template v-if="selectedPointPackage">
            <div class="modal-card__plan">{{ selectedPointPackage.name }}</div>
            <div class="modal-card__amount">NT${{ formatPrice(selectedPointPackage.price) }}</div>
            <div class="modal-card__meta">
              {{ selectedPointPackage.points }} 點
              <span v-if="selectedPointPackage.bonusPoints > 0">+ 贈送 {{ selectedPointPackage.bonusPoints }} 點</span>
            </div>
            <div class="modal-card__actions">
              <button class="btn-secondary" @click="showPointConfirm = false">取消</button>
              <button class="btn-primary" :disabled="isSubmitting" @click="confirmPointPurchase">
                {{ isSubmitting ? '處理中...' : '前往付款' }}
              </button>
            </div>
          </template>
        </div>
      </div>

      <!-- 點數交易紀錄 Modal -->
      <div v-if="showHistoryModal" class="modal-overlay" @click="showHistoryModal = false">
        <div class="modal-card modal-card--wide" @click.stop>
          <h3 class="modal-card__title">點數交易紀錄</h3>
          <div v-if="pointHistory.length === 0" class="point-history__empty">尚無交易紀錄</div>
          <div v-else class="point-history">
            <div v-for="t in pointHistory" :key="t.id" class="point-history__row">
              <div class="point-history__left">
                <span class="point-history__type">{{ formatTxnType(t.type) }}</span>
                <span v-if="t.feature" class="point-history__feature">{{ formatFeature(t.feature) }}</span>
                <span class="point-history__desc">{{ t.description ?? '' }}</span>
              </div>
              <div class="point-history__right">
                <span :class="t.amount > 0 ? 'point-history__amount--plus' : 'point-history__amount--minus'">
                  {{ t.amount > 0 ? '+' : '' }}{{ t.amount }}
                </span>
                <span class="point-history__balance">餘額 {{ t.balanceAfter }}</span>
                <span class="point-history__time">{{ formatDate(t.createdAt) }}</span>
              </div>
            </div>
          </div>
          <div class="modal-card__actions">
            <button class="btn-primary" @click="showHistoryModal = false">關閉</button>
          </div>
        </div>
      </div>

      <!-- 訂閱確認 Modal -->
      <div v-if="showConfirmModal" class="modal-overlay" @click="showConfirmModal = false">
        <div class="modal-card" @click.stop>
          <h3 class="modal-card__title">確認購買</h3>
          <template v-if="selectedPlan">
            <div class="modal-card__plan">{{ selectedPlan.name }}</div>
            <div class="modal-card__amount">NT${{ formatPrice(selectedPlan.price) }}</div>

            <!-- 金流選擇 -->
            <div class="modal-card__section">
              <div class="modal-card__section-title">付款方式</div>
              <div
                v-for="pm in PAYMENT_METHODS"
                :key="pm.value"
                class="payment-method-row"
                :class="{ 'payment-method-row--selected': selectedPaymentMethod === pm.value }"
                @click="selectedPaymentMethod = pm.value"
              >
                <span class="payment-method-row__icon">{{ pm.icon }}</span>
                <div class="payment-method-row__info">
                  <span class="payment-method-row__label">{{ pm.label }}</span>
                  <span class="payment-method-row__desc">{{ pm.desc }}</span>
                </div>
                <div class="payment-method-row__check">
                  <span v-if="selectedPaymentMethod === pm.value">✓</span>
                </div>
              </div>
            </div>

            <label class="modal-card__check">
              <input type="checkbox" v-model="autoRenewChecked" />
              <span>到期後自動續訂</span>
            </label>
          </template>
          <div class="modal-card__actions">
            <button class="btn-secondary" @click="showConfirmModal = false">取消</button>
            <button class="btn-primary" :disabled="isSubmitting" @click="confirmPurchase">
              {{ isSubmitting ? '處理中...' : '確認付款' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style>
/* ── F40 Tab 切換 ──────────────────────────────────── */
.shop-tabs { display:flex; gap:8px; margin-bottom:16px; padding:4px; background:#F1F5F9; border-radius:12px; }
.shop-tab { flex:1; padding:10px; border:none; background:transparent; font-size:14px; font-weight:600; color:#6B7280; border-radius:8px; cursor:pointer; transition:all 0.15s; }
.shop-tab--active { background:#fff; color:#F0294E; box-shadow:0 2px 4px rgba(17,24,39,0.06); }

/* ── 點數餘額卡片 ──────────────────────────────────── */
.points-balance-card { display:flex; justify-content:space-between; align-items:center; padding:16px; background:linear-gradient(135deg,#FFE4EA 0%,#FFF5F7 100%); border-radius:14px; margin-bottom:16px; }
.points-balance-card__label { display:block; font-size:12px; color:#6B7280; margin-bottom:4px; }
.points-balance-card__value { display:block; font-size:24px; font-weight:800; color:#F0294E; }
.points-balance-card__history-btn { padding:8px 14px; border:1.5px solid #F0294E; background:#fff; color:#F0294E; border-radius:9999px; font-size:12px; font-weight:600; cursor:pointer; }

/* ── 點數包 grid ───────────────────────────────────── */
.points-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.point-card { position:relative; padding:16px 14px; background:#fff; border:1.5px solid #E5E7EB; border-radius:14px; text-align:center; cursor:pointer; transition:all 0.15s; }
.point-card:hover { border-color:#F0294E; transform:translateY(-2px); }
.point-card--popular { border-color:#F0294E; background:linear-gradient(180deg,#FFF5F7 0%,#fff 60%); }
.point-card--best { border-color:#F59E0B; background:linear-gradient(180deg,#FFFBEB 0%,#fff 60%); }
.point-card__tag { position:absolute; top:-10px; left:50%; transform:translateX(-50%); padding:2px 10px; background:#F0294E; color:#fff; border-radius:9999px; font-size:11px; font-weight:700; white-space:nowrap; }
.point-card__tag--best { background:#F59E0B; }
.point-card__name { font-size:15px; font-weight:700; color:#111827; margin-bottom:8px; }
.point-card__points { display:flex; align-items:baseline; justify-content:center; gap:4px; margin-bottom:4px; }
.point-card__base { font-size:22px; font-weight:800; color:#F0294E; font-variant-numeric:tabular-nums; }
.point-card__bonus { font-size:13px; color:#F59E0B; font-weight:700; }
.point-card__price { font-size:14px; color:#111827; font-weight:600; margin:4px 0 2px; }
.point-card__cost { font-size:11px; color:#9CA3AF; margin-bottom:10px; }
.point-card__btn { width:100%; padding:8px; background:#F0294E; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
.points-note { font-size:12px; color:#6B7280; text-align:center; margin-top:16px; padding:10px; background:#F9FAFB; border-radius:8px; }

/* ── 交易紀錄 ──────────────────────────────────────── */
.modal-card--wide { max-width:520px; max-height:70dvh; overflow:hidden; display:flex; flex-direction:column; }
.point-history { flex:1; overflow-y:auto; margin:8px 0; }
.point-history__row { display:flex; justify-content:space-between; align-items:center; padding:10px 4px; border-bottom:1px solid #F3F4F6; }
.point-history__left { display:flex; flex-direction:column; gap:2px; }
.point-history__type { font-size:13px; font-weight:600; color:#111827; }
.point-history__feature { font-size:11px; color:#6B7280; }
.point-history__desc { font-size:11px; color:#9CA3AF; }
.point-history__right { display:flex; flex-direction:column; align-items:flex-end; gap:2px; }
.point-history__amount--plus { font-size:14px; font-weight:700; color:#10B981; font-variant-numeric:tabular-nums; }
.point-history__amount--minus { font-size:14px; font-weight:700; color:#EF4444; font-variant-numeric:tabular-nums; }
.point-history__balance { font-size:11px; color:#6B7280; }
.point-history__time { font-size:10px; color:#9CA3AF; }
.point-history__empty { padding:32px; text-align:center; color:#9CA3AF; font-size:13px; }
.modal-card__meta { font-size:13px; color:#6B7280; margin:8px 0 16px; }

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
.modal-card__section { margin-bottom: 16px; text-align: left; }
.modal-card__section-title { font-size: 13px; font-weight: 600; color: #6B7280; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.modal-card__check { display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; color: #374151; margin-bottom: 20px; cursor: pointer; }
.modal-card__check input { accent-color: #F0294E; width: 18px; height: 18px; }
.modal-card__actions { display: flex; gap: 10px; }

/* ── Payment method selector ── */
.payment-method-row { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; border: 1.5px solid #E5E7EB; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
.payment-method-row--selected { border-color: #F0294E; background: #FFF5F7; }
.payment-method-row__icon { font-size: 20px; }
.payment-method-row__info { flex: 1; display: flex; flex-direction: column; }
.payment-method-row__label { font-size: 14px; font-weight: 600; color: #111827; }
.payment-method-row__desc { font-size: 12px; color: #9CA3AF; }
.payment-method-row__check { width: 20px; height: 20px; border-radius: 50%; background: #F0294E; color: white; display: flex; align-items: center; justify-content: center; font-size: 12px; opacity: 0; }
.payment-method-row--selected .payment-method-row__check { opacity: 1; }

/* ── Buttons ── */
.btn-primary { flex: 1; padding: 12px; border-radius: 10px; border: none; background: #F0294E; color: white; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-primary:hover { background: #D01A3C; }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-secondary { flex: 1; padding: 12px; border-radius: 10px; border: 1.5px solid #E5E7EB; background: white; color: #374151; font-size: 15px; font-weight: 500; cursor: pointer; }
</style>
