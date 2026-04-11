<<<<<<< HEAD
import { useState, useMemo, useEffect, useCallback } from 'react'
import { Table, Card, Typography, Select, DatePicker, Space, Tag, Input, Switch } from 'antd'
import { SearchOutlined } from '@ant-design/icons'
import { mockLogs, actionMeta } from '../../mocks/logs'
import type { ActionType, LogEntry } from '../../mocks/logs'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography
const { RangePicker } = DatePicker

export default function ActivityLogsPage() {
  const [logs, setLogs] = useState<LogEntry[]>(mockLogs)
  const [loading, setLoading] = useState(false)
  const [actionFilter, setActionFilter] = useState<ActionType | 'all'>('all')
  const [search, setSearch] = useState('')
  const [showIp, setShowIp] = useState(false)
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null] | null>(null)

  const fetchLogs = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/logs')
      const apiData = res.data.data ?? res.data
      if (Array.isArray(apiData) && apiData.length > 0) {
        setLogs(apiData)
      }
    } catch {
      // Fall back to mock data (already set as default)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchLogs()
  }, [fetchLogs])

  const filtered = useMemo(() => {
    let data = [...logs]
    if (actionFilter !== 'all') {
      data = data.filter((l) => l.action_type === actionFilter)
    }
    if (search) {
      const q = search.toLowerCase()
      data = data.filter(
        (l) =>
          l.description.toLowerCase().includes(q) ||
          l.admin.name.toLowerCase().includes(q) ||
          (l.target_user_nickname && l.target_user_nickname.toLowerCase().includes(q))
      )
    }
    if (dateRange && dateRange[0] && dateRange[1]) {
      const start = dateRange[0].startOf('day')
      const end = dateRange[1].endOf('day')
      data = data.filter((l) => {
        const d = dayjs(l.created_at)
        return d.isAfter(start) && d.isBefore(end)
      })
    }
    return data
  }, [logs, actionFilter, search, dateRange])

  const columns = [
    {
      title: 'ID',
      dataIndex: 'id',
      key: 'id',
      width: 80,
    },
    {
      title: '管理員',
      key: 'admin',
      width: 160,
      render: (_: unknown, r: LogEntry) => (
        <div>
          <div>{r.admin.name}</div>
          <Text type="secondary" style={{ fontSize: 12 }}>{r.admin.role}</Text>
        </div>
      ),
    },
    {
      title: '操作類型',
      dataIndex: 'action_type',
      key: 'action_type',
      width: 120,
      render: (v: ActionType) => {
        const meta = actionMeta[v]
        return <Tag color={meta.color}>{meta.label}</Tag>
      },
    },
    {
      title: '說明',
      dataIndex: 'description',
      key: 'description',
    },
    {
      title: '對象',
      dataIndex: 'target_user_nickname',
      key: 'target',
      width: 120,
      render: (v: string | null) => v || <Text type="secondary">—</Text>,
    },
    ...(showIp
      ? [
          {
            title: 'IP',
            dataIndex: 'ip_address',
            key: 'ip',
            width: 140,
          },
        ]
      : []),
    {
      title: '時間',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 180,
      render: (v: string) => dayjs(v).format('YYYY-MM-DD HH:mm'),
=======
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
>>>>>>> develop
    },
  ]

  return (
    <div>
<<<<<<< HEAD
      <Title level={4} style={{ marginBottom: 24 }}>操作日誌</Title>

      <Card style={{ marginBottom: 16 }}>
        <Space wrap size="middle">
          <Input
            placeholder="搜尋描述 / 管理員 / 對象"
            prefix={<SearchOutlined />}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            style={{ width: 240 }}
            allowClear
          />
          <Select
            value={actionFilter}
            onChange={setActionFilter}
            style={{ width: 160 }}
            options={[
              { value: 'all', label: '全部類型' },
              ...Object.entries(actionMeta).map(([k, v]) => ({ value: k, label: v.label })),
            ]}
          />
          <RangePicker
            onChange={(v) => setDateRange(v as [dayjs.Dayjs | null, dayjs.Dayjs | null] | null)}
          />
          <Space>
            <Text type="secondary">顯示 IP</Text>
            <Switch checked={showIp} onChange={setShowIp} size="small" />
          </Space>
        </Space>
      </Card>

      <Card>
        <Table
          dataSource={filtered}
          columns={columns}
          rowKey="id"
          loading={loading}
          pagination={{ pageSize: 20, showSizeChanger: true, showTotal: (t) => `共 ${t} 筆` }}
          size="middle"
        />
      </Card>
=======
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
>>>>>>> develop
    </div>
  )
}
