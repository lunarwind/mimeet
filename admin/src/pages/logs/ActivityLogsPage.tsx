import { useState } from 'react'
import { Table, Input, Select, Button, Tag, Typography, Card, Space, Result } from 'antd'
import { ReloadOutlined } from '@ant-design/icons'
import { useAuthStore } from '../../stores/authStore'
import dayjs from 'dayjs'

// Operation log types
type ActionType = 'adjust_credit' | 'suspend' | 'unsuspend' | 'set_level' | 'settings_change' | 'ticket_process'

const actionMeta: Record<string, { label: string; color: string }> = {
  adjust_credit:   { label: '調整分數',   color: 'orange' },
  suspend:         { label: '停權',       color: 'red' },
  unsuspend:       { label: '解除停權',   color: 'green' },
  set_level:       { label: '調整等級',   color: 'blue' },
  settings_change: { label: '設定變更',   color: 'purple' },
  ticket_process:  { label: 'Ticket處理', color: 'cyan' },
  system_settings_change: { label: '系統設定', color: 'geekblue' },
  appeal_approved: { label: '申訴核准', color: 'green' },
  gdpr_deletion:   { label: 'GDPR 刪除', color: 'red' },
}

interface LogEntry {
  id: number
  admin: { name: string; email: string }
  action_type: string
  description: string
  ip_address: string
  created_at: string
}

// TODO: Implement GET /api/v1/admin/logs endpoint to fetch real operation logs

const { Title, Text } = Typography

export default function ActivityLogsPage() {
  const user = useAuthStore((s) => s.user)

  if (user?.role !== 'super_admin') {
    return <Result status="403" title="權限不足" subTitle="此頁面僅限 super_admin 查看" />
  }

  return <LogsContent />
}

function LogsContent() {
  const [actionFilter, setActionFilter] = useState<string>('all')
  const [adminEmail, setAdminEmail] = useState('')
  const [nickname, setNickname] = useState('')
  const [logs] = useState<LogEntry[]>([]) // TODO: fetch from GET /api/v1/admin/logs

  const filtered = logs.filter((log) => {
    if (actionFilter !== 'all' && log.action_type !== actionFilter) return false
    if (adminEmail && !log.admin.email.toLowerCase().includes(adminEmail.toLowerCase())) return false
    return true
  })

  const resetFilters = () => {
    setDateRange(null)
    setActionFilter('all')
    setAdminEmail('')
    setNickname('')
  }

  const columns = [
    {
      title: '時間',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 160,
      render: (d: string) => dayjs(d).format('YYYY/MM/DD HH:mm'),
    },
    {
      title: '操作類型',
      dataIndex: 'action_type',
      key: 'action_type',
      width: 120,
      render: (t: ActionType) => <Tag color={actionMeta[t].color}>{actionMeta[t].label}</Tag>,
    },
    {
      title: '描述',
      dataIndex: 'description',
      key: 'description',
    },
    {
      title: '操作者',
      dataIndex: 'admin',
      key: 'admin',
      width: 160,
      render: (_: unknown, record: LogEntry) => (
        <div>
          <div>{record.admin.name}</div>
          <Text type="secondary" style={{ fontSize: 12 }}>{record.admin.email}</Text>
        </div>
      ),
    },
    {
      title: 'IP',
      dataIndex: 'ip_address',
      key: 'ip_address',
      width: 145,
      render: (ip: string) => <code>{ip}</code>,
    },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>操作日誌</Title>

      <Card style={{ marginBottom: 16 }}>
        <Space wrap>
          <Select value={actionFilter} onChange={setActionFilter} style={{ width: 160 }}>
            <Select.Option value="all">全部操作</Select.Option>
            {(Object.keys(actionMeta) as ActionType[]).map((key) => (
              <Select.Option key={key} value={key}>{actionMeta[key].label}</Select.Option>
            ))}
          </Select>
          <Input.Search
            placeholder="操作者 Email"
            value={adminEmail}
            onChange={(e) => setAdminEmail(e.target.value)}
            style={{ width: 200 }}
            allowClear
          />
          <Input.Search
            placeholder="用戶暱稱"
            value={nickname}
            onChange={(e) => setNickname(e.target.value)}
            style={{ width: 180 }}
            allowClear
          />
          <Button icon={<ReloadOutlined />} onClick={resetFilters}>重設</Button>
        </Space>
      </Card>

      <Text type="secondary" style={{ marginBottom: 12, display: 'block' }}>
        共 {filtered.length} 筆記錄
      </Text>

      <Table
        dataSource={filtered}
        columns={columns}
        rowKey="id"
        pagination={{ pageSize: 50, showTotal: (total) => `共 ${total} 筆` }}
        size="middle"
      />
    </div>
  )
}
