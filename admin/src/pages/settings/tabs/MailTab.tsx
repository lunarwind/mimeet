import { useState, useEffect } from 'react'
import { Card, Form, Input, InputNumber, Select, Button, Space, Segmented, message } from 'antd'
import apiClient from '../../../api/client'
import DebugResultPanel from '../../../components/common/DebugResultPanel'

export default function MailTab() {
  const [form] = Form.useForm()
  const [testEmail, setTestEmail] = useState('')
  const [testResult, setTestResult] = useState<Record<string, unknown> | null>(null)
  const [testLoading, setTestLoading] = useState(false)
  const [saveLoading, setSaveLoading] = useState(false)

  useEffect(() => {
    apiClient.get('/admin/settings/system-control').then(res => {
      const mail = res.data.data.mail
      form.setFieldsValue({
        host: mail.host, port: mail.port, encryption: mail.encryption,
        username: mail.username, from_address: mail.from_address, from_name: mail.from_name,
      })
    }).catch(() => {})
  }, [form])

  function applyPreset(preset: string) {
    if (preset === 'SendGrid') {
      form.setFieldsValue({ host: 'smtp.sendgrid.net', port: 587, encryption: 'tls', username: 'apikey' })
    }
  }

  async function handleTestMail() {
    if (!testEmail) return
    setTestLoading(true)
    setTestResult(null)
    try {
      const res = await apiClient.post('/admin/settings/mail/test', { test_email: testEmail }, { timeout: 30000 })
      setTestResult(res.data)
    } catch (err: unknown) {
      const resp = (err as { response?: { data?: Record<string, unknown>; status?: number }; message?: string })?.response
      setTestResult(resp?.data as Record<string, unknown> ?? {
        success: false,
        debug_log: [`❌ HTTP ${resp?.status ?? '?'} 請求失敗`, `  ${(err as { message?: string })?.message ?? ''}`],
        error_detail: { http_status: resp?.status, response: resp?.data },
      })
    }
    setTestLoading(false)
  }

  async function handleSave() {
    setSaveLoading(true)
    try {
      await apiClient.patch('/admin/settings/mail', form.getFieldsValue())
      message.success('Email 設定已更新')
    } catch (err: unknown) {
      const resp = (err as { response?: { data?: { message?: string; error?: { message?: string } } } })?.response?.data
      message.error(resp?.error?.message || resp?.message || '儲存失敗')
    }
    setSaveLoading(false)
  }

  return (
    <Card>
      <Segmented options={['SendGrid', '其他 SMTP']} onChange={(v) => applyPreset(v as string)} style={{ marginBottom: 24 }} />

      <Form form={form} layout="vertical" style={{ maxWidth: 500 }} onValuesChange={(changed) => {
        // Auto-suggest encryption when port changes
        if (changed.port === 465) form.setFieldValue('encryption', 'ssl')
        else if (changed.port === 587) form.setFieldValue('encryption', 'tls')
        else if (changed.port === 25) form.setFieldValue('encryption', 'null')
      }}>
        <Form.Item label="SMTP 主機" name="host"><Input /></Form.Item>
        <Form.Item label="Port" name="port"><InputNumber style={{ width: '100%' }} /></Form.Item>
        <Form.Item label="加密" name="encryption" extra="Port 465 → SSL，Port 587 → TLS">
          <Select options={[{ value: 'null', label: '無' }, { value: 'tls', label: 'TLS (Port 587)' }, { value: 'ssl', label: 'SSL (Port 465)' }]} />
        </Form.Item>
        <Form.Item label="使用者名稱" name="username"><Input /></Form.Item>
        <Form.Item label="密碼" name="password"><Input.Password placeholder="留空保留現有密碼" /></Form.Item>
        <Form.Item label="寄件人地址" name="from_address"><Input /></Form.Item>
        <Form.Item label="寄件人名稱" name="from_name"><Input /></Form.Item>
      </Form>

      <Card title="📧 發送測試信" size="small" style={{ marginBottom: 16, background: '#F9FAFB' }}>
        <Space>
          <Input placeholder="收件人 Email" value={testEmail} onChange={e => setTestEmail(e.target.value)} style={{ width: 280 }} />
          <Button onClick={handleTestMail} loading={testLoading}>發送測試信</Button>
        </Space>
        <DebugResultPanel result={testResult as Parameters<typeof DebugResultPanel>[0]['result']} isLoading={testLoading} />
      </Card>

      <Button type="primary" onClick={handleSave} loading={saveLoading}>儲存設定</Button>
    </Card>
  )
}
