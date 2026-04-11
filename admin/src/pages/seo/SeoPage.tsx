import { useState, useEffect } from 'react'
import {
  Tabs, Table, Button, Modal, Form, Input, Tag, Space, Typography, message, Popconfirm, Card, Statistic, Row, Col,
} from 'antd'
import { PlusOutlined, EditOutlined, DeleteOutlined, BarChartOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title } = Typography

interface MetaTag {
  id: number
  page_key: string
  title: string
  description: string
  og_image: string
}

interface AdLink {
  id: number
  slug: string
  target_url: string
  campaign: string
  click_count: number
  register_count: number
  is_active: boolean
  created_at: string
}

interface LinkStats {
  total_clicks: number
  total_registers: number
  conversion_rate: string
  daily_stats: { date: string; clicks: number; registers: number }[]
}

export default function SeoPage() {
  const [metas, setMetas] = useState<MetaTag[]>([])
  const [links, setLinks] = useState<AdLink[]>([])
  const [loading, setLoading] = useState(false)
  const [metaModalOpen, setMetaModalOpen] = useState(false)
  const [linkModalOpen, setLinkModalOpen] = useState(false)
  const [statsModalOpen, setStatsModalOpen] = useState(false)
  const [editingMeta, setEditingMeta] = useState<MetaTag | null>(null)
  const [editingLink, setEditingLink] = useState<AdLink | null>(null)
  const [linkStats, setLinkStats] = useState<LinkStats | null>(null)
  const [metaForm] = Form.useForm()
  const [linkForm] = Form.useForm()

  useEffect(() => {
    fetchMetas()
    fetchLinks()
  }, [])

  async function fetchMetas() {
    setLoading(true)
    try {
      const res = await apiClient.get('/admin/seo/meta')
      setMetas(res.data?.data?.metas || [])
    } catch {
      // Use empty array on error
    } finally {
      setLoading(false)
    }
  }

  async function fetchLinks() {
    try {
      const res = await apiClient.get('/admin/seo/links')
      setLinks(res.data?.data?.links || [])
    } catch {
      // Use empty array on error
    }
  }

  function openMetaEdit(meta: MetaTag) {
    setEditingMeta(meta)
    metaForm.setFieldsValue(meta)
    setMetaModalOpen(true)
  }

  async function handleMetaSave() {
    try {
      const values = await metaForm.validateFields()
      await apiClient.patch(`/admin/seo/meta/${editingMeta!.id}`, values)
      message.success('SEO Meta 已更新')
      setMetaModalOpen(false)
      fetchMetas()
    } catch {
      // validation or API error
    }
  }

  function openLinkAdd() {
    setEditingLink(null)
    linkForm.resetFields()
    setLinkModalOpen(true)
  }

  function openLinkEdit(link: AdLink) {
    setEditingLink(link)
    linkForm.setFieldsValue(link)
    setLinkModalOpen(true)
  }

  async function handleLinkSave() {
    try {
      const values = await linkForm.validateFields()
      if (editingLink) {
        await apiClient.patch(`/admin/seo/links/${editingLink.id}`, values)
        message.success('連結已更新')
      } else {
        await apiClient.post('/admin/seo/links', values)
        message.success('跳轉連結已建立')
      }
      setLinkModalOpen(false)
      fetchLinks()
    } catch {
      // validation or API error
    }
  }

  async function handleLinkDelete(id: number) {
    try {
      await apiClient.delete(`/admin/seo/links/${id}`)
      message.success('連結已刪除')
      fetchLinks()
    } catch {
      message.error('刪除失敗')
    }
  }

  async function openStats(id: number) {
    try {
      const res = await apiClient.get(`/admin/seo/links/${id}/stats`)
      setLinkStats(res.data?.data || null)
      setStatsModalOpen(true)
    } catch {
      message.error('載入統計失敗')
    }
  }

  const metaColumns = [
    { title: '頁面', dataIndex: 'page_key', key: 'page_key' },
    { title: 'Title', dataIndex: 'title', key: 'title', ellipsis: true },
    { title: 'Description', dataIndex: 'description', key: 'description', ellipsis: true },
    {
      title: '操作', key: 'action',
      render: (_: unknown, record: MetaTag) => (
        <Button icon={<EditOutlined />} size="small" onClick={() => openMetaEdit(record)}>編輯</Button>
      ),
    },
  ]

  const linkColumns = [
    { title: 'Slug', dataIndex: 'slug', key: 'slug', render: (v: string) => <Tag color="blue">/go/{v}</Tag> },
    { title: '目標網址', dataIndex: 'target_url', key: 'target_url', ellipsis: true },
    { title: '活動名稱', dataIndex: 'campaign', key: 'campaign' },
    { title: '點擊數', dataIndex: 'click_count', key: 'click_count', sorter: (a: AdLink, b: AdLink) => a.click_count - b.click_count },
    { title: '註冊數', dataIndex: 'register_count', key: 'register_count', sorter: (a: AdLink, b: AdLink) => a.register_count - b.register_count },
    {
      title: '狀態', dataIndex: 'is_active', key: 'is_active',
      render: (v: boolean) => v ? <Tag color="green">啟用</Tag> : <Tag color="red">停用</Tag>,
    },
    { title: '建立時間', dataIndex: 'created_at', key: 'created_at', render: (v: string) => dayjs(v).format('YYYY-MM-DD') },
    {
      title: '操作', key: 'action',
      render: (_: unknown, record: AdLink) => (
        <Space>
          <Button icon={<BarChartOutlined />} size="small" onClick={() => openStats(record.id)}>統計</Button>
          <Button icon={<EditOutlined />} size="small" onClick={() => openLinkEdit(record)}>編輯</Button>
          <Popconfirm title="確定要刪除此連結？" onConfirm={() => handleLinkDelete(record.id)} okText="確定" cancelText="取消">
            <Button icon={<DeleteOutlined />} size="small" danger>刪除</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  return (
    <div>
      <Title level={3}>SEO 管理</Title>
      <Tabs
        defaultActiveKey="meta"
        items={[
          {
            key: 'meta',
            label: 'Meta Tags',
            children: (
              <Table dataSource={metas} columns={metaColumns} rowKey="id" loading={loading} pagination={false} />
            ),
          },
          {
            key: 'links',
            label: '廣告跳轉連結',
            children: (
              <>
                <div style={{ marginBottom: 16 }}>
                  <Button type="primary" icon={<PlusOutlined />} onClick={openLinkAdd}>新增連結</Button>
                </div>
                <Table dataSource={links} columns={linkColumns} rowKey="id" pagination={{ pageSize: 10 }} />
              </>
            ),
          },
        ]}
      />

      {/* Meta Edit Modal */}
      <Modal
        title="編輯 SEO Meta"
        open={metaModalOpen}
        onOk={handleMetaSave}
        onCancel={() => setMetaModalOpen(false)}
        okText="儲存"
        cancelText="取消"
      >
        <Form form={metaForm} layout="vertical">
          <Form.Item name="title" label="Title" rules={[{ required: true, message: '請輸入標題' }, { max: 70, message: '最多 70 字' }]}>
            <Input />
          </Form.Item>
          <Form.Item name="description" label="Description" rules={[{ required: true, message: '請輸入描述' }, { max: 200, message: '最多 200 字' }]}>
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item name="og_image" label="OG Image URL">
            <Input placeholder="https://..." />
          </Form.Item>
        </Form>
      </Modal>

      {/* Link Add/Edit Modal */}
      <Modal
        title={editingLink ? '編輯跳轉連結' : '新增跳轉連結'}
        open={linkModalOpen}
        onOk={handleLinkSave}
        onCancel={() => setLinkModalOpen(false)}
        okText="儲存"
        cancelText="取消"
      >
        <Form form={linkForm} layout="vertical">
          <Form.Item name="slug" label="Slug" rules={[{ required: true, message: '請輸入 slug' }, { max: 50, message: '最多 50 字' }]}>
            <Input addonBefore="/go/" />
          </Form.Item>
          <Form.Item name="target_url" label="目標網址" rules={[{ required: true, message: '請輸入目標網址' }, { type: 'url', message: '請輸入有效的 URL' }]}>
            <Input placeholder="https://mimeet.tw/register" />
          </Form.Item>
          <Form.Item name="campaign" label="活動名稱">
            <Input placeholder="例：Instagram廣告" />
          </Form.Item>
        </Form>
      </Modal>

      {/* Stats Modal */}
      <Modal
        title="連結統計"
        open={statsModalOpen}
        onCancel={() => setStatsModalOpen(false)}
        footer={null}
        width={600}
      >
        {linkStats && (
          <>
            <Row gutter={16} style={{ marginBottom: 24 }}>
              <Col span={8}><Card><Statistic title="總點擊數" value={linkStats.total_clicks} /></Card></Col>
              <Col span={8}><Card><Statistic title="總註冊數" value={linkStats.total_registers} /></Card></Col>
              <Col span={8}><Card><Statistic title="轉換率" value={linkStats.conversion_rate} /></Card></Col>
            </Row>
            <Title level={5}>最近 7 日統計</Title>
            <Table
              dataSource={linkStats.daily_stats}
              rowKey="date"
              pagination={false}
              size="small"
              columns={[
                { title: '日期', dataIndex: 'date', key: 'date' },
                { title: '點擊數', dataIndex: 'clicks', key: 'clicks' },
                { title: '註冊數', dataIndex: 'registers', key: 'registers' },
              ]}
            />
          </>
        )}
      </Modal>
    </div>
  )
}
