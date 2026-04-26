/**
 * ECPay AIO 付款跳轉 helper
 *
 * 用法：
 *   const { aio_url, params } = await initiateCreditCardVerification()
 *   redirectToECPay(aio_url, params)
 *
 * 原理：動態建立 hidden form 並 POST 到 ECPay，
 * 比 window.location.href 更符合 ECPay AIO 規範（GET 無法帶大量參數）。
 */
export function redirectToECPay(
  aioUrl: string,
  params: Record<string, string | number>,
): void {
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = aioUrl
  form.style.display = 'none'
  form.acceptCharset = 'UTF-8'

  Object.entries(params).forEach(([key, value]) => {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = key
    input.value = String(value)
    form.appendChild(input)
  })

  document.body.appendChild(form)
  form.submit()
}
