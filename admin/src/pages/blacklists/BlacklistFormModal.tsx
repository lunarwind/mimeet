import { useState } from 'react'
import { Modal, Form, Select, Input, DatePicker, message, Alert } from 'antd'
import dayjs from 'dayjs'
import apiClient from '../../api/client'

interface Props {
  open: boolean
  onClose: () => void
  onSuccess: () => void
}

export default function BlacklistFormModal({ open, onClose, onSuccess }: Props) {
  const [form] = Form.useForm()
  const [submitting, setSubmitting] = useState(false)
  const [type, setType] = useState<'email' | 'mobile'>('email')

  async function handleOk() {
    try {
      const values = await form.validateFields()
      setSubmitting(true)

      // Email client-side normalize (lowercase + trim) — server 仍會做,client 是 UX 輔助
      const value = type === 'email' ? value_lower(values.value) : values.value

      await apiClient.post('/admin/blacklists', {
        type: values.type,
        value,
        reason: values.reason || null,
        expires_at: values.expires_at ? values.expires_at.toISOString() : null,
      })
      message.success('已加入禁止名單')
      form.resetFields()
      onSuccess()
    } catch (err: unknown) {
      const errorObj = (err as { response?: { status?: number; data?: { error?: { code?: string; message?: string } } } })?.response
      if (errorObj?.status === 409) {
        message.error(errorObj.data?.error?.message || '此值已在禁止名單中')
      } else if (errorObj?.status === 422) {
        message.error(errorObj.data?.error?.message || '輸入格式有誤')
      } else if (errorObj?.status === 403) {
        message.error('權限不足')
      } else if (errorObj?.status) {
        message.error(errorObj.data?.error?.message || '送出失敗')
      }
      // form.validateFields 失敗時 errorObj 為 undefined,不顯示 toast
    } finally {
      setSubmitting(false)
    }
  }

  function value_lower(v: string): string {
    return (v || '').toLowerCase().trim()
  }

  return (
    <Modal
      title="新增註冊禁止名單"
      open={open}
      onOk={handleOk}
      onCancel={() => { form.resetFields(); onClose() }}
      confirmLoading={submitting}
      okText="新增"
      cancelText="取消"
      destroyOnClose
    >
      <Alert
        type="info"
        showIcon
        message="加入禁止名單後,該 email/手機將無法用於新註冊。可隨時於本頁面解除。"
        style={{ marginBottom: 16 }}
      />
      <Form form={form} layout="vertical" initialValues={{ type: 'email' }}>
        <Form.Item name="type" label="類型" rules={[{ required: true }]}>
          <Select onChange={(v) => setType(v)}>
            <Select.Option value="email">Email</Select.Option>
            <Select.Option value="mobile">手機號碼</Select.Option>
          </Select>
        </Form.Item>

        <Form.Item
          name="value"
          label={type === 'email' ? 'Email' : '手機號碼'}
          rules={[
            { required: true, message: '此欄位必填' },
            { max: 255, message: '不可超過 255 字' },
            type === 'email'
              ? { type: 'email', message: '請輸入有效 Email' }
              : { pattern: /^(09\d{8}|\+8869\d{8})$/, message: '請輸入台灣手機號碼(09xxxxxxxx 或 +8869xxxxxxxx)' },
          ]}
        >
          <Input placeholder={type === 'email' ? 'spam@example.com' : '0912345678'} />
        </Form.Item>

        <Form.Item name="reason" label="原因(選填,建議填寫供日後追溯)" rules={[{ max: 500 }]}>
          <Input.TextArea rows={3} maxLength={500} showCount placeholder="例:多次違規 / 詐騙嫌疑 / 客訴" />
        </Form.Item>

        <Form.Item name="expires_at" label="到期日(選填,留空為永久)">
          <DatePicker
            showTime
            disabledDate={(d) => !d || d.isBefore(dayjs())}
            style={{ width: '100%' }}
            placeholder="選擇到期日(留空為永久)"
          />
        </Form.Item>
      </Form>
    </Modal>
  )
}
