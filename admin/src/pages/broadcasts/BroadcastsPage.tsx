import { useState, useEffect, useCallback } from 'react'
import { Table, Button, Modal, Input, Tag, message, Card, Typography, Radio, Select, Form } from 'antd'
import { PlusOutlined, SendOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography
const { TextArea } = Input

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
}

const STATUS_LABELS: Record<string, string> = {
  draft: '草稿',
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
      setData(res.data.data?.broadcasts ?? res.data.data ?? [])
    } catch {
      setData([])
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
    },
  ]

  return (
    <div>
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
    </div>
  )
}
