/**
 * types/payment.ts
 * 訂閱/支付相關型別定義
 */

export interface SubscriptionPlan {
  id: string
  slug: string
  type: string
  name: string
  price: number
  originalPrice: number | null
  durationDays: number
  features: string[]
  isPopular?: boolean
}

export interface CurrentSubscription {
  planType: string
  planName: string
  expiresAt: string
  autoRenew: boolean
  daysRemaining: number
}

/** ECPay AIO 跳轉回傳格式（Step 5 統一後）*/
export interface EcpayAioResponse {
  payment_id: number
  aio_url: string
  params: Record<string, string | number>
  order?: {
    id: number
    order_number: string
    amount: number
    status: string
    expires_at?: string
  }
}

/** @deprecated 改用 EcpayAioResponse */
export interface CreateOrderResponse {
  payment_url?: string
  orderUrl?: string
  aio_url?: string
  params?: Record<string, string | number>
  payment_id?: number
  order?: {
    id: number
    order_number: string
    amount: number
    status: string
  }
}

export interface TrialInfo {
  available: boolean    // trial_available：後台是否有啟用中的體驗方案
  isEligible: boolean   // is_eligible：當前用戶是否還能購買
  notice: string        // 購買注意事項文案
  plan: {
    id: string
    name: string
    durationDays: number
    price: number
    currency: string
    features: string[]
  } | null
}
