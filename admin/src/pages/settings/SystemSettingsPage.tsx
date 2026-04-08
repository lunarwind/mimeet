import { useState, useEffect } from 'react'
import { Tabs, Card, InputNumber, Button, Typography, Divider, Space, message, Switch, Modal, Input, Tag, Alert, Statistic, Row, Col } from 'antd'
import { SaveOutlined, DeleteOutlined, DatabaseOutlined, ReloadOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import { useAuthStore } from '../../stores/authStore'
import AppModeTab from './tabs/AppModeTab'
import DatabaseTab from './tabs/DatabaseTab'
import MailTab from './tabs/MailTab'
import SmsTab from './tabs/SmsTab'

const { Title, Text } = Typography

function SystemParamsTab() {
  const [qrGpsScore, setQrGpsScore] = useState(5)
  const [qrNoGpsScore, setQrNoGpsScore] = useState(2)
  const [reportDeduct, setReportDeduct] = useState(-15)
  const [noShowDeduct, setNoShowDeduct] = useState(-10)
  const [suspendThreshold, setSuspendThreshold] = useState(0)

  return (
    <div>
      <Card title="誠信分數規則" style={{ marginBottom: 24 }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, maxWidth: 600 }}>
          <div><Text>QR 約會 GPS 通過得分</Text><InputNumber value={qrGpsScore} onChange={(v) => setQrGpsScore(v || 0)} style={{ width: '100%', marginTop: 4 }} /></div>
          <div><Text>QR 約會無 GPS 得分</Text><InputNumber value={qrNoGpsScore} onChange={(v) => setQrNoGpsScore(v || 0)} style={{ width: '100%', marginTop: 4 }} /></div>
          <div><Text>被檢舉扣分</Text><InputNumber value={reportDeduct} onChange={(v) => setReportDeduct(v || 0)} style={{ width: '100%', marginTop: 4 }} max={0} /></div>
          <div><Text>爽約扣分</Text><InputNumber value={noShowDeduct} onChange={(v) => setNoShowDeduct(v || 0)} style={{ width: '100%', marginTop: 4 }} max={0} /></div>
          <div><Text>停權門檻</Text><InputNumber value={suspendThreshold} onChange={(v) => setSuspendThreshold(v || 0)} style={{ width: '100%', marginTop: 4 }} min={0} max={30} /></div>
        </div>
        <Divider />
        <Button type="primary" icon={<SaveOutlined />} onClick={() => message.success('已儲存')}>儲存</Button>
      </Card>

      <DatasetManager />
    </div>
  )
}

function DatasetManager() {
  const [stats, setStats] = useState<{ is_clean: boolean; counts: Record<string, number> }>({ is_clean: true, counts: {} })
  const [loading, setLoading] = useState(false)
  const [freshMode, setFreshMode] = useState(true)

  const labels: Record<string, string> = {
    users: '用戶', conversations: '對話', messages: '訊息',
    date_invitations: '約會', orders: '訂單', subscriptions: '訂閱',
    reports: '回報', credit_score_histories: '分數記錄', notifications: '通知',
  }

  useEffect(() => { loadStats() }, [])

  async function loadStats() {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/settings/dataset/stats')
      setStats(res.data.data)
    } catch { /* ignore */ }
    setLoading(false)
  }

  function confirmAction(action: 'reset' | 'seed') {
    const title = action === 'reset' ? '確認清空資料庫' : '確認匯入測試資料集'
    const content = (
      <div>
        <Alert
          type={action === 'reset' ? 'error' : 'warning'}
          message={action === 'reset'
            ? '此操作將永久刪除所有業務資料（用戶、聊天、訂單等），僅保留管理員帳號和系統設定。'
            : `將匯入測試資料集（30 用戶、15 對話等）${freshMode ? '，會先清空現有資料' : ''}。`}
          showIcon style={{ marginBottom: 16 }}
        />
        <Text>請輸入管理員密碼確認：</Text>
        <Input.Password id="dataset-confirm-pw" style={{ marginTop: 8 }} placeholder="登入密碼" />
      </div>
    )

    Modal.confirm({
      title,
      content,
      okText: action === 'reset' ? '清空' : '匯入',
      okButtonProps: { danger: action === 'reset' },
      onOk: async () => {
        const pw = (document.getElementById('dataset-confirm-pw') as HTMLInputElement)?.value
        if (!pw) { message.error('請輸入密碼'); throw new Error('no password') }
        setLoading(true)
        try {
          if (action === 'reset') {
            await apiClient.post('/admin/settings/dataset/reset', { confirm_password: pw })
            message.success('資料庫已清空')
          } else {
            await apiClient.post('/admin/settings/dataset/seed', { fresh: freshMode, confirm_password: pw })
            message.success('測試資料集已匯入')
          }
          await loadStats()
        } catch (err: unknown) {
          const msg = (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message || '操作失敗'
          message.error(msg)
        }
        setLoading(false)
      },
    })
  }

  return (
    <Card title={<Space><DatabaseOutlined />測試資料集管理</Space>}>
      <Alert
        type={stats.is_clean ? 'success' : 'info'}
        message={stats.is_clean ? '資料庫目前為乾淨狀態 ✅' : '資料庫含有業務資料'}
        style={{ marginBottom: 16 }}
        showIcon
      />

      <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
        {Object.entries(stats.counts).map(([key, count]) => (
          <Col span={6} key={key}>
            <Statistic title={labels[key] || key} value={count} valueStyle={{ color: count === 0 ? '#9CA3AF' : '#111827', fontSize: 20 }} />
          </Col>
        ))}
      </Row>

      <Button icon={<ReloadOutlined />} onClick={loadStats} loading={loading} style={{ marginBottom: 16 }}>重新整理</Button>

      <Divider />

      <Space direction="vertical" size={16} style={{ width: '100%' }}>
        <Card size="small" style={{ background: '#FEF2F2', borderColor: '#FECACA' }}>
          <Space style={{ width: '100%', justifyContent: 'space-between' }}>
            <div>
              <Text strong>清空業務資料</Text>
              <br /><Text type="secondary" style={{ fontSize: 12 }}>刪除所有用戶、聊天、訂單。保留：管理員帳號、系統設定。</Text>
            </div>
            <Button danger icon={<DeleteOutlined />} onClick={() => confirmAction('reset')} disabled={loading || stats.is_clean}>
              清空資料庫
            </Button>
          </Space>
        </Card>

        <Card size="small" style={{ background: '#FEF3C7', borderColor: '#FDE68A' }}>
          <Space style={{ width: '100%', justifyContent: 'space-between' }}>
            <div>
              <Text strong>匯入測試資料集</Text>
              <br /><Text type="secondary" style={{ fontSize: 12 }}>匯入 30 個測試用戶、15 組對話、8 筆約會等。</Text>
              <br />
              <label style={{ fontSize: 12, cursor: 'pointer' }}>
                <input type="checkbox" checked={freshMode} onChange={(e) => setFreshMode(e.target.checked)} style={{ marginRight: 4 }} />
                先清空再匯入（Fresh 模式）
              </label>
            </div>
            <Button type="primary" style={{ background: '#F59E0B', borderColor: '#F59E0B' }} icon={<DatabaseOutlined />} onClick={() => confirmAction('seed')} disabled={loading}>
              匯入測試資料
            </Button>
          </Space>
        </Card>
      </Space>
    </Card>
  )
}

function PricingTab() {
  const [trialPrice, setTrialPrice] = useState(199)
  const [trialDays, setTrialDays] = useState(30)
  const [autoRenew, setAutoRenew] = useState(true)

  return (
    <Card title="訂閱設定">
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, maxWidth: 600 }}>
        <div><Text>體驗價</Text><InputNumber value={trialPrice} onChange={(v) => setTrialPrice(v || 0)} style={{ width: '100%', marginTop: 4 }} addonBefore="NT$" /></div>
        <div><Text>體驗天數</Text><InputNumber value={trialDays} onChange={(v) => setTrialDays(v || 0)} style={{ width: '100%', marginTop: 4 }} addonAfter="天" /></div>
        <div><Text>自動續訂預設</Text><div style={{ marginTop: 4 }}><Switch checked={autoRenew} onChange={setAutoRenew} checkedChildren="開" unCheckedChildren="關" /></div></div>
      </div>
      <Divider />
      <Button type="primary" icon={<SaveOutlined />} onClick={() => message.success('已儲存')}>儲存</Button>
    </Card>
  )
}

function AdminsTab() {
  return (
    <Card>
      <Text type="secondary">管理員帳號管理（待完善）</Text>
    </Card>
  )
}

export default function SystemSettingsPage() {
  const user = useAuthStore((s) => s.user)
  const isSuperAdmin = user?.role === 'super_admin'

  const superAdminTabs = ['mode', 'database', 'mail', 'sms']

  const allTabs = [
    { key: 'admins', label: '管理員帳號', children: <AdminsTab /> },
    { key: 'mode', label: '系統模式', children: <AppModeTab /> },
    { key: 'database', label: '資料庫設定', children: <DatabaseTab /> },
    { key: 'mail', label: 'Email 設定', children: <MailTab /> },
    { key: 'sms', label: 'SMS 設定', children: <SmsTab /> },
    { key: 'pricing', label: '訂閱方案', children: <PricingTab /> },
    { key: 'params', label: '系統參數', children: <SystemParamsTab /> },
  ].filter(tab => {
    if (superAdminTabs.includes(tab.key)) return isSuperAdmin
    return true
  })

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>系統設定</Title>
      <Tabs items={allTabs} />
    </div>
  )
}
