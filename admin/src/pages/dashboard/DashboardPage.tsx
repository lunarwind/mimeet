import { useState, useRef, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Row, Col, Card, Statistic, Typography, Tag, List, Badge, Segmented } from 'antd'
import {
  UserOutlined,
  DollarOutlined,
  CrownOutlined,
  FileExclamationOutlined,
  ArrowUpOutlined,
  ArrowDownOutlined,
  WarningOutlined,
} from '@ant-design/icons'
import * as echarts from 'echarts'
import apiClient from '../../api/client'
import {
  mockSummary,
  mockLevelDistribution,
  mockRegistrationChart,
  mockHourlyChart,
  mockRecentTickets,
  mockRecentPayments,
} from '../../mocks/dashboard'

const { Title, Text } = Typography

function EChart({ option, style }: { option: echarts.EChartsOption; style?: React.CSSProperties }) {
  const ref = useRef<HTMLDivElement>(null)
  const chartRef = useRef<echarts.ECharts>()

  useEffect(() => {
    if (!ref.current) return
    chartRef.current = echarts.init(ref.current)
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

function TrendSuffix({ pct }: { pct: number }) {
  if (pct > 0) return <span style={{ color: '#52c41a', fontSize: 14 }}><ArrowUpOutlined /> {pct}%</span>
  if (pct < 0) return <span style={{ color: '#ff4d4f', fontSize: 14 }}><ArrowDownOutlined /> {Math.abs(pct)}%</span>
  return null
}

export default function DashboardPage() {
  const navigate = useNavigate()
  const [chartMode, setChartMode] = useState<string>('近 30 天（按天）')

  // State for API data with mock fallback
  const [summary, setSummary] = useState(mockSummary)
  const [levelDist, setLevelDist] = useState(mockLevelDistribution)
  const [regChart, setRegChart] = useState(mockRegistrationChart)
  const [hourlyChart, setHourlyChart] = useState(mockHourlyChart)
  const [recentTickets, setRecentTickets] = useState(mockRecentTickets)
  const [recentPayments, setRecentPayments] = useState(mockRecentPayments)

  useEffect(() => {
    // Try real API first, fall back to mock data on failure
    apiClient.get('/admin/stats/summary')
      .then((res) => {
        if (res.data?.data) setSummary(res.data.data)
      })
      .catch(() => { /* keep mock data */ })

    apiClient.get('/admin/stats/level-distribution')
      .then((res) => {
        if (res.data?.data) setLevelDist(res.data.data)
      })
      .catch(() => { /* keep mock data */ })

    apiClient.get('/admin/stats/chart?type=registrations&granularity=daily')
      .then((res) => {
        if (res.data?.data) setRegChart(res.data.data)
      })
      .catch(() => { /* keep mock data */ })

    apiClient.get('/admin/stats/chart?type=registrations&granularity=hourly')
      .then((res) => {
        if (res.data?.data) setHourlyChart(res.data.data)
      })
      .catch(() => { /* keep mock data */ })

    apiClient.get('/admin/tickets?status=1&per_page=5')
      .then((res) => {
        if (res.data?.data?.tickets) {
          setRecentTickets(res.data.data.tickets.map((t: Record<string, unknown>) => ({
            id: t.ticket_number || t.id,
            type: t.type_label || t.type,
            time: t.created_at,
          })))
        }
      })
      .catch(() => { /* keep mock data */ })

    apiClient.get('/admin/payments?per_page=5&sort_by=paid_at_desc')
      .then((res) => {
        if (res.data?.data?.payments) {
          setRecentPayments(res.data.data.payments.map((p: Record<string, unknown>) => ({
            user: (p.user as Record<string, unknown>)?.nickname || 'Unknown',
            plan: p.plan,
            amount: p.amount,
            time: p.paid_at,
          })))
        }
      })
      .catch(() => { /* keep mock data */ })
  }, [])

  const chartData = chartMode === '近 30 天（按天）' ? regChart : hourlyChart

  const lineOption: echarts.EChartsOption = {
    tooltip: { trigger: 'axis' },
    legend: { data: ['男性', '女性'] },
    xAxis: { data: chartData.labels },
    yAxis: {},
    series: [
      { name: '男性', type: 'line', smooth: true, data: chartData.male, itemStyle: { color: '#3B82F6' } },
      { name: '女性', type: 'line', smooth: true, data: chartData.female, itemStyle: { color: '#F0294E' } },
    ],
  }

  const pieOption: echarts.EChartsOption = {
    tooltip: { formatter: '{b}: {c} 人 ({d}%)' },
    legend: {
      orient: 'vertical',
      right: 10,
      top: 'center',
      formatter: (name: string) => {
        const item = levelDist.find((d) => d.name === name)
        return `${name}  ${item?.value ?? ''}`
      },
    },
    series: [
      {
        type: 'pie',
        radius: ['45%', '70%'],
        center: ['35%', '50%'],
        data: levelDist,
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
            <Statistic
              title="今日新增會員"
              value={summary.today_new_members}
              prefix={<UserOutlined />}
              suffix={<TrendSuffix pct={summary.today_new_members_pct} />}
            />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic
              title="本月收入"
              value={`NT$ ${summary.month_revenue.toLocaleString()}`}
              prefix={<DollarOutlined />}
              suffix={<TrendSuffix pct={summary.month_revenue_pct} />}
            />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic
              title="付費會員數"
              value={summary.paid_members_total}
              prefix={<CrownOutlined />}
              suffix={<TrendSuffix pct={summary.paid_members_pct} />}
            />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic
              title="待處理 Ticket"
              value={summary.pending_tickets}
              prefix={<FileExclamationOutlined />}
              valueStyle={summary.pending_tickets > 10 ? { color: '#F0294E' } : undefined}
              suffix={
                summary.pending_tickets > 10 ? (
                  <Badge dot>
                    <WarningOutlined style={{ color: '#F0294E' }} />
                  </Badge>
                ) : null
              }
            />
          </Card>
        </Col>
      </Row>

      {/* Charts */}
      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col span={16}>
          <Card
            title="近 30 天新增會員趨勢"
            extra={
              <Segmented
                options={['近 30 天（按天）', '今日（按小時）']}
                value={chartMode}
                onChange={(v) => setChartMode(v as string)}
              />
            }
          >
            <EChart option={lineOption} style={{ height: 300 }} />
          </Card>
        </Col>
        <Col span={8}>
          <Card title="會員等級分佈">
            <EChart option={pieOption} style={{ height: 300 }} />
          </Card>
        </Col>
      </Row>

      {/* Recent Activity */}
      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col span={12}>
          <Card title="最新 Ticket" extra={<a onClick={() => navigate('/tickets')}>查看全部</a>}>
            <List
              dataSource={recentTickets}
              renderItem={(item) => (
                <List.Item>
                  <Tag color="orange">{item.type}</Tag> {item.id} <Text type="secondary">{item.time}</Text>
                </List.Item>
              )}
            />
          </Card>
        </Col>
        <Col span={12}>
          <Card title="最新付款" extra={<a onClick={() => navigate('/payments')}>查看全部</a>}>
            <List
              dataSource={recentPayments}
              renderItem={(item) => (
                <List.Item>
                  {item.user} <Tag color="blue">{item.plan}</Tag> <Text strong>NT${item.amount}</Text>{' '}
                  <Text type="secondary">{item.time}</Text>
                </List.Item>
              )}
            />
          </Card>
        </Col>
      </Row>
    </div>
  )
}
