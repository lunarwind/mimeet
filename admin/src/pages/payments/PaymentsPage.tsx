<<<<<<< HEAD
import { useState, useMemo, useEffect } from 'react'
import { Table, Select, DatePicker, Tag, Button, Card, Row, Col, Statistic, Typography, Space, Modal, Descriptions } from 'antd'
import { DollarOutlined, UserOutlined, RiseOutlined, ShoppingCartOutlined, DownloadOutlined, EyeOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import { MOCK_PAYMENTS } from '../../mocks/members'
import type { PaymentRecord } from '../../types/admin'
import dayjs from 'dayjs'

const { Title, Text } = Typography
const { RangePicker } = DatePicker
=======
import { useState, useEffect } from 'react'
import { Table, Select, Tag, Card, Row, Col, Statistic, Typography, Space, Modal, Descriptions } from 'antd'
import { EyeOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import dayjs from 'dayjs'

const { Title, Text } = Typography
>>>>>>> develop

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

const PAYMENT_METHOD_LABEL: Record<string, string> = {
  Credit: '信用卡',
  ATM: 'ATM 轉帳',
  WebATM: '網路 ATM',
  CVS: '超商代碼',
  BARCODE: '超商條碼',
}

// Extend mock data with ecpay fields for display
const EXTENDED_PAYMENTS = MOCK_PAYMENTS.map((p, idx) => ({
  ...p,
  ecpay_trade_no: `2026041${String(idx + 1).padStart(7, '0')}`,
  ecpay_invoice_no: idx % 2 === 0 ? `AA${String((idx + 1) * 111).padStart(8, '0')}` : null,
}))

type ExtendedPayment = PaymentRecord & { ecpay_trade_no: string; ecpay_invoice_no: string | null }

export default function PaymentsPage() {
  const [payments, setPayments] = useState<Payment[]>([])
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState<string>('all')
<<<<<<< HEAD
  const [planFilter, setPlanFilter] = useState<string>('all')
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null] | null>(null)
  const [detailRecord, setDetailRecord] = useState<ExtendedPayment | null>(null)
  const [detailOpen, setDetailOpen] = useState(false)
  const [allPayments, setAllPayments] = useState<ExtendedPayment[]>(EXTENDED_PAYMENTS)

  useEffect(() => {
    // Try real API first, fall back to mock data on failure
    apiClient.get('/admin/payments', { params: { per_page: 100 } })
      .then((res) => {
        if (res.data?.data?.payments && res.data.data.payments.length > 0) {
          setAllPayments(res.data.data.payments.map((p: PaymentRecord, idx: number) => ({
            ...p,
            ecpay_trade_no: p.ecpay_trade_no || `2026041${String(idx + 1).padStart(7, '0')}`,
            ecpay_invoice_no: p.ecpay_invoice_no || null,
          })))
        }
      })
      .catch(() => { /* keep mock data */ })
  }, [])

  const filtered = useMemo(() => {
    let data = [...allPayments]
    if (statusFilter !== 'all') data = data.filter((p) => p.status === statusFilter)
    if (planFilter !== 'all') data = data.filter((p) => p.plan === planFilter)
    if (dateRange && dateRange[0] && dateRange[1]) {
      const start = dateRange[0].startOf('day').valueOf()
      const end = dateRange[1].endOf('day').valueOf()
      data = data.filter((p) => {
        const t = new Date(p.paid_at).getTime()
        return t >= start && t <= end
      })
    }
    return data
  }, [statusFilter, planFilter, dateRange])

  const todayRevenue = allPayments.filter((p) => p.status === 'paid' && dayjs(p.paid_at).isSame(dayjs(), 'day')).reduce((s, p) => s + p.amount_paid, 0)
  const monthRevenue = allPayments.filter((p) => p.status === 'paid' && dayjs(p.paid_at).isSame(dayjs(), 'month')).reduce((s, p) => s + p.amount_paid, 0)
  const paidMembers = new Set(allPayments.filter((p) => p.status === 'paid').map((p) => p.user.uid)).size
  const monthNewSubs = allPayments.filter((p) => p.status === 'paid' && dayjs(p.paid_at).isSame(dayjs(), 'month')).length

  const handleRowClick = (record: ExtendedPayment) => {
    setDetailRecord(record)
    setDetailOpen(true)
  }
=======
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [detailRecord, setDetailRecord] = useState<Payment | null>(null)

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
>>>>>>> develop

  const columns = [
    { title: '訂單編號', dataIndex: 'order_number', key: 'order_number', width: 180, ellipsis: true },
    { title: '用戶', key: 'user', width: 100, render: (_: unknown, r: Payment) => r.user?.nickname || '-' },
    { title: '方案', dataIndex: 'plan_name', key: 'plan_name', width: 100 },
    { title: '金額', dataIndex: 'amount', key: 'amount', width: 100, render: (a: number) => `NT$${(a || 0).toLocaleString()}` },
    { title: '狀態', dataIndex: 'status', key: 'status', width: 80, render: (s: string) => <Tag color={STATUS_COLOR[s] || 'default'}>{STATUS_LABEL[s] || s}</Tag> },
    {
<<<<<<< HEAD
      title: '用戶', key: 'user', width: 120,
      render: (_: unknown, r: ExtendedPayment) => r.user.nickname,
    },
    { title: '方案', dataIndex: 'plan', key: 'plan', width: 100 },
    {
      title: '金額', dataIndex: 'amount', key: 'amount', width: 100,
      render: (a: number) => `NT$${a}`,
    },
    {
      title: '付款方式', dataIndex: 'payment_method', key: 'payment_method', width: 100,
      render: (m: string) => PAYMENT_METHOD_LABEL[m] || m,
    },
    {
      title: 'ECPay 交易編號', dataIndex: 'ecpay_trade_no', key: 'ecpay_trade_no', width: 160,
      render: (v: string) => <Text copyable style={{ fontSize: 12 }}>{v}</Text>,
    },
    {
      title: '發票號碼', dataIndex: 'ecpay_invoice_no', key: 'ecpay_invoice_no', width: 130,
      render: (v: string | null) => v ? <Tag color="blue">{v}</Tag> : <Text type="secondary">-</Text>,
    },
    {
      title: '狀態', dataIndex: 'status', key: 'status', width: 80,
      render: (s: string) => <Tag color={STATUS_COLOR[s]}>{STATUS_LABEL[s]}</Tag>,
=======
      title: '綠界交易序號', dataIndex: 'ecpay_trade_no', key: 'ecpay_trade_no', width: 160, ellipsis: true,
      render: (v: string | null) => v ? <Text copyable style={{ fontSize: 12 }}>{v}</Text> : <Text type="secondary">-</Text>,
>>>>>>> develop
    },
    {
      title: '發票號碼', dataIndex: 'invoice_no', key: 'invoice_no', width: 120,
      render: (v: string | null) => v ? <Tag color="blue">{v}</Tag> : <Text type="secondary">-</Text>,
    },
    { title: '付款時間', dataIndex: 'paid_at', key: 'paid_at', width: 130, render: (d: string | null) => d ? dayjs(d).format('MM/DD HH:mm') : '-' },
    {
      title: '', key: 'actions', width: 50, fixed: 'right' as const,
      render: (_: unknown, r: Payment) => (
        <EyeOutlined style={{ cursor: 'pointer', color: '#1677ff' }} onClick={() => setDetailRecord(r)} title="查看詳情" />
      ),
    },
    {
      title: '操作', key: 'actions', width: 70, fixed: 'right' as const,
      render: (_: unknown, r: ExtendedPayment) => (
        <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => handleRowClick(r)}>
          詳情
        </Button>
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
      </Space>

<<<<<<< HEAD
      <Table
        dataSource={filtered}
        columns={columns}
        rowKey="id"
        pagination={{ pageSize: 20, showTotal: (total) => `共 ${total} 筆` }}
        size="middle"
        scroll={{ x: 1200 }}
        onRow={(record) => ({
          onClick: () => handleRowClick(record),
          style: { cursor: 'pointer' },
        })}
=======
      <Table dataSource={payments} columns={columns} rowKey="id" loading={loading}
        pagination={{ current: page, pageSize: 20, total, onChange: setPage, showTotal: (t) => `共 ${t} 筆` }}
        size="middle" locale={{ emptyText: '目前無支付記錄' }}
        scroll={{ x: 1100 }}
>>>>>>> develop
      />

      {/* Detail Modal */}
      <Modal
<<<<<<< HEAD
        title="付款詳情"
        open={detailOpen}
        onCancel={() => setDetailOpen(false)}
        footer={[
          <Button key="close" onClick={() => setDetailOpen(false)}>
            關閉
          </Button>,
        ]}
        width={640}
      >
        {detailRecord && (
          <Descriptions bordered column={1} size="small">
            <Descriptions.Item label="訂單編號">{detailRecord.order_number}</Descriptions.Item>
            <Descriptions.Item label="用戶">{detailRecord.user.nickname} (UID: {detailRecord.user.uid})</Descriptions.Item>
            <Descriptions.Item label="方案">{detailRecord.plan}</Descriptions.Item>
            <Descriptions.Item label="訂單金額">NT${detailRecord.amount}</Descriptions.Item>
            <Descriptions.Item label="實付金額">NT${detailRecord.amount_paid}</Descriptions.Item>
            <Descriptions.Item label="付款方式">{PAYMENT_METHOD_LABEL[detailRecord.payment_method] || detailRecord.payment_method}</Descriptions.Item>
            <Descriptions.Item label="付款類型">{detailRecord.payment_type}</Descriptions.Item>
            <Descriptions.Item label="狀態">
              <Tag color={STATUS_COLOR[detailRecord.status]}>{STATUS_LABEL[detailRecord.status]}</Tag>
            </Descriptions.Item>
            <Descriptions.Item label="ECPay 交易編號">
              <Text copyable>{detailRecord.ecpay_trade_no}</Text>
            </Descriptions.Item>
            <Descriptions.Item label="發票號碼">
              {detailRecord.ecpay_invoice_no ? (
                <Tag color="blue">{detailRecord.ecpay_invoice_no}</Tag>
              ) : (
                <Text type="secondary">無</Text>
              )}
            </Descriptions.Item>
            <Descriptions.Item label="付款時間">{dayjs(detailRecord.paid_at).format('YYYY/MM/DD HH:mm:ss')}</Descriptions.Item>
=======
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
>>>>>>> develop
          </Descriptions>
        )}
      </Modal>
    </div>
  )
}
