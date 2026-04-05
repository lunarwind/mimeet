// src/utils/validators.ts
// 表單驗證共用規則，供所有 View 共用

// ── Email ────────────────────────────────────
export function validateEmail(value: string): string {
  if (!value.trim()) return '此欄位為必填'
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Email 格式不正確'
  return ''
}

// ── 密碼 ─────────────────────────────────────
export function validatePassword(value: string): string {
  if (!value) return '此欄位為必填'
  if (value.length < 8) return '密碼至少 8 個字元'
  return ''
}

export function validatePasswordConfirm(value: string, original: string): string {
  if (!value) return '此欄位為必填'
  if (value !== original) return '兩次密碼不一致'
  return ''
}

// ── 手機號碼（台灣格式）──────────────────────
export function validatePhone(value: string): string {
  if (!value.trim()) return '此欄位為必填'
  if (!/^09\d{8}$/.test(value)) return '格式應為 09xxxxxxxx'
  return ''
}

// ── 暱稱 ─────────────────────────────────────
export function validateNickname(value: string): string {
  if (!value.trim()) return '此欄位為必填'
  if (value.trim().length < 2) return '暱稱至少 2 個字'
  if (value.trim().length > 20) return '暱稱最多 20 個字'
  return ''
}

// ── 生日（需年滿 18 歲）──────────────────────
export function validateBirthDate(year: string, month: string, day: string): string {
  if (!year || !month || !day) return '請填寫完整生日'
  const birth = new Date(
    `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  )
  if (isNaN(birth.getTime())) return '日期格式不正確'
  const today = new Date()
  const age =
    today.getFullYear() -
    birth.getFullYear() -
    (today < new Date(today.getFullYear(), birth.getMonth(), birth.getDate()) ? 1 : 0)
  if (age < 18) return '未滿 18 歲無法加入'
  return ''
}

// ── 必填欄位 ──────────────────────────────────
export function validateRequired(value: string, label = '此欄位'): string {
  if (!value.trim()) return `${label}為必填`
  return ''
}

// ── 密碼強度計算 ──────────────────────────────
export interface PasswordStrength {
  score: number        // 0-5
  label: '弱' | '中' | '強' | ''
  color: string
}

export function getPasswordStrength(password: string): PasswordStrength {
  if (!password) return { score: 0, label: '', color: '#E5E7EB' }
  let score = 0
  if (password.length >= 8) score++
  if (password.length >= 12) score++
  if (/[A-Z]/.test(password)) score++
  if (/[0-9]/.test(password)) score++
  if (/[^A-Za-z0-9]/.test(password)) score++

  if (score <= 1) return { score, label: '弱', color: '#EF4444' }
  if (score <= 3) return { score, label: '中', color: '#F59E0B' }
  return { score, label: '強', color: '#10B981' }
}

// ── OTP 6 位數 ────────────────────────────────
export function validateOtp(digits: string[]): string {
  if (digits.some(d => !d)) return '請輸入完整驗證碼'
  if (!/^\d{6}$/.test(digits.join(''))) return '驗證碼格式不正確'
  return ''
}

// ── 通用：批次驗證，回傳第一個錯誤 ─────────────
export function runValidations(
  rules: Array<() => string>
): string {
  for (const rule of rules) {
    const err = rule()
    if (err) return err
  }
  return ''
}
