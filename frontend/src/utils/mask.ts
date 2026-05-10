/**
 * Mask phone number (Taiwan format).
 * 規則對齊 backend `\App\Support\Mask::phone()`：
 *   '0912345678'    → '09xx-xxx-678'
 *   '+886912345678' → '09xx-xxx-678'
 *   '0987654321'    → '09xx-xxx-321'
 *   '0223456789'    → '022****789' (length 6-9 fallback)
 *
 * @deprecated PR-4 (2026-05-08) — 目前無 caller。
 *   Backend response 對「user 看自己」endpoint 已改 raw（API-001 §Phone 欄位 mask 原則）；
 *   「user 看別人」場景目前不存在。此 helper 保留以備未來需求。
 *   若新增 caller，請同時 review backend response 是否該保持 raw。
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
 *
 * @deprecated PR-4 (2026-05-08) — 同 maskPhone() 同邏輯,目前無 caller。
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
