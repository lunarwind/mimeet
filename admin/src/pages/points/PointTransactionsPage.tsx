import { useState, useEffect, useCallback } from 'react'
import { Table, Input, Select, Button, Tag, Space, Typography, DatePicker, Badge } from 'antd'
import { SearchOutlined, ReloadOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'
import type { Dayjs } from 'dayjs'

const { Title } = Typography
const { RangePicker } = DatePicker

interface Txn {
  id: number
  user: { id: number; nickname: string | null; email: string }
  type: string
  amount: number
  balance_after: number
  feature: string | null
  description: string | null
  reference_id: number | null
  created_at: string
}

const TYPE_LABELS: Record<string, { label: string; color: string }> = {
  purchase: { label: '購買', color: 'green' },
  consume: { label: '消費', color: 'orange' },
  refund: { label: '退款', color: 'blue' },
  admin_gift: { label: '贈送', color: 'purple' },
  admin_deduct: { label: '扣除', color: 'red' },
}

const FEATURE_LABELS: Record<string, string> = {
  stealth: '🕶 隱身',
  super_like: '⭐ 超級讚',
  reverse_msg: '💬 突破訊息',
  broadcast: '📢 廣播',
}

export default function PointTransactionsPage() {
  const [data, setData] = useState<Txn[]>([])
  const [loading, setLoading] = useState(true)
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)

  // filters
  const [nickname, setNickname] = useState('')
  const [typeFilter, setTypeFilter] = useState<string | undefined>()
  const [featureFilter, setFeatureFilter] = useState<string | undefined>()
  const [dateRange, setDateRange] = useState<[Dayjs | null, Dayjs | null] | null>(null)

  const fetchData = useCallback(async () => {
    setLoading(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (nickname.trim()) params.nickname = nickname.trim()
      if (typeFilter) params.type = typeFilter
      if (featureFilter) params.feature = featureFilter
      if (dateRange?.[0]) params.date_from = dateRange[0].format('YYYY-MM-DD')
      if (dateRange?.[1]) params.date_to = dateRange[1].format('YYYY-MM-DD')
      const res = await apiClient.get('/admin/point-transactions', { params })
      setData(res.data?.data ?? [])
      setTotal(res.data?.meta?.total ?? 0)
    } finally {
      setLoading(false)
    }
  }, [page, nickname, typeFilter, featureFilter, dateRange])

  useEffect(() => { fetchData() }, [fetchData])

  function reset() {
    setNickname('')
    setTypeFilter(undefined)
    setFeatureFilter(undefined)
    setDateRange(null)
    setPage(1)
  }

  const columns = [
    { title: '時間', dataIndex: 'created_at', key: 'created_at', width: 160,
      render: (v: string) => dayjs(v).format('MM/DD HH:mm:ss') },
    { title: '用戶', key: 'user', width: 180,
      render: (_: unknown, r: Txn) => (
        <span>
          {r.user.nickname ?? '—'}
          <Typography.Text type="secondary" style={{ marginLeft: 6, fontSize: 11 }}>#{r.user.id}</Typography.Text>
        </span>
      )},
    { title: '類型', dataIndex: 'type', key: 'type', width: 90,
      render: (v: string) => <Tag color={TYPE_LABELS[v]?.color ?? 'default'}>{TYPE_LABELS[v]?.label ?? v}</Tag> },
    { title: '功能', dataIndex: 'feature', key: 'feature', width: 110,
      render: (v: string | null) => v ? (FEATURE_LABELS[v] ?? v) : <span style={{ color: '#999' }}>—</span> },
    { title: '點數', dataIndex: 'amount', key: 'amount', width: 90, align: 'right' as const,
      render: (v: number) => (
        <span style={{ fontWeight: 700, color: v > 0 ? '#10B981' : '#EF4444' }}>
          {v > 0 ? '+' : ''}{v}
        </span>
      )},
    { title: '餘額', dataIndex: 'balance_after', key: 'balance_after', width: 80, align: 'right' as const },
    { title: '說明', dataIndex: 'description', key: 'description', ellipsis: true },
  ]

  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
        <Space>
          <Title level={4} style={{ margin: 0 }}>💎 點數交易紀錄</Title>
          <Badge count={total} showZero style={{ backgroundColor: '#F0294E' }} />
        </Space>
      </div>

      <Space wrap style={{ marginBottom: 16 }}>
        <Input placeholder="搜尋暱稱" prefix={<SearchOutlined />}
          value={nickname} onChange={(e) => setNickname(e.target.value)}
          onPressEnter={() => { setPage(1); fetchData() }} style={{ width: 180 }} allowClear />
        <Select value={typeFilter} onChange={(v) => { setTypeFilter(v); setPage(1) }}
          placeholder="交易類型" style={{ width: 130 }} allowClear>
          {Object.entries(TYPE_LABELS).map(([k, v]) => <Select.Option key={k} value={k}>{v.label}</Select.Option>)}
        </Select>
        <Select value={featureFilter} onChange={(v) => { setFeatureFilter(v); setPage(1) }}
          placeholder="消費功能" style={{ width: 140 }} allowClear>
          {Object.entries(FEATURE_LABELS).map(([k, v]) => <Select.Option key={k} value={k}>{v}</Select.Option>)}
        </Select>
        <RangePicker value={dateRange as any} onChange={(v) => { setDateRange(v as any); setPage(1) }} />
        <Button onClick={() => { setPage(1); fetchData() }}>搜尋</Button>
        <Button icon={<ReloadOutlined />} onClick={reset}>重設</Button>
      </Space>

      <Table
        dataSource={data}
        columns={columns}
        rowKey="id"
        loading={loading}
        pagination={{ current: page, pageSize: 20, total, onChange: setPage, showTotal: (t) => `共 ${t} 筆` }}
        size="middle"
        locale={{ emptyText: '目前無交易紀錄' }}
      />
    </div>
  )
}
