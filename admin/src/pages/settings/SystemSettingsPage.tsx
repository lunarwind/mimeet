import { useState } from 'react'
import { Tabs, Card, InputNumber, Button, Typography, Divider, Space, message, Switch } from 'antd'
import { SaveOutlined } from '@ant-design/icons'
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
    <Card title="誠信分數規則">
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
