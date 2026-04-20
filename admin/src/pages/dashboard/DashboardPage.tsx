import { useState, useRef, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Row, Col, Card, Statistic, Typography, Tag, List, Badge } from 'antd'
import {
  UserOutlined,
  DollarOutlined,
  CrownOutlined,
  FileExclamationOutlined,
  WarningOutlined,
} from '@ant-design/icons'
import * as echarts from 'echarts'
import apiClient from '../../api/client'

const { Title, Text } = Typography

function EChart({ option, style }: { option: echarts.EChartsOption; style?: React.CSSProperties }) {
  const ref = useRef<HTMLDivElement>(null)
  const chartRef = useRef<echarts.ECharts>(null)

  useEffect(() => {
    if (!ref.current) return
    chartRef.current = echarts.init(ref.current as unknown as HTMLElement)
    const onResize = () => chartRef.current?.resize()
    window.addEventListener('resize', onResize)
    return () => {
      window.removeEventListener('resize', onResize)
      chartRef.current?.dispose()
    }
  }, [])

  useEffect(() => {
    chartRef.current?.setOption(option, true)
  }, [option])

  return <div ref={ref} style={style} />
}


interface DashboardStats {
  total_members: number
  month_revenue: number
  paid_members: number
  pending_tickets: number
  level_distribution: { value: number; name: string; itemStyle: { color: string } }[]
  recent_tickets: { id: string; type: string; time: string }[]
  recent_payments: { user: string; plan: string; amount: number; time: string }[]
  // F40 第二排 KPI
  points_sales_month: number
  points_circulating: number
  points_consumed_today: number
  pending_verifications: number
  consumption_by_feature: { name: string; value: number }[]
}

export default function DashboardPage() {
  const navigate = useNavigate()
  const [stats, setStats] = useState<DashboardStats>({
    total_members: 0, month_revenue: 0, paid_members: 0, pending_tickets: 0,
    level_distribution: [], recent_tickets: [], recent_payments: [],
    points_sales_month: 0, points_circulating: 0, points_consumed_today: 0,
    pending_verifications: 0, consumption_by_feature: [],
  })

  useEffect(() => {
    loadDashboardData()
  }, [])

  async function loadDashboardData() {
    // F40 優先嘗試 stats/summary API（補完 A01）
    try {
      const res = await apiClient.get('/admin/stats/summary')
      const d = res.data?.data
      if (d) {
        const cbf = d.points?.consumption_by_feature ?? {}
        const featureNames: Record<string, string> = {
          stealth: '🕶 隱身', super_like: '⭐ 超級讚', reverse_msg: '💬 突破訊息', broadcast: '📢 廣播',
        }
        const consumption = Object.entries(cbf).map(([k, v]) => ({ name: featureNames[k] ?? k, value: Number(v) }))
        setStats(prev => ({
          ...prev,
          total_members: d.members?.total ?? 0,
          month_revenue: d.revenue?.subscription_month ?? 0,
          paid_members: d.members?.paid ?? 0,
          pending_tickets: d.pending_tickets ?? 0,
          points_sales_month: d.revenue?.points_month ?? 0,
          points_circulating: d.points?.circulating ?? 0,
          points_consumed_today: d.points?.consumed_today ?? 0,
          pending_verifications: d.pending_verifications ?? 0,
          consumption_by_feature: consumption,
        }))
      }
    } catch {
      // fallback 下面的聚合
    }

    try {
      // Fetch real data from multiple endpoints
      const [membersRes, ticketsRes, paymentsRes] = await Promise.allSettled([
        apiClient.get('/admin/members', { params: { per_page: 1000 } }),
        apiClient.get('/admin/tickets', { params: { per_page: 100 } }),
        apiClient.get('/admin/payments', { params: { per_page: 100 } }),
      ])

      const members = membersRes.status === 'fulfilled' ? (membersRes.value.data?.data?.members ?? []) : []
      const tickets = ticketsRes.status === 'fulfilled' ? (ticketsRes.value.data?.data?.tickets ?? []) : []
      const payments = paymentsRes.status === 'fulfilled' ? (paymentsRes.value.data?.data?.payments ?? []) : []

      // Calculate stats from real data
      const paidCount = Array.isArray(members) ? members.filter((m: { membership_level: number }) => m.membership_level >= 3).length : 0
      const pendingCount = Array.isArray(tickets) ? tickets.filter((t: { status: string }) => t.status === 'pending').length : 0
      const paidPayments = Array.isArray(payments) ? payments.filter((p: { status: string }) => p.status === 'paid') : []
      const monthRevenue = paidPayments.reduce((sum: number, p: { amount: number }) => sum + (p.amount || 0), 0)

      // Level distribution
      const lvCounts = [0, 0, 0, 0] // Lv0, Lv1, Lv2, Lv3
      if (Array.isArray(members)) {
        members.forEach((m: { membership_level: number }) => {
          const lv = Math.min(m.membership_level, 3)
          lvCounts[lv]++
        })
      }

      setStats(prev => ({
        ...prev,
        total_members: Array.isArray(members) ? members.length : 0,
        month_revenue: monthRevenue,
        paid_members: paidCount,
        pending_tickets: pendingCount,
        level_distribution: [
          { value: lvCounts[0], name: 'Lv0 未驗證', itemStyle: { color: '#9CA3AF' } },
          { value: lvCounts[1], name: 'Lv1 Email驗證', itemStyle: { color: '#3B82F6' } },
          { value: lvCounts[2], name: 'Lv2 進階驗證', itemStyle: { color: '#10B981' } },
          { value: lvCounts[3], name: 'Lv3 付費會員', itemStyle: { color: '#F0294E' } },
        ],
        recent_tickets: Array.isArray(tickets) ? tickets.slice(0, 5).map((t: { id: number; type: string; created_at: string }) => ({
          id: `TK-${t.id}`, type: t.type, time: t.created_at ? new Date(t.created_at).toLocaleString('zh-TW') : '',
        })) : [],
        recent_payments: Array.isArray(payments) ? paidPayments.slice(0, 5).map((p: { user?: { nickname: string }; plan_name?: string; amount: number; paid_at?: string }) => ({
          user: p.user?.nickname || '—', plan: p.plan_name || '—', amount: p.amount,
          time: p.paid_at ? new Date(p.paid_at).toLocaleString('zh-TW') : '',
        })) : [],
      }))
    } catch {
      // API unavailable — show zeros
    }
  }

  // Empty chart data when DB is clean
  const emptyChart = { labels: [] as string[], male: [] as number[], female: [] as number[] }

  const lineOption: echarts.EChartsOption = {
    tooltip: { trigger: 'axis' },
    legend: { data: ['男性', '女性'] },
    xAxis: { data: emptyChart.labels },
    yAxis: {},
    series: [
      { name: '男性', type: 'line', smooth: true, data: emptyChart.male, itemStyle: { color: '#3B82F6' } },
      { name: '女性', type: 'line', smooth: true, data: emptyChart.female, itemStyle: { color: '#F0294E' } },
    ],
  }

  const pieOption: echarts.EChartsOption = {
    tooltip: { formatter: '{b}: {c} 人 ({d}%)' },
    legend: {
      orient: 'vertical',
      right: 10,
      top: 'center',
      formatter: (name: string) => {
        const item = stats.level_distribution.find((d) => d.name === name)
        return `${name}  ${item?.value ?? 0}`
      },
    },
    series: [
      {
        type: 'pie',
        radius: ['45%', '70%'],
        center: ['35%', '50%'],
        data: stats.level_distribution,
        label: { show: false },
      },
    ],
  }

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>儀表板</Title>

      {/* KPI Cards */}
      <Row gutter={[16, 16]}>
        <Col span={6}>
          <Card>
            <Statistic title="會員總數" value={stats.total_members} prefix={<UserOutlined />} />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic title="付款總額" value={`NT$ ${stats.month_revenue.toLocaleString()}`} prefix={<DollarOutlined />} />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic title="付費會員數" value={stats.paid_members} prefix={<CrownOutlined />} />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic
              title="待處理 Ticket"
              value={stats.pending_tickets}
              prefix={<FileExclamationOutlined />}
              valueStyle={stats.pending_tickets > 10 ? { color: '#F0294E' } : undefined}
              suffix={
                stats.pending_tickets > 10 ? (
                  <Badge dot><WarningOutlined style={{ color: '#F0294E' }} /></Badge>
                ) : null
              }
            />
          </Card>
        </Col>
      </Row>

      {/* F40 第二排 KPI 卡片 */}
      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col span={6}>
          <Card>
            <Statistic title="💎 本月點數銷售" value={`NT$ ${stats.points_sales_month.toLocaleString()}`} />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic title="全站流通點數" value={stats.points_circulating} suffix="點" />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic title="今日消費量" value={stats.points_consumed_today} suffix="點" />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic title="待處理驗證" value={stats.pending_verifications}
              valueStyle={stats.pending_verifications > 5 ? { color: '#F59E0B' } : undefined} />
          </Card>
        </Col>
      </Row>

      {/* 點數消費分布 */}
      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col span={24}>
          <Card title="💎 本月點數消費分布">
            {stats.consumption_by_feature.length === 0 ? (
              <div style={{ padding: 40, textAlign: 'center', color: '#9CA3AF' }}>目前無消費資料</div>
            ) : (
              <EChart
                style={{ height: 260 }}
                option={{
                  tooltip: { trigger: 'item', formatter: '{b}: {c} 點 ({d}%)' },
                  legend: { orient: 'horizontal', bottom: 0 },
                  series: [{
                    name: '點數消費', type: 'pie', radius: ['40%', '70%'],
                    data: stats.consumption_by_feature,
                    label: { formatter: '{b}\n{d}%' },
                  }],
                }}
              />
            )}
          </Card>
        </Col>
      </Row>

      {/* Charts */}
      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col span={16}>
          <Card title="新增會員趨勢">
            {stats.total_members === 0 ? (
              <div style={{ height: 300, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9CA3AF' }}>
                目前無會員資料
              </div>
            ) : (
              <EChart option={lineOption} style={{ height: 300 }} />
            )}
          </Card>
        </Col>
        <Col span={8}>
          <Card title="會員等級分佈">
            {stats.total_members === 0 ? (
              <div style={{ height: 300, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9CA3AF' }}>
                目前無會員資料
              </div>
            ) : (
              <EChart option={pieOption} style={{ height: 300 }} />
            )}
          </Card>
        </Col>
      </Row>

      {/* Recent Activity */}
      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col span={12}>
          <Card title="最新 Ticket" extra={<a onClick={() => navigate('/tickets')}>查看全部</a>}>
            {stats.recent_tickets.length === 0 ? (
              <Text type="secondary">目前無回報案件</Text>
            ) : (
              <List
                dataSource={stats.recent_tickets}
                renderItem={(item) => (
                  <List.Item>
                    <Tag color="orange">{item.type}</Tag> {item.id} <Text type="secondary">{item.time}</Text>
                  </List.Item>
                )}
              />
            )}
          </Card>
        </Col>
        <Col span={12}>
          <Card title="最新付款" extra={<a onClick={() => navigate('/payments')}>查看全部</a>}>
            {stats.recent_payments.length === 0 ? (
              <Text type="secondary">目前無支付記錄</Text>
            ) : (
              <List
                dataSource={stats.recent_payments}
                renderItem={(item) => (
                  <List.Item>
                    {item.user} <Tag color="blue">{item.plan}</Tag> <Text strong>NT${item.amount}</Text>{' '}
                    <Text type="secondary">{item.time}</Text>
                  </List.Item>
                )}
              />
            )}
          </Card>
        </Col>
      </Row>
    </div>
  )
}
