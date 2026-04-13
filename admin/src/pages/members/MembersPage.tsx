import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Table, Input, Select, Button, Tag, Badge, Space, Typography, Avatar } from 'antd'
import { SearchOutlined, ReloadOutlined } from '@ant-design/icons'
import { getCreditLevel, CreditLevelLabel, CreditLevelColor, CreditLevelBg } from '../../types/admin'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title } = Typography

interface Member {
  id: number
  nickname: string | null
  email: string
  gender: string | null
  membership_level: number
  credit_score: number
  status: string
  email_verified: boolean
  created_at: string
  avatar_url?: string | null
}

export default function MembersPage() {
  const navigate = useNavigate()
  const [members, setMembers] = useState<Member[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)

  useEffect(() => { fetchMembers() }, [page, statusFilter])

  async function fetchMembers() {
    setLoading(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (search) params.search = search
      if (statusFilter !== 'all') params.status = statusFilter
      const res = await apiClient.get('/admin/members', { params })
      setMembers(res.data?.data?.members ?? [])
      setTotal(res.data?.data?.pagination?.total ?? 0)
    } catch {
      setMembers([])
    }
    setLoading(false)
  }

  function handleSearch() {
    setPage(1)
    fetchMembers()
  }

  function resetFilters() {
    setSearch('')
    setStatusFilter('all')
    setPage(1)
    setTimeout(fetchMembers, 0)
  }

  const columns = [
    {
      title: '暱稱', dataIndex: 'nickname', key: 'nickname',
      render: (_: string | null, record: Member) => (
        <Space>
          <Avatar src={record.avatar_url || undefined} size={36}>{record.nickname?.[0] ?? '?'}</Avatar>
          <a onClick={() => navigate(`/members/${record.id}`)}>{record.nickname || <span style={{ color: '#9CA3AF' }}>未設定</span>}</a>
        </Space>
      ),
    },
    { title: 'ID', dataIndex: 'id', key: 'id', width: 70 },
    { title: '性別', dataIndex: 'gender', key: 'gender', width: 70, render: (g: string | null) => g === 'male' ? '男' : g === 'female' ? '女' : '—' },
    {
      title: '誠信分數', dataIndex: 'credit_score', key: 'credit_score', width: 120,
      render: (score: number) => {
        const s = score ?? 0
        const level = getCreditLevel(s)
        return <Tag style={{ background: CreditLevelBg[level], color: CreditLevelColor[level], border: 'none', fontWeight: 600 }}>{s} {CreditLevelLabel[level]}</Tag>
      },
    },
    {
      title: '等級', dataIndex: 'membership_level', key: 'level', width: 80,
      render: (lv: number) => { const v = lv ?? 0; return <Tag color={v >= 3 ? 'gold' : v >= 2 ? 'blue' : 'default'}>Lv{v}</Tag> },
    },
    {
      title: '狀態', dataIndex: 'status', key: 'status', width: 80,
      render: (s: string) => {
        if (s === 'active') return <Tag color="green">正常</Tag>
        if (s === 'suspended' || s === 'auto_suspended') return <Tag color="red">停權</Tag>
        return <Tag>{s || '未知'}</Tag>
      },
    },
    {
      title: 'Email', dataIndex: 'email_verified', key: 'email_verified', width: 80,
      render: (v: boolean) => v ? <Tag color="blue">已驗證</Tag> : <Tag>未驗證</Tag>,
    },
    {
      title: '註冊時間', dataIndex: 'created_at', key: 'created_at', width: 140,
      render: (d: string) => d ? dayjs(d).format('MM/DD HH:mm') : '—',
    },
    {
      title: '操作', key: 'actions', width: 80,
      render: (_: unknown, record: Member) => (
        <Button type="link" size="small" onClick={() => navigate(`/members/${record.id}`)}>查看</Button>
      ),
    },
  ]

  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
        <Space>
          <Title level={4} style={{ margin: 0 }}>會員管理</Title>
          <Badge count={total} showZero style={{ backgroundColor: '#F0294E' }} />
        </Space>
      </div>

      <Space wrap style={{ marginBottom: 16 }}>
        <Input placeholder="搜尋暱稱或 Email" prefix={<SearchOutlined />} value={search}
          onChange={(e) => setSearch(e.target.value)} onPressEnter={handleSearch} style={{ width: 220 }} allowClear />
        <Select value={statusFilter} onChange={setStatusFilter} style={{ width: 100 }}>
          <Select.Option value="all">全部狀態</Select.Option>
          <Select.Option value="active">正常</Select.Option>
          <Select.Option value="suspended">停權</Select.Option>
        </Select>
        <Button onClick={handleSearch}>搜尋</Button>
        <Button icon={<ReloadOutlined />} onClick={resetFilters}>重設</Button>
      </Space>

      <Table
        dataSource={members}
        columns={columns}
        rowKey="id"
        loading={loading}
        pagination={{ current: page, pageSize: 20, total, onChange: setPage, showTotal: (t) => `共 ${t} 筆` }}
        size="middle"
        locale={{ emptyText: '目前無會員資料' }}
      />
    </div>
  )
}
