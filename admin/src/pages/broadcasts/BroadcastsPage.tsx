import { useState, useEffect, useCallback } from 'react'
<<<<<<< HEAD
import { Table, Button, Modal, Input, Tag, message, Space, Card, Typography, Radio, Select, Form } from 'antd'
import { PlusOutlined, SendOutlined } from '@ant-design/icons'
=======
import {
  Table, Tag, Button, Drawer, Space, Typography, Input, Select, message,
  Form, Radio, Collapse, InputNumber, Modal,
} from 'antd'
import { PlusOutlined } from '@ant-design/icons'
>>>>>>> develop
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography
const { TextArea } = Input

<<<<<<< HEAD
interface Broadcast {
  id: number
  title: string
  content: string
  delivery_mode: 'push' | 'in_app' | 'both'
  target_gender: 'all' | 'male' | 'female'
  status: 'draft' | 'sent' | 'scheduled'
  sent_count: number
  created_at: string
}

const MOCK_BROADCASTS: Broadcast[] = Array.from({ length: 8 }, (_, i) => ({
  id: 200 + i,
  title: `廣播活動 ${i + 1}`,
  content: `這是廣播內容 ${i + 1}，包含一些通知訊息。`,
  delivery_mode: (['push', 'in_app', 'both'] as const)[i % 3],
  target_gender: (['all', 'male', 'female'] as const)[i % 3],
  status: i < 3 ? 'draft' : 'sent',
  sent_count: i < 3 ? 0 : 500 + i * 100,
  created_at: dayjs().subtract(i, 'day').toISOString(),
}))

const DELIVERY_LABELS: Record<string, string> = {
  push: '推播通知',
  in_app: '站內通知',
  both: '推播 + 站內',
}

const GENDER_LABELS: Record<string, string> = {
  all: '全部',
  male: '僅男性',
  female: '僅女性',
}

const STATUS_COLORS: Record<string, string> = {
  draft: 'default',
  sent: 'green',
  scheduled: 'blue',
=======
const MODE_LABELS: Record<string, string> = {
  notification: '系統通知',
  dm: '私訊',
  both: '兩者',
}
const MODE_COLORS: Record<string, string> = {
  notification: 'blue',
  dm: 'purple',
  both: 'cyan',
>>>>>>> develop
}

const STATUS_LABELS: Record<string, string> = {
  draft: '草稿',
<<<<<<< HEAD
  sent: '已發送',
  scheduled: '排程中',
}

export default function BroadcastsPage() {
  const [data, setData] = useState<Broadcast[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [sendLoading, setSendLoading] = useState(false)
  const [form] = Form.useForm()

  const fetchData = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/broadcasts')
      setData(res.data.data ?? res.data)
    } catch {
      setData(MOCK_BROADCASTS)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  const handleCreate = async () => {
    try {
      const values = await form.validateFields()
      try {
        const res = await apiClient.post('/admin/broadcasts', values)
        const newItem = res.data.data ?? {
          id: Date.now(),
          ...values,
          status: 'draft',
          sent_count: 0,
          created_at: new Date().toISOString(),
        }
        setData((prev) => [newItem, ...prev])
        message.success('廣播已建立')
      } catch {
        const newItem: Broadcast = {
          id: Date.now(),
          ...values,
          status: 'draft',
          sent_count: 0,
          created_at: new Date().toISOString(),
        }
        setData((prev) => [newItem, ...prev])
        message.success('廣播已建立（模擬）')
      }
      setModalOpen(false)
      form.resetFields()
    } catch {
      // form validation failed
    }
  }

  const handleSend = async (id: number) => {
    setSendLoading(true)
    try {
      await apiClient.post(`/admin/broadcasts/${id}/send`)
      message.success('廣播已發送')
      setData((prev) =>
        prev.map((b) => (b.id === id ? { ...b, status: 'sent' as const, sent_count: 999 } : b))
      )
    } catch {
      message.success('廣播已發送（模擬）')
      setData((prev) =>
        prev.map((b) => (b.id === id ? { ...b, status: 'sent' as const, sent_count: 999 } : b))
      )
    } finally {
      setSendLoading(false)
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
      title: '標題',
      dataIndex: 'title',
      key: 'title',
    },
    {
      title: '發送方式',
      dataIndex: 'delivery_mode',
      key: 'delivery_mode',
      width: 130,
      render: (v: string) => DELIVERY_LABELS[v] || v,
    },
    {
      title: '目標性別',
      dataIndex: 'target_gender',
      key: 'target_gender',
      width: 100,
      render: (v: string) => GENDER_LABELS[v] || v,
    },
    {
      title: '狀態',
      dataIndex: 'status',
      key: 'status',
      width: 100,
      render: (v: string) => <Tag color={STATUS_COLORS[v]}>{STATUS_LABELS[v]}</Tag>,
    },
    {
      title: '發送數',
      dataIndex: 'sent_count',
      key: 'sent_count',
      width: 100,
      render: (v: number) => (v > 0 ? v.toLocaleString() : <Text type="secondary">-</Text>),
    },
    {
      title: '建立時間',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 180,
      render: (v: string) => dayjs(v).format('YYYY-MM-DD HH:mm'),
    },
    {
      title: '操作',
      key: 'actions',
      width: 120,
      render: (_: unknown, r: Broadcast) =>
        r.status === 'draft' ? (
          <Button
            type="primary"
            size="small"
            icon={<SendOutlined />}
            loading={sendLoading}
            onClick={() => handleSend(r.id)}
          >
            發送
          </Button>
        ) : null,
=======
  sending: '發送中',
  completed: '已完成',
  failed: '失敗',
}
const STATUS_COLORS: Record<string, string> = {
  draft: 'default',
  sending: 'processing',
  completed: 'success',
  failed: 'error',
}

interface Broadcast {
  id: number
  title: string
  content: string
  mode: string
  target_count: number
  delivered_count: number
  status: string
  filters: {
    gender?: string
    level_min?: number
    level_max?: number
    credit_min?: number
    credit_max?: number
  }
  created_at: string
}

interface BroadcastForm {
  title: string
  content: string
  mode: string
  gender: string
  level_min: number
  level_max: number
  credit_min: number
  credit_max: number
}

const defaultForm: BroadcastForm = {
  title: '',
  content: '',
  mode: 'notification',
  gender: 'all',
  level_min: 0,
  level_max: 3,
  credit_min: 0,
  credit_max: 100,
}

export default function BroadcastsPage() {
  const [broadcasts, setBroadcasts] = useState<Broadcast[]>([])
  const [loading, setLoading] = useState(true)
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)

  const [drawerOpen, setDrawerOpen] = useState(false)
  const [form, setForm] = useState<BroadcastForm>({ ...defaultForm })
  const [estimatedCount, setEstimatedCount] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => { fetchList() }, [page])

  async function fetchList() {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/broadcasts', { params: { page, per_page: 20 } })
      setBroadcasts(res.data.data.broadcasts ?? res.data.data ?? [])
      setTotal(res.data.data.pagination?.total ?? res.data.pagination?.total ?? 0)
    } catch {
      setBroadcasts([])
    }
    setLoading(false)
  }

  const fetchEstimate = useCallback(async (f: BroadcastForm) => {
    try {
      const params: Record<string, string | number> = {}
      if (f.gender !== 'all') params.gender = f.gender
      params.level_min = f.level_min
      params.level_max = f.level_max
      params.credit_min = f.credit_min
      params.credit_max = f.credit_max
      const res = await apiClient.get('/admin/broadcasts/estimate', { params })
      setEstimatedCount(res.data.data.count ?? res.data.data?.estimated_count ?? null)
    } catch {
      setEstimatedCount(null)
    }
  }, [])

  function openCreate() {
    const f = { ...defaultForm }
    setForm(f)
    setEstimatedCount(null)
    setDrawerOpen(true)
    fetchEstimate(f)
  }

  function updateForm(patch: Partial<BroadcastForm>) {
    const next = { ...form, ...patch }
    setForm(next)
    fetchEstimate(next)
  }

  async function handleCreate() {
    if (!form.title.trim()) { message.warning('請填寫標題'); return }
    if (!form.content.trim()) { message.warning('請填寫內容'); return }
    setSubmitting(true)
    try {
      const payload = {
        title: form.title.trim(),
        content: form.content.trim(),
        mode: form.mode,
        filters: {
          gender: form.gender !== 'all' ? form.gender : undefined,
          level_min: form.level_min,
          level_max: form.level_max,
          credit_min: form.credit_min,
          credit_max: form.credit_max,
        },
      }
      await apiClient.post('/admin/broadcasts', payload)
      message.success('廣播已建立（草稿）')
      setDrawerOpen(false)
      fetchList()
    } catch {
      message.error('建立失敗')
    }
    setSubmitting(false)
  }

  function handleSend(record: Broadcast) {
    Modal.confirm({
      title: '確認發送',
      content: `將發送給 ${record.target_count} 位用戶，確定嗎？`,
      okText: '確定發送',
      cancelText: '取消',
      onOk: async () => {
        try {
          await apiClient.post(`/admin/broadcasts/${record.id}/send`)
          message.success('已開始發送')
          fetchList()
        } catch {
          message.error('發送失敗')
        }
      },
    })
  }

  const columns = [
    { title: '標題', dataIndex: 'title', key: 'title', ellipsis: true },
    {
      title: '發送模式', dataIndex: 'mode', key: 'mode', width: 100,
      render: (m: string) => <Tag color={MODE_COLORS[m]}>{MODE_LABELS[m] || m}</Tag>,
    },
    { title: '目標人數', dataIndex: 'target_count', key: 'target_count', width: 100 },
    { title: '已送達', dataIndex: 'delivered_count', key: 'delivered_count', width: 80 },
    {
      title: '狀態', dataIndex: 'status', key: 'status', width: 90,
      render: (s: string) => <Tag color={STATUS_COLORS[s]}>{STATUS_LABELS[s] || s}</Tag>,
    },
    {
      title: '建立時間', dataIndex: 'created_at', key: 'created_at', width: 150,
      render: (d: string) => d ? dayjs(d).format('YYYY/MM/DD HH:mm') : '-',
    },
    {
      title: '操作', key: 'actions', width: 100,
      render: (_: unknown, record: Broadcast) => (
        <Space>
          {record.status === 'draft' && (
            <Button type="link" size="small" onClick={() => handleSend(record)}>發送</Button>
          )}
        </Space>
      ),
>>>>>>> develop
    },
  ]

  return (
    <div>
<<<<<<< HEAD
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <Title level={4} style={{ margin: 0 }}>廣播工具</Title>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => setModalOpen(true)}>
          新增廣播
        </Button>
      </div>

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
        title="新增廣播"
        open={modalOpen}
        onOk={handleCreate}
        onCancel={() => {
          setModalOpen(false)
          form.resetFields()
        }}
        okText="建立"
        cancelText="取消"
        width={520}
      >
        <Form form={form} layout="vertical" initialValues={{ delivery_mode: 'both', target_gender: 'all' }}>
          <Form.Item name="title" label="標題" rules={[{ required: true, message: '請輸入標題' }]}>
            <Input placeholder="廣播標題" />
          </Form.Item>
          <Form.Item name="content" label="內容" rules={[{ required: true, message: '請輸入內容' }]}>
            <TextArea rows={4} placeholder="廣播內容..." />
          </Form.Item>
          <Form.Item name="delivery_mode" label="發送方式">
            <Radio.Group>
              <Radio value="push">推播通知</Radio>
              <Radio value="in_app">站內通知</Radio>
              <Radio value="both">推播 + 站內</Radio>
            </Radio.Group>
          </Form.Item>
          <Form.Item name="target_gender" label="目標性別">
            <Select>
              <Select.Option value="all">全部</Select.Option>
              <Select.Option value="male">僅男性</Select.Option>
              <Select.Option value="female">僅女性</Select.Option>
            </Select>
          </Form.Item>
        </Form>
      </Modal>
=======
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Title level={4} style={{ margin: 0 }}>廣播管理</Title>
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>新增廣播</Button>
      </div>

      <Table dataSource={broadcasts} columns={columns} rowKey="id" loading={loading}
        pagination={{ current: page, pageSize: 20, total, onChange: setPage, showTotal: (t) => `共 ${t} 筆` }}
        size="middle" locale={{ emptyText: '目前無廣播記錄' }} />

      <Drawer title="新增廣播" placement="right" width={520}
        open={drawerOpen} onClose={() => setDrawerOpen(false)}
        extra={
          <Button type="primary" loading={submitting} onClick={handleCreate}>建立草稿</Button>
        }>
        <Form layout="vertical">
          <Form.Item label="標題" required>
            <Input value={form.title} onChange={(e) => updateForm({ title: e.target.value })}
              placeholder="輸入廣播標題" maxLength={100} />
          </Form.Item>

          <Form.Item label="內容" required>
            <TextArea rows={4} value={form.content} onChange={(e) => updateForm({ content: e.target.value })}
              placeholder="輸入廣播內容" maxLength={1000} showCount />
          </Form.Item>

          <Form.Item label="發送模式">
            <Radio.Group value={form.mode} onChange={(e) => updateForm({ mode: e.target.value })}>
              <Radio.Button value="notification">系統通知</Radio.Button>
              <Radio.Button value="dm">私訊</Radio.Button>
              <Radio.Button value="both">兩者</Radio.Button>
            </Radio.Group>
          </Form.Item>

          <Collapse ghost items={[{
            key: 'filters',
            label: '篩選條件',
            children: (
              <div>
                <Form.Item label="性別" style={{ marginBottom: 12 }}>
                  <Select value={form.gender} onChange={(v) => updateForm({ gender: v })} style={{ width: 160 }}>
                    <Select.Option value="all">全部</Select.Option>
                    <Select.Option value="male">男</Select.Option>
                    <Select.Option value="female">女</Select.Option>
                  </Select>
                </Form.Item>

                <Form.Item label="會員等級" style={{ marginBottom: 12 }}>
                  <Space>
                    <InputNumber min={0} max={3} value={form.level_min}
                      onChange={(v) => updateForm({ level_min: v ?? 0 })} style={{ width: 80 }} />
                    <Text type="secondary">~</Text>
                    <InputNumber min={0} max={3} value={form.level_max}
                      onChange={(v) => updateForm({ level_max: v ?? 3 })} style={{ width: 80 }} />
                  </Space>
                </Form.Item>

                <Form.Item label="誠信分數區間" style={{ marginBottom: 12 }}>
                  <Space>
                    <InputNumber min={0} max={100} value={form.credit_min}
                      onChange={(v) => updateForm({ credit_min: v ?? 0 })} style={{ width: 80 }} />
                    <Text type="secondary">~</Text>
                    <InputNumber min={0} max={100} value={form.credit_max}
                      onChange={(v) => updateForm({ credit_max: v ?? 100 })} style={{ width: 80 }} />
                  </Space>
                </Form.Item>
              </div>
            ),
          }]} />

          {estimatedCount !== null && (
            <div style={{ background: '#f6ffed', border: '1px solid #b7eb8f', borderRadius: 6, padding: '8px 12px', marginTop: 12 }}>
              <Text>預估目標人數：</Text>
              <Text strong style={{ fontSize: 18 }}>{estimatedCount}</Text>
              <Text type="secondary"> 位用戶</Text>
            </div>
          )}
        </Form>
      </Drawer>
>>>>>>> develop
    </div>
  )
}
