/**
 * 約會偏好選項（F27）
 * dating_type: 4 個值（dining / companion / consultation / undisclosed）
 * dating_budget: 7 個值，UI 用 optgroup 分「單次見面」「長期月費」
 */

export interface SimpleOption {
  readonly value: string
  readonly label: string
}

export interface OptionGroup {
  readonly label: string
  readonly options: readonly SimpleOption[]
}

/** dating_type — 可複選 */
export const DATING_TYPE_OPTIONS: readonly SimpleOption[] = [
  { value: 'dining', label: '餐敘' },
  { value: 'companion', label: '伴遊' },
  { value: 'consultation', label: '諮商' },
  { value: 'intimate', label: '親密' },
  { value: 'undisclosed', label: '不透露' },
] as const

/** dating_budget — 單選，UI 用 optgroup */
export const DATING_BUDGET_GROUPS: readonly OptionGroup[] = [
  {
    label: '單次見面',
    options: [
      { value: 'single_under_8k', label: '8K 以下' },
      { value: 'single_8k_12k', label: '8K～12K' },
      { value: 'single_above_12k', label: '12K 以上' },
    ],
  },
  {
    label: '長期月費',
    options: [
      { value: 'long_under_40k', label: '40K 以下' },
      { value: 'long_40k_60k', label: '40K～60K' },
      { value: 'long_above_60k', label: '60K 以上' },
    ],
  },
] as const

export const DATING_BUDGET_UNDISCLOSED: SimpleOption = { value: 'undisclosed', label: '不透露' }
