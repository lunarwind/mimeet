<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import TopBar from '@/components/layout/TopBar.vue'

const authStore = useAuthStore()

const isPaid = computed(() => (authStore.user?.membership_level ?? 0) >= 3)
const isEligible = ref(true) // Mock: 假設首次可使用
const isPurchasing = ref(false)
const purchased = ref(false)

const TRIAL_FEATURES = [
  '30 天完整體驗所有付費功能',
  '無限聊天、查看已讀狀態',
  '隱身模式、進階搜尋',
  '每位會員限購一次',
  '不自動續費，到期後恢復免費方案',
]

async function handlePurchase() {
  if (isPurchasing.value) return
  isPurchasing.value = true
  await new Promise(r => setTimeout(r, 1000))
  purchased.value = true
  isPurchasing.value = false
}
</script>

<template>
  <div class="trial-view">
    <TopBar title="體驗訂閱" show-back />

    <div class="trial-body">
      <!-- 已購買完成 -->
      <div v-if="purchased" class="result-card">
        <div class="result-card__icon">🎉</div>
        <h2 class="result-card__title">體驗訂閱開通成功！</h2>
        <p class="result-card__text">您已享有 30 天完整付費功能，盡情探索吧。</p>
      </div>

      <!-- 已付費 -->
      <div v-else-if="isPaid" class="result-card">
        <div class="result-card__icon">✨</div>
        <h2 class="result-card__title">您已是付費會員</h2>
        <p class="result-card__text">無需購買體驗訂閱，您已擁有所有功能。</p>
      </div>

      <!-- 不符資格 -->
      <div v-else-if="!isEligible" class="result-card">
        <div class="result-card__icon">🔒</div>
        <h2 class="result-card__title">已使用過體驗訂閱</h2>
        <p class="result-card__text">每位會員限購一次體驗訂閱。請選擇正式方案。</p>
      </div>

      <!-- 正常購買頁面 -->
      <template v-else>
        <div class="hero-card">
          <div class="hero-card__tag">限時體驗</div>
          <div class="hero-card__price">
            <span class="hero-card__currency">NT$</span>
            <span class="hero-card__amount">199</span>
            <span class="hero-card__period">/ 30 天</span>
          </div>
          <p class="hero-card__original">原價 NT$ 499</p>
        </div>

        <section class="features-section">
          <h3 class="features-section__title">體驗內容</h3>
          <ul class="features-list">
            <li v-for="f in TRIAL_FEATURES" :key="f">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              {{ f }}
            </li>
          </ul>
        </section>

        <div class="notice-box">
          <p>📌 購買後不可退款，不自動續費。</p>
          <p>體驗到期後將恢復為免費方案，已產生的聊天紀錄不會消失。</p>
        </div>

        <button
          class="purchase-btn"
          :disabled="isPurchasing"
          @click="handlePurchase"
        >
          {{ isPurchasing ? '處理中…' : '以 NT$ 199 開始體驗' }}
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.trial-view {
  display: flex;
  flex-direction: column;
  flex: 1;
  background: #F9F9FB;
}

.trial-body {
  flex: 1;
  padding: 16px;
}

/* ── Result Card ─────────────────────────────────────────── */
.result-card {
  text-align: center;
  padding: 48px 16px;
}

.result-card__icon { font-size: 48px; margin-bottom: 16px; }
.result-card__title { font-size: 20px; font-weight: 700; color: #111827; }
.result-card__text { font-size: 14px; color: #6B7280; margin-top: 8px; line-height: 1.5; }

/* ── Hero Card ───────────────────────────────────────────── */
.hero-card {
  background: linear-gradient(135deg, #F0294E, #A80F2C);
  border-radius: 16px;
  padding: 28px 20px;
  text-align: center;
  color: #fff;
  margin-bottom: 20px;
}

.hero-card__tag {
  display: inline-block;
  background: rgba(255,255,255,0.2);
  padding: 3px 12px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 12px;
}

.hero-card__price { display: flex; align-items: baseline; justify-content: center; gap: 4px; }
.hero-card__currency { font-size: 18px; font-weight: 600; }
.hero-card__amount { font-size: 48px; font-weight: 800; font-variant-numeric: tabular-nums; }
.hero-card__period { font-size: 16px; opacity: 0.8; }
.hero-card__original { font-size: 13px; opacity: 0.6; text-decoration: line-through; margin-top: 4px; }

/* ── Features ────────────────────────────────────────────── */
.features-section {
  background: #fff;
  border-radius: 14px;
  padding: 18px 16px;
  border: 1px solid #F1F5F9;
  margin-bottom: 16px;
}

.features-section__title {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 12px;
}

.features-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.features-list li {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: #374151;
}

/* ── Notice ──────────────────────────────────────────────── */
.notice-box {
  background: #FFFBEB;
  border: 1px solid #FDE68A;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 20px;
}

.notice-box p {
  font-size: 12px;
  color: #92400E;
  line-height: 1.5;
}

.notice-box p + p { margin-top: 4px; }

/* ── Purchase Button ─────────────────────────────────────── */
.purchase-btn {
  width: 100%;
  height: 52px;
  border-radius: 12px;
  border: none;
  background: #F0294E;
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.purchase-btn:active { transform: scale(0.97); background: #D01A3C; }
.purchase-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
