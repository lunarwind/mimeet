import { useState, useEffect, useCallback } from 'react'
import { Table, Button, Tag, Select, Space, Typography, message, Modal, Badge } from 'antd'
import { ReloadOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title } = Typography

interface CcVerification {
  id: number
  user: { id: number; nickname: string; email: string } | null
  order_no: string
  amount: number
  status: 'pending' | 'paid' | 'refunded' | 'failed' | 'refund_failed'
  gateway_trade_no: string | null
  card_last4: string | null
  paid_at: string | null
  refunded_at: string | null
  created_at: string
}

const STATUS_META: Record<string, { label: string; color: string }> = {
  pending:      { label: '待付款', color: 'blue' },
  paid:         { label: '已付款', color: 'green' },
  refunded:     { label: '已退款', color: 'default' },
  failed:       { label: '付款失敗', color: 'red' },
  refund_failed:{ label: '退款失敗', color: 'volcano' },
}

export default function CreditCardVerificationsPage() {
  const [data, setData] = useState<CcVerification[]>([])
  const [loading, setLoading] = useState(false)
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [refundingId, setRefundingId] = useState<number | null>(null)

  const fetchData = useCallback(async () => {
    setLoading(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (statusFilter !== 'all') params.status = statusFilter
      const res = await apiClient.get('/admin/credit-card-verifications', { params })
      setData(res.data.data ?? [])
      setTotal(res.data.meta?.total ?? 0)
    } catch { setData([]) }
    setLoading(false)
  }, [page, statusFilter])

  useEffect(() => { fetchData() }, [fetchData])

  async function handleRefund(record: CcVerification) {
    Modal.confirm({
      title: '確認退款',
      content: `對訂單 ${record.order_no} 發起退款 NT$${record.amount}？`,
      onOk: async () => {
        setRefundingId(record.id)
        try {
          await apiClient.post(`/admin/credit-card-verifications/${record.id}/refund`)
          message.success('退款已觸發')
          fetchData()
        } catch (err: unknown) {
          const e = err as { response?: { data?: { message?: string } } }
          message.error(e?.response?.data?.message ?? '退款失敗')
        } finally {
          setRefundingId(null)
        }
      },
    })
  }

  const columns = [
    { title: 'ID', dataIndex: 'id', width: 60 },
    { title: '用戶', key: 'user', width: 160,
      render: (_: unknown, r: CcVerification) => r.user
        ? <span>{r.user.nickname} <span style={{ color: '#9CA3AF', fontSize: 11 }}>#{r.user.id}</span></span>
        : '—' },
    { title: '訂單號', dataIndex: 'order_no', width: 220 },
    { title: '金額', dataIndex: 'amount', width: 80, render: (v: number) => `NT$${v}` },
    { title: '狀態', dataIndex: 'status', width: 100,
      render: (v: string) => {
        const m = STATUS_META[v] ?? { label: v, color: 'default' }
        return <Tag color={m.color}>{m.label}</Tag>
      }},
    { title: '末四碼', dataIndex: 'card_last4', width: 80, render: (v: string | null) => v ? `****${v}` : '—' },
    { title: '付款時間', dataIndex: 'paid_at', width: 160,
      render: (v: string | null) => v ? dayjs(v).format('MM/DD HH:mm') : '—' },
    { title: '退款時間', dataIndex: 'refunded_at', width: 160,
      render: (v: string | null) => v ? dayjs(v).format('MM/DD HH:mm') : '—' },
    { title: '建立時間', dataIndex: 'created_at', width: 160,
      render: (v: string) => dayjs(v).format('MM/DD HH:mm') },
    { title: '操作', key: 'actions', width: 100,
      render: (_: unknown, r: CcVerification) =>
        r.status === 'paid' ? (
          <Button
            size="small"
            danger
            loading={refundingId === r.id}
            onClick={() => handleRefund(r)}
          >
            退款
          </Button>
        ) : null
    },
  ]

  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
        <Space>
          <Title level={4} style={{ margin: 0 }}>💳 信用卡驗證紀錄</Title>
          <Badge count={total} showZero style={{ backgroundColor: '#F0294E' }} />
        </Space>
      </div>

      <Space style={{ marginBottom: 16 }}>
        <Select
          value={statusFilter}
          onChange={v => { setStatusFilter(v); setPage(1) }}
          style={{ width: 130 }}
        >
          <Select.Option value="all">全部狀態</Select.Option>
          {Object.entries(STATUS_META).map(([k, v]) => (
            <Select.Option key={k} value={k}>{v.label}</Select.Option>
          ))}
        </Select>
        <Button icon={<ReloadOutlined />} onClick={fetchData}>重新整理</Button>
      </Space>

      <Table
        dataSource={data}
        columns={columns}
        rowKey="id"
        loading={loading}
        pagination={{
          current: page,
          pageSize: 20,
          total,
          onChange: setPage,
          showTotal: (t) => `共 ${t} 筆`,
        }}
        size="middle"
        scroll={{ x: 1100 }}
        locale={{ emptyText: '目前無信用卡驗證紀錄' }}
      />
    </div>
  )
}
