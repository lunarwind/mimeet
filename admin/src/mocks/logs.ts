import dayjs from 'dayjs'

export type ActionType =
  | 'adjust_credit' | 'suspend' | 'unsuspend'
  | 'set_level' | 'settings_change' | 'ticket_process'

export const actionMeta: Record<ActionType, { label: string; color: string }> = {
  adjust_credit:   { label: '調整分數',   color: 'orange' },
  suspend:         { label: '停權',       color: 'red' },
  unsuspend:       { label: '解除停權',   color: 'green' },
  set_level:       { label: '調整等級',   color: 'blue' },
  settings_change: { label: '設定變更',   color: 'purple' },
  ticket_process:  { label: 'Ticket處理', color: 'cyan' },
}

const actionKeys = Object.keys(actionMeta) as ActionType[]
const admins = [
  { id: 1, name: 'Super Admin', email: 'super@mimeet.tw', role: 'super_admin' },
  { id: 2, name: 'Admin 小明',  email: 'admin@mimeet.tw', role: 'admin' },
]

export interface LogEntry {
  id: number
  admin: typeof admins[0]
  action_type: ActionType
  description: string
  target_user_nickname: string | null
  ip_address: string
  created_at: string
}

const templates = [
  (uid: number) => `調整用戶 #${uid} 誠信分數 -10，原因：違規發送廣告訊息`,
  (uid: number) => `停權用戶 #${uid}，原因：多次被檢舉屬實`,
  (uid: number) => `解除停權用戶 #${uid}，申訴審核通過`,
  (uid: number) => `調整用戶 #${uid} 會員等級為 Lv3`,
  (_: number)   => `變更系統設定 date_verify.score_with_gps 從 3 改為 5`,
  (uid: number) => `處理 Ticket #TK-${uid}，狀態 → resolved，檢舉屬實`,
]

export const mockLogs: LogEntry[] = Array.from({ length: 100 }, (_, i) => {
  const action = actionKeys[i % actionKeys.length]
  const uid = 1000 + (i * 7 % 500)
  return {
    id: 9000 + i,
    admin: admins[i % 2],
    action_type: action,
    description: templates[i % templates.length](uid),
    target_user_nickname: action !== 'settings_change' ? `用戶${uid}` : null,
    ip_address: `203.74.${(i * 3) % 256}.${(i * 7) % 256}`,
    created_at: dayjs().subtract(i * 28, 'minute').toISOString(),
  }
})
