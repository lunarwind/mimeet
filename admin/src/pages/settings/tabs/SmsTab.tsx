import { useState, useEffect } from 'react'
import { Card, Form, Input, Select, Button, Space, message, Divider, Typography, Alert } from 'antd'
import apiClient from '../../../api/client'
import DebugResultPanel from '../../../components/common/DebugResultPanel'

const { Text } = Typography

export default function SmsTab() {
  const [form] = Form.useForm()
  const [provider, setProvider] = useState('disabled')
  const [appMode, setAppMode] = useState<string | null>(null)
  const [saveLoading, setSaveLoading] = useState(false)

  // --- Per-provider test credentials (separate state, never shared) ---
  // Mitake
  const [mitakeTestUser, setMitakeTestUser] = useState('')
  const [mitakeTestPass, setMitakeTestPass] = useState('')
  // Twilio
  const [twilioTestSid, setTwilioTestSid] = useState('')
  const [twilioTestToken, setTwilioTestToken] = useState('')
  const [twilioTestFrom, setTwilioTestFrom] = useState('')
  // Common
  const [testPhone, setTestPhone] = useState('0983144094')
  const [testMessage, setTestMessage] = useState('TEST from mimeet admin panel')
  const [testLoading, setTestLoading] = useState(false)
  const [testResult, setTestResult] = useState<Record<string, unknown> | null>(null)

  useEffect(() => {
    apiClient.get('/admin/settings/system-control').then(res => {
      const data = res.data.data
      const sms = data.sms
      setProvider(sms.provider)
      setAppMode(data.app_mode?.mode ?? data.app_mode ?? null)
      form.setFieldsValue({
        provider: sms.provider,
        mitake_username: sms.mitake?.username || '',
        twilio_account_sid: sms.twilio?.account_sid || '',
        twilio_from_number: sms.twilio?.from_number || '',
      })
      // Pre-fill test fields from saved settings
      if (sms.mitake?.username) setMitakeTestUser(sms.mitake.username)
      if (sms.twilio?.account_sid) setTwilioTestSid(sms.twilio.account_sid)
      if (sms.twilio?.from_number) setTwilioTestFrom(sms.twilio.from_number)
    }).catch(() => {})
  }, [form])

  async function handleSave() {
    setSaveLoading(true)
    const vals = form.getFieldsValue()
    const body: Record<string, unknown> = { provider: vals.provider }
    if (vals.provider === 'mitake') body.mitake = { username: vals.mitake_username, password: vals.mitake_password || undefined }
    if (vals.provider === 'twilio') body.twilio = { account_sid: vals.twilio_account_sid, auth_token: vals.twilio_auth_token || undefined, from_number: vals.twilio_from_number }

    try {
      const res = await apiClient.patch('/admin/settings/sms', body)
      message.success(res.data.data.message)
    } catch { message.error('儲存失敗') }
    setSaveLoading(false)
  }

  function toE164(phone: string): string {
    const cleaned = phone.replace(/[\s-]/g, '')
    if (cleaned.startsWith('09')) return '+886' + cleaned.substring(1)
    if (cleaned.startsWith('+')) return cleaned
    return '+886' + cleaned.replace(/^0+/, '')
  }

  async function handleTestSms() {
    if (!testPhone) { message.warning('請輸入電話號碼'); return }
    setTestLoading(true)
    setTestResult(null)

    const payload: Record<string, string | undefined> = {
      phone: testPhone,
      message: testMessage || undefined,
      provider_override: provider,
    }

    if (provider === 'twilio') {
      payload.username = twilioTestSid || undefined
      payload.password = twilioTestToken || undefined
      payload.from_number = twilioTestFrom || undefined
      payload.phone = toE164(testPhone) // Convert for display consistency
    } else if (provider === 'mitake') {
      payload.username = mitakeTestUser || undefined
      payload.password = mitakeTestPass || undefined
    }

    try {
      const res = await apiClient.post('/admin/settings/sms/test', payload, { timeout: 30000 })
      setTestResult(res.data)
    } catch (err: unknown) {
      const resp = (err as { response?: { data?: Record<string, unknown>; status?: number }; message?: string })?.response
      setTestResult(resp?.data as Record<string, unknown> ?? {
        success: false,
        debug_log: [`❌ HTTP ${resp?.status ?? '?'} 請求失敗`, `  ${(err as { message?: string })?.message ?? ''}`],
        error_detail: { http_status: resp?.status },
      })
    }
    setTestLoading(false)
  }

  const isTwilio = provider === 'twilio'
  const isMitake = provider === 'mitake'

  return (
    <Card>
      {provider !== 'disabled' && (
        <Alert
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
          message="SMS 行為說明（2026-04-28 更新）"
          description={
            <span>
              SMS 發送行為由上方「SMS 服務商」設定決定，<strong>與系統模式（app_mode）無關</strong>。
              {' '}當前 app_mode = <code>{appMode ?? '讀取中...'}</code>。
              {' '}設定服務商為「停用」時只寫 log；設為 Twilio/三竹時前台即真實寄出。
            </span>
          }
        />
      )}
      <Form form={form} layout="vertical" style={{ maxWidth: 500 }} onValuesChange={(changed) => { if (changed.provider) setProvider(changed.provider) }}>
        <Form.Item label="SMS 服務商" name="provider">
          <Select options={[
            { value: 'mitake', label: '三竹簡訊' },
            { value: 'twilio', label: 'Twilio' },
            { value: 'disabled', label: '停用（僅寫 Log）' },
          ]} />
        </Form.Item>

        {isMitake && (<>
          <Form.Item label="三竹帳號" name="mitake_username"><Input /></Form.Item>
          <Form.Item label="三竹密碼" name="mitake_password"><Input.Password placeholder="留空保留現有" /></Form.Item>
        </>)}

        {isTwilio && (<>
          <Form.Item label="Account SID" name="twilio_account_sid"><Input placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" /></Form.Item>
          <Form.Item label="Auth Token" name="twilio_auth_token"><Input.Password placeholder="留空保留現有" /></Form.Item>
          <Form.Item label="發送號碼 (From)" name="twilio_from_number"><Input placeholder="+16067553121" /></Form.Item>
        </>)}

        <Button type="primary" onClick={handleSave} loading={saveLoading}>儲存設定</Button>
      </Form>

      <Divider />

      <Card
        title={`📱 發送測試簡訊${isTwilio ? ' (Twilio)' : isMitake ? ' (三竹)' : ''}`}
        size="small"
        style={{ background: '#F9FAFB', border: '1px solid #E5E7EB' }}
      >
        <Text type="secondary" style={{ display: 'block', marginBottom: 12, fontSize: 12 }}>
          {isTwilio
            ? '輸入 Twilio 憑證測試發送。電話號碼自動轉換為 E.164 格式（09xx → +886xx）。'
            : isMitake
              ? '輸入三竹帳密測試發送，不影響系統已儲存的設定。'
              : '請先選擇 SMS 服務商。'}
        </Text>

        <Space direction="vertical" style={{ width: '100%' }} size={8}>
          {/* Mitake test fields */}
          {isMitake && (
            <Space wrap>
              <Input
                value={mitakeTestUser}
                onChange={e => setMitakeTestUser(e.target.value)}
                style={{ width: 220 }}
                addonBefore="帳號"
                placeholder="三竹帳號"
              />
              <Input.Password
                value={mitakeTestPass}
                onChange={e => setMitakeTestPass(e.target.value)}
                style={{ width: 240 }}
                addonBefore="密碼"
                placeholder="三竹密碼"
              />
            </Space>
          )}

          {/* Twilio test fields */}
          {isTwilio && (<>
            <Space wrap>
              <Input
                value={twilioTestSid}
                onChange={e => setTwilioTestSid(e.target.value)}
                style={{ width: 320 }}
                addonBefore="SID"
                placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
              />
            </Space>
            <Space wrap>
              <Input.Password
                value={twilioTestToken}
                onChange={e => setTwilioTestToken(e.target.value)}
                style={{ width: 320 }}
                addonBefore="Token"
                placeholder="Auth Token"
              />
            </Space>
            <Input
              value={twilioTestFrom}
              onChange={e => setTwilioTestFrom(e.target.value)}
              style={{ width: 240 }}
              addonBefore="From"
              placeholder="+16067553121"
            />
          </>)}

          {/* Common: phone + message */}
          <Space wrap>
            <Input
              value={testPhone}
              onChange={e => setTestPhone(e.target.value)}
              style={{ width: 200 }}
              addonBefore="電話"
              placeholder="09xxxxxxxx"
              suffix={isTwilio && testPhone ? <Text type="secondary" style={{ fontSize: 11 }}>{toE164(testPhone)}</Text> : null}
            />
            <Input
              value={testMessage}
              onChange={e => setTestMessage(e.target.value)}
              style={{ width: 300 }}
              addonBefore="訊息"
              placeholder="測試訊息內容"
            />
          </Space>

          <Button type="primary" onClick={handleTestSms} loading={testLoading} disabled={provider === 'disabled'}>
            發送測試簡訊
          </Button>
        </Space>

        <DebugResultPanel result={testResult as Parameters<typeof DebugResultPanel>[0]['result']} isLoading={testLoading} />
      </Card>
    </Card>
  )
}
