/**
 * api/dnd.ts — F22 Part B 全域免打擾設定
 * 對應 API-001 §10.12
 */
import client from './client'

export interface DndSetting {
  dndEnabled: boolean
  dndStart: string | null   // "HH:MM"
  dndEnd: string | null
  currentlyActive: boolean
}

function mapResponse(raw: any): DndSetting {
  return {
    dndEnabled: !!raw?.dnd_enabled,
    dndStart: raw?.dnd_start ?? null,
    dndEnd: raw?.dnd_end ?? null,
    currentlyActive: !!raw?.currently_active,
  }
}

export async function getDnd(): Promise<DndSetting> {
  const res = await client.get('/me/dnd')
  return mapResponse(res.data?.data ?? {})
}

export async function updateDnd(payload: {
  dndEnabled: boolean
  dndStart: string | null
  dndEnd: string | null
}): Promise<DndSetting> {
  const res = await client.patch('/me/dnd', {
    dnd_enabled: payload.dndEnabled,
    dnd_start: payload.dndStart,
    dnd_end: payload.dndEnd,
  })
  return mapResponse(res.data?.data ?? {})
}

/** 判斷目前本地時間是否在 DND 時段內（前端通知決策用）*/
export function isNowInDndPeriod(s: Pick<DndSetting, 'dndEnabled' | 'dndStart' | 'dndEnd'>): boolean {
  if (!s.dndEnabled || !s.dndStart || !s.dndEnd) return false
  const now = new Date()
  const hm = now.toTimeString().slice(0, 5) // "HH:MM"
  const start = s.dndStart
  const end = s.dndEnd
  if (start > end) {
    return hm >= start || hm < end
  }
  return hm >= start && hm < end
}
