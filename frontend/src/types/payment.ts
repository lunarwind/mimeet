/**
 * types/payment.ts
 * 訂閱/支付相關型別定義
 */

export interface SubscriptionPlan {
  id: number
  type: 'weekly' | 'monthly' | 'quarterly' | 'yearly'
  name: string
  price: number
  originalPrice: number | null
  durationDays: number
  features: string[]
  isPopular?: boolean
}

export interface CurrentSubscription {
  planType: 'weekly' | 'monthly' | 'quarterly' | 'yearly'
  planName: string
  expiresAt: string
  autoRenew: boolean
  daysRemaining: number
}

export interface CreateOrderResponse {
  orderUrl: string
  orderId: string
}

export interface TrialInfo {
  available: boolean
  price: number
  durationDays: number
  isEligible: boolean
}
