import { useState, useEffect } from 'react'
import { Table, Input, Select, Tag, Typography, Space, Button } from 'antd'
import { SearchOutlined, ReloadOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography

const ACTION_LABELS: Record<string, { label: string; color: string }> = {
  login: { label: '登入', color: 'blue' },
  profile_update: { label: '修改資料', color: 'cyan' },
  photo_upload: { label: '上傳照片', color: 'green' },
  photo_delete: { label: '刪除照片', color: 'orange' },
  phone_changed: { label: '變更手機', color: 'gold' },
  verification_submitted: { label: '提交驗證', color: 'purple' },
}

interface ActivityLog {
  id: number
  user_id: number
  user_nickname: string
  user_email: string
  action: string
  metadata: Record<string, unknown> | null
  ip_address: string | null
  created_at: string
}

export default function UserActivityLogsPage() {
  const [logs, setLogs] = useState<ActivityLog[]>([])
  const [loading, setLoading] = useState(true)
  const [userId, setUserId] = useState('')
  const [actionFilter, setActionFilter] = useState<string>('all')
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)

  useEffect(() => { fetchLogs() }, [page, actionFilter])

  async function fetchLogs() {
    setLoading(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (userId) params.user_id = userId
      if (actionFilter !== 'all') params.action = actionFilter
      const res = await apiClient.get('/admin/user-activity-logs', { params })
      setLogs(res.data.data.logs ?? [])
      setTotal(res.data.data.pagination?.total ?? 0)
    } catch { setLogs([]) }
    setLoading(false)
  }

  function handleSearch() {
    setPage(1)
    fetchLogs()
  }

  function resetFilters() {
    setUserId('')
    setActionFilter('all')
    setPage(1)
    setTimeout(fetchLogs, 0)
  }

  const columns = [
    {
      title: '時間', dataIndex: 'created_at', key: 'created_at', width: 160,
      render: (d: string) => d ? dayjs(d).format('MM/DD HH:mm:ss') : '-',
    },
    {
      title: '用戶', key: 'user', width: 160,
      render: (_: unknown, r: ActivityLog) => (
        <div>
          <Text strong style={{ fontSize: 13 }}>{r.user_nickname}</Text>
          <br />
          <Text type="secondary" style={{ fontSize: 11 }}>ID:{r.user_id}</Text>
        </div>
      ),
    },
    {
      title: '操作', dataIndex: 'action', key: 'action', width: 120,
      render: (action: string) => {
        const info = ACTION_LABELS[action]
        return info
          ? <Tag color={info.color}>{info.label}</Tag>
          : <Tag>{action}</Tag>
      },
    },
    {
      title: '詳細', dataIndex: 'metadata', key: 'metadata',
      render: (meta: Record<string, unknown> | null) => {
        if (!meta) return '-'
        return <Text type="secondary" style={{ fontSize: 12 }}>{JSON.stringify(meta)}</Text>
      },
    },
    {
      title: 'IP', dataIndex: 'ip_address', key: 'ip_address', width: 130,
      render: (ip: string | null) => ip ?? '-',
    },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>用戶活動日誌</Title>

      <Space wrap style={{ marginBottom: 16 }}>
        <Input
          placeholder="搜尋用戶 ID"
          prefix={<SearchOutlined />}
          value={userId}
          onChange={(e) => setUserId(e.target.value)}
          onPressEnter={handleSearch}
          style={{ width: 160 }}
          allowClear
        />
        <Select value={actionFilter} onChange={(v) => { setActionFilter(v); setPage(1) }} style={{ width: 140 }}>
          <Select.Option value="all">全部操作</Select.Option>
          <Select.Option value="login">登入</Select.Option>
          <Select.Option value="profile_update">修改資料</Select.Option>
          <Select.Option value="photo_upload">上傳照片</Select.Option>
          <Select.Option value="phone_changed">變更手機</Select.Option>
          <Select.Option value="verification_submitted">提交驗證</Select.Option>
        </Select>
        <Button onClick={handleSearch}>搜尋</Button>
        <Button icon={<ReloadOutlined />} onClick={resetFilters}>重設</Button>
      </Space>

      <Table
        dataSource={logs}
        columns={columns}
        rowKey="id"
        loading={loading}
        pagination={{ current: page, pageSize: 20, total, onChange: setPage, showTotal: (t) => `共 ${t} 筆` }}
        size="middle"
        locale={{ emptyText: '目前無活動日誌' }}
      />
    </div>
  )
}
