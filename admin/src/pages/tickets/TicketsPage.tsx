import { useState, useEffect } from 'react'
import { Tabs, Table, Input, Tag, Button, Drawer, Descriptions, Space, Typography, Select, message } from 'antd'
import { SearchOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography

const STATUS_COLORS: Record<string, string> = { pending: 'orange', investigating: 'blue', resolved: 'green', dismissed: 'default' }
const STATUS_LABELS: Record<string, string> = { pending: '待處理', investigating: '處理中', resolved: '已結案', dismissed: '已駁回' }

interface Ticket {
  id: number
  uuid: string
  type: string
  status: string
  description: string
  reporter: { id: number; nickname: string } | null
  reported_user: { id: number; nickname: string } | null
  created_at: string
}

export default function TicketsPage() {
  const [tickets, setTickets] = useState<Ticket[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [typeFilter, setTypeFilter] = useState<string>('all')
  const [selectedTicket, setSelectedTicket] = useState<Ticket | null>(null)
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)

  useEffect(() => { fetchTickets() }, [page, statusFilter, typeFilter])

  async function fetchTickets() {
    setLoading(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (statusFilter !== 'all') params.status = statusFilter
      if (typeFilter !== 'all') params.type = typeFilter
      const res = await apiClient.get('/admin/tickets', { params })
      setTickets(res.data.data.tickets ?? [])
      setTotal(res.data.data.pagination?.total ?? 0)
    } catch { setTickets([]) }
    setLoading(false)
  }

  const columns = [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 70 },
    {
      title: '類型', dataIndex: 'type', key: 'type', width: 120,
      render: (t: string) => <Tag color={t === 'appeal' ? 'purple' : t === 'harassment' ? 'red' : 'blue'}>{t}</Tag>,
    },
    { title: '描述', dataIndex: 'description', key: 'description', ellipsis: true },
    {
      title: '回報者', key: 'reporter', width: 120,
      render: (_: unknown, r: Ticket) => r.reporter?.nickname || '-',
    },
    {
      title: '被回報者', key: 'reported', width: 120,
      render: (_: unknown, r: Ticket) => r.reported_user?.nickname || '-',
    },
    {
      title: '時間', dataIndex: 'created_at', key: 'created_at', width: 140,
      render: (d: string) => d ? dayjs(d).format('MM/DD HH:mm') : '-',
    },
    {
      title: '狀態', dataIndex: 'status', key: 'status', width: 90,
      render: (s: string) => <Tag color={STATUS_COLORS[s]}>{STATUS_LABELS[s] || s}</Tag>,
    },
    {
      title: '操作', key: 'actions', width: 80,
      render: (_: unknown, record: Ticket) => (
        <Button type="link" size="small" onClick={() => { setSelectedTicket(record); setDrawerOpen(true) }}>查看</Button>
      ),
    },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>Ticket 回報管理</Title>

      <Space style={{ marginBottom: 16 }}>
        <Input placeholder="搜尋" prefix={<SearchOutlined />} value={search}
          onChange={(e) => setSearch(e.target.value)} style={{ width: 200 }} allowClear />
        <Select value={statusFilter} onChange={(v) => { setStatusFilter(v); setPage(1) }} style={{ width: 120 }}>
          <Select.Option value="all">全部狀態</Select.Option>
          <Select.Option value="pending">待處理</Select.Option>
          <Select.Option value="investigating">處理中</Select.Option>
          <Select.Option value="resolved">已結案</Select.Option>
          <Select.Option value="dismissed">已駁回</Select.Option>
        </Select>
        <Select value={typeFilter} onChange={(v) => { setTypeFilter(v); setPage(1) }} style={{ width: 140 }}>
          <Select.Option value="all">全部類型</Select.Option>
          <Select.Option value="harassment">騷擾</Select.Option>
          <Select.Option value="scam">詐騙</Select.Option>
          <Select.Option value="fake_photo">假照片</Select.Option>
          <Select.Option value="appeal">停權申訴</Select.Option>
          <Select.Option value="other">其他</Select.Option>
        </Select>
      </Space>

      <Table dataSource={tickets} columns={columns} rowKey="id" loading={loading}
        pagination={{ current: page, pageSize: 20, total, onChange: setPage, showTotal: (t) => `共 ${t} 筆` }}
        size="middle" locale={{ emptyText: '目前無回報案件' }} />

      <Drawer title={`Ticket #${selectedTicket?.id}`} placement="right" width={520}
        open={drawerOpen} onClose={() => setDrawerOpen(false)}>
        {selectedTicket && (
          <Descriptions column={1} bordered size="small">
            <Descriptions.Item label="類型"><Tag>{selectedTicket.type}</Tag></Descriptions.Item>
            <Descriptions.Item label="狀態"><Tag color={STATUS_COLORS[selectedTicket.status]}>{STATUS_LABELS[selectedTicket.status]}</Tag></Descriptions.Item>
            <Descriptions.Item label="回報者">{selectedTicket.reporter?.nickname || '-'}</Descriptions.Item>
            <Descriptions.Item label="被回報者">{selectedTicket.reported_user?.nickname || '-'}</Descriptions.Item>
            <Descriptions.Item label="描述">{selectedTicket.description}</Descriptions.Item>
            <Descriptions.Item label="建立時間">{selectedTicket.created_at ? dayjs(selectedTicket.created_at).format('YYYY/MM/DD HH:mm') : '-'}</Descriptions.Item>
          </Descriptions>
        )}
      </Drawer>
    </div>
  )
}
