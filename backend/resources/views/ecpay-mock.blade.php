<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>綠界科技 — 信用卡付款（測試環境）</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #F3F4F6; min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 16px;
    }
    .card {
      background: white; border-radius: 16px;
      padding: 32px; max-width: 420px; width: 100%;
      box-shadow: 0 4px 24px rgba(0,0,0,0.10);
    }
    .header { text-align: center; margin-bottom: 24px; }
    .header__logo { font-size: 22px; font-weight: 700; color: #1a7f37; margin-bottom: 4px; }
    .header__sub { font-size: 13px; color: #6B7280; }
    .sandbox-badge {
      display: inline-block; background: #FEF3C7; color: #92400E;
      font-size: 11px; font-weight: 600; padding: 3px 10px;
      border-radius: 9999px; margin-bottom: 16px;
    }
    .amount-box {
      background: #F9FAFB; border-radius: 10px; padding: 16px;
      text-align: center; margin-bottom: 24px; border: 1px solid #E5E7EB;
    }
    .amount-box__label { font-size: 13px; color: #6B7280; }
    .amount-box__value { font-size: 28px; font-weight: 700; color: #111827; }
    .field { margin-bottom: 16px; }
    .field label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .field input {
      width: 100%; padding: 10px 14px; border: 1.5px solid #D1D5DB;
      border-radius: 8px; font-size: 16px; color: #111827;
      outline: none; transition: border-color 0.15s;
    }
    .field input:focus { border-color: #1a7f37; }
    .field-row { display: flex; gap: 12px; }
    .field-row .field { flex: 1; }
    .test-hint {
      background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px;
      padding: 10px 12px; font-size: 12px; color: #1E40AF;
      margin-bottom: 20px; line-height: 1.6;
    }
    .test-hint strong { display: block; margin-bottom: 4px; }
    .btn-pay {
      display: block; width: 100%; padding: 14px; background: #1a7f37;
      color: white; border: none; border-radius: 10px; font-size: 16px;
      font-weight: 700; cursor: pointer; margin-bottom: 12px;
      text-align: center; text-decoration: none; transition: background 0.15s;
    }
    .btn-pay:hover { background: #166534; }
    .btn-cancel {
      display: block; width: 100%; padding: 12px; background: white;
      color: #6B7280; border: 1.5px solid #E5E7EB; border-radius: 10px;
      font-size: 14px; cursor: pointer; text-align: center; text-decoration: none;
    }
    .footer { text-align: center; margin-top: 20px; font-size: 11px; color: #9CA3AF; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <div class="header__logo">綠界科技 ECPay</div>
      <div class="header__sub">安全付款服務</div>
    </div>

    <div style="text-align:center">
      <span class="sandbox-badge">⚠️ 測試環境 — 不會真實扣款</span>
    </div>

    <div class="amount-box">
      <div class="amount-box__label">付款金額</div>
      <div class="amount-box__value">NT$ {{ $amount }}</div>
    </div>

    <div class="test-hint">
      <strong>📋 測試信用卡資料（已自動填入）</strong>
      卡號：4311-9522-2222-2222<br>
      有效期：12/26 &nbsp;|&nbsp; CVV：222
    </div>

    <div class="field">
      <label>信用卡卡號</label>
      <input type="text" value="4311-9522-2222-2222" maxlength="19" readonly>
    </div>

    <div class="field-row">
      <div class="field">
        <label>有效期限</label>
        <input type="text" value="12/26" maxlength="5" readonly>
      </div>
      <div class="field">
        <label>安全碼 CVV</label>
        <input type="text" value="222" maxlength="3" readonly>
      </div>
    </div>

    <a class="btn-pay" href="{!! $confirmUrl !!}">確認付款</a>
    <a class="btn-cancel" href="{!! $cancelUrl !!}">取消</a>

    <div class="footer">此為測試環境，信用卡資料不會被儲存或傳送至銀行</div>
  </div>
</body>
</html>
