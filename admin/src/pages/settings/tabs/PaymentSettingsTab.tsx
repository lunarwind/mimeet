import { useState, useEffect, useCallback } from 'react'
import {
  Card, Input, Button, Alert, Divider, Space, Typography, Tag, Form,
  message, Switch, Modal, Tabs, Badge, Table, Select, InputNumber, Popconfirm,
} from 'antd'
import { SaveOutlined, ExperimentOutlined, WarningOutlined, LockOutlined, FileTextOutlined, ReloadOutlined } from '@ant-design/icons'
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

      {/* ── 電子發票字軌管理 ────────────────────────────────────────── */}
      {invoiceEnabled && <InvoiceWordManager isSandbox={isSandbox} />}

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

// ── 電子發票字軌管理 ─────────────────────────────────────────────────────
function InvoiceWordManager({ isSandbox }: { isSandbox: boolean }) {
  const rocYear     = new Date().getFullYear() - 1911
  const currentTerm = Math.ceil((new Date().getMonth() + 1) / 2)

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [words, setWords]       = useState<any[]>([])
  const [listLoading, setListLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [form] = Form.useForm()

  async function loadWords() {
    setListLoading(true)
    try {
      const res = await apiClient.get(
        `/admin/settings/ecpay/invoice-words?year=${rocYear}&term=${currentTerm}`,
      )
      const data = res.data.data
      // 綠界回應可能把清單放在 WordsList / InvoiceWordList 等欄位
      const list = data?.WordsList ?? data?.InvoiceWordList ?? (Array.isArray(data) ? data : [])
      setWords(list)
    } catch {
      // 查詢失敗不中斷主頁面
    }
    setListLoading(false)
  }

  useEffect(() => { loadWords() }, [])

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  async function handleSubmit(values: any) {
    setSubmitting(true)
    try {
      await apiClient.post('/admin/settings/ecpay/invoice-words', values)
      message.success('字軌新增並啟用成功')
      setModalOpen(false)
      form.resetFields()
      await loadWords()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: { message?: string } } } }
      message.error(e?.response?.data?.error?.message ?? '新增失敗')
    }
    setSubmitting(false)
  }

  async function toggleStatus(trackId: string, enabled: boolean) {
    try {
      await apiClient.patch(`/admin/settings/ecpay/invoice-words/${trackId}/status`, { enabled })
      message.success(enabled ? '已啟用' : '已停用')
      await loadWords()
    } catch {
      message.error('狀態更新失敗')
    }
  }

  return (
    <Card
      size="small"
      title={<Space><FileTextOutlined />電子發票字軌管理</Space>}
      style={{ marginTop: 20 }}
      extra={<Button size="small" icon={<ReloadOutlined />} onClick={loadWords} loading={listLoading}>重新整理</Button>}
    >
      {isSandbox ? (
        <Alert
          type="info" showIcon style={{ marginBottom: 12 }}
          message="Sandbox：字軌可任意設定"
          description={
            <span>
              字軌字母任意兩個英文字母（如 ZZ）。
              號碼每組 50 個，起號尾數 00 或 50，迄號尾數 49 或 99。
              期別不可小於當期（{rocYear} 年第 {currentTerm} 期）。
              RtnCode 5070350 = 字軌未設定或已用完 → 點「新增字軌」即可解決。
            </span>
          }
        />
      ) : (
        <Alert
          type="warning" showIcon style={{ marginBottom: 12 }}
          message="正式環境：字軌必須與財政部配號完全一致"
          description={
            <span>
              必須先向當地國稅局申請，至財政部電子發票整合服務平台取號（每兩個月配發）。
              收到配號後填入下方，否則發票無法上傳財政部對帳。
              {' '}<a href="https://www.einvoice.nat.gov.tw" target="_blank" rel="noreferrer">財政部電子發票整合平台</a>
            </span>
          }
        />
      )}

      <Button type="primary" size="small" onClick={() => setModalOpen(true)} style={{ marginBottom: 12 }}>
        新增字軌
      </Button>

      <Table
        dataSource={words}
        loading={listLoading}
        rowKey={(r) => r.TrackID ?? r.InvoiceHeader}
        size="small"
        pagination={false}
        locale={{ emptyText: '尚無字軌（RtnCode 5070350 表示需要新增）' }}
        columns={[
          { title: '期別', width: 120, render: (_, r) => `${r.InvoiceYear ?? ''}年第${r.InvoiceTerm ?? ''}期` },
          { title: '字軌', dataIndex: 'InvoiceHeader', width: 60 },
          { title: '起號', dataIndex: 'InvoiceStart', width: 100 },
          { title: '迄號', dataIndex: 'InvoiceEnd', width: 100 },
          { title: '已用', dataIndex: 'UseCount', width: 60 },
          {
            title: '狀態', width: 80,
            render: (_, r) => (
              <Tag color={r.InvoiceStatus === '1' ? 'green' : 'default'}>
                {r.InvoiceStatus === '1' ? '啟用中' : '未啟用'}
              </Tag>
            ),
          },
          {
            title: '切換',
            render: (_, r) => (
              <Popconfirm
                title={r.InvoiceStatus === '1' ? '確認停用此字軌？' : '確認啟用此字軌？'}
                onConfirm={() => toggleStatus(r.TrackID, r.InvoiceStatus !== '1')}
              >
                <Switch
                  checked={r.InvoiceStatus === '1'}
                  size="small"
                  checkedChildren="啟" unCheckedChildren="停"
                />
              </Popconfirm>
            ),
          },
        ]}
      />

      <Modal
        title="新增字軌"
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => form.submit()}
        okText="新增並啟用"
        confirmLoading={submitting}
        width={460}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSubmit}
          initialValues={{
            invoice_year: String(rocYear),
            invoice_term: currentTerm,
            header: 'ZZ',
            start: 12345000,
            end: 12345049,
          }}
        >
          <Form.Item name="invoice_year" label="民國年" rules={[{ required: true }]}>
            <Input placeholder={`例：${rocYear}`} style={{ width: 120 }} />
          </Form.Item>
          <Form.Item
            name="invoice_term" label="期別"
            rules={[{ required: true }]}
            extra="1=1-2月, 2=3-4月, 3=5-6月, 4=7-8月, 5=9-10月, 6=11-12月"
          >
            <Select style={{ width: 150 }} options={[1,2,3,4,5,6].map(t => ({ value: t, label: `第 ${t} 期` }))} />
          </Form.Item>
          <Form.Item
            name="header" label="字軌（兩個英文字母）"
            rules={[{ required: true, pattern: /^[A-Za-z]{2}$/, message: '必須為兩個英文字母' }]}
          >
            <Input maxLength={2} style={{ width: 80, textTransform: 'uppercase' }} placeholder="ZZ" />
          </Form.Item>
          <Form.Item name="start" label="起始號碼" rules={[{ required: true }]} extra="尾數必須為 00 或 50">
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="end" label="結束號碼" rules={[{ required: true }]} extra="尾數必須為 49 或 99">
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Modal>
    </Card>
  )
}
