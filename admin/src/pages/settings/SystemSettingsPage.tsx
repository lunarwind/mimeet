import { useState, useEffect, useCallback } from 'react'
<<<<<<< HEAD
import {
  Card,
  InputNumber,
  Button,
  Typography,
  Divider,
  message,
  Switch,
  Tabs,
  Radio,
  Alert,
  Modal,
  Input,
  Form,
  Tag,
  Space,
  Spin,
  Descriptions,
} from 'antd'
import {
  SaveOutlined,
  SettingOutlined,
  ToolOutlined,
  DatabaseOutlined,
  MailOutlined,
  MessageOutlined,
  ClearOutlined,
  SendOutlined,
  ExclamationCircleOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  LockOutlined,
  DollarOutlined,
} from '@ant-design/icons'
import apiClient from '../../api/client'

const { Title, Text } = Typography

// ─── Tab 1: System Parameters (existing content) ─────────────────────────────

=======
import { Tabs, Card, InputNumber, Button, Typography, Divider, Space, message, Switch, Modal, Input, Tag, Alert, Statistic, Row, Col, Table, Form, Select, Drawer, Checkbox } from 'antd'
import { SaveOutlined, DeleteOutlined, DatabaseOutlined, ReloadOutlined, PlusOutlined, DollarOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import { useAuthStore } from '../../stores/authStore'
import AppModeTab from './tabs/AppModeTab'
import DatabaseTab from './tabs/DatabaseTab'
import MailTab from './tabs/MailTab'
import SmsTab from './tabs/SmsTab'

const { Title, Text } = Typography

>>>>>>> develop
function SystemParamsTab() {
  const [qrGpsScore, setQrGpsScore] = useState(5)
  const [qrNoGpsScore, setQrNoGpsScore] = useState(2)
  const [reportDeduct, setReportDeduct] = useState(-15)
  const [noShowDeduct, setNoShowDeduct] = useState(-10)
  const [suspendThreshold, setSuspendThreshold] = useState(0)
  const [retentionDays, setRetentionDays] = useState(180)
  const [retentionSaving, setRetentionSaving] = useState(false)

<<<<<<< HEAD
  const [trialPrice, setTrialPrice] = useState(199)
  const [trialDays, setTrialDays] = useState(30)
  const [autoRenewDefault, setAutoRenewDefault] = useState(true)
=======
  useEffect(() => {
    apiClient.get('/admin/settings').then(res => {
      const s = res.data?.data?.settings
      if (s?.data_retention_days) setRetentionDays(Number(s.data_retention_days) || 180)
    }).catch(() => {})
  }, [])
>>>>>>> develop

  async function saveRetention() {
    setRetentionSaving(true)
    try {
      await apiClient.patch('/admin/settings', { settings: { data_retention_days: String(retentionDays) } })
      message.success('資料保留天數已更新')
    } catch { message.error('儲存失敗') }
    setRetentionSaving(false)
  }

  return (
    <div>
      <Card title="誠信分數規則" style={{ marginBottom: 24 }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, maxWidth: 600 }}>
<<<<<<< HEAD
          <div>
            <Text>QR 約會 GPS 通過得分</Text>
            <InputNumber
              value={qrGpsScore}
              onChange={(v) => setQrGpsScore(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              addonBefore="+"
            />
          </div>
          <div>
            <Text>QR 約會無 GPS 得分</Text>
            <InputNumber
              value={qrNoGpsScore}
              onChange={(v) => setQrNoGpsScore(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              addonBefore="+"
            />
          </div>
          <div>
            <Text>被檢舉扣分</Text>
            <InputNumber
              value={reportDeduct}
              onChange={(v) => setReportDeduct(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              max={0}
            />
          </div>
          <div>
            <Text>爽約扣分</Text>
            <InputNumber
              value={noShowDeduct}
              onChange={(v) => setNoShowDeduct(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              max={0}
            />
          </div>
          <div>
            <Text>停權門檻（分數 &le; 此值停權）</Text>
            <InputNumber
              value={suspendThreshold}
              onChange={(v) => setSuspendThreshold(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              min={0}
              max={30}
            />
          </div>
=======
          <div><Text>QR 約會 GPS 通過得分</Text><InputNumber value={qrGpsScore} onChange={(v) => setQrGpsScore(v || 0)} style={{ width: '100%', marginTop: 4 }} /></div>
          <div><Text>QR 約會無 GPS 得分</Text><InputNumber value={qrNoGpsScore} onChange={(v) => setQrNoGpsScore(v || 0)} style={{ width: '100%', marginTop: 4 }} /></div>
          <div><Text>被檢舉扣分</Text><InputNumber value={reportDeduct} onChange={(v) => setReportDeduct(v || 0)} style={{ width: '100%', marginTop: 4 }} max={0} /></div>
          <div><Text>爽約扣分</Text><InputNumber value={noShowDeduct} onChange={(v) => setNoShowDeduct(v || 0)} style={{ width: '100%', marginTop: 4 }} max={0} /></div>
          <div><Text>停權門檻</Text><InputNumber value={suspendThreshold} onChange={(v) => setSuspendThreshold(v || 0)} style={{ width: '100%', marginTop: 4 }} min={0} max={30} /></div>
>>>>>>> develop
        </div>
        <Divider />
        <Button type="primary" icon={<SaveOutlined />} onClick={() => message.success('已儲存')}>儲存</Button>
      </Card>

<<<<<<< HEAD
      <Card title="訂閱設定">
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, maxWidth: 600 }}>
          <div>
            <Text>新手體驗價</Text>
            <InputNumber
              value={trialPrice}
              onChange={(v) => setTrialPrice(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              addonBefore="NT$"
              min={0}
            />
          </div>
          <div>
            <Text>體驗方案天數</Text>
            <InputNumber
              value={trialDays}
              onChange={(v) => setTrialDays(v || 0)}
              style={{ width: '100%', marginTop: 4 }}
              addonAfter="天"
              min={1}
            />
          </div>
          <div>
            <Text>自動續訂預設開關</Text>
            <div style={{ marginTop: 4 }}>
              <Switch
                checked={autoRenewDefault}
                onChange={setAutoRenewDefault}
                checkedChildren="開"
                unCheckedChildren="關"
              />
            </div>
=======
      <Card title="資料保留政策（Data Retention）" style={{ marginBottom: 24 }}>
        <Alert type="info" message="超過保留期限的軟刪除訊息、隔離區檔案與用戶活動日誌將被每日排程永久清除。" showIcon style={{ marginBottom: 16 }} />
        <div style={{ maxWidth: 400 }}>
          <Text strong>資料物理銷毀期限</Text>
          <InputNumber
            value={retentionDays}
            onChange={(v) => setRetentionDays(v || 30)}
            min={30} max={730}
            style={{ width: '100%', marginTop: 4 }}
            addonAfter="天"
          />
          <div style={{ marginTop: 4 }}>
            <Text type="secondary" style={{ fontSize: 12 }}>
              建議值：180 天（約 6 個月）。最低 30 天，最高 730 天。
            </Text>
>>>>>>> develop
          </div>
        </div>
        <Divider />
        <Button type="primary" icon={<SaveOutlined />} onClick={saveRetention} loading={retentionSaving}>儲存保留政策</Button>
      </Card>

      <MemberLevelPermissionsCard />

      <DatasetManager />
    </div>
  )
}

function DatasetManager() {
  const [stats, setStats] = useState<{ is_clean: boolean; counts: Record<string, number> }>({ is_clean: true, counts: {} })
  const [loading, setLoading] = useState(false)
  const [freshMode, setFreshMode] = useState(true)

  const labels: Record<string, string> = {
    users: '用戶', conversations: '對話', messages: '訊息',
    date_invitations: '約會', orders: '訂單', subscriptions: '訂閱',
    reports: '回報', credit_score_histories: '分數記錄', notifications: '通知',
  }

  useEffect(() => { loadStats() }, [])

  async function loadStats() {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/settings/dataset/stats')
      setStats(res.data.data)
    } catch { /* ignore */ }
    setLoading(false)
  }

  function confirmAction(action: 'reset' | 'seed') {
    const title = action === 'reset' ? '確認清空資料庫' : '確認匯入測試資料集'
    const content = (
      <div>
        <Alert
          type={action === 'reset' ? 'error' : 'warning'}
          message={action === 'reset'
            ? '此操作將永久刪除所有業務資料（用戶、聊天、訂單等），僅保留管理員帳號和系統設定。'
            : `將匯入測試資料集（30 用戶、15 對話等）${freshMode ? '，會先清空現有資料' : ''}。`}
          showIcon style={{ marginBottom: 16 }}
        />
        <Text>請輸入管理員密碼確認：</Text>
        <Input.Password id="dataset-confirm-pw" style={{ marginTop: 8 }} placeholder="登入密碼" />
      </div>
    )

    Modal.confirm({
      title,
      content,
      okText: action === 'reset' ? '清空' : '匯入',
      okButtonProps: { danger: action === 'reset' },
      onOk: async () => {
        const pw = (document.getElementById('dataset-confirm-pw') as HTMLInputElement)?.value
        if (!pw) { message.error('請輸入密碼'); throw new Error('no password') }
        setLoading(true)
        try {
          if (action === 'reset') {
            await apiClient.post('/admin/settings/dataset/reset', { confirm_password: pw })
            message.success('資料庫已清空')
          } else {
            await apiClient.post('/admin/settings/dataset/seed', { fresh: freshMode, confirm_password: pw })
            message.success('測試資料集已匯入')
          }
          await loadStats()
        } catch (err: unknown) {
          const msg = (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message || '操作失敗'
          message.error(msg)
        }
        setLoading(false)
      },
    })
  }

  return (
    <Card title={<Space><DatabaseOutlined />測試資料集管理</Space>}>
      <Alert
        type={stats.is_clean ? 'success' : 'info'}
        message={stats.is_clean ? '資料庫目前為乾淨狀態 ✅' : '資料庫含有業務資料'}
        style={{ marginBottom: 16 }}
        showIcon
      />

      <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
        {Object.entries(stats.counts).map(([key, count]) => (
          <Col span={6} key={key}>
            <Statistic title={labels[key] || key} value={count} valueStyle={{ color: count === 0 ? '#9CA3AF' : '#111827', fontSize: 20 }} />
          </Col>
        ))}
      </Row>

      <Button icon={<ReloadOutlined />} onClick={loadStats} loading={loading} style={{ marginBottom: 16 }}>重新整理</Button>

      <Divider />

      <Space direction="vertical" size={16} style={{ width: '100%' }}>
        <Card size="small" style={{ background: '#FEF2F2', borderColor: '#FECACA' }}>
          <Space style={{ width: '100%', justifyContent: 'space-between' }}>
            <div>
              <Text strong>清空業務資料</Text>
              <br /><Text type="secondary" style={{ fontSize: 12 }}>刪除所有用戶、聊天、訂單。保留：管理員帳號、系統設定。</Text>
            </div>
            <Button danger icon={<DeleteOutlined />} onClick={() => confirmAction('reset')} disabled={loading || stats.is_clean}>
              清空資料庫
            </Button>
          </Space>
        </Card>

        <Card size="small" style={{ background: '#FEF3C7', borderColor: '#FDE68A' }}>
          <Space style={{ width: '100%', justifyContent: 'space-between' }}>
            <div>
              <Text strong>匯入測試資料集</Text>
              <br /><Text type="secondary" style={{ fontSize: 12 }}>匯入 30 個測試用戶、15 組對話、8 筆約會等。</Text>
              <br />
              <label style={{ fontSize: 12, cursor: 'pointer' }}>
                <input type="checkbox" checked={freshMode} onChange={(e) => setFreshMode(e.target.checked)} style={{ marginRight: 4 }} />
                先清空再匯入（Fresh 模式）
              </label>
            </div>
            <Button type="primary" style={{ background: '#F59E0B', borderColor: '#F59E0B' }} icon={<DatabaseOutlined />} onClick={() => confirmAction('seed')} disabled={loading}>
              匯入測試資料
            </Button>
          </Space>
        </Card>
      </Space>
    </Card>
  )
}

function PricingTab() {
  const [trialPrice, setTrialPrice] = useState(199)
  const [trialDays, setTrialDays] = useState(30)
  const [autoRenew, setAutoRenew] = useState(true)

  return (
    <Card title="訂閱設定">
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, maxWidth: 600 }}>
        <div><Text>體驗價</Text><InputNumber value={trialPrice} onChange={(v) => setTrialPrice(v || 0)} style={{ width: '100%', marginTop: 4 }} addonBefore="NT$" /></div>
        <div><Text>體驗天數</Text><InputNumber value={trialDays} onChange={(v) => setTrialDays(v || 0)} style={{ width: '100%', marginTop: 4 }} addonAfter="天" /></div>
        <div><Text>自動續訂預設</Text><div style={{ marginTop: 4 }}><Switch checked={autoRenew} onChange={setAutoRenew} checkedChildren="開" unCheckedChildren="關" /></div></div>
      </div>
      <Divider />
      <Button type="primary" icon={<SaveOutlined />} onClick={() => message.success('已儲存')}>儲存</Button>
    </Card>
  )
}

function MemberLevelPermissionsCard() {
  const LEVELS = [0, 1, 1.5, 2, 3]
  const LEVEL_LABELS: Record<number, string> = { 0: 'Lv0 一般', 1: 'Lv1 驗證', 1.5: 'Lv1.5 女驗證', 2: 'Lv2 進階', 3: 'Lv3 付費' }
  const FEATURES = [
    { key: 'browse', label: '瀏覽探索' },
    { key: 'basic_search', label: '基礎搜尋' },
    { key: 'advanced_search', label: '進階搜尋' },
    { key: 'daily_message_limit', label: '每日訊息額度', hasValue: true },
    { key: 'view_full_profile', label: '查看完整資料' },
    { key: 'post_moment', label: '發送動態', hasValue: true },
    { key: 'read_receipt', label: '已讀回執' },
    { key: 'qr_date', label: 'QR碼約會' },
    { key: 'vip_invisible', label: '隱身模式' },
    { key: 'broadcast', label: '廣播' },
  ]

  interface Perm { level: number; feature_key: string; enabled: boolean; value: string | null }
  const [perms, setPerms] = useState<Perm[]>([])
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    apiClient.get('/admin/settings/member-level-permissions').then(res => {
      setPerms(res.data.data.permissions)
    }).catch(() => {})
  }, [])

  function getPerm(level: number, key: string): Perm | undefined {
    return perms.find(p => Number(p.level) === level && p.feature_key === key)
  }

  function updatePerm(level: number, key: string, field: 'enabled' | 'value', val: boolean | string | null) {
    setPerms(prev => prev.map(p => {
      if (Number(p.level) === level && p.feature_key === key) {
        return { ...p, [field]: val }
      }
      return p
    }))
  }

  async function handleSave() {
    setSaving(true)
    try {
      const payload = perms.map(p => ({ level: p.level, feature_key: p.feature_key, enabled: p.enabled, value: p.value }))
      await apiClient.patch('/admin/settings/member-level-permissions', { permissions: payload })
      message.success('會員等級功能設定已儲存')
    } catch { message.error('儲存失敗') }
    setSaving(false)
  }

  const columns = [
    { title: '功能項目', dataIndex: 'label', key: 'label', width: 140, fixed: 'left' as const },
    ...LEVELS.map(lv => ({
      title: LEVEL_LABELS[lv] ?? `Lv${lv}`,
      key: `lv-${lv}`,
      width: 110,
      align: 'center' as const,
      render: (_: unknown, row: { key: string; hasValue?: boolean }) => {
        const perm = getPerm(lv, row.key)
        if (!perm) return <Tag color="default">-</Tag>
        if (row.hasValue) {
          return (
            <Space size={4} direction="vertical" style={{ alignItems: 'center' }}>
              <Checkbox checked={perm.enabled} onChange={e => updatePerm(lv, row.key, 'enabled', e.target.checked)} />
              <InputNumber size="small" value={perm.value ? Number(perm.value) : 0} onChange={v => updatePerm(lv, row.key, 'value', String(v ?? 0))}
                style={{ width: 60 }} disabled={!perm.enabled} min={0} />
            </Space>
          )
        }
        return <Checkbox checked={perm.enabled} onChange={e => updatePerm(lv, row.key, 'enabled', e.target.checked)} />
      },
    })),
  ]

  return (
    <Card title="會員等級功能權限矩陣" style={{ marginBottom: 24 }}>
      <Alert type="info" message="勾選代表該等級擁有此功能。額度欄位 0 代表無限。修改後儲存即時生效，同步更新 JSON 矩陣。" showIcon style={{ marginBottom: 16 }} />
      <Table dataSource={FEATURES} columns={columns} pagination={false} size="small" rowKey="key" scroll={{ x: 750 }} bordered />
      <Divider />
      <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={saving}>儲存權限矩陣</Button>
    </Card>
  )
}

function AdminsTab() {
  interface Admin { id: number; nickname: string; email: string; role: string; status: string; last_active_at: string | null; created_at: string }
  const [admins, setAdmins] = useState<Admin[]>([])
  const [loading, setLoading] = useState(false)
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [form] = Form.useForm()

  const loadAdmins = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/settings/admins')
      setAdmins(res.data.data.admins)
    } catch { /* ignore */ }
    setLoading(false)
  }, [])

  useEffect(() => { loadAdmins() }, [loadAdmins])

  async function handleCreate(values: { name: string; email: string; password: string; role: string }) {
    try {
      await apiClient.post('/admin/settings/admins', values)
      message.success('管理員已新增')
      setDrawerOpen(false)
      form.resetFields()
      loadAdmins()
    } catch { message.error('新增失敗') }
  }

  async function handleRoleChange(id: number, role: string) {
    try {
      await apiClient.patch(`/admin/settings/admins/${id}/role`, { role })
      message.success('角色已更新')
      loadAdmins()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '更新失敗'
      message.error(msg)
    }
  }

  const roleColors: Record<string, string> = { super_admin: 'red', admin: 'blue', cs: 'green' }

  const columns = [
    { title: '姓名', dataIndex: 'nickname', key: 'nickname' },
    { title: 'Email', dataIndex: 'email', key: 'email' },
    { title: '角色', dataIndex: 'role', key: 'role', render: (role: string) => <Tag color={roleColors[role] || 'default'}>{role}</Tag> },
    { title: '狀態', dataIndex: 'status', key: 'status', render: (s: string) => <Tag color={s === 'active' ? 'green' : 'red'}>{s}</Tag> },
    {
      title: '操作', key: 'actions', render: (_: unknown, record: Admin) => (
        <Select size="small" value={record.role} onChange={(v) => handleRoleChange(record.id, v)} style={{ width: 120 }}>
          <Select.Option value="super_admin">Super Admin</Select.Option>
          <Select.Option value="admin">Admin</Select.Option>
          <Select.Option value="cs">CS</Select.Option>
        </Select>
      ),
    },
  ]

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 16 }}>
        <Title level={5} style={{ margin: 0 }}>管理員帳號</Title>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => setDrawerOpen(true)}>新增管理員</Button>
      </div>
      <Table dataSource={admins} columns={columns} rowKey="id" loading={loading} pagination={false} size="small" />
      <Drawer title="新增管理員" open={drawerOpen} onClose={() => setDrawerOpen(false)} width={400}>
        <Form form={form} layout="vertical" onFinish={handleCreate}>
          <Form.Item name="name" label="姓名" rules={[{ required: true }]}><Input /></Form.Item>
          <Form.Item name="email" label="Email" rules={[{ required: true, type: 'email' }]}><Input /></Form.Item>
          <Form.Item name="password" label="初始密碼" rules={[{ required: true, min: 8 }]}><Input.Password /></Form.Item>
          <Form.Item name="role" label="角色" rules={[{ required: true }]}>
            <Select>
              <Select.Option value="super_admin">Super Admin</Select.Option>
              <Select.Option value="admin">Admin</Select.Option>
              <Select.Option value="cs">CS</Select.Option>
            </Select>
          </Form.Item>
          <Button type="primary" htmlType="submit" block>建立</Button>
        </Form>
      </Drawer>
    </div>
  )
}

function ECPayTab() {
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [mode, setMode] = useState('sandbox')
  const [payMid, setPayMid] = useState('')
  const [payKey, setPayKey] = useState('')
  const [payIv, setPayIv] = useState('')
  const [invMid, setInvMid] = useState('')
  const [invKey, setInvKey] = useState('')
  const [invIv, setInvIv] = useState('')
  const [invEnabled, setInvEnabled] = useState(false)
  const [loveCode, setLoveCode] = useState('168001')

  useEffect(() => {
    setLoading(true)
    apiClient.get('/admin/settings/ecpay')
      .then(res => {
        const s = res.data.data.settings
        setMode(s['mode']?.value ?? 'sandbox')
        setPayMid(s['payment.merchant_id']?.value ?? '')
        setPayKey(s['payment.hash_key']?.value ?? '')
        setPayIv(s['payment.hash_iv']?.value ?? '')
        setInvMid(s['invoice.merchant_id']?.value ?? '')
        setInvKey(s['invoice.hash_key']?.value ?? '')
        setInvIv(s['invoice.hash_iv']?.value ?? '')
        setInvEnabled(s['invoice.enabled']?.value === '1' || s['invoice.enabled']?.value === 'true')
        setLoveCode(s['invoice.donation_love_code']?.value ?? '168001')
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  async function handleSave() {
    setSaving(true)
    try {
      await apiClient.post('/admin/settings/ecpay', {
        settings: [
          { key: 'ecpay.mode', value: mode },
          { key: 'ecpay.payment.merchant_id', value: payMid },
          { key: 'ecpay.payment.hash_key', value: payKey },
          { key: 'ecpay.payment.hash_iv', value: payIv },
          { key: 'ecpay.invoice.merchant_id', value: invMid },
          { key: 'ecpay.invoice.hash_key', value: invKey },
          { key: 'ecpay.invoice.hash_iv', value: invIv },
          { key: 'ecpay.invoice.enabled', value: invEnabled ? '1' : '0' },
          { key: 'ecpay.invoice.donation_love_code', value: loveCode },
        ],
      })
      message.success('綠界設定已儲存')
    } catch {
      message.error('儲存失敗')
    }
    setSaving(false)
  }

  if (loading) return <Card loading />

  const isSandbox = mode === 'sandbox'

  return (
    <div>
      {/* Mode switch */}
      <Card
        title={<Space><DollarOutlined />環境設定</Space>}
        style={{ marginBottom: 24 }}
      >
        <Space size={16} align="center">
          <Text strong>目前環境：</Text>
          <Switch
            checked={!isSandbox}
            onChange={(v) => setMode(v ? 'production' : 'sandbox')}
            checkedChildren="正式"
            unCheckedChildren="測試"
          />
          {isSandbox ? (
            <Tag color="warning" style={{ fontSize: 13 }}>測試環境 — 交易不會真正扣款</Tag>
          ) : (
            <Tag color="success" style={{ fontSize: 13 }}>正式環境</Tag>
          )}
        </Space>
        {isSandbox && (
          <Alert
            type="warning"
            message="目前使用綠界測試環境（Sandbox），所有付款與發票操作不會產生真實交易。切換至正式環境前，請確認已填入正式金鑰。"
            showIcon
            style={{ marginTop: 16 }}
          />
        )}
      </Card>

      {/* Payment credentials */}
      <Card title="金流設定（全方位金流 API）" style={{ marginBottom: 24 }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 16, maxWidth: 900 }}>
          <div>
            <Text>MerchantID</Text>
            <Input value={payMid} onChange={e => setPayMid(e.target.value)} style={{ marginTop: 4 }} placeholder="3002607" />
          </div>
          <div>
            <Text>HashKey</Text>
            <Input.Password value={payKey} onChange={e => setPayKey(e.target.value)} style={{ marginTop: 4 }} placeholder="HashKey" />
          </div>
          <div>
            <Text>HashIV</Text>
            <Input.Password value={payIv} onChange={e => setPayIv(e.target.value)} style={{ marginTop: 4 }} placeholder="HashIV" />
          </div>
        </div>
      </Card>

      {/* Invoice credentials */}
      <Card
        title={
          <Space>
            電子發票設定（B2C 發票 API）
            <Switch size="small" checked={invEnabled} onChange={setInvEnabled} checkedChildren="啟用" unCheckedChildren="停用" />
          </Space>
        }
        style={{ marginBottom: 24, opacity: invEnabled ? 1 : 0.6 }}
      >
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 16, maxWidth: 900 }}>
          <div>
            <Text>MerchantID（發票）</Text>
            <Input value={invMid} onChange={e => setInvMid(e.target.value)} style={{ marginTop: 4 }} placeholder="2000132" disabled={!invEnabled} />
          </div>
          <div>
            <Text>HashKey（發票）</Text>
            <Input.Password value={invKey} onChange={e => setInvKey(e.target.value)} style={{ marginTop: 4 }} placeholder="HashKey" disabled={!invEnabled} />
          </div>
          <div>
            <Text>HashIV（發票）</Text>
            <Input.Password value={invIv} onChange={e => setInvIv(e.target.value)} style={{ marginTop: 4 }} placeholder="HashIV" disabled={!invEnabled} />
          </div>
        </div>
        <Divider />
        <div style={{ maxWidth: 300 }}>
          <Text>預設捐贈碼（愛心碼）</Text>
          <Input value={loveCode} onChange={e => setLoveCode(e.target.value)} style={{ marginTop: 4 }} placeholder="168001" disabled={!invEnabled} />
        </div>
      </Card>

      <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={saving} size="large">
        儲存綠界設定
      </Button>
    </div>
  )
}

export default function SystemSettingsPage() {
  const user = useAuthStore((s) => s.user)
  const isSuperAdmin = user?.role === 'super_admin'

  const superAdminTabs = ['mode', 'database', 'mail', 'sms', 'ecpay']

  const allTabs = [
    { key: 'admins', label: '管理員帳號', children: <AdminsTab /> },
    { key: 'mode', label: '系統模式', children: <AppModeTab /> },
    { key: 'database', label: '資料庫設定', children: <DatabaseTab /> },
    { key: 'mail', label: 'Email 設定', children: <MailTab /> },
    { key: 'sms', label: 'SMS 設定', children: <SmsTab /> },
    { key: 'ecpay', label: '金流與發票', children: <ECPayTab /> },
    { key: 'pricing', label: '訂閱方案', children: <PricingTab /> },
    { key: 'params', label: '系統參數', children: <SystemParamsTab /> },
  ].filter(tab => {
    if (superAdminTabs.includes(tab.key)) return isSuperAdmin
    return true
  })

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>系統設定</Title>
      <Tabs items={allTabs} />
    </div>
  )
}

// ─── Tab 2: App Mode ──────────────────────────────────────────────────────────

function AppModeTab() {
  const [mode, setMode] = useState<'normal' | 'maintenance'>('normal')
  const [loading, setLoading] = useState(false)
  const [fetching, setFetching] = useState(true)
  const [passwordModalOpen, setPasswordModalOpen] = useState(false)
  const [pendingMode, setPendingMode] = useState<'normal' | 'maintenance'>('normal')
  const [password, setPassword] = useState('')
  const [confirmLoading, setConfirmLoading] = useState(false)

  const fetchStatus = useCallback(async () => {
    try {
      const res = await apiClient.get('/admin/system/status')
      if (res.data?.data?.app_mode) {
        setMode(res.data.data.app_mode)
      }
    } catch {
      message.error('無法取得系統狀態')
    } finally {
      setFetching(false)
    }
  }, [])

  useEffect(() => {
    fetchStatus()
  }, [fetchStatus])

  const handleSave = () => {
    setPendingMode(mode)
    setPassword('')
    setPasswordModalOpen(true)
  }

  const handlePasswordConfirm = async () => {
    if (!password) {
      message.warning('請輸入密碼')
      return
    }
    setConfirmLoading(true)
    try {
      // Confirm password first
      await apiClient.post('/admin/auth/confirm-password', { password })

      // Then set mode
      setLoading(true)
      const res = await apiClient.post('/admin/system/mode', { mode: pendingMode })
      message.success(res.data?.message || '系統模式已更新')
      setPasswordModalOpen(false)
    } catch (err: any) {
      message.error(err.response?.data?.message || '操作失敗')
    } finally {
      setConfirmLoading(false)
      setLoading(false)
    }
  }

  if (fetching) {
    return (
      <div style={{ textAlign: 'center', padding: 48 }}>
        <Spin />
      </div>
    )
  }

  return (
    <div>
      <Card title="系統運行模式" style={{ maxWidth: 600 }}>
        <Radio.Group value={mode} onChange={(e) => setMode(e.target.value)}>
          <Radio.Button value="normal">正常模式</Radio.Button>
          <Radio.Button value="maintenance">維護模式</Radio.Button>
        </Radio.Group>

        {mode === 'maintenance' && (
          <Alert
            style={{ marginTop: 16 }}
            type="warning"
            showIcon
            icon={<ExclamationCircleOutlined />}
            message="維護模式警告"
            description="切換至維護模式後，一般使用者將無法存取系統。僅管理員可正常使用。請確認已通知使用者後再進行切換。"
          />
        )}

        <Divider />
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={loading}>
          儲存模式設定
        </Button>
      </Card>

      <Modal
        title={
          <span>
            <LockOutlined style={{ marginRight: 8 }} />
            密碼確認
          </span>
        }
        open={passwordModalOpen}
        onOk={handlePasswordConfirm}
        onCancel={() => setPasswordModalOpen(false)}
        confirmLoading={confirmLoading}
        okText="確認"
        cancelText="取消"
      >
        <p>此操作需要確認管理員密碼才能執行。</p>
        <Input.Password
          placeholder="請輸入管理員密碼"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          onPressEnter={handlePasswordConfirm}
          prefix={<LockOutlined />}
        />
      </Modal>
    </div>
  )
}

// ─── Tab 3: Database ──────────────────────────────────────────────────────────

function DatabaseTab() {
  const [status, setStatus] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [clearingCache, setClearingCache] = useState(false)

  const fetchStatus = useCallback(async () => {
    try {
      const res = await apiClient.get('/admin/system/status')
      setStatus(res.data?.data)
    } catch {
      message.error('無法取得系統狀態')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchStatus()
  }, [fetchStatus])

  const handleClearCache = async () => {
    setClearingCache(true)
    try {
      const res = await apiClient.post('/admin/system/cache-clear')
      message.success(res.data?.message || '快取已清除')
    } catch (err: any) {
      message.error(err.response?.data?.message || '清除快取失敗')
    } finally {
      setClearingCache(false)
    }
  }

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: 48 }}>
        <Spin />
      </div>
    )
  }

  return (
    <div>
      <Card title="資料庫連線資訊" style={{ marginBottom: 24 }}>
        <Descriptions column={1} bordered size="small">
          <Descriptions.Item label="DB Host">{status?.db_host || '-'}</Descriptions.Item>
          <Descriptions.Item label="DB Name">{status?.db_name || '-'}</Descriptions.Item>
          <Descriptions.Item label="連線狀態">
            {status?.db_status === 'connected' ? (
              <Tag icon={<CheckCircleOutlined />} color="success">
                連線正常
              </Tag>
            ) : (
              <Tag icon={<CloseCircleOutlined />} color="error">
                連線異常
              </Tag>
            )}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      <Card title="快取資訊" style={{ marginBottom: 24 }}>
        <Descriptions column={1} bordered size="small">
          <Descriptions.Item label="快取驅動">{status?.cache_driver || '-'}</Descriptions.Item>
          <Descriptions.Item label="PHP 版本">{status?.php_version || '-'}</Descriptions.Item>
          <Descriptions.Item label="Laravel 版本">{status?.laravel_version || '-'}</Descriptions.Item>
        </Descriptions>
        <Divider />
        <Button
          type="primary"
          danger
          icon={<ClearOutlined />}
          onClick={handleClearCache}
          loading={clearingCache}
        >
          清除快取
        </Button>
      </Card>
    </div>
  )
}

// ─── Tab 4: Mail ──────────────────────────────────────────────────────────────

function MailTab() {
  const [form] = Form.useForm()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testEmail, setTestEmail] = useState('')
  const [testSending, setTestSending] = useState(false)
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null)

  const fetchSettings = useCallback(async () => {
    try {
      const res = await apiClient.get('/admin/settings/mail')
      if (res.data?.data) {
        form.setFieldsValue(res.data.data)
      }
    } catch {
      message.error('無法取得 Email 設定')
    } finally {
      setLoading(false)
    }
  }, [form])

  useEffect(() => {
    fetchSettings()
  }, [fetchSettings])

  const handleSave = async () => {
    try {
      const values = await form.validateFields()
      setSaving(true)
      const res = await apiClient.patch('/admin/settings/mail', values)
      message.success(res.data?.message || 'Email 設定已更新')
    } catch (err: any) {
      if (err.errorFields) return // Validation error, Ant Design handles display
      message.error(err.response?.data?.message || '儲存失敗')
    } finally {
      setSaving(false)
    }
  }

  const handleSendTest = async () => {
    if (!testEmail) {
      message.warning('請輸入收件人信箱')
      return
    }
    setTestSending(true)
    setTestResult(null)
    try {
      const res = await apiClient.post('/admin/settings/mail/test', { recipient: testEmail })
      setTestResult({ success: true, message: res.data?.message || '測試信已發送' })
    } catch (err: any) {
      setTestResult({
        success: false,
        message: err.response?.data?.message || '發送失敗',
      })
    } finally {
      setTestSending(false)
    }
  }

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: 48 }}>
        <Spin />
      </div>
    )
  }

  return (
    <div>
      <Card title="SMTP 設定" style={{ marginBottom: 24 }}>
        <Form form={form} layout="vertical" style={{ maxWidth: 600 }}>
          <Form.Item
            name="host"
            label="SMTP Host"
            rules={[{ required: true, message: '請輸入 SMTP Host' }]}
          >
            <Input placeholder="smtp.example.com" />
          </Form.Item>
          <Form.Item
            name="port"
            label="Port"
            rules={[{ required: true, message: '請輸入 Port' }]}
          >
            <InputNumber style={{ width: '100%' }} min={1} max={65535} placeholder="587" />
          </Form.Item>
          <Form.Item
            name="username"
            label="Username"
            rules={[{ required: true, message: '請輸入 Username' }]}
          >
            <Input placeholder="user@example.com" />
          </Form.Item>
          <Form.Item name="password" label="Password">
            <Input.Password placeholder="SMTP 密碼" />
          </Form.Item>
          <Form.Item
            name="from_address"
            label="From Address"
            rules={[
              { required: true, message: '請輸入寄件人信箱' },
              { type: 'email', message: '請輸入有效的 Email 地址' },
            ]}
          >
            <Input placeholder="noreply@example.com" />
          </Form.Item>
          <Form.Item
            name="from_name"
            label="From Name"
            rules={[{ required: true, message: '請輸入寄件人名稱' }]}
          >
            <Input placeholder="MiMeet" />
          </Form.Item>
        </Form>
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={saving}>
          儲存 Email 設定
        </Button>
      </Card>

      <Card title="發送測試信">
        <Space.Compact style={{ maxWidth: 400, width: '100%' }}>
          <Input
            placeholder="收件人 Email"
            value={testEmail}
            onChange={(e) => setTestEmail(e.target.value)}
            onPressEnter={handleSendTest}
          />
          <Button
            type="primary"
            icon={<SendOutlined />}
            onClick={handleSendTest}
            loading={testSending}
          >
            發送測試信
          </Button>
        </Space.Compact>
        {testResult && (
          <Alert
            style={{ marginTop: 16, maxWidth: 400 }}
            type={testResult.success ? 'success' : 'error'}
            showIcon
            message={testResult.message}
          />
        )}
      </Card>
    </div>
  )
}

// ─── Tab 5: SMS ───────────────────────────────────────────────────────────────

function SmsTab() {
  const [provider, setProvider] = useState<'mitake' | 'twilio' | 'disabled'>('disabled')
  const [mitake, setMitake] = useState({ username: '', password: '' })
  const [twilio, setTwilio] = useState({ sid: '', auth_token: '', from_number: '' })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testPhone, setTestPhone] = useState('')
  const [testSending, setTestSending] = useState(false)
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null)

  const fetchSettings = useCallback(async () => {
    try {
      const res = await apiClient.get('/admin/settings/sms')
      if (res.data?.data) {
        const data = res.data.data
        setProvider(data.provider || 'disabled')
        if (data.mitake) setMitake(data.mitake)
        if (data.twilio) setTwilio(data.twilio)
      }
    } catch {
      message.error('無法取得 SMS 設定')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchSettings()
  }, [fetchSettings])

  const handleSave = async () => {
    setSaving(true)
    try {
      const res = await apiClient.patch('/admin/settings/sms', { provider, mitake, twilio })
      message.success(res.data?.message || 'SMS 設定已更新')
    } catch (err: any) {
      message.error(err.response?.data?.message || '儲存失敗')
    } finally {
      setSaving(false)
    }
  }

  const handleSendTest = async () => {
    if (!testPhone) {
      message.warning('請輸入測試電話號碼')
      return
    }
    setTestSending(true)
    setTestResult(null)
    try {
      const res = await apiClient.post('/admin/settings/sms/test', { phone: testPhone })
      setTestResult({ success: true, message: res.data?.message || '測試簡訊已發送' })
    } catch (err: any) {
      setTestResult({
        success: false,
        message: err.response?.data?.message || '發送失敗',
      })
    } finally {
      setTestSending(false)
    }
  }

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: 48 }}>
        <Spin />
      </div>
    )
  }

  return (
    <div>
      <Card title="SMS 服務設定" style={{ marginBottom: 24 }}>
        <div style={{ marginBottom: 16 }}>
          <Text strong>簡訊服務提供商</Text>
          <div style={{ marginTop: 8 }}>
            <Radio.Group value={provider} onChange={(e) => setProvider(e.target.value)}>
              <Radio.Button value="mitake">三竹 Mitake</Radio.Button>
              <Radio.Button value="twilio">Twilio</Radio.Button>
              <Radio.Button value="disabled">停用</Radio.Button>
            </Radio.Group>
          </div>
        </div>

        {provider === 'mitake' && (
          <Card type="inner" title="三竹 Mitake 設定" style={{ marginBottom: 16 }}>
            <div style={{ maxWidth: 400 }}>
              <div style={{ marginBottom: 12 }}>
                <Text>Username</Text>
                <Input
                  style={{ marginTop: 4 }}
                  value={mitake.username}
                  onChange={(e) => setMitake({ ...mitake, username: e.target.value })}
                  placeholder="Mitake 帳號"
                />
              </div>
              <div>
                <Text>Password</Text>
                <Input.Password
                  style={{ marginTop: 4 }}
                  value={mitake.password}
                  onChange={(e) => setMitake({ ...mitake, password: e.target.value })}
                  placeholder="Mitake 密碼"
                />
              </div>
            </div>
          </Card>
        )}

        {provider === 'twilio' && (
          <Card type="inner" title="Twilio 設定" style={{ marginBottom: 16 }}>
            <div style={{ maxWidth: 400 }}>
              <div style={{ marginBottom: 12 }}>
                <Text>Account SID</Text>
                <Input
                  style={{ marginTop: 4 }}
                  value={twilio.sid}
                  onChange={(e) => setTwilio({ ...twilio, sid: e.target.value })}
                  placeholder="Twilio Account SID"
                />
              </div>
              <div style={{ marginBottom: 12 }}>
                <Text>Auth Token</Text>
                <Input.Password
                  style={{ marginTop: 4 }}
                  value={twilio.auth_token}
                  onChange={(e) => setTwilio({ ...twilio, auth_token: e.target.value })}
                  placeholder="Twilio Auth Token"
                />
              </div>
              <div>
                <Text>From Number</Text>
                <Input
                  style={{ marginTop: 4 }}
                  value={twilio.from_number}
                  onChange={(e) => setTwilio({ ...twilio, from_number: e.target.value })}
                  placeholder="+1234567890"
                />
              </div>
            </div>
          </Card>
        )}

        <Divider />
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={saving}>
          儲存 SMS 設定
        </Button>
      </Card>

      <Card title="發送測試簡訊">
        <Space.Compact style={{ maxWidth: 400, width: '100%' }}>
          <Input
            placeholder="測試電話號碼"
            value={testPhone}
            onChange={(e) => setTestPhone(e.target.value)}
            onPressEnter={handleSendTest}
          />
          <Button
            type="primary"
            icon={<SendOutlined />}
            onClick={handleSendTest}
            loading={testSending}
          >
            發送測試簡訊
          </Button>
        </Space.Compact>
        {testResult && (
          <Alert
            style={{ marginTop: 16, maxWidth: 400 }}
            type={testResult.success ? 'success' : 'error'}
            showIcon
            message={testResult.message}
          />
        )}
      </Card>
    </div>
  )
}

// ─── Tab 6: ECPay ────────────────────────────────────────────────────────────

function ECPayTab() {
  const [merchantId, setMerchantId] = useState('')
  const [hashKey, setHashKey] = useState('')
  const [hashIv, setHashIv] = useState('')
  const [isSandbox, setIsSandbox] = useState(true)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  const fetchSettings = useCallback(async () => {
    try {
      const res = await apiClient.get('/admin/settings/ecpay')
      if (res.data?.data) {
        const d = res.data.data
        setMerchantId(d.merchant_id || '')
        setHashKey(d.hash_key || '')
        setHashIv(d.hash_iv || '')
        setIsSandbox(d.is_sandbox ?? true)
      }
    } catch {
      message.error('無法取得金流設定')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchSettings()
  }, [fetchSettings])

  const handleSave = async () => {
    setSaving(true)
    try {
      const res = await apiClient.patch('/admin/settings/ecpay', {
        merchant_id: merchantId,
        hash_key: hashKey,
        hash_iv: hashIv,
        is_sandbox: isSandbox,
      })
      message.success(res.data?.message || '金流設定已更新')
    } catch (err: any) {
      message.error(err.response?.data?.message || '儲存失敗')
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: 48 }}>
        <Spin />
      </div>
    )
  }

  return (
    <div>
      <Card title="綠界科技 ECPay 設定" style={{ marginBottom: 24, maxWidth: 600 }}>
        <div style={{ marginBottom: 16 }}>
          <Text strong>MerchantID（特店編號）</Text>
          <Input
            style={{ marginTop: 4 }}
            value={merchantId}
            onChange={(e) => setMerchantId(e.target.value)}
            placeholder="例如 3002607"
          />
        </div>
        <div style={{ marginBottom: 16 }}>
          <Text strong>HashKey</Text>
          <Input.Password
            style={{ marginTop: 4 }}
            value={hashKey}
            onChange={(e) => setHashKey(e.target.value)}
            placeholder="ECPay HashKey"
          />
        </div>
        <div style={{ marginBottom: 16 }}>
          <Text strong>HashIV</Text>
          <Input.Password
            style={{ marginTop: 4 }}
            value={hashIv}
            onChange={(e) => setHashIv(e.target.value)}
            placeholder="ECPay HashIV"
          />
        </div>
        <div style={{ marginBottom: 16 }}>
          <Text strong>沙箱模式（Sandbox）</Text>
          <div style={{ marginTop: 4 }}>
            <Switch
              checked={isSandbox}
              onChange={setIsSandbox}
              checkedChildren="測試"
              unCheckedChildren="正式"
            />
          </div>
          {isSandbox && (
            <Alert
              style={{ marginTop: 8 }}
              type="info"
              showIcon
              message="目前為沙箱測試模式，付款將導向 ECPay 測試環境"
            />
          )}
          {!isSandbox && (
            <Alert
              style={{ marginTop: 8 }}
              type="warning"
              showIcon
              icon={<ExclamationCircleOutlined />}
              message="正式模式：付款將使用真實金流，請確認設定正確"
            />
          )}
        </div>
        <Divider />
        <Space>
          <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={saving}>
            儲存金流設定
          </Button>
          <Button
            icon={<DollarOutlined />}
            onClick={() =>
              message.info('測試付款功能請至前台使用體驗方案進行測試付款')
            }
          >
            測試付款說明
          </Button>
        </Space>
      </Card>
    </div>
  )
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function SystemSettingsPage() {
  const tabItems = [
    {
      key: 'params',
      label: (
        <span>
          <SettingOutlined /> 系統參數
        </span>
      ),
      children: <SystemParamsTab />,
    },
    {
      key: 'mode',
      label: (
        <span>
          <ToolOutlined /> 系統模式
        </span>
      ),
      children: <AppModeTab />,
    },
    {
      key: 'database',
      label: (
        <span>
          <DatabaseOutlined /> 資料庫
        </span>
      ),
      children: <DatabaseTab />,
    },
    {
      key: 'mail',
      label: (
        <span>
          <MailOutlined /> Email
        </span>
      ),
      children: <MailTab />,
    },
    {
      key: 'sms',
      label: (
        <span>
          <MessageOutlined /> SMS
        </span>
      ),
      children: <SmsTab />,
    },
    {
      key: 'ecpay',
      label: (
        <span>
          <DollarOutlined /> 金流設定
        </span>
      ),
      children: <ECPayTab />,
    },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 24 }}>
        系統控制中心
      </Title>
      <Tabs defaultActiveKey="params" items={tabItems} />
    </div>
  )
}
