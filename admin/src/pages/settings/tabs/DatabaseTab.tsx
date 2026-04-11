import { useState, useEffect } from 'react'
import { Card, Form, Input, InputNumber, Button, Alert, Space, Typography, Modal, message, Tag } from 'antd'
import apiClient from '../../../api/client'

const { Text } = Typography

export default function DatabaseTab() {
  const [form] = Form.useForm()
  const [connStatus, setConnStatus] = useState<string>('unknown')
  const [testResult, setTestResult] = useState<string>('')
  const [testLoading, setTestLoading] = useState(false)
  const [saveLoading, setSaveLoading] = useState(false)

  useEffect(() => {
    apiClient.get('/admin/settings/system-control').then(res => {
      const db = res.data.data.database
      form.setFieldsValue({ host: db.host, port: db.port, database: db.database, username: db.username })
      setConnStatus(db.connection_status)
    }).catch(() => {})
  }, [form])

  async function handleTest() {
    const vals = form.getFieldsValue()
    if (!vals.password) { message.warning('請輸入密碼以測試連線'); return }
    setTestLoading(true)
    try {
      const res = await apiClient.post('/admin/settings/database/test', vals)
      setTestResult(`✅ 連線成功（${res.data.data.response_ms}ms，版本 ${res.data.data.server_version}）`)
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message || '連線失敗'
      setTestResult(`❌ ${msg}`)
    }
    setTestLoading(false)
  }

  async function handleSave() {
    Modal.confirm({
      title: '確認變更資料庫設定',
      content: (
        <div>
          <Alert type="warning" message="變更後需重啟容器才完全生效（約 30 秒）" style={{ marginBottom: 12 }} showIcon />
          <Text>請輸入管理員密碼確認：</Text>
          <Input.Password id="db-confirm-pw" style={{ marginTop: 8 }} placeholder="登入密碼" />
        </div>
      ),
      onOk: async () => {
        const pw = (document.getElementById('db-confirm-pw') as HTMLInputElement)?.value
        if (!pw) { message.error('請輸入密碼'); return }
        setSaveLoading(true)
        try {
          const vals = form.getFieldsValue()
          await apiClient.patch('/admin/settings/database', { ...vals, confirm_password: pw })
          message.success('資料庫設定已更新，請重啟容器')
        } catch (err: unknown) {
          const msg = (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message || '儲存失敗'
          message.error(msg)
        }
        setSaveLoading(false)
      },
    })
  }

  return (
    <Card>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 16 }}>
        <Text strong>連線狀態：</Text>
        <Tag color={connStatus === 'connected' ? 'green' : 'red'}>{connStatus === 'connected' ? '已連線' : '錯誤'}</Tag>
      </div>

      <Form form={form} layout="vertical" style={{ maxWidth: 500 }}>
        <Form.Item label="主機" name="host"><Input /></Form.Item>
        <Form.Item label="Port" name="port"><InputNumber style={{ width: '100%' }} /></Form.Item>
        <Form.Item label="資料庫名" name="database"><Input /></Form.Item>
        <Form.Item label="使用者名稱" name="username"><Input /></Form.Item>
        <Form.Item label="密碼" name="password"><Input.Password placeholder="留空保留現有密碼" /></Form.Item>
      </Form>

      {testResult && <Alert message={testResult} type={testResult.startsWith('✅') ? 'success' : 'error'} style={{ marginBottom: 16 }} />}

      <Space>
        <Button onClick={handleTest} loading={testLoading}>🔌 測試連線</Button>
        <Button type="primary" onClick={handleSave} loading={saveLoading}>儲存設定</Button>
      </Space>

      <Alert type="warning" message="⚠️ 變更資料庫設定後需重啟應用容器才完全生效" style={{ marginTop: 16 }} showIcon />
    </Card>
  )
}
