import { useState } from 'react'
import { Card, InputNumber, Button, Typography, Divider, Space, message, Switch } from 'antd'
import { SaveOutlined } from '@ant-design/icons'

const { Title, Text } = Typography

export default function SystemSettingsPage() {
  // Credit score rules
  const [qrGpsScore, setQrGpsScore] = useState(5)
  const [qrNoGpsScore, setQrNoGpsScore] = useState(2)
  const [reportDeduct, setReportDeduct] = useState(-15)
  const [noShowDeduct, setNoShowDeduct] = useState(-10)
  const [suspendThreshold, setSuspendThreshold] = useState(0)

  // Subscription settings
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
      <Title level={4} style={{ marginBottom: 24 }}>系統參數設定</Title>

      <Card title="誠信分數規則" style={{ marginBottom: 24 }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, maxWidth: 600 }}>
          <div>
            <Text>QR 約會 GPS 通過得分</Text>
            <InputNumber value={qrGpsScore} onChange={(v) => setQrGpsScore(v || 0)} style={{ width: '100%', marginTop: 4 }} addonBefore="+" />
          </div>
          <div>
            <Text>QR 約會無 GPS 得分</Text>
            <InputNumber value={qrNoGpsScore} onChange={(v) => setQrNoGpsScore(v || 0)} style={{ width: '100%', marginTop: 4 }} addonBefore="+" />
          </div>
          <div>
            <Text>被檢舉扣分</Text>
            <InputNumber value={reportDeduct} onChange={(v) => setReportDeduct(v || 0)} style={{ width: '100%', marginTop: 4 }} max={0} />
          </div>
          <div>
            <Text>爽約扣分</Text>
            <InputNumber value={noShowDeduct} onChange={(v) => setNoShowDeduct(v || 0)} style={{ width: '100%', marginTop: 4 }} max={0} />
          </div>
          <div>
            <Text>停權門檻（分數 ≤ 此值停權）</Text>
            <InputNumber value={suspendThreshold} onChange={(v) => setSuspendThreshold(v || 0)} style={{ width: '100%', marginTop: 4 }} min={0} max={30} />
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
            <InputNumber value={trialPrice} onChange={(v) => setTrialPrice(v || 0)} style={{ width: '100%', marginTop: 4 }} addonBefore="NT$" min={0} />
          </div>
          <div>
            <Text>體驗方案天數</Text>
            <InputNumber value={trialDays} onChange={(v) => setTrialDays(v || 0)} style={{ width: '100%', marginTop: 4 }} addonAfter="天" min={1} />
          </div>
          <div>
            <Text>自動續訂預設開關</Text>
            <div style={{ marginTop: 4 }}>
              <Switch checked={autoRenewDefault} onChange={setAutoRenewDefault} checkedChildren="開" unCheckedChildren="關" />
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
