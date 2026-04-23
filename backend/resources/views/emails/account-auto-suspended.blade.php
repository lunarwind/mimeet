<!DOCTYPE html>
<html lang="zh-TW">
<head><meta charset="UTF-8"><title>帳號自動停權通知</title></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 20px;">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;">
        <tr><td style="background:#F0294E;padding:24px 32px;">
          <span style="color:#fff;font-size:22px;font-weight:700;">MiMeet</span>
        </td></tr>
        <tr><td style="padding:40px 32px;">
          <p style="font-size:16px;color:#111827;margin:0 0 8px;">嗨，{{ $user->nickname }}！</p>
          <p style="font-size:14px;color:#6B7280;margin:0 0 24px;">您的帳號因誠信分數歸零已被系統自動停權，目前無法使用 MiMeet 的服務。</p>
          <p style="font-size:14px;color:#6B7280;margin:0 0 24px;">若您認為此停權有誤，或希望申訴，請登入後至「停權申訴」頁面提交申請，我們將在 3 個工作天內回覆。</p>
          <p style="font-size:13px;color:#9CA3AF;margin:0;">若您未進行任何違規行為，請聯絡客服協助處理。</p>
        </td></tr>
        <tr><td style="background:#F9FAFB;padding:20px 32px;border-top:1px solid #E5E7EB;">
          <p style="font-size:12px;color:#9CA3AF;margin:0;">您收到此信是因為您在 MiMeet 有帳號。</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
