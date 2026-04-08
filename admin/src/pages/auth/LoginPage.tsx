import { useState } from 'react'
import { useNavigate, Navigate } from 'react-router-dom'
import { Card, Form, Input, Button, Alert, Typography } from 'antd'
import { MailOutlined, LockOutlined } from '@ant-design/icons'
import { useAuthStore } from '../../stores/authStore'

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

    // Simulate network delay
    await new Promise((r) => setTimeout(r, 500))

    if (import.meta.env.DEV) {
      // Mock login
      if (values.email === 'admin@mimeet.tw' && values.password === 'password') {
        login({ id: 1, name: '管理員', email: 'admin@mimeet.tw', role: 'super_admin' })
        navigate('/dashboard', { replace: true })
        setLoading(false)
        return
      }
      if (values.email === 'cs@mimeet.tw' && values.password === 'password') {
        login({ id: 2, name: '客服人員', email: 'cs@mimeet.tw', role: 'cs' })
        navigate('/tickets', { replace: true })
        setLoading(false)
        return
      }
      if (values.email === 'mod@mimeet.tw' && values.password === 'password') {
        login({ id: 3, name: '管理員B', email: 'mod@mimeet.tw', role: 'admin' })
        navigate('/dashboard', { replace: true })
        setLoading(false)
        return
      }
    }

    setAttempts((a) => a + 1)
    setError('Email 或密碼不正確')
    setLoading(false)
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

        {import.meta.env.DEV && (
          <div style={{ marginTop: 16, padding: 12, background: '#FFFBEB', borderRadius: 8, fontSize: 12 }}>
            <Text strong style={{ color: '#92400E' }}>DEV Mock 帳號：</Text>
            <br />
            <Text style={{ color: '#92400E' }}>super_admin: admin@mimeet.tw / password</Text>
            <br />
            <Text style={{ color: '#92400E' }}>admin: mod@mimeet.tw / password</Text>
            <br />
            <Text style={{ color: '#92400E' }}>cs: cs@mimeet.tw / password</Text>
          </div>
        )}
      </Card>
    </div>
  )
}
