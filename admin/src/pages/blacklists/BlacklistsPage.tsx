import { useState, useEffect, useCallback } from 'react'
import { Table, Input, Select, Button, Tag, Space, Typography, message, DatePicker, Tooltip } from 'antd'
import { SearchOutlined, ReloadOutlined, PlusOutlined, StopOutlined } from '@ant-design/icons'
import dayjs, { Dayjs } from 'dayjs'
import apiClient from '../../api/client'
import { useAuthStore } from '../../stores/authStore'
import BlacklistFormModal from './BlacklistFormModal'
import BlacklistDeactivateModal from './BlacklistDeactivateModal'

const { Title } = Typography
const { RangePicker } = DatePicker

interface BlacklistItem {
  id: number
  type: 'email' | 'mobile'
  value_masked: string
  reason: string | null
  source: 'manual' | 'admin_delete'
  source_user_id: number | null
  is_active: boolean
  status: 'active' | 'inactive' | 'expired'
  expires_at: string | null
  created_at: string
  created_by_name: string | null
  deactivated_at: string | null
  deactivated_by_name: string | null
  deactivation_reason: string | null
}

export default function BlacklistsPage() {
  const role = useAuthStore((s) => s.user?.role)
  const canMutate = role === 'super_admin' || role === 'admin'

  const [items, setItems] = useState<BlacklistItem[]>([])
  const [loading, setLoading] = useState(false)
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [perPage] = useState(20)

  const [typeFilter, setTypeFilter] = useState<string | undefined>()
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [sourceFilter, setSourceFilter] = useState<string | undefined>()
  const [searchQ, setSearchQ] = useState('')
  const [dateRange, setDateRange] = useState<[Dayjs | null, Dayjs | null] | null>(null)

  const [formOpen, setFormOpen] = useState(false)
  const [deactivateOpen, setDeactivateOpen] = useState(false)
  const [deactivateTarget, setDeactivateTarget] = useState<BlacklistItem | null>(null)

  const fetchItems = useCallback(async () => {
    setLoading(true)
    try {
      const params: Record<string, string | number> = { page, per_page: perPage }
      if (typeFilter) params.type = typeFilter
      if (statusFilter !== 'all') params.status = statusFilter
      if (sourceFilter) params.source = sourceFilter
      if (searchQ) params.q = searchQ
      if (dateRange?.[0]) params.created_from = dateRange[0].toISOString()
      if (dateRange?.[1]) params.created_to = dateRange[1].toISOString()

      const res = await apiClient.get('/admin/blacklists', { params })
      setItems(res.data?.data ?? [])
      setTotal(res.data?.meta?.total ?? 0)
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message
      message.error(msg || '載入失敗')
    } finally {
      setLoading(false)
    }
  }, [page, perPage, typeFilter, statusFilter, sourceFilter, searchQ, dateRange])

  useEffect(() => { fetchItems() }, [fetchItems])

  function resetFilters() {
    setTypeFilter(undefined)
    setStatusFilter('all')
    setSourceFilter(undefined)
    setSearchQ('')
    setDateRange(null)
    setPage(1)
  }

  function handleDeactivateClick(record: BlacklistItem) {
    setDeactivateTarget(record)
    setDeactivateOpen(true)
  }

  const columns = [
    {
      title: 'Type', dataIndex: 'type', key: 'type', width: 90,
      render: (t: string) => t === 'email' ? <Tag color="blue">Email</Tag> : <Tag color="green">手機</Tag>,
    },
    { title: '遮罩值', dataIndex: 'value_masked', key: 'value_masked', width: 200 },
    {
      title: '原因', dataIndex: 'reason', key: 'reason', ellipsis: true,
      render: (r: string | null) => r ? <Tooltip title={r}>{r}</Tooltip> : <span style={{ color: '#999' }}>—</span>,
    },
    {
      title: '來源', dataIndex: 'source', key: 'source', width: 110,
      render: (s: string) => s === 'admin_delete' ? <Tag>刪除附帶</Tag> : <Tag>手動新增</Tag>,
    },
    {
      title: '狀態', dataIndex: 'status', key: 'status', width: 90,
      render: (s: string) => {
        if (s === 'active') return <Tag color="red">有效</Tag>
        if (s === 'expired') return <Tag color="orange">已過期</Tag>
        return <Tag>已解除</Tag>
      },
    },
    { title: '到期日', dataIndex: 'expires_at', key: 'expires_at', width: 120, render: (d: string | null) => d ? dayjs(d).format('YYYY-MM-DD') : <span style={{ color: '#999' }}>永久</span> },
    { title: '建立者', dataIndex: 'created_by_name', key: 'created_by_name', width: 120 },
    { title: '建立時間', dataIndex: 'created_at', key: 'created_at', width: 150, render: (d: string) => dayjs(d).format('MM/DD HH:mm') },
    {
      title: '操作', key: 'actions', width: 110, fixed: 'right' as const,
      render: (_: unknown, record: BlacklistItem) => (
        canMutate && record.is_active ? (
          <Button danger size="small" icon={<StopOutlined />} onClick={() => handleDeactivateClick(record)}>解除</Button>
        ) : <span style={{ color: '#bbb' }}>—</span>
      ),
    },
  ]

  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
        <Title level={4} style={{ margin: 0 }}>註冊禁止名單</Title>
        {canMutate && (
          <Button type="primary" icon={<PlusOutlined />} onClick={() => setFormOpen(true)}>新增</Button>
        )}
      </div>

      <Space wrap style={{ marginBottom: 16 }}>
        <Input
          placeholder="搜尋遮罩值（前綴）"
          prefix={<SearchOutlined />}
          value={searchQ}
          onChange={(e) => setSearchQ(e.target.value)}
          onPressEnter={fetchItems}
          allowClear
          style={{ width: 220 }}
        />
        <Select value={typeFilter} placeholder="類型" onChange={setTypeFilter} allowClear style={{ width: 120 }}>
          <Select.Option value="email">Email</Select.Option>
          <Select.Option value="mobile">手機</Select.Option>
        </Select>
        <Select value={statusFilter} onChange={setStatusFilter} style={{ width: 120 }}>
          <Select.Option value="all">全部狀態</Select.Option>
          <Select.Option value="active">有效</Select.Option>
          <Select.Option value="expired">已過期</Select.Option>
          <Select.Option value="inactive">已解除</Select.Option>
        </Select>
        <Select value={sourceFilter} placeholder="來源" onChange={setSourceFilter} allowClear style={{ width: 130 }}>
          <Select.Option value="manual">手動新增</Select.Option>
          <Select.Option value="admin_delete">刪除附帶</Select.Option>
        </Select>
        <RangePicker value={dateRange} onChange={(range) => setDateRange(range as [Dayjs | null, Dayjs | null] | null)} />
        <Button onClick={fetchItems}>搜尋</Button>
        <Button icon={<ReloadOutlined />} onClick={resetFilters}>重設</Button>
      </Space>

      <Table
        dataSource={items}
        columns={columns}
        rowKey="id"
        loading={loading}
        pagination={{ current: page, pageSize: perPage, total, onChange: setPage, showTotal: (t) => `共 ${t} 筆` }}
        size="middle"
        scroll={{ x: 1100 }}
        locale={{ emptyText: '目前無資料' }}
      />

      <BlacklistFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSuccess={() => { setFormOpen(false); fetchItems() }}
      />

      <BlacklistDeactivateModal
        open={deactivateOpen}
        target={deactivateTarget}
        onClose={() => setDeactivateOpen(false)}
        onSuccess={() => { setDeactivateOpen(false); fetchItems() }}
      />
    </div>
  )
}
