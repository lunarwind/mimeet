/**
 * composables/usePoints.ts — F40 點數系統
 */
import { ref } from 'vue'
import client from '@/api/client'
import type {
  PointPackage,
  PointTransaction,
  PointBalanceResponse,
  PointPurchaseResponse,
} from '@/types/points'

export function usePoints() {
  const packages = ref<PointPackage[]>([])
  const balance = ref(0)
  const stealthUntil = ref<string | null>(null)
  const stealthActive = ref(false)
  const history = ref<PointTransaction[]>([])
  const loading = ref(false)

  function mapPackage(p: any): PointPackage {
    return {
      id: p.id,
      slug: p.slug,
      name: p.name,
      points: p.points,
      bonusPoints: p.bonus_points,
      totalPoints: p.total_points,
      price: p.price,
      costPerPoint: p.cost_per_point,
      description: p.description ?? null,
      sortOrder: p.sort_order,
    }
  }

  function mapTransaction(t: any): PointTransaction {
    return {
      id: t.id,
      type: t.type,
      amount: t.amount,
      balanceAfter: t.balance_after,
      feature: t.feature ?? null,
      description: t.description ?? null,
      referenceId: t.reference_id ?? null,
      createdAt: t.created_at,
    }
  }

  async function fetchPackages() {
    loading.value = true
    try {
      const res = await client.get('/points/packages')
      packages.value = (res.data?.data ?? []).map(mapPackage)
    } finally {
      loading.value = false
    }
  }

  async function fetchBalance(): Promise<PointBalanceResponse> {
    const res = await client.get('/points/balance')
    const d = res.data?.data ?? {}
    const result: PointBalanceResponse = {
      pointsBalance: d.points_balance ?? 0,
      stealthUntil: d.stealth_until ?? null,
      stealthActive: !!d.stealth_active,
    }
    balance.value = result.pointsBalance
    stealthUntil.value = result.stealthUntil
    stealthActive.value = result.stealthActive
    return result
  }

  async function fetchHistory(page = 1, perPage = 20) {
    loading.value = true
    try {
      const res = await client.get('/points/history', { params: { page, per_page: perPage } })
      history.value = (res.data?.data?.transactions ?? []).map(mapTransaction)
    } finally {
      loading.value = false
    }
  }

  async function purchasePackage(slug: string): Promise<PointPurchaseResponse> {
    const res = await client.post('/points/purchase', { package_slug: slug })
    const d = res.data?.data ?? {}
    return {
      order: {
        id: d.order?.id,
        tradeNo: d.order?.trade_no,
        points: d.order?.points,
        amount: d.order?.amount,
        status: d.order?.status,
      },
      paymentUrl: d.payment_url,
    }
  }

  return {
    packages, balance, stealthUntil, stealthActive, history, loading,
    fetchPackages, fetchBalance, fetchHistory, purchasePackage,
  }
}
