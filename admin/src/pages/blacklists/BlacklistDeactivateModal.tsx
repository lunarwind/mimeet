import { useState } from 'react'
import { Modal, Input, message, Tag, Descriptions } from 'antd'
import apiClient from '../../api/client'

interface BlacklistItem {
  id: number
  type: 'email' | 'mobile'
  value_masked: string
  reason: string | null
  expires_at: string | null
  created_at: string
}

interface Props {
  open: boolean
  target: BlacklistItem | null
  onClose: () => void
  onSuccess: () => void
}

export default function BlacklistDeactivateModal({ open, target, onClose, onSuccess }: Props) {
  const [reason, setReason] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleOk() {
    if (!target) return
    if (!reason.trim() || reason.trim().length < 1) {
      message.warning('請填寫解除原因')
      return
    }
    setSubmitting(true)
    try {
      await apiClient.patch(`/admin/blacklists/${target.id}/deactivate`, { reason: reason.trim() })
      message.success('已解除')
      setReason('')
      onSuccess()
    } catch (err: unknown) {
      const errorObj = (err as { response?: { status?: number; data?: { error?: { message?: string } } } })?.response
      message.error(errorObj?.data?.error?.message || '解除失敗')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal
      title="解除註冊禁止名單"
      open={open}
      onOk={handleOk}
      onCancel={() => { setReason(''); onClose() }}
      confirmLoading={submitting}
      okButtonProps={{ disabled: !reason.trim(), danger: true }}
      okText="確認解除"
      cancelText="取消"
      destroyOnClose
    >
      {target && (
        <Descriptions column={1} size="small" bordered style={{ marginBottom: 16 }}>
          <Descriptions.Item label="類型">
            {target.type === 'email' ? <Tag color="blue">Email</Tag> : <Tag color="green">手機</Tag>}
          </Descriptions.Item>
          <Descriptions.Item label="遮罩值">{target.value_masked}</Descriptions.Item>
          <Descriptions.Item label="原因">{target.reason || '—'}</Descriptions.Item>
        </Descriptions>
      )}
      <p style={{ fontSize: 12, color: '#999' }}>解除後該 email/手機可再次用於註冊。請填寫解除原因供稽核追溯。</p>
      <Input.TextArea
        value={reason}
        onChange={(e) => setReason(e.target.value)}
        placeholder="解除原因(必填)"
        rows={3}
        maxLength={500}
        showCount
      />
    </Modal>
  )
}
