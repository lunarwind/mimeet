import { useState, useEffect } from 'react'
import { Table, Input, Tag, Button, Drawer, Descriptions, Space, Typography, Select, Divider, List, message } from 'antd'
import { SearchOutlined, SendOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography
const { TextArea } = Input

const STATUS_COLORS: Record<string, string> = { pending: 'orange', investigating: 'blue', resolved: 'green', dismissed: 'default' }
const STATUS_LABELS: Record<string, string> = { pending: '待處理', investigating: '處理中', resolved: '已結案', dismissed: '已駁回' }

interface Followup {
  id: number
  admin_name: string
  content: string
  created_at: string
}

interface Ticket {
  id: number
  uuid: string
  type: string
  status: string
  description: string
  admin_reply: string | null
  reporter: { id: number; nickname: string } | null
  reported_user: { id: number; nickname: string } | null
  followups?: Followup[]
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

  // Drawer action state
  const [newStatus, setNewStatus] = useState<string>('')
  const [adminReply, setAdminReply] = useState('')
  const [processing, setProcessing] = useState(false)
  const [followupText, setFollowupText] = useState('')
  const [followupSubmitting, setFollowupSubmitting] = useState(false)

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

  async function fetchTicketDetail(id: number) {
    try {
      const res = await apiClient.get(`/admin/tickets/${id}`)
      const ticket = res.data.data
      setSelectedTicket(ticket)
      setNewStatus(ticket.status)
      setAdminReply(ticket.admin_reply || '')
    } catch {
      // fallback: use list data
    }
  }

  function openDrawer(ticket: Ticket) {
    setSelectedTicket(ticket)
    setNewStatus(ticket.status)
    setAdminReply(ticket.admin_reply || '')
    setFollowupText('')
    setDrawerOpen(true)
    fetchTicketDetail(ticket.id)
  }

  async function handleProcess() {
    if (!selectedTicket) return
    setProcessing(true)
    try {
      await apiClient.patch(`/admin/tickets/${selectedTicket.id}`, {
        data: { status: newStatus, admin_reply: adminReply || undefined },
      })
      message.success('Ticket 已更新')
      await fetchTicketDetail(selectedTicket.id)
      fetchTickets()
    } catch {
      message.error('更新失敗')
    }
    setProcessing(false)
  }

  async function handleAddFollowup() {
    if (!selectedTicket || !followupText.trim()) return
    setFollowupSubmitting(true)
    try {
      await apiClient.post(`/admin/tickets/${selectedTicket.id}/reply`, {
        data: { content: followupText },
      })
      message.success('留言已新增')
      setFollowupText('')
      await fetchTicketDetail(selectedTicket.id)
    } catch {
      message.error('留言失敗')
    }
    setFollowupSubmitting(false)
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
        <Button type="link" size="small" onClick={() => openDrawer(record)}>處理</Button>
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

      <Drawer title={`Ticket #${selectedTicket?.id}`} placement="right" width={560}
        open={drawerOpen} onClose={() => setDrawerOpen(false)}>
        {selectedTicket && (
          <div>
            {/* Ticket info */}
            <Descriptions column={1} bordered size="small">
              <Descriptions.Item label="類型"><Tag>{selectedTicket.type}</Tag></Descriptions.Item>
              <Descriptions.Item label="目前狀態"><Tag color={STATUS_COLORS[selectedTicket.status]}>{STATUS_LABELS[selectedTicket.status]}</Tag></Descriptions.Item>
              <Descriptions.Item label="回報者">{selectedTicket.reporter?.nickname || '-'}</Descriptions.Item>
              <Descriptions.Item label="被回報者">{selectedTicket.reported_user?.nickname || '-'}</Descriptions.Item>
              <Descriptions.Item label="描述">{selectedTicket.description}</Descriptions.Item>
              <Descriptions.Item label="建立時間">{selectedTicket.created_at ? dayjs(selectedTicket.created_at).format('YYYY/MM/DD HH:mm') : '-'}</Descriptions.Item>
              {selectedTicket.admin_reply && (
                <Descriptions.Item label="管理員回覆">{selectedTicket.admin_reply}</Descriptions.Item>
              )}
            </Descriptions>

            {/* Status update & reply */}
            <Divider>處理操作</Divider>

            <div style={{ marginBottom: 16 }}>
              <Text strong style={{ display: 'block', marginBottom: 8 }}>變更狀態</Text>
              <Select value={newStatus} onChange={setNewStatus} style={{ width: '100%' }}>
                <Select.Option value="pending">待處理</Select.Option>
                <Select.Option value="investigating">處理中</Select.Option>
                <Select.Option value="resolved">已結案</Select.Option>
                <Select.Option value="dismissed">已駁回</Select.Option>
              </Select>
            </div>

            <div style={{ marginBottom: 16 }}>
              <Text strong style={{ display: 'block', marginBottom: 8 }}>管理員回覆</Text>
              <TextArea
                value={adminReply}
                onChange={(e) => setAdminReply(e.target.value)}
                placeholder="輸入給用戶的回覆..."
                rows={3}
              />
            </div>

            <Button type="primary" onClick={handleProcess} loading={processing}
              style={{ width: '100%', marginBottom: 24 }}>
              送出處理結果
            </Button>

            {/* Followups */}
            <Divider>追蹤留言</Divider>

            {selectedTicket.followups && selectedTicket.followups.length > 0 && (
              <List
                size="small"
                dataSource={selectedTicket.followups}
                renderItem={(f: Followup) => (
                  <List.Item>
                    <div style={{ width: '100%' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                        <Text strong style={{ fontSize: 12 }}>{f.admin_name}</Text>
                        <Text type="secondary" style={{ fontSize: 11 }}>{dayjs(f.created_at).format('MM/DD HH:mm')}</Text>
                      </div>
                      <Text style={{ fontSize: 13 }}>{f.content}</Text>
                    </div>
                  </List.Item>
                )}
                style={{ marginBottom: 16 }}
              />
            )}

            <Space.Compact style={{ width: '100%' }}>
              <TextArea
                value={followupText}
                onChange={(e) => setFollowupText(e.target.value)}
                placeholder="新增追蹤留言..."
                rows={2}
                style={{ flex: 1 }}
              />
              <Button type="primary" icon={<SendOutlined />}
                onClick={handleAddFollowup} loading={followupSubmitting}
                disabled={!followupText.trim()}
                style={{ height: 'auto' }}>
                送出
              </Button>
            </Space.Compact>
          </div>
        )}
      </Drawer>
    </div>
  )
}
