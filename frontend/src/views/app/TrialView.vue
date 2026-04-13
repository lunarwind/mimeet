<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import { usePayment } from '@/composables/usePayment'
import { useUiStore } from '@/stores/ui'

const uiStore = useUiStore()
const { trialInfo, isPaid, isLoading, fetchTrialInfo, purchaseTrial } = usePayment()

const showSuccess = ref(false)

onMounted(() => { fetchTrialInfo() })

async function handlePurchase() {
  const result = await purchaseTrial()
  if (result) {
    window.location.href = result.orderUrl
  } else {
    uiStore.showToast('購買失敗，請稍後再試', 'error')
  }
}

const FEATURES = [
  '無限聊天 — 不限次數傳送訊息',
  '已讀回執 — 知道對方是否已讀',
  'QR 約會驗證 — 見面掃碼加誠信分',
  '動態發布 — 分享生活吸引對象',
  '隱身模式 — 瀏覽不留足跡',
  '進階搜尋 — 精準篩選心儀對象',
]
</script>

<template>
  <AppLayout title="新手體驗方案" :show-back="true">
    <div class="trial-page">
      <!-- 已使用過 -->
      <div v-if="trialInfo && !trialInfo.isEligible" class="used-notice">
        <div class="used-notice__icon">ℹ️</div>
        <div class="used-notice__title">您已使用過體驗方案</div>
        <p class="used-notice__desc">每位會員限購一次體驗方案，您可以選擇正式訂閱方案繼續享受全功能。</p>
        <button class="btn-primary btn-full" @click="$router.push('/app/shop')">查看正式方案</button>
      </div>

      <!-- 可購買 -->
      <template v-else>
        <div class="trial-hero">
          <div class="trial-hero__price">
            <span class="trial-hero__currency">NT$</span>
            <span class="trial-hero__amount">199</span>
          </div>
          <div class="trial-hero__duration">體驗 30 天</div>
          <div class="trial-hero__subtitle">全功能解鎖，感受 MiMeet 的完整體驗</div>
        </div>

        <section class="trial-features">
          <h3 class="trial-features__title">包含功能</h3>
          <ul class="trial-features__list">
            <li v-for="f in FEATURES" :key="f">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              <span>{{ f }}</span>
            </li>
          </ul>
        </section>

        <section class="trial-notice">
          <h3 class="trial-notice__title">注意事項</h3>
          <ul>
            <li>到期後不會自動續費</li>
            <li>每位會員限購一次</li>
            <li>購買後不可退款</li>
            <li>體驗期間享有與付費會員完全相同的功能</li>
          </ul>
        </section>

        <button
          class="btn-primary btn-full btn-lg"
          :disabled="isLoading"
          @click="handlePurchase"
        >
          {{ isLoading ? '處理中...' : '立即購買 NT$199' }}
        </button>
      </template>

      <!-- Mock 成功 -->
      <div v-if="showSuccess" class="modal-overlay" @click="showSuccess = false">
        <div class="mock-success" @click.stop>
          <div class="mock-success__icon">🎉</div>
          <div class="mock-success__title">購買成功（Mock）</div>
          <p class="mock-success__desc">體驗方案已啟用，為期 30 天</p>
          <button class="btn-primary btn-full" @click="$router.push('/app/explore')">開始探索</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style>
.trial-page { padding: 16px; }

.trial-hero { text-align: center; padding: 32px 0 24px; }
.trial-hero__price { display: flex; align-items: baseline; justify-content: center; gap: 4px; }
.trial-hero__currency { font-size: 20px; font-weight: 600; color: #F0294E; }
.trial-hero__amount { font-size: 56px; font-weight: 800; color: #F0294E; line-height: 1; font-variant-numeric: tabular-nums; }
.trial-hero__duration { font-size: 18px; font-weight: 600; color: #374151; margin-top: 4px; }
.trial-hero__subtitle { font-size: 14px; color: #6B7280; margin-top: 8px; }

.trial-features { margin-bottom: 20px; }
.trial-features__title { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 10px; }
.trial-features__list { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.trial-features__list li { display: flex; align-items: center; gap: 10px; font-size: 14px; color: #374151; }

.trial-notice { background: #FFF5F7; border-radius: 12px; padding: 16px; margin-bottom: 24px; }
.trial-notice__title { font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 8px; }
.trial-notice ul { padding-left: 18px; }
.trial-notice li { font-size: 13px; color: #6B7280; margin-bottom: 4px; }

.used-notice { text-align: center; padding: 40px 16px; }
.used-notice__icon { font-size: 40px; margin-bottom: 12px; }
.used-notice__title { font-size: 18px; font-weight: 700; color: #111827; }
.used-notice__desc { font-size: 14px; color: #6B7280; margin: 8px 0 24px; }

.btn-full { width: 100%; }
.btn-lg { padding: 14px; font-size: 16px; }
</style>
