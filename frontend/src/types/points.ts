/**
 * types/points.ts — F40 點數系統
 */
export interface PointPackage {
  id: number
  slug: string
  name: string
  points: number
  bonusPoints: number
  totalPoints: number
  price: number
  costPerPoint: number
  description: string | null
  sortOrder: number
}

export type PointTransactionType =
  | 'purchase'
  | 'consume'
  | 'refund'
  | 'admin_gift'
  | 'admin_deduct'

export interface PointTransaction {
  id: number
  type: PointTransactionType
  amount: number
  balanceAfter: number
  feature: string | null
  description: string | null
  referenceId: number | null
  createdAt: string
}

export interface PointBalanceResponse {
  pointsBalance: number
  stealthUntil: string | null
  stealthActive: boolean
}

export interface PointPurchaseResponse {
  order: {
    id: number
    tradeNo: string
    points: number
    amount: number
    status: string
  }
  aioUrl: string
  params: Record<string, string | number>
}
