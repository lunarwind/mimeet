import { useState, useEffect, useCallback, useRef } from 'react'
import { Tabs, Card, InputNumber, Button, Typography, Divider, Space, message, Modal, Input, Tag, Alert, Statistic, Row, Col, Table, Form, Select, Drawer, Checkbox, Popconfirm, Tooltip, Collapse } from 'antd'
import { SaveOutlined, DeleteOutlined, DatabaseOutlined, ReloadOutlined, PlusOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import { useAuthStore } from '../../stores/authStore'
import AppModeTab from './tabs/AppModeTab'
import DatabaseTab from './tabs/DatabaseTab'
import MailTab from './tabs/MailTab'
import SmsTab from './tabs/SmsTab'
import CreditScoreTab from './tabs/CreditScoreTab'
import PaymentSettingsTab from './tabs/PaymentSettingsTab'

const { Title, Text } = Typography

// F40 點數消費設定 key 清單
const POINT_SETTING_KEYS = [
  'point_cost_stealth',
  'point_cost_reverse_msg',
  'point_cost_super_like',
  'point_cost_broadcast_per_user',
  'broadcast_user_daily_limit',
  'broadcast_user_max_recipients',
  'stealth_duration_hours',
] as const

type PointSettingKey = typeof POINT_SETTING_KEYS[number]

const POINT_SETTING_META: Record<PointSettingKey, { label: string; suffix?: string; min: number; max?: number }> = {
  point_cost_stealth:            { label: '隱身模式（點/24h）',   suffix: '點', min: 1, max: 999 },
  point_cost_reverse_msg:        { label: '逆區間訊息（點/則）', suffix: '點', min: 1, max: 999 },
  point_cost_super_like:         { label: '超級讚（點/次）',      suffix: '點', min: 1, max: 999 },
  point_cost_broadcast_per_user: { label: '廣播每人（點/人）',   suffix: '點', min: 1, max: 999 },
  broadcast_user_daily_limit:    { label: '用戶每日廣播次數',    suffix: '次', min: 1, max: 99 },
  broadcast_user_max_recipients: { label: '每次廣播最多人數',    suffix: '人', min: 1, max: 500 },
  stealth_duration_hours:        { label: '隱身持續時數',        suffix: '小時', min: 1, max: 168 },
}

function SystemParamsTab() {
  const [retentionDays, setRetentionDays] = useState(180)
  const [retentionSaving, setRetentionSaving] = useState(false)

  // F40 點數消費設定（7 個）
  const [pointSettings, setPointSettings] = useState<Record<string, number>>({})
  const debounceTimers = useRef<Record<string, ReturnType<typeof setTimeout>>>({})

  useEffect(() => {
    apiClient.get('/admin/settings').then(res => {
      const s = res.data?.data?.settings
      if (s?.data_retention_days) setRetentionDays(Number(s.data_retention_days) || 180)
      // 把 7 個點數設定從整包 settings 撈出
      const next: Record<string, number> = {}
      for (const k of POINT_SETTING_KEYS) {
        if (s?.[k] !== undefined) next[k] = Number(s[k])
      }
      setPointSettings(next)
    }).catch(() => {})
  }, [])

  function updatePointSetting(key: PointSettingKey, value: number | null) {
    const v = value ?? 0
    setPointSettings(prev => ({ ...prev, [key]: v }))
    if (debounceTimers.current[key]) clearTimeout(debounceTimers.current[key])
    debounceTimers.current[key] = setTimeout(async () => {
      try {
        await apiClient.patch('/admin/settings', { settings: { [key]: String(v) } })
        message.success(`${POINT_SETTING_META[key].label} 已更新`, 1.5)
      } catch { message.error('儲存失敗') }
    }, 300)
  }

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
          </div>
        </div>
        <Divider />
        <Button type="primary" icon={<SaveOutlined />} onClick={saveRetention} loading={retentionSaving}>儲存保留政策</Button>
      </Card>

      <Card title="💎 點數消費設定（F40）" style={{ marginBottom: 24 }}>
        <Alert
          type="info"
          message="修改即時儲存（debounce 300ms）。影響範圍：隱身 / 逆區間訊息 / 超級讚 / 廣播的點數單價與每日上限。"
          showIcon
          style={{ marginBottom: 16 }}
        />
        <Row gutter={[16, 12]} style={{ maxWidth: 820 }}>
          {POINT_SETTING_KEYS.map((k) => {
            const meta = POINT_SETTING_META[k]
            return (
              <Col xs={24} sm={12} md={8} key={k}>
                <Text>{meta.label}</Text>
                <InputNumber
                  value={pointSettings[k] ?? 0}
                  onChange={(v) => updatePointSetting(k, v as number | null)}
                  min={meta.min}
                  max={meta.max}
                  style={{ width: '100%', marginTop: 4 }}
                  addonAfter={meta.suffix}
                />
              </Col>
            )
          })}
        </Row>
      </Card>

      <MemberLevelPermissionsCard />

      <DatasetManager />
    </div>
  )
}

const SUPER_ADMIN_EMAIL = 'chuck@lunarwind.org'

function DatasetManager() {
  const adminUser = useAuthStore((s) => s.user)
  const isSuperAdminChuck = adminUser?.email === SUPER_ADMIN_EMAIL

  const [stats, setStats] = useState<{ is_clean: boolean; counts: Record<string, number> }>({ is_clean: true, counts: {} })
  const [loading, setLoading] = useState(false)

  const labels: Record<string, string> = {
    users: '用戶', conversations: '對話', messages: '訊息',
    date_invitations: '約會', orders: '訂單', subscriptions: '訂閱',
    point_orders: '點數訂單', point_transactions: '點數交易',
    payments: '金流', credit_card_verifications: '卡驗證',
    reports: '檢舉', report_followups: '檢舉跟進', report_images: '檢舉圖片',
    credit_score_histories: '分數記錄', notifications: '通知', fcm_tokens: 'FCM Token',
    user_profile_visits: '足跡', user_follows: '追蹤', user_blocks: '封鎖',
    user_activity_logs: '活動日誌', user_verifications: '驗證紀錄',
    user_broadcasts: '廣播訊息', broadcast_campaigns: '廣播活動',
    registration_blacklists: '註冊黑名單', phone_change_histories: '手機變更紀錄',
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

  function confirmReset() {
    const content = (
      <div>
        <Alert
          type="error"
          message="此操作將永久刪除所有業務資料（用戶、聊天、訂單等），僅保留管理員帳號和系統設定。"
          showIcon style={{ marginBottom: 16 }}
        />
        <Text>請輸入管理員密碼確認：</Text>
        <Input.Password id="dataset-confirm-pw" style={{ marginTop: 8 }} placeholder="登入密碼" />
      </div>
    )

    Modal.confirm({
      title: '確認清空資料庫',
      content,
      okText: '清空',
      okButtonProps: { danger: true },
      onOk: async () => {
        const pw = (document.getElementById('dataset-confirm-pw') as HTMLInputElement)?.value
        if (!pw) { message.error('請輸入密碼'); throw new Error('no password') }
        setLoading(true)
        try {
          await apiClient.post('/admin/settings/dataset/reset', { confirm_password: pw })
          message.success('資料庫已清空，2 秒後跳轉至登入頁...')
          // reset 會 truncate personal_access_tokens → chuck token 也失效，必須重新登入
          setTimeout(() => {
            useAuthStore.getState().logout?.()
            window.location.href = '/admin/login'
          }, 2000)
        } catch (err: unknown) {
          const msg = (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message || '操作失敗'
          message.error(msg)
          setLoading(false)
        }
      },
    })
  }

  return (
    <Card title={<Space><DatabaseOutlined />業務資料統計</Space>}>
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
              <br /><Text type="secondary" style={{ fontSize: 12 }}>
                刪除所有用戶、聊天、訂單、admin_users（僅保留 {SUPER_ADMIN_EMAIL}）。
                保留：系統設定、訂閱方案。重建：uid=1 官方帳號。
              </Text>
              {!isSuperAdminChuck && (
                <><br /><Text type="danger" style={{ fontSize: 11 }}>⚠️ 僅 {SUPER_ADMIN_EMAIL} 可執行</Text></>
              )}
            </div>
            <Tooltip title={!isSuperAdminChuck ? `僅 ${SUPER_ADMIN_EMAIL} 可執行此操作` : undefined}>
              <Button
                danger
                icon={<DeleteOutlined />}
                onClick={confirmReset}
                disabled={loading || stats.is_clean || !isSuperAdminChuck}
              >
                清空資料庫
              </Button>
            </Tooltip>
          </Space>
        </Card>
      </Space>
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
  const [resetModalOpen, setResetModalOpen] = useState(false)
  const [resetTarget, setResetTarget] = useState<Admin | null>(null)
  const [resetForm] = Form.useForm()
  const [resetLoading, setResetLoading] = useState(false)

  const loadAdmins = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/settings/admins')
      setAdmins(res.data?.data?.admins ?? [])
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

  async function handleDelete(id: number) {
    try {
      await apiClient.delete(`/admin/settings/admins/${id}`)
      message.success('管理員已刪除')
      loadAdmins()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '刪除失敗'
      message.error(msg)
    }
  }

  async function handleResetPassword() {
    try {
      const values = await resetForm.validateFields()
      setResetLoading(true)
      await apiClient.post(`/admin/settings/admins/${resetTarget?.id}/reset-password`, {
        password: values.password,
        password_confirmation: values.password_confirmation,
      })
      message.success('密碼已重設')
      setResetModalOpen(false)
      resetForm.resetFields()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '重設失敗'
      message.error(msg)
    } finally {
      setResetLoading(false)
    }
  }

  const roleColors: Record<string, string> = { super_admin: 'red', admin: 'blue', cs: 'green' }

  const columns = [
    { title: '姓名', dataIndex: 'nickname', key: 'nickname' },
    { title: 'Email', dataIndex: 'email', key: 'email' },
    { title: '角色', dataIndex: 'role', key: 'role', render: (role: string) => <Tag color={roleColors[role] || 'default'}>{role}</Tag> },
    { title: '狀態', dataIndex: 'status', key: 'status', render: (s: string) => <Tag color={s === 'active' ? 'green' : 'red'}>{s}</Tag> },
    {
      title: '操作', key: 'actions', width: 280, render: (_: unknown, record: Admin) => (
        <Space>
          <Select size="small" value={record.role} onChange={(v) => handleRoleChange(record.id, v)} style={{ width: 120 }}>
            <Select.Option value="super_admin">Super Admin</Select.Option>
            <Select.Option value="admin">Admin</Select.Option>
            <Select.Option value="cs">CS</Select.Option>
          </Select>
          <Button size="small" onClick={() => { setResetTarget(record); resetForm.resetFields(); setResetModalOpen(true) }}>重設密碼</Button>
          {record.id !== 1 && (
            <Popconfirm title={`確定要刪除管理員「${record.nickname}」嗎？`} description="此操作無法復原。"
              onConfirm={() => handleDelete(record.id)} okText="確定刪除" okButtonProps={{ danger: true }} cancelText="取消">
              <Button size="small" danger>刪除</Button>
            </Popconfirm>
          )}
        </Space>
      ),
    },
  ]

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 16 }}>
        <Title level={5} style={{ margin: 0 }}>管理員帳號</Title>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => setDrawerOpen(true)}>新增管理員</Button>
      </div>
      {/* 角色權限說明 — 預設收合，點擊展開 */}
      <Collapse
        style={{ marginBottom: 16 }}
        items={[{
          key: 'role-permissions',
          label: '各角色權限設計意圖（後端實作持續完善中）',
          children: (
            <div>
              <Text type="secondary" style={{ display: 'block', marginBottom: 12 }}>
                以下表格為 <Tag color="red">super_admin 超級管理員</Tag>
                <Tag color="blue">admin 一般管理員</Tag>
                <Tag color="green">cs 客服人員</Tag>
                三種角色的<strong>設計意圖</strong>。
                後端 RBAC 實作目前仍在補齊中，部分路由細粒度控制尚未完整生效，
                實際行為以系統現況為準。如發現「應限制但仍可操作」情況，請通報技術負責人。
              </Text>
              <Table
                size="small"
                pagination={false}
                bordered
                style={{ marginBottom: 12 }}
                columns={[
                  { title: '功能模組', dataIndex: 'module', key: 'module', width: 160 },
                  { title: '查看', dataIndex: 'view', key: 'view', width: 130 },
                  { title: '新增', dataIndex: 'create', key: 'create', width: 100 },
                  { title: '編輯 / 調整', dataIndex: 'edit', key: 'edit', width: 160 },
                  { title: '刪除 / 停權', dataIndex: 'del', key: 'del', width: 160 },
                  { title: '備註', dataIndex: 'note', key: 'note' },
                ]}
                dataSource={[
                  { key: '1', module: '會員管理', view: 'super/admin/cs', create: '—', edit: 'super/admin（停權/調分）', del: 'super（刪帳）admin（停權）', note: 'cs 僅查看' },
                  { key: '2', module: '舉報 / 申訴處理', view: 'super/admin/cs', create: '—', edit: 'super/admin/cs（處理）', del: 'super/admin', note: 'cs 可處理回報' },
                  { key: '3', module: '聊天記錄', view: 'super/admin', create: '—', edit: '—', del: '—', note: 'cs 不可存取' },
                  { key: '4', module: '支付記錄', view: 'super/admin', create: '—', edit: '—', del: 'super（退款/補發票）', note: 'cs 不可存取' },
                  { key: '5', module: '系統設定（方案定價）', view: 'super/admin', create: '—', edit: 'super/admin', del: '—', note: '訂閱/點數方案' },
                  { key: '6', module: '系統設定（其他）', view: 'super', create: '—', edit: 'super', del: '—', note: '僅 super 可存取' },
                  { key: '7', module: 'SEO / 廣告連結', view: 'super/admin', create: 'super/admin', edit: 'super/admin', del: '—', note: 'cs 不可存取' },
                  { key: '8', module: '廣播 / 系統公告', view: 'super/admin', create: 'super/admin', edit: 'super/admin', del: 'super/admin', note: 'cs 不可存取' },
                  { key: '9', module: '後台操作日誌', view: 'super/admin', create: '—', edit: '—', del: '—', note: 'cs 不可存取' },
                  { key: '10', module: '管理員帳號管理', view: 'super', create: 'super', edit: 'super（角色調整）', del: 'super（非自己）', note: '此頁面本身須 super 才可見' },
                ]}
              />
              <Text type="secondary" style={{ fontSize: 12 }}>
                ⚠️ 本表為角色設計意圖。部分權限規則尚未在後端完整生效，實際操作以系統實際行為為準。
                詳見 <code>docs/audits/audit-F-20260423.md</code> F-001 觀察項。
                <br />
                權限說明版本：2026-04-30 · 依據：AdminPermissionsSeeder.php + 路由 middleware 實際檢視
              </Text>
            </div>
          ),
        }]}
      />
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
      <Modal title={`重設密碼：${resetTarget?.nickname ?? ''}`} open={resetModalOpen}
        onOk={handleResetPassword} onCancel={() => { setResetModalOpen(false); resetForm.resetFields() }}
        confirmLoading={resetLoading} okText="確認重設" cancelText="取消" destroyOnClose>
        <Form form={resetForm} layout="vertical" style={{ marginTop: 16 }}>
          <Form.Item name="password" label="新密碼" rules={[{ required: true, message: '請輸入新密碼' }, { min: 8, message: '密碼至少 8 個字元' }]}>
            <Input.Password placeholder="至少 8 個字元" />
          </Form.Item>
          <Form.Item name="password_confirmation" label="確認新密碼" dependencies={['password']}
            rules={[{ required: true, message: '請再次輸入' },
              ({ getFieldValue }) => ({ validator(_, value) {
                return !value || getFieldValue('password') === value ? Promise.resolve() : Promise.reject(new Error('兩次密碼不一致'))
              }})
            ]}>
            <Input.Password placeholder="再次輸入新密碼" />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}

// 舊版 ECPayTab（dot-notation key 格式）已移除（Step cleanup）
// 統一使用 PaymentSettingsTab（新格式 ecpay_* key）

export default function SystemSettingsPage() {
  const user = useAuthStore((s) => s.user)
  const isSuperAdmin = user?.role === 'super_admin'

  const superAdminTabs = ['mode', 'database', 'mail', 'sms', 'ecpay', 'credit-score']

  const allTabs = [
    { key: 'admins', label: '管理員帳號', children: <AdminsTab />, forceRender: true },
    { key: 'mode', label: '系統模式', children: <AppModeTab />, forceRender: true },
    { key: 'database', label: '資料庫設定', children: <DatabaseTab />, forceRender: true },
    { key: 'mail', label: 'Email 設定', children: <MailTab />, forceRender: true },
    { key: 'sms', label: 'SMS 設定', children: <SmsTab />, forceRender: true },
    // ecpay tab 現在使用新版 PaymentSettingsTab（移除舊 dot-notation 版本）
    { key: 'ecpay', label: '💳 金流與發票', children: <PaymentSettingsTab />, forceRender: false },
    { key: 'params', label: '系統參數', children: <SystemParamsTab />, forceRender: true },
    { key: 'credit-score', label: '⭐ 誠信分數配分', children: <CreditScoreTab />, forceRender: false },
  ].filter(tab => {
    if (superAdminTabs.includes(tab.key)) return isSuperAdmin
    return true
  })

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>系統設定</Title>
      <Tabs items={allTabs} destroyInactiveTabPane={false} />
    </div>
  )
}
