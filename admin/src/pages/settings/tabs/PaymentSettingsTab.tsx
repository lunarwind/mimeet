import { useState, useEffect } from 'react'
import { Card, Input, Select, Button, Alert, Divider, Space, Typography, Tag, Form, message, Switch } from 'antd'
import { SaveOutlined, LinkOutlined } from '@ant-design/icons'
import apiClient from '../../../api/client'

const { Text } = Typography

interface EcpaySettings {
  ecpay_environment: 'sandbox' | 'production'
  ecpay_sandbox_merchant_id: string
  ecpay_sandbox_hash_key: string    // ****xxxx 格式
  ecpay_sandbox_hash_iv: string
  ecpay_production_merchant_id: string
  ecpay_production_hash_key: string
  ecpay_production_hash_iv: string
  ecpay_invoice_enabled: string
}

export default function PaymentSettingsTab() {
  const [form] = Form.useForm()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [env, setEnv] = useState<'sandbox' | 'production'>('sandbox')
  const [invoiceEnabled, setInvoiceEnabled] = useState(false)
  const [prodWarning, setProdWarning] = useState(false)

  useEffect(() => { fetchSettings() }, [])

  async function fetchSettings() {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/settings/payment')
      const d: EcpaySettings = res.data.data ?? {}
      form.setFieldsValue(d)
      const curEnv = (d.ecpay_environment ?? 'sandbox') as 'sandbox' | 'production'
      setEnv(curEnv)
      setInvoiceEnabled(d.ecpay_invoice_enabled === '1')
      checkProdWarning(curEnv, d)
    } catch { message.error('載入金流設定失敗') }
    setLoading(false)
  }

  function checkProdWarning(curEnv: string, d?: Partial<EcpaySettings>) {
    if (curEnv !== 'production') { setProdWarning(false); return }
    const vals = d ?? form.getFieldsValue()
    const isEmpty = !vals.ecpay_production_merchant_id ||
                    !vals.ecpay_production_hash_key || vals.ecpay_production_hash_key === '' ||
                    !vals.ecpay_production_hash_iv || vals.ecpay_production_hash_iv === ''
    setProdWarning(isEmpty)
  }

  async function handleSave() {
    const vals = form.getFieldsValue()
    setSaving(true)
    try {
      await apiClient.put('/admin/settings/payment', {
        ...vals,
        ecpay_environment: env,
        ecpay_invoice_enabled: invoiceEnabled ? '1' : '0',
      })
      message.success('金流設定已儲存，下一筆交易即生效')
      fetchSettings()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      message.error(e?.response?.data?.message ?? '儲存失敗')
    }
    setSaving(false)
  }

  if (loading) return <div style={{ padding: 40, textAlign: 'center' }}>載入中...</div>

  return (
    <div style={{ maxWidth: 700 }}>
      {/* production 憑證未設定警示 */}
      {prodWarning && (
        <Alert
          type="error"
          showIcon
          message="⚠️ 正式環境憑證未完整設定"
          description="目前切換為 Production 環境，但正式 MerchantID 或 HashKey/IV 尚未填入，金流將無法運作！"
          style={{ marginBottom: 16 }}
        />
      )}

      <Alert
        type="info"
        showIcon
        message="⚡ 儲存後即時生效"
        description={
          <span>
            修改後下一筆交易即套用新憑證。HashKey/IV 為加密欄位，顯示後 4 碼（****xxxx）。
            留空時沙箱自動 fallback 到 ECPay 公開測試憑證。
            <Button
              type="link"
              icon={<LinkOutlined />}
              href="https://vendor.ecpay.com.tw"
              target="_blank"
              style={{ padding: 0, marginLeft: 8 }}
            >
              前往 ECPay 後台取得憑證
            </Button>
          </span>
        }
        style={{ marginBottom: 20 }}
      />

      <Form form={form} layout="vertical">
        {/* 環境切換 */}
        <Card
          size="small"
          title={<span style={{ fontWeight: 600 }}>🌐 環境設定</span>}
          style={{ marginBottom: 16 }}
        >
          <Form.Item label="運行環境">
            <Select
              value={env}
              onChange={(v: 'sandbox' | 'production') => {
                setEnv(v)
                checkProdWarning(v)
              }}
              style={{ width: 200 }}
            >
              <Select.Option value="sandbox">
                <Tag color="blue">Sandbox</Tag> 沙箱測試
              </Select.Option>
              <Select.Option value="production">
                <Tag color="red">Production</Tag> 正式環境
              </Select.Option>
            </Select>
          </Form.Item>
        </Card>

        {/* 沙箱 */}
        <Card
          size="small"
          title={<span style={{ fontWeight: 600, color: '#16a34a' }}>🟢 沙箱環境憑證</span>}
          style={{ marginBottom: 16, border: '1.5px solid #bbf7d0' }}
          extra={<Text type="secondary" style={{ fontSize: 12 }}>留空自動使用 ECPay 公開測試值</Text>}
        >
          <Form.Item label="沙箱 MerchantID" name="ecpay_sandbox_merchant_id">
            <Input placeholder="2000132（ECPay 公開測試 ID）" />
          </Form.Item>
          <Form.Item label="沙箱 HashKey" name="ecpay_sandbox_hash_key">
            <Input.Password placeholder="留空 fallback 5294y06JbISpM5x9" visibilityToggle={false} />
          </Form.Item>
          <Form.Item label="沙箱 HashIV" name="ecpay_sandbox_hash_iv">
            <Input.Password placeholder="留空 fallback v77hoKGq4kWxNNIS" visibilityToggle={false} />
          </Form.Item>
        </Card>

        {/* 正式 */}
        <Card
          size="small"
          title={<span style={{ fontWeight: 600, color: '#dc2626' }}>🔴 正式環境憑證</span>}
          style={{ marginBottom: 16, border: '1.5px solid #fecaca' }}
          extra={<Text type="danger" style={{ fontSize: 12 }}>⚠️ 上線前必填</Text>}
        >
          <Form.Item label="正式 MerchantID" name="ecpay_production_merchant_id">
            <Input placeholder="從 ECPay 後台取得" />
          </Form.Item>
          <Form.Item label="正式 HashKey" name="ecpay_production_hash_key">
            <Input.Password placeholder="加密儲存，顯示後 4 碼" visibilityToggle={false} />
          </Form.Item>
          <Form.Item label="正式 HashIV" name="ecpay_production_hash_iv">
            <Input.Password placeholder="加密儲存，顯示後 4 碼" visibilityToggle={false} />
          </Form.Item>
        </Card>

        {/* 發票 */}
        <Card
          size="small"
          title={<span style={{ fontWeight: 600 }}>🧾 電子發票</span>}
          style={{ marginBottom: 16 }}
        >
          <Space align="center">
            <Switch
              checked={invoiceEnabled}
              onChange={setInvoiceEnabled}
              checkedChildren="啟用"
              unCheckedChildren="停用"
            />
            <Text>{invoiceEnabled ? '每筆付款將自動開立電子發票（需在 ECPay 後台完成發票設定）' : '電子發票功能已停用'}</Text>
          </Space>
        </Card>
      </Form>

      <Divider />

      <Button
        type="primary"
        icon={<SaveOutlined />}
        loading={saving}
        onClick={handleSave}
      >
        儲存金流設定
      </Button>
    </div>
  )
}
