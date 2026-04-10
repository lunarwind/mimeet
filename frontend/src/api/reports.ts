/**
 * api/reports.ts
 * 回報/檢舉相關 API
 * 對應 API-001 §8 / §10.4
 */
import client from './client'

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

export async function createReport(payload: CreateReportPayload): Promise<{ ticketNumber: string }> {
  const res = await client.post<{
    data: { report: { report_number: string } }
  }>('/reports', { data: payload })
  return { ticketNumber: res.data.data.report.report_number }
}

export async function fetchReportHistory(): Promise<ReportRecord[]> {
  const res = await client.get<{
    data: { reports: ReportRecord[] }
  }>('/reports/history')
  return res.data.data.reports
}
