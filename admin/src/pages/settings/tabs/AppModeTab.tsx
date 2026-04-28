import { useState, useEffect } from 'react'
import { Card, Button, Tag, Modal, Input, Switch, Alert, Space, Typography, message } from 'antd'
import apiClient from '../../../api/client'

const { Title, Text } = Typography

interface AppModeData {
  mode: string
  maintenance_mode: boolean
  version: string
  ecpay_environment?: 'sandbox' | 'production'
  ecpay_sandbox?: boolean
}

export default function AppModeTab() {
  const [data, setData] = useState<AppModeData | null>(null)
  const [loading, setLoading] = useState(false)
  const [switchModalOpen, setSwitchModalOpen] = useState(false)
  const [confirmPassword, setConfirmPassword] = useState('')

  useEffect(() => {
    fetchData()
  }, [])

  async function fetchData() {
    try {
      const res = await apiClient.get('/admin/settings/system-control')
      setData(res.data.data.app_mode)
    } catch {
      // use defaults
      setData({ mode: 'testing', maintenance_mode: false, version: '1.0.0' })
    }
  }

  async function handleSwitchMode() {
    if (!confirmPassword) return
    setLoading(true)
    try {
      const newMode = data?.mode === 'testing' ? 'production' : 'testing'
      await apiClient.patch('/admin/settings/app-mode', {
        mode: newMode,
        confirm_password: confirmPassword,
      })
      message.success(`已切換為${newMode === 'production' ? '正式' : '測試'}模式`)
      setData(prev => prev ? { ...prev, mode: newMode } : prev)
      setSwitchModalOpen(false)
      setConfirmPassword('')
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message || '切換失敗'
      message.error(msg)
    }
    setLoading(false)
  }

  if (!data) return null

  const isTesting = data.mode === 'testing'

  return (
    <div>
      <Card style={{ marginBottom: 16 }}>
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <Title level={4} style={{ margin: 0 }}>目前模式</Title>
            <Tag color={isTesting ? 'orange' : 'green'} style={{ fontSize: 16, padding: '4px 16px' }}>
              {isTesting ? '🟡 測試模式' : '🟢 正式模式'}
            </Tag>
          </div>

          <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }}>
            <Tag color={isTesting ? 'red' : 'green'}>Email {isTesting ? '● 停用' : '● 啟用'}</Tag>
            <Tag color={isTesting ? 'red' : 'green'}>SMS {isTesting ? '● 停用' : '● 啟用'}</Tag>
            {/* 綠界標籤讀真實 ecpay_environment，與 app_mode 獨立 */}
            <Tag color={(data.ecpay_environment ?? 'sandbox') === 'sandbox' ? 'orange' : 'green'}>
              綠界 {(data.ecpay_environment ?? 'sandbox') === 'sandbox' ? '● Sandbox' : '● 正式'}
            </Tag>
          </div>

          {isTesting && (
            <Alert type="warning" message="測試模式下，Email 與 SMS 僅寫入 Log，不會實際發送。" showIcon />
          )}
          <Alert
            type="info"
            message="金流環境（綠界 Sandbox / 正式）與系統模式獨立管理，請至「金流與發票」分頁切換。"
            showIcon
            style={{ marginTop: 4 }}
          />

          <Button type="primary" danger={!isTesting} onClick={() => setSwitchModalOpen(true)}>
            切換為{isTesting ? '正式' : '測試'}模式
          </Button>
        </Space>
      </Card>

      <Card title="其他設定">
        <Space direction="vertical" size={12}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <Text>維護模式</Text>
            <Switch checked={data.maintenance_mode} onChange={() => message.info('維護模式切換功能待實作')} />
          </div>
          <Text type="secondary">版本號：{data.version}</Text>
        </Space>
      </Card>

      <Modal
        title="確認切換系統模式"
        open={switchModalOpen}
        onOk={handleSwitchMode}
        onCancel={() => { setSwitchModalOpen(false); setConfirmPassword('') }}
        confirmLoading={loading}
        okText="確認切換"
        okButtonProps={{ danger: !isTesting }}
      >
        <Alert
          type={isTesting ? 'info' : 'warning'}
          message={isTesting
            ? '即將切換為正式模式，Email 與 SMS 將開始實際發送'
            : '即將切換為測試模式，Email 與 SMS 將停止實際發送'}
          style={{ marginBottom: 16 }}
          showIcon
        />
        <Text>請輸入管理員密碼確認：</Text>
        <Input.Password
          value={confirmPassword}
          onChange={e => setConfirmPassword(e.target.value)}
          style={{ marginTop: 8 }}
          placeholder="輸入您的登入密碼"
        />
      </Modal>
    </div>
  )
}
