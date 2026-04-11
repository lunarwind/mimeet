import { useState, useEffect } from 'react'
import {
  Table, Button, Modal, Form, Input, Select, DatePicker, Tag, Space, Typography, message, Popconfirm,
} from 'antd'
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title } = Typography
const { RangePicker } = DatePicker

interface Announcement {
  id: number
  title: string
  content: string
  type: 'info' | 'warning' | 'success'
  is_active: boolean
  start_at: string
  end_at: string
  created_at: string
}

const TYPE_OPTIONS = [
  { value: 'info', label: '一般通知' },
  { value: 'warning', label: '警告' },
  { value: 'success', label: '成功/活動' },
]

const TYPE_COLORS: Record<string, string> = {
  info: 'blue',
  warning: 'orange',
  success: 'green',
}

export default function AnnouncementsPage() {
  const [announcements, setAnnouncements] = useState<Announcement[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Announcement | null>(null)
  const [form] = Form.useForm()

  useEffect(() => {
    fetchAnnouncements()
  }, [])

  async function fetchAnnouncements() {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/announcements')
      setAnnouncements(res.data?.data?.announcements || [])
    } catch {
      // Use empty array on error
    } finally {
      setLoading(false)
    }
  }

  function openAdd() {
    setEditing(null)
    form.resetFields()
    form.setFieldsValue({ type: 'info' })
    setModalOpen(true)
  }

  function openEdit(record: Announcement) {
    setEditing(record)
    form.setFieldsValue({
      title: record.title,
      content: record.content,
      type: record.type,
      dateRange: [dayjs(record.start_at), dayjs(record.end_at)],
    })
    setModalOpen(true)
  }

  async function handleSave() {
    try {
      const values = await form.validateFields()
      const payload = {
        title: values.title,
        content: values.content,
        type: values.type,
        start_at: values.dateRange[0].toISOString(),
        end_at: values.dateRange[1].toISOString(),
      }
      if (editing) {
        await apiClient.patch(`/admin/announcements/${editing.id}`, payload)
        message.success('公告已更新')
      } else {
        await apiClient.post('/admin/announcements', payload)
        message.success('公告已建立')
      }
      setModalOpen(false)
      fetchAnnouncements()
    } catch {
      // validation or API error
    }
  }

  async function handleDelete(id: number) {
    try {
      await apiClient.delete(`/admin/announcements/${id}`)
      message.success('公告已刪除')
      fetchAnnouncements()
    } catch {
      message.error('刪除失敗')
    }
  }

  const columns = [
    { title: '標題', dataIndex: 'title', key: 'title', ellipsis: true },
    {
      title: '類型', dataIndex: 'type', key: 'type',
      render: (v: string) => <Tag color={TYPE_COLORS[v] || 'default'}>{TYPE_OPTIONS.find(o => o.value === v)?.label || v}</Tag>,
    },
    {
      title: '狀態', dataIndex: 'is_active', key: 'is_active',
      render: (v: boolean) => v ? <Tag color="green">啟用</Tag> : <Tag color="red">停用</Tag>,
    },
    {
      title: '開始時間', dataIndex: 'start_at', key: 'start_at',
      render: (v: string) => dayjs(v).format('YYYY-MM-DD HH:mm'),
    },
    {
      title: '結束時間', dataIndex: 'end_at', key: 'end_at',
      render: (v: string) => dayjs(v).format('YYYY-MM-DD HH:mm'),
    },
    {
      title: '建立時間', dataIndex: 'created_at', key: 'created_at',
      render: (v: string) => dayjs(v).format('YYYY-MM-DD'),
    },
    {
      title: '操作', key: 'action',
      render: (_: unknown, record: Announcement) => (
        <Space>
          <Button icon={<EditOutlined />} size="small" onClick={() => openEdit(record)}>編輯</Button>
          <Popconfirm title="確定要刪除此公告？" onConfirm={() => handleDelete(record.id)} okText="確定" cancelText="取消">
            <Button icon={<DeleteOutlined />} size="small" danger>刪除</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Title level={3} style={{ margin: 0 }}>系統公告</Title>
        <Button type="primary" icon={<PlusOutlined />} onClick={openAdd}>新增公告</Button>
      </div>
      <Table dataSource={announcements} columns={columns} rowKey="id" loading={loading} pagination={{ pageSize: 10 }} />

      <Modal
        title={editing ? '編輯公告' : '新增公告'}
        open={modalOpen}
        onOk={handleSave}
        onCancel={() => setModalOpen(false)}
        okText="儲存"
        cancelText="取消"
        width={600}
      >
        <Form form={form} layout="vertical">
          <Form.Item name="title" label="標題" rules={[{ required: true, message: '請輸入標題' }, { max: 100, message: '最多 100 字' }]}>
            <Input />
          </Form.Item>
          <Form.Item name="content" label="內容" rules={[{ required: true, message: '請輸入內容' }, { max: 500, message: '最多 500 字' }]}>
            <Input.TextArea rows={4} showCount maxLength={500} />
          </Form.Item>
          <Form.Item name="type" label="類型" rules={[{ required: true, message: '請選擇類型' }]}>
            <Select options={TYPE_OPTIONS} />
          </Form.Item>
          <Form.Item name="dateRange" label="顯示期間" rules={[{ required: true, message: '請選擇顯示期間' }]}>
            <RangePicker showTime style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
