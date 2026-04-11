<<<<<<< HEAD
import { useState, useEffect, useCallback } from 'react'
import { Table, Button, Modal, Input, Tag, message, Space, Card, Typography } from 'antd'
import { CheckOutlined, CloseOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
=======
import { useState, useEffect } from 'react'
import { Tabs, Table, Tag, Button, Drawer, Space, Typography, Input, message, Avatar } from 'antd'
import { UserOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import { useAuthStore } from '../../stores/authStore'
>>>>>>> develop
import dayjs from 'dayjs'

const { Title, Text } = Typography
const { TextArea } = Input

<<<<<<< HEAD
interface Verification {
  id: number
  user: { uid: number; nickname: string; avatar: string }
  gender: string
  type: 'photo' | 'credit_card'
  submitted_at: string
  status: 'pending' | 'approved' | 'rejected'
}

const MOCK_VERIFICATIONS: Verification[] = Array.from({ length: 12 }, (_, i) => ({
  id: 100 + i,
  user: {
    uid: 1000 + i,
    nickname: `用戶${1000 + i}`,
    avatar: `https://i.pravatar.cc/40?img=${i + 1}`,
  },
  gender: i % 2 === 0 ? 'female' : 'male',
  type: i % 2 === 0 ? 'photo' : 'credit_card',
  submitted_at: dayjs().subtract(i * 3, 'hour').toISOString(),
  status: 'pending',
}))

const TYPE_LABELS: Record<string, string> = {
  photo: '照片驗證',
  credit_card: '信用卡驗證',
}

const TYPE_COLORS: Record<string, string> = {
  photo: 'blue',
  credit_card: 'green',
}

export default function VerificationsPage() {
  const [data, setData] = useState<Verification[]>([])
  const [loading, setLoading] = useState(false)
  const [rejectModalOpen, setRejectModalOpen] = useState(false)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [actionLoading, setActionLoading] = useState(false)

  const fetchData = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/verifications/pending')
      setData(res.data.data ?? res.data)
    } catch {
      setData(MOCK_VERIFICATIONS)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  const handleApprove = async (id: number) => {
    setActionLoading(true)
    try {
      await apiClient.patch(`/admin/verifications/${id}`, { status: 'approved' })
      message.success('已核准驗證')
      setData((prev) => prev.filter((v) => v.id !== id))
    } catch {
      message.success('已核准驗證（模擬）')
      setData((prev) => prev.filter((v) => v.id !== id))
    } finally {
      setActionLoading(false)
    }
  }

  const openRejectModal = (id: number) => {
    setSelectedId(id)
    setRejectReason('')
    setRejectModalOpen(true)
  }

  const handleReject = async () => {
    if (!selectedId) return
=======
const STATUS_COLORS: Record<string, string> = {
  pending_review: 'orange',
  approved: 'green',
  rejected: 'red',
}
const STATUS_LABELS: Record<string, string> = {
  pending_review: '待審核',
  approved: '已核准',
  rejected: '已拒絕',
}

const LEVEL_LABELS: Record<number, string> = {
  0: 'Lv0 未驗證',
  1: 'Lv1 基本驗證',
  2: 'Lv2 進階驗證',
  3: 'Lv3 完整驗證',
}

const GENDER_LABELS: Record<string, string> = {
  male: '男',
  female: '女',
}

interface Verification {
  id: number
  user: {
    uid: number
    nickname: string
    avatar: string
    gender: string
    level: number
    credit_score: number
  }
  photo_url: string
  random_code: string
  status: string
  reject_reason: string | null
  created_at: string
  reviewed_at: string | null
}

export default function VerificationsPage() {
  const user = useAuthStore((s) => s.user)

  const [activeTab, setActiveTab] = useState<string>('pending')
  const [records, setRecords] = useState<Verification[]>([])
  const [loading, setLoading] = useState(true)
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)

  const [drawerOpen, setDrawerOpen] = useState(false)
  const [selected, setSelected] = useState<Verification | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [processedStatus, setProcessedStatus] = useState<string>('approved')

  useEffect(() => { setPage(1) }, [activeTab, processedStatus])
  useEffect(() => { fetchList() }, [page, activeTab, processedStatus])

  if (user?.role === 'cs') {
    return (
      <div style={{ textAlign: 'center', padding: 80 }}>
        <Title level={3}>403 - 權限不足</Title>
        <Text type="secondary">客服角色無法存取此頁面</Text>
      </div>
    )
  }

  async function fetchList() {
    setLoading(true)
    try {
      if (activeTab === 'pending') {
        const res = await apiClient.get('/admin/verifications/pending', { params: { page, per_page: 20 } })
        setRecords(res.data.data.verifications ?? res.data.data ?? [])
        setTotal(res.data.data.pagination?.total ?? res.data.pagination?.total ?? 0)
      } else {
        const res = await apiClient.get('/admin/verifications', { params: { status: processedStatus, page, per_page: 20 } })
        setRecords(res.data.data.verifications ?? res.data.data ?? [])
        setTotal(res.data.data.pagination?.total ?? res.data.pagination?.total ?? 0)
      }
    } catch {
      setRecords([])
    }
    setLoading(false)
  }

  function openReview(record: Verification) {
    setSelected(record)
    setRejectReason('')
    setDrawerOpen(true)
  }

  async function handleApprove() {
    if (!selected) return
    setSubmitting(true)
    try {
      await apiClient.patch(`/admin/verifications/${selected.id}`, { result: 'approved' })
      message.success('已核准')
      setDrawerOpen(false)
      fetchList()
    } catch {
      message.error('操作失敗')
    }
    setSubmitting(false)
  }

  async function handleReject() {
    if (!selected) return
>>>>>>> develop
    if (!rejectReason.trim()) {
      message.warning('請填寫拒絕原因')
      return
    }
<<<<<<< HEAD
    setActionLoading(true)
    try {
      await apiClient.patch(`/admin/verifications/${selectedId}`, {
        status: 'rejected',
        reason: rejectReason,
      })
      message.success('已拒絕驗證')
      setData((prev) => prev.filter((v) => v.id !== selectedId))
    } catch {
      message.success('已拒絕驗證（模擬）')
      setData((prev) => prev.filter((v) => v.id !== selectedId))
    } finally {
      setActionLoading(false)
      setRejectModalOpen(false)
      setSelectedId(null)
    }
=======
    setSubmitting(true)
    try {
      await apiClient.patch(`/admin/verifications/${selected.id}`, { result: 'rejected', reject_reason: rejectReason.trim() })
      message.success('已拒絕')
      setDrawerOpen(false)
      fetchList()
    } catch {
      message.error('操作失敗')
    }
    setSubmitting(false)
>>>>>>> develop
  }

  const columns = [
    {
<<<<<<< HEAD
      title: 'ID',
      dataIndex: 'id',
      key: 'id',
      width: 80,
    },
    {
      title: '用戶暱稱',
      key: 'user',
      width: 160,
      render: (_: unknown, r: Verification) => (
        <Space>
          <img
            src={r.user.avatar}
            alt={r.user.nickname}
            style={{ width: 32, height: 32, borderRadius: '50%' }}
          />
          <div>
            <div>{r.user.nickname}</div>
            <Text type="secondary" style={{ fontSize: 12 }}>UID: {r.user.uid}</Text>
          </div>
=======
      title: '申請時間', dataIndex: 'created_at', key: 'created_at', width: 150,
      render: (d: string) => d ? dayjs(d).format('YYYY/MM/DD HH:mm') : '-',
    },
    {
      title: '用戶暱稱', key: 'nickname', width: 140,
      render: (_: unknown, r: Verification) => (
        <Space>
          <Avatar size="small" src={r.user.avatar} icon={<UserOutlined />} />
          {r.user.nickname}
>>>>>>> develop
        </Space>
      ),
    },
    {
<<<<<<< HEAD
      title: '性別',
      dataIndex: 'gender',
      key: 'gender',
      width: 80,
      render: (v: string) => (v === 'male' ? '男' : '女'),
    },
    {
      title: '驗證類型',
      dataIndex: 'type',
      key: 'type',
      width: 120,
      render: (v: string) => <Tag color={TYPE_COLORS[v]}>{TYPE_LABELS[v]}</Tag>,
    },
    {
      title: '提交時間',
      dataIndex: 'submitted_at',
      key: 'submitted_at',
      width: 180,
      render: (v: string) => dayjs(v).format('YYYY-MM-DD HH:mm'),
    },
    {
      title: '狀態',
      dataIndex: 'status',
      key: 'status',
      width: 100,
      render: (v: string) => {
        const map: Record<string, { color: string; label: string }> = {
          pending: { color: 'orange', label: '待審核' },
          approved: { color: 'green', label: '已核准' },
          rejected: { color: 'red', label: '已拒絕' },
        }
        const m = map[v] || { color: 'default', label: v }
        return <Tag color={m.color}>{m.label}</Tag>
      },
    },
    {
      title: '操作',
      key: 'actions',
      width: 180,
      render: (_: unknown, r: Verification) =>
        r.status === 'pending' ? (
          <Space>
            <Button
              type="primary"
              size="small"
              icon={<CheckOutlined />}
              loading={actionLoading}
              onClick={() => handleApprove(r.id)}
            >
              核准
            </Button>
            <Button
              danger
              size="small"
              icon={<CloseOutlined />}
              onClick={() => openRejectModal(r.id)}
            >
              拒絕
            </Button>
          </Space>
        ) : null,
=======
      title: '性別', key: 'gender', width: 70,
      render: (_: unknown, r: Verification) => GENDER_LABELS[r.user.gender] || r.user.gender,
    },
    {
      title: '目前等級', key: 'level', width: 120,
      render: (_: unknown, r: Verification) => LEVEL_LABELS[r.user.level] ?? `Lv${r.user.level}`,
    },
    {
      title: '狀態', dataIndex: 'status', key: 'status', width: 90,
      render: (s: string) => <Tag color={STATUS_COLORS[s]}>{STATUS_LABELS[s] || s}</Tag>,
    },
    {
      title: '操作', key: 'actions', width: 90,
      render: (_: unknown, record: Verification) => (
        <Button type="link" size="small" onClick={() => openReview(record)}>
          {record.status === 'pending_review' ? '審核' : '查看'}
        </Button>
      ),
>>>>>>> develop
    },
  ]

  return (
    <div>
<<<<<<< HEAD
      <Title level={4} style={{ marginBottom: 24 }}>驗證審核</Title>

      <Card>
        <Table
          dataSource={data}
          columns={columns}
          rowKey="id"
          loading={loading}
          pagination={{ pageSize: 20, showSizeChanger: true, showTotal: (t) => `共 ${t} 筆` }}
          size="middle"
        />
      </Card>

      <Modal
        title="拒絕驗證"
        open={rejectModalOpen}
        onOk={handleReject}
        onCancel={() => setRejectModalOpen(false)}
        confirmLoading={actionLoading}
        okText="確認拒絕"
        cancelText="取消"
        okButtonProps={{ danger: true }}
      >
        <div style={{ marginBottom: 8 }}>
          <Text>請填寫拒絕原因：</Text>
        </div>
        <TextArea
          rows={4}
          value={rejectReason}
          onChange={(e) => setRejectReason(e.target.value)}
          placeholder="例如：照片不清晰、無法辨識身份..."
        />
      </Modal>
=======
      <Title level={4} style={{ marginBottom: 16 }}>Lv1.5 照片審核</Title>

      <Tabs activeKey={activeTab} onChange={(k) => setActiveTab(k)} items={[
        { key: 'pending', label: '待審核' },
        { key: 'processed', label: '已處理' },
      ]} />

      {activeTab === 'processed' && (
        <Space style={{ marginBottom: 12 }}>
          <Button type={processedStatus === 'approved' ? 'primary' : 'default'} size="small"
            onClick={() => setProcessedStatus('approved')}>已核准</Button>
          <Button type={processedStatus === 'rejected' ? 'primary' : 'default'} size="small"
            onClick={() => setProcessedStatus('rejected')}>已拒絕</Button>
        </Space>
      )}

      <Table dataSource={records} columns={columns} rowKey="id" loading={loading}
        pagination={{ current: page, pageSize: 20, total, onChange: setPage, showTotal: (t) => `共 ${t} 筆` }}
        size="middle" locale={{ emptyText: '目前無審核資料' }} />

      <Drawer title="照片審核" placement="right" width={640}
        open={drawerOpen} onClose={() => setDrawerOpen(false)}>
        {selected && (
          <div>
            <div style={{ display: 'flex', gap: 24, marginBottom: 24 }}>
              {/* Left: user info */}
              <div style={{ flex: '0 0 180px' }}>
                <div style={{ textAlign: 'center', marginBottom: 12 }}>
                  <Avatar size={80} src={selected.user.avatar} icon={<UserOutlined />} />
                </div>
                <div style={{ textAlign: 'center', marginBottom: 8 }}>
                  <Text strong style={{ fontSize: 16 }}>{selected.user.nickname}</Text>
                </div>
                <div style={{ fontSize: 13, color: '#666' }}>
                  <div style={{ marginBottom: 4 }}>目前等級：{LEVEL_LABELS[selected.user.level] ?? `Lv${selected.user.level}`}</div>
                  <div style={{ marginBottom: 4 }}>誠信分數：{selected.user.credit_score}</div>
                  <div>性別：{GENDER_LABELS[selected.user.gender] || selected.user.gender}</div>
                </div>
              </div>

              {/* Right: verification photo */}
              <div style={{ flex: 1 }}>
                <div style={{ marginBottom: 8 }}>
                  <Text type="secondary">驗證照片</Text>
                </div>
                <div style={{ border: '1px solid #d9d9d9', borderRadius: 8, overflow: 'hidden', marginBottom: 12 }}>
                  <img src={selected.photo_url} alt="verification" style={{ width: '100%', display: 'block' }} />
                </div>
                <div style={{ background: '#f5f5f5', padding: '8px 12px', borderRadius: 6 }}>
                  <Text type="secondary">隨機驗證碼：</Text>
                  <Text strong style={{ fontSize: 18, letterSpacing: 2 }}>{selected.random_code}</Text>
                </div>
              </div>
            </div>

            {selected.status === 'pending_review' && (
              <div>
                <div style={{ marginBottom: 12 }}>
                  <Text type="secondary">拒絕原因（拒絕時必填）：</Text>
                  <TextArea rows={3} value={rejectReason} onChange={(e) => setRejectReason(e.target.value)}
                    placeholder="請輸入拒絕原因..." style={{ marginTop: 4 }} />
                </div>
                <Space>
                  <Button type="primary" style={{ background: '#52c41a', borderColor: '#52c41a' }}
                    loading={submitting} onClick={handleApprove}>
                    核准
                  </Button>
                  <Button danger loading={submitting} onClick={handleReject}>
                    拒絕
                  </Button>
                </Space>
              </div>
            )}

            {selected.status === 'rejected' && selected.reject_reason && (
              <div style={{ background: '#fff2f0', border: '1px solid #ffccc7', borderRadius: 6, padding: 12, marginTop: 12 }}>
                <Text type="secondary">拒絕原因：</Text>
                <div>{selected.reject_reason}</div>
              </div>
            )}
          </div>
        )}
      </Drawer>
>>>>>>> develop
    </div>
  )
}
