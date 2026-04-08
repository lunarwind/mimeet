import { useState, useMemo } from 'react'
import { Tabs, Table, Input, Tag, Button, Drawer, Descriptions, Space, Typography, Image, Select, message } from 'antd'
import { SearchOutlined } from '@ant-design/icons'
import { MOCK_TICKETS } from '../../mocks/members'
import type { Ticket } from '../../types/admin'
import dayjs from 'dayjs'

const { Title, Text, Paragraph } = Typography
const { TextArea } = Input

const STATUS_COLORS: Record<number, string> = { 1: 'orange', 2: 'blue', 3: 'green' }

export default function TicketsPage() {
  const [activeTab, setActiveTab] = useState<string>('1')
  const [search, setSearch] = useState('')
  const [selectedTicket, setSelectedTicket] = useState<Ticket | null>(null)
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [replyText, setReplyText] = useState('')
  const [newStatus, setNewStatus] = useState<number | null>(null)
  const [typeFilter, setTypeFilter] = useState<string>('all')

  const filtered = useMemo(() => {
    let data = MOCK_TICKETS.filter((t) => String(t.status) === activeTab)
    if (search) {
      data = data.filter((t) => t.ticket_number.includes(search) || t.title.includes(search))
    }
    if (typeFilter !== 'all') {
      data = data.filter((t) => String(t.type) === typeFilter || t.type_label === typeFilter)
    }
    return data
  }, [activeTab, search, typeFilter])

  const openDetail = (ticket: Ticket) => {
    setSelectedTicket(ticket)
    setNewStatus(ticket.status)
    setReplyText('')
    setDrawerOpen(true)
  }

  const handleStatusChange = () => {
    message.success('案件狀態已更新')
    setDrawerOpen(false)
  }

  const columns = [
    { title: '案號', dataIndex: 'ticket_number', key: 'ticket_number', width: 200 },
    {
      title: '類型', dataIndex: 'type_label', key: 'type_label', width: 120,
      render: (label: string, r: Ticket) => <Tag color={r.type === 1 ? 'red' : r.type === 2 ? 'blue' : 'orange'}>{label}</Tag>,
    },
    { title: '標題', dataIndex: 'title', key: 'title' },
    {
      title: '回報者', key: 'reporter', width: 120,
      render: (_: unknown, r: Ticket) => r.reporter.nickname,
    },
    {
      title: '被回報者', key: 'reported_user', width: 120,
      render: (_: unknown, r: Ticket) => r.reported_user?.nickname || '-',
    },
    {
      title: '建立時間', dataIndex: 'created_at', key: 'created_at', width: 140,
      render: (d: string) => dayjs(d).format('MM/DD HH:mm'),
    },
    {
      title: '狀態', dataIndex: 'status', key: 'status', width: 90,
      render: (s: number, r: Ticket) => <Tag color={STATUS_COLORS[s]}>{r.status_label}</Tag>,
    },
    {
      title: '操作', key: 'actions', width: 80,
      render: (_: unknown, record: Ticket) => (
        <Button type="link" size="small" onClick={() => openDetail(record)}>查看</Button>
      ),
    },
  ]

  const tabCounts = {
    '1': MOCK_TICKETS.filter((t) => t.status === 1).length,
    '2': MOCK_TICKETS.filter((t) => t.status === 2).length,
    '3': MOCK_TICKETS.filter((t) => t.status === 3).length,
  }

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>Ticket 回報管理</Title>

      <Space style={{ marginBottom: 16 }}>
        <Input
          placeholder="搜尋案號或標題"
          prefix={<SearchOutlined />}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          style={{ width: 280 }}
          allowClear
        />
        <Select value={typeFilter} onChange={setTypeFilter} style={{ width: 160 }}>
          <Select.Option value="all">全部類型</Select.Option>
          <Select.Option value="1">一般檢舉</Select.Option>
          <Select.Option value="2">系統問題</Select.Option>
          <Select.Option value="3">匿名聊天檢舉</Select.Option>
          <Select.Option value="appeal">停權申訴</Select.Option>
        </Select>
      </Space>

      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={[
          { key: '1', label: `待處理 (${tabCounts['1']})` },
          { key: '2', label: `處理中 (${tabCounts['2']})` },
          { key: '3', label: `已結案 (${tabCounts['3']})` },
        ]}
      />

      <Table dataSource={filtered} columns={columns} rowKey="id" pagination={{ pageSize: 20 }} size="middle" />

      <Drawer
        title={selectedTicket?.ticket_number}
        placement="right"
        width={520}
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
      >
        {selectedTicket && (
          <div>
            <Descriptions column={1} bordered size="small">
              <Descriptions.Item label="案號">{selectedTicket.ticket_number}</Descriptions.Item>
              <Descriptions.Item label="類型"><Tag>{selectedTicket.type_label}</Tag></Descriptions.Item>
              <Descriptions.Item label="回報者">{selectedTicket.reporter.nickname} (UID: {selectedTicket.reporter.uid})</Descriptions.Item>
              {selectedTicket.reported_user && (
                <Descriptions.Item label="被回報者">{selectedTicket.reported_user.nickname} (UID: {selectedTicket.reported_user.uid})</Descriptions.Item>
              )}
              <Descriptions.Item label="建立時間">{dayjs(selectedTicket.created_at).format('YYYY/MM/DD HH:mm')}</Descriptions.Item>
              <Descriptions.Item label="狀態"><Tag color={STATUS_COLORS[selectedTicket.status]}>{selectedTicket.status_label}</Tag></Descriptions.Item>
            </Descriptions>

            <div style={{ marginTop: 16 }}>
              <Text strong>回報內容：</Text>
              <Paragraph style={{ marginTop: 8, padding: 12, background: '#f9f9f9', borderRadius: 8 }}>
                {selectedTicket.content}
              </Paragraph>
            </div>

            {selectedTicket.images.length > 0 && (
              <div style={{ marginTop: 16 }}>
                <Text strong>截圖：</Text>
                <div style={{ marginTop: 8 }}>
                  <Image.PreviewGroup>
                    <Space>
                      {selectedTicket.images.map((url, i) => (
                        <Image key={i} src={url} width={120} height={90} style={{ objectFit: 'cover', borderRadius: 8 }} />
                      ))}
                    </Space>
                  </Image.PreviewGroup>
                </div>
              </div>
            )}

            {selectedTicket.admin_reply && (
              <div style={{ marginTop: 16 }}>
                <Text strong>管理員回覆：</Text>
                <Paragraph style={{ marginTop: 8, padding: 12, background: '#ECFDF5', borderRadius: 8 }}>
                  {selectedTicket.admin_reply}
                </Paragraph>
              </div>
            )}

            <div style={{ marginTop: 24, borderTop: '1px solid #f0f0f0', paddingTop: 16 }}>
              <div style={{ marginBottom: 12 }}>
                <Text strong>變更狀態：</Text>
                <Select value={newStatus} onChange={setNewStatus} style={{ width: '100%', marginTop: 4 }}>
                  <Select.Option value={1}>待處理</Select.Option>
                  <Select.Option value={2}>處理中</Select.Option>
                  <Select.Option value={3}>已結案</Select.Option>
                </Select>
              </div>
              <div style={{ marginBottom: 12 }}>
                <Text strong>回覆：</Text>
                <TextArea value={replyText} onChange={(e) => setReplyText(e.target.value)} rows={4} placeholder="填寫處理說明..." style={{ marginTop: 4 }} />
              </div>
              <Space>
                <Button type="primary" onClick={handleStatusChange}>更新狀態</Button>
                {selectedTicket.reported_user && (
                  <>
                    <Button danger>扣分</Button>
                    <Button danger type="dashed">停權</Button>
                  </>
                )}
              </Space>
            </div>
          </div>
        )}
      </Drawer>
    </div>
  )
}
