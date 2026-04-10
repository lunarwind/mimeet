import { useState, useEffect, useCallback } from 'react'
import { Table, Select, Button, Tag, Typography, Card, Space, Result, Switch } from 'antd'
import { ReloadOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import { useAuthStore } from '../../stores/authStore'
import dayjs from 'dayjs'

const actionMeta: Record<string, { label: string; color: string }> = {
  adjust_credit:   { label: '調整分數',   color: 'orange' },
  member_action:   { label: '會員操作',   color: 'orange' },
  suspend:         { label: '停權',       color: 'red' },
  unsuspend:       { label: '解除停權',   color: 'green' },
  set_level:       { label: '調整等級',   color: 'blue' },
  settings_change: { label: '設定變更',   color: 'purple' },
  ticket_process:  { label: 'Ticket處理', color: 'cyan' },
  system_settings_change: { label: '系統設定', color: 'geekblue' },
  appeal_approved: { label: '申訴核准', color: 'green' },
  verification_review: { label: '驗證審核', color: 'blue' },
  broadcast_manage: { label: '廣播管理', color: 'purple' },
  delete:          { label: '刪除', color: 'red' },
}

interface LogEntry {
  id: number
  admin_id: number
  action: string
  resource_type: string | null
  resource_id: number | null
  description: string
  ip_address: string | null
  created_at: string
}

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
  const [logs, setLogs] = useState<LogEntry[]>([])
  const [loading, setLoading] = useState(false)
  const [showIp, setShowIp] = useState(false)
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)

  const loadLogs = useCallback(async () => {
    setLoading(true)
    try {
      const params: Record<string, string | number | boolean> = { page, per_page: 50, show_ip: showIp }
      if (actionFilter !== 'all') params.action_type = actionFilter
      const res = await apiClient.get('/admin/logs', { params })
      setLogs(res.data.data.logs)
      setTotal(res.data.pagination?.total ?? 0)
    } catch { /* ignore */ }
    setLoading(false)
  }, [page, showIp, actionFilter])

  useEffect(() => { loadLogs() }, [loadLogs])

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
      dataIndex: 'action',
      key: 'action',
      width: 120,
      render: (t: string) => {
        const meta = actionMeta[t] || { label: t, color: 'default' }
        return <Tag color={meta.color}>{meta.label}</Tag>
      },
    },
    {
      title: '描述',
      dataIndex: 'description',
      key: 'description',
    },
    {
      title: '資源',
      key: 'resource',
      width: 120,
      render: (_: unknown, record: LogEntry) => record.resource_type
        ? <Text type="secondary">{record.resource_type} #{record.resource_id}</Text>
        : '-',
    },
    {
      title: 'IP',
      dataIndex: 'ip_address',
      key: 'ip_address',
      width: 145,
      render: (ip: string | null) => ip ? <code>{ip}</code> : <Text type="secondary">隱藏</Text>,
    },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>操作日誌</Title>

      <Card style={{ marginBottom: 16 }}>
        <Space wrap>
          <Select value={actionFilter} onChange={(v) => { setActionFilter(v); setPage(1) }} style={{ width: 160 }}>
            <Select.Option value="all">全部操作</Select.Option>
            {Object.entries(actionMeta).map(([key, meta]) => (
              <Select.Option key={key} value={key}>{meta.label}</Select.Option>
            ))}
          </Select>
          <Space>
            <Text>顯示 IP</Text>
            <Switch checked={showIp} onChange={(v) => { setShowIp(v); setPage(1) }} size="small" />
          </Space>
          <Button icon={<ReloadOutlined />} onClick={loadLogs}>重新整理</Button>
        </Space>
      </Card>

      <Text type="secondary" style={{ marginBottom: 12, display: 'block' }}>
        共 {total} 筆記錄
      </Text>

      <Table
        dataSource={logs}
        columns={columns}
        rowKey="id"
        loading={loading}
        pagination={{
          current: page,
          pageSize: 50,
          total,
          onChange: setPage,
          showTotal: (t) => `共 ${t} 筆`,
        }}
        size="middle"
      />
    </div>
  )
}
