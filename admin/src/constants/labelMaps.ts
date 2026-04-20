/**
 * admin/src/constants/labelMaps.ts
 * F27 profile 欄位中英對照表（後台專用）
 */

export const STYLE_LABELS: Record<string, string> = {
  fresh: '清新',
  sweet: '甜美',
  sexy: '性感',
  intellectual: '知性',
  sporty: '運動',
}

export const DATING_BUDGET_LABELS: Record<string, string> = {
  casual: '輕鬆小聚',
  moderate: '質感約會',
  generous: '高品質體驗',
  luxury: '頂級享受',
  undisclosed: '不透露',
}

export const DATING_FREQUENCY_LABELS: Record<string, string> = {
  occasional: '偶爾見面',
  weekly: '每週約會',
  flexible: '看心情',
}

export const DATING_TYPE_LABELS: Record<string, string> = {
  dining: '餐敘',
  travel: '旅遊',
  companion: '陪伴',
  mentorship: '指導',
  undisclosed: '不透露',
}

export const RELATIONSHIP_GOAL_LABELS: Record<string, string> = {
  short_term: '短期約會',
  long_term: '長期穩定',
  open: '開放探索',
  undisclosed: '不透露',
}

export const SMOKING_LABELS: Record<string, string> = {
  never: '從不',
  sometimes: '偶爾',
  often: '經常',
}

export const DRINKING_LABELS: Record<string, string> = {
  never: '從不',
  social: '社交場合',
  often: '經常',
}

export const AVAILABILITY_LABELS: Record<string, string> = {
  weekday_day: '平日白天',
  weekday_night: '平日晚上',
  weekend: '週末',
  flexible: '彈性配合',
}

export const EDUCATION_LABELS: Record<string, string> = {
  high_school: '高中 / 高職',
  associate: '專科',
  bachelor: '大學',
  master: '碩士',
  phd: '博士',
  other: '其他',
}

/** 通用 helper：取 label，找不到就回原 value，null/空字串回「未填寫」 */
export function formatLabel(value: string | null | undefined, map: Record<string, string>): string {
  if (!value) return '未填寫'
  return map[value] ?? value
}
