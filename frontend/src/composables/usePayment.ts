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

  const isPaid = computed(() => (authStore.user?.membership_level ?? 0) >= 2)
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
      const res = await client.get('/subscriptions/plans')
      const raw = res.data.data?.plans ?? res.data.data ?? []
      // Map backend field names to frontend type
      plans.value = raw.map((p: any) => ({
        id: p.id,
        slug: p.slug ?? p.id,
        type: p.slug ?? p.id,
        name: p.name,
        price: p.price,
        originalPrice: p.original_price ?? null,
        durationDays: p.duration_days ?? p.durationDays ?? 30,
        features: p.features ?? [],
        isPopular: p.is_popular ?? (p.slug ?? p.id) === 'plan_monthly',
      }))
      return plans.value
    } catch (e) {
      error.value = '載入方案失敗'
      return []
    } finally {
      isLoading.value = false
    }
  }

  async function fetchCurrentSubscription(): Promise<CurrentSubscription | null> {
    isLoading.value = true
    error.value = null
    try {
      const res = await client.get('/subscriptions/me')
      const raw = res.data?.data?.subscription
      if (!raw) {
        currentSubscription.value = null
        return null
      }
      // Map backend snake_case → frontend camelCase
      const sub: CurrentSubscription = {
        planType: raw.plan_id ?? raw.planType ?? '',
        planName: raw.plan_name ?? raw.planName ?? '',
        expiresAt: raw.expires_at ?? raw.expiresAt ?? '',
        autoRenew: raw.auto_renew ?? raw.autoRenew ?? false,
        daysRemaining: raw.days_remaining ?? raw.daysRemaining
          ?? (raw.expires_at
            ? Math.max(0, Math.ceil((new Date(raw.expires_at).getTime() - Date.now()) / 86400000))
            : 0),
      }
      currentSubscription.value = sub
      return sub
    } catch (e) {
      error.value = '載入訂閱狀態失敗'
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
        plan_id: planType,
      })
      return res.data.data
    } catch (e) {
      error.value = '建立訂單失敗'
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function cancelSubscription(reason: string): Promise<boolean> {
    isLoading.value = true
    error.value = null
    try {
      await client.post('/subscriptions/cancel-request', { reason })
      return true
    } catch (e) {
      error.value = '取消訂閱失敗'
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function fetchTrialInfo(): Promise<TrialInfo | null> {
    isLoading.value = true
    try {
      const res = await client.get('/subscription/trial')
      const raw = res.data?.data ?? {}
      // 優先讀頂層扁平欄位（新格式），fallback 到巢狀 plan（舊格式）
      trialInfo.value = {
        trial_available: raw.trial_available ?? (raw.plan != null),
        is_eligible: raw.is_eligible ?? raw.eligible ?? false,
        price: raw.price ?? raw.plan?.price ?? 0,
        duration_days: raw.duration_days ?? raw.plan?.duration_days ?? 0,
        notice: raw.notice,
      }
      return trialInfo.value
    } catch (e) {
      error.value = '載入體驗方案資訊失敗'
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function purchaseTrial(): Promise<CreateOrderResponse | null> {
    isLoading.value = true
    try {
      const res = await client.post<{ data: CreateOrderResponse }>('/subscription/trial/purchase', {
        payment_method: 'credit_card',
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
      await client.patch('/subscriptions/me', { auto_renew: value })
      if (currentSubscription.value) currentSubscription.value.autoRenew = value
      return true
    } catch (e) {
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
