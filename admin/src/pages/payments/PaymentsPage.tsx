import { useState, useEffect } from 'react'
import { Table, Select, Tag, Card, Row, Col, Statistic, Typography, Space } from 'antd'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title } = Typography

const STATUS_COLOR: Record<string, string> = { paid: 'green', failed: 'red', refunded: 'orange', pending: 'blue', expired: 'default' }

interface Payment {
  id: number
  order_number: string
  user: { id: number; nickname: string } | null
  plan_name: string
  amount: number
  status: string
  paid_at: string | null
  created_at: string
}

export default function PaymentsPage() {
  const [payments, setPayments] = useState<Payment[]>([])
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)

  useEffect(() => { fetchPayments() }, [page, statusFilter])

  async function fetchPayments() {
    setLoading(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (statusFilter !== 'all') params.status = statusFilter
      const res = await apiClient.get('/admin/payments', { params })
      setPayments(res.data.data.payments ?? [])
      setTotal(res.data.data.pagination?.total ?? 0)
    } catch { setPayments([]) }
    setLoading(false)
  }

  const paidPayments = payments.filter(p => p.status === 'paid')
  const totalRevenue = paidPayments.reduce((s, p) => s + (p.amount || 0), 0)

  const columns = [
    { title: '訂單編號', dataIndex: 'order_number', key: 'order_number', width: 200 },
    { title: '用戶', key: 'user', width: 120, render: (_: unknown, r: Payment) => r.user?.nickname || '-' },
    { title: '方案', dataIndex: 'plan_name', key: 'plan_name', width: 100 },
    { title: '金額', dataIndex: 'amount', key: 'amount', width: 100, render: (a: number) => `NT$${(a || 0).toLocaleString()}` },
    { title: '狀態', dataIndex: 'status', key: 'status', width: 90, render: (s: string) => <Tag color={STATUS_COLOR[s] || 'default'}>{s}</Tag> },
    { title: '付款時間', dataIndex: 'paid_at', key: 'paid_at', width: 140, render: (d: string | null) => d ? dayjs(d).format('MM/DD HH:mm') : '-' },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>支付記錄</Title>

      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col span={8}><Card><Statistic title="總收入" value={`NT$ ${totalRevenue.toLocaleString()}`} /></Card></Col>
        <Col span={8}><Card><Statistic title="已付款" value={paidPayments.length} /></Card></Col>
        <Col span={8}><Card><Statistic title="總訂單" value={total} /></Card></Col>
      </Row>

      <Space style={{ marginBottom: 16 }}>
        <Select value={statusFilter} onChange={(v) => { setStatusFilter(v); setPage(1) }} style={{ width: 120 }}>
          <Select.Option value="all">全部狀態</Select.Option>
          <Select.Option value="paid">已付款</Select.Option>
          <Select.Option value="pending">待付款</Select.Option>
          <Select.Option value="failed">失敗</Select.Option>
        </Select>
      </Space>

      <Table dataSource={payments} columns={columns} rowKey="id" loading={loading}
        pagination={{ current: page, pageSize: 20, total, onChange: setPage, showTotal: (t) => `共 ${t} 筆` }}
        size="middle" locale={{ emptyText: '目前無支付記錄' }}
      />
    </div>
  )
}
