import dayjs from 'dayjs'

export const mockSummary = {
  today_new_members: 42,
  today_new_members_pct: 12.5,
  month_revenue: 158400,
  month_revenue_pct: -3.2,
  paid_members_total: 234,
  paid_members_pct: 8.1,
  pending_tickets: 13,
}

export const mockLevelDistribution = [
  { value: 120, name: 'Lv0 未驗證',   itemStyle: { color: '#9CA3AF' } },
  { value: 340, name: 'Lv1 Email驗證', itemStyle: { color: '#3B82F6' } },
  { value: 189, name: 'Lv2 進階驗證', itemStyle: { color: '#10B981' } },
  { value: 234, name: 'Lv3 付費會員', itemStyle: { color: '#F0294E' } },
]

export const mockRegistrationChart = (() => {
  const labels: string[] = []
  const male: number[] = []
  const female: number[] = []
  for (let i = 29; i >= 0; i--) {
    labels.push(dayjs().subtract(i, 'day').format('MM/DD'))
    male.push(Math.floor(Math.random() * 80) + 20)
    female.push(Math.floor(Math.random() * 120) + 30)
  }
  return { labels, male, female }
})()

export const mockHourlyChart = (() => {
  const labels: string[] = []
  const male: number[] = []
  const female: number[] = []
  for (let h = 0; h < 24; h++) {
    labels.push(`${String(h).padStart(2, '0')}:00`)
    male.push(Math.floor(Math.random() * 15))
    female.push(Math.floor(Math.random() * 20))
  }
  return { labels, male, female }
})()

export const mockRecentTickets = [
  { id: 'TK-1024', type: '用戶檢舉',  time: '3 分鐘前' },
  { id: 'TK-1023', type: '系統問題',  time: '12 分鐘前' },
  { id: 'TK-1022', type: '用戶檢舉',  time: '35 分鐘前' },
  { id: 'TK-1021', type: '申訴解停',  time: '1 小時前' },
  { id: 'TK-1020', type: '取消訂閱',  time: '2 小時前' },
]

export const mockRecentPayments = [
  { user: '甜心001',  plan: '月費方案', amount: 399,  time: '5 分鐘前' },
  { user: '陽光男孩', plan: '季費方案', amount: 1077, time: '18 分鐘前' },
  { user: '薔薇女孩', plan: '年費方案', amount: 3832, time: '1 小時前' },
  { user: '帥氣達達', plan: '週費方案', amount: 149,  time: '2 小時前' },
  { user: '甜蜜公主', plan: '月費方案', amount: 399,  time: '3 小時前' },
]
