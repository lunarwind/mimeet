import { useState, useEffect, useCallback } from 'react'
import { Table, Button, Modal, Input, Tag, message, Card, Typography, Select, Popconfirm, Form } from 'antd'
import { PlusOutlined, DeleteOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import type { AdminRole } from '../../types/admin'
import dayjs from 'dayjs'

const { Title, Text } = Typography

interface AdminUser {
  id: number
  name: string
  email: string
  role: AdminRole
  last_login_at: string | null
}

const ROLE_COLORS: Record<AdminRole, string> = {
  super_admin: 'red',
  admin: 'blue',
  cs: 'green',
}

const ROLE_LABELS: Record<AdminRole, string> = {
  super_admin: '超級管理員',
  admin: '一般管理員',
  cs: '客服人員',
}

export default function AdminUsersPage() {
  const [data, setData] = useState<AdminUser[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [form] = Form.useForm()

  const fetchData = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/settings/admins')
      setData(res.data.data ?? res.data)
    } catch {
      message.error('載入管理員列表失敗')
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
        const res = await apiClient.post('/admin/settings/admins', values)
        const newAdmin = res.data.data ?? {
          id: Date.now(),
          ...values,
          last_login_at: null,
        }
        setData((prev) => [...prev, newAdmin])
        message.success('管理員已建立')
      } catch {
        message.error('建立管理員失敗')
      }
      setModalOpen(false)
      form.resetFields()
    } catch {
      // form validation failed
    }
  }

  const handleRoleChange = async (id: number, newRole: AdminRole) => {
    try {
      await apiClient.patch(`/admin/settings/admins/${id}/role`, { role: newRole })
      setData((prev) => prev.map((a) => (a.id === id ? { ...a, role: newRole } : a)))
      message.success('角色已更新')
    } catch {
      message.error('更新角色失敗')
    }
  }

  const handleDelete = async (id: number) => {
    try {
      await apiClient.delete(`/admin/settings/admins/${id}`)
      message.success('管理員已刪除')
      setData((prev) => prev.filter((a) => a.id !== id))
    } catch {
      message.error('刪除管理員失敗')
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
      title: '姓名',
      dataIndex: 'name',
      key: 'name',
      width: 160,
    },
    {
      title: 'Email',
      dataIndex: 'email',
      key: 'email',
      width: 220,
    },
    {
      title: '角色',
      dataIndex: 'role',
      key: 'role',
      width: 180,
      render: (role: AdminRole, r: AdminUser) => (
        <Select
          value={role}
          onChange={(v) => handleRoleChange(r.id, v)}
          style={{ width: 150 }}
          size="small"
        >
          <Select.Option value="super_admin">
            <Tag color={ROLE_COLORS.super_admin} style={{ margin: 0 }}>{ROLE_LABELS.super_admin}</Tag>
          </Select.Option>
          <Select.Option value="admin">
            <Tag color={ROLE_COLORS.admin} style={{ margin: 0 }}>{ROLE_LABELS.admin}</Tag>
          </Select.Option>
          <Select.Option value="cs">
            <Tag color={ROLE_COLORS.cs} style={{ margin: 0 }}>{ROLE_LABELS.cs}</Tag>
          </Select.Option>
        </Select>
      ),
    },
    {
      title: '最後登入',
      dataIndex: 'last_login_at',
      key: 'last_login_at',
      width: 180,
      render: (v: string | null) =>
        v ? dayjs(v).format('YYYY-MM-DD HH:mm') : <Text type="secondary">尚未登入</Text>,
    },
    {
      title: '操作',
      key: 'actions',
      width: 100,
      render: (_: unknown, r: AdminUser) => (
        <Popconfirm
          title="確認刪除此管理員？"
          description="此操作無法復原"
          onConfirm={() => handleDelete(r.id)}
          okText="確認刪除"
          cancelText="取消"
          okButtonProps={{ danger: true }}
        >
          <Button danger size="small" icon={<DeleteOutlined />}>
            刪除
          </Button>
        </Popconfirm>
      ),
    },
  ]

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <Title level={4} style={{ margin: 0 }}>管理員帳號</Title>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => setModalOpen(true)}>
          新增管理員
        </Button>
      </div>

      <Card>
        <Table
          dataSource={data}
          columns={columns}
          rowKey="id"
          loading={loading}
          pagination={{ pageSize: 20, showTotal: (t) => `共 ${t} 筆` }}
          size="middle"
        />
      </Card>

      <Modal
        title="新增管理員"
        open={modalOpen}
        onOk={handleCreate}
        onCancel={() => {
          setModalOpen(false)
          form.resetFields()
        }}
        okText="建立"
        cancelText="取消"
        width={480}
      >
        <Form form={form} layout="vertical" initialValues={{ role: 'admin' }}>
          <Form.Item name="name" label="姓名" rules={[{ required: true, message: '請輸入姓名' }]}>
            <Input placeholder="管理員姓名" />
          </Form.Item>
          <Form.Item
            name="email"
            label="Email"
            rules={[
              { required: true, message: '請輸入 Email' },
              { type: 'email', message: '請輸入有效的 Email' },
            ]}
          >
            <Input placeholder="chuck@lunarwind.org" />
          </Form.Item>
          <Form.Item
            name="password"
            label="密碼"
            rules={[
              { required: true, message: '請輸入密碼' },
              { min: 8, message: '密碼至少 8 個字元' },
            ]}
          >
            <Input.Password placeholder="至少 8 個字元" />
          </Form.Item>
          <Form.Item name="role" label="角色">
            <Select>
              <Select.Option value="super_admin">超級管理員</Select.Option>
              <Select.Option value="admin">一般管理員</Select.Option>
              <Select.Option value="cs">客服人員</Select.Option>
            </Select>
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
