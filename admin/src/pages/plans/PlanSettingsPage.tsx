import { useState, useEffect, useCallback, useMemo } from 'react'
import {
  Tabs, Table, Button, Modal, Drawer, Form, Input, InputNumber, Switch,
  Tag, Space, Typography, message, Card, Select, Radio, DatePicker, Divider,
} from 'antd'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Text, Title } = Typography

/* ─────────────────────────────────────────────────────
   Tab 1：訂閱方案（從 SystemSettingsPage 的 PricingTab 搬移）
───────────────────────────────────────────────────── */

interface SubPlan {
  id: number
  slug: string
  name: string
  price: number
  original_price: number
  duration_days: number
  is_trial: boolean
  is_active: boolean
  membership_level: number
  promo: {
    type: 'none' | 'percentage' | 'fixed'
    value: number | null
    start_at: string | null
    end_at: string | null
    note: string | null
    is_active: boolean
  }
}

function SubscriptionPlansTab() {
  const [plans, setPlans] = useState<SubPlan[]>([])
  const [loading, setLoading] = useState(true)
  const [editingPlan, setEditingPlan] = useState<SubPlan | null>(null)
  const [editForm] = Form.useForm()
  const [saving, setSaving] = useState(false)
  const [promoType, setPromoType] = useState<'none' | 'percentage' | 'fixed'>('none')

  const fetchPlans = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/settings/subscription-plans')
      setPlans(res.data.data ?? [])
    } catch { setPlans([]) }
    setLoading(false)
  }, [])

  useEffect(() => { fetchPlans() }, [fetchPlans])

  const handleEdit = (plan: SubPlan) => {
    setEditingPlan(plan)
    setPromoType(plan.promo?.type ?? 'none')
    editForm.setFieldsValue({
      name: plan.name,
      original_price: plan.original_price,
      duration_days: plan.duration_days,
      is_active: plan.is_active,
      membership_level: plan.membership_level ?? 2,
      promo_type: plan.promo?.type ?? 'none',
      promo_value: plan.promo?.value ?? undefined,
      promo_start_at: plan.promo?.start_at ? dayjs(plan.promo.start_at) : null,
      promo_end_at: plan.promo?.end_at ? dayjs(plan.promo.end_at) : null,
      promo_note: plan.promo?.note ?? '',
    })
  }

  const handleSave = async () => {
    if (!editingPlan) return
    setSaving(true)
    try {
      const vals = await editForm.validateFields()
      await apiClient.patch(`/admin/settings/subscription-plans/${editingPlan.id}`, {
        name: vals.name,
        original_price: vals.original_price,
        duration_days: vals.duration_days,
        is_active: vals.is_active,
        membership_level: vals.membership_level,
        promo_type: vals.promo_type,
        promo_value: vals.promo_value ?? null,
        promo_start_at: vals.promo_start_at?.toISOString() ?? null,
        promo_end_at: vals.promo_end_at?.toISOString() ?? null,
        promo_note: vals.promo_note ?? null,
      })
      message.success('方案已更新')
      setEditingPlan(null)
      fetchPlans()
    } catch { message.error('更新失敗') }
    setSaving(false)
  }

  const promoValue = Form.useWatch('promo_value', editForm) ?? 0
  const originalPrice = Form.useWatch('original_price', editForm) ?? editingPlan?.original_price ?? 0
  const previewPrice = useMemo(() => {
    if (promoType === 'percentage') return Math.round(originalPrice * (1 - promoValue / 100))
    if (promoType === 'fixed') return Math.max(1, originalPrice - promoValue)
    return originalPrice
  }, [promoType, promoValue, originalPrice])

  const columns = [
    { title: '方案名稱', dataIndex: 'name', key: 'name' },
    { title: '代碼', dataIndex: 'slug', key: 'slug', render: (s: string) => <Tag>{s}</Tag> },
    {
      title: '售價', key: 'price',
      render: (_: unknown, r: SubPlan) => (
        <Space>
          <Text strong>NT$ {r.price.toLocaleString()}</Text>
          {r.price !== r.original_price && (
            <Text type="secondary" delete style={{ fontSize: 12 }}>NT$ {r.original_price.toLocaleString()}</Text>
          )}
        </Space>
      ),
    },
    { title: '天數', dataIndex: 'duration_days', key: 'duration_days', render: (d: number) => `${d} 天` },
    {
      title: '折扣', key: 'promo',
      render: (_: unknown, r: SubPlan) => {
        if (r.promo?.is_active) {
          return (
            <Tag color="red">
              {r.promo.type === 'percentage' ? `${r.promo.value}% off` : `折抵 NT$${r.promo.value}`}
              {r.promo.end_at && ` 至 ${dayjs(r.promo.end_at).format('MM/DD')}`}
            </Tag>
          )
        }
        if (r.promo?.type !== 'none') return <Tag>折扣未生效</Tag>
        return <Tag>原價</Tag>
      },
    },
    {
      title: '狀態', dataIndex: 'is_active', key: 'is_active',
      render: (a: boolean) => <Tag color={a ? 'green' : 'default'}>{a ? '啟用' : '停用'}</Tag>,
    },
    {
      title: '操作', key: 'action', width: 80,
      render: (_: unknown, r: SubPlan) => <Button type="link" size="small" onClick={() => handleEdit(r)}>編輯</Button>,
    },
  ]

  return (
    <div>
      <Card title="訂閱方案管理" style={{ marginBottom: 16 }}>
        <Table dataSource={plans} columns={columns} rowKey="id" loading={loading}
          pagination={false} size="middle" />
      </Card>

      <Modal
        title={`編輯方案 — ${editingPlan?.name ?? ''}`}
        open={!!editingPlan}
        onOk={handleSave}
        onCancel={() => setEditingPlan(null)}
        confirmLoading={saving}
        okText="儲存"
        width={560}
      >
        <Form form={editForm} layout="vertical" style={{ marginTop: 16 }}>
          <Form.Item label="方案名稱" name="name" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item label="定價（原價 NT$）" name="original_price" rules={[{ required: true }]}>
            <InputNumber min={1} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="有效天數" name="duration_days" rules={[{ required: true }]}>
            <Select>
              <Select.Option value={3}>3 天</Select.Option>
              <Select.Option value={7}>7 天</Select.Option>
              <Select.Option value={30}>30 天</Select.Option>
              <Select.Option value={90}>90 天</Select.Option>
              <Select.Option value={365}>365 天</Select.Option>
            </Select>
          </Form.Item>
          <Form.Item label="啟用" name="is_active" valuePropName="checked">
            <Switch checkedChildren="啟用" unCheckedChildren="停用" />
          </Form.Item>
          <Form.Item label="購買後會員等級" name="membership_level" rules={[{ required: true }]}
            tooltip="購買此方案後，用戶的 membership_level 將升至此等級"
          >
            <Select>
              <Select.Option value={1}>Lv1 — 驗證會員</Select.Option>
              <Select.Option value={2}>Lv2 — 進階驗證會員</Select.Option>
              <Select.Option value={3}>Lv3 — 付費會員</Select.Option>
            </Select>
          </Form.Item>

          <Divider style={{ fontSize: 13, color: '#888' }}>優惠折扣設定</Divider>

          <Form.Item label="折扣類型" name="promo_type">
            <Radio.Group onChange={e => setPromoType(e.target.value)}>
              <Radio.Button value="none">無折扣</Radio.Button>
              <Radio.Button value="percentage">百分比折扣</Radio.Button>
              <Radio.Button value="fixed">固定金額折抵</Radio.Button>
            </Radio.Group>
          </Form.Item>

          {promoType === 'percentage' && (
            <Form.Item label="折扣百分比" name="promo_value"
              help="例如輸入 15，代表打 85 折"
              rules={[{ required: true, message: '請輸入折扣百分比' }]}>
              <InputNumber min={1} max={99} addonAfter="%" style={{ width: 160 }} />
            </Form.Item>
          )}

          {promoType === 'fixed' && (
            <Form.Item label="折抵金額" name="promo_value"
              rules={[{ required: true, message: '請輸入折抵金額' }]}>
              <InputNumber min={1} addonBefore="NT$" style={{ width: 180 }} />
            </Form.Item>
          )}

          {promoType !== 'none' && (
            <>
              <Form.Item label="預覽售價">
                <Space>
                  <Text strong style={{ fontSize: 20 }}>NT$ {previewPrice.toLocaleString()}</Text>
                  {previewPrice !== originalPrice && (
                    <>
                      <Text type="secondary" delete>NT$ {originalPrice.toLocaleString()}</Text>
                      <Tag color="red">省 NT$ {(originalPrice - previewPrice).toLocaleString()}</Tag>
                    </>
                  )}
                </Space>
              </Form.Item>

              <Form.Item label="優惠開始時間" name="promo_start_at">
                <DatePicker showTime format="YYYY-MM-DD HH:mm" placeholder="不設定 = 立即生效" style={{ width: '100%' }} />
              </Form.Item>
              <Form.Item label="優惠結束時間" name="promo_end_at">
                <DatePicker showTime format="YYYY-MM-DD HH:mm" placeholder="不設定 = 永久有效" style={{ width: '100%' }} />
              </Form.Item>
              <Form.Item label="活動備註" name="promo_note">
                <Input placeholder="例：聖誕跨年優惠" maxLength={100} showCount />
              </Form.Item>
            </>
          )}
        </Form>
      </Modal>
    </div>
  )
}

/* ─────────────────────────────────────────────────────
   Tab 2：點數方案 CRUD
───────────────────────────────────────────────────── */

interface PointPackage {
  id: number
  slug: string
  name: string
  points: number
  bonus_points: number
  total_points: number
  price: number
  cost_per_point: number
  description: string | null
  sort_order: number
  is_active: boolean
}

function PointPackagesTab() {
  const [packages, setPackages] = useState<PointPackage[]>([])
  const [loading, setLoading] = useState(true)
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [editing, setEditing] = useState<PointPackage | null>(null)
  const [form] = Form.useForm()
  const [saving, setSaving] = useState(false)

  const fetchPackages = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/point-packages')
      setPackages(res.data?.data ?? [])
    } catch { setPackages([]) }
    setLoading(false)
  }, [])

  useEffect(() => { fetchPackages() }, [fetchPackages])

  function openEdit(pkg: PointPackage) {
    setEditing(pkg)
    form.setFieldsValue({
      name: pkg.name,
      points: pkg.points,
      bonus_points: pkg.bonus_points,
      price: pkg.price,
      description: pkg.description ?? '',
      is_active: pkg.is_active,
    })
    setDrawerOpen(true)
  }

  async function handleSave() {
    if (!editing) return
    setSaving(true)
    try {
      const values = await form.validateFields()
      await apiClient.patch(`/admin/point-packages/${editing.id}`, values)
      message.success('方案已更新')
      setDrawerOpen(false)
      setEditing(null)
      fetchPackages()
    } catch { message.error('更新失敗') }
    setSaving(false)
  }

  const columns = [
    { title: '方案名稱', dataIndex: 'name', key: 'name', width: 130 },
    { title: '代碼', dataIndex: 'slug', key: 'slug', width: 110, render: (s: string) => <Tag>{s}</Tag> },
    { title: '點數', dataIndex: 'points', key: 'points', width: 80 },
    {
      title: '贈送', dataIndex: 'bonus_points', key: 'bonus_points', width: 80,
      render: (v: number) => v > 0 ? <Tag color="gold">+{v}</Tag> : <span style={{ color: '#999' }}>—</span>,
    },
    {
      title: '價格', dataIndex: 'price', key: 'price', width: 100, align: 'right' as const,
      render: (v: number) => v > 0 ? `NT$${v.toLocaleString()}` : '—',
    },
    {
      title: '每點成本', key: 'cost_per_point', width: 90, align: 'right' as const,
      render: (_: unknown, r: PointPackage) => {
        const total = r.points + r.bonus_points
        return total > 0 ? `$${(r.price / total).toFixed(1)}` : '—'
      },
    },
    {
      title: '說明', dataIndex: 'description', key: 'description', ellipsis: true,
      render: (v: string | null) => v || <span style={{ color: '#999' }}>—</span>,
    },
    {
      title: '狀態', dataIndex: 'is_active', key: 'is_active', width: 80,
      render: (v: boolean) => v ? <Tag color="green">啟用</Tag> : <Tag>停用</Tag>,
    },
    {
      title: '操作', key: 'action', width: 70,
      render: (_: unknown, r: PointPackage) => <Button type="link" size="small" onClick={() => openEdit(r)}>編輯</Button>,
    },
  ]

  return (
    <div>
      <Card title="點數方案管理" style={{ marginBottom: 16 }}>
        <Table
          dataSource={packages}
          columns={columns}
          rowKey="id"
          loading={loading}
          pagination={false}
          size="middle"
        />
      </Card>

      <Drawer
        title={`編輯點數方案 — ${editing?.name ?? ''}`}
        open={drawerOpen}
        onClose={() => { setDrawerOpen(false); setEditing(null) }}
        width={420}
        extra={<Button type="primary" loading={saving} onClick={handleSave}>儲存</Button>}
      >
        <Form form={form} layout="vertical">
          <Form.Item name="name" label="方案名稱" rules={[{ required: true, max: 50 }]}>
            <Input />
          </Form.Item>
          <Form.Item name="points" label="基本點數" rules={[{ required: true }]}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="bonus_points" label="贈送點數">
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="price" label="價格 NT$" rules={[{ required: true }]}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="description" label="方案說明">
            <Input.TextArea rows={3} maxLength={200} showCount />
          </Form.Item>
          <Form.Item name="is_active" label="啟用狀態" valuePropName="checked">
            <Switch checkedChildren="啟用" unCheckedChildren="停用" />
          </Form.Item>
        </Form>
      </Drawer>
    </div>
  )
}

/* ─────────────────────────────────────────────────────
   主頁面
───────────────────────────────────────────────────── */
export default function PlanSettingsPage() {
  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>💰 方案設定</Title>
      <Tabs
        defaultActiveKey="subscription"
        items={[
          { key: 'subscription', label: '訂閱方案', children: <SubscriptionPlansTab /> },
          { key: 'points', label: '點數方案', children: <PointPackagesTab /> },
        ]}
      />
    </div>
  )
}
