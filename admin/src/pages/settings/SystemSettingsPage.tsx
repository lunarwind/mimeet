import { useState, useEffect, useCallback } from 'react'
import {
  Card,
  InputNumber,
  Button,
  Typography,
  Divider,
  message,
  Switch,
  Tabs,
  Radio,
  Alert,
  Modal,
  Input,
  Form,
  Tag,
  Space,
  Spin,
  Descriptions,
} from 'antd'
import {
  SaveOutlined,
  SettingOutlined,
  ToolOutlined,
  DatabaseOutlined,
  MailOutlined,
  MessageOutlined,
  ClearOutlined,
  SendOutlined,
  ExclamationCircleOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  LockOutlined,
} from '@ant-design/icons'
import apiClient from '../../api/client'

const { Title, Text } = Typography

// ─── Tab 1: System Parameters (existing content) ─────────────────────────────

function SystemParamsTab() {
  const [qrGpsScore, setQrGpsScore] = useState(5)
  const [qrNoGpsScore, setQrNoGpsScore] = useState(2)
  const [reportDeduct, setReportDeduct] = useState(-15)
  const [noShowDeduct, setNoShowDeduct] = useState(-10)
  const [suspendThreshold, setSuspendThreshold] = useState(0)

  const [trialPrice, setTrialPrice] = useState(199)
  const [trialDays, setTrialDays] = useState(30)
  const [autoRenewDefault, setAutoRenewDefault] = useState(true)

  const handleSaveCreditRules = () => {
    message.success('誠信分數規則已儲存')
  }

  const handleSaveSubscription = () => {
    message.success('訂閱設定已儲存')
  }

  return (
    <div>
      <Card title="誠信分數規則" style={{ marginBottom: 24 }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, maxWidth: 600 }}>
          <div>
            <Text>QR 約會 GPS 通過得分</Text>
            <InputNumber
              value={qrGpsScore}
              onChange={(v) => setQrGpsScore(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              addonBefore="+"
            />
          </div>
          <div>
            <Text>QR 約會無 GPS 得分</Text>
            <InputNumber
              value={qrNoGpsScore}
              onChange={(v) => setQrNoGpsScore(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              addonBefore="+"
            />
          </div>
          <div>
            <Text>被檢舉扣分</Text>
            <InputNumber
              value={reportDeduct}
              onChange={(v) => setReportDeduct(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              max={0}
            />
          </div>
          <div>
            <Text>爽約扣分</Text>
            <InputNumber
              value={noShowDeduct}
              onChange={(v) => setNoShowDeduct(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              max={0}
            />
          </div>
          <div>
            <Text>停權門檻（分數 &le; 此值停權）</Text>
            <InputNumber
              value={suspendThreshold}
              onChange={(v) => setSuspendThreshold(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              min={0}
              max={30}
            />
          </div>
        </div>
        <Divider />
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSaveCreditRules}>
          儲存誠信規則
        </Button>
      </Card>

      <Card title="訂閱設定">
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, maxWidth: 600 }}>
          <div>
            <Text>新手體驗價</Text>
            <InputNumber
              value={trialPrice}
              onChange={(v) => setTrialPrice(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              addonBefore="NT$"
              min={0}
            />
          </div>
          <div>
            <Text>體驗方案天數</Text>
            <InputNumber
              value={trialDays}
              onChange={(v) => setTrialDays(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              addonAfter="天"
              min={1}
            />
          </div>
          <div>
            <Text>自動續訂預設開關</Text>
            <div style={{ marginTop: 4 }}>
              <Switch
                checked={autoRenewDefault}
                onChange={setAutoRenewDefault}
                checkedChildren="開"
                unCheckedChildren="關"
              />
            </div>
          </div>
        </div>
        <Divider />
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSaveSubscription}>
          儲存訂閱設定
        </Button>
      </Card>
    </div>
  )
}

// ─── Tab 2: App Mode ──────────────────────────────────────────────────────────

function AppModeTab() {
  const [mode, setMode] = useState<'normal' | 'maintenance'>('normal')
  const [loading, setLoading] = useState(false)
  const [fetching, setFetching] = useState(true)
  const [passwordModalOpen, setPasswordModalOpen] = useState(false)
  const [pendingMode, setPendingMode] = useState<'normal' | 'maintenance'>('normal')
  const [password, setPassword] = useState('')
  const [confirmLoading, setConfirmLoading] = useState(false)

  const fetchStatus = useCallback(async () => {
    try {
      const res = await apiClient.get('/admin/system/status')
      if (res.data?.data?.app_mode) {
        setMode(res.data.data.app_mode)
      }
    } catch {
      message.error('無法取得系統狀態')
    } finally {
      setFetching(false)
    }
  }, [])

  useEffect(() => {
    fetchStatus()
  }, [fetchStatus])

  const handleSave = () => {
    setPendingMode(mode)
    setPassword('')
    setPasswordModalOpen(true)
  }

  const handlePasswordConfirm = async () => {
    if (!password) {
      message.warning('請輸入密碼')
      return
    }
    setConfirmLoading(true)
    try {
      // Confirm password first
      await apiClient.post('/admin/auth/confirm-password', { password })

      // Then set mode
      setLoading(true)
      const res = await apiClient.post('/admin/system/mode', { mode: pendingMode })
      message.success(res.data?.message || '系統模式已更新')
      setPasswordModalOpen(false)
    } catch (err: any) {
      message.error(err.response?.data?.message || '操作失敗')
    } finally {
      setConfirmLoading(false)
      setLoading(false)
    }
  }

  if (fetching) {
    return (
      <div style={{ textAlign: 'center', padding: 48 }}>
        <Spin />
      </div>
    )
  }

  return (
    <div>
      <Card title="系統運行模式" style={{ maxWidth: 600 }}>
        <Radio.Group value={mode} onChange={(e) => setMode(e.target.value)}>
          <Radio.Button value="normal">正常模式</Radio.Button>
          <Radio.Button value="maintenance">維護模式</Radio.Button>
        </Radio.Group>

        {mode === 'maintenance' && (
          <Alert
            style={{ marginTop: 16 }}
            type="warning"
            showIcon
            icon={<ExclamationCircleOutlined />}
            message="維護模式警告"
            description="切換至維護模式後，一般使用者將無法存取系統。僅管理員可正常使用。請確認已通知使用者後再進行切換。"
          />
        )}

        <Divider />
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={loading}>
          儲存模式設定
        </Button>
      </Card>

      <Modal
        title={
          <span>
            <LockOutlined style={{ marginRight: 8 }} />
            密碼確認
          </span>
        }
        open={passwordModalOpen}
        onOk={handlePasswordConfirm}
        onCancel={() => setPasswordModalOpen(false)}
        confirmLoading={confirmLoading}
        okText="確認"
        cancelText="取消"
      >
        <p>此操作需要確認管理員密碼才能執行。</p>
        <Input.Password
          placeholder="請輸入管理員密碼"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          onPressEnter={handlePasswordConfirm}
          prefix={<LockOutlined />}
        />
      </Modal>
    </div>
  )
}

// ─── Tab 3: Database ──────────────────────────────────────────────────────────

function DatabaseTab() {
  const [status, setStatus] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [clearingCache, setClearingCache] = useState(false)

  const fetchStatus = useCallback(async () => {
    try {
      const res = await apiClient.get('/admin/system/status')
      setStatus(res.data?.data)
    } catch {
      message.error('無法取得系統狀態')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchStatus()
  }, [fetchStatus])

  const handleClearCache = async () => {
    setClearingCache(true)
    try {
      const res = await apiClient.post('/admin/system/cache-clear')
      message.success(res.data?.message || '快取已清除')
    } catch (err: any) {
      message.error(err.response?.data?.message || '清除快取失敗')
    } finally {
      setClearingCache(false)
    }
  }

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: 48 }}>
        <Spin />
      </div>
    )
  }

  return (
    <div>
      <Card title="資料庫連線資訊" style={{ marginBottom: 24 }}>
        <Descriptions column={1} bordered size="small">
          <Descriptions.Item label="DB Host">{status?.db_host || '-'}</Descriptions.Item>
          <Descriptions.Item label="DB Name">{status?.db_name || '-'}</Descriptions.Item>
          <Descriptions.Item label="連線狀態">
            {status?.db_status === 'connected' ? (
              <Tag icon={<CheckCircleOutlined />} color="success">
                連線正常
              </Tag>
            ) : (
              <Tag icon={<CloseCircleOutlined />} color="error">
                連線異常
              </Tag>
            )}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      <Card title="快取資訊" style={{ marginBottom: 24 }}>
        <Descriptions column={1} bordered size="small">
          <Descriptions.Item label="快取驅動">{status?.cache_driver || '-'}</Descriptions.Item>
          <Descriptions.Item label="PHP 版本">{status?.php_version || '-'}</Descriptions.Item>
          <Descriptions.Item label="Laravel 版本">{status?.laravel_version || '-'}</Descriptions.Item>
        </Descriptions>
        <Divider />
        <Button
          type="primary"
          danger
          icon={<ClearOutlined />}
          onClick={handleClearCache}
          loading={clearingCache}
        >
          清除快取
        </Button>
      </Card>
    </div>
  )
}

// ─── Tab 4: Mail ──────────────────────────────────────────────────────────────

function MailTab() {
  const [form] = Form.useForm()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testEmail, setTestEmail] = useState('')
  const [testSending, setTestSending] = useState(false)
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null)

  const fetchSettings = useCallback(async () => {
    try {
      const res = await apiClient.get('/admin/settings/mail')
      if (res.data?.data) {
        form.setFieldsValue(res.data.data)
      }
    } catch {
      message.error('無法取得 Email 設定')
    } finally {
      setLoading(false)
    }
  }, [form])

  useEffect(() => {
    fetchSettings()
  }, [fetchSettings])

  const handleSave = async () => {
    try {
      const values = await form.validateFields()
      setSaving(true)
      const res = await apiClient.patch('/admin/settings/mail', values)
      message.success(res.data?.message || 'Email 設定已更新')
    } catch (err: any) {
      if (err.errorFields) return // Validation error, Ant Design handles display
      message.error(err.response?.data?.message || '儲存失敗')
    } finally {
      setSaving(false)
    }
  }

  const handleSendTest = async () => {
    if (!testEmail) {
      message.warning('請輸入收件人信箱')
      return
    }
    setTestSending(true)
    setTestResult(null)
    try {
      const res = await apiClient.post('/admin/settings/mail/test', { recipient: testEmail })
      setTestResult({ success: true, message: res.data?.message || '測試信已發送' })
    } catch (err: any) {
      setTestResult({
        success: false,
        message: err.response?.data?.message || '發送失敗',
      })
    } finally {
      setTestSending(false)
    }
  }

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: 48 }}>
        <Spin />
      </div>
    )
  }

  return (
    <div>
      <Card title="SMTP 設定" style={{ marginBottom: 24 }}>
        <Form form={form} layout="vertical" style={{ maxWidth: 600 }}>
          <Form.Item
            name="host"
            label="SMTP Host"
            rules={[{ required: true, message: '請輸入 SMTP Host' }]}
          >
            <Input placeholder="smtp.example.com" />
          </Form.Item>
          <Form.Item
            name="port"
            label="Port"
            rules={[{ required: true, message: '請輸入 Port' }]}
          >
            <InputNumber style={{ width: '100%' }} min={1} max={65535} placeholder="587" />
          </Form.Item>
          <Form.Item
            name="username"
            label="Username"
            rules={[{ required: true, message: '請輸入 Username' }]}
          >
            <Input placeholder="user@example.com" />
          </Form.Item>
          <Form.Item name="password" label="Password">
            <Input.Password placeholder="SMTP 密碼" />
          </Form.Item>
          <Form.Item
            name="from_address"
            label="From Address"
            rules={[
              { required: true, message: '請輸入寄件人信箱' },
              { type: 'email', message: '請輸入有效的 Email 地址' },
            ]}
          >
            <Input placeholder="noreply@example.com" />
          </Form.Item>
          <Form.Item
            name="from_name"
            label="From Name"
            rules={[{ required: true, message: '請輸入寄件人名稱' }]}
          >
            <Input placeholder="MiMeet" />
          </Form.Item>
        </Form>
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={saving}>
          儲存 Email 設定
        </Button>
      </Card>

      <Card title="發送測試信">
        <Space.Compact style={{ maxWidth: 400, width: '100%' }}>
          <Input
            placeholder="收件人 Email"
            value={testEmail}
            onChange={(e) => setTestEmail(e.target.value)}
            onPressEnter={handleSendTest}
          />
          <Button
            type="primary"
            icon={<SendOutlined />}
            onClick={handleSendTest}
            loading={testSending}
          >
            發送測試信
          </Button>
        </Space.Compact>
        {testResult && (
          <Alert
            style={{ marginTop: 16, maxWidth: 400 }}
            type={testResult.success ? 'success' : 'error'}
            showIcon
            message={testResult.message}
          />
        )}
      </Card>
    </div>
  )
}

// ─── Tab 5: SMS ───────────────────────────────────────────────────────────────

function SmsTab() {
  const [provider, setProvider] = useState<'mitake' | 'twilio' | 'disabled'>('disabled')
  const [mitake, setMitake] = useState({ username: '', password: '' })
  const [twilio, setTwilio] = useState({ sid: '', auth_token: '', from_number: '' })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testPhone, setTestPhone] = useState('')
  const [testSending, setTestSending] = useState(false)
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null)

  const fetchSettings = useCallback(async () => {
    try {
      const res = await apiClient.get('/admin/settings/sms')
      if (res.data?.data) {
        const data = res.data.data
        setProvider(data.provider || 'disabled')
        if (data.mitake) setMitake(data.mitake)
        if (data.twilio) setTwilio(data.twilio)
      }
    } catch {
      message.error('無法取得 SMS 設定')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchSettings()
  }, [fetchSettings])

  const handleSave = async () => {
    setSaving(true)
    try {
      const res = await apiClient.patch('/admin/settings/sms', { provider, mitake, twilio })
      message.success(res.data?.message || 'SMS 設定已更新')
    } catch (err: any) {
      message.error(err.response?.data?.message || '儲存失敗')
    } finally {
      setSaving(false)
    }
  }

  const handleSendTest = async () => {
    if (!testPhone) {
      message.warning('請輸入測試電話號碼')
      return
    }
    setTestSending(true)
    setTestResult(null)
    try {
      const res = await apiClient.post('/admin/settings/sms/test', { phone: testPhone })
      setTestResult({ success: true, message: res.data?.message || '測試簡訊已發送' })
    } catch (err: any) {
      setTestResult({
        success: false,
        message: err.response?.data?.message || '發送失敗',
      })
    } finally {
      setTestSending(false)
    }
  }

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: 48 }}>
        <Spin />
      </div>
    )
  }

  return (
    <div>
      <Card title="SMS 服務設定" style={{ marginBottom: 24 }}>
        <div style={{ marginBottom: 16 }}>
          <Text strong>簡訊服務提供商</Text>
          <div style={{ marginTop: 8 }}>
            <Radio.Group value={provider} onChange={(e) => setProvider(e.target.value)}>
              <Radio.Button value="mitake">三竹 Mitake</Radio.Button>
              <Radio.Button value="twilio">Twilio</Radio.Button>
              <Radio.Button value="disabled">停用</Radio.Button>
            </Radio.Group>
          </div>
        </div>

        {provider === 'mitake' && (
          <Card type="inner" title="三竹 Mitake 設定" style={{ marginBottom: 16 }}>
            <div style={{ maxWidth: 400 }}>
              <div style={{ marginBottom: 12 }}>
                <Text>Username</Text>
                <Input
                  style={{ marginTop: 4 }}
                  value={mitake.username}
                  onChange={(e) => setMitake({ ...mitake, username: e.target.value })}
                  placeholder="Mitake 帳號"
                />
              </div>
              <div>
                <Text>Password</Text>
                <Input.Password
                  style={{ marginTop: 4 }}
                  value={mitake.password}
                  onChange={(e) => setMitake({ ...mitake, password: e.target.value })}
                  placeholder="Mitake 密碼"
                />
              </div>
            </div>
          </Card>
        )}

        {provider === 'twilio' && (
          <Card type="inner" title="Twilio 設定" style={{ marginBottom: 16 }}>
            <div style={{ maxWidth: 400 }}>
              <div style={{ marginBottom: 12 }}>
                <Text>Account SID</Text>
                <Input
                  style={{ marginTop: 4 }}
                  value={twilio.sid}
                  onChange={(e) => setTwilio({ ...twilio, sid: e.target.value })}
                  placeholder="Twilio Account SID"
                />
              </div>
              <div style={{ marginBottom: 12 }}>
                <Text>Auth Token</Text>
                <Input.Password
                  style={{ marginTop: 4 }}
                  value={twilio.auth_token}
                  onChange={(e) => setTwilio({ ...twilio, auth_token: e.target.value })}
                  placeholder="Twilio Auth Token"
                />
              </div>
              <div>
                <Text>From Number</Text>
                <Input
                  style={{ marginTop: 4 }}
                  value={twilio.from_number}
                  onChange={(e) => setTwilio({ ...twilio, from_number: e.target.value })}
                  placeholder="+1234567890"
                />
              </div>
            </div>
          </Card>
        )}

        <Divider />
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={saving}>
          儲存 SMS 設定
        </Button>
      </Card>

      <Card title="發送測試簡訊">
        <Space.Compact style={{ maxWidth: 400, width: '100%' }}>
          <Input
            placeholder="測試電話號碼"
            value={testPhone}
            onChange={(e) => setTestPhone(e.target.value)}
            onPressEnter={handleSendTest}
          />
          <Button
            type="primary"
            icon={<SendOutlined />}
            onClick={handleSendTest}
            loading={testSending}
          >
            發送測試簡訊
          </Button>
        </Space.Compact>
        {testResult && (
          <Alert
            style={{ marginTop: 16, maxWidth: 400 }}
            type={testResult.success ? 'success' : 'error'}
            showIcon
            message={testResult.message}
          />
        )}
      </Card>
    </div>
  )
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function SystemSettingsPage() {
  const tabItems = [
    {
      key: 'params',
      label: (
        <span>
          <SettingOutlined /> 系統參數
        </span>
      ),
      children: <SystemParamsTab />,
    },
    {
      key: 'mode',
      label: (
        <span>
          <ToolOutlined /> 系統模式
        </span>
      ),
      children: <AppModeTab />,
    },
    {
      key: 'database',
      label: (
        <span>
          <DatabaseOutlined /> 資料庫
        </span>
      ),
      children: <DatabaseTab />,
    },
    {
      key: 'mail',
      label: (
        <span>
          <MailOutlined /> Email
        </span>
      ),
      children: <MailTab />,
    },
    {
      key: 'sms',
      label: (
        <span>
          <MessageOutlined /> SMS
        </span>
      ),
      children: <SmsTab />,
    },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 24 }}>
        系統控制中心
      </Title>
      <Tabs defaultActiveKey="params" items={tabItems} />
    </div>
  )
}
