import { useState, useEffect, useCallback } from 'react'
import {
  Card, Input, Button, Alert, Divider, Space, Typography, Tag, Form,
  message, Switch, Modal, Tabs, Badge,
} from 'antd'
import { SaveOutlined, ExperimentOutlined, WarningOutlined, LockOutlined } from '@ant-design/icons'
import apiClient from '../../../api/client'

const { Text, Title } = Typography

// ── 綠界官方測試憑證（供「快速填入」按鈕使用）───────────────────────
const ECPAY_SANDBOX_PAYMENT = { mid: '3002607', key: 'pwFHCqoQZGmho4w6', iv: 'EkRm7iFT261dpevs' }
const ECPAY_SANDBOX_INVOICE = { mid: '2000132', key: 'ejCk326UnaZWKisg', iv: 'q9jcZX8Ib9LM8wYk' }

interface EcpaySettings {
  ecpay_environment: 'sandbox' | 'production'
  ecpay_sandbox_merchant_id: string
  ecpay_sandbox_hash_key: string
  ecpay_sandbox_hash_iv: string
  ecpay_production_merchant_id: string
  ecpay_production_hash_key: string
  ecpay_production_hash_iv: string
  ecpay_invoice_enabled: string
  ecpay_invoice_donation_love_code: string
  ecpay_invoice_sandbox_merchant_id: string
  ecpay_invoice_sandbox_hash_key: string
  ecpay_invoice_sandbox_hash_iv: string
  ecpay_invoice_production_merchant_id: string
  ecpay_invoice_production_hash_key: string
  ecpay_invoice_production_hash_iv: string
}

export default function PaymentSettingsTab() {
  const [form] = Form.useForm()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [env, setEnv] = useState<'sandbox' | 'production'>('sandbox')
  const [invoiceEnabled, setInvoiceEnabled] = useState(false)

  // 密碼確認 Modal
  const [pwModalOpen, setPwModalOpen] = useState(false)
  const [pendingEnv, setPendingEnv] = useState<'production' | null>(null)
  const [confirmPassword, setConfirmPassword] = useState('')
  const [pwLoading, setPwLoading] = useState(false)
  const [pwError, setPwError] = useState('')

  const fetchSettings = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/settings/payment')
      const d: EcpaySettings = res.data.data ?? {}
      form.setFieldsValue(d)
      setEnv((d.ecpay_environment ?? 'sandbox') as 'sandbox' | 'production')
      setInvoiceEnabled(d.ecpay_invoice_enabled === '1')
    } catch {
      message.error('載入金流設定失敗')
    }
    setLoading(false)
  }, [form])

  useEffect(() => { fetchSettings() }, [fetchSettings])

  // ── 環境切換：sandbox → production 需密碼 ─────────────────────────
  function handleEnvChange(newEnv: 'sandbox' | 'production') {
    if (newEnv === 'production' && env === 'sandbox') {
      setPendingEnv('production')
      setConfirmPassword('')
      setPwError('')
      setPwModalOpen(true)
    } else {
      setEnv(newEnv)
    }
  }

  async function handlePasswordConfirm() {
    if (!confirmPassword) { setPwError('請輸入密碼'); return }
    setPwLoading(true)
    setPwError('')
    try {
      const vals = form.getFieldsValue()
      await apiClient.put('/admin/settings/payment', {
        ...vals,
        ecpay_environment: 'production',
        ecpay_invoice_enabled: invoiceEnabled ? '1' : '0',
        confirm_password: confirmPassword,
      })
      setEnv('production')
      message.success('已切換至正式環境，設定已儲存')
      setPwModalOpen(false)
      fetchSettings()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string; errors?: Record<string, string> } } }
      const errMsg = e?.response?.data?.errors?.confirm_password
        ?? e?.response?.data?.message
        ?? '密碼錯誤或憑證未填齊'
      setPwError(errMsg)
    }
    setPwLoading(false)
  }

  // ── 儲存（非環境切換情境）────────────────────────────────────────
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

  // ── 快速填入官方測試憑證 ────────────────────────────────────────
  function fillSandboxPaymentCredentials() {
    form.setFieldsValue({
      ecpay_sandbox_merchant_id: ECPAY_SANDBOX_PAYMENT.mid,
      ecpay_sandbox_hash_key: ECPAY_SANDBOX_PAYMENT.key,
      ecpay_sandbox_hash_iv: ECPAY_SANDBOX_PAYMENT.iv,
    })
    message.info('已填入綠界官方金流測試憑證，記得點「儲存」')
  }

  function fillSandboxInvoiceCredentials() {
    form.setFieldsValue({
      ecpay_invoice_sandbox_merchant_id: ECPAY_SANDBOX_INVOICE.mid,
      ecpay_invoice_sandbox_hash_key: ECPAY_SANDBOX_INVOICE.key,
      ecpay_invoice_sandbox_hash_iv: ECPAY_SANDBOX_INVOICE.iv,
    })
    message.info('已填入綠界官方發票測試憑證，記得點「儲存」')
  }

  if (loading) return <div style={{ padding: 40, textAlign: 'center' }}>載入中...</div>

  const isSandbox = env === 'sandbox'

  return (
    <div style={{ maxWidth: 760 }}>

      {/* ── 環境徽章 ─────────────────────────────────────────────── */}
      <Card
        size="small"
        style={{ marginBottom: 16, background: isSandbox ? '#fffbeb' : '#fef2f2', border: `1.5px solid ${isSandbox ? '#fde68a' : '#fecaca'}` }}
      >
        <Space align="center" style={{ width: '100%', justifyContent: 'space-between' }}>
          <Space>
            <Badge status={isSandbox ? 'warning' : 'error'} />
            <Title level={5} style={{ margin: 0 }}>
              目前環境：{isSandbox
                ? <Tag color="gold">Sandbox — 測試環境</Tag>
                : <Tag color="red">Production — 正式環境</Tag>
              }
            </Title>
          </Space>
          <Space>
            {!isSandbox && (
              <Button size="small" onClick={() => handleEnvChange('sandbox')}>
                切換回 Sandbox
              </Button>
            )}
            {isSandbox && (
              <Button size="small" danger icon={<WarningOutlined />} onClick={() => handleEnvChange('production')}>
                切換至 Production
              </Button>
            )}
          </Space>
        </Space>
        {!isSandbox && (
          <Alert
            type="error" showIcon
            style={{ marginTop: 10 }}
            message="正式環境警告"
            description="目前為正式環境，所有付款均為真實交易，請確認憑證正確。"
          />
        )}
      </Card>

      <Form form={form} layout="vertical">

        {/* ── 金流憑證 ─────────────────────────────────────────────── */}
        <Card
          size="small"
          title={<span style={{ fontWeight: 600 }}>💳 綠界金流憑證（ECPay AIO）</span>}
          style={{ marginBottom: 16 }}
        >
          <Tabs
            defaultActiveKey="sandbox"
            items={[
              {
                key: 'sandbox',
                label: <Tag color="blue">測試憑證</Tag>,
                children: (
                  <>
                    {isSandbox && (
                      <Alert
                        type="info" showIcon
                        style={{ marginBottom: 12 }}
                        message={
                          <Space>
                            <span>可使用綠界官方測試特店（MID: 3002607）</span>
                            <Button size="small" icon={<ExperimentOutlined />} onClick={fillSandboxPaymentCredentials}>
                              填入官方測試憑證
                            </Button>
                          </Space>
                        }
                      />
                    )}
                    <Form.Item label="測試 MerchantID" name="ecpay_sandbox_merchant_id">
                      <Input placeholder="3002607（綠界官方測試特店）" />
                    </Form.Item>
                    <Form.Item label="測試 HashKey" name="ecpay_sandbox_hash_key">
                      <Input.Password placeholder="顯示後 4 碼（****xxxx）" visibilityToggle={false} />
                    </Form.Item>
                    <Form.Item label="測試 HashIV" name="ecpay_sandbox_hash_iv">
                      <Input.Password placeholder="顯示後 4 碼（****xxxx）" visibilityToggle={false} />
                    </Form.Item>
                    <Text type="secondary" style={{ fontSize: 12 }}>
                      測試卡號：4311-9511-1111-1111　3D 驗證碼：1234
                    </Text>
                  </>
                ),
              },
              {
                key: 'production',
                label: <Tag color="red">正式憑證</Tag>,
                children: (
                  <>
                    <Alert
                      type="warning" showIcon
                      message="正式憑證上線前必填"
                      description="切換至 Production 環境時，這三個欄位不可為空"
                      style={{ marginBottom: 12 }}
                    />
                    <Form.Item label="正式 MerchantID" name="ecpay_production_merchant_id">
                      <Input placeholder="從綠界後台 vendor.ecpay.com.tw 取得" />
                    </Form.Item>
                    <Form.Item label="正式 HashKey" name="ecpay_production_hash_key">
                      <Input.Password placeholder="加密儲存，顯示後 4 碼" visibilityToggle={false} />
                    </Form.Item>
                    <Form.Item label="正式 HashIV" name="ecpay_production_hash_iv">
                      <Input.Password placeholder="加密儲存，顯示後 4 碼" visibilityToggle={false} />
                    </Form.Item>
                  </>
                ),
              },
            ]}
          />
        </Card>

        {/* ── 電子發票 ─────────────────────────────────────────────── */}
        <Card
          size="small"
          title={<span style={{ fontWeight: 600 }}>🧾 電子發票</span>}
          style={{ marginBottom: 16 }}
        >
          <Form.Item label="啟用電子發票">
            <Space>
              <Switch
                checked={invoiceEnabled}
                onChange={setInvoiceEnabled}
                checkedChildren="啟用"
                unCheckedChildren="停用"
              />
              <Text type="secondary">
                {invoiceEnabled ? '每筆訂閱/點數付款成功後自動開立電子發票' : '目前停用'}
              </Text>
            </Space>
          </Form.Item>

          {invoiceEnabled && isSandbox && (
            <Alert
              type="info" showIcon
              style={{ marginBottom: 12 }}
              message="Sandbox 發票說明"
              description="測試環境發票會在綠界系統開立並通過驗核，但不會實際上傳財政部（直接壓「已上傳」狀態）。可用於完整生命週期測試。"
            />
          )}

          {invoiceEnabled && (
            <>
              <Form.Item label="捐贈愛心碼" name="ecpay_invoice_donation_love_code">
                <Input placeholder="168001" style={{ width: 200 }} />
              </Form.Item>

              <Tabs
                defaultActiveKey="sandbox"
                items={[
                  {
                    key: 'sandbox',
                    label: <Tag color="blue">測試發票憑證</Tag>,
                    children: (
                      <>
                        {isSandbox && (
                          <Alert
                            type="info" showIcon
                            style={{ marginBottom: 12 }}
                            message={
                              <Space>
                                <span>可使用綠界官方發票測試特店（MID: 2000132）</span>
                                <Button size="small" icon={<ExperimentOutlined />} onClick={fillSandboxInvoiceCredentials}>
                                  填入官方測試憑證
                                </Button>
                              </Space>
                            }
                          />
                        )}
                        <Form.Item label="測試 MerchantID" name="ecpay_invoice_sandbox_merchant_id">
                          <Input placeholder="2000132（綠界官方發票測試特店）" />
                        </Form.Item>
                        <Form.Item label="測試 HashKey" name="ecpay_invoice_sandbox_hash_key">
                          <Input.Password placeholder="顯示後 4 碼" visibilityToggle={false} />
                        </Form.Item>
                        <Form.Item label="測試 HashIV" name="ecpay_invoice_sandbox_hash_iv">
                          <Input.Password placeholder="顯示後 4 碼" visibilityToggle={false} />
                        </Form.Item>
                        <Text type="secondary" style={{ fontSize: 12 }}>
                          測試後台：https://vendor-stage.ecpay.com.tw　帳號：Stagetest1234 / test1234
                        </Text>
                      </>
                    ),
                  },
                  {
                    key: 'production',
                    label: <Tag color="red">正式發票憑證</Tag>,
                    children: (
                      <>
                        <Alert
                          type="warning" showIcon
                          message="發票正式憑證上線前必填（若已啟用發票）"
                          style={{ marginBottom: 12 }}
                        />
                        <Form.Item label="正式 MerchantID" name="ecpay_invoice_production_merchant_id">
                          <Input placeholder="從綠界後台取得（與金流 MerchantID 不同）" />
                        </Form.Item>
                        <Form.Item label="正式 HashKey" name="ecpay_invoice_production_hash_key">
                          <Input.Password placeholder="加密儲存，顯示後 4 碼" visibilityToggle={false} />
                        </Form.Item>
                        <Form.Item label="正式 HashIV" name="ecpay_invoice_production_hash_iv">
                          <Input.Password placeholder="加密儲存，顯示後 4 碼" visibilityToggle={false} />
                        </Form.Item>
                      </>
                    ),
                  },
                ]}
              />
            </>
          )}
        </Card>

      </Form>

      <Divider />

      <Button
        type="primary"
        icon={<SaveOutlined />}
        loading={saving}
        onClick={handleSave}
      >
        儲存設定
      </Button>
      <Text type="secondary" style={{ marginLeft: 12, fontSize: 12 }}>
        儲存後下一筆交易即套用（環境切換立即生效）
      </Text>

      {/* ── 切換至 Production 密碼確認 Modal ────────────────────────── */}
      <Modal
        title={<Space><LockOutlined style={{ color: '#dc2626' }} /><span>切換至正式環境 — 需要密碼確認</span></Space>}
        open={pwModalOpen}
        onCancel={() => { setPwModalOpen(false); setPendingEnv(null) }}
        onOk={handlePasswordConfirm}
        okText="確認切換"
        okButtonProps={{ danger: true, loading: pwLoading }}
        cancelText="取消"
      >
        <Alert
          type="error" showIcon
          message="⚠️ 切換後所有付款均為真實交易"
          description="正式環境將向用戶真實收款。請確認正式憑證已填入且金鑰正確。"
          style={{ marginBottom: 16 }}
        />
        <p>請輸入您的管理員密碼以確認切換：</p>
        <Input.Password
          value={confirmPassword}
          onChange={e => { setConfirmPassword(e.target.value); setPwError('') }}
          onPressEnter={handlePasswordConfirm}
          placeholder="管理員密碼"
          status={pwError ? 'error' : undefined}
        />
        {pwError && <Text type="danger" style={{ display: 'block', marginTop: 4 }}>{pwError}</Text>}
        {pendingEnv && (
          <Text type="secondary" style={{ display: 'block', marginTop: 8, fontSize: 12 }}>
            切換後若要回到 Sandbox 只需點「切換回 Sandbox」按鈕，無需密碼。
          </Text>
        )}
      </Modal>
    </div>
  )
}
