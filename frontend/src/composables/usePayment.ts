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
      const res = await client.get<{ data: { plans: SubscriptionPlan[] } }>('/subscriptions/plans')
      plans.value = res.data.data.plans
      return plans.value
    } catch (e) {
      error.value = '載入方案失敗'
      console.error('[usePayment] fetchPlans error:', e)
      return []
    } finally {
      isLoading.value = false
    }
  }

  async function fetchCurrentSubscription(): Promise<CurrentSubscription | null> {
    isLoading.value = true
    error.value = null
    try {
      const res = await client.get<{ data: { subscription: CurrentSubscription | null } }>('/subscriptions/me')
      currentSubscription.value = res.data.data.subscription
      return currentSubscription.value
    } catch (e) {
      error.value = '載入訂閱狀態失敗'
      console.error('[usePayment] fetchCurrentSubscription error:', e)
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function createOrder(planType: string): Promise<CreateOrderResponse | null> {
    isLoading.value = true
    error.value = null
    try {
      const res = await client.post<{ data: CreateOrderResponse }>('/subscriptions/orders', {
        data: { plan_type: planType },
      })
      return res.data.data
    } catch (e) {
      error.value = '建立訂單失敗'
      console.error('[usePayment] createOrder error:', e)
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function cancelSubscription(reason: string): Promise<boolean> {
    isLoading.value = true
    error.value = null
    try {
      await client.post('/subscriptions/cancel-request', { data: { reason } })
      return true
    } catch (e) {
      error.value = '取消訂閱失敗'
      console.error('[usePayment] cancelSubscription error:', e)
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function fetchTrialInfo(): Promise<TrialInfo | null> {
    isLoading.value = true
    try {
      const res = await client.get<{ data: TrialInfo }>('/subscription/trial')
      trialInfo.value = res.data.data
      return trialInfo.value
    } catch (e) {
      console.error('[usePayment] fetchTrialInfo error:', e)
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function purchaseTrial(): Promise<CreateOrderResponse | null> {
    isLoading.value = true
    try {
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
      await client.patch('/me/subscription/auto-renew', { auto_renew: value })
      if (currentSubscription.value) currentSubscription.value.autoRenew = value
      return true
    } catch (e) {
      console.error('[usePayment] toggleAutoRenew error:', e)
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
