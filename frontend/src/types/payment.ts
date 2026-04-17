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

export interface CreateOrderResponse {
  payment_url?: string
  orderUrl?: string
  order?: {
    id: number
    order_number: string
    amount: number
    status: string
  }
}

export interface TrialInfo {
  available: boolean
  price: number
  durationDays: number
  isEligible: boolean
}
