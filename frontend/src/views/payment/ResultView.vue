<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface PaymentData {
  payment_id: number
  order_no: string
  type: 'verification' | 'subscription' | 'points'
  status: 'pending' | 'paid' | 'failed' | 'cancelled' | 'refunded' | 'refund_failed'
  amount: number
  paid_at: string | null
  business_data: Record<string, unknown>
}

const route  = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const orderNo   = ref<string>('')
const payment   = ref<PaymentData | null>(null)
const loading   = ref(true)
const error     = ref<string | null>(null)
const pollCount = ref(0)
let pollTimer: ReturnType<typeof setTimeout> | null = null

const MAX_POLLS = 5

onMounted(async () => {
  const hash = window.location.hash
  const searchStr = hash.includes('?') ? hash.split('?')[1] : ''
  const params = new URLSearchParams(searchStr)

  orderNo.value = params.get('order_no') ?? (route.query.order_no as string) ?? ''

  if (!orderNo.value) {
    error.value = '找不到訂單號，請返回重試'
    loading.value = false
    return
  }

  await fetchPayment()
})

onUnmounted(() => {
  if (pollTimer) clearTimeout(pollTimer)
})

async function fetchPayment() {
  loading.value = true
  error.value = null
  try {
    const res = await client.get(`/payments/${orderNo.value}`)
    payment.value = res.data.data
    loading.value = false

    // 若還是 pending，最多 polling 5 次（每 3 秒）
    if (payment.value?.status === 'pending' && pollCount.value < MAX_POLLS) {
      pollCount.value++
      pollTimer = setTimeout(fetchPayment, 3000)
    }
  } catch {
    loading.value = false
    error.value = '查詢訂單狀態失敗，請稍後重新整理'
  }
}

function successMessage(p: PaymentData): string {
  const bd = p.business_data ?? {}
  switch (p.type) {
    case 'verification':
      return '✅ 身份驗證完成，誠信分數 +15！\n3-5 個工作日內退還 NT$100 驗證金。'
    case 'subscription': {
      const expiresAt = bd.expires_at as string | null
      if (expiresAt) {
        const d = new Date(expiresAt).toLocaleDateString('zh-TW')
        return `✅ 訂閱方案已啟用，有效至 ${d}`
      }
      return '✅ 訂閱方案已啟用'
    }
    case 'points': {
      const balance = bd.balance as number | null
      return balance != null ? `✅ 加值成功！目前點數 ${balance} 點` : '✅ 點數加值成功'
    }
    default:
      return '✅ 付款完成'
  }
}

function typeLabel(type: string): string {
  return { verification: '信用卡身份驗證', subscription: '會員訂閱', points: '點數加值' }[type] ?? type
}

function goBack() {
  if (!payment.value) { router.back(); return }
  switch (payment.value.type) {
    case 'verification': router.push('/app/settings/verify'); break
    case 'subscription': router.push('/app/shop'); break
    case 'points':       router.push('/app/shop?tab=points'); break
    default:             router.push('/app/explore'); break
  }
}
</script>

<template>
  <div class="result-view">
    <div class="result-card">
      <!-- Loading -->
      <div v-if="loading" class="result-state">
        <div class="result-spinner" />
        <p class="result-title">查詢中…</p>
        <p class="result-desc">
          {{ pollCount > 0 ? `等待付款確認（第 ${pollCount}/${MAX_POLLS} 次查詢）` : '正在取得訂單狀態' }}
        </p>
      </div>

      <!-- Error -->
      <div v-else-if="error || !payment" class="result-state result-state--error">
        <div class="result-icon">❌</div>
        <p class="result-title">查詢失敗</p>
        <p class="result-desc">{{ error ?? '找不到訂單' }}</p>
        <button class="result-btn" @click="fetchPayment">重新查詢</button>
      </div>

      <!-- Paid: success -->
      <div v-else-if="payment.status === 'paid'" class="result-state result-state--success">
        <div class="result-icon">🎉</div>
        <p class="result-title">{{ typeLabel(payment.type) }}</p>
        <p class="result-desc result-desc--success" style="white-space: pre-line">
          {{ successMessage(payment) }}
        </p>
        <p class="result-order">訂單號：{{ payment.order_no }}</p>
        <button class="result-btn" @click="goBack">返回</button>
      </div>

      <!-- Failed -->
      <div v-else-if="payment.status === 'failed'" class="result-state result-state--error">
        <div class="result-icon">❌</div>
        <p class="result-title">付款未完成</p>
        <p class="result-desc">訂單已取消，請重新嘗試。</p>
        <p class="result-order">訂單號：{{ payment.order_no }}</p>
        <button class="result-btn" @click="goBack">重新購買</button>
      </div>

      <!-- Pending（polling 結束後仍 pending）-->
      <div v-else-if="payment.status === 'pending'" class="result-state result-state--pending">
        <div class="result-icon">⏳</div>
        <p class="result-title">付款處理中</p>
        <p class="result-desc">
          付款正在確認，通常在 30 秒內完成。<br>
          您也可以稍後重新整理此頁面查看結果。
        </p>
        <p class="result-order">訂單號：{{ payment.order_no }}</p>
        <button class="result-btn result-btn--secondary" @click="fetchPayment">重新查詢</button>
        <button class="result-btn" @click="goBack">返回首頁</button>
      </div>

      <!-- Refunded / Cancelled -->
      <div v-else class="result-state">
        <div class="result-icon">ℹ️</div>
        <p class="result-title">訂單狀態：{{ payment.status }}</p>
        <p class="result-order">訂單號：{{ payment.order_no }}</p>
        <button class="result-btn" @click="goBack">返回</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.result-view {
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #F9F9FB;
  padding: 24px;
}
.result-card {
  background: white;
  border-radius: 16px;
  padding: 40px 32px;
  max-width: 420px;
  width: 100%;
  box-shadow: 0 4px 24px rgba(0,0,0,0.08);
  text-align: center;
}
.result-state { display: flex; flex-direction: column; align-items: center; gap: 12px; }
.result-icon { font-size: 48px; line-height: 1; }
.result-title { font-size: 20px; font-weight: 700; color: #111827; margin: 0; }
.result-desc { font-size: 14px; color: #6B7280; margin: 0; line-height: 1.6; }
.result-desc--success { color: #065F46; }
.result-order { font-size: 12px; color: #9CA3AF; font-family: monospace; }
.result-btn {
  width: 100%;
  padding: 14px;
  border-radius: 10px;
  border: none;
  background: #F0294E;
  color: white;
  font-weight: 600;
  font-size: 16px;
  cursor: pointer;
  margin-top: 4px;
}
.result-btn--secondary {
  background: #F3F4F6;
  color: #374151;
  margin-bottom: 4px;
}
.result-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #E5E7EB;
  border-top-color: #F0294E;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
