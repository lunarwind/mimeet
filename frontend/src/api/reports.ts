/**
 * api/reports.ts
 * 回報/檢舉相關 API
 * 對應 API-001 §8 / §10.4
 */
import client from './client'

const USE_MOCK = import.meta.env.VITE_USE_MOCK === 'true'

function delay(ms: number) {
  return new Promise(resolve => setTimeout(resolve, ms))
}

export interface CreateReportPayload {
  type: number
  reportedUserId?: number
  title: string
  content: string
  images: string[]
}

export interface ReportRecord {
  id: number
  ticketNumber: string
  type: number
  typeLabel: string
  title: string
  status: number
  statusLabel: string
  adminReply: string | null
  createdAt: string
  processedAt: string | null
}

const TYPE_LABELS: Record<number, string> = {
  1: '騷擾或不當訊息',
  2: '假冒身份',
  3: '詐騙行為',
  4: '不雅照片或內容',
  5: '其他',
}

const STATUS_LABELS: Record<number, string> = {
  1: '處理中',
  2: '已處理',
}

export async function createReport(payload: CreateReportPayload): Promise<{ ticketNumber: string }> {
  if (USE_MOCK) {
    await delay(800)
    const now = new Date()
    const dateStr = now.toISOString().slice(0, 10).replace(/-/g, '')
    const rand = String(Math.floor(Math.random() * 99999)).padStart(5, '0')
    return { ticketNumber: `REPORT-${dateStr}-${rand}` }
  }

  const res = await client.post<{
    data: { report: { report_number: string } }
  }>('/reports', payload)
  return { ticketNumber: res.data.data.report.report_number }
}

export async function fetchReportHistory(): Promise<ReportRecord[]> {
  if (USE_MOCK) {
    await delay(400)
    return [
      {
        id: 1, ticketNumber: 'REPORT-20260401-00001', type: 1,
        typeLabel: TYPE_LABELS[1] ?? '', title: '對方傳送騷擾訊息',
        status: 2, statusLabel: STATUS_LABELS[2] ?? '',
        adminReply: '經查證屬實，已對違規用戶進行處理',
        createdAt: new Date(Date.now() - 5 * 86400000).toISOString(),
        processedAt: new Date(Date.now() - 3 * 86400000).toISOString(),
      },
      {
        id: 2, ticketNumber: 'REPORT-20260405-00002', type: 3,
        typeLabel: TYPE_LABELS[3] ?? '', title: '疑似詐騙行為',
        status: 1, statusLabel: STATUS_LABELS[1] ?? '',
        adminReply: null,
        createdAt: new Date(Date.now() - 1 * 86400000).toISOString(),
        processedAt: null,
      },
    ]
  }

  const res = await client.get<{
    data: { reports: ReportRecord[] }
  }>('/reports/history')
  return res.data.data.reports
}
