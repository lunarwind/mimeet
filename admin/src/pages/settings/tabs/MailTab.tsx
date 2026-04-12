import { useState, useEffect } from 'react'
import { Card, Form, Input, InputNumber, Select, Button, Space, Segmented, message, Divider, Typography, Alert } from 'antd'
import { SaveOutlined } from '@ant-design/icons'
import apiClient from '../../../api/client'
import DebugResultPanel from '../../../components/common/DebugResultPanel'

const { Text } = Typography

export default function MailTab() {
  const [form] = Form.useForm()
  const [driver, setDriver] = useState<'resend' | 'smtp'>('resend')
  const [testEmail, setTestEmail] = useState('')
  const [testResult, setTestResult] = useState<Record<string, unknown> | null>(null)
  const [testLoading, setTestLoading] = useState(false)
  const [saveLoading, setSaveLoading] = useState(false)

  useEffect(() => {
    apiClient.get('/admin/settings/system-control').then(res => {
      const mail = res.data.data.mail
      const d = mail.driver || 'smtp'
      setDriver(d as 'resend' | 'smtp')
      form.setFieldsValue({
        driver: d,
        resend_api_key: mail.resend_api_key || '',
        host: mail.host || '', port: mail.port || 587, encryption: mail.encryption || 'tls',
        username: mail.username || '', from_address: mail.from_address || '', from_name: mail.from_name || 'MiMeet',
      })
    }).catch(() => {})
  }, [form])

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
        success: false, debug_log: [`❌ HTTP ${resp?.status ?? '?'}`, `  ${(err as { message?: string })?.message ?? ''}`],
      })
    }
    setTestLoading(false)
  }

  async function handleSave() {
    setSaveLoading(true)
    try {
      const vals = form.getFieldsValue()
      const body: Record<string, unknown> = { driver: vals.driver || driver, from_address: vals.from_address, from_name: vals.from_name }
      if ((vals.driver || driver) === 'resend') {
        body.resend_api_key = vals.resend_api_key
      } else {
        body.host = vals.host; body.port = vals.port; body.encryption = vals.encryption; body.username = vals.username
        if (vals.password) body.password = vals.password
      }
      await apiClient.patch('/admin/settings/mail', body)
      message.success('Email 設定已更新')
    } catch (err: unknown) {
      message.error((err as { response?: { data?: { message?: string } } })?.response?.data?.message || '儲存失敗')
    }
    setSaveLoading(false)
  }

  return (
    <Card>
      <Form form={form} layout="vertical" style={{ maxWidth: 500 }} onValuesChange={(changed) => {
        if (changed.driver) setDriver(changed.driver)
        if (changed.port === 465) form.setFieldValue('encryption', 'ssl')
        else if (changed.port === 587) form.setFieldValue('encryption', 'tls')
      }}>
        <Form.Item label="寄信方式" name="driver">
          <Select>
            <Select.Option value="resend">Resend API（推薦，不受 SMTP Port 限制）</Select.Option>
            <Select.Option value="smtp">SMTP</Select.Option>
          </Select>
        </Form.Item>

        {driver === 'resend' ? (
          <>
            <Alert type="info" message="Resend 透過 HTTP API 寄信，不需要 SMTP Port。適合 DigitalOcean 等封鎖 25/465/587 的環境。" showIcon style={{ marginBottom: 16 }} />
            <Form.Item label="Resend API Key" name="resend_api_key">
              <Input.Password placeholder="re_xxxxxxxxxxxxxxxx" />
            </Form.Item>
            <Text type="secondary" style={{ fontSize: 12, display: 'block', marginBottom: 16 }}>
              取得 API Key：<a href="https://resend.com/api-keys" target="_blank" rel="noreferrer">resend.com/api-keys</a>
            </Text>
          </>
        ) : (
          <>
            <Segmented options={['SendGrid', '其他 SMTP']} onChange={(v) => {
              if (v === 'SendGrid') form.setFieldsValue({ host: 'smtp.sendgrid.net', port: 587, encryption: 'tls', username: 'apikey' })
            }} style={{ marginBottom: 16 }} />
            <Form.Item label="SMTP 主機" name="host"><Input /></Form.Item>
            <Form.Item label="Port" name="port"><InputNumber style={{ width: '100%' }} /></Form.Item>
            <Form.Item label="加密" name="encryption" extra="Port 465 → SSL，Port 587 → TLS">
              <Select options={[{ value: 'null', label: '無' }, { value: 'tls', label: 'TLS (587)' }, { value: 'ssl', label: 'SSL (465)' }]} />
            </Form.Item>
            <Form.Item label="使用者名稱" name="username"><Input /></Form.Item>
            <Form.Item label="密碼" name="password"><Input.Password placeholder="留空保留現有密碼" /></Form.Item>
          </>
        )}

        <Form.Item label="寄件人 Email" name="from_address"><Input /></Form.Item>
        <Form.Item label="寄件人名稱" name="from_name"><Input /></Form.Item>
      </Form>

      <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={saveLoading} style={{ marginBottom: 24 }}>儲存設定</Button>

      <Divider />

      <Card title="📧 發送測試信" size="small" style={{ background: '#F9FAFB' }}>
        <Space>
          <Input placeholder="收件人 Email" value={testEmail} onChange={e => setTestEmail(e.target.value)} style={{ width: 280 }} />
          <Button onClick={handleTestMail} loading={testLoading}>發送測試信</Button>
        </Space>
        <DebugResultPanel result={testResult as Parameters<typeof DebugResultPanel>[0]['result']} isLoading={testLoading} />
      </Card>
    </Card>
  )
}
