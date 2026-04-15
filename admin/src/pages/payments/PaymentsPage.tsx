import { useState, useEffect } from 'react'
import { Table, Select, Tag, Card, Row, Col, Statistic, Typography, Space, Modal, Descriptions, Button } from 'antd'
import { EyeOutlined, DownloadOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography

const STATUS_COLOR: Record<string, string> = { paid: 'green', failed: 'red', refunded: 'orange', pending: 'blue', expired: 'default' }
const STATUS_LABEL: Record<string, string> = { paid: '已付款', failed: '失敗', refunded: '已退款', pending: '待付款', expired: '已過期' }

interface Payment {
  id: number
  order_number: string
  user: { id: number; nickname: string } | null
  plan_name: string
  amount: number
  payment_method: string | null
  status: string
  paid_at: string | null
  ecpay_trade_no: string | null
  ecpay_payment_date: string | null
  ecpay_payment_type: string | null
  invoice_no: string | null
  invoice_date: string | null
  created_at: string
}

export default function PaymentsPage() {
  const [payments, setPayments] = useState<Payment[]>([])
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [detailRecord, setDetailRecord] = useState<Payment | null>(null)
  const [exporting, setExporting] = useState(false)

  useEffect(() => { fetchPayments() }, [page, statusFilter])

  async function fetchPayments() {
    setLoading(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (statusFilter !== 'all') params.status = statusFilter
      const res = await apiClient.get('/admin/payments', { params })
      setPayments(res.data.data.payments ?? [])
      setTotal(res.data.data.pagination?.total ?? 0)
    } catch { setPayments([]) }
    setLoading(false)
  }

  const paidPayments = payments.filter(p => p.status === 'paid')
  const totalRevenue = paidPayments.reduce((s, p) => s + (p.amount || 0), 0)

  const columns = [
    { title: '訂單編號', dataIndex: 'order_number', key: 'order_number', width: 180, ellipsis: true },
    { title: '用戶', key: 'user', width: 100, render: (_: unknown, r: Payment) => r.user?.nickname || '-' },
    { title: '方案', dataIndex: 'plan_name', key: 'plan_name', width: 100 },
    { title: '金額', dataIndex: 'amount', key: 'amount', width: 100, render: (a: number) => `NT$${(a || 0).toLocaleString()}` },
    { title: '狀態', dataIndex: 'status', key: 'status', width: 80, render: (s: string) => <Tag color={STATUS_COLOR[s] || 'default'}>{STATUS_LABEL[s] || s}</Tag> },
    {
      title: '綠界交易序號', dataIndex: 'ecpay_trade_no', key: 'ecpay_trade_no', width: 160, ellipsis: true,
      render: (v: string | null) => v ? <Text copyable style={{ fontSize: 12 }}>{v}</Text> : <Text type="secondary">-</Text>,
    },
    {
      title: '發票號碼', dataIndex: 'invoice_no', key: 'invoice_no', width: 120,
      render: (v: string | null) => v ? <Tag color="blue">{v}</Tag> : <Text type="secondary">-</Text>,
    },
    { title: '付款時間', dataIndex: 'paid_at', key: 'paid_at', width: 130, render: (d: string | null) => d ? dayjs(d).format('MM/DD HH:mm') : '-' },
    {
      title: '', key: 'actions', width: 50, fixed: 'right' as const,
      render: (_: unknown, r: Payment) => (
        <EyeOutlined style={{ cursor: 'pointer', color: '#F0294E' }} onClick={() => setDetailRecord(r)} title="查看詳情" />
      ),
    },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>支付記錄</Title>

      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col span={8}><Card><Statistic title="總收入" value={`NT$ ${totalRevenue.toLocaleString()}`} /></Card></Col>
        <Col span={8}><Card><Statistic title="已付款" value={paidPayments.length} /></Card></Col>
        <Col span={8}><Card><Statistic title="總訂單" value={total} /></Card></Col>
      </Row>

      <Space style={{ marginBottom: 16 }}>
        <Select value={statusFilter} onChange={(v) => { setStatusFilter(v); setPage(1) }} style={{ width: 120 }}>
          <Select.Option value="all">全部狀態</Select.Option>
          <Select.Option value="paid">已付款</Select.Option>
          <Select.Option value="pending">待付款</Select.Option>
          <Select.Option value="failed">失敗</Select.Option>
        </Select>
        <Button icon={<DownloadOutlined />} loading={exporting} onClick={async () => {
          setExporting(true)
          try {
            const params: Record<string, string> = {}
            if (statusFilter !== 'all') params.status = statusFilter
            const res = await apiClient.get('/admin/payments/export', { params, responseType: 'blob' })
            const url = window.URL.createObjectURL(new Blob([res.data]))
            const a = document.createElement('a')
            a.href = url
            a.download = `payments_${dayjs().format('YYYYMMDD')}.csv`
            a.click()
            window.URL.revokeObjectURL(url)
          } catch { /* ignore */ }
          setExporting(false)
        }}>
          匯出 CSV
        </Button>
      </Space>

      <Table dataSource={payments} columns={columns} rowKey="id" loading={loading}
        pagination={{ current: page, pageSize: 20, total, onChange: setPage, showTotal: (t) => `共 ${t} 筆` }}
        size="middle" locale={{ emptyText: '目前無支付記錄' }}
        scroll={{ x: 1100 }}
      />

      {/* Detail Modal */}
      <Modal
        title={`訂單詳情 — ${detailRecord?.order_number ?? ''}`}
        open={!!detailRecord}
        onCancel={() => setDetailRecord(null)}
        footer={null}
        width={640}
      >
        {detailRecord && (
          <Descriptions column={2} bordered size="small" style={{ marginTop: 8 }}>
            <Descriptions.Item label="訂單編號" span={2}>{detailRecord.order_number}</Descriptions.Item>
            <Descriptions.Item label="用戶">{detailRecord.user?.nickname ?? '-'}</Descriptions.Item>
            <Descriptions.Item label="方案">{detailRecord.plan_name ?? '-'}</Descriptions.Item>
            <Descriptions.Item label="金額">NT$ {(detailRecord.amount || 0).toLocaleString()}</Descriptions.Item>
            <Descriptions.Item label="付款方式">{detailRecord.payment_method ?? '-'}</Descriptions.Item>
            <Descriptions.Item label="狀態">
              <Tag color={STATUS_COLOR[detailRecord.status] || 'default'}>{STATUS_LABEL[detailRecord.status] || detailRecord.status}</Tag>
            </Descriptions.Item>
            <Descriptions.Item label="建立時間">{detailRecord.created_at ? dayjs(detailRecord.created_at).format('YYYY/MM/DD HH:mm:ss') : '-'}</Descriptions.Item>

            {/* ECPay reconciliation */}
            <Descriptions.Item label="綠界交易序號" span={2}>
              {detailRecord.ecpay_trade_no || <Text type="secondary">尚無</Text>}
            </Descriptions.Item>
            <Descriptions.Item label="綠界付款時間">{detailRecord.ecpay_payment_date ?? '-'}</Descriptions.Item>
            <Descriptions.Item label="綠界付款方式">{detailRecord.ecpay_payment_type ?? '-'}</Descriptions.Item>

            {/* Invoice */}
            <Descriptions.Item label="發票號碼" span={2}>
              {detailRecord.invoice_no
                ? <Tag color="blue" style={{ fontSize: 14 }}>{detailRecord.invoice_no}</Tag>
                : <Text type="secondary">未開立</Text>
              }
            </Descriptions.Item>
            <Descriptions.Item label="發票開立時間">{detailRecord.invoice_date ?? '-'}</Descriptions.Item>
            <Descriptions.Item label="付款完成時間">{detailRecord.paid_at ? dayjs(detailRecord.paid_at).format('YYYY/MM/DD HH:mm:ss') : '-'}</Descriptions.Item>
          </Descriptions>
        )}
      </Modal>
    </div>
  )
}
