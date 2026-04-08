import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { Table, Input, Select, Button, Tag, Badge, Space, Typography, Avatar } from 'antd'
import { SearchOutlined, ReloadOutlined } from '@ant-design/icons'
import { MOCK_MEMBERS } from '../../mocks/members'
import { getCreditLevel, CreditLevelLabel, CreditLevelColor, CreditLevelBg } from '../../types/admin'
import type { MemberListItem } from '../../types/admin'
import dayjs from 'dayjs'

const { Title } = Typography

export default function MembersPage() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [genderFilter, setGenderFilter] = useState<string>('all')
  const [levelFilter, setLevelFilter] = useState<number | null>(null)
  const [creditFilter, setCreditFilter] = useState<string>('all')
  const [statusFilter, setStatusFilter] = useState<string>('all')

  const filtered = useMemo(() => {
    let data = [...MOCK_MEMBERS]
    if (search) {
      const q = search.toLowerCase()
      data = data.filter((m) => m.nickname.includes(q) || m.email.toLowerCase().includes(q))
    }
    if (genderFilter !== 'all') data = data.filter((m) => m.gender === genderFilter)
    if (levelFilter !== null) data = data.filter((m) => m.level === levelFilter)
    if (creditFilter !== 'all') {
      const ranges: Record<string, [number, number]> = { top: [91, 100], good: [61, 90], normal: [31, 60], low: [0, 30] }
      const [min, max] = ranges[creditFilter] || [0, 100]
      data = data.filter((m) => m.credit_score >= min && m.credit_score <= max)
    }
    if (statusFilter !== 'all') data = data.filter((m) => m.status === statusFilter)
    return data
  }, [search, genderFilter, levelFilter, creditFilter, statusFilter])

  const resetFilters = () => {
    setSearch('')
    setGenderFilter('all')
    setLevelFilter(null)
    setCreditFilter('all')
    setStatusFilter('all')
  }

  const columns = [
    {
      title: '暱稱',
      dataIndex: 'nickname',
      key: 'nickname',
      render: (_: string, record: MemberListItem) => (
        <Space>
          <Avatar src={record.avatar} size={36} />
          <a onClick={() => navigate(`/members/${record.uid}`)}>{record.nickname}</a>
        </Space>
      ),
    },
    { title: 'UID', dataIndex: 'uid', key: 'uid', width: 70 },
    {
      title: '性別',
      dataIndex: 'gender',
      key: 'gender',
      width: 70,
      render: (g: string) => (g === 'male' ? '男' : '女'),
    },
    { title: '年齡', dataIndex: 'age', key: 'age', width: 70, sorter: (a: MemberListItem, b: MemberListItem) => a.age - b.age },
    { title: '地區', dataIndex: 'location', key: 'location', width: 90 },
    {
      title: '誠信分數',
      dataIndex: 'credit_score',
      key: 'credit_score',
      width: 120,
      sorter: (a: MemberListItem, b: MemberListItem) => a.credit_score - b.credit_score,
      render: (score: number) => {
        const level = getCreditLevel(score)
        return (
          <Tag style={{ background: CreditLevelBg[level], color: CreditLevelColor[level], border: 'none', fontWeight: 600 }}>
            {score} {CreditLevelLabel[level]}
          </Tag>
        )
      },
    },
    {
      title: '等級',
      dataIndex: 'level',
      key: 'level',
      width: 80,
      render: (lv: number) => <Tag color={lv >= 3 ? 'gold' : lv >= 2 ? 'blue' : 'default'}>Lv{lv}</Tag>,
    },
    {
      title: '驗證',
      key: 'verify',
      width: 120,
      render: (_: unknown, r: MemberListItem) => (
        <Space size={4}>
          {r.email_verified && <Tag color="blue" style={{ margin: 0, fontSize: 10, padding: '0 4px' }}>Email</Tag>}
          {r.phone_verified && <Tag color="green" style={{ margin: 0, fontSize: 10, padding: '0 4px' }}>手機</Tag>}
          {r.advanced_verified && <Tag color="orange" style={{ margin: 0, fontSize: 10, padding: '0 4px' }}>進階</Tag>}
        </Space>
      ),
    },
    {
      title: '最後上線',
      dataIndex: 'last_login_at',
      key: 'last_login_at',
      width: 140,
      sorter: (a: MemberListItem, b: MemberListItem) => new Date(a.last_login_at).getTime() - new Date(b.last_login_at).getTime(),
      render: (d: string) => dayjs(d).format('MM/DD HH:mm'),
    },
    {
      title: '狀態',
      dataIndex: 'status',
      key: 'status',
      width: 80,
      render: (s: string) => <Tag color={s === 'active' ? 'green' : 'red'}>{s === 'active' ? '正常' : '停權'}</Tag>,
    },
    {
      title: '操作',
      key: 'actions',
      width: 80,
      render: (_: unknown, record: MemberListItem) => (
        <Button type="link" size="small" onClick={() => navigate(`/members/${record.uid}`)}>
          查看
        </Button>
      ),
    },
  ]

  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
        <Space>
          <Title level={4} style={{ margin: 0 }}>會員管理</Title>
          <Badge count={filtered.length} showZero style={{ backgroundColor: '#F0294E' }} />
        </Space>
      </div>

      <Space wrap style={{ marginBottom: 16 }}>
        <Input
          placeholder="搜尋暱稱或 Email"
          prefix={<SearchOutlined />}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          style={{ width: 220 }}
          allowClear
        />
        <Select value={genderFilter} onChange={setGenderFilter} style={{ width: 100 }}>
          <Select.Option value="all">全部性別</Select.Option>
          <Select.Option value="male">男</Select.Option>
          <Select.Option value="female">女</Select.Option>
        </Select>
        <Select value={levelFilter} onChange={setLevelFilter} style={{ width: 120 }} allowClear placeholder="會員等級">
          <Select.Option value={1}>Lv1</Select.Option>
          <Select.Option value={2}>Lv2</Select.Option>
          <Select.Option value={3}>Lv3</Select.Option>
        </Select>
        <Select value={creditFilter} onChange={setCreditFilter} style={{ width: 120 }}>
          <Select.Option value="all">全部誠信</Select.Option>
          <Select.Option value="top">頂級 91+</Select.Option>
          <Select.Option value="good">優質 61-90</Select.Option>
          <Select.Option value="normal">普通 31-60</Select.Option>
          <Select.Option value="low">受限 0-30</Select.Option>
        </Select>
        <Select value={statusFilter} onChange={setStatusFilter} style={{ width: 100 }}>
          <Select.Option value="all">全部狀態</Select.Option>
          <Select.Option value="active">正常</Select.Option>
          <Select.Option value="suspended">停權</Select.Option>
        </Select>
        <Button icon={<ReloadOutlined />} onClick={resetFilters}>重設</Button>
      </Space>

      <Table
        dataSource={filtered}
        columns={columns}
        rowKey="uid"
        pagination={{ pageSize: 20, showTotal: (total) => `共 ${total} 筆` }}
        size="middle"
        scroll={{ x: 1100 }}
      />
    </div>
  )
}
