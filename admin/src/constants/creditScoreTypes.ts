/**
 * CreditScoreHistory.type 枚舉值的中文對照與視覺樣式定義
 *
 * 對應 docs/DEV-008_誠信分數系統規格書.md §10.3 的 14 個 type 枚舉值。
 *
 * ⚠️ 維護注意：
 * 1. 新增 type 時，必須同步更新此檔案 + DEV-008 §10.3 + 後端寫入點
 * 2. 顏色設計原則：加分（系統）綠 / 加分（管理員）金 / 退還藍 / 扣分（系統）紅 / 扣分（管理員）朱
 * 3. 後端 type 改名時，pre-merge-check 14i 會攔截舊枚舉值
 */

export type CreditScoreType =
  | 'email_verify'
  | 'phone_verify'
  | 'adv_verify_male'
  | 'adv_verify_female'
  | 'date_gps'
  | 'date_no_gps'
  | 'date_noshow'
  | 'report_submit'
  | 'report_result_refund'
  | 'report_result_penalty'
  | 'admin_reward'
  | 'admin_penalty'
  | 'content_violation'
  | 'appeal_refund'

export interface CreditScoreTypeMeta {
  label: string
  color: string // Ant Design Tag color
}

// Record<CreditScoreType, ...> 利用 TypeScript exhaustiveness check：
// 新增 CreditScoreType union 成員但忘了加對照表時，編譯器會報錯。
export const CREDIT_SCORE_TYPE_META: Record<CreditScoreType, CreditScoreTypeMeta> = {
  // 加分（系統自動觸發）
  email_verify:          { label: 'Email 驗證',       color: 'green' },
  phone_verify:          { label: '手機驗證',         color: 'green' },
  adv_verify_male:       { label: '男性進階驗證',     color: 'green' },
  adv_verify_female:     { label: '女性進階驗證',     color: 'green' },
  date_gps:              { label: '約會驗證（GPS）',  color: 'green' },
  date_no_gps:           { label: '約會驗證（無GPS）',color: 'green' },

  // 扣分（系統自動觸發）
  date_noshow:           { label: '約會爽約',         color: 'red' },
  report_submit:         { label: '提交檢舉',         color: 'red' },
  content_violation:     { label: '內容違規',         color: 'red' },
  report_result_penalty: { label: '檢舉處分',         color: 'red' },

  // 管理員觸發（加/扣分區隔顏色）
  admin_reward:          { label: '管理員獎勵',       color: 'gold' },
  admin_penalty:         { label: '管理員懲罰',       color: 'volcano' },

  // 中性退還（檢舉/申訴歸還，非獎勵）
  report_result_refund:  { label: '檢舉退分',         color: 'blue' },
  appeal_refund:         { label: '申訴補分',         color: 'blue' },
}

/**
 * 取得 type 對應的顯示元素資料。
 * 未知 type 回傳原始字串 + default 樣式，並以 console.warn 提示維護者。
 */
export function getCreditScoreTypeMeta(type: string): CreditScoreTypeMeta {
  const meta = CREDIT_SCORE_TYPE_META[type as CreditScoreType]

  if (!meta) {
    console.warn(
      `[CreditScore] Unknown type: "${type}". ` +
      '請檢查 DEV-008 §10.3 是否新增枚舉但未更新 admin/src/constants/creditScoreTypes.ts'
    )
    return { label: type, color: 'default' }
  }

  return meta
}
