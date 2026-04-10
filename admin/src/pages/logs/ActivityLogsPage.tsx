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
    },
  ]

  return (
    <div>
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
    </div>
  )
}
