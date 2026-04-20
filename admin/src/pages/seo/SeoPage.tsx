import { useState, useEffect } from 'react'
import { Table, Button, Modal, Form, Input, Typography, message, Tag, Divider, Card, Space, Alert } from 'antd'
import { EditOutlined, SaveOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'

const { Title, Paragraph, Text } = Typography

interface TrackingConfig {
  ga_measurement_id: string
  fb_pixel_id: string
  gtm_id: string
}

interface SeoMeta {
  id: number
  route: string
  title: string
  description: string
  og_title: string | null
  og_description: string | null
  og_image_url: string | null
  updated_at: string
}

export default function SeoPage() {
  const [metas, setMetas] = useState<SeoMeta[]>([])
  const [loading, setLoading] = useState(false)
  const [metaModalOpen, setMetaModalOpen] = useState(false)
  const [editingMeta, setEditingMeta] = useState<SeoMeta | null>(null)
  const [metaForm] = Form.useForm()

  // 追蹤碼（A17 擴充 — 2026-04-20）
  const [tracking, setTracking] = useState<TrackingConfig>({
    ga_measurement_id: '',
    fb_pixel_id: '',
    gtm_id: '',
  })
  const [trackingLoading, setTrackingLoading] = useState(false)
  const [trackingSaving, setTrackingSaving] = useState(false)

  useEffect(() => {
    fetchMetas()
    fetchTracking()
  }, [])

  async function fetchTracking() {
    setTrackingLoading(true)
    try {
      const res = await apiClient.get('/admin/settings/tracking')
      const d = res.data?.data || {}
      setTracking({
        ga_measurement_id: d.ga_measurement_id || '',
        fb_pixel_id: d.fb_pixel_id || '',
        gtm_id: d.gtm_id || '',
      })
    } catch {
      // 首次未設定時路由可能 404，視為空值
    } finally {
      setTrackingLoading(false)
    }
  }

  async function saveTracking() {
    setTrackingSaving(true)
    try {
      await apiClient.patch('/admin/settings/tracking', tracking)
      message.success('追蹤碼已更新（最多 60 秒內生效）')
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      message.error(e?.response?.data?.message || '儲存失敗')
    } finally {
      setTrackingSaving(false)
    }
  }

  async function fetchMetas() {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/seo/meta')
      setMetas(res.data?.data || [])
    } catch {
      // Use empty array on error
    } finally {
      setLoading(false)
    }
  }

  function openMetaEdit(meta: SeoMeta) {
    setEditingMeta(meta)
    metaForm.setFieldsValue({
      title: meta.title,
      description: meta.description,
      og_title: meta.og_title ?? '',
      og_description: meta.og_description ?? '',
      og_image_url: meta.og_image_url ?? '',
    })
    setMetaModalOpen(true)
  }

  async function handleMetaSave() {
    try {
      const values = await metaForm.validateFields()
      const payload = {
        title: values.title,
        description: values.description,
        og_title: values.og_title?.trim() || null,
        og_description: values.og_description?.trim() || null,
        og_image_url: values.og_image_url?.trim() || null,
      }
      await apiClient.patch(`/admin/seo/meta/${editingMeta!.id}`, payload)
      message.success('SEO Meta 已更新')
      setMetaModalOpen(false)
      fetchMetas()
    } catch {
      // validation or API error
    }
  }

  const metaColumns = [
    {
      title: '路由',
      dataIndex: 'route',
      key: 'route',
      width: 120,
      render: (v: string) => <Tag color="blue">{v}</Tag>,
    },
    { title: 'Title', dataIndex: 'title', key: 'title', ellipsis: true },
    { title: 'Description', dataIndex: 'description', key: 'description', ellipsis: true },
    {
      title: 'OG Image',
      dataIndex: 'og_image_url',
      key: 'og_image_url',
      width: 120,
      render: (v: string | null) => (v ? <Tag color="green">已設定</Tag> : <Tag>未設定</Tag>),
    },
    {
      title: '操作',
      key: 'action',
      width: 100,
      render: (_: unknown, record: SeoMeta) => (
        <Button icon={<EditOutlined />} size="small" onClick={() => openMetaEdit(record)}>
          編輯
        </Button>
      ),
    },
  ]

  return (
    <div>
      <Title level={3}>SEO Meta 管理</Title>
      <Paragraph type="secondary">
        管理前台各頁面的 SEO meta tag（title / description / OG tags）。對應 A17 功能；A18 廣告跳轉連結保留 Phase 2。
      </Paragraph>

      <Table
        dataSource={metas}
        columns={metaColumns}
        rowKey="id"
        loading={loading}
        pagination={false}
      />

      <Modal
        title={`編輯 SEO Meta - ${editingMeta?.route ?? ''}`}
        open={metaModalOpen}
        onOk={handleMetaSave}
        onCancel={() => setMetaModalOpen(false)}
        okText="儲存"
        cancelText="取消"
        width={600}
      >
        <Form form={metaForm} layout="vertical">
          <Form.Item
            name="title"
            label="Title"
            rules={[
              { required: true, message: '請輸入標題' },
              { max: 70, message: '最多 70 字' },
            ]}
          >
            <Input placeholder="例：MiMeet - 台灣高端交友平台" />
          </Form.Item>
          <Form.Item
            name="description"
            label="Description"
            rules={[
              { required: true, message: '請輸入描述' },
              { max: 200, message: '最多 200 字' },
            ]}
          >
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item name="og_title" label="OG Title" rules={[{ max: 70, message: '最多 70 字' }]}>
            <Input placeholder="（選填）Open Graph 標題" />
          </Form.Item>
          <Form.Item name="og_description" label="OG Description" rules={[{ max: 200, message: '最多 200 字' }]}>
            <Input.TextArea rows={2} placeholder="（選填）Open Graph 描述" />
          </Form.Item>
          <Form.Item
            name="og_image_url"
            label="OG Image URL"
            rules={[{ type: 'url', message: '請輸入有效的 URL' }]}
          >
            <Input placeholder="https://cdn.mimeet.tw/og/home.jpg" />
          </Form.Item>
        </Form>
      </Modal>

      <Divider style={{ marginTop: 32 }} />

      <Title level={3} style={{ marginBottom: 8 }}>📊 追蹤碼管理</Title>
      <Paragraph type="secondary" style={{ marginBottom: 16 }}>
        管理員填入 ID 後，前台下一次載入會自動插入對應 script。空白代表不啟用。
        更新後最多 60 秒內生效（公開 site-config 端點的 Cache TTL）。
      </Paragraph>

      <Alert
        type="info"
        showIcon
        style={{ marginBottom: 16 }}
        message="資料流：管理員填值 → PATCH /admin/settings/tracking → 清除 site_config Cache → 前台 GET /site-config → 動態插入 <script>"
      />

      <Card loading={trackingLoading} style={{ maxWidth: 640 }}>
        <Form layout="vertical">
          <Form.Item
            label="Google Analytics 4 Measurement ID"
            help="格式：G-XXXXXXXXXX（到 analytics.google.com 建立資源後取得）"
          >
            <Input
              value={tracking.ga_measurement_id}
              onChange={(e) => setTracking({ ...tracking, ga_measurement_id: e.target.value.trim() })}
              placeholder="G-XXXXXXXXXX"
              allowClear
            />
          </Form.Item>

          <Form.Item
            label="Facebook Pixel ID"
            help="格式：10–20 位純數字（到 Facebook 廣告管理員取得）"
          >
            <Input
              value={tracking.fb_pixel_id}
              onChange={(e) => setTracking({ ...tracking, fb_pixel_id: e.target.value.trim() })}
              placeholder="1234567890123456"
              allowClear
            />
          </Form.Item>

          <Form.Item
            label="Google Tag Manager ID"
            help="格式：GTM-XXXXXXX。進階用戶可用 GTM 統一管理所有追蹤碼。"
          >
            <Input
              value={tracking.gtm_id}
              onChange={(e) => setTracking({ ...tracking, gtm_id: e.target.value.trim() })}
              placeholder="GTM-XXXXXXX"
              allowClear
            />
          </Form.Item>

          <Space>
            <Button type="primary" icon={<SaveOutlined />} loading={trackingSaving} onClick={saveTracking}>
              儲存追蹤碼
            </Button>
            <Text type="secondary">清空欄位並儲存即可停用對應追蹤碼</Text>
          </Space>
        </Form>
      </Card>

      {/*
        [Phase 2] A18 廣告跳轉連結（SEO Redirect Links）
        - 對應規格：docs/API-002_後台管理API規格書.md §9.1–9.3
        - 後端：SeoController::linkIndex/linkStore/linkUpdate/linkDestroy/linkStats 方法尚未實作
        - 啟用時：補 seo_links + seo_click_logs 遷移、恢復本 tab 與 Modal、補 /go/{slug} 前台路由
      */}
    </div>
  )
}
