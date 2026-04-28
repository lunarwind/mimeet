import { useState, useEffect } from 'react'
import { Table, Select, Tag, Card, Row, Col, Statistic, Typography, Space, Modal, Descriptions, Button, Alert, Popconfirm } from 'antd'
import { EyeOutlined, DownloadOutlined, ReloadOutlined } from '@ant-design/icons'
import apiClient from '../../api/client'
import { useAuthStore } from '../../stores/authStore'
import dayjs from 'dayjs'

const { Title, Text } = Typography

// ── 靜態映射 ──────────────────────────────────────────────────────────
const STATUS_COLOR: Record<string, string> = {
  paid: 'green', failed: 'red', refunded: 'orange', pending: 'blue',
  cancelled: 'default', refund_failed: 'volcano',
}
const STATUS_LABEL: Record<string, string> = {
  paid: '已付款', failed: '失敗', refunded: '已退款', pending: '待付款',
  cancelled: '已取消', refund_failed: '退款失敗',
}
const TYPE_COLOR: Record<string, string> = {
  verification: 'blue', subscription: 'green', points: 'orange',
}
const TYPE_LABEL: Record<string, string> = {
  verification: '💳 信用卡驗證', subscription: '📦 會員購買', points: '🪙 點數儲值',
}
const ENV_COLOR: Record<string, string> = {
  sandbox: 'orange', production: 'green',
}
const ENV_LABEL: Record<string, string> = {
  sandbox: '沙箱', production: '正式',
}

interface Payment {
  id: number
  order_no: string
  type: string
  type_label?: string
  item_name?: string
  environment: string
  user: { id: number; nickname: string; email?: string } | null
  amount: number
  currency?: string
  payment_method: string | null
  status: string
  gateway?: string
  gateway_trade_no: string | null
  card_country?: string | null
  paid_at: string | null
  refunded_at?: string | null
  refund_trade_no?: string | null
  invoice_applicable: boolean
  invoice_no: string | null
  invoice_issued_at?: string | null
  invoice_status?: string
  failure_reason?: string | null
  created_at: string
}

interface Meta {
  page: number; per_page: number; total: number; last_page: number
  total_revenue?: number; total_paid?: number; total_orders?: number
}

export default function PaymentsPage() {
  const user = useAuthStore((s) => s.user)
  const isSuperAdmin = user?.role === 'super_admin'

  const [payments, setPayments] = useState<Payment[]>([])
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [typeFilter, setTypeFilter] = useState<string>('all')
  const [envFilter, setEnvFilter] = useState<string>('all')
  const [meta, setMeta] = useState<Meta | null>(null)
  const [page, setPage] = useState(1)
  const [detailRecord, setDetailRecord] = useState<Payment | null>(null)
  const [exporting, setExporting] = useState(false)
  const [refunding, setRefunding] = useState(false)
  const [issuingInvoice, setIssuingInvoice] = useState<number | null>(null)

  useEffect(() => { fetchPayments() }, [page, statusFilter, typeFilter, envFilter])

  async function fetchPayments() {
    setLoading(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (statusFilter !== 'all') params.status = statusFilter
      if (typeFilter !== 'all')   params.type = typeFilter
      if (envFilter !== 'all')    params.environment = envFilter
      const res = await apiClient.get('/admin/payments', { params })
      setPayments(res.data.data ?? [])
      setMeta(res.data.meta ?? null)
    } catch { setPayments([]) }
    setLoading(false)
  }

  async function handleIssueInvoice(id: number) {
    setIssuingInvoice(id)
    try {
      await apiClient.post(`/admin/payments/${id}/issue-invoice`)
      alert('已排入佇列，約 1 分鐘後完成，請重整查看。')
      setTimeout(() => { fetchPayments(); setIssuingInvoice(null) }, 60_000)
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: { message?: string } } } }
      alert(e?.response?.data?.error?.message ?? '補開失敗')
      setIssuingInvoice(null)
    }
  }

  async function handleManualRefund(record: Payment) {
    if (!window.confirm(`確定對訂單 ${record.order_no} 發起退款嗎？`)) return
    setRefunding(true)
    try {
      await apiClient.post(`/admin/payments/${record.id}/refund`)
      alert('退款已排入 Queue，請稍後查看狀態')
      fetchPayments()
      setDetailRecord(null)
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      alert(e?.response?.data?.message ?? '退款失敗')
    }
    setRefunding(false)
  }

  const columns = [
    {
      title: '訂單編號', dataIndex: 'order_no', key: 'order_no', width: 200, ellipsis: true,
      render: (v: string) => <Text copyable style={{ fontSize: 11 }}>{v}</Text>,
    },
    {
      title: '類型', dataIndex: 'type', key: 'type', width: 110,
      render: (v: string) => <Tag color={TYPE_COLOR[v] ?? 'default'} style={{ fontSize: 11 }}>{TYPE_LABEL[v] ?? v}</Tag>,
    },
    {
      title: '環境', dataIndex: 'environment', key: 'environment', width: 70,
      render: (v: string) => <Tag color={ENV_COLOR[v] ?? 'default'} style={{ fontSize: 11 }}>{ENV_LABEL[v] ?? v}</Tag>,
    },
    { title: '項目', dataIndex: 'item_name', key: 'item_name', width: 130, ellipsis: true,
      render: (v: string | null) => <Text style={{ fontSize: 11 }}>{v ?? '-'}</Text>,
    },
    { title: '用戶', key: 'user', width: 90, render: (_: unknown, r: Payment) => r.user?.nickname || '-' },
    { title: '金額', dataIndex: 'amount', key: 'amount', width: 90, render: (a: number) => `NT$${(a||0).toLocaleString()}` },
    { title: '狀態', dataIndex: 'status', key: 'status', width: 90,
      render: (s: string) => <Tag color={STATUS_COLOR[s] || 'default'}>{STATUS_LABEL[s] || s}</Tag>,
    },
    { title: '綠界序號', dataIndex: 'gateway_trade_no', key: 'gateway_trade_no', width: 140, ellipsis: true,
      render: (v: string | null) => v ? <Text copyable style={{ fontSize: 11 }}>{v}</Text> : '-',
    },
    { title: '發票', key: 'invoice', width: 160,
      render: (_: unknown, r: Payment) => {
        if (r.invoice_no) return <Tag color="blue" style={{ fontSize: 11 }}>{r.invoice_no}</Tag>
        if (r.status !== 'paid') return <Text type="secondary" style={{ fontSize: 11 }}>-</Text>
        const statusTag = r.invoice_status === 'failed'
          ? <Tag color="red" style={{ fontSize: 10 }}>❌ 失敗</Tag>
          : <Tag color="orange" style={{ fontSize: 10 }}>⏳ 待開立</Tag>
        if (isSuperAdmin) {
          return (
            <Space size={4}>
              {statusTag}
              <Popconfirm
                title="確認補開發票？"
                description="此操作將呼叫綠界發票 API，無法撤銷"
                onConfirm={() => handleIssueInvoice(r.id)}
              >
                <Button size="small" loading={issuingInvoice === r.id} style={{ fontSize: 11 }}>補開</Button>
              </Popconfirm>
            </Space>
          )
        }
        return statusTag
      },
    },
    { title: '付款時間', dataIndex: 'paid_at', key: 'paid_at', width: 120,
      render: (d: string | null) => d ? dayjs(d).format('MM/DD HH:mm') : '-',
    },
    { title: '', key: 'actions', width: 40, fixed: 'right' as const,
      render: (_: unknown, r: Payment) => (
        <EyeOutlined style={{ cursor: 'pointer', color: '#F0294E' }} onClick={() => setDetailRecord(r)} />
      ),
    },
  ]

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>💲 支付記錄</Title>

      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col span={8}><Card><Statistic title="總收入" value={`NT$ ${(meta?.total_revenue ?? 0).toLocaleString()}`} /></Card></Col>
        <Col span={8}><Card><Statistic title="已付款筆數" value={meta?.total_paid ?? 0} /></Card></Col>
        <Col span={8}><Card><Statistic title="總訂單" value={meta?.total_orders ?? 0} /></Card></Col>
      </Row>

      <Space wrap style={{ marginBottom: 16 }}>
        <Select value={statusFilter} onChange={v => { setStatusFilter(v); setPage(1) }} style={{ width: 120 }}>
          <Select.Option value="all">全部狀態</Select.Option>
          <Select.Option value="paid">已付款</Select.Option>
          <Select.Option value="pending">待付款</Select.Option>
          <Select.Option value="failed">失敗</Select.Option>
          <Select.Option value="refunded">已退款</Select.Option>
          <Select.Option value="refund_failed">退款失敗</Select.Option>
        </Select>
        <Select value={typeFilter} onChange={v => { setTypeFilter(v); setPage(1) }} style={{ width: 130 }}>
          <Select.Option value="all">全部類型</Select.Option>
          <Select.Option value="verification">💳 信用卡驗證</Select.Option>
          <Select.Option value="subscription">📦 會員購買</Select.Option>
          <Select.Option value="points">🪙 點數儲值</Select.Option>
        </Select>
        <Select value={envFilter} onChange={v => { setEnvFilter(v); setPage(1) }} style={{ width: 120 }}>
          <Select.Option value="all">全部環境</Select.Option>
          <Select.Option value="sandbox">🟡 沙箱</Select.Option>
          <Select.Option value="production">🟢 正式</Select.Option>
        </Select>
        <Button icon={<ReloadOutlined />} onClick={() => fetchPayments()}>重新整理</Button>
        <Button icon={<DownloadOutlined />} loading={exporting} onClick={async () => {
          setExporting(true)
          try {
            const params: Record<string, string> = {}
            if (statusFilter !== 'all') params.status = statusFilter
            if (typeFilter !== 'all')   params.type = typeFilter
            if (envFilter !== 'all')    params.environment = envFilter
            const res = await apiClient.get('/admin/payments', {
              params: { ...params, per_page: 1000 },
            })
            const rows = res.data.data ?? []
            const headers = ['id', 'order_no', 'type', 'environment', 'user', 'amount', 'status', 'gateway_trade_no', 'invoice_no', 'paid_at', 'created_at']
            const csv = [
              headers.join(','),
              ...rows.map((r: Record<string, unknown>) => headers.map(h => {
                const v = h === 'user' ? (r.user as { nickname?: string } | null)?.nickname ?? '' : r[h]
                return `"${String(v ?? '').replace(/"/g,'""')}"`
              }).join(','))
            ].join('\n')
            const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' })
            const url = window.URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url; a.download = `payments_${dayjs().format('YYYYMMDD')}.csv`; a.click()
            window.URL.revokeObjectURL(url)
          } catch { /* ignore */ }
          setExporting(false)
        }}>匯出 CSV</Button>
      </Space>

      <Table
        dataSource={payments} columns={columns} rowKey="id" loading={loading}
        pagination={{ current: page, pageSize: 20, total: meta?.total ?? 0, onChange: setPage, showTotal: t => `共 ${t} 筆` }}
        size="middle" locale={{ emptyText: '目前無支付記錄' }} scroll={{ x: 1100 }}
        rowClassName={(_r: Payment) => ''}
      />

      {/* Detail Modal */}
      <Modal
        title={`訂單詳情 — ${detailRecord?.order_no ?? ''}`}
        open={!!detailRecord}
        onCancel={() => setDetailRecord(null)}
        footer={isSuperAdmin && detailRecord?.status === 'paid' && !detailRecord?.refunded_at ? (
          <Button danger loading={refunding} onClick={() => detailRecord && handleManualRefund(detailRecord)}>
            手動退款
          </Button>
        ) : null}
        width={680}
      >
        {detailRecord && (
          <div>
            {detailRecord.failure_reason && (
              <Alert type="error" showIcon message={`付款失敗：${detailRecord.failure_reason}`} style={{ marginBottom: 12 }} />
            )}

            <Descriptions column={2} bordered size="small">
              <Descriptions.Item label="訂單類型" span={2}>
                <Tag color={TYPE_COLOR[detailRecord.type] ?? 'default'}>{TYPE_LABEL[detailRecord.type] ?? detailRecord.type}</Tag>
                <Tag color={ENV_COLOR[detailRecord.environment] ?? 'default'} style={{ marginLeft: 8 }}>
                  {ENV_LABEL[detailRecord.environment] ?? detailRecord.environment}
                </Tag>
              </Descriptions.Item>
              <Descriptions.Item label="訂單編號" span={2}><Text copyable>{detailRecord.order_no}</Text></Descriptions.Item>
              <Descriptions.Item label="項目" span={2}>{detailRecord.item_name ?? '-'}</Descriptions.Item>
              <Descriptions.Item label="用戶">{detailRecord.user?.nickname ?? '-'}</Descriptions.Item>
              <Descriptions.Item label="Email">{detailRecord.user?.email ?? '-'}</Descriptions.Item>
              <Descriptions.Item label="金額">NT$ {(detailRecord.amount || 0).toLocaleString()}</Descriptions.Item>
              <Descriptions.Item label="狀態">
                <Tag color={STATUS_COLOR[detailRecord.status] || 'default'}>{STATUS_LABEL[detailRecord.status] || detailRecord.status}</Tag>
              </Descriptions.Item>
              <Descriptions.Item label="付款方式">{detailRecord.payment_method ?? '-'}</Descriptions.Item>
              <Descriptions.Item label="發卡國">{detailRecord.card_country ?? '-'}</Descriptions.Item>
              <Descriptions.Item label="建立時間">{detailRecord.created_at ? dayjs(detailRecord.created_at).format('YYYY/MM/DD HH:mm:ss') : '-'}</Descriptions.Item>
              <Descriptions.Item label="付款完成時間">{detailRecord.paid_at ? dayjs(detailRecord.paid_at).format('YYYY/MM/DD HH:mm:ss') : '-'}</Descriptions.Item>
              {detailRecord.refunded_at && (
                <Descriptions.Item label="退款時間">{dayjs(detailRecord.refunded_at).format('YYYY/MM/DD HH:mm:ss')}</Descriptions.Item>
              )}
              {detailRecord.refund_trade_no && (
                <Descriptions.Item label="退款序號">{detailRecord.refund_trade_no}</Descriptions.Item>
              )}
              <Descriptions.Item label="綠界交易序號" span={2}>
                {detailRecord.gateway_trade_no ? <Text copyable>{detailRecord.gateway_trade_no}</Text> : <Text type="secondary">尚無</Text>}
              </Descriptions.Item>
              <Descriptions.Item label="發票" span={2}>
                {!detailRecord.invoice_applicable
                  ? <Tag color="default">不適用（驗證身份，不開立發票）</Tag>
                  : detailRecord.invoice_no
                    ? <Tag color="blue">{detailRecord.invoice_no}（開立時間：{detailRecord.invoice_issued_at ? dayjs(detailRecord.invoice_issued_at).format('YYYY/MM/DD HH:mm') : '-'}）</Tag>
                    : detailRecord.status === 'paid'
                      ? <Tag color="orange">已付款但尚未開立（請確認發票設定）</Tag>
                      : <Text type="secondary">-</Text>
                }
              </Descriptions.Item>
            </Descriptions>

            {/* Raw Callback 折疊區（後端 API 目前不回傳，保留 UI 骨架）*/}
          </div>
        )}
      </Modal>

      <style>{`.row-warning td { background: #fff1f0 !important; }`}</style>
    </div>
  )
}
