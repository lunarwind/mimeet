import { useState } from 'react'
import { useNavigate, Navigate } from 'react-router-dom'
import { Card, Form, Input, Button, Alert, Typography } from 'antd'
import { MailOutlined, LockOutlined } from '@ant-design/icons'
import { useAuthStore } from '../../stores/authStore'
import type { AdminUser } from '../../types/admin'
import apiClient from '../../api/client'

const { Title, Text } = Typography

// Mock credentials for fallback when backend is unavailable
const MOCK_ACCOUNTS: Record<string, { id: number; name: string; email: string; role: string; defaultRoute: string }> = {
  'admin@mimeet.tw': { id: 1, name: '管理員', email: 'admin@mimeet.tw', role: 'super_admin', defaultRoute: '/dashboard' },
  'cs@mimeet.tw': { id: 2, name: '客服人員', email: 'cs@mimeet.tw', role: 'cs', defaultRoute: '/tickets' },
  'mod@mimeet.tw': { id: 3, name: '管理員B', email: 'mod@mimeet.tw', role: 'admin', defaultRoute: '/dashboard' },
}

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

    // Try real API first
    try {
      const res = await apiClient.post('/admin/auth/login', {
        email: values.email,
        password: values.password,
      })
      if (res.data?.data?.admin) {
        const user = res.data.data.admin
        const token = res.data.data.token || ''
        login({ id: user.id, name: user.name || user.nickname, email: user.email, role: user.role }, token)
        navigate(user.role === 'cs' ? '/tickets' : '/dashboard', { replace: true })
        return
      }
    } catch {
      // API unavailable — fall back to mock credentials in DEV mode
      if (import.meta.env.DEV) {
        const mock = MOCK_ACCOUNTS[values.email]
        if (mock && values.password === 'password') {
          const { defaultRoute, ...userData } = mock
          login(userData as AdminUser)
          navigate(defaultRoute, { replace: true })
          return
        }
      }
    } finally {
      setLoading(false)
    }

    setAttempts((a) => a + 1)
    setError('Email 或密碼不正確')
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
