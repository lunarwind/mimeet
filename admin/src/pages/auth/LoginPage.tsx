import { useState } from 'react'
import { useNavigate, Navigate } from 'react-router-dom'
import { Card, Form, Input, Button, Alert, Typography } from 'antd'
import { MailOutlined, LockOutlined } from '@ant-design/icons'
import { useAuthStore } from '../../stores/authStore'
import apiClient from '../../api/client'

const { Title, Text } = Typography

export default function LoginPage() {
  const navigate = useNavigate()
  const login = useAuthStore((s) => s.login)
  const isLoggedIn = useAuthStore((s) => s.isLoggedIn)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [attempts, setAttempts] = useState(0)

  // Already logged in
  if (isLoggedIn) {
    return <Navigate to="/dashboard" replace />
  }

  const handleLogin = async (values: { email: string; password: string }) => {
    if (attempts >= 5) {
      setError('請稍後再試，您已嘗試過多次')
      return
    }

    setLoading(true)
    setError('')

    try {
      const res = await apiClient.post('/admin/auth/login', {
        email: values.email,
        password: values.password,
      })
      const admin = res.data?.data?.admin
      const token = res.data?.data?.token || ''
      if (!admin || !token) {
        setError('登入失敗：伺服器回應格式異常')
        return
      }
      login({ id: admin.id, name: admin.name || admin.nickname, email: admin.email, role: admin.role }, token)
      navigate(admin.role === 'cs' ? '/tickets' : '/dashboard', { replace: true })
      return
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string }; status?: number } }
      const msg = axiosErr?.response?.data?.message
      if (axiosErr?.response?.status === 429) {
        setError('請稍後再試，您已嘗試過多次')
      } else {
        setAttempts((a) => a + 1)
        setError(msg || 'Email 或密碼不正確')
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%)',
      }}
    >
      <Card style={{ width: 400, boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}>
        <div style={{ textAlign: 'center', marginBottom: 32 }}>
          <Title level={3} style={{ marginBottom: 4 }}>
            <span style={{ color: '#F0294E' }}>Mi</span>Meet
          </Title>
          <Text type="secondary">後台管理系統</Text>
        </div>

        {error && <Alert message={error} type="error" showIcon style={{ marginBottom: 16 }} />}
        {attempts >= 5 && <Alert message="請稍後再試，您已嘗試過多次" type="warning" showIcon style={{ marginBottom: 16 }} />}

        <Form layout="vertical" onFinish={handleLogin} autoComplete="off">
          <Form.Item name="email" rules={[{ required: true, message: '請輸入 Email' }, { pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, message: '請輸入有效的 Email' }]}>
            <Input prefix={<MailOutlined />} placeholder="Email" size="large" />
          </Form.Item>
          <Form.Item name="password" rules={[{ required: true, message: '請輸入密碼' }]}>
            <Input.Password prefix={<LockOutlined />} placeholder="密碼" size="large" />
          </Form.Item>
          <Form.Item>
            <Button type="primary" htmlType="submit" loading={loading} block size="large" disabled={attempts >= 5}>
              登入
            </Button>
          </Form.Item>
        </Form>
      </Card>
    </div>
  )
}
