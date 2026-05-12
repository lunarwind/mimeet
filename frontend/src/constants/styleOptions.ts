/**
 * 自我風格選項（F27）
 * 男女互斥兩組 enum，UI 依用戶 / 篩選性別動態切換可選範圍。
 * 後端寫入時 gender-strict 驗證；搜尋／廣播 filter 接受全 18 個。
 */

export interface StyleOption {
  readonly value: string
  readonly label: string
}

export const FEMALE_STYLE_OPTIONS: readonly StyleOption[] = [
  { value: 'fresh', label: '清新' },
  { value: 'sweet', label: '甜美' },
  { value: 'sexy', label: '性感' },
  { value: 'intellectual', label: '知性' },
  { value: 'sporty', label: '運動' },
  { value: 'elegant', label: '優雅' },
  { value: 'korean', label: '韓系' },
  { value: 'pure_student', label: '學院' },
  { value: 'petite_japanese', label: '日系' },
] as const

export const MALE_STYLE_OPTIONS: readonly StyleOption[] = [
  { value: 'business_elite', label: '商務菁英' },
  { value: 'british_gentleman', label: '英倫紳士' },
  { value: 'smart_casual', label: '休閒正裝' },
  { value: 'outdoor', label: '戶外運動' },
  { value: 'boy_next_door', label: '鄰家男孩' },
  { value: 'minimalist', label: '極簡' },
  { value: 'japanese', label: '日系' },
  { value: 'warm_guy', label: '暖男' },
  { value: 'preppy', label: '學院風' },
] as const

export const ALL_STYLE_OPTIONS: readonly StyleOption[] = [
  ...FEMALE_STYLE_OPTIONS,
  ...MALE_STYLE_OPTIONS,
]

export type StyleGender = 'male' | 'female' | '' | null | undefined

/**
 * 依 gender 取對應風格選項。
 * gender 為空 / null / undefined 時回傳全 18 個（用於未選性別篩選的搜尋情境）。
 */
export function getStyleOptionsByGender(gender: StyleGender): readonly StyleOption[] {
  if (gender === 'male') return MALE_STYLE_OPTIONS
  if (gender === 'female') return FEMALE_STYLE_OPTIONS
  return ALL_STYLE_OPTIONS
}
