import { useState, useMemo } from 'react'
import { Table, Select, DatePicker, Tag, Button, Card, Row, Col, Statistic, Typography, Space } from 'antd'
import { DollarOutlined, UserOutlined, RiseOutlined, ShoppingCartOutlined, DownloadOutlined } from '@ant-design/icons'
import { MOCK_PAYMENTS } from '../../mocks/members'
import type { PaymentRecord } from '../../types/admin'
import dayjs from 'dayjs'

const { Title } = Typography
const { RangePicker } = DatePicker

const STATUS_COLOR: Record<string, string> = { paid: 'green', failed: 'red', refunded: 'orange', pending: 'blue' }
const STATUS_LABEL: Record<string, string> = { paid: '成功', failed: '失敗', refunded: '退款', pending: '待付款' }

export default function PaymentsPage() {
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [planFilter, setPlanFilter] = useState<string>('all')
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null] | null>(null)

  const filtered = useMemo(() => {
    let data = [...MOCK_PAYMENTS]
    if (statusFilter !== 'all') data = data.filter((p) => p.status === statusFilter)
    if (planFilter !== 'all') data = data.filter((p) => p.plan === planFilter)
    if (dateRange && dateRange[0] && dateRange[1]) {
      const start = dateRange[0].startOf('day').valueOf()
      const end = dateRange[1].endOf('day').valueOf()
      data = data.filter((p) => {
        const t = new Date(p.paid_at).getTime()
        return t >= start && t <= end
      })
    }
    return data
  }, [statusFilter, planFilter, dateRange])

  const todayRevenue = MOCK_PAYMENTS.filter((p) => p.status === 'paid' && dayjs(p.paid_at).isSame(dayjs(), 'day')).reduce((s, p) => s + p.amount_paid, 0)
  const monthRevenue = MOCK_PAYMENTS.filter((p) => p.status === 'paid' && dayjs(p.paid_at).isSame(dayjs(), 'month')).reduce((s, p) => s + p.amount_paid, 0)
  const paidMembers = new Set(MOCK_PAYMENTS.filter((p) => p.status === 'paid').map((p) => p.user.uid)).size
  const monthNewSubs = MOCK_PAYMENTS.filter((p) => p.status === 'paid' && dayjs(p.paid_at).isSame(dayjs(), 'month')).length

  const columns = [
    { title: '訂單編號', dataIndex: 'order_number', key: 'order_number', width: 160 },
    {
      title: '用戶', key: 'user', width: 120,
      render: (_: unknown, r: PaymentRecord) => r.user.nickname,
    },
    { title: '方案', dataIndex: 'plan', key: 'plan', width: 100 },
    {
      title: '金額', dataIndex: 'amount', key: 'amount', width: 100,
      render: (a: number) => `NT$${a}`,
    },
    { title: '付款方式', dataIndex: 'payment_method', key: 'payment_method', width: 100 },
    {
      title: '狀態', dataIndex: 'status', key: 'status', width: 80,
      render: (s: string) => <Tag color={STATUS_COLOR[s]}>{STATUS_LABEL[s]}</Tag>,
    },
    {
      title: '付款時間', dataIndex: 'paid_at', key: 'paid_at', width: 140,
      render: (d: string) => dayjs(d).format('MM/DD HH:mm'),
    },
  ]

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Title level={4} style={{ margin: 0 }}>支付記錄</Title>
        <Button icon={<DownloadOutlined />}>匯出 CSV</Button>
      </div>

      <Row gutter={16} style={{ marginBottom: 24 }}>
        <Col xs={12} md={6}>
          <Card><Statistic title="今日收入" value={todayRevenue} prefix={<DollarOutlined />} suffix="NT$" valueStyle={{ color: '#F0294E' }} /></Card>
        </Col>
        <Col xs={12} md={6}>
          <Card><Statistic title="本月收入" value={monthRevenue} prefix={<RiseOutlined />} suffix="NT$" /></Card>
        </Col>
        <Col xs={12} md={6}>
          <Card><Statistic title="付費會員數" value={paidMembers} prefix={<UserOutlined />} /></Card>
        </Col>
        <Col xs={12} md={6}>
          <Card><Statistic title="本月新增訂閱" value={monthNewSubs} prefix={<ShoppingCartOutlined />} /></Card>
        </Col>
      </Row>

      <Space wrap style={{ marginBottom: 16 }}>
        <RangePicker onChange={(dates) => setDateRange(dates as [dayjs.Dayjs | null, dayjs.Dayjs | null] | null)} />
        <Select value={statusFilter} onChange={setStatusFilter} style={{ width: 120 }}>
          <Select.Option value="all">全部狀態</Select.Option>
          <Select.Option value="paid">成功</Select.Option>
          <Select.Option value="failed">失敗</Select.Option>
          <Select.Option value="refunded">退款</Select.Option>
        </Select>
        <Select value={planFilter} onChange={setPlanFilter} style={{ width: 120 }}>
          <Select.Option value="all">全部方案</Select.Option>
          <Select.Option value="月費方案">月費</Select.Option>
          <Select.Option value="季費方案">季費</Select.Option>
          <Select.Option value="年費方案">年費</Select.Option>
          <Select.Option value="體驗方案">體驗</Select.Option>
        </Select>
      </Space>

      <Table
        dataSource={filtered}
        columns={columns}
        rowKey="id"
        pagination={{ pageSize: 20, showTotal: (total) => `共 ${total} 筆` }}
        size="middle"
      />
    </div>
  )
}
