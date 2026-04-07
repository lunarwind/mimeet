<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import TopBar from '@/components/layout/TopBar.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const upgradeReason = computed(() => route.query.reason as string | undefined)
const isPaid = computed(() => (authStore.user?.membership_level ?? 0) >= 3)

// ── 方案資料 ──────────────────────────────────────────────
interface Plan {
  id: number
  name: string
  price: number
  duration: string
  durationLabel: string
  features: string[]
  popular: boolean
}

const plans = ref<Plan[]>([
  {
    id: 1,
    name: '月費方案',
    price: 499,
    duration: 'monthly',
    durationLabel: '/ 月',
    features: ['無限聊天', '查看已讀狀態', '進階搜尋', '隱身模式'],
    popular: false,
  },
  {
    id: 2,
    name: '季費方案',
    price: 1199,
    duration: 'quarterly',
    durationLabel: '/ 3個月',
    features: ['所有月費功能', 'VIP 標誌', '優先客服', '廣播訊息'],
    popular: true,
  },
  {
    id: 3,
    name: '年費方案',
    price: 3999,
    duration: 'yearly',
    durationLabel: '/ 年',
    features: ['所有季費功能', '年度最佳 CP 值', '專屬活動邀請'],
    popular: false,
  },
])

const selectedPlan = ref<number>(2) // 預設選中季費

function selectPlan(id: number) {
  selectedPlan.value = id
}

function handleSubscribe() {
  // Mock: 實際會串接金流
  alert(`模擬付款：方案 ${selectedPlan.value}（DEV 環境不串接金流）`)
}

function goToTrial() {
  router.push({ name: 'shop-trial' })
}
</script>

<template>
  <div class="shop-view">
    <TopBar title="會員商城" show-back />

    <div class="shop-body">
      <!-- 升級提示 -->
      <div v-if="upgradeReason === 'upgrade_required'" class="upgrade-banner">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        <span>此功能需要付費會員才能使用</span>
      </div>

      <!-- 已付費狀態 -->
      <div v-if="isPaid" class="paid-card">
        <div class="paid-card__badge">✨</div>
        <h2 class="paid-card__title">您已是付費會員</h2>
        <p class="paid-card__text">所有功能已解鎖，感謝您的支持！</p>
      </div>

      <!-- 方案選擇 -->
      <template v-if="!isPaid">
        <h2 class="shop-title">選擇適合你的方案</h2>

        <div class="plan-list">
          <div
            v-for="plan in plans"
            :key="plan.id"
            class="plan-card"
            :class="{
              'plan-card--selected': selectedPlan === plan.id,
              'plan-card--popular': plan.popular,
            }"
            @click="selectPlan(plan.id)"
          >
            <span v-if="plan.popular" class="plan-card__tag">最受歡迎</span>
            <div class="plan-card__header">
              <h3 class="plan-card__name">{{ plan.name }}</h3>
              <div class="plan-card__price">
                <span class="plan-card__amount">NT$ {{ plan.price.toLocaleString() }}</span>
                <span class="plan-card__period">{{ plan.durationLabel }}</span>
              </div>
            </div>
            <ul class="plan-card__features">
              <li v-for="f in plan.features" :key="f">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                {{ f }}
              </li>
            </ul>
            <div class="plan-card__radio" :class="{ 'plan-card__radio--checked': selectedPlan === plan.id }" />
          </div>
        </div>

        <button class="subscribe-btn" @click="handleSubscribe">
          立即訂閱
        </button>

        <!-- 體驗價入口 -->
        <div class="trial-entry" @click="goToTrial">
          <span>🎁 首次體驗只要 <strong>NT$ 199</strong></span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.shop-view {
  display: flex;
  flex-direction: column;
  flex: 1;
  background: #F9F9FB;
}

.shop-body {
  flex: 1;
  padding: 16px;
}

/* ── Upgrade Banner ──────────────────────────────────────── */
.upgrade-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #FFFBEB;
  border: 1px solid #FDE68A;
  border-radius: 10px;
  padding: 12px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #92400E;
  margin-bottom: 16px;
}

/* ── Paid Card ───────────────────────────────────────────── */
.paid-card {
  text-align: center;
  padding: 40px 16px;
  background: linear-gradient(135deg, #FFFBEB, #FEF3C7);
  border-radius: 16px;
  border: 1px solid #FDE68A;
}

.paid-card__badge { font-size: 40px; margin-bottom: 12px; }
.paid-card__title { font-size: 20px; font-weight: 700; color: #92400E; }
.paid-card__text { font-size: 14px; color: #A16207; margin-top: 6px; }

/* ── Title ───────────────────────────────────────────────── */
.shop-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 16px;
}

/* ── Plan Card ───────────────────────────────────────────── */
.plan-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.plan-card {
  position: relative;
  background: #fff;
  border: 2px solid #E5E7EB;
  border-radius: 14px;
  padding: 18px 16px;
  cursor: pointer;
  transition: all 0.2s;
}

.plan-card--selected {
  border-color: #F0294E;
  background: #FFF5F7;
}

.plan-card--popular {
  border-color: #F0294E;
}

.plan-card__tag {
  position: absolute;
  top: -10px;
  right: 16px;
  background: #F0294E;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 10px;
  border-radius: 9999px;
}

.plan-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.plan-card__name {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
}

.plan-card__price {
  text-align: right;
}

.plan-card__amount {
  font-size: 20px;
  font-weight: 800;
  color: #F0294E;
  font-variant-numeric: tabular-nums;
}

.plan-card__period {
  display: block;
  font-size: 12px;
  color: #9CA3AF;
}

.plan-card__features {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.plan-card__features li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #374151;
}

.plan-card__radio {
  position: absolute;
  top: 18px;
  left: 16px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid #D1D5DB;
}

.plan-card__radio--checked {
  border-color: #F0294E;
  background: #F0294E;
  box-shadow: inset 0 0 0 3px #fff;
}

.plan-card__header { padding-left: 28px; }

/* ── Subscribe Button ────────────────────────────────────── */
.subscribe-btn {
  width: 100%;
  height: 52px;
  border-radius: 12px;
  border: none;
  background: #F0294E;
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  margin-top: 20px;
  transition: all 0.15s;
}

.subscribe-btn:active { transform: scale(0.97); background: #D01A3C; }

/* ── Trial Entry ─────────────────────────────────────────── */
.trial-entry {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 16px;
  padding: 14px;
  background: #FFFBEB;
  border: 1px solid #FDE68A;
  border-radius: 10px;
  font-size: 14px;
  color: #92400E;
  cursor: pointer;
  transition: background 0.15s;
}

.trial-entry:active { background: #FEF3C7; }
</style>
