import { useState, useEffect } from 'react'
import { Card, Form, Input, Select, Button, Alert, Space, message } from 'antd'
import apiClient from '../../../api/client'

export default function SmsTab() {
  const [form] = Form.useForm()
  const [provider, setProvider] = useState('disabled')
  const [testPhone, setTestPhone] = useState('')
  const [testResult, setTestResult] = useState('')
  const [testLoading, setTestLoading] = useState(false)
  const [saveLoading, setSaveLoading] = useState(false)

  useEffect(() => {
    apiClient.get('/admin/settings/system-control').then(res => {
      const sms = res.data.data.sms
      setProvider(sms.provider)
      form.setFieldsValue({
        provider: sms.provider,
        mitake_username: sms.mitake?.username || '',
        twilio_account_sid: sms.twilio?.account_sid || '',
        twilio_from_number: sms.twilio?.from_number || '',
        every8d_username: sms.every8d?.username || '',
      })
    }).catch(() => {})
  }, [form])

  async function handleSave() {
    setSaveLoading(true)
    const vals = form.getFieldsValue()
    const body: Record<string, unknown> = { provider: vals.provider }
    if (vals.provider === 'mitake') body.mitake = { username: vals.mitake_username, password: vals.mitake_password || undefined }
    if (vals.provider === 'twilio') body.twilio = { account_sid: vals.twilio_account_sid, auth_token: vals.twilio_auth_token || undefined, from_number: vals.twilio_from_number }
    if (vals.provider === 'every8d') body.every8d = { username: vals.every8d_username, password: vals.every8d_password || undefined }

    try {
      const res = await apiClient.patch('/admin/settings/sms', body)
      message.success(res.data.data.message)
    } catch { message.error('儲存失敗') }
    setSaveLoading(false)
  }

  async function handleTestSms() {
    if (!testPhone) return
    setTestLoading(true)
    try {
      const res = await apiClient.post('/admin/settings/sms/test', { phone: testPhone })
      setTestResult(`✅ ${res.data.data.message}`)
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message || '發送失敗'
      setTestResult(`❌ ${msg}`)
    }
    setTestLoading(false)
  }

  return (
    <Card>
      <Form form={form} layout="vertical" style={{ maxWidth: 500 }} onValuesChange={(changed) => { if (changed.provider) setProvider(changed.provider) }}>
        <Form.Item label="SMS 服務商" name="provider">
          <Select options={[
            { value: 'mitake', label: '三竹簡訊' },
            { value: 'twilio', label: 'Twilio' },
            { value: 'every8d', label: '每日簡訊' },
            { value: 'disabled', label: '停用（僅寫 Log）' },
          ]} />
        </Form.Item>

        {provider === 'mitake' && (<>
          <Form.Item label="三竹帳號" name="mitake_username"><Input /></Form.Item>
          <Form.Item label="三竹密碼" name="mitake_password"><Input.Password placeholder="留空保留現有" /></Form.Item>
        </>)}

        {provider === 'twilio' && (<>
          <Form.Item label="Account SID" name="twilio_account_sid"><Input /></Form.Item>
          <Form.Item label="Auth Token" name="twilio_auth_token"><Input.Password placeholder="留空保留現有" /></Form.Item>
          <Form.Item label="發送號碼" name="twilio_from_number"><Input placeholder="+1xxxxxxxxxx" /></Form.Item>
        </>)}

        {provider === 'every8d' && (<>
          <Form.Item label="帳號" name="every8d_username"><Input /></Form.Item>
          <Form.Item label="密碼" name="every8d_password"><Input.Password placeholder="留空保留現有" /></Form.Item>
        </>)}
      </Form>

      <Card title="📱 發送測試簡訊" size="small" style={{ marginBottom: 16, background: '#F9FAFB' }}>
        <Space>
          <Input placeholder="手機號碼 09xxxxxxxx" value={testPhone} onChange={e => setTestPhone(e.target.value)} style={{ width: 200 }} />
          <Button onClick={handleTestSms} loading={testLoading}>發送測試簡訊</Button>
        </Space>
        {testResult && <Alert message={testResult} type={testResult.startsWith('✅') ? 'success' : 'error'} style={{ marginTop: 8 }} />}
      </Card>

      <Button type="primary" onClick={handleSave} loading={saveLoading}>儲存設定</Button>
    </Card>
  )
}
