/**
 * Mask phone number (Taiwan format).
 * 規則對齊 backend `\App\Support\Mask::phone()`，PR-1 v3.6 ship 過實測：
 *   '0912345678'    → '09xx-xxx-678'
 *   '+886912345678' → '09xx-xxx-678'
 *   '0987654321'    → '09xx-xxx-321'
 *   '0223456789'    → '022****789' (length 6-9 fallback)
 *
 * ⚠️ 不要對 backend 已 masked 的字串呼叫此 function（會 double-mask）。
 *    backend 在 register/login/me response 的 phone 欄位已經 masked。
 *    本 helper 只用於前端 user 輸入的 raw phone 顯示用 mask。
 */
export function maskPhone(phone?: string | null): string {
  if (!phone) return ''
  const local = phone.startsWith('+886') ? '0' + phone.substring(4) : phone
  if (local.length === 10) {
    return local.substring(0, 2) + 'xx-xxx-' + local.substring(local.length - 3)
  }
  if (local.length >= 6) {
    return local.substring(0, 3) + '****' + local.substring(local.length - 3)
  }
  return local
}

/**
 * Mask email (PR-2 v4.2 對齊規則).
 *   'chuck@example.com' → 'c***k@example.com'
 *   'ab@example.com'    → '***@example.com' (local ≤ 2)
 */
export function maskEmail(email?: string | null): string {
  if (!email || !email.includes('@')) return ''
  const parts = email.split('@')
  const local = parts[0] ?? ''
  const domain = parts[1] ?? ''
  if (!local || !domain) return ''
  if (local.length <= 2) return `***@${domain}`
  return `${local[0]}***${local[local.length - 1]}@${domain}`
}
