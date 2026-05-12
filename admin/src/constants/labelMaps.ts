/**
 * admin/src/constants/labelMaps.ts
 * F27 profile 欄位中英對照表（後台專用）
 */

// 女性風格（9）
export const FEMALE_STYLE_KEYS = ['fresh', 'sweet', 'sexy', 'intellectual', 'sporty', 'elegant', 'korean', 'pure_student', 'petite_japanese'] as const

// 男性風格（9）
export const MALE_STYLE_KEYS = ['business_elite', 'british_gentleman', 'smart_casual', 'outdoor', 'boy_next_door', 'minimalist', 'japanese', 'warm_guy', 'preppy'] as const

export const STYLE_LABELS: Record<string, string> = {
  // 女性
  fresh: '清新',
  sweet: '甜美',
  sexy: '性感',
  intellectual: '知性',
  sporty: '運動',
  elegant: '優雅',
  korean: '韓系',
  pure_student: '學院',
  petite_japanese: '日系',
  // 男性
  business_elite: '商務菁英',
  british_gentleman: '英倫紳士',
  smart_casual: '休閒正裝',
  outdoor: '戶外運動',
  boy_next_door: '鄰家男孩',
  minimalist: '極簡',
  japanese: '日系',
  warm_guy: '暖男',
  preppy: '學院風',
}

export const DATING_BUDGET_LABELS: Record<string, string> = {
  single_under_8k: '單次 8K 以下',
  single_8k_12k: '單次 8K～12K',
  single_above_12k: '單次 12K 以上',
  long_under_40k: '長期 月 40K 以下',
  long_40k_60k: '長期 月 40K～60K',
  long_above_60k: '長期 月 60K 以上',
  undisclosed: '不透露',
}

export const DATING_FREQUENCY_LABELS: Record<string, string> = {
  occasional: '偶爾見面',
  weekly: '每週約會',
  flexible: '看心情',
}

export const DATING_TYPE_LABELS: Record<string, string> = {
  dining: '餐敘',
  companion: '伴遊',
  consultation: '諮商',
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
