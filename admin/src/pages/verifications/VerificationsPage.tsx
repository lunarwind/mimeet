import { useState, useEffect, useCallback } from 'react'
import { Table, Button, Modal, Input, Tag, message, Space, Card, Typography } from 'antd'
import { CheckOutlined, CloseOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography
const { TextArea } = Input

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
    if (!rejectReason.trim()) {
      message.warning('請填寫拒絕原因')
      return
    }
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
  }

  const columns = [
    {
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
        </Space>
      ),
    },
    {
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
    },
  ]

  return (
    <div>
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
    </div>
  )
}
