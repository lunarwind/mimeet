/**
 * usePayment.ts
 * 訂閱/支付邏輯 composable
 * 對應 API-001 §7 / §10.3 / §10.5
 */
import { ref, computed } from 'vue'
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type {
  SubscriptionPlan,
  CurrentSubscription,
  CreateOrderResponse,
  TrialInfo,
} from '@/types/payment'

const USE_MOCK = import.meta.env.DEV

function delay(ms: number) {
  return new Promise(resolve => setTimeout(resolve, ms))
}

// ── Mock 資料 ──────────────────────────────────────────────
const MOCK_PLANS: SubscriptionPlan[] = [
  {
    id: 1,
    type: 'weekly',
    name: '週費方案',
    price: 199,
    originalPrice: null,
    durationDays: 7,
    features: ['無限聊天', '已讀回執', '進階搜尋'],
  },
  {
    id: 2,
    type: 'monthly',
    name: '月費方案',
    price: 499,
    originalPrice: null,
    durationDays: 30,
    features: ['無限聊天', '已讀回執', '進階搜尋', 'QR 約會驗證', '動態發布'],
    isPopular: true,
  },
  {
    id: 3,
    type: 'quarterly',
    name: '季費方案',
    price: 1199,
    originalPrice: 1497,
    durationDays: 90,
    features: ['無限聊天', '已讀回執', '進階搜尋', 'QR 約會驗證', '動態發布', '隱身模式'],
  },
  {
    id: 4,
    type: 'yearly',
    name: '年費方案',
    price: 3999,
    originalPrice: 5988,
    durationDays: 365,
    features: ['所有功能解鎖', '專屬客服', '優先推薦曝光'],
  },
]

export function usePayment() {
  const authStore = useAuthStore()

  const plans = ref<SubscriptionPlan[]>([])
  const currentSubscription = ref<CurrentSubscription | null>(null)
  const trialInfo = ref<TrialInfo | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const isPaid = computed(() => (authStore.user?.membership_level ?? 0) >= 3)
  const isExpiringSoon = computed(() => {
    if (!currentSubscription.value) return false
    return currentSubscription.value.daysRemaining <= 7
  })
  const daysRemaining = computed(() => currentSubscription.value?.daysRemaining ?? 0)
  const currentPlan = computed(() => currentSubscription.value)

  async function fetchPlans(): Promise<SubscriptionPlan[]> {
    isLoading.value = true
    error.value = null
    try {
      if (USE_MOCK) {
        await delay(300)
        plans.value = MOCK_PLANS
        return MOCK_PLANS
      }
      const res = await client.get<{ data: { plans: SubscriptionPlan[] } }>('/subscriptions/plans')
      plans.value = res.data.data.plans
      return plans.value
    } catch (e) {
      error.value = '載入方案失敗'
      // Error handled via error ref
      return []
    } finally {
      isLoading.value = false
    }
  }

  async function fetchCurrentSubscription(): Promise<CurrentSubscription | null> {
    isLoading.value = true
    error.value = null
    try {
      if (USE_MOCK) {
        await delay(200)
        if (isPaid.value) {
          const sub: CurrentSubscription = {
            planType: 'monthly',
            planName: '月費方案',
            expiresAt: new Date(Date.now() + 30 * 86400000).toISOString(),
            autoRenew: true,
            daysRemaining: 30,
          }
          currentSubscription.value = sub
          return sub
        }
        currentSubscription.value = null
        return null
      }
      const res = await client.get<{ data: { subscription: CurrentSubscription | null } }>('/subscriptions/me')
      currentSubscription.value = res.data.data.subscription
      return currentSubscription.value
    } catch (e) {
      error.value = '載入訂閱狀態失敗'
      // Error handled via error ref
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function createOrder(planType: string): Promise<CreateOrderResponse | null> {
    isLoading.value = true
    error.value = null
    try {
      if (USE_MOCK) {
        await delay(500)
        return {
          orderUrl: '#/app/shop?mock_payment=success',
          orderId: `ORD${Date.now()}`,
        }
      }
      const res = await client.post<{ data: CreateOrderResponse }>('/subscriptions/orders', {
        data: { plan_type: planType },
      })
      return res.data.data
    } catch (e) {
      error.value = '建立訂單失敗'
      // Error handled via error ref
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function cancelSubscription(reason: string): Promise<boolean> {
    isLoading.value = true
    error.value = null
    try {
      if (USE_MOCK) {
        await delay(500)
        return true
      }
      await client.post('/subscriptions/cancel-request', { data: { reason } })
      return true
    } catch (e) {
      error.value = '取消訂閱失敗'
      // Error handled via error ref
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function fetchTrialInfo(): Promise<TrialInfo | null> {
    isLoading.value = true
    try {
      if (USE_MOCK) {
        await delay(200)
        const info: TrialInfo = {
          available: true,
          price: 199,
          durationDays: 30,
          isEligible: !isPaid.value,
        }
        trialInfo.value = info
        return info
      }
      const res = await client.get<{ data: TrialInfo }>('/subscription/trial')
      trialInfo.value = res.data.data
      return trialInfo.value
    } catch (e) {
      // Error silently handled
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function purchaseTrial(): Promise<CreateOrderResponse | null> {
    isLoading.value = true
    try {
      if (USE_MOCK) {
        await delay(500)
        return {
          orderUrl: '#/app/shop?mock_payment=trial_success',
          orderId: `TRIAL${Date.now()}`,
        }
      }
      const res = await client.post<{ data: CreateOrderResponse }>('/subscription/trial/purchase', {
        data: { payment_method: 'green_world' },
      })
      return res.data.data
    } catch (e) {
      error.value = '購買體驗方案失敗'
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function toggleAutoRenew(value: boolean): Promise<boolean> {
    try {
      if (USE_MOCK) {
        await delay(300)
        if (currentSubscription.value) currentSubscription.value.autoRenew = value
        return true
      }
      await client.patch('/me/subscription/auto-renew', { auto_renew: value })
      if (currentSubscription.value) currentSubscription.value.autoRenew = value
      return true
    } catch (e) {
      // Error silently handled
      return false
    }
  }

  return {
    plans,
    currentSubscription,
    trialInfo,
    isLoading,
    error,
    isPaid,
    isExpiringSoon,
    daysRemaining,
    currentPlan,
    fetchPlans,
    fetchCurrentSubscription,
    createOrder,
    cancelSubscription,
    fetchTrialInfo,
    purchaseTrial,
    toggleAutoRenew,
  }
}
